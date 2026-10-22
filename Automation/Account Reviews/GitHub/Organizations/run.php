<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  GitHub Organization Members and Assigned Access
 *  Technology: GitHub (GitHub.com, Free to Enterprise Cloud)
 *  id: github-account-review        version: 0.1.1 (draft)
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
 *  Read-only: the script never changes GitHub and makes no review decisions.
 *  With AUTH = 'app' it only requests a one-hour installation token limited to "Members: read".
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Only the secret of the chosen AUTH method must exist (README §6).
$secrets = [
    'GITHUB_TOKEN' => '%SECRET_github_token%', // AUTH = 'token': fine-grained or classic personal access token
    'GITHUB_APP_PRIVATE_KEY_B64' => '%SECRET_github_app_private_key_b64%', // AUTH = 'app': the app's .pem private key, base64-encoded
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'ORGANIZATIONS' => ['example-org'], // GitHub organization logins (as in https://github.com/<org>), 1 to 20
    'AUTH' => 'token', // token (personal access token) or app (GitHub App installed on every organization)
    'APP_CLIENT_ID' => '', // AUTH = 'app' only: the app's Client ID (or numeric App ID)
    'USER_KEY' => 'login', // Account identifier: login, id, saml_name_id or email (no fallback; README §7)
    'ROLES' => ['org_role', 'teams'], // Access listed as roles; [] = accounts only (README §7)
    'INCLUDE_OUTSIDE_COLLABORATORS' => true, // Also list outside collaborators (repository access without membership)
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum accounts in the feed; never truncate
    'MAX_OBJECTS' => 2000, // Maximum records per API list (members, teams, team members); never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'github-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
const TPL_ALLOWED_REQUESTS = [
    'GET https://api.github.com/orgs/', // Members, outside collaborators, teams, team members, app installation
    'POST https://api.github.com/graphql', // SAML identities, only when USER_KEY is saml_name_id or email (a query)
    'POST https://api.github.com/app/installations/', // Installation token, only when AUTH = 'app'
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
// ─── shared:providers/github v1 (edit Automation/_shared/providers/github.php, then run Automation/tools/sync.php) ───
/** Headers of every GitHub REST call. The API version is pinned so field changes never arrive silently. */
function tpl_githubHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28',
    ];
}

/**
 * Stops before the primary rate limit is spent: GitHub then answers 403 until X-RateLimit-Reset,
 * which is usually longer than the run budget.
 */
function tpl_githubCheckRateLimit(array $headers): void
{
    $remaining = $headers['x-ratelimit-remaining'] ?? null;
    if ($remaining !== null && ctype_digit($remaining) && (int) $remaining < 5) {
        throw new RuntimeException('GitHub API rate limit almost spent; retry after it resets or narrow the scope.');
    }
}

/**
 * Every item of a GitHub list, following the Link: <…>; rel="next" header to the last page.
 * GitHub returns no totals on these lists, so completeness relies on reaching a page without a next link.
 */
function tpl_githubList(string $url, string $token, int $limit): array
{
    $first = $url;
    $seen = [];
    $items = [];
    while ($url !== null) {
        if (isset($seen[$url]) || count($seen) >= 200) {
            throw new RuntimeException('GitHub pagination repeats or exceeds 200 pages; narrow the scope.');
        }
        $seen[$url] = true;
        $response = tpl_http('GET', $url, ['headers' => tpl_githubHeaders($token)]);
        tpl_githubCheckRateLimit($response['headers']);
        if (!array_is_list($response['data'])) {
            throw new RuntimeException('GitHub returned an unexpected list.');
        }
        foreach ($response['data'] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (!is_int($id) || $id < 1) {
                throw new RuntimeException('GitHub list contains an invalid item.');
            }
            if (isset($items[$id])) {
                throw new RuntimeException('GitHub list contains a duplicate item.');
            }
            $items[$id] = $item;
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
// ─── end shared:providers/github v1 sha256:f941e76bc0186bc1 ───
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

// ─── 5. COLLECT: GitHub ─────────────────────────────────────────────────────

const ROLE_KINDS = ['org_role', 'teams'];
const API = 'https://api.github.com';

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    $orgs = $c['ORGANIZATIONS'];
    if (!is_array($orgs) || !array_is_list($orgs) || $orgs === [] || count($orgs) > 20) {
        throw new RuntimeException('ORGANIZATIONS must list 1 to 20 organization logins.');
    }
    foreach ($orgs as $org) {
        if (!is_string($org) || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/D', $org) !== 1) {
            throw new RuntimeException('ORGANIZATIONS contains an invalid organization login.');
        }
    }
    if (count(array_unique(array_map('strtolower', $orgs))) !== count($orgs)) {
        throw new RuntimeException('ORGANIZATIONS lists the same organization twice.');
    }
    if (!in_array($c['AUTH'], ['token', 'app'], true)) {
        throw new RuntimeException('AUTH must be token or app.');
    }
    if ($c['AUTH'] === 'app' && (!is_string($c['APP_CLIENT_ID']) || preg_match('/^[A-Za-z0-9.]{1,64}$/D', $c['APP_CLIENT_ID']) !== 1)) {
        throw new RuntimeException("APP_CLIENT_ID must be the GitHub App's Client ID or App ID when AUTH is app.");
    }
    if (!in_array($c['USER_KEY'], ['login', 'id', 'saml_name_id', 'email'], true)) {
        throw new RuntimeException('USER_KEY must be login, id, saml_name_id or email.');
    }
    foreach (['INCLUDE_OUTSIDE_COLLABORATORS', 'ALLOW_EMPTY_FEED'] as $key) {
        if (!is_bool($c[$key])) {
            throw new RuntimeException("$key must be true or false.");
        }
    }
    foreach (['MAX_USERS' => 5000, 'MAX_OBJECTS' => 10000] as $key => $max) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > $max) {
            throw new RuntimeException("$key must be a number from 1 to $max.");
        }
    }
}

/** Only the secret of the chosen AUTH method must exist. */
function checkAuthSecrets(array $secrets, array $config): void
{
    $name = $config['AUTH'] === 'app' ? 'GITHUB_APP_PRIVATE_KEY_B64' : 'GITHUB_TOKEN';
    tpl_checkSecrets([$name => $secrets[$name]]);
}

function base64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/** A 9-minute JWT signed with the app's private key (RS256), used only to request installation tokens. */
function appJwt(string $clientId, string $pem): string
{
    $key = openssl_pkey_get_private($pem);
    if ($key === false) {
        throw new RuntimeException("Secret 'github_app_private_key_b64' does not contain a valid private key.");
    }
    $now = time();
    $input = base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)) . '.'
        . base64Url(json_encode(['iat' => $now - 60, 'exp' => $now + 540, 'iss' => $clientId], JSON_THROW_ON_ERROR));
    if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Could not sign the GitHub App token request.');
    }
    return $input . '.' . base64Url($signature);
}

/** One token per organization: the personal access token, or an installation token limited to Members: read. */
function orgTokens(array $secrets, array $config): array
{
    $tokens = [];
    if ($config['AUTH'] === 'token') {
        return array_fill_keys($config['ORGANIZATIONS'], $secrets['GITHUB_TOKEN']);
    }
    $jwt = appJwt($config['APP_CLIENT_ID'], tpl_base64Secret($secrets['GITHUB_APP_PRIVATE_KEY_B64'], 'github_app_private_key_b64'));
    foreach ($config['ORGANIZATIONS'] as $org) {
        $installation = tpl_http('GET', API . '/orgs/' . rawurlencode($org) . '/installation', ['headers' => tpl_githubHeaders($jwt)])['data'];
        $id = $installation['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new RuntimeException('GitHub returned an unexpected app installation.');
        }
        $response = tpl_http('POST', API . '/app/installations/' . $id . '/access_tokens', [
            'headers' => tpl_githubHeaders($jwt),
            'json' => ['permissions' => ['members' => 'read']],
        ])['data'];
        if (!is_string($response['token'] ?? null) || $response['token'] === '') {
            throw new RuntimeException('GitHub did not return an installation token.');
        }
        $tokens[$org] = $response['token'];
    }
    return $tokens;
}

/** Every user of a list, checked: positive integer id and a login. */
function listUsers(string $url, string $token, int $limit): array
{
    $users = tpl_githubList($url, $token, $limit);
    foreach ($users as $user) {
        if (!is_string($user['login'] ?? null) || $user['login'] === '') {
            throw new RuntimeException('GitHub returned a user without a login.');
        }
    }
    return $users;
}

/**
 * SAML NameID and email of every member with a linked identity, from the organization's SAML SSO
 * (GraphQL externalIdentities; enterprise-level SAML is not read). Returns [user id => ['saml_name_id' => …, 'email' => …]].
 */
function samlIdentities(string $org, string $token, int $limit): array
{
    $query = 'query($org: String!, $after: String) { organization(login: $org) { samlIdentityProvider { '
        . 'externalIdentities(first: 100, after: $after, membersOnly: true) { pageInfo { hasNextPage endCursor } '
        . 'nodes { samlIdentity { nameId emails { value primary } } user { databaseId } } } } } }';
    $identities = [];
    $after = null;
    for ($page = 0; $page === 0 || $after !== null; $page++) {
        if ($page >= 200) {
            throw new RuntimeException('GitHub SAML identities exceed 200 pages; narrow the scope.');
        }
        $response = tpl_http('POST', API . '/graphql', [
            'headers' => tpl_githubHeaders($token),
            'json' => ['query' => $query, 'variables' => ['org' => $org, 'after' => $after]],
        ]);
        tpl_githubCheckRateLimit($response['headers']);
        if (isset($response['data']['errors'])) {
            throw new RuntimeException('GitHub GraphQL returned an error; check that the token can read SAML identities (README §5).');
        }
        if (!is_array($response['data']['data']['organization'] ?? null)) {
            throw new RuntimeException('GitHub GraphQL did not return the organization; check its login and the token access.');
        }
        $provider = $response['data']['data']['organization']['samlIdentityProvider'];
        if (!is_array($provider)) {
            throw new RuntimeException('An organization has no organization-level SAML SSO visible to the token; use USER_KEY login or id.');
        }
        $connection = $provider['externalIdentities'] ?? null;
        if (!is_array($connection) || !is_array($connection['nodes'] ?? null) || !is_bool($connection['pageInfo']['hasNextPage'] ?? null)) {
            throw new RuntimeException('GitHub GraphQL returned unexpected SAML identities.');
        }
        foreach ($connection['nodes'] as $node) {
            $userId = $node['user']['databaseId'] ?? null;
            if (!is_int($userId)) {
                continue; // Identity not linked to a GitHub account: no member to key
            }
            if (isset($identities[$userId])) {
                throw new RuntimeException('Two SAML identities are linked to the same GitHub account.');
            }
            $saml = is_array($node['samlIdentity'] ?? null) ? $node['samlIdentity'] : [];
            $emails = array_values(array_filter(is_array($saml['emails'] ?? null) ? $saml['emails'] : [], 'is_array'));
            $primary = array_values(array_filter($emails, static fn (array $e): bool => ($e['primary'] ?? false) === true));
            $email = count($primary) === 1 ? $primary[0]['value'] ?? null : (count($emails) === 1 ? $emails[0]['value'] ?? null : null);
            $identities[$userId] = ['saml_name_id' => $saml['nameId'] ?? null, 'email' => $email];
            if (count($identities) > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        }
        $after = $connection['pageInfo']['hasNextPage'] ? ($connection['pageInfo']['endCursor'] ?? null) : null;
        if ($connection['pageInfo']['hasNextPage'] && (!is_string($after) || $after === '')) {
            throw new RuntimeException('GitHub GraphQL pagination cursor is invalid.');
        }
    }
    return $identities;
}

/** Adds one account of an organization; an account listed twice in one organization means it changed during the pull. */
function addAccount(array &$accounts, array $user, string $org, string $label, array $config): void
{
    $id = $user['id'];
    if (isset($accounts[$id]['orgs'][$org])) {
        throw new RuntimeException('An account is listed twice in one organization; memberships changed during the pull, retry later.');
    }
    $accounts[$id]['login'] = $user['login'];
    $accounts[$id]['type'] = is_string($user['type'] ?? null) ? $user['type'] : 'User';
    $accounts[$id]['orgs'][$org] = $label;
    $accounts[$id]['roles'] ??= [];
    if (in_array('org_role', $config['ROLES'], true)) {
        $accounts[$id]['roles'][] = tpl_roleString('Org role', $label, $org);
    }
    if (count($accounts) > $config['MAX_USERS']) {
        throw new RuntimeException('More accounts than MAX_USERS; raise it or narrow ORGANIZATIONS.');
    }
}

function collectFeed(array $secrets, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $limit = $config['MAX_OBJECTS'];
    $samlKey = in_array($config['USER_KEY'], ['saml_name_id', 'email'], true);
    $tokens = orgTokens($secrets, $config);
    $accounts = [];
    $keyValues = [];
    foreach ($config['ORGANIZATIONS'] as $n => $org) {
        $token = $tokens[$org];
        $base = API . '/orgs/' . rawurlencode($org);
        // With org_role, owners and members are listed separately; together they are every member.
        $lists = isset($kinds['org_role'])
            ? ['owner' => '/members?role=admin&per_page=100', 'member' => '/members?role=member&per_page=100']
            : ['member' => '/members?role=all&per_page=100'];
        if ($config['INCLUDE_OUTSIDE_COLLABORATORS']) {
            $lists['outside collaborator'] = '/outside_collaborators?filter=all&per_page=100';
        }
        foreach ($lists as $label => $path) {
            foreach (listUsers($base . $path, $token, $limit) as $user) {
                addAccount($accounts, $user, $org, $label, $config);
            }
        }

        $teams = isset($kinds['teams']) ? tpl_githubList($base . '/teams?per_page=100', $token, $limit) : [];
        foreach ($teams as $team) {
            if (!is_string($team['slug'] ?? null) || preg_match('/^[A-Za-z0-9_.-]{1,255}$/D', $team['slug']) !== 1) {
                throw new RuntimeException('GitHub returned a team with an invalid slug.');
            }
            $role = tpl_roleString('Team', $org . '/' . tpl_feedText($team['name'] ?? null, 'Team name'), (string) $team['id']);
            foreach (listUsers($base . '/teams/' . rawurlencode($team['slug']) . '/members?role=all&per_page=100', $token, $limit) as $member) {
                if (!isset($accounts[$member['id']]['orgs'][$org])) {
                    throw new RuntimeException('A team member is not a listed member of its organization; memberships changed during the pull, retry later.');
                }
                $accounts[$member['id']]['roles'][] = $role;
            }
        }

        if ($samlKey) {
            foreach (samlIdentities($org, $token, $limit) as $userId => $identity) {
                if (!isset($accounts[$userId])) {
                    continue; // Identity of a user that is no longer a member
                }
                $value = $identity[$config['USER_KEY']];
                if (isset($keyValues[$userId]) && $keyValues[$userId] !== $value) {
                    throw new RuntimeException('An account has different SAML identities in two organizations; use USER_KEY login or id.');
                }
                $keyValues[$userId] = $value;
            }
        }
        tpl_logInfo(sprintf('Organization %d of %d collected (%d teams).', $n + 1, count($tokens), count($teams)));
    }
    tpl_logInfo(count($accounts) . ' accounts found.');

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    foreach ($accounts as $id => $account) {
        if ($samlKey && !is_string($keyValues[$id] ?? null)) {
            throw new RuntimeException('An account has no linked SAML identity with that value (outside collaborators never have one); see README §7.');
        }
        $key = tpl_feedKey(match ($config['USER_KEY']) {
            'login' => $account['login'],
            'id' => (string) $id,
            default => $keyValues[$id],
        }, $keys);
        $orgs = [];
        foreach ($account['orgs'] as $org => $label) {
            $orgs[] = "$org: $label";
        }
        $description = sprintf('%s; %s; id=%d; %s', $account['login'], tpl_feedText($account['type'], 'Account type'), $id, implode(', ', $orgs));
        $description = mb_strcut(tpl_feedText($description, 'Description'), 0, 255, 'UTF-8');
        if (count(array_unique($account['roles'])) > $config['MAX_OBJECTS']) {
            throw new RuntimeException('Combined account access limit exceeded.');
        }
        $bytes = tpl_feedAdd($feed, $key, $description, $account['roles']);
    }
    if ($accounts === [] && !$config['ALLOW_EMPTY_FEED']) {
        throw new TplEmptyFeedException('No accounts found; check ORGANIZATIONS, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    tpl_logInfo(sprintf('%d accounts staged (%d bytes).', count($accounts), $bytes));
    return $feed;
}

// ─── 6. MAIN ────────────────────────────────────────────────────────────────
tpl_installErrorHandler();
tpl_elapsed();
try {
    tpl_log(sprintf('%s v%s', AUTOMATION_ID, AUTOMATION_VERSION));

    tpl_logStep(1, 'Checking configuration');
    validateConfig($config);
    checkAuthSecrets($secrets, $config);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Collecting organization members, outside collaborators and teams');
    $feed = collectFeed($secrets, $config);

    tpl_logStep(3, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
