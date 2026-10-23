<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Entra ID – Application permissions
 *  Technology: Microsoft Entra ID
 *  id: entra-app-permissions-review        version: 0.2.0 (tested)
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Account%20Reviews
 *
 *  Composer packages (paste in the automation's composer field):
 *    guzzlehttp/guzzle:^7.9
 * ============================================================================
 *
 *  Each row is one enterprise application (service principal); its roles are
 *  the API permissions it holds: application permissions and delegated
 *  permissions consented for all users or for some users.
 *
 *  Output
 *    STDOUT  Reserved for the Account Review artifact declaration written by
 *            writeAccountReviewArtifact(). Nothing else is ever printed there.
 *    STDERR  Step-by-step log with counts only, and technical errors.
 *            Account Review feeds accept STDERR when the run exits 0.
 *
 *  Exit codes
 *    0  Complete list collected and published.
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
    'INCLUDE_MICROSOFT_APPS' => false, // True also lists applications owned by Microsoft
    'ALLOW_EMPTY_FEED' => false, // True only if zero applications is a legitimate result
    'MAX_APPS' => 2000, // Maximum applications in the feed; never truncate
    'MAX_OBJECTS' => 10000, // Maximum records per API collection; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'entra-app-permissions-review';
const AUTOMATION_VERSION = '0.2.0';
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
 * e.g. "Group: Finance (through group Finance EU)". At most 244 bytes, which leaves room for the " [<id>]"
 * that tells equal names apart: only the name is shortened.
 */
function tpl_entraRole(string $kind, mixed $name, array $details = []): string
{
    if (!is_string($name) || preg_match('//u', $name) !== 1 || ($name = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $name))) === '') {
        throw new RuntimeException("$kind name is missing or not valid text.");
    }
    $suffix = $details === [] ? '' : ' (' . implode('; ', $details) . ')';
    $fixed = strlen($kind) + 2 + strlen($suffix);
    if ($fixed + 3 > 244) {
        throw new RuntimeException("$kind details are too long for a role value.");
    }
    if ($fixed + strlen($name) > 244) {
        $name = mb_strcut($name, 0, 244 - $fixed - 3, 'UTF-8') . '…';
    }
    return "$kind: $name$suffix";
}

/**
 * Names in Entra are not unique, so each role travels with its identity: the object IDs it stands for, e.g.
 * [application, app role, group]. Records the roles of one row in $names (text => identity => IDs), so that
 * equal texts can be told apart once the whole feed is known, and returns them as [text, identity] pairs.
 */
function tpl_entraNote(array $roles, array &$names): array
{
    $noted = [];
    foreach ($roles as [$text, $ids]) {
        $ids = array_map('strval', (array) $ids);
        $identity = implode(';', $ids);
        $names[$text][$identity] = $ids;
        $noted[] = [$text, $identity];
    }
    return $noted;
}

/**
 * For each role text that stands for two or more different objects, the text each of them gets: the text plus
 * " [<first characters of the object ID that tells them apart>]", e.g. the group when one application role
 * comes through two groups named "Sales". Returns text => identity => final text, only for those texts.
 */
function tpl_entraTags(array $names): array
{
    $tags = [];
    foreach ($names as $text => $identities) {
        if (count($identities) < 2) {
            continue;
        }
        $width = max(array_map('count', $identities));
        $short = null;
        for ($position = 0; $position < $width && $short === null; $position++) {
            $values = array_map(static fn (array $ids): string => $ids[$position] ?? '', $identities);
            if (count(array_unique($values)) !== count($values)) {
                continue; // this ID is shared by some of them: it does not tell them apart
            }
            foreach ([8, 12, 36] as $length) {
                $cut = array_map(static fn (string $id): string => substr($id, 0, $length), $values);
                if (count(array_unique($cut)) === count($cut)) {
                    $short = $cut;
                    break;
                }
            }
        }
        // No single ID tells them all apart: fall back to a short fingerprint of the whole identity.
        $short ??= array_combine(array_keys($identities), array_map(static fn ($identity): string => substr(hash('sha256', (string) $identity), 0, 8), array_keys($identities)));
        foreach ($short as $identity => $id) {
            $tags[$text][$identity] = "$text [$id]";
        }
    }
    return $tags;
}

/** The final, de-duplicated role list of one row from its [text, identity] pairs. */
function tpl_entraFinal(array $roles, array $tags): array
{
    $final = [];
    foreach ($roles as [$text, $identity]) {
        $final[] = $tags[$text][$identity] ?? $text;
    }
    return array_values(array_unique($final));
}

/** Rows held in memory (small feeds): roles as [text, IDs] in, plain role lists out. */
function tpl_entraDisambiguate(array $rows): array
{
    $names = [];
    foreach ($rows as &$row) {
        $row['roles'] = tpl_entraNote($row['roles'], $names);
    }
    unset($row);
    $tags = tpl_entraTags($names);
    foreach ($rows as &$row) {
        $row['roles'] = tpl_entraFinal($row['roles'], $tags);
    }
    unset($row);
    return $rows;
}
// ─── end shared:providers/graph v2 sha256:de9983d9a373aeb9 ───
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

// ─── 5. COLLECT: Entra ID application permissions ───────────────────────────

// Role kinds, worded like the Permissions page of an enterprise application in Entra (Admin consent and
// User consent tabs, with the permission type), so reviewers find each permission where it can be revoked.
const ADMIN_APP = 'Application permission (admin consent)';
const ADMIN_DELEGATED = 'Delegated permission (admin consent)';
const USER_DELEGATED = 'Delegated permission (user consent)';

const MICROSOFT_TENANTS = ['f8cdef31-a31e-4b4a-93e4-5f571e91255a', '72f988bf-86f1-41af-91ab-2d7cd011db47'];

function validateConfig(array $c): void
{
    foreach (['INCLUDE_MICROSOFT_APPS', 'ALLOW_EMPTY_FEED'] as $key) {
        if (!is_bool($c[$key])) {
            throw new RuntimeException("$key must be true or false.");
        }
    }
    foreach (['MAX_APPS' => 5000, 'MAX_OBJECTS' => 10000] as $key => $max) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > $max) {
            throw new RuntimeException("$key must be a number from 1 to $max.");
        }
    }
}

/**
 * Builds one row per application that holds at least one API permission.
 *
 * @param array $servicePrincipals    Graph servicePrincipals (id, appId, displayName, appOwnerOrganizationId,
 *                                    accountEnabled, appRoleAssignmentRequired, appRoles)
 * @param array $appRoleAssignments   Client service principal id => list of {resourceId, appRoleId}
 * @param array $grants               oauth2PermissionGrants (clientId, consentType, principalId, resourceId, scope)
 * @param array $config               INCLUDE_MICROSOFT_APPS (bool), TENANT_ID (string)
 * @return list<array{key: string, description: string, roles: list<string>}> Sorted by key
 */
function permissionRows(array $servicePrincipals, array $appRoleAssignments, array $grants, array $config): array
{
    $unresolved = 'An application permission cannot be resolved; retry later.';
    $byId = [];
    foreach ($servicePrincipals as $sp) {
        $byId[$sp['id']] = $sp;
    }
    $grantsByClient = [];
    foreach ($grants as $grant) {
        $grantsByClient[$grant['clientId']][] = $grant;
    }
    $includeMicrosoft = ($config['INCLUDE_MICROSOFT_APPS'] ?? false) === true;
    $tenant = strtolower((string) ($config['TENANT_ID'] ?? ''));

    $rows = [];
    $seen = [];
    foreach ($servicePrincipals as $sp) {
        $owner = strtolower((string) ($sp['appOwnerOrganizationId'] ?? ''));
        $isMicrosoft = in_array($owner, MICROSOFT_TENANTS, true);
        if ($isMicrosoft && !$includeMicrosoft) {
            continue;
        }

        $roles = [];
        foreach ($appRoleAssignments[$sp['id']] ?? [] as $assignment) {
            $resource = $byId[$assignment['resourceId']] ?? null;
            if ($resource === null) {
                throw new RuntimeException($unresolved);
            }
            if ($assignment['appRoleId'] === '00000000-0000-0000-0000-000000000000') {
                // Default access: the application is assigned to the resource without a specific app role.
                $roles[] = [tpl_entraRole(ADMIN_APP, apiPermission($resource, 'Default access')), [$resource['id'], $assignment['appRoleId']]];
                continue;
            }
            $appRole = null;
            foreach ($resource['appRoles'] ?? [] as $candidate) {
                if (($candidate['id'] ?? null) === $assignment['appRoleId']) {
                    $appRole = $candidate;
                    break;
                }
            }
            if ($appRole === null) {
                throw new RuntimeException($unresolved);
            }
            $name = ($appRole['value'] ?? '') !== '' ? $appRole['value'] : $appRole['displayName'];
            $roles[] = [tpl_entraRole(ADMIN_APP, apiPermission($resource, $name)), [$resource['id'], $assignment['appRoleId']]];
        }

        $someUsers = [];
        foreach ($grantsByClient[$sp['id']] ?? [] as $grant) {
            $resource = $byId[$grant['resourceId']] ?? null;
            if ($resource === null) {
                throw new RuntimeException($unresolved);
            }
            $scopes = preg_split('/\s+/', trim((string) ($grant['scope'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            if ($grant['consentType'] === 'AllPrincipals') {
                $kind = ADMIN_DELEGATED;
            } elseif ($grant['consentType'] === 'Principal') {
                $kind = USER_DELEGATED;
                $someUsers[(string) ($grant['principalId'] ?? '')] = true;
            } else {
                throw new RuntimeException('A delegated permission grant has an unsupported consent type; update the automation.');
            }
            foreach ($scopes as $scope) {
                $roles[] = [tpl_entraRole($kind, apiPermission($resource, $scope)), [$resource['id'], $scope]];
            }
        }

        if ($roles === []) {
            continue;
        }
        $publisher = $isMicrosoft ? 'Microsoft' : ($tenant !== '' && $owner === $tenant ? 'Your organization' : 'Third party');
        $consents = count($someUsers);
        $description = sprintf(
            '%s; %s; sign-in: %s; %sapplication ID %s',
            $publisher,
            ($sp['accountEnabled'] ?? false) === true ? 'enabled' : 'disabled',
            ($sp['appRoleAssignmentRequired'] ?? false) === true ? 'assigned users only' : 'everyone',
            $consents > 0 ? sprintf('some-user consents: %d %s; ', $consents, $consents === 1 ? 'user' : 'users') : '',
            $sp['appId']
        );
        $name = is_string($sp['displayName'] ?? null) ? mb_strcut(trim($sp['displayName']), 0, 240, 'UTF-8') : null;
        $rows[] = ['name' => tpl_feedText($name, 'Application name'), 'appId' => $sp['appId'], 'description' => $description, 'roles' => $roles];
    }
    // The row is the application's name; only applications that share a name also show the start of their
    // application ID, so reviewers can tell them apart.
    $names = array_count_values(array_map(static fn (array $row): string => mb_strtolower($row['name'], 'UTF-8'), $rows));
    foreach ($rows as &$row) {
        $shared = $names[mb_strtolower($row['name'], 'UTF-8')] > 1;
        $key = $shared ? mb_strcut($row['name'], 0, 240, 'UTF-8') . ' [' . substr($row['appId'], 0, 8) . ']' : $row['name'];
        $row = ['key' => tpl_feedKey($key, $seen, true)] + $row;
        unset($row['name'], $row['appId']);
    }
    unset($row);
    $rows = tpl_entraDisambiguate($rows);
    usort($rows, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));
    return $rows;
}

/**
 * "<API> / <permission>": a long API name is shortened first, so the permission name, which tells two
 * permissions of one API apart, is always kept whole.
 */
function apiPermission(array $resource, string $permission): string
{
    $api = $resource['displayName'] ?? null;
    if (!is_string($api) || preg_match('//u', $api) !== 1 || ($api = trim($api)) === '') {
        throw new RuntimeException('API name is missing or not valid text.');
    }
    return (strlen($api) > 100 ? mb_strcut($api, 0, 97, 'UTF-8') . '…' : $api) . ' / ' . $permission;
}

/** Aborts instead of publishing an empty or oversized list. */
function checkRows(array $rows, array $config): void
{
    if ($rows === [] && $config['ALLOW_EMPTY_FEED'] !== true) {
        throw new TplEmptyFeedException('No applications with permissions found; set INCLUDE_MICROSOFT_APPS to include Microsoft applications, or ALLOW_EMPTY_FEED if none is expected.');
    }
    if (count($rows) > $config['MAX_APPS']) {
        throw new RuntimeException('More applications than MAX_APPS; raise it (up to 5000).');
    }
}

/**
 * Reads the three inputs of permissionRows(). Every list aborts the run when it exceeds its limit.
 *
 * @return array{0: array, 1: array, 2: array} service principals, app role assignments by client id, delegated grants
 */
function collectPermissions(string $token, array $config): array
{
    // Resources (Microsoft Graph, other APIs) are service principals too, so the limit is wider than MAX_APPS.
    try {
        $servicePrincipals = tpl_graphList(
            'servicePrincipals?$select=id,appId,displayName,appOwnerOrganizationId,accountEnabled,appRoleAssignmentRequired,appRoles&$top=999',
            $token,
            min($config['MAX_APPS'] * 10, 10000)
        );
    } catch (TplGraphLimitException $e) {
        throw new RuntimeException(sprintf('The tenant has %s enterprise applications, more than the limit of %d (10 × MAX_APPS, at most 10000).',
            $e->total ?? 'more', $e->limit), 0, $e);
    }
    tpl_logInfo(count($servicePrincipals) . ' service principals.');

    try {
        $grants = tpl_graphList('oauth2PermissionGrants?$top=999', $token, $config['MAX_OBJECTS'], false);
    } catch (TplGraphLimitException $e) {
        throw new RuntimeException(sprintf('More than MAX_OBJECTS (%d) delegated permission grants; raise MAX_OBJECTS (up to 10000).', $e->limit), 0, $e);
    }
    tpl_logInfo(count($grants) . ' delegated permission grants.');

    $paths = [];
    foreach ($servicePrincipals as $sp) {
        $owner = strtolower((string) ($sp['appOwnerOrganizationId'] ?? ''));
        if (in_array($owner, MICROSOFT_TENANTS, true) && $config['INCLUDE_MICROSOFT_APPS'] !== true) {
            continue;
        }
        $paths[$sp['id']] = 'servicePrincipals/' . rawurlencode($sp['id']) . '/appRoleAssignments?$select=id,resourceId,appRoleId&$top=999';
    }
    $assignments = [];
    $total = 0;
    $ids = array_keys($paths);
    foreach (array_chunk($ids, 100) as $chunk) {
        $lists = tpl_graphLists(array_map(static fn($id) => $paths[$id], $chunk), $token, $config['MAX_OBJECTS'], false);
        foreach ($chunk as $n => $id) {
            if ($lists[$n] !== []) {
                $assignments[$id] = $lists[$n];
                $total += count($lists[$n]);
            }
        }
    }
    tpl_logInfo(sprintf('%d application permission assignments across %d applications checked.', $total, count($ids)));
    return [$servicePrincipals, $assignments, $grants];
}

// ─── 6. MAIN ────────────────────────────────────────────────────────────────
tpl_installErrorHandler();
tpl_elapsed();
try {
    tpl_log(sprintf('%s v%s', AUTOMATION_ID, AUTOMATION_VERSION));

    tpl_logStep(1, 'Checking configuration');
    validateConfig($config);
    tpl_checkSecrets($secrets);
    tpl_logInfo('Secrets present. Microsoft applications ' . ($config['INCLUDE_MICROSOFT_APPS'] ? 'included.' : 'excluded.'));

    tpl_logStep(2, 'Authenticating to Microsoft Graph');
    $token = tpl_graphToken($secrets['ENTRA_TENANT_ID'], $secrets['ENTRA_CLIENT_ID'], tpl_base64Secret($secrets['ENTRA_CLIENT_SECRET_B64'], 'entra_client_secret_b64'));

    tpl_logStep(3, 'Collecting applications and permissions');
    [$servicePrincipals, $assignments, $grants] = collectPermissions($token, $config);
    $rows = permissionRows($servicePrincipals, $assignments, $grants, $config + ['TENANT_ID' => $secrets['ENTRA_TENANT_ID']]);
    checkRows($rows, $config);

    tpl_logStep(4, 'Publishing the permission review feed');
    $feed = tpl_feedOpen();
    $publishers = ['Third party' => 0, 'Your organization' => 0, 'Microsoft' => 0];
    foreach ($rows as $row) {
        tpl_feedAdd($feed, $row['key'], $row['description'], $row['roles']);
        $publishers[explode(';', $row['description'], 2)[0]]++;
    }
    tpl_feedPublish($feed);
    tpl_logSummary('applications', [sprintf('%d third-party, %d your organization, %d Microsoft.', $publishers['Third party'], $publishers['Your organization'], $publishers['Microsoft'])]);
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('permission review feed', $e);
    exit(1);
}
