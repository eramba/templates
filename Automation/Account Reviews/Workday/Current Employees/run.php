<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Workday Current Employees
 *  Technology: Workday HCM
 *  id: workday-current-employees        version: 0.1.1 (draft)
 *  Docs: README.md in the same folder (report, ISU, secrets, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Account%20Reviews
 *
 *  Composer packages (paste in the automation's composer field):
 *    guzzlehttp/guzzle:^7.9
 * ============================================================================
 *
 *  For a feed of type "List of current employees" (or, with FEED_MODE 'former',
 *  "List of former employees"): the people an Exit review compares the
 *  application accounts against, as Workday knows them.
 *
 *  Source: one Workday custom report exposed as a web service (Report-as-a-Service,
 *  RaaS), read as JSON with an Integration System User (ISU) and HTTP Basic
 *  authentication. The report decides who is included; this script only reads it.
 *
 *  Output
 *    STDOUT  Reserved for the Account Review artifact declaration written by
 *            writeAccountReviewArtifact(). Nothing else is ever printed there.
 *    STDERR  Step-by-step log with counts only, and technical errors.
 *            Account Review feeds accept STDERR when the run exits 0.
 *
 *  Exit codes
 *    0  Complete employee list collected and published.
 *    1  Technical error (credentials, network, permissions, limits). Nothing
 *       is published, so the pull fails instead of returning a partial list.
 *
 *  Read-only: the script never changes Workday and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'WORKDAY_REPORT_URL' => '%SECRET_workday_report_url%', // RaaS URL from the report's Web Service → View URLs (JSON)
    'WORKDAY_ISU_USERNAME' => '%SECRET_workday_isu_username%', // Integration System User name
    'WORKDAY_ISU_PASSWORD_B64' => '%SECRET_workday_isu_password_b64%', // Base64 of the ISU password
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'email', // Identifier compared with the application accounts: email or employee_id
    'FIELD_EMAIL' => 'Work_Email', // Report field (XML alias) holding the primary work email
    'FIELD_EMPLOYEE_ID' => 'Employee_ID', // Report field (XML alias) holding the Employee ID
    'FIELD_NAME' => 'Worker', // Report field (XML alias) holding the worker's name
    'FIELD_ACTIVE' => '', // Optional report field (XML alias) with the Active status; empty: trust the report filter
    'FEED_MODE' => 'current', // current: List of current employees; former: List of former employees
    'MAX_USERS' => 10000, // Maximum employees; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'workday-current-employees';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout
// TPL_ALLOWED_REQUESTS is defined in MAIN from the report URL: only
// GET https://<workday host>/ccx/service/customreport2/<tenant>/<owner>/<report> is allowed.

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

// ─── 5. COLLECT: Workday ────────────────────────────────────────────────────
// The custom report returns every row in one response (RaaS has no pages); the report's data source
// and filter decide who is a current (or former) employee.

function validateConfig(array $c): void
{
    if (!in_array($c['USER_KEY'], ['email', 'employee_id'], true)) {
        throw new RuntimeException('USER_KEY must be email or employee_id.');
    }
    foreach (['FIELD_EMAIL', 'FIELD_EMPLOYEE_ID', 'FIELD_NAME', 'FIELD_ACTIVE'] as $name) {
        $value = $c[$name];
        $optional = $name === 'FIELD_ACTIVE';
        if (!is_string($value) || (!($optional && $value === '') && preg_match('/^[A-Za-z_][A-Za-z0-9_.-]{0,99}$/D', $value) !== 1)) {
            throw new RuntimeException("$name must be a report field XML alias (letters, digits, _ . -)" . ($optional ? ' or empty.' : '.'));
        }
    }
    if (!in_array($c['FEED_MODE'], ['current', 'former'], true)) {
        throw new RuntimeException('FEED_MODE must be current or former.');
    }
    if (!is_int($c['MAX_USERS']) || $c['MAX_USERS'] < 1 || $c['MAX_USERS'] > 50000) {
        throw new RuntimeException('MAX_USERS must be a number from 1 to 50000.');
    }
}

/**
 * The RaaS URL copied from Workday (Actions → Web Service → View URLs). Only a Workday host and the
 * /ccx/service/customreport2/<tenant>/<owner>/<report> path are accepted; the query is rebuilt as format=json.
 */
function workdayReportUrl(string $url): string
{
    $parts = parse_url(trim($url));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['port']) || isset($parts['fragment'])
        || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:myworkday|workday)\.com$/D', $host) !== 1) {
        throw new RuntimeException("Secret 'workday_report_url' must be an https URL on a workday.com or myworkday.com host.");
    }
    if (preg_match('#^/ccx/service/customreport2/[A-Za-z0-9_-]{1,100}/[A-Za-z0-9_.@%-]{1,200}/[A-Za-z0-9_.%-]{1,200}$#D', $path) !== 1) {
        throw new RuntimeException("Secret 'workday_report_url' must be a report URL: /ccx/service/customreport2/<tenant>/<owner>/<report>.");
    }
    parse_str((string) ($parts['query'] ?? ''), $query);
    if (array_diff(array_keys($query), ['format']) !== []) {
        throw new RuntimeException("Secret 'workday_report_url' must not carry report prompts; set them as defaults in the report.");
    }
    return 'https://' . $host . $path;
}

/** One report field as text, or null when Workday left it out (empty fields are omitted from the JSON). */
function reportText(array $entry, string $field): ?string
{
    $value = $entry[$field] ?? null;
    if ($value === null || (is_string($value) && trim($value) === '')) {
        return null;
    }
    if (!is_string($value)) {
        throw new RuntimeException("Report field $field is not a single text value; use a single-instance field.");
    }
    return $value;
}

/** Workday renders boolean report fields as "1"/"0" in JSON; also accept true/false. */
function reportBool(array $entry, string $field): bool
{
    $value = $entry[$field] ?? null;
    if (is_bool($value)) {
        return $value;
    }
    $value = is_string($value) ? strtolower(trim($value)) : null;
    if (in_array($value, ['1', 'true'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'false'], true)) {
        return false;
    }
    throw new RuntimeException("Report field $field is missing or not a boolean in some rows; check FIELD_ACTIVE.");
}

function listReportEntries(string $reportUrl, string $username, string $password, int $limit): array
{
    if ($username === '' || str_contains($username, ':')) {
        throw new RuntimeException("Secret 'workday_isu_username' must not be empty or contain ':'.");
    }
    $response = tpl_http('GET', $reportUrl . '?format=json', [
        'headers' => ['Authorization' => 'Basic ' . base64_encode($username . ':' . $password)],
    ]);
    $data = $response['data'];
    if ($data !== [] && !array_key_exists('Report_Entry', $data)) {
        throw new RuntimeException('Workday returned an unexpected report; check that the URL is the JSON RaaS URL.');
    }
    $entries = $data['Report_Entry'] ?? [];
    if (!is_array($entries) || !array_is_list($entries)) {
        throw new RuntimeException('Workday returned an unexpected report entry list.');
    }
    if (count($entries) > $limit) {
        throw new RuntimeException('The report has more rows than MAX_USERS; raise it or narrow the report.');
    }
    foreach ($entries as $entry) {
        if (!is_array($entry) || array_is_list($entry)) {
            throw new RuntimeException('Workday report contains an invalid row.');
        }
    }
    return $entries;
}

function collectFeed(array $entries, array $config)
{
    $keyField = $config['USER_KEY'] === 'email' ? $config['FIELD_EMAIL'] : $config['FIELD_EMPLOYEE_ID'];
    $wantActive = $config['FEED_MODE'] === 'current';
    $feed = tpl_feedOpen();
    $keys = [];
    $count = 0;
    $withoutKey = 0;
    $otherStatus = 0;
    foreach ($entries as $entry) {
        if ($config['FIELD_ACTIVE'] !== '' && reportBool($entry, $config['FIELD_ACTIVE']) !== $wantActive) {
            // A row the report should not contain (wrong status for this feed): left out and counted.
            $otherStatus++;
            continue;
        }
        $value = reportText($entry, $keyField);
        if ($value === null) {
            // Leaving someone out of a current-employees list only adds findings (their accounts get
            // flagged); it can never hide one. Workers without the key field are therefore counted, not fatal.
            $withoutKey++;
            continue;
        }
        $key = tpl_feedKey($value, $keys);
        $employeeId = reportText($entry, $config['FIELD_EMPLOYEE_ID']);
        $name = tpl_feedText(mb_strcut(reportText($entry, $config['FIELD_NAME']) ?? $employeeId ?? $value, 0, 150, 'UTF-8'), 'Worker name');
        $suffix = $employeeId !== null ? '; employee ' . mb_strcut($employeeId, 0, 40, 'UTF-8') : '';
        tpl_feedAdd($feed, $key, tpl_feedText($name . $suffix, 'Description'), []);
        $count++;
    }
    // An empty current-employees list would flag every application account, and an empty former-employees
    // list would hide every leaver: never publish one.
    if ($count === 0) {
        throw new RuntimeException('No employees found; check the report filter, the field aliases and USER_KEY.');
    }
    tpl_logInfo(sprintf('%d employees staged; %d skipped without %s; %d skipped with another status.', $count, $withoutKey, $keyField, $otherStatus));
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
    $reportUrl = workdayReportUrl($secrets['WORKDAY_REPORT_URL']);
    define('TPL_ALLOWED_REQUESTS', ['GET ' . $reportUrl]);
    tpl_logInfo('Secrets present. Mode: ' . $config['FEED_MODE'] . ' employees.');

    tpl_logStep(2, 'Reading the Workday report');
    $entries = listReportEntries($reportUrl, $secrets['WORKDAY_ISU_USERNAME'],
        tpl_base64Secret($secrets['WORKDAY_ISU_PASSWORD_B64'], 'workday_isu_password_b64'), $config['MAX_USERS']);
    tpl_logInfo(count($entries) . ' report rows received.');

    tpl_logStep(3, 'Building the employee list');
    $feed = collectFeed($entries, $config);

    tpl_logStep(4, 'Publishing the employee list');
    tpl_feedPublish($feed);
    tpl_logSummary('current employees');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('employee list', $e);
    exit(1);
}
