<?php
declare(strict_types=1);
// version: 2 — Microsoft Graph (public cloud): app-only token, complete collections, JSON batching

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
