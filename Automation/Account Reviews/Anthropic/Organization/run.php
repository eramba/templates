<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Anthropic Organization Members and Workspace Access
 *  Technology: Claude Console (Anthropic API)
 *  id: anthropic-account-review        version: 0.1.0 (draft)
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
 *  Read-only: the script never changes Anthropic and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'ANTHROPIC_ADMIN_KEY' => '%SECRET_anthropic_admin_key%', // Admin API key, sk-ant-admin01-… (README §5)
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'email', // Account identifier: email or id (no fallback)
    'ROLES' => ['org_role', 'workspaces'], // Access listed as roles; [] = accounts only (README §7)
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum accounts in the feed; never truncate
    'MAX_OBJECTS' => 2000, // Maximum workspaces, and members per workspace; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'anthropic-account-review';
const AUTOMATION_VERSION = '0.1.0';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const TPL_ALLOWED_REQUESTS = [
    'GET https://api.anthropic.com/v1/organizations/users', // Organization members
    'GET https://api.anthropic.com/v1/organizations/workspaces', // Workspaces and workspace members
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
// ─── shared:providers/anthropic v1 (edit Automation/_shared/providers/anthropic.php, then run Automation/tools/sync.php) ───
/**
 * Every item of an Anthropic Admin API list, e.g. tpl_anthropicList('/v1/organizations/users', $adminKey, 2000).
 * Pages are requested with limit=100 and after_id=<last_id> while has_more is true. Items are identified by
 * $idField ('user_id' for workspace members). A page that says there is more but gives no cursor, a repeated
 * cursor, a duplicate item or more than $limit items stops the run.
 */
function tpl_anthropicList(string $path, string $adminKey, int $limit, array $query = [], string $idField = 'id'): array
{
    $headers = ['x-api-key' => $adminKey, 'anthropic-version' => '2023-06-01'];
    $items = [];
    $after = null;
    $cursors = [];
    for ($page = 0; ; $page++) {
        if ($page >= 200) {
            throw new RuntimeException('Anthropic pagination exceeds 200 pages; narrow the scope.');
        }
        $params = $query + ['limit' => 100] + ($after === null ? [] : ['after_id' => $after]);
        $url = 'https://api.anthropic.com' . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $data = tpl_http('GET', $url, ['headers' => $headers])['data'];
        if (!is_array($data['data'] ?? null) || !array_is_list($data['data']) || !is_bool($data['has_more'] ?? null)) {
            throw new RuntimeException('Anthropic returned an unexpected list.');
        }
        foreach ($data['data'] as $item) {
            $id = is_array($item) ? ($item[$idField] ?? null) : null;
            if (!is_string($id) || $id === '') {
                throw new RuntimeException('Anthropic list contains an invalid item.');
            }
            if (isset($items[$id])) {
                throw new RuntimeException('Anthropic list contains a duplicate item; the organization changed during the pull, retry later.');
            }
            $items[$id] = $item;
            if (count($items) > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        }
        if (!$data['has_more']) {
            return array_values($items);
        }
        $after = $data['last_id'] ?? null;
        if (!is_string($after) || $after === '' || isset($cursors[$after])) {
            throw new RuntimeException('Anthropic pagination cursor is missing or repeats; retry later.');
        }
        $cursors[$after] = true;
    }
}
// ─── end shared:providers/anthropic v1 sha256:dc61f917fde4c8cf ───
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

// ─── 5. COLLECT: Anthropic ──────────────────────────────────────────────────

const ROLE_KINDS = ['org_role', 'workspaces'];

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['email', 'id'], true)) {
        throw new RuntimeException('USER_KEY must be email or id.');
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    foreach (['MAX_USERS', 'MAX_OBJECTS'] as $key) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > 10000) {
            throw new RuntimeException("$key must be a number from 1 to 10000.");
        }
    }
}

/** A role value such as "workspace_developer" as a readable label ("Workspace developer"). The raw value stays in the role ID. */
function roleLabel(mixed $role, string $what): array
{
    $role = tpl_feedText($role, $what);
    return [ucfirst(str_replace('_', ' ', $role)), $role];
}

/**
 * Workspace memberships of the active workspaces, keyed by user ID. Archived workspaces grant no access, and
 * the Default Workspace has no member list of its own (access to it follows the organization role).
 */
function collectWorkspaces(string $key, array $users, array $config): array
{
    $roles = [];
    $workspaces = tpl_anthropicList('/v1/organizations/workspaces', $key, $config['MAX_OBJECTS']);
    $memberships = 0;
    foreach ($workspaces as $workspace) {
        if (($workspace['archived_at'] ?? null) !== null) {
            continue; // Listed without include_archived, so this only skips a workspace archived during the pull
        }
        $path = '/v1/organizations/workspaces/' . rawurlencode($workspace['id']) . '/members';
        foreach (tpl_anthropicList($path, $key, $config['MAX_OBJECTS'], [], 'user_id') as $member) {
            if (($member['workspace_id'] ?? $workspace['id']) !== $workspace['id']) {
                throw new RuntimeException('Anthropic returned a member of another workspace.');
            }
            if (!isset($users[$member['user_id']])) {
                throw new RuntimeException('A workspace member is not in the organization list; members changed during the pull, retry later.');
            }
            [$label, $role] = roleLabel($member['workspace_role'] ?? null, 'Workspace role');
            $roles[$member['user_id']][] = tpl_roleString('Workspace', $workspace['name'] ?? $workspace['id'], $workspace['id'] . ' / ' . $role);
            $memberships++;
        }
    }
    tpl_logInfo(sprintf('%d workspaces and %d workspace memberships collected.', count($workspaces), $memberships));
    return $roles;
}

function collectFeed(string $key, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $users = [];
    foreach (tpl_anthropicList('/v1/organizations/users', $key, $config['MAX_USERS']) as $user) {
        $users[$user['id']] = $user;
    }
    tpl_logInfo(count($users) . ' organization members found.');
    $roles = isset($kinds['workspaces']) ? collectWorkspaces($key, $users, $config) : [];

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    foreach ($users as $id => $user) {
        $account = tpl_feedKey($config['USER_KEY'] === 'id' ? $id : ($user['email'] ?? null), $keys);
        $userRoles = $roles[$id] ?? [];
        $orgRole = is_string($user['role'] ?? null) && $user['role'] !== '' ? $user['role'] : null;
        if (isset($kinds['org_role'])) {
            [$label, $value] = roleLabel($orgRole, 'Organization role');
            $userRoles[] = tpl_roleString('Organization role', $label, $value);
        }
        if (count(array_unique($userRoles)) > $config['MAX_OBJECTS']) {
            throw new RuntimeException('Combined account access limit exceeded.');
        }
        $name = mb_strcut(tpl_feedText($user['name'] ?? $user['email'] ?? $id, 'User name'), 0, 150, 'UTF-8');
        $description = implode('; ', array_merge([$name], $orgRole === null ? [] : ["organization $orgRole"], ["id=$id"]));
        $bytes = tpl_feedAdd($feed, $account, tpl_feedText($description, 'Description'), $userRoles);
    }
    if ($users === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new RuntimeException('No accounts found; set ALLOW_EMPTY_FEED if zero accounts is intended.');
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

    tpl_logStep(2, 'Collecting organization members and workspace access');
    $feed = collectFeed($secrets['ANTHROPIC_ADMIN_KEY'], $config);

    tpl_logStep(3, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_log("\nDone.");
    exit(0);
} catch (Throwable $e) {
    tpl_log("\nABORTED: no account review feed was published.\nERROR: " . $e->getMessage());
    exit(1);
}
