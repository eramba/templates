<?php
declare(strict_types=1);
// version: 1 — Auth0 Management API: token and complete page-based or checkpoint pagination

/** The canonical tenant domain (e.g. example.eu.auth0.com), as "https://host". */
function tpl_auth0BaseUrl(string $domain): string
{
    $domain = strtolower(trim(preg_replace('~^https://~i', '', trim($domain)), '/'));
    if (preg_match('~^[a-z0-9-]+(?:\.[a-z0-9-]+)+$~D', $domain) !== 1) {
        throw new RuntimeException('The Auth0 domain must look like example.eu.auth0.com (no path).');
    }
    return 'https://' . $domain;
}

/** Management API token through the client-credentials grant of a machine-to-machine application. */
function tpl_auth0Token(string $base, string $clientId, string $clientSecret): string
{
    $response = tpl_http('POST', $base . '/oauth/token', ['json' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'audience' => $base . '/api/v2/',
    ]]);
    $token = $response['data']['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Auth0 returned no access token.');
    }
    return $token;
}

/**
 * Every item of a page-based list (page, per_page=100, include_totals). Auth0 only returns the first
 * 1000 results this way: a larger total stops the run instead of returning a partial list.
 */
function tpl_auth0Pages(string $base, string $path, string $key, string $token, int $limit, string $itemId = 'id'): array
{
    $items = [];
    $total = null;
    for ($page = 0; $page < 10; $page++) {
        $url = $base . $path . (str_contains($path, '?') ? '&' : '?') . "page=$page&per_page=100&include_totals=true";
        $data = tpl_http('GET', $url, ['headers' => ['Authorization' => 'Bearer ' . $token]])['data'];
        if (!is_array($data[$key] ?? null) || !is_int($data['total'] ?? null)) {
            throw new RuntimeException("Auth0 returned an unexpected $key list.");
        }
        if ($total === null) {
            $total = $data['total'];
            if ($total > 1000) {
                throw new RuntimeException("Auth0 reports $total $key; this feed supports up to 1000 (Auth0's page limit).");
            }
            if ($total > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        } elseif ($data['total'] !== $total) {
            throw new RuntimeException("Auth0 $key changed during the pull; retry later.");
        }
        $items = tpl_auth0Add($items, $data[$key], $itemId);
        if ($data[$key] === [] || count($items) >= $total) {
            break;
        }
    }
    if (count($items) !== $total) {
        throw new RuntimeException("Auth0 $key list is incomplete or changed during the pull; retry later.");
    }
    return array_values($items);
}

/** Every item of a checkpoint list (take=100, from=<next>), used by role members and organizations. */
function tpl_auth0Checkpoint(string $base, string $path, string $key, string $token, int $limit, string $itemId = 'id'): array
{
    $items = [];
    $from = null;
    $seen = [];
    do {
        if (count($seen) >= 200) {
            throw new RuntimeException("Auth0 $key list exceeds 200 pages; narrow the scope.");
        }
        $url = $base . $path . (str_contains($path, '?') ? '&' : '?') . 'take=100' . ($from === null ? '' : '&from=' . rawurlencode($from));
        $data = tpl_http('GET', $url, ['headers' => ['Authorization' => 'Bearer ' . $token]])['data'];
        if (!is_array($data[$key] ?? null)) {
            throw new RuntimeException("Auth0 returned an unexpected $key list.");
        }
        $items = tpl_auth0Add($items, $data[$key], $itemId);
        if (count($items) > $limit) {
            throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
        }
        $from = $data['next'] ?? null;
        if ($from !== null && (!is_string($from) || $from === '' || isset($seen[$from]))) {
            throw new RuntimeException('Auth0 pagination checkpoint is invalid or repeats.');
        }
        if ($from !== null) {
            $seen[$from] = true;
        }
    } while ($from !== null);
    return array_values($items);
}

function tpl_auth0Add(array $items, array $page, string $itemId): array
{
    foreach ($page as $item) {
        if (!is_array($item) || !is_string($item[$itemId] ?? null) || $item[$itemId] === '') {
            throw new RuntimeException('Auth0 list contains an invalid item.');
        }
        if (isset($items[$item[$itemId]])) {
            throw new RuntimeException('Auth0 list contains a duplicate item.');
        }
        $items[$item[$itemId]] = $item;
    }
    return $items;
}
