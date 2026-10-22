<?php
declare(strict_types=1);
// version: 1 — Anthropic Admin API: complete cursor lists (after_id / has_more)

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
