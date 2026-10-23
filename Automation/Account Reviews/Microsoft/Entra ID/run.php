<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Entra ID – Accounts
 *  Technology: Microsoft Entra ID
 *  id: entra-account-review        version: 0.3.0 (tested)
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
    'GROUP_ID' => '', // Empty: whole tenant. Otherwise only the members of this group (nested included)
    'USER_IDS' => [], // Empty: whole tenant or GROUP_ID. Otherwise a list of Entra user object IDs (testing)
    'ROLES' => ['groups', 'directory_roles', 'applications', 'licenses'], // Access listed as roles; [] = accounts only (README §7)
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum accounts in the feed; never truncate
    'MAX_OBJECTS' => 2000, // Maximum records per API collection; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'entra-account-review';
const AUTOMATION_VERSION = '0.3.0';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const TPL_ALLOWED_REQUESTS = [
    'POST https://login.microsoftonline.com/', // Token
    'GET https://graph.microsoft.com/v1.0/',
    'POST https://graph.microsoft.com/v1.0/$batch', // Read requests grouped 20 at a time
];

// ─── 4. SHARED CODE (copied from Automation/_shared, do not edit here) ──────
// ─── shared:core v2 (edit Automation/_shared/core.php, then run Automation/tools/sync.php) ───
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

/**
 * eramba keeps only the first 10,000 bytes of a run's output, so step lines stop after 8,000 bytes
 * and the rest is left for the result. $keep writes the line regardless (the final result).
 */
function tpl_log(string $line, bool $keep = false): void
{
    static $bytes = 0;
    static $shortened = false;
    $text = $line . "\n";
    if (!$keep && $bytes + strlen($text) > 8000) {
        if ($shortened) {
            return;
        }
        $shortened = true;
        $text = "  [Further steps not logged: eramba keeps only the first 10,000 bytes of output.]\n";
    }
    $bytes += strlen($text);
    fwrite(TPL_LOG_STREAM === 'stdout' ? STDOUT : STDERR, $text);
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
// ─── end shared:core v2 sha256:54b06b7248971a34 ───
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
// ─── shared:providers/graph v2 (edit Automation/_shared/providers/graph.php, then run Automation/tools/sync.php) ───
/**
 * Thrown when a list holds more records than the caller's limit. The script never truncates: it stops, and the
 * caller can turn this into a message that names its own variable. $total is Graph's count when it is known.
 */
final class TplGraphLimitException extends RuntimeException
{
    public function __construct(public readonly int $limit, public readonly ?int $total)
    {
        parent::__construct($total === null
            ? sprintf('More than %d records in one list; narrow the scope or raise the limit.', $limit)
            : sprintf('The list has %d records, more than the limit of %d; narrow the scope or raise the limit.', $total, $limit));
    }
}

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
        if (is_int($count) && $count > $limit) {
            throw new TplGraphLimitException($limit, $count);
        }
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
            throw new TplGraphLimitException($limit, $state['expected']);
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

/**
 * One Entra role as reviewers read it, without object IDs: "<kind>: <name>", then any details in brackets,
 * e.g. "Group: Finance (through group Finance EU)". At most 255 bytes: only the name is shortened.
 */
function tpl_entraRole(string $kind, mixed $name, array $details = []): string
{
    if (!is_string($name) || preg_match('//u', $name) !== 1 || ($name = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $name))) === '') {
        throw new RuntimeException("$kind name is missing or not valid text.");
    }
    $suffix = $details === [] ? '' : ' (' . implode('; ', $details) . ')';
    $fixed = strlen($kind) + 2 + strlen($suffix);
    if ($fixed + 3 > 255) {
        throw new RuntimeException("$kind details are too long for a role value.");
    }
    if ($fixed + strlen($name) > 255) {
        $name = mb_strcut($name, 0, 255 - $fixed - 3, 'UTF-8') . '…';
    }
    return "$kind: $name$suffix";
}

/**
 * Rows whose roles are lists of [text, identity]. Names in Entra are not unique, so when one role text stands
 * for two different objects anywhere in the feed (two groups named "Finance"), each of those roles gets
 * " [<first 8 characters of its object ID>]". Returns the rows with plain, sorted role lists.
 */
function tpl_entraDisambiguate(array $rows): array
{
    $identities = [];
    foreach ($rows as $row) {
        foreach ($row['roles'] as [$text, $identity]) {
            $identities[$text][$identity] = true;
        }
    }
    foreach ($rows as &$row) {
        $roles = [];
        foreach ($row['roles'] as [$text, $identity]) {
            $roles[] = count($identities[$text]) > 1
                ? mb_strcut($text, 0, 244, 'UTF-8') . ' [' . substr($identity, 0, 8) . ']'
                : $text;
        }
        $row['roles'] = array_values(array_unique($roles));
    }
    return $rows;
}
// ─── end shared:providers/graph v2 sha256:b64e3db3f3e9f8b2 ───
// ─── shared:feed v6 (edit Automation/_shared/feed.php, then run Automation/tools/sync.php) ───
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
 * Thrown when the provider returns no rows and the script must not publish an empty list. It is not an
 * error of the run: tpl_logFailure() reports it as NOT PUBLISHED, with the reason in the message.
 */
final class TplEmptyFeedException extends RuntimeException
{
}

/**
 * Ends a successful run with a block people can read at a glance in eramba's Test output.
 * $what names the rows ("accounts", "current employees"); $details adds script-specific counts.
 * Counts only, never names: the output may be shared or screenshotted.
 */
function tpl_logSummary(string $what, array $details = []): void
{
    $stats = tpl_feedStats();
    tpl_log("\n──────────── SUMMARY ────────────", true);
    tpl_log(sprintf('RESULT: OK. Published the complete list of %d %s.', $stats['rows'], $what), true);
    foreach ($details as $detail) {
        tpl_log('  ' . $detail, true);
    }
    if ($stats['kinds'] !== []) {
        ksort($stats['kinds'], SORT_STRING);
        $kinds = [];
        foreach ($stats['kinds'] as $kind => $count) {
            $kinds[] = "$count $kind";
        }
        tpl_log('  Roles listed: ' . implode(', ', $kinds) . '.', true);
        tpl_log(sprintf('  %s without any role: %d.', ucfirst($what), $stats['without roles']), true);
    }
}

/** Ends a run that published nothing: eramba records a failed pull and keeps the last successful one. */
function tpl_logFailure(string $what, Throwable $e): void
{
    tpl_log("\n──────────── SUMMARY ────────────", true);
    if ($e instanceof TplEmptyFeedException) {
        tpl_log("RESULT: NOT PUBLISHED. The provider returned an empty list, so no $what was sent. REASON: " . $e->getMessage(), true);
        return;
    }
    tpl_log("RESULT: FAILED. No $what was published; eramba keeps the last successful pull. ERROR: " . $e->getMessage(), true);
}
// ─── end shared:feed v6 sha256:124b2c93fef309f8 ───

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
    foreach (['MAX_USERS' => 5000, 'MAX_OBJECTS' => 10000] as $key => $max) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > $max) {
            throw new RuntimeException("$key must be a number from 1 to $max.");
        }
    }
    if (!is_string($c['GROUP_ID']) || ($c['GROUP_ID'] !== '' && preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD', $c['GROUP_ID']) !== 1)) {
        throw new RuntimeException('GROUP_ID must be empty or an Entra group object ID.');
    }
    if (!is_array($c['USER_IDS']) || !array_is_list($c['USER_IDS'])) {
        throw new RuntimeException('USER_IDS must be a list of user object IDs.');
    }
    $ids = array_map('entraId', $c['USER_IDS']);
    if (count($ids) !== count(array_unique($ids)) || count($ids) > $c['MAX_USERS']) {
        throw new RuntimeException('USER_IDS contains duplicates or exceeds MAX_USERS.');
    }
    if ($ids !== [] && $c['GROUP_ID'] !== '') {
        throw new RuntimeException('Set GROUP_ID or USER_IDS, not both.');
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

/**
 * Active admin role assignments, keyed by principal (user or role-assignable group). Each holds the role name,
 * where it applies when that is not the whole organisation, and an identity for telling equal names apart.
 */
function directoryRoles(string $token, array $config): array
{
    $names = [];
    foreach (tpl_graphList('roleManagement/directory/roleDefinitions?$select=id,displayName', $token, $config['MAX_OBJECTS'], false) as $role) {
        $names[entraId($role['id'])] = $role['displayName'] ?? null;
    }
    $scopes = ['/' => null];
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
        if (!array_key_exists($scope, $scopes)) {
            $scopes[$scope] = roleScope($scope, $token);
        }
        $byPrincipal[entraId($assignment['principalId'] ?? null)][] = [
            'name' => $names[$roleId], 'scope' => $scopes[$scope], 'id' => "$roleId;$scope",
        ];
    }
    return $byPrincipal;
}

/** Where a role applies, in words: an administrative unit or one object (for example one application). */
function roleScope(string $scope, string $token): string
{
    if (preg_match('~^/administrativeUnits/([^/]+)$~D', $scope, $m) === 1) {
        $unit = tpl_graphGet('administrativeUnits/' . entraId($m[1]) . '?$select=id,displayName', $token);
        return mb_strcut('administrative unit ' . tpl_feedText($unit['displayName'] ?? null, 'Administrative unit name'), 0, 100, 'UTF-8');
    }
    if (preg_match('~^/([^/]+)$~D', $scope, $m) === 1) {
        $object = tpl_graphGet('directoryObjects/' . entraId($m[1]), $token);
        return mb_strcut('only for ' . tpl_feedText($object['displayName'] ?? null, 'Role scope name'), 0, 100, 'UTF-8');
    }
    throw new RuntimeException('A directory role assignment has an unsupported scope.');
}

/** The whole tenant, the members of GROUP_ID (nested included), or exactly the configured USER_IDS. */
function listUsers(string $token, array $config, bool $licenses): array
{
    $select = '$select=id,displayName,userPrincipalName,mail,accountEnabled,userType' . ($licenses ? ',licenseAssignmentStates' : '');
    if ($config['USER_IDS'] === []) {
        $group = $config['GROUP_ID'] !== '';
        $path = $group ? 'groups/' . strtolower($config['GROUP_ID']) . '/transitiveMembers/microsoft.graph.user?' : 'users?';
        try {
            return tpl_graphList($path . $select . '&$top=999', $token, $config['MAX_USERS']);
        } catch (TplGraphLimitException $e) {
            throw new RuntimeException(sprintf('The %s has %s accounts, more than MAX_USERS (%d); %s',
                $group ? 'group' : 'tenant', $e->total ?? 'more', $e->limit,
                $group ? 'review a smaller group, or raise MAX_USERS (up to 5000).' : 'set GROUP_ID to review one group per feed, or raise MAX_USERS (up to 5000).'), 0, $e);
        }
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

/**
 * Product names reviewers see in the Microsoft 365 admin center, for the licences most often assigned to people,
 * from Microsoft's "Product names and service plan identifiers for licensing". Others keep their SKU code.
 */
const LICENSE_NAMES = [
    'AAD_PREMIUM' => 'Microsoft Entra ID P1',
    'AAD_PREMIUM_P2' => 'Microsoft Entra ID P2',
    'ATP_ENTERPRISE' => 'Microsoft Defender for Office 365 (Plan 1)',
    'DESKLESSPACK' => 'Office 365 F3',
    'DEVELOPERPACK_E5' => 'Microsoft 365 E5 Developer (without Windows and Audio Conferencing)',
    'EMS' => 'Enterprise Mobility + Security E3',
    'EMSPREMIUM' => 'Enterprise Mobility + Security E5',
    'ENTERPRISEPACK' => 'Office 365 E3',
    'ENTERPRISEPREMIUM' => 'Office 365 E5',
    'ENTERPRISEPREMIUM_NOPSTNCONF' => 'Office 365 E5 Without Audio Conferencing',
    'EXCHANGEDESKLESS' => 'Exchange Online Kiosk',
    'EXCHANGEENTERPRISE' => 'Exchange Online (Plan 2)',
    'EXCHANGESTANDARD' => 'Exchange Online (Plan 1)',
    'FLOW_FREE' => 'Microsoft Power Automate Free',
    'IDENTITY_THREAT_PROTECTION' => 'Microsoft 365 E5 Security',
    'INTUNE_A' => 'Intune',
    'M365_F1' => 'Microsoft 365 F1',
    'MCOEV' => 'Microsoft Teams Phone Standard',
    'MCOMEETADV' => 'Microsoft 365 Audio Conferencing',
    'MCOPSTN1' => 'Skype for Business PSTN Domestic Calling',
    'Microsoft_365_Copilot' => 'Microsoft Copilot for Microsoft 365',
    'Microsoft_365_E3_(no_Teams)' => 'Microsoft 365 E3 (no Teams)',
    'Microsoft_365_E5_(no_Teams)' => 'Microsoft 365 E5 (no Teams)',
    'Microsoft_Teams_Enterprise_New' => 'Microsoft Teams Enterprise',
    'Microsoft_Teams_Premium' => 'Microsoft Teams Premium Introductory Pricing',
    'Microsoft_Teams_Rooms_Pro' => 'Microsoft Teams Rooms Pro',
    'O365_BUSINESS' => 'Microsoft 365 Apps for Business',
    'O365_BUSINESS_ESSENTIALS' => 'Microsoft 365 Business Basic',
    'O365_BUSINESS_PREMIUM' => 'Microsoft 365 Business Standard',
    'O365_w/o_Teams_Bundle_M5' => 'Microsoft 365 E5 EEA (no Teams)',
    'OFFICESUBSCRIPTION' => 'Microsoft 365 Apps for Enterprise',
    'Office_365_E3_(no_Teams)' => 'Office 365 E3 (no Teams)',
    'PHONESYSTEM_VIRTUALUSER' => 'Microsoft Teams Phone Resource Account',
    'POWERAPPS_DEV' => 'Microsoft Power Apps for Developer',
    'POWERAPPS_VIRAL' => 'Microsoft Power Apps Plan 2 Trial',
    'POWER_BI_PRO' => 'Power BI Pro',
    'POWER_BI_STANDARD' => 'Microsoft Fabric (Free)',
    'PROJECTPREMIUM' => 'Project Online Premium',
    'PROJECTPROFESSIONAL' => 'Planner and Project Plan 3',
    'PROJECT_P1' => 'Planner Plan 1',
    'RIGHTSMANAGEMENT' => 'Azure Information Protection Plan 1',
    'SMB_BUSINESS' => 'Microsoft 365 Apps for Business',
    'SPB' => 'Microsoft 365 Business Premium',
    'SPE_E3' => 'Microsoft 365 E3',
    'SPE_E5' => 'Microsoft 365 E5',
    'SPE_F1' => 'Microsoft 365 F3',
    'SPE_F5_SECCOMP' => 'Microsoft 365 F5 Security + Compliance Add-on',
    'STANDARDPACK' => 'Office 365 E1',
    'STREAM' => 'Microsoft Stream',
    'TEAMS_EXPLORATORY' => 'Microsoft Teams Exploratory',
    'TEAMS_FREE' => 'Microsoft Teams (Free)',
    'THREAT_INTELLIGENCE' => 'Microsoft Defender for Office 365 (Plan 2)',
    'VISIOCLIENT' => 'Visio Plan 2',
    'VISIOONLINE_PLAN1' => 'Visio Plan 1',
    'WINDOWS_STORE' => 'Windows Store for Business',
    'WIN_DEF_ATP' => 'Microsoft Defender for Endpoint',
];

/** Licences the tenant subscribes to: SKU ID => product name (or SKU code when the name is not listed above). */
function licenseNames(string $token): array
{
    $names = [];
    foreach (tpl_graphGet('subscribedSkus?$select=skuId,skuPartNumber', $token)['value'] ?? null ?: [] as $sku) {
        $code = tpl_feedText($sku['skuPartNumber'] ?? null, 'Licence name');
        $names[entraId($sku['skuId'] ?? null)] = LICENSE_NAMES[$code] ?? $code;
    }
    return $names;
}

/** Application and role names, fetched once per service principal. */
function application(string $id, string $token, array &$cache): array
{
    if (!isset($cache[$id])) {
        $app = tpl_graphGet("servicePrincipals/$id?\$select=id,displayName,appRoles", $token);
        if (entraId($app['id'] ?? null) !== $id || !is_array($app['appRoles'] ?? null)) {
            throw new RuntimeException('Application metadata is missing or inaccessible.');
        }
        $roles = ['00000000-0000-0000-0000-000000000000' => null]; // Default access: assigned without a role
        foreach ($app['appRoles'] as $role) {
            $roles[entraId(is_array($role) ? ($role['id'] ?? null) : null)] = tpl_feedText($role['displayName'] ?? null, 'Application role name');
        }
        $cache[$id] = ['name' => $app['displayName'] ?? null, 'roles' => $roles];
    }
    return $cache[$id];
}

/** The groups each of these groups belongs to, nested included, fetched once per group. */
function parentGroups(array $groupIds, string $token, array $config, array &$cache): void
{
    $missing = array_values(array_diff($groupIds, array_keys($cache)));
    foreach (array_chunk($missing, 100) as $chunk) {
        $paths = array_map(fn ($g) => "groups/$g/transitiveMemberOf/microsoft.graph.group?\$select=id,displayName", $chunk);
        foreach (tpl_graphLists($paths, $token, $config['MAX_OBJECTS']) as $n => $parents) {
            $cache[$chunk[$n]] = [];
            foreach ($parents as $parent) {
                $cache[$chunk[$n]][entraId($parent['id'])] = $parent['displayName'] ?? null;
            }
        }
    }
}

/**
 * The access of one user as [text, identity] pairs, named as on the user's page in Entra (Groups, Assigned
 * roles, Applications, Licenses) and saying how it was obtained: directly, or through which group.
 * $direct are the user's direct groups (id => name); $parents maps each group to the groups it belongs to.
 */
function userRoles(string $id, array $user, array $direct, array $parents, array $appAssignments, string $token, array $directoryRoles, array $licenseNames, array &$apps, array $kinds): array
{
    // Every group the user belongs to, with the direct groups it comes through (none when it is direct).
    $groups = [];
    foreach ($direct as $groupId => $name) {
        $groups[$groupId] = ['name' => $name, 'through' => []];
    }
    foreach ($direct as $groupId => $name) {
        foreach ($parents[$groupId] as $parentId => $parentName) {
            if (!isset($direct[$parentId])) {
                $groups[$parentId] ??= ['name' => $parentName, 'through' => []];
                $groups[$parentId]['through'][] = tpl_feedText($name, 'Group name');
            }
        }
    }
    $via = fn (?string $groupId): array => $groupId === null ? [] : ['through group ' . mb_strcut(tpl_feedText($groups[$groupId]['name'], 'Group name'), 0, 100, 'UTF-8')];

    $roles = [];
    if (isset($kinds['groups'])) {
        foreach ($groups as $groupId => $group) {
            $through = array_unique($group['through']);
            sort($through, SORT_STRING);
            // Long lists are cut to the first groups, so the role always fits eramba's 255 bytes.
            $shown = $through;
            while (count($shown) > 1 && strlen(implode(', ', $shown)) > 120) {
                array_pop($shown);
            }
            $more = count($through) - count($shown);
            $details = $through === [] ? [] : ['through group ' . mb_strcut(implode(', ', $shown), 0, 120, 'UTF-8') . ($more > 0 ? " and $more more" : '')];
            $roles[] = [tpl_entraRole('Group', $group['name'], $details), $groupId];
        }
    }
    if (isset($kinds['directory_roles'])) {
        foreach ([$id => null] + array_fill_keys(array_keys($groups), true) as $principal => $isGroup) {
            foreach ($directoryRoles[$principal] ?? [] as $role) {
                $details = [...($role['scope'] === null ? [] : [$role['scope']]), ...$via($isGroup ? $principal : null)];
                $roles[] = [tpl_entraRole('Admin role', $role['name'], $details), $role['id'] . ($isGroup ? ";$principal" : '')];
            }
        }
    }
    if (isset($kinds['applications'])) {
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
            $details = [...($app['roles'][$roleId] === null ? [] : ['role ' . $app['roles'][$roleId]]), ...$via($type === 'Group' ? $principal : null)];
            $roles[] = [tpl_entraRole('Application', $app['name'], $details), "$resource;$roleId" . ($type === 'Group' ? ";$principal" : '')];
        }
    }
    if (isset($kinds['licenses'])) {
        if (!is_array($user['licenseAssignmentStates'] ?? null)) {
            throw new RuntimeException('Licence assignments are missing; cannot establish licence coverage.');
        }
        foreach ($user['licenseAssignmentStates'] as $state) {
            $skuId = entraId(is_array($state) ? ($state['skuId'] ?? null) : null);
            if (!isset($licenseNames[$skuId])) {
                throw new RuntimeException('A user has a licence the tenant does not list; retry later.');
            }
            $group = $state['assignedByGroup'] ?? null;
            $group = $group === null ? null : entraId($group);
            if ($group !== null && !isset($groups[$group])) {
                throw new RuntimeException('A group-based licence comes from a group the user is not in.');
            }
            $roles[] = [tpl_entraRole('License', $licenseNames[$skuId], $via($group)), $skuId . ($group === null ? '' : ";$group")];
        }
    }
    return $roles;
}

/**
 * Collects every account, 100 users at a time (memberships and app assignments batched), then names the
 * roles (an object ID only where two objects share a name) and stages the feed.
 * Returns the feed and the account counts shown in the run summary.
 */
function collectFeed(string $token, array $config, array $directoryRoles, array $kinds): array
{
    $users = listUsers($token, $config, isset($kinds['licenses']));
    $licenseNames = isset($kinds['licenses']) ? licenseNames($token) : [];
    // Direct memberships tell reviewers which group to change; groups, admin roles held by groups and
    // group-based app and licence assignments all need them.
    $needGroups = isset($kinds['groups']) || isset($kinds['directory_roles']) || isset($kinds['applications']) || isset($kinds['licenses']);
    $keys = [];
    $apps = [];
    $parents = [];
    $rows = [];
    $stats = ['enabled' => 0, 'disabled' => 0, 'Member' => 0, 'Guest' => 0];
    foreach (array_chunk($users, 100) as $chunk) {
        $paths = [];
        foreach ($chunk as $user) {
            $id = entraId($user['id'] ?? null);
            if ($needGroups) {
                $paths[] = "users/$id/memberOf?\$select=id,displayName";
            }
            if (isset($kinds['applications'])) {
                $paths[] = "users/$id/appRoleAssignments?\$select=id,principalId,principalType,resourceId,appRoleId";
            }
        }
        $lists = $paths === [] ? [] : tpl_graphLists($paths, $token, $config['MAX_OBJECTS']);
        $perUser = !$needGroups ? 0 : (isset($kinds['applications']) ? 2 : 1);
        $direct = [];
        foreach ($chunk as $n => $user) {
            $direct[$n] = [];
            foreach ($perUser === 0 ? [] : $lists[$perUser * $n] as $member) {
                $type = $member['@odata.type'] ?? '';
                if ($type === '#microsoft.graph.group') {
                    $direct[$n][entraId($member['id'])] = $member['displayName'] ?? null;
                } elseif (!in_array($type, ['#microsoft.graph.directoryRole', '#microsoft.graph.administrativeUnit'], true)) {
                    throw new RuntimeException('Unknown membership type; refusing incomplete access coverage.');
                }
            }
        }
        parentGroups(array_keys(array_replace([], ...$direct)), $token, $config, $parents);
        foreach ($chunk as $n => $user) {
            $id = entraId($user['id']);
            $key = tpl_feedKey($config['USER_KEY'] === 'id' ? $id : ($user[$config['USER_KEY']] ?? null), $keys);
            if (!is_bool($user['accountEnabled'] ?? null) || !in_array($user['userType'] ?? null, ['Member', 'Guest'], true)) {
                throw new RuntimeException('Account status or type is missing; cannot establish population coverage.');
            }
            $name = mb_strcut(tpl_feedText($user['displayName'] ?? null, 'Display name'), 0, 150, 'UTF-8');
            $description = sprintf('%s; %s; %s; object=%s', $name, $user['userType'], $user['accountEnabled'] ? 'enabled' : 'disabled', $id);
            $roles = $kinds === [] ? [] : userRoles($id, $user, $direct[$n], $parents, $perUser === 2 ? $lists[2 * $n + 1] : [], $token, $directoryRoles, $licenseNames, $apps, $kinds);
            if (count($roles) > $config['MAX_OBJECTS']) {
                throw new RuntimeException('Combined account access limit exceeded.');
            }
            $rows[] = ['key' => $key, 'description' => tpl_feedText($description, 'Description'), 'roles' => $roles];
            $stats[$user['accountEnabled'] ? 'enabled' : 'disabled']++;
            $stats[$user['userType']]++;
        }
        // One progress line per 1,000 accounts keeps the log short on large tenants.
        if (count($keys) % 1000 === 0 || count($keys) === count($users)) {
            tpl_logInfo(sprintf('%d/%d accounts collected.', count($keys), count($users)));
        }
    }
    if ($users === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new TplEmptyFeedException('No accounts found; check GROUP_ID and USER_IDS, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    $feed = tpl_feedOpen();
    $bytes = 0;
    foreach (tpl_entraDisambiguate($rows) as $row) {
        $bytes = tpl_feedAdd($feed, $row['key'], $row['description'], $row['roles']);
    }
    tpl_logInfo(sprintf('%d accounts staged (%d bytes), %d groups and %d applications resolved.', count($users), $bytes, count($parents), count($apps)));
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
    tpl_logInfo('Secrets present. Scope: ' . ($config['USER_IDS'] !== [] ? count($config['USER_IDS']) . ' explicit users.' : ($config['GROUP_ID'] !== '' ? 'members of one group, nested included.' : 'whole tenant.')));

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
