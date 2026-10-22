<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Okta Accounts and Assigned Access
 *  Technology: Okta Workforce Identity
 *  id: okta-account-review        version: 0.1.1 (draft)
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
 *  Read-only: the script never changes Okta and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'OKTA_ORG_URL' => '%SECRET_okta_org_url%', // e.g. https://example.okta.com
    'OKTA_API_TOKEN' => '%SECRET_okta_api_token%', // Token of a read-only administrator
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'login', // Account identifier: login, email or id (no fallback)
    'ROLES' => ['groups', 'applications', 'admin_roles'], // Access listed as roles; [] = accounts only (README §7)
    'INCLUDE_DEPROVISIONED' => true, // Also list deactivated (DEPROVISIONED) users, which Okta hides by default
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum accounts in the feed; never truncate
    'MAX_OBJECTS' => 2000, // Maximum records per API list (groups, apps, members); never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'okta-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
// TPL_ALLOWED_REQUESTS is defined in MAIN from the org URL: only GET https://<org>/api/v1/ is allowed.

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
// ─── shared:providers/okta v1 (edit Automation/_shared/providers/okta.php, then run Automation/tools/sync.php) ───
/** The Okta org URL (e.g. https://example.okta.com), as "https://host" without path, port or credentials. */
function tpl_oktaBaseUrl(string $url): string
{
    $url = rtrim(trim($url), '/');
    if (preg_match('~^https://[a-z0-9-]+(?:\.[a-z0-9-]+)+$~D', $url) !== 1) {
        throw new RuntimeException('The Okta org URL must look like https://example.okta.com (HTTPS, no path).');
    }
    return $url;
}

/** One Okta object, e.g. tpl_oktaGet($base, '/api/v1/users/<id>', $token). */
function tpl_oktaGet(string $base, string $path, string $token): array
{
    return tpl_http('GET', $base . $path, ['headers' => ['Authorization' => 'SSWS ' . $token]])['data'];
}

/**
 * Every item of an Okta list, following the Link: <…>; rel="next" header to the last page.
 * Okta returns no totals, so completeness relies on reaching a page without a next link.
 */
function tpl_oktaList(string $base, string $path, string $token, int $limit): array
{
    $first = $base . $path;
    $url = $first;
    $seen = [];
    $items = [];
    while ($url !== null) {
        if (isset($seen[$url]) || count($seen) >= 200) {
            throw new RuntimeException('Okta pagination repeats or exceeds 200 pages; narrow the scope.');
        }
        $seen[$url] = true;
        $response = tpl_http('GET', $url, ['headers' => ['Authorization' => 'SSWS ' . $token]]);
        if (!array_is_list($response['data'])) {
            throw new RuntimeException('Okta returned an unexpected list.');
        }
        foreach ($response['data'] as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null) || $item['id'] === '') {
                throw new RuntimeException('Okta list contains an invalid item.');
            }
            if (isset($items[$item['id']])) {
                throw new RuntimeException('Okta list contains a duplicate item.');
            }
            $items[$item['id']] = $item;
            if (count($items) > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        }
        $url = null;
        foreach (explode(',', $response['headers']['link'] ?? '') as $link) {
            if (preg_match('~^\s*<([^>]+)>\s*;\s*rel="next"\s*$~', $link, $m) === 1) {
                tpl_assertNextUrl($first, $m[1]);
                $url = $m[1];
            }
        }
    }
    return array_values($items);
}
// ─── end shared:providers/okta v1 sha256:3193527228e2d298 ───
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

// ─── 5. COLLECT: Okta ───────────────────────────────────────────────────────

const ROLE_KINDS = ['groups', 'applications', 'admin_roles'];

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['login', 'email', 'id'], true)) {
        throw new RuntimeException('USER_KEY must be login, email or id.');
    }
    foreach (['INCLUDE_DEPROVISIONED', 'ALLOW_EMPTY_FEED'] as $key) {
        if (!is_bool($c[$key])) {
            throw new RuntimeException("$key must be true or false.");
        }
    }
    foreach (['MAX_USERS', 'MAX_OBJECTS'] as $key) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > 10000) {
            throw new RuntimeException("$key must be a number from 1 to 10000.");
        }
    }
}

/** All users. Okta's user list omits DEPROVISIONED users, so they are fetched with a second, filtered list. */
function listUsers(string $base, string $token, array $config): array
{
    $users = [];
    $lists = [tpl_oktaList($base, '/api/v1/users?limit=200', $token, $config['MAX_USERS'])];
    if ($config['INCLUDE_DEPROVISIONED']) {
        $lists[] = tpl_oktaList($base, '/api/v1/users?limit=200&search=' . rawurlencode('status eq "DEPROVISIONED"'), $token, $config['MAX_USERS']);
    }
    foreach (array_merge(...$lists) as $user) {
        if (isset($users[$user['id']])) {
            throw new RuntimeException('A user appears twice; the directory changed during the pull, retry later.');
        }
        $users[$user['id']] = $user;
    }
    if (count($users) > $config['MAX_USERS']) {
        throw new RuntimeException('More users than MAX_USERS; raise the limit.');
    }
    return $users;
}

/**
 * Whether an assigned user belongs to the collected population. Deactivated users are skipped when
 * INCLUDE_DEPROVISIONED is off; any other unknown user means the directory changed during the pull.
 */
function inPopulation(array $users, string $userId, ?string $status, array $config): bool
{
    if (isset($users[$userId])) {
        return true;
    }
    if (!$config['INCLUDE_DEPROVISIONED'] && $status === 'DEPROVISIONED') {
        return false;
    }
    throw new RuntimeException('Access is assigned to a user outside the collected population; the directory changed during the pull, retry later.');
}

/** Adds $role to every listed member that belongs to the population. */
function grant(array &$roles, array $users, array $members, string $role, array $config): void
{
    foreach ($members as $member) {
        if (inPopulation($users, $member['id'], $member['status'] ?? null, $config)) {
            $roles[$member['id']][] = $role;
        }
    }
}

/** Admin role as a role string. Standard roles are identified by type, custom roles by their role ID. */
function adminRole(array $role): string
{
    $type = $role['type'] ?? null;
    if (!is_string($type) || $type === '') {
        throw new RuntimeException('An Okta admin role has no type.');
    }
    $id = $type === 'CUSTOM' ? ($role['role'] ?? $role['id'] ?? null) : $type;
    return tpl_roleString('Admin role', $role['label'] ?? $type, tpl_feedText($id, 'Admin role ID'));
}

/** Group memberships, application assignments and admin roles, keyed by user ID. */
function collectRoles(string $base, string $token, array $users, array $config): array
{
    $roles = [];
    $limit = $config['MAX_OBJECTS'];
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);

    $groups = isset($kinds['groups']) || isset($kinds['admin_roles']) ? tpl_oktaList($base, '/api/v1/groups?limit=200', $token, $limit) : [];
    foreach ($groups as $group) {
        $isOktaGroup = ($group['type'] ?? '') === 'OKTA_GROUP'; // Admin roles can only be assigned to Okta groups
        if (!isset($kinds['groups']) && !$isOktaGroup) {
            continue;
        }
        $members = tpl_oktaList($base, '/api/v1/groups/' . rawurlencode($group['id']) . '/users?limit=200', $token, $limit);
        if (isset($kinds['groups'])) {
            grant($roles, $users, $members, tpl_roleString('Group', $group['profile']['name'] ?? null, $group['id']), $config);
        }
        if ($isOktaGroup && isset($kinds['admin_roles'])) {
            foreach (tpl_oktaList($base, '/api/v1/groups/' . rawurlencode($group['id']) . '/roles', $token, $limit) as $role) {
                grant($roles, $users, $members, adminRole($role), $config);
            }
        }
    }
    tpl_logInfo(count($groups) . ' groups collected.');

    $apps = isset($kinds['applications']) ? tpl_oktaList($base, '/api/v1/apps?limit=200', $token, $limit) : [];
    foreach ($apps as $app) {
        $assigned = tpl_oktaList($base, '/api/v1/apps/' . rawurlencode($app['id']) . '/users?limit=200', $token, $limit);
        grant($roles, $users, $assigned, tpl_roleString('Application', $app['label'] ?? null, $app['id']), $config);
    }
    tpl_logInfo(count($apps) . ' applications collected.');

    // Users holding admin roles directly. The response is {"value": [...], "_links": {"next": {"href": …}}}.
    $first = $base . '/api/v1/iam/assignees/users?limit=200';
    $url = isset($kinds['admin_roles']) ? $first : null;
    $admins = 0;
    for ($page = 0; $url !== null; $page++) {
        if ($page >= 100) {
            throw new RuntimeException('Okta role assignees exceed 100 pages.');
        }
        $data = tpl_http('GET', $url, ['headers' => ['Authorization' => 'SSWS ' . $token]])['data'];
        if (!is_array($data['value'] ?? null)) {
            throw new RuntimeException('Okta returned an unexpected list of role assignees.');
        }
        foreach ($data['value'] as $assignee) {
            $userId = is_array($assignee) ? ($assignee['id'] ?? null) : null;
            if (!is_string($userId) || $userId === '') {
                throw new RuntimeException('Okta returned an invalid role assignee.');
            }
            if (!inPopulation($users, $userId, isset($users[$userId]) ? null : 'DEPROVISIONED', $config)) {
                continue;
            }
            $admins++;
            foreach (tpl_oktaList($base, '/api/v1/users/' . rawurlencode($userId) . '/roles', $token, $limit) as $role) {
                $roles[$userId][] = adminRole($role);
            }
        }
        $url = $data['_links']['next']['href'] ?? null;
        if ($url !== null) {
            if (!is_string($url)) {
                throw new RuntimeException('Okta pagination link is invalid.');
            }
            tpl_assertNextUrl($first, $url);
        }
    }
    tpl_logInfo("$admins users hold admin roles directly.");
    return $roles;
}

function collectFeed(string $base, string $token, array $config)
{
    $users = listUsers($base, $token, $config);
    tpl_logInfo(count($users) . ' users found.');
    $roles = collectRoles($base, $token, $users, $config);
    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    foreach ($users as $id => $user) {
        $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
        $key = tpl_feedKey($config['USER_KEY'] === 'id' ? $id : ($profile[$config['USER_KEY']] ?? null), $keys);
        $status = tpl_feedText($user['status'] ?? null, 'User status');
        $name = trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));
        $name = mb_strcut(tpl_feedText($profile['displayName'] ?? ($name !== '' ? $name : ($profile['login'] ?? null)), 'User name'), 0, 150, 'UTF-8');
        $userRoles = $roles[$id] ?? [];
        if (count(array_unique($userRoles)) > $config['MAX_OBJECTS']) {
            throw new RuntimeException('Combined account access limit exceeded.');
        }
        $bytes = tpl_feedAdd($feed, $key, tpl_feedText("$name; $status; id=$id", 'Description'), $userRoles);
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
    $base = tpl_oktaBaseUrl($secrets['OKTA_ORG_URL']);
    define('TPL_ALLOWED_REQUESTS', ['GET ' . $base . '/api/v1/']);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Collecting users, groups, applications and admin roles');
    $feed = collectFeed($base, $secrets['OKTA_API_TOKEN'], $config);

    tpl_logStep(3, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
