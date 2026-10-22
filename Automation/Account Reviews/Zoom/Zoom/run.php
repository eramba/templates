<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Zoom Users and Assigned Access
 *  Technology: Zoom Workplace
 *  id: zoom-account-review        version: 0.1.1 (draft)
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
 *  Read-only: the script never changes Zoom and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
// The client secret is stored base64-encoded and decoded with tpl_base64Secret().
$secrets = [
    'ZOOM_ACCOUNT_ID' => '%SECRET_zoom_account_id%', // Server-to-Server OAuth app: Account ID
    'ZOOM_CLIENT_ID' => '%SECRET_zoom_client_id%', // Server-to-Server OAuth app: Client ID
    'ZOOM_CLIENT_SECRET_B64' => '%SECRET_zoom_client_secret_b64%', // Client secret, base64-encoded
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'email', // Account identifier: email or id (Zoom user ID; pending users have none); no fallback
    'ROLES' => ['role', 'user_type'], // Access listed as roles: role, user_type, groups; [] = accounts only
    'STATUSES' => ['active', 'inactive', 'pending'], // User statuses to list; inactive = deactivated
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum users in the feed; never truncate
    'MAX_OBJECTS' => 2000, // Maximum roles or groups in the account; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'zoom-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const TPL_ALLOWED_REQUESTS = [
    'POST https://zoom.us/oauth/token', // Server-to-Server OAuth token (account_credentials grant)
    'GET https://api.zoom.us/v2/users',
    'GET https://api.zoom.us/v2/roles',
    'GET https://api.zoom.us/v2/groups',
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

// ─── 5. COLLECT: Zoom ───────────────────────────────────────────────────────

const ROLE_KINDS = ['role', 'user_type', 'groups'];
const USER_STATUSES = ['active', 'inactive', 'pending'];
const USER_TYPES = [1 => 'Basic', 2 => 'Licensed', 4 => 'Unassigned without Meetings Basic', 99 => 'None'];
const API = 'https://api.zoom.us/v2';

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['email', 'id'], true)) {
        throw new RuntimeException('USER_KEY must be email or id.');
    }
    $statuses = $c['STATUSES'];
    if (!is_array($statuses) || $statuses === [] || !array_is_list($statuses) || count($statuses) !== count(array_unique($statuses))
        || array_diff($statuses, USER_STATUSES) !== []) {
        throw new RuntimeException('STATUSES must be a non-empty list chosen from: ' . implode(', ', USER_STATUSES) . '.');
    }
    if ($c['USER_KEY'] === 'id' && in_array('pending', $statuses, true)) {
        throw new RuntimeException('Pending Zoom users have no user ID: with USER_KEY = id, remove pending from STATUSES.');
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    foreach (['MAX_USERS' => 5000, 'MAX_OBJECTS' => 10000] as $key => $max) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > $max) {
            throw new RuntimeException("$key must be a number from 1 to $max.");
        }
    }
}

/** Server-to-Server OAuth access token (account_credentials grant). */
function zoomToken(array $secrets): string
{
    foreach (['ZOOM_ACCOUNT_ID', 'ZOOM_CLIENT_ID'] as $key) {
        if (preg_match('/^[A-Za-z0-9_-]{5,128}$/D', $secrets[$key]) !== 1) {
            throw new RuntimeException("Secret '" . strtolower($key) . "' is not a valid Zoom identifier.");
        }
    }
    $secret = tpl_base64Secret($secrets['ZOOM_CLIENT_SECRET_B64'], 'zoom_client_secret_b64');
    $data = tpl_http('POST', 'https://zoom.us/oauth/token', [
        'auth' => [$secrets['ZOOM_CLIENT_ID'], $secret],
        'form_params' => ['grant_type' => 'account_credentials', 'account_id' => $secrets['ZOOM_ACCOUNT_ID']],
    ])['data'];
    $token = $data['access_token'] ?? null;
    if (!is_string($token) || $token === '' || preg_match('/[\x00-\x20\x7f]/', $token) === 1
        || strtolower((string) ($data['token_type'] ?? '')) !== 'bearer') {
        throw new RuntimeException('Zoom returned no usable bearer token.');
    }
    return $token;
}

/**
 * Every record of a Zoom list that paginates with next_page_token and reports total_records.
 * Completeness: the collected count must equal total_records, which must not change between pages.
 */
function zoomList(string $path, array $query, string $field, string $token, int $limit): array
{
    $items = [];
    $tokens = [];
    $total = null;
    $next = '';
    for ($page = 0; ; $page++) {
        if ($page >= 200) {
            throw new RuntimeException("Zoom $field list exceeds 200 pages.");
        }
        $q = $query + ['page_size' => 300];
        if ($next !== '') {
            $q['next_page_token'] = $next;
        }
        $data = tpl_http('GET', API . $path . '?' . http_build_query($q), ['headers' => ['Authorization' => 'Bearer ' . $token]])['data'];
        $records = $data[$field] ?? null;
        if (!is_array($records) || !array_is_list($records) || !is_int($data['total_records'] ?? null) || $data['total_records'] < 0) {
            throw new RuntimeException("Zoom returned an incomplete $field page.");
        }
        if ($total !== null && $data['total_records'] !== $total) {
            throw new RuntimeException("Zoom $field changed during the pull; retry later.");
        }
        $total = $data['total_records'];
        if ($total > $limit) {
            throw new RuntimeException("More Zoom $field than the configured limit; raise it.");
        }
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new RuntimeException("Zoom returned an invalid $field record.");
            }
            $items[] = $record;
        }
        $next = $data['next_page_token'] ?? '';
        if (!is_string($next)) {
            throw new RuntimeException('Zoom pagination token is invalid.');
        }
        if ($next === '') {
            break;
        }
        if ($records === [] || isset($tokens[$next])) {
            throw new RuntimeException('Zoom pagination token repeats or returns empty pages; retry later.');
        }
        $tokens[$next] = true;
    }
    if (count($items) !== $total || count($items) > $limit) {
        throw new RuntimeException("Zoom $field count does not match the reported total; retry later.");
    }
    return $items;
}

/** Name of each record, keyed by its ID. Used for roles and groups. */
function namesById(array $records, string $what): array
{
    $names = [];
    foreach ($records as $record) {
        $id = $record['id'] ?? null;
        if (!is_string($id) || $id === '' || strlen($id) > 64 || isset($names[$id])) {
            throw new RuntimeException("Zoom returned a $what without a valid, unique ID.");
        }
        $names[$id] = tpl_feedText($record['name'] ?? null, "Zoom $what name");
    }
    return $names;
}

/** Roles of the account. GET /roles is not paginated; its total_records must match the list. */
function listRoles(string $token, array $config): array
{
    $data = tpl_http('GET', API . '/roles', ['headers' => ['Authorization' => 'Bearer ' . $token]])['data'];
    $roles = $data['roles'] ?? null;
    if (!is_array($roles) || !array_is_list($roles) || count($roles) > $config['MAX_OBJECTS']
        || (isset($data['total_records']) && $data['total_records'] !== count($roles))) {
        throw new RuntimeException('Zoom returned an incomplete or too large role list.');
    }
    return namesById($roles, 'role');
}

/** Users of every selected status. Pending users have no ID and are identified by email. */
function listUsers(string $token, array $config): array
{
    $users = [];
    foreach ($config['STATUSES'] as $status) {
        $list = zoomList('/users', ['status' => $status], 'users', $token, $config['MAX_USERS']);
        foreach ($list as $user) {
            if (($user['status'] ?? $status) !== $status) {
                throw new RuntimeException('Zoom returned a user with an unexpected status.');
            }
            $user['status'] = $status;
            $users[] = $user;
        }
        tpl_logInfo(sprintf('%d %s users found.', count($list), $status));
    }
    if (count($users) > $config['MAX_USERS']) {
        throw new RuntimeException('More users than MAX_USERS; raise the limit.');
    }
    return $users;
}

function collectFeed(string $token, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $users = listUsers($token, $config);
    $roleNames = isset($kinds['role']) ? listRoles($token, $config) : [];
    $groupNames = isset($kinds['groups']) ? namesById(zoomList('/groups', [], 'groups', $token, $config['MAX_OBJECTS']), 'group') : [];
    if (isset($kinds['role'])) {
        tpl_logInfo(count($roleNames) . ' roles collected.');
    }
    if (isset($kinds['groups'])) {
        tpl_logInfo(count($groupNames) . ' groups collected.');
    }

    $feed = tpl_feedOpen();
    $keys = [];
    $ids = [];
    $bytes = 0;
    $noRole = 0;
    foreach ($users as $user) {
        $id = $user['id'] ?? null;
        if ($user['status'] !== 'pending' || $id !== null) {
            if (!is_string($id) || $id === '' || strlen($id) > 64 || isset($ids[$id])) {
                throw new RuntimeException('Zoom returned a user without a valid, unique ID; retry later.');
            }
            $ids[$id] = true;
        }
        $key = tpl_feedKey($config['USER_KEY'] === 'id' ? $id : ($user['email'] ?? null), $keys);
        $names = array_filter([$user['display_name'] ?? null, trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))],
            static fn ($n): bool => is_string($n) && trim($n) !== '');
        $name = mb_strcut(tpl_feedText(reset($names) ?: 'no name', 'User name'), 0, 150, 'UTF-8');
        $type = $user['type'] ?? null;
        if (!is_int($type) || !isset(USER_TYPES[$type])) {
            throw new RuntimeException('Zoom returned a user with an unknown user type.');
        }

        $roles = [];
        if (isset($kinds['role'])) {
            $roleId = $user['role_id'] ?? null;
            if ($roleId === null && $user['status'] === 'pending') {
                $noRole++; // Pending invitations may not carry a role yet (README §3)
            } elseif (!is_string($roleId) || !isset($roleNames[$roleId])) {
                throw new RuntimeException('A user has a role that is not in the role list; retry later.');
            } else {
                $roles[] = tpl_roleString('Role', $roleNames[$roleId], $roleId);
            }
        }
        if (isset($kinds['user_type'])) {
            $roles[] = tpl_roleString('User type', USER_TYPES[$type], (string) $type);
        }
        if (isset($kinds['groups'])) {
            $groupIds = $user['group_ids'] ?? [];
            if (!is_array($groupIds) || !array_is_list($groupIds) || count($groupIds) > $config['MAX_OBJECTS']) {
                throw new RuntimeException('Zoom returned an invalid group list for a user.');
            }
            foreach ($groupIds as $groupId) {
                if (!is_string($groupId) || !isset($groupNames[$groupId])) {
                    throw new RuntimeException('A user belongs to a group that is not in the group list; retry later.');
                }
                $roles[] = tpl_roleString('Group', $groupNames[$groupId], $groupId);
            }
        }
        $description = sprintf('%s; %s; %s; id=%s', $name, USER_TYPES[$type], $user['status'], $id ?? 'none (pending)');
        $bytes = tpl_feedAdd($feed, $key, tpl_feedText($description, 'Description'), $roles);
    }
    if ($users === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new TplEmptyFeedException('No accounts found; check the app scopes, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    if ($noRole > 0) {
        tpl_logInfo("$noRole pending users have no role yet.");
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
    tpl_checkSecrets($secrets);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Requesting a Zoom access token');
    $token = zoomToken($secrets);

    tpl_logStep(3, 'Collecting users, roles, user types and groups');
    $feed = collectFeed($token, $config);

    tpl_logStep(4, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
