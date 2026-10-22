<?php
declare(strict_types=1);
// version: 1 — GitHub REST API: headers, complete Link-header pagination and primary rate-limit checks

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
