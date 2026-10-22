<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Entra ID Accounts and Assigned Access
 *  Technology: Microsoft Entra ID
 *  id: entra-account-review        version: 0.1.1 (draft)
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Account%20Reviews
 *
 *  Composer packages (paste in the automation's composer field):
 *    guzzlehttp/guzzle:^7.9
 * ============================================================================
 *
 *  Output
 *    STDOUT  Reserved for the Account Review artifact declaration written by
 *            writeAccountReviewArtifact(). Nothing else is ever printed there.
 *    STDERR  Step-by-step log with counts only, and technical errors.
 *            Account Review feeds accept STDERR when the run exits 0.
 *
 *  Exit codes
 *    0  Complete account list collected and published.
 *    1  Technical error (credentials, network, permissions, limits). Nothing
 *       is published, so the pull fails instead of returning a partial list.
 *
 *  Read-only: the script never changes Entra ID and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'ENTRA_TENANT_ID' => '%SECRET_entra_tenant_id%',
    'ENTRA_CLIENT_ID' => '%SECRET_entra_client_id%',
    'ENTRA_CLIENT_SECRET_B64' => '%SECRET_entra_client_secret_b64%', // Base64 of the client secret, shared with the other Entra automations
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'userPrincipalName', // Account identifier: userPrincipalName, id or mail (no fallback)
    'USER_IDS' => [], // Empty: whole tenant. Otherwise a list of Entra user object IDs
    'ROLES' => ['groups', 'directory_roles', 'applications', 'licenses'], // Access listed as roles; [] = accounts only (README §7)
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum accounts in the feed; never truncate
    'MAX_OBJECTS' => 2000, // Maximum records per API collection; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'entra-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const TPL_ALLOWED_REQUESTS = [
    'POST https://login.microsoftonline.com/', // Token
    'GET https://graph.microsoft.com/v1.0/',
    'POST https://graph.microsoft.com/v1.0/$batch', // Read requests grouped 20 at a time
];

// ─── 4. SHARED CODE (copied from Automation/_shared, do not edit here) ──────
// ─── shared:core v1 (edit Automation/_shared/core.php, then run Automation/tools/sync.php) ───
/**
 * Turns every PHP warning or notice into an exception, so it fails the run
 * instead of being printed on stdout (where it would corrupt a feed or hide a problem).
 * Call it first in MAIN.
 */
function tpl_installErrorHandler(): void
{
    ini_set('display_errors', 'stderr');
    set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
}

/** Step log. The script chooses the stream: 'stdout' for control audits, 'stderr' for feeds. */
function tpl_logStep(int $n, string $title): void
{
    tpl_log(sprintf("\n[%s] STEP %d: %s", gmdate('H:i:s'), $n, $title));
}

function tpl_logInfo(string $message): void
{
    tpl_log('  ' . $message);
}

function tpl_log(string $line): void
{
    fwrite(TPL_LOG_STREAM === 'stdout' ? STDOUT : STDERR, $line . "\n");
}

/** Seconds since the first call. MAIN calls it once at start. */
function tpl_elapsed(): float
{
    static $start = null;
    $start ??= microtime(true);
    return microtime(true) - $start;
}

/** Seconds left of TPL_RUN_SECONDS; throws when the time or memory budget is spent. */
function tpl_remaining(): float
{
    $remaining = TPL_RUN_SECONDS - tpl_elapsed();
    if ($remaining <= 0) {
        throw new RuntimeException('Time budget exceeded; narrow the scope.');
    }
    if (memory_get_usage(true) > 48 * 1024 * 1024) {
        throw new RuntimeException('Memory budget exceeded; narrow the scope.');
    }
    return $remaining;
}

/** A secret that eramba did not replace is still "%SECRET_<name>%": it does not exist. */
function tpl_checkSecrets(array $secrets): void
{
    foreach ($secrets as $key => $value) {
        if (!is_string($value) || $value === '' || preg_match('/^%SECRET_([^%]+)%$/', $value, $m) === 1) {
            $secretName = $m[1] ?? strtolower((string) $key);
            throw new RuntimeException(
                "Secret '$secretName' is missing: create it with exactly that name in Settings / Application Configuration / Automation Secrets (see README §6)."
            );
        }
    }
}

/** Secrets that may contain quotes or backslashes are stored base64-encoded (encoding, not encryption). */
function tpl_base64Secret(string $value, string $name): string
{
    $decoded = base64_decode($value, true);
    if ($decoded === false || $decoded === '' || strlen($decoded) > 8192 || base64_encode($decoded) !== $value) {
        throw new RuntimeException("Secret '$name' is not valid single-line base64.");
    }
    return $decoded;
}
// ─── end shared:core v1 sha256:277e2f6c5d57b964 ───
// ─── shared:http v2 (edit Automation/_shared/http.php, then run Automation/tools/sync.php) ───
/**
 * One JSON request to a provider API. Returns ['status' => int, 'headers' => [lower-case name => value], 'data' => array].
 *
 * - Only "METHOD https://host/path" combinations starting with an entry of TPL_ALLOWED_REQUESTS are sent,
 *   so the script cannot write to the provider or send its token to another host.
 * - TLS verification on, redirects off, timeouts capped to the remaining run budget.
 * - 429/503/504, and 403 with X-RateLimit-Remaining: 0 (GitHub's rate limit), are retried up to 3 attempts, waiting what the provider asks (Retry-After, X-Rate-Limit-Reset
 *   or X-RateLimit-Reset) when it fits the budget; otherwise the run stops.
 * - Responses over 4 MB, non-JSON bodies and any other non-2xx status stop the run. Response bodies and
 *   request URLs are never included in errors: they may contain account data or tokens.
 */
function tpl_http(string $method, string $url, array $options = []): array
{
    tpl_assertAllowedRequest($method, $url);
    static $client = null;
    $client ??= new \GuzzleHttp\Client(['http_errors' => false, 'allow_redirects' => false, 'verify' => true]);
    $host = (string) parse_url($url, PHP_URL_HOST);
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $remaining = tpl_remaining();
        if ($remaining < 2) {
            throw new RuntimeException('Time budget exceeded; narrow the scope.');
        }
        $timeout = min(20.0, $remaining - 1);
        $request = ['connect_timeout' => min(5.0, $timeout), 'timeout' => $timeout, 'read_timeout' => $timeout, 'stream' => true]
            + $options + ['headers' => []];
        $request['headers'] += ['Accept' => 'application/json'];
        $request['verify'] = true;
        $request['allow_redirects'] = false;
        try {
            $response = $client->request($method, $url, $request);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new RuntimeException("Connection to $host failed; check connectivity and credentials.");
        }
        $status = $response->getStatusCode();
        $rateLimited = $status === 403 && trim($response->getHeaderLine('X-RateLimit-Remaining')) === '0';
        if ((in_array($status, [429, 503, 504], true) || $rateLimited) && $attempt < 3) {
            $response->getBody()->close();
            $wait = tpl_retryDelay($response, $attempt);
            if ($wait > min(30, tpl_remaining() - 5)) {
                throw new RuntimeException("$host is throttling this run; retry later.");
            }
            tpl_logInfo(sprintf('%s asked to slow down (HTTP %d); waiting %d s.', $host, $status, $wait));
            sleep($wait);
            continue;
        }
        $body = tpl_readBody($response->getBody(), 4 * 1024 * 1024, $host);
        if ($status < 200 || $status > 299) {
            throw new RuntimeException("$host returned HTTP $status; check permissions, consent and configuration.");
        }
        try {
            $data = $body === '' ? [] : json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("$host returned invalid JSON.");
        }
        if (!is_array($data)) {
            throw new RuntimeException("$host returned an unexpected response.");
        }
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }
        return ['status' => $status, 'headers' => $headers, 'data' => $data];
    }
    throw new RuntimeException("$host retry limit reached; retry later.");
}

function tpl_assertAllowedRequest(string $method, string $url): void
{
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])
        || preg_match('/[\x00-\x20\\\\]/', $url) === 1) {
        throw new RuntimeException('Rejected an unexpected request URL.');
    }
    $request = strtoupper($method) . ' https://' . strtolower($parts['host']) . ($parts['path'] ?? '/');
    foreach (TPL_ALLOWED_REQUESTS as $allowed) {
        if (str_starts_with($request, $allowed)) {
            return;
        }
    }
    throw new RuntimeException('Rejected a request outside TPL_ALLOWED_REQUESTS (' . strtoupper($method) . ' ' . strtolower($parts['host']) . ').');
}

/** Pagination links must stay on the same endpoint as the first page, so the token never leaves it. */
function tpl_assertNextUrl(string $first, string $next): void
{
    $a = parse_url($first);
    $b = parse_url($next);
    foreach (['scheme', 'host', 'path'] as $part) {
        if (!is_array($a) || !is_array($b) || strtolower((string) ($a[$part] ?? '')) !== strtolower((string) ($b[$part] ?? ''))) {
            throw new RuntimeException('Pagination link points to a different endpoint; stopping.');
        }
    }
    if (isset($b['user']) || isset($b['pass']) || isset($b['port']) || isset($b['fragment'])) {
        throw new RuntimeException('Pagination link points to a different endpoint; stopping.');
    }
}

/** Seconds to wait before retrying: Retry-After (seconds or HTTP date), then reset epochs, then backoff. */
function tpl_retryDelay(\Psr\Http\Message\ResponseInterface $response, int $attempt): int
{
    $retryAfter = trim($response->getHeaderLine('Retry-After'));
    if ($retryAfter !== '') {
        if (ctype_digit($retryAfter)) {
            return max(1, (int) $retryAfter);
        }
        $date = strtotime($retryAfter);
        if ($date !== false) {
            return max(1, $date - time());
        }
    }
    foreach (['X-Rate-Limit-Reset', 'X-RateLimit-Reset'] as $header) {
        $reset = trim($response->getHeaderLine($header));
        if (ctype_digit($reset) && (int) $reset > time()) {
            return max(1, (int) $reset - time());
        }
    }
    return 2 ** ($attempt - 1);
}

function tpl_readBody(\Psr\Http\Message\StreamInterface $stream, int $maxBytes, string $host): string
{
    $body = '';
    try {
        while (!$stream->eof()) {
            tpl_remaining();
            $chunk = $stream->read(min(65536, $maxBytes + 1 - strlen($body)));
            if ($chunk === '' && !$stream->eof()) {
                throw new RuntimeException("$host response could not be read completely.");
            }
            $body .= $chunk;
            if (strlen($body) > $maxBytes) {
                throw new RuntimeException("$host response too large; narrow the scope.");
            }
        }
    } finally {
        $stream->close();
    }
    return $body;
}
// ─── end shared:http v2 sha256:2fa2c253cb37e4d7 ───
// ─── shared:providers/graph v1 (edit Automation/_shared/providers/graph.php, then run Automation/tools/sync.php) ───
/** Application token through the client-credentials flow. */
function tpl_graphToken(string $tenantId, string $clientId, string $clientSecret): string
{
    foreach (['tenant ID' => $tenantId, 'client ID' => $clientId] as $what => $value) {
        if (preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $value) !== 1) {
            throw new RuntimeException("Invalid Entra $what.");
        }
    }
    $response = tpl_http('POST', 'https://login.microsoftonline.com/' . strtolower($tenantId) . '/oauth2/v2.0/token', [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
        ],
    ]);
    $token = $response['data']['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Microsoft returned no access token.');
    }
    return $token;
}

/** One Graph object, e.g. tpl_graphGet('users/<id>?$select=id', $token). */
function tpl_graphGet(string $path, string $token): array
{
    return tpl_http('GET', 'https://graph.microsoft.com/v1.0/' . $path, ['headers' => ['Authorization' => 'Bearer ' . $token]])['data'];
}

/**
 * Every item of one collection, or an exception. With $counted the request asks for @odata.count
 * (advanced query, ConsistencyLevel: eventual) and the items received must match it.
 */
function tpl_graphList(string $path, string $token, int $limit, bool $counted = true): array
{
    return tpl_graphLists([$path], $token, $limit, $counted)[0];
}

/**
 * Several collections at once through Graph JSON batching (20 requests per call), each followed to its
 * last page. Returns the items of each path, in the order given. Any failed sub-request stops the run.
 */
function tpl_graphLists(array $paths, string $token, int $limit, bool $counted = true): array
{
    $auth = ['Authorization' => 'Bearer ' . $token];
    $headers = $counted ? ['ConsistencyLevel' => 'eventual'] : [];
    $pending = [];
    foreach (array_values($paths) as $i => $path) {
        $url = '/' . $path . ($counted ? (str_contains($path, '?') ? '&' : '?') . '$count=true' : '');
        $pending[$i] = ['url' => $url, 'first' => 'https://graph.microsoft.com/v1.0' . $url, 'pages' => 0, 'expected' => null, 'items' => [], 'attempts' => 0];
    }
    $done = [];
    while ($pending !== []) {
        foreach (array_chunk($pending, 20, true) as $chunk) {
            $requests = [];
            foreach ($chunk as $i => $state) {
                $requests[] = ['id' => (string) $i, 'method' => 'GET', 'url' => $state['url'], 'headers' => (object) $headers];
            }
            $batch = tpl_http('POST', 'https://graph.microsoft.com/v1.0/$batch', ['headers' => $auth, 'json' => ['requests' => $requests]])['data'];
            if (!is_array($batch['responses'] ?? null) || count($batch['responses']) !== count($requests)) {
                throw new RuntimeException('Graph batch response is incomplete.');
            }
            $wait = 0;
            foreach ($batch['responses'] as $answer) {
                $i = (int) ($answer['id'] ?? -1);
                if (!isset($chunk[$i])) {
                    throw new RuntimeException('Graph batch response does not match its requests.');
                }
                $status = (int) ($answer['status'] ?? 0);
                if (in_array($status, [429, 503, 504], true) && ++$pending[$i]['attempts'] < 3) {
                    $wait = max($wait, (int) ($answer['headers']['Retry-After'] ?? 1));
                    continue;
                }
                if ($status !== 200) {
                    throw new RuntimeException("Microsoft Graph returned HTTP $status for a collection; check permissions and consent.");
                }
                $pending[$i] = tpl_graphPage($pending[$i], $answer['body'] ?? null, $limit, $counted);
                if ($pending[$i]['url'] === null) {
                    $done[$i] = $pending[$i]['items'];
                    unset($pending[$i]);
                }
            }
            if ($wait > 0) {
                if ($wait > min(30, tpl_remaining() - 5)) {
                    throw new RuntimeException('Microsoft Graph is throttling this run; retry later.');
                }
                tpl_logInfo(sprintf('Microsoft Graph asked to slow down; waiting %d s.', $wait));
                sleep($wait);
            }
        }
    }
    ksort($done);
    return array_values($done);
}

/** Applies one page to a collection's state; sets 'url' to the next page or null when complete. */
function tpl_graphPage(array $state, mixed $page, int $limit, bool $counted): array
{
    if (!is_array($page) || !is_array($page['value'] ?? null)) {
        throw new RuntimeException('Graph collection is missing or malformed.');
    }
    $count = $page['@odata.count'] ?? null;
    if (++$state['pages'] === 1) {
        if ($counted && (!is_int($count) || $count < 0)) {
            throw new RuntimeException('Graph collection count is missing.');
        }
        $state['expected'] = $count;
    } elseif ($count !== null && $count !== $state['expected']) {
        throw new RuntimeException('Graph collection changed during the pull; retry later.');
    }
    if ($state['pages'] > 100) {
        throw new RuntimeException('Graph collection has more than 100 pages; narrow the scope.');
    }
    foreach ($page['value'] as $item) {
        if (!is_array($item) || !is_string($item['id'] ?? null) || $item['id'] === '') {
            throw new RuntimeException('Graph collection contains an invalid item.');
        }
        $id = strtolower($item['id']);
        if (isset($state['items'][$id])) {
            throw new RuntimeException('Graph collection contains a duplicate item.');
        }
        $state['items'][$id] = $item;
        if (count($state['items']) > $limit) {
            throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
        }
    }
    $next = $page['@odata.nextLink'] ?? null;
    if ($next === null) {
        if ($state['expected'] !== null && count($state['items']) !== $state['expected']) {
            throw new RuntimeException('Graph collection is incomplete or changed during the pull; retry later.');
        }
        $state['items'] = array_values($state['items']);
        $state['url'] = null;
        return $state;
    }
    if (!is_string($next) || !str_starts_with($next, 'https://graph.microsoft.com/v1.0/')) {
        throw new RuntimeException('Graph pagination link is invalid.');
    }
    tpl_assertNextUrl($state['first'], $next);
    $state['url'] = substr($next, strlen('https://graph.microsoft.com/v1.0'));
    return $state;
}
// ─── end shared:providers/graph v1 sha256:0f75dd180874adb4 ───
// ─── shared:feed v4 (edit Automation/_shared/feed.php, then run Automation/tools/sync.php) ───
/** A required feed value: trimmed, valid UTF-8, at most 255 bytes (the eramba field size). */
function tpl_feedText(mixed $value, string $what): string
{
    if (!is_string($value) || preg_match('//u', $value) !== 1) {
        throw new RuntimeException("$what is missing or not valid text.");
    }
    $value = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value));
    if ($value === '') {
        throw new RuntimeException("$what is missing.");
    }
    if (strlen($value) > 255) {
        throw new RuntimeException("$what is longer than 255 bytes.");
    }
    return $value;
}

/**
 * The role kinds a script collects, from its ROLES variable. Empty means accounts only: reviews then
 * compare which accounts exist (added, deleted, creeping accounts) and never report role changes.
 * Returns the selection as a set, e.g. ['groups' => true].
 */
function tpl_roleKinds(mixed $selected, array $allowed): array
{
    if (!is_array($selected) || !array_is_list($selected) || count($selected) !== count(array_unique($selected))
        || array_diff($selected, $allowed) !== []) {
        throw new RuntimeException('ROLES must be a list chosen from: ' . implode(', ', $allowed) . ' (or empty for accounts only).');
    }
    return array_fill_keys($selected, true);
}

/**
 * One role string: "<kind>: <name> [<id>]". The ID is what keeps two roles with the same name apart,
 * so only the human-readable name is shortened (at a UTF-8 boundary) when the string would exceed 255 bytes.
 */
function tpl_roleString(string $kind, mixed $name, string $id): string
{
    $name = tpl_feedText($name, "$kind name");
    $fixed = strlen($kind) + strlen($id) + 5; // ": " + " [" + "]"
    if ($fixed + 3 > 255) {
        throw new RuntimeException("$kind identifier is too long for a role value.");
    }
    if ($fixed + strlen($name) > 255) {
        $name = mb_strcut($name, 0, 255 - $fixed - 3, 'UTF-8') . '…';
    }
    return sprintf('%s: %s [%s]', $kind, $name, $id);
}

/**
 * The key eramba matches between pulls and between feeds. eramba compares keys ignoring case,
 * so two rows must never share a key ignoring case.
 */
function tpl_feedKey(mixed $value, array &$seen, bool $allowSpaces = false): string
{
    $key = tpl_feedText($value, 'Account key');
    if (!$allowSpaces && preg_match('/\s/u', $key) === 1) {
        throw new RuntimeException('An account key contains spaces; choose another key field.');
    }
    $folded = mb_strtolower($key, 'UTF-8');
    if (isset($seen[$folded])) {
        throw new RuntimeException('Two accounts share the same key (ignoring case); choose a unique key field.');
    }
    $seen[$folded] = true;
    return $key;
}

/**
 * Accounts are staged in a private temporary file and published once, after the whole population
 * has been collected: a partial list must never reach eramba (a differential review would report
 * the missing accounts as removed).
 */
function tpl_feedOpen()
{
    $feed = tmpfile();
    if ($feed === false) {
        throw new RuntimeException('Private staging file is unavailable.');
    }
    return $feed;
}

/** Adds one row. $roles may be empty. Returns the staged size in bytes. */
function tpl_feedAdd($feed, string $user, string $description, array $roles): int
{
    static $bytes = 0;
    $roles = array_values(array_unique($roles));
    sort($roles, SORT_STRING);
    $line = json_encode(['user' => $user, 'description' => $description, 'roles' => $roles],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $bytes += strlen($line);
    if ($bytes > 8 * 1024 * 1024 || fwrite($feed, $line) !== strlen($line)) {
        throw new RuntimeException('Feed larger than 8 MiB or staging write failed; narrow the scope.');
    }
    tpl_feedStats($roles);
    return $bytes;
}

/**
 * Running counts of the staged rows for the run summary: rows, rows without roles and roles per kind
 * (the "<kind>" of tpl_roleString). Called with the roles of each new row; without arguments it only reads.
 */
function tpl_feedStats(?array $roles = null): array
{
    static $stats = ['rows' => 0, 'without roles' => 0, 'kinds' => []];
    if ($roles !== null) {
        $stats['rows']++;
        $stats['without roles'] += $roles === [] ? 1 : 0;
        foreach ($roles as $role) {
            $kind = strstr($role, ': ', true);
            $kind = $kind === false ? 'Other' : $kind;
            $stats['kinds'][$kind] = ($stats['kinds'][$kind] ?? 0) + 1;
        }
    }
    return $stats;
}

/** Publishes every staged row through eramba's Account Review artifact helper. */
function tpl_feedPublish($feed): void
{
    if (!function_exists('writeAccountReviewArtifact')) {
        throw new RuntimeException('writeAccountReviewArtifact() is unavailable; create the automation from the Account Review feed form.');
    }
    if (!fflush($feed) || !rewind($feed)) {
        throw new RuntimeException('Could not finalize the staged feed.');
    }
    writeAccountReviewArtifact((static function () use ($feed): Generator {
        while (($line = fgets($feed)) !== false) {
            yield json_decode($line, true, 64, JSON_THROW_ON_ERROR);
        }
        if (!feof($feed)) {
            throw new RuntimeException('Could not read the complete staged feed.');
        }
    })());
}

/**
 * Ends a successful run with a block people can read at a glance in eramba's Test output.
 * $what names the rows ("accounts", "current employees"); $details adds script-specific counts.
 * Counts only, never names: the output may be shared or screenshotted.
 */
function tpl_logSummary(string $what, array $details = []): void
{
    $stats = tpl_feedStats();
    tpl_log("\n──────────── SUMMARY ────────────");
    tpl_log(sprintf('RESULT: OK. eramba received the complete list of %d %s.', $stats['rows'], $what));
    foreach ($details as $detail) {
        tpl_logInfo($detail);
    }
    if ($stats['kinds'] !== []) {
        ksort($stats['kinds'], SORT_STRING);
        $kinds = [];
        foreach ($stats['kinds'] as $kind => $count) {
            $kinds[] = "$count $kind";
        }
        tpl_logInfo('Roles listed: ' . implode(', ', $kinds) . '.');
        tpl_logInfo(sprintf('%s without any role: %d.', ucfirst($what), $stats['without roles']));
    }
}

/** Ends a failed run: nothing reached eramba, and why. */
function tpl_logFailure(string $what, Throwable $e): void
{
    tpl_log("\n──────────── SUMMARY ────────────\nRESULT: FAILED. No $what was published.\nERROR: " . $e->getMessage());
}
// ─── end shared:feed v4 sha256:c6a059301e52f3e0 ───

// ─── 5. COLLECT: Entra ID ───────────────────────────────────────────────────

const ROLE_KINDS = ['groups', 'directory_roles', 'applications', 'licenses'];

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['userPrincipalName', 'id', 'mail'], true)) {
        throw new RuntimeException('USER_KEY must be userPrincipalName, id or mail.');
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    foreach (['MAX_USERS' => 50000, 'MAX_OBJECTS' => 10000] as $key => $max) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > $max) {
            throw new RuntimeException("$key must be a number from 1 to $max.");
        }
    }
    if (!is_array($c['USER_IDS']) || !array_is_list($c['USER_IDS'])) {
        throw new RuntimeException('USER_IDS must be a list of user object IDs.');
    }
    $ids = array_map('entraId', $c['USER_IDS']);
    if (count($ids) !== count(array_unique($ids)) || count($ids) > $c['MAX_USERS']) {
        throw new RuntimeException('USER_IDS contains duplicates or exceeds MAX_USERS.');
    }
}

/** Entra object IDs, lower-cased so lookups are stable. */
function entraId(mixed $value): string
{
    if (!is_string($value) || preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $value) !== 1) {
        throw new RuntimeException('An Entra object ID is missing or invalid.');
    }
    return strtolower($value);
}

/** Active directory role assignments, keyed by principal (user or role-assignable group). */
function directoryRoles(string $token, array $config): array
{
    $names = [];
    foreach (tpl_graphList('roleManagement/directory/roleDefinitions?$select=id,displayName', $token, $config['MAX_OBJECTS'], false) as $role) {
        $names[entraId($role['id'])] = $role['displayName'] ?? null;
    }
    $byPrincipal = [];
    // The directory provider rejects appScopeId in $select: its assignments are scoped by directoryScopeId only.
    $select = 'roleManagement/directory/roleAssignments?$select=id,principalId,roleDefinitionId,directoryScopeId';
    foreach (tpl_graphList($select, $token, $config['MAX_OBJECTS'], false) as $assignment) {
        $roleId = entraId($assignment['roleDefinitionId'] ?? null);
        if (!array_key_exists($roleId, $names)) {
            // The list leaves out built-in roles Microsoft has deprecated ("Deprecated - Do Not Use.", also absent
            // from directoryRoleTemplates), yet tenants can still hold assignments to them, e.g. AdHoc License
            // Administrator on Microsoft's own Office 365 Portal service principal. They are still readable by ID.
            $role = tpl_graphGet("roleManagement/directory/roleDefinitions/$roleId?\$select=id,displayName", $token);
            if (entraId($role['id'] ?? null) !== $roleId) {
                throw new RuntimeException('A directory role assignment cannot be resolved.');
            }
            $names[$roleId] = $role['displayName'] ?? null;
        }
        $scope = $assignment['directoryScopeId'] ?? null;
        if (!is_string($scope) || $scope === '') {
            throw new RuntimeException('A directory role assignment has no scope.');
        }
        $byPrincipal[entraId($assignment['principalId'] ?? null)][] = tpl_roleString('Directory role', $names[$roleId], "$roleId; scope=$scope");
    }
    return $byPrincipal;
}

/** The whole tenant, or exactly the configured USER_IDS. */
function listUsers(string $token, array $config): array
{
    $select = '$select=id,displayName,userPrincipalName,mail,accountEnabled,userType,assignedLicenses';
    if ($config['USER_IDS'] === []) {
        return tpl_graphList('users?' . $select . '&$top=999', $token, $config['MAX_USERS']);
    }
    $users = [];
    foreach (array_map('entraId', $config['USER_IDS']) as $id) {
        $user = tpl_graphGet("users/$id?$select", $token);
        if (entraId($user['id'] ?? null) !== $id) {
            throw new RuntimeException('An explicitly scoped user was not returned correctly.');
        }
        $users[] = $user;
    }
    return $users;
}

/** Microsoft 365 and other licences the tenant subscribes to: SKU ID => SKU part number (e.g. ENTERPRISEPACK). */
function licenseNames(string $token): array
{
    $names = [];
    foreach (tpl_graphGet('subscribedSkus?$select=skuId,skuPartNumber', $token)['value'] ?? null ?: [] as $sku) {
        $names[entraId($sku['skuId'] ?? null)] = tpl_feedText($sku['skuPartNumber'] ?? null, 'Licence name');
    }
    return $names;
}

/** Licences assigned to one user, directly or through a group. */
function userLicenses(array $user, array $licenseNames): array
{
    if (!is_array($user['assignedLicenses'] ?? null)) {
        throw new RuntimeException('Assigned licences are missing; cannot establish licence coverage.');
    }
    $roles = [];
    foreach ($user['assignedLicenses'] as $license) {
        $skuId = entraId(is_array($license) ? ($license['skuId'] ?? null) : null);
        if (!isset($licenseNames[$skuId])) {
            throw new RuntimeException('A user has a licence the tenant does not list; retry later.');
        }
        $roles[] = tpl_roleString('License', $licenseNames[$skuId], $skuId);
    }
    return $roles;
}

/** Application and role names, fetched once per service principal. */
function application(string $id, string $token, array &$cache): array
{
    if (!isset($cache[$id])) {
        $app = tpl_graphGet("servicePrincipals/$id?\$select=id,displayName,appRoles", $token);
        if (entraId($app['id'] ?? null) !== $id || !is_array($app['appRoles'] ?? null)) {
            throw new RuntimeException('Application metadata is missing or inaccessible.');
        }
        $roles = ['00000000-0000-0000-0000-000000000000' => 'Default access'];
        foreach ($app['appRoles'] as $role) {
            $roles[entraId(is_array($role) ? ($role['id'] ?? null) : null)] = $role['displayName'] ?? null;
        }
        $cache[$id] = ['name' => $app['displayName'] ?? null, 'roles' => $roles];
    }
    return $cache[$id];
}

/**
 * Groups (nested included), directory roles (direct or through a role-assignable group) and
 * application roles (direct or through a group) of one user.
 */
function userRoles(string $id, array $memberships, array $appAssignments, string $token, array $directoryRoles, array &$apps, array $kinds): array
{
    $roles = $directoryRoles[$id] ?? [];
    $groups = [];
    foreach ($memberships as $member) {
        $type = $member['@odata.type'] ?? '';
        if ($type === '#microsoft.graph.group') {
            $groupId = entraId($member['id']);
            $groups[$groupId] = true;
            if (isset($kinds['groups'])) {
                $roles[] = tpl_roleString('Group', $member['displayName'] ?? null, $groupId);
            }
            // Role-assignable groups cannot contain other groups, so their members are direct members.
            array_push($roles, ...($directoryRoles[$groupId] ?? []));
        } elseif (!in_array($type, ['#microsoft.graph.directoryRole', '#microsoft.graph.administrativeUnit'], true)) {
            throw new RuntimeException('Unknown membership type; refusing incomplete access coverage.');
        }
    }
    foreach ($appAssignments as $assignment) {
        $principal = entraId($assignment['principalId'] ?? null);
        $type = $assignment['principalType'] ?? '';
        if (!(($type === 'User' && $principal === $id) || ($type === 'Group' && isset($groups[$principal])))) {
            throw new RuntimeException('An application assignment has an unresolved principal.');
        }
        $resource = entraId($assignment['resourceId'] ?? null);
        $roleId = entraId($assignment['appRoleId'] ?? null);
        $app = application($resource, $token, $apps);
        if (!array_key_exists($roleId, $app['roles'])) {
            throw new RuntimeException('An application role assignment cannot be resolved.');
        }
        $name = tpl_feedText($app['name'], 'Application name') . ' / ' . tpl_feedText($app['roles'][$roleId], 'Application role name');
        $roles[] = tpl_roleString('Application', $name, "$resource / $roleId");
    }
    return $roles;
}

/**
 * Collects every account, 100 users at a time (memberships and app assignments batched), into the staged feed.
 * Returns the feed and the account counts shown in the run summary.
 */
function collectFeed(string $token, array $config, array $directoryRoles, array $kinds): array
{
    $feed = tpl_feedOpen();
    $users = listUsers($token, $config);
    $licenseNames = isset($kinds['licenses']) ? licenseNames($token) : [];
    $keys = [];
    $apps = [];
    $bytes = 0;
    $stats = ['enabled' => 0, 'disabled' => 0, 'Member' => 0, 'Guest' => 0];
    foreach (array_chunk($users, 100) as $chunk) {
        // Memberships are needed for groups, group-held directory roles and to verify group-based app assignments.
        $paths = [];
        foreach ($chunk as $user) {
            $id = entraId($user['id'] ?? null);
            if ($kinds !== []) {
                $paths[] = "users/$id/transitiveMemberOf?\$select=id,displayName";
            }
            if (isset($kinds['applications'])) {
                $paths[] = "users/$id/appRoleAssignments?\$select=id,principalId,principalType,resourceId,appRoleId";
            }
        }
        $lists = $paths === [] ? [] : tpl_graphLists($paths, $token, $config['MAX_OBJECTS']);
        $perUser = $kinds === [] ? 0 : (isset($kinds['applications']) ? 2 : 1);
        foreach ($chunk as $n => $user) {
            $id = entraId($user['id']);
            $key = tpl_feedKey($config['USER_KEY'] === 'id' ? $id : ($user[$config['USER_KEY']] ?? null), $keys);
            if (!is_bool($user['accountEnabled'] ?? null) || !in_array($user['userType'] ?? null, ['Member', 'Guest'], true)) {
                throw new RuntimeException('Account status or type is missing; cannot establish population coverage.');
            }
            $name = mb_strcut(tpl_feedText($user['displayName'] ?? null, 'Display name'), 0, 150, 'UTF-8');
            $description = sprintf('%s; %s; %s; object=%s', $name, $user['userType'], $user['accountEnabled'] ? 'enabled' : 'disabled', $id);
            $roles = isset($kinds['licenses']) ? userLicenses($user, $licenseNames) : [];
            $roles = [...$roles, ...($perUser === 0 ? [] : userRoles($id, $lists[$perUser * $n], $perUser === 2 ? $lists[2 * $n + 1] : [], $token, $directoryRoles, $apps, $kinds))];
            if (count(array_unique($roles)) > $config['MAX_OBJECTS']) {
                throw new RuntimeException('Combined account access limit exceeded.');
            }
            $bytes = tpl_feedAdd($feed, $key, tpl_feedText($description, 'Description'), $roles);
            $stats[$user['accountEnabled'] ? 'enabled' : 'disabled']++;
            $stats[$user['userType']]++;
        }
        // One progress line per 1,000 accounts keeps the log short on large tenants.
        if (count($keys) % 1000 === 0 || count($keys) === count($users)) {
            tpl_logInfo(sprintf('%d/%d accounts collected.', count($keys), count($users)));
        }
    }
    if ($users === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new RuntimeException('No accounts found; check USER_IDS, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    tpl_logInfo(sprintf('%d accounts staged (%d bytes), %d applications resolved.', count($users), $bytes, count($apps)));
    return [$feed, $stats];
}

// ─── 6. MAIN ────────────────────────────────────────────────────────────────
tpl_installErrorHandler();
tpl_elapsed();
try {
    tpl_log(sprintf('%s v%s', AUTOMATION_ID, AUTOMATION_VERSION));

    tpl_logStep(1, 'Checking configuration');
    validateConfig($config);
    tpl_checkSecrets($secrets);
    tpl_logInfo('Secrets present. Scope: ' . ($config['USER_IDS'] === [] ? 'whole tenant.' : count($config['USER_IDS']) . ' explicit users.'));

    tpl_logStep(2, 'Authenticating to Microsoft Graph');
    $token = tpl_graphToken($secrets['ENTRA_TENANT_ID'], $secrets['ENTRA_CLIENT_ID'], tpl_base64Secret($secrets['ENTRA_CLIENT_SECRET_B64'], 'entra_client_secret_b64'));

    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    tpl_logStep(3, 'Collecting directory role assignments');
    $directoryRoles = isset($kinds['directory_roles']) ? directoryRoles($token, $config) : [];
    tpl_logInfo(isset($kinds['directory_roles']) ? count($directoryRoles) . ' principals hold active directory roles.' : 'Skipped (not in ROLES).');

    tpl_logStep(4, 'Collecting accounts' . ($kinds === [] ? '' : ' and ' . implode(', ', array_keys($kinds))));
    [$feed, $stats] = collectFeed($token, $config, $directoryRoles, $kinds);

    tpl_logStep(5, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts', [sprintf('Accounts: %d enabled, %d disabled; %d members, %d guests.', $stats['enabled'], $stats['disabled'], $stats['Member'], $stats['Guest'])]);
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
