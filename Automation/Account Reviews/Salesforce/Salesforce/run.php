<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Salesforce – Accounts
 *  Technology: Salesforce Platform (Sales Cloud, Service Cloud and other orgs)
 *  id: salesforce-account-review        version: 0.1.1 (draft)
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
 *  Read-only: the script only runs SOQL queries, never changes Salesforce and
 *  makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'SALESFORCE_MY_DOMAIN' => '%SECRET_salesforce_my_domain%', // My Domain host, e.g. example.my.salesforce.com
    'SALESFORCE_CLIENT_ID' => '%SECRET_salesforce_client_id%', // Consumer key of the external client app
    'SALESFORCE_CLIENT_SECRET_B64' => '%SECRET_salesforce_client_secret_b64%', // Base64 of the consumer secret
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'email', // Account identifier: email, username or id (no fallback)
    'ROLES' => ['profile', 'role', 'permission_sets', 'permission_set_groups'], // Access listed as roles; [] = accounts only
    'USER_TYPES' => [], // Salesforce user types to include, e.g. ['Standard']; [] = every user type
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum users in the feed; never truncate
    'MAX_ASSIGNMENTS' => 100000, // Maximum permission set (group) assignments; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'salesforce-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const SALESFORCE_API = '/services/data/v62.0'; // REST API version (Winter '25)
// TPL_ALLOWED_REQUESTS is defined in MAIN from the My Domain host: the token POST and GET <host>/services/data/v62.0/query.

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

// ─── 5. COLLECT: Salesforce ─────────────────────────────────────────────────
// Collect the WHOLE population and every access type the README documents.
// Throw on anything incomplete or unexpected: never skip an account or a role silently.

const ROLE_KINDS = ['profile', 'role', 'permission_sets', 'permission_set_groups'];
const USER_KEY_FIELDS = ['email' => 'Email', 'username' => 'Username', 'id' => 'Id'];

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!is_string($c['USER_KEY']) || !isset(USER_KEY_FIELDS[$c['USER_KEY']])) {
        throw new RuntimeException('USER_KEY must be email, username or id.');
    }
    if (!is_array($c['USER_TYPES']) || !array_is_list($c['USER_TYPES'])) {
        throw new RuntimeException('USER_TYPES must be a list of Salesforce user types, or empty for all.');
    }
    foreach ($c['USER_TYPES'] as $type) {
        if (!is_string($type) || preg_match('/^[A-Za-z]{1,40}$/D', $type) !== 1) {
            throw new RuntimeException('USER_TYPES entries must be Salesforce user type names such as Standard.');
        }
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    if (!is_int($c['MAX_USERS']) || $c['MAX_USERS'] < 1 || $c['MAX_USERS'] > 5000) {
        throw new RuntimeException('MAX_USERS must be a number from 1 to 5000.');
    }
    if (!is_int($c['MAX_ASSIGNMENTS']) || $c['MAX_ASSIGNMENTS'] < 1 || $c['MAX_ASSIGNMENTS'] > 500000) {
        throw new RuntimeException('MAX_ASSIGNMENTS must be a number from 1 to 500000.');
    }
}

/** The My Domain host (e.g. example.my.salesforce.com or example--uat.sandbox.my.salesforce.com), as "https://host". */
function salesforceBaseUrl(string $host): string
{
    $host = strtolower(trim(preg_replace('~^https://~i', '', trim($host)), '/'));
    if (preg_match('~^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.my\.salesforce\.com$~D', $host) !== 1) {
        throw new RuntimeException('The Salesforce My Domain must look like example.my.salesforce.com (no path).');
    }
    return 'https://' . $host;
}

/** Access token through the OAuth 2.0 client credentials flow of an external client app (or connected app). */
function salesforceToken(string $base, string $clientId, string $clientSecret): string
{
    $data = tpl_http('POST', $base . '/services/oauth2/token', ['form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]])['data'];
    $token = $data['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Salesforce returned no access token.');
    }
    $instance = $data['instance_url'] ?? null;
    if ($instance !== null && (!is_string($instance) || strtolower(rtrim($instance, '/')) !== $base)) {
        throw new RuntimeException('Salesforce issued the token for another instance URL; set salesforce_my_domain to the My Domain host.');
    }
    return $token;
}

/**
 * Runs one SOQL query and passes every record to $each, following nextRecordsUrl until done.
 * totalSize must stay the same on every page and match the records received, so a partial or
 * changing result stops the run. Returns the number of records.
 */
function soqlEach(string $base, string $token, string $soql, int $limit, string $what, callable $each): int
{
    $path = SALESFORCE_API . '/query';
    $url = $base . $path . '?q=' . rawurlencode($soql);
    $headers = ['Authorization' => 'Bearer ' . $token];
    $total = null;
    $count = 0;
    $seen = [];
    while (true) {
        $data = tpl_http('GET', $url, ['headers' => $headers])['data'];
        if (!is_int($data['totalSize'] ?? null) || !is_bool($data['done'] ?? null) || !is_array($data['records'] ?? null)
            || !array_is_list($data['records'])) {
            throw new RuntimeException("Salesforce returned an unexpected $what list.");
        }
        if ($total === null) {
            $total = $data['totalSize'];
            if ($total > $limit) {
                throw new RuntimeException("Salesforce reports $total $what, above the configured limit; narrow the scope or raise the limit.");
            }
        } elseif ($data['totalSize'] !== $total) {
            throw new RuntimeException("Salesforce $what changed during the pull; retry later.");
        }
        foreach ($data['records'] as $record) {
            if (!is_array($record)) {
                throw new RuntimeException("Salesforce $what list contains an invalid record.");
            }
            $each($record);
            $count++;
        }
        if ($count > $total) {
            throw new RuntimeException("Salesforce returned more $what than it reported; retry later.");
        }
        if ($data['done']) {
            break;
        }
        $next = $data['nextRecordsUrl'] ?? null;
        if (!is_string($next) || preg_match('~^' . preg_quote($path, '~') . '/[A-Za-z0-9-]{1,100}$~D', $next) !== 1
            || isset($seen[$next]) || count($seen) >= 1000) {
            throw new RuntimeException('Salesforce pagination link is missing, invalid or repeats; stopping.');
        }
        $seen[$next] = true;
        $url = $base . $next;
    }
    if ($count !== $total) {
        throw new RuntimeException("Salesforce $what list is incomplete ($count of $total); retry later.");
    }
    return $count;
}

/** A Salesforce record ID (15 or 18 characters). */
function salesforceId(mixed $value, string $what): string
{
    if (!is_string($value) || preg_match('/^[A-Za-z0-9]{15}(?:[A-Za-z0-9]{3})?$/D', $value) !== 1) {
        throw new RuntimeException("Salesforce returned an invalid $what ID.");
    }
    return $value;
}

/** Stores a role once and records its index for the user: assignment lists can be long. */
function grant(array &$userRoles, array &$roleIndex, string $userId, string $role): void
{
    $roleIndex[$role] ??= count($roleIndex);
    $userRoles[$userId][] = $roleIndex[$role];
}

function collectFeed(string $base, string $token, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $typeFilter = $config['USER_TYPES'] === [] ? '' : " IN ('" . implode("', '", $config['USER_TYPES']) . "')";

    $fields = ['Id', 'Username', 'Email', 'Name', 'IsActive', 'UserType'];
    if (isset($kinds['profile'])) {
        array_push($fields, 'ProfileId', 'Profile.Name');
    }
    if (isset($kinds['role'])) {
        array_push($fields, 'UserRoleId', 'UserRole.Name');
    }
    $soql = 'SELECT ' . implode(', ', $fields) . ' FROM User' . ($typeFilter === '' ? '' : ' WHERE UserType' . $typeFilter) . ' ORDER BY Id';

    $users = [];
    $userRoles = [];
    $roleIndex = [];
    $addUser = static function (array $u) use (&$users, &$userRoles, &$roleIndex, $kinds, $config): void {
        $id = salesforceId($u['Id'] ?? null, 'user');
        if (isset($users[$id]) || !is_bool($u['IsActive'] ?? null)) {
            throw new RuntimeException('Salesforce user list contains a duplicate or invalid user.');
        }
        $users[$id] = [
            'key' => $u[USER_KEY_FIELDS[$config['USER_KEY']]] ?? null,
            'name' => $u['Name'] ?? ($u['Username'] ?? $id),
            'state' => $u['IsActive'] ? 'active' : 'inactive',
            'type' => is_string($u['UserType'] ?? null) ? $u['UserType'] : 'unknown type',
        ];
        if (isset($kinds['profile'])) {
            $profileId = salesforceId($u['ProfileId'] ?? null, 'profile');
            grant($userRoles, $roleIndex, $id, tpl_roleString('Profile', $u['Profile']['Name'] ?? null, $profileId));
        }
        if (isset($kinds['role']) && ($u['UserRoleId'] ?? null) !== null) {
            $roleId = salesforceId($u['UserRoleId'], 'role');
            grant($userRoles, $roleIndex, $id, tpl_roleString('Role', $u['UserRole']['Name'] ?? null, $roleId));
        }
    };
    soqlEach($base, $token, $soql, $config['MAX_USERS'], 'users', $addUser);
    tpl_logInfo(count($users) . ' users found.');

    if (isset($kinds['permission_sets']) || isset($kinds['permission_set_groups'])) {
        // Profile-owned permission sets are the profile itself, already listed as "Profile".
        $where = ['PermissionSet.IsOwnedByProfile = false'];
        if (!isset($kinds['permission_set_groups'])) {
            $where[] = 'PermissionSetGroupId = null';
        } elseif (!isset($kinds['permission_sets'])) {
            $where[] = 'PermissionSetGroupId != null';
        }
        if ($typeFilter !== '') {
            $where[] = 'Assignee.UserType' . $typeFilter;
        }
        $soql = 'SELECT Id, AssigneeId, PermissionSetId, PermissionSet.Label, PermissionSetGroupId, PermissionSetGroup.MasterLabel'
            . ' FROM PermissionSetAssignment WHERE ' . implode(' AND ', $where) . ' ORDER BY Id';
        $assignments = soqlEach($base, $token, $soql, $config['MAX_ASSIGNMENTS'], 'permission set assignments',
            static function (array $a) use (&$userRoles, &$roleIndex, $users): void {
                $userId = salesforceId($a['AssigneeId'] ?? null, 'assignee');
                if (!isset($users[$userId])) {
                    throw new RuntimeException('Access is assigned to a user outside the collected list; users changed during the pull, retry.');
                }
                if (($a['PermissionSetGroupId'] ?? null) !== null) {
                    $groupId = salesforceId($a['PermissionSetGroupId'], 'permission set group');
                    $role = tpl_roleString('Permission set group', $a['PermissionSetGroup']['MasterLabel'] ?? null, $groupId);
                } else {
                    $setId = salesforceId($a['PermissionSetId'] ?? null, 'permission set');
                    $role = tpl_roleString('Permission set', $a['PermissionSet']['Label'] ?? null, $setId);
                }
                grant($userRoles, $roleIndex, $userId, $role);
            });
        tpl_logInfo("$assignments permission set assignments collected.");
    }
    $roleNames = array_flip($roleIndex);
    tpl_logInfo(count($roleNames) . ' distinct roles collected.');

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    foreach ($users as $id => $user) {
        $key = tpl_feedKey($user['key'], $keys);
        $name = mb_strcut(tpl_feedText($user['name'], 'User name'), 0, 120, 'UTF-8');
        $description = tpl_feedText(sprintf('%s; %s; %s; id=%s', $name, $user['state'], $user['type'], $id), 'Description');
        $roles = array_map(static fn (int $i): string => $roleNames[$i], $userRoles[$id] ?? []);
        $bytes = tpl_feedAdd($feed, $key, $description, $roles);
    }
    if ($users === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new TplEmptyFeedException('No accounts found; check USER_TYPES, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
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
    $base = salesforceBaseUrl($secrets['SALESFORCE_MY_DOMAIN']);
    define('TPL_ALLOWED_REQUESTS', ['POST ' . $base . '/services/oauth2/token', 'GET ' . $base . SALESFORCE_API . '/query']);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Authenticating with the OAuth 2.0 client credentials flow');
    $clientSecret = tpl_base64Secret($secrets['SALESFORCE_CLIENT_SECRET_B64'], 'salesforce_client_secret_b64');
    $token = salesforceToken($base, $secrets['SALESFORCE_CLIENT_ID'], $clientSecret);

    tpl_logStep(3, 'Collecting users, profiles, roles and permission sets');
    $feed = collectFeed($base, $token, $config);

    tpl_logStep(4, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
