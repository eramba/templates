<?php
declare(strict_types=1);
// version: 1 — Microsoft Graph (public cloud): app-only token, complete collections, JSON batching

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
            throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
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
