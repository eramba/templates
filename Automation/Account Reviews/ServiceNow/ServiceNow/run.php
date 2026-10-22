<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  ServiceNow Accounts and Assigned Access
 *  Technology: ServiceNow AI Platform (Now Platform) instance
 *  id: servicenow-account-review        version: 0.1.0 (draft)
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
 *  Read-only: the script only reads the Table API, never changes ServiceNow
 *  and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
// Only the secrets of the chosen AUTH method are needed (README §6).
$secrets = [
    'SERVICENOW_INSTANCE' => '%SECRET_servicenow_instance%', // Instance host, e.g. example.service-now.com
    'SERVICENOW_CLIENT_ID' => '%SECRET_servicenow_client_id%', // AUTH oauth: client ID of the OAuth application
    'SERVICENOW_CLIENT_SECRET_B64' => '%SECRET_servicenow_client_secret_b64%', // AUTH oauth: base64 of the client secret
    'SERVICENOW_USERNAME' => '%SECRET_servicenow_username%', // AUTH basic: integration user ID
    'SERVICENOW_PASSWORD_B64' => '%SECRET_servicenow_password_b64%', // AUTH basic: base64 of its password
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'AUTH' => 'oauth', // oauth (client credentials grant, preferred) or basic
    'USER_KEY' => 'email', // Account identifier: email, user_name or sys_id (no fallback)
    'ROLES' => ['roles', 'groups'], // Access listed as roles; [] = accounts only
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 10000, // Maximum users in the feed; never truncate
    'MAX_ASSIGNMENTS' => 300000, // Maximum role or group membership records per list; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'servicenow-account-review';
const AUTOMATION_VERSION = '0.1.0';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
// TPL_ALLOWED_REQUESTS is defined in MAIN from the instance host: the token POST (oauth only) and GET of the three tables.

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
// ─── shared:feed v3 (edit Automation/_shared/feed.php, then run Automation/tools/sync.php) ───
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
    return $bytes;
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
// ─── end shared:feed v3 sha256:39800df21bc1737d ───

// ─── 5. COLLECT: ServiceNow ─────────────────────────────────────────────────
// Collect the WHOLE population and every access type the README documents.
// Throw on anything incomplete or unexpected: never skip an account or a role silently.

const ROLE_KINDS = ['roles', 'groups'];
const USER_KEYS = ['email', 'user_name', 'sys_id'];
const AUTH_SECRETS = [
    'oauth' => ['SERVICENOW_INSTANCE', 'SERVICENOW_CLIENT_ID', 'SERVICENOW_CLIENT_SECRET_B64'],
    'basic' => ['SERVICENOW_INSTANCE', 'SERVICENOW_USERNAME', 'SERVICENOW_PASSWORD_B64'],
];
const PAGE_SIZE = 5000; // Records per Table API page (well below ServiceNow's 10000 default maximum)

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!is_string($c['AUTH']) || !isset(AUTH_SECRETS[$c['AUTH']])) {
        throw new RuntimeException('AUTH must be oauth or basic.');
    }
    if (!in_array($c['USER_KEY'], USER_KEYS, true)) {
        throw new RuntimeException('USER_KEY must be email, user_name or sys_id.');
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    if (!is_int($c['MAX_USERS']) || $c['MAX_USERS'] < 1 || $c['MAX_USERS'] > 20000) {
        throw new RuntimeException('MAX_USERS must be a number from 1 to 20000.');
    }
    if (!is_int($c['MAX_ASSIGNMENTS']) || $c['MAX_ASSIGNMENTS'] < 1 || $c['MAX_ASSIGNMENTS'] > 1000000) {
        throw new RuntimeException('MAX_ASSIGNMENTS must be a number from 1 to 1000000.');
    }
}

/** The instance host (e.g. example.service-now.com), as "https://host". */
function servicenowBaseUrl(string $host): string
{
    $host = strtolower(trim(preg_replace('~^https://~i', '', trim($host)), '/'));
    if (preg_match('~^[a-z0-9-]+\.(?:service-now\.com|servicenowservices\.com)$~D', $host) !== 1) {
        throw new RuntimeException('The ServiceNow instance must look like example.service-now.com (no path).');
    }
    return 'https://' . $host;
}

/** Request options that authenticate every Table API call: an OAuth bearer token or basic authentication. */
function servicenowAuth(string $base, array $secrets, string $method): array
{
    if ($method === 'basic') {
        return ['auth' => [$secrets['SERVICENOW_USERNAME'], tpl_base64Secret($secrets['SERVICENOW_PASSWORD_B64'], 'servicenow_password_b64')]];
    }
    $data = tpl_http('POST', $base . '/oauth_token.do', ['form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $secrets['SERVICENOW_CLIENT_ID'],
        'client_secret' => tpl_base64Secret($secrets['SERVICENOW_CLIENT_SECRET_B64'], 'servicenow_client_secret_b64'),
    ]])['data'];
    $token = $data['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('ServiceNow returned no access token.');
    }
    return ['headers' => ['Authorization' => 'Bearer ' . $token]];
}

/**
 * Passes every record of a table to $each, ordered by sys_id, with sysparm_limit/sysparm_offset pages.
 * X-Total-Count must stay the same on every page and match the records received; without it the list
 * ends at the first short page. sys_ids must keep increasing, so records shifting between pages stop the run
 * (checked without keeping every sys_id in memory).
 */
function tableEach(string $base, array $auth, string $table, array $fields, int $limit, callable $each): int
{
    $count = 0;
    $last = '';
    $total = null;
    for ($offset = 0; ; $offset += PAGE_SIZE) {
        $query = http_build_query([
            'sysparm_query' => 'ORDERBYsys_id',
            'sysparm_fields' => implode(',', array_merge(['sys_id'], $fields)),
            'sysparm_limit' => PAGE_SIZE,
            'sysparm_offset' => $offset,
            'sysparm_display_value' => 'false',
            'sysparm_exclude_reference_link' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);
        $response = tpl_http('GET', $base . '/api/now/table/' . $table . '?' . $query, $auth);
        $records = $response['data']['result'] ?? null;
        if (!is_array($records) || !array_is_list($records)) {
            throw new RuntimeException("ServiceNow returned an unexpected $table list.");
        }
        $header = $response['headers']['x-total-count'] ?? null;
        if ($header !== null) {
            if (!ctype_digit($header) || ($total !== null && (int) $header !== $total)) {
                throw new RuntimeException("ServiceNow $table changed during the pull; retry later.");
            }
            $total = (int) $header;
            if ($total > $limit) {
                throw new RuntimeException("ServiceNow reports $total $table records, above the configured limit; raise the limit.");
            }
        }
        foreach ($records as $record) {
            $id = $record['sys_id'] ?? null;
            if (!is_array($record) || !is_string($id) || preg_match('/^[0-9a-f]{32}$/D', $id) !== 1 || strcmp($id, $last) <= 0) {
                throw new RuntimeException("ServiceNow $table list contains an invalid or repeated record; retry later.");
            }
            $last = $id;
            $count++;
            $each($record);
        }
        if ($count > $limit) {
            throw new RuntimeException("ServiceNow $table list exceeds the configured limit; raise the limit.");
        }
        if (count($records) < PAGE_SIZE || ($total !== null && $count >= $total)) {
            break;
        }
    }
    if ($total !== null && $count !== $total) {
        throw new RuntimeException("ServiceNow $table list is incomplete ($count of $total); check read access (ACLs) or retry later.");
    }
    return $count;
}

/** A sys_id reference returned by the Table API with sysparm_exclude_reference_link. */
function sysId(mixed $value, string $what): string
{
    if (!is_string($value) || preg_match('/^[0-9a-f]{32}$/D', $value) !== 1) {
        throw new RuntimeException("ServiceNow returned an invalid $what reference.");
    }
    return $value;
}

/** Stores a role once and records its index for the user: role lists with inheritance can be long. */
function grant(array &$userRoles, array &$roleIndex, array $users, string $userId, string $role): void
{
    if (!isset($users[$userId])) {
        throw new RuntimeException('Access is assigned to a user outside the collected list; users changed during the pull, retry later.');
    }
    $roleIndex[$role] ??= count($roleIndex);
    $userRoles[$userId][] = $roleIndex[$role];
}

function collectFeed(string $base, array $auth, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $users = [];
    tableEach($base, $auth, 'sys_user', ['user_name', 'email', 'name', 'active', 'locked_out'], $config['MAX_USERS'],
        static function (array $u) use (&$users, $config): void {
            if (!in_array($u['active'] ?? null, ['true', 'false'], true)) {
                throw new RuntimeException('ServiceNow user list contains an invalid user.');
            }
            $state = $u['active'] === 'true' ? 'active' : 'inactive';
            if (($u['locked_out'] ?? '') === 'true') {
                $state .= ', locked out';
            }
            $users[$u['sys_id']] = [
                'key' => $u[$config['USER_KEY']] ?? null,
                'name' => ($u['name'] ?? '') !== '' ? $u['name'] : ($u['user_name'] ?? $u['sys_id']),
                'state' => $state,
            ];
        });
    tpl_logInfo(count($users) . ' users found.');

    $userRoles = [];
    $roleIndex = [];
    if (isset($kinds['roles'])) {
        $count = tableEach($base, $auth, 'sys_user_has_role', ['user', 'role', 'role.name', 'inherited'], $config['MAX_ASSIGNMENTS'],
            static function (array $r) use (&$userRoles, &$roleIndex, $users): void {
                $inherited = $r['inherited'] ?? null;
                if (!in_array($inherited, ['true', 'false'], true)) {
                    throw new RuntimeException('ServiceNow role assignment has no inherited flag.');
                }
                $kind = $inherited === 'true' ? 'Role (inherited)' : 'Role';
                $role = tpl_roleString($kind, $r['role.name'] ?? null, sysId($r['role'] ?? null, 'role'));
                grant($userRoles, $roleIndex, $users, sysId($r['user'] ?? null, 'user'), $role);
            });
        tpl_logInfo("$count role assignments collected.");
    }
    if (isset($kinds['groups'])) {
        $count = tableEach($base, $auth, 'sys_user_grmember', ['user', 'group', 'group.name'], $config['MAX_ASSIGNMENTS'],
            static function (array $m) use (&$userRoles, &$roleIndex, $users): void {
                $role = tpl_roleString('Group', $m['group.name'] ?? null, sysId($m['group'] ?? null, 'group'));
                grant($userRoles, $roleIndex, $users, sysId($m['user'] ?? null, 'user'), $role);
            });
        tpl_logInfo("$count group memberships collected.");
    }
    $roleNames = array_flip($roleIndex);
    tpl_logInfo(count($roleNames) . ' distinct roles and groups collected.');

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    foreach ($users as $id => $user) {
        $key = tpl_feedKey($user['key'], $keys);
        $name = mb_strcut(tpl_feedText($user['name'], 'User name'), 0, 120, 'UTF-8');
        $description = tpl_feedText(sprintf('%s; %s; sys_id=%s', $name, $user['state'], $id), 'Description');
        $roles = array_map(static fn (int $i): string => $roleNames[$i], $userRoles[$id] ?? []);
        $bytes = tpl_feedAdd($feed, $key, $description, $roles);
    }
    if ($users === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new RuntimeException('No accounts found; check read access to sys_user, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    tpl_logInfo(sprintf('%d accounts staged (%d bytes).', count($users), $bytes));
    return $feed;
}

// ─── 6. MAIN ────────────────────────────────────────────────────────────────
tpl_installErrorHandler();
tpl_elapsed();
try {
    tpl_log(sprintf('%s v%s', AUTOMATION_ID, AUTOMATION_VERSION));

    tpl_logStep(1, 'Checking configuration');
    validateConfig($config);
    $secrets = array_intersect_key($secrets, array_flip(AUTH_SECRETS[$config['AUTH']]));
    tpl_checkSecrets($secrets);
    $base = servicenowBaseUrl($secrets['SERVICENOW_INSTANCE']);
    $allowed = [];
    foreach (['sys_user', 'sys_user_has_role', 'sys_user_grmember'] as $table) {
        $allowed[] = 'GET ' . $base . '/api/now/table/' . $table;
    }
    if ($config['AUTH'] === 'oauth') {
        $allowed[] = 'POST ' . $base . '/oauth_token.do';
    }
    define('TPL_ALLOWED_REQUESTS', $allowed);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, $config['AUTH'] === 'oauth' ? 'Authenticating with the OAuth client credentials grant' : 'Using basic authentication');
    $auth = servicenowAuth($base, $secrets, $config['AUTH']);

    tpl_logStep(3, 'Collecting users, roles and group memberships');
    $feed = collectFeed($base, $auth, $config);

    tpl_logStep(4, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_log("\nDone.");
    exit(0);
} catch (Throwable $e) {
    tpl_log("\nABORTED: no account review feed was published.\nERROR: " . $e->getMessage());
    exit(1);
}
