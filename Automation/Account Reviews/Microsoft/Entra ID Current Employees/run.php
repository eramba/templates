<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Entra ID Current Employees
 *  Technology: Microsoft Entra ID
 *  id: entra-current-employees        version: 0.1.1 (draft)
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Account%20Reviews
 *
 *  Composer packages (paste in the automation's composer field):
 *    guzzlehttp/guzzle:^7.9
 * ============================================================================
 *
 *  For a feed of type "List of current employees": the people who should have
 *  access today. An Exit review flags every application account that does not
 *  match one of them.
 *
 *  Output
 *    STDOUT  Reserved for the Account Review artifact declaration written by
 *            writeAccountReviewArtifact(). Nothing else is ever printed there.
 *    STDERR  Step-by-step log with counts only, and technical errors.
 *
 *  Exit codes
 *    0  Complete employee list collected and published.
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
    'USER_KEY' => 'mail', // Identifier compared with the application accounts: mail, userPrincipalName or id
    'GROUP_ID' => '', // Empty: every enabled member user. Otherwise only enabled users in this group (nested included)
    'REQUIRE_EMPLOYEE_ID' => false, // True: only users with an Employee ID (typically those provisioned from HR)
    'MAX_USERS' => 10000, // Maximum employees; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'entra-current-employees';
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

function validateConfig(array $c): void
{
    if (!in_array($c['USER_KEY'], ['mail', 'userPrincipalName', 'id'], true)) {
        throw new RuntimeException('USER_KEY must be mail, userPrincipalName or id.');
    }
    if (!is_string($c['GROUP_ID']) || ($c['GROUP_ID'] !== '' && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $c['GROUP_ID']) !== 1)) {
        throw new RuntimeException('GROUP_ID must be empty or an Entra group object ID.');
    }
    if (!is_bool($c['REQUIRE_EMPLOYEE_ID'])) {
        throw new RuntimeException('REQUIRE_EMPLOYEE_ID must be true or false.');
    }
    if (!is_int($c['MAX_USERS']) || $c['MAX_USERS'] < 1 || $c['MAX_USERS'] > 50000) {
        throw new RuntimeException('MAX_USERS must be a number from 1 to 50000.');
    }
}

/** Enabled member users of the tenant, or of the configured group (nested members included). */
function listUsers(string $token, array $config): array
{
    $select = '$select=id,displayName,userPrincipalName,mail,employeeId,accountEnabled,userType';
    if ($config['GROUP_ID'] !== '') {
        $path = 'groups/' . strtolower($config['GROUP_ID']) . '/transitiveMembers/microsoft.graph.user?' . $select . '&$top=999';
    } else {
        $path = 'users?' . $select . '&$filter=' . rawurlencode("accountEnabled eq true and userType eq 'Member'") . '&$top=999';
    }
    $users = tpl_graphList($path, $token, $config['MAX_USERS']);
    return array_values(array_filter($users, fn ($u) => ($u['accountEnabled'] ?? null) === true && ($u['userType'] ?? null) === 'Member'));
}

/** Returns the staged feed and the counts shown in the run summary. */
function collectFeed(string $token, array $config): array
{
    $users = listUsers($token, $config);
    $feed = tpl_feedOpen();
    $keys = [];
    $count = 0;
    $withoutKey = 0;
    $withoutEmployeeId = 0;
    foreach ($users as $user) {
        if ($config['REQUIRE_EMPLOYEE_ID'] && !is_string($user['employeeId'] ?? null)) {
            $withoutEmployeeId++;
            continue;
        }
        $value = $user[$config['USER_KEY']] ?? null;
        if (!is_string($value) || trim($value) === '') {
            // Leaving someone out of a current-employees list only adds findings (their accounts get
            // flagged); it can never hide one. Users without the key field are therefore counted, not fatal.
            $withoutKey++;
            continue;
        }
        $key = tpl_feedKey($value, $keys);
        $name = mb_strcut(tpl_feedText($user['displayName'] ?? $value, 'Display name'), 0, 150, 'UTF-8');
        $employeeId = is_string($user['employeeId'] ?? null) ? '; employee ' . mb_strcut($user['employeeId'], 0, 40, 'UTF-8') : '';
        tpl_feedAdd($feed, $key, tpl_feedText($name . $employeeId, 'Description'), []);
        $count++;
    }
    // An empty current-employees list would flag every application account: never publish one.
    if ($count === 0) {
        throw new RuntimeException('No employees found; check GROUP_ID, REQUIRE_EMPLOYEE_ID and USER_KEY.');
    }
    tpl_logInfo(sprintf('%d employees staged; %d skipped without %s; %d skipped without Employee ID.', $count, $withoutKey, $config['USER_KEY'], $withoutEmployeeId));
    return [$feed, ['found' => count($users), 'without key' => $withoutKey, 'without employee id' => $withoutEmployeeId]];
}

// ─── 6. MAIN ────────────────────────────────────────────────────────────────
tpl_installErrorHandler();
tpl_elapsed();
try {
    tpl_log(sprintf('%s v%s', AUTOMATION_ID, AUTOMATION_VERSION));

    tpl_logStep(1, 'Checking configuration');
    validateConfig($config);
    tpl_checkSecrets($secrets);
    tpl_logInfo('Secrets present. Scope: ' . ($config['GROUP_ID'] === '' ? 'all enabled member users.' : 'enabled users of one group.'));

    tpl_logStep(2, 'Authenticating to Microsoft Graph');
    $token = tpl_graphToken($secrets['ENTRA_TENANT_ID'], $secrets['ENTRA_CLIENT_ID'], tpl_base64Secret($secrets['ENTRA_CLIENT_SECRET_B64'], 'entra_client_secret_b64'));

    tpl_logStep(3, 'Collecting current employees');
    [$feed, $stats] = collectFeed($token, $config);

    tpl_logStep(4, 'Publishing the employee list');
    tpl_feedPublish($feed);
    $details = [
        sprintf('Enabled members found: %d (%s).', $stats['found'], $config['GROUP_ID'] === '' ? 'whole tenant' : 'one group, nested members included'),
        sprintf('Left out without %s: %d. Left out without Employee ID: %d.', $config['USER_KEY'], $stats['without key'], $stats['without employee id']),
    ];
    if ($stats['without key'] > 0) {
        $details[] = 'Accounts of people left out will be flagged in Exit reviews; check USER_KEY if this number is high.';
    }
    tpl_logSummary('current employees', $details);
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('employee list', $e);
    exit(1);
}
