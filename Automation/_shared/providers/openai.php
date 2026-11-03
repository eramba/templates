<?php
declare(strict_types=1);
// version: 1 — OpenAI Administration API: complete cursor lists (after / has_more)

/**
 * Every item of an OpenAI Administration API list, e.g. tpl_openaiList('/v1/organization/users', $adminKey, 2000).
 * Pages are requested with limit=100 and after=<last_id> while has_more is true. A page that says there is more
 * but gives no cursor, a repeated cursor, a duplicate item or more than $limit items stops the run.
 */
function tpl_openaiList(string $path, string $adminKey, int $limit, array $query = []): array
{
    $items = [];
    $after = null;
    $cursors = [];
    for ($page = 0; ; $page++) {
        if ($page >= 200) {
            throw new RuntimeException('OpenAI pagination exceeds 200 pages; narrow the scope.');
        }
        $params = $query + ['limit' => 100] + ($after === null ? [] : ['after' => $after]);
        $url = 'https://api.openai.com' . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $data = tpl_http('GET', $url, ['headers' => ['Authorization' => 'Bearer ' . $adminKey]])['data'];
        if (($data['object'] ?? null) !== 'list' || !is_array($data['data'] ?? null) || !array_is_list($data['data'])
            || !is_bool($data['has_more'] ?? null)) {
            throw new RuntimeException('OpenAI returned an unexpected list.');
        }
        foreach ($data['data'] as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null) || $item['id'] === '') {
                throw new RuntimeException('OpenAI list contains an invalid item.');
            }
            if (isset($items[$item['id']])) {
                throw new RuntimeException('OpenAI list contains a duplicate item; the organization changed during the pull, retry later.');
            }
            $items[$item['id']] = $item;
            if (count($items) > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        }
        if (!$data['has_more']) {
            return array_values($items);
        }
        $after = $data['last_id'] ?? null;
        if (!is_string($after) || $after === '' || isset($cursors[$after])) {
            throw new RuntimeException('OpenAI pagination cursor is missing or repeats; retry later.');
        }
        $cursors[$after] = true;
    }
}
