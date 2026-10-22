<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Slack Members and Assigned Access
 *  Technology: Slack
 *  id: slack-account-review        version: 0.1.1 (draft)
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
 *  Read-only: the script never changes Slack and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'SLACK_TOKEN' => '%SECRET_slack_token%', // Bot token (xoxb-…) with users:read, users:read.email (and usergroups:read)
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'email', // Account identifier: email (profile.email) or id (Slack member ID); no fallback
    'ROLES' => ['workspace_role'], // Access listed as roles: workspace_role, user_groups; [] = accounts only
    'INCLUDE_BOTS' => false, // Also list bot users, workflow bots and Slackbot (requires USER_KEY = id)
    'TEAM_ID' => '', // Enterprise Grid org-level token only: the workspace ID (T…) to list; '' otherwise
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum members in the feed (all listed members counted); never truncate
    'MAX_OBJECTS' => 2000, // Maximum user groups, and members per user group; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'slack-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const TPL_ALLOWED_REQUESTS = [
    'GET https://slack.com/api/users.list',
    'GET https://slack.com/api/usergroups.list',
    'GET https://slack.com/api/usergroups.users.list',
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

// ─── 5. COLLECT: Slack ──────────────────────────────────────────────────────

const ROLE_KINDS = ['workspace_role', 'user_groups'];

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['email', 'id'], true)) {
        throw new RuntimeException('USER_KEY must be email or id.');
    }
    foreach (['INCLUDE_BOTS', 'ALLOW_EMPTY_FEED'] as $key) {
        if (!is_bool($c[$key])) {
            throw new RuntimeException("$key must be true or false.");
        }
    }
    if ($c['INCLUDE_BOTS'] && $c['USER_KEY'] !== 'id') {
        throw new RuntimeException('Bots have no email address: INCLUDE_BOTS requires USER_KEY = id.');
    }
    if (!is_string($c['TEAM_ID']) || ($c['TEAM_ID'] !== '' && preg_match('/^T[A-Z0-9]{2,30}$/D', $c['TEAM_ID']) !== 1)) {
        throw new RuntimeException("TEAM_ID must be '' or a Slack workspace ID such as T0123ABCD.");
    }
    foreach (['MAX_USERS', 'MAX_OBJECTS'] as $key) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > 10000) {
            throw new RuntimeException("$key must be a number from 1 to 10000.");
        }
    }
}

function slackToken(string $token): string
{
    if (preg_match('/^xox[A-Za-z0-9.-]{8,1000}$/D', $token) !== 1) {
        throw new RuntimeException('The Slack token must be a bot (xoxb-…) or user (xoxp-…) token.');
    }
    return $token;
}

/**
 * One Slack Web API call. Slack reports most errors as HTTP 200 with "ok": false and an error code,
 * which is safe to show (it never contains account data). HTTP 429 is retried with Retry-After by tpl_http().
 */
function slackCall(string $method, array $query, string $token, array $config): array
{
    if ($config['TEAM_ID'] !== '') {
        $query['team_id'] = $config['TEAM_ID'];
    }
    $data = tpl_http('GET', 'https://slack.com/api/' . $method . '?' . http_build_query($query), [
        'headers' => ['Authorization' => 'Bearer ' . $token],
    ])['data'];
    if (($data['ok'] ?? null) !== true) {
        $error = is_string($data['error'] ?? null) && preg_match('/^[a-z0-9_.]{1,64}$/D', $data['error']) === 1 ? $data['error'] : 'unknown';
        $hint = in_array($error, ['missing_scope', 'not_allowed_token_type', 'no_permission', 'access_denied'], true)
            ? ' Check the token type and the scopes in README §5.' : '';
        throw new RuntimeException("Slack $method failed with error '$error'.$hint");
    }
    return $data;
}

function isBot(array $member): bool
{
    // Slackbot is special: is_bot is false for it, so it is recognised by its fixed member ID.
    return ($member['is_bot'] ?? false) === true || ($member['is_workflow_bot'] ?? false) === true || $member['id'] === 'USLACKBOT';
}

/**
 * Every member of the workspace, all cursor pages, deactivated members included.
 * Returns ['members' => [id => member], 'excluded' => [id => true]]: bots (unless INCLUDE_BOTS) and
 * members of other organisations (Slack Connect, is_stranger) are excluded and counted.
 */
function listMembers(string $token, array $config): array
{
    $members = [];
    $excluded = [];
    $cursors = [];
    $cursor = '';
    for ($page = 0; ; $page++) {
        if ($page >= 200) {
            throw new RuntimeException('Slack member list exceeds 200 pages.');
        }
        $query = ['limit' => 200];
        if ($cursor !== '') {
            $query['cursor'] = $cursor;
        }
        $data = slackCall('users.list', $query, $token, $config);
        if (!is_array($data['members'] ?? null) || !array_is_list($data['members'])) {
            throw new RuntimeException('Slack returned an unexpected member list.');
        }
        foreach ($data['members'] as $member) {
            $id = is_array($member) ? ($member['id'] ?? null) : null;
            if (!is_string($id) || preg_match('/^[A-Z0-9]{2,40}$/D', $id) !== 1) {
                throw new RuntimeException('Slack returned a member without a valid ID.');
            }
            if (isset($members[$id]) || isset($excluded[$id])) {
                throw new RuntimeException('A member appears twice; the workspace changed during the pull, retry later.');
            }
            if (($member['is_stranger'] ?? false) === true || (isBot($member) && !$config['INCLUDE_BOTS'])) {
                $excluded[$id] = true;
            } else {
                $members[$id] = $member;
            }
            if (count($members) + count($excluded) > $config['MAX_USERS']) {
                throw new RuntimeException('More members than MAX_USERS; raise the limit.');
            }
        }
        $next = $data['response_metadata']['next_cursor'] ?? '';
        if (!is_string($next)) {
            throw new RuntimeException('Slack pagination cursor is invalid.');
        }
        if ($next === '') {
            break;
        }
        if (isset($cursors[$next])) {
            throw new RuntimeException('Slack pagination cursor repeats; retry later.');
        }
        $cursors[$next] = true;
        $cursor = $next;
    }
    return ['members' => $members, 'excluded' => $excluded];
}

/** The single, highest workspace role of a member, from the documented user-object flags. */
function workspaceRole(array $member): string
{
    $flags = [
        'primary_owner' => ['is_primary_owner', 'Primary Owner'],
        'owner' => ['is_owner', 'Owner'],
        'admin' => ['is_admin', 'Admin'],
        'ultra_restricted' => ['is_ultra_restricted', 'Single-channel guest'],
        'restricted' => ['is_restricted', 'Multi-channel guest'],
    ];
    foreach ($flags as $id => [$field, $label]) {
        if (($member[$field] ?? false) === true) {
            return tpl_roleString('Workspace role', $label, $id);
        }
    }
    return isBot($member) ? tpl_roleString('Workspace role', 'Bot', 'bot') : tpl_roleString('Workspace role', 'Member', 'member');
}

/** Enabled user groups and their members, keyed by member ID. Disabled user groups grant nothing. */
function collectUserGroups(string $token, array $population, array $config): array
{
    $roles = [];
    $data = slackCall('usergroups.list', [], $token, $config);
    if (!is_array($data['usergroups'] ?? null) || !array_is_list($data['usergroups'])) {
        throw new RuntimeException('Slack returned an unexpected user group list.');
    }
    if (count($data['usergroups']) > $config['MAX_OBJECTS']) {
        throw new RuntimeException('More user groups than MAX_OBJECTS; raise the limit.');
    }
    $seen = [];
    foreach ($data['usergroups'] as $group) {
        $id = is_array($group) ? ($group['id'] ?? null) : null;
        if (!is_string($id) || preg_match('/^S[A-Z0-9]{2,40}$/D', $id) !== 1 || isset($seen[$id])) {
            throw new RuntimeException('Slack returned a user group without a valid, unique ID.');
        }
        $seen[$id] = true;
        if (($group['date_delete'] ?? 0) !== 0) {
            continue; // Disabled user groups are not listed by default; skipped if one appears
        }
        $role = tpl_roleString('User group', $group['name'] ?? ($group['handle'] ?? null), $id);
        $users = slackCall('usergroups.users.list', ['usergroup' => $id], $token, $config)['users'] ?? null;
        if (!is_array($users) || !array_is_list($users) || count($users) > $config['MAX_OBJECTS']) {
            throw new RuntimeException('Slack returned an unexpected or too large user group member list.');
        }
        foreach ($users as $userId) {
            if (!is_string($userId) || !(isset($population['members'][$userId]) || isset($population['excluded'][$userId]))) {
                throw new RuntimeException('A user group lists a member outside the collected population; members changed during the pull, retry later.');
            }
            if (isset($population['members'][$userId])) {
                $roles[$userId][] = $role;
            }
        }
    }
    tpl_logInfo(count($seen) . ' user groups collected.');
    return $roles;
}

function memberState(array $member): string
{
    if (($member['deleted'] ?? null) === true) {
        return 'deactivated';
    }
    if (($member['deleted'] ?? null) !== false) {
        throw new RuntimeException('Slack returned a member without a deactivation state.');
    }
    return ($member['is_invited_user'] ?? false) === true ? 'invited' : 'active';
}

function collectFeed(string $token, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $population = listMembers($token, $config);
    $members = $population['members'];
    tpl_logInfo(sprintf('%d members found; %d bots or external members excluded.', count($members), count($population['excluded'])));
    $groupRoles = isset($kinds['user_groups']) ? collectUserGroups($token, $population, $config) : [];

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    $deactivated = 0;
    foreach ($members as $id => $member) {
        $profile = is_array($member['profile'] ?? null) ? $member['profile'] : [];
        $email = $profile['email'] ?? null;
        if ($config['USER_KEY'] === 'email' && (!is_string($email) || trim($email) === '')) {
            throw new RuntimeException('A member has no email address: grant users:read.email, or set USER_KEY = id.');
        }
        $key = tpl_feedKey($config['USER_KEY'] === 'id' ? $id : $email, $keys);
        $state = memberState($member);
        $deactivated += $state === 'deactivated' ? 1 : 0;
        $names = array_filter([$profile['real_name'] ?? null, $profile['display_name'] ?? null, $member['real_name'] ?? null],
            static fn ($n): bool => is_string($n) && trim($n) !== '');
        $name = mb_strcut(tpl_feedText(reset($names) ?: $id, 'Member name'), 0, 150, 'UTF-8');
        $kind = isBot($member) ? 'bot' : (($member['is_restricted'] ?? false) === true ? 'guest' : 'member');
        $roles = isset($kinds['workspace_role']) ? [workspaceRole($member)] : [];
        $roles = array_merge($roles, $groupRoles[$id] ?? []);
        $bytes = tpl_feedAdd($feed, $key, tpl_feedText("$name; $kind; $state; id=$id", 'Description'), $roles);
    }
    if ($members === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new RuntimeException('No accounts found; check the token and TEAM_ID, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    tpl_logInfo(sprintf('%d accounts staged, %d deactivated (%d bytes).', count($members), $deactivated, $bytes));
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
    $token = slackToken($secrets['SLACK_TOKEN']);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Collecting members, workspace roles and user groups');
    $feed = collectFeed($token, $config);

    tpl_logStep(3, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
