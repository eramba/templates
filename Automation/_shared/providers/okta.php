<?php
declare(strict_types=1);
// version: 1 — Okta Management API: org URL validation and complete Link-header pagination

/** The Okta org URL (e.g. https://example.okta.com), as "https://host" without path, port or credentials. */
function tpl_oktaBaseUrl(string $url): string
{
    $url = rtrim(trim($url), '/');
    if (preg_match('~^https://[a-z0-9-]+(?:\.[a-z0-9-]+)+$~D', $url) !== 1) {
        throw new RuntimeException('The Okta org URL must look like https://example.okta.com (HTTPS, no path).');
    }
    return $url;
}

/** One Okta object, e.g. tpl_oktaGet($base, '/api/v1/users/<id>', $token). */
function tpl_oktaGet(string $base, string $path, string $token): array
{
    return tpl_http('GET', $base . $path, ['headers' => ['Authorization' => 'SSWS ' . $token]])['data'];
}

/**
 * Every item of an Okta list, following the Link: <…>; rel="next" header to the last page.
 * Okta returns no totals, so completeness relies on reaching a page without a next link.
 */
function tpl_oktaList(string $base, string $path, string $token, int $limit): array
{
    $first = $base . $path;
    $url = $first;
    $seen = [];
    $items = [];
    while ($url !== null) {
        if (isset($seen[$url]) || count($seen) >= 200) {
            throw new RuntimeException('Okta pagination repeats or exceeds 200 pages; narrow the scope.');
        }
        $seen[$url] = true;
        $response = tpl_http('GET', $url, ['headers' => ['Authorization' => 'SSWS ' . $token]]);
        if (!array_is_list($response['data'])) {
            throw new RuntimeException('Okta returned an unexpected list.');
        }
        foreach ($response['data'] as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null) || $item['id'] === '') {
                throw new RuntimeException('Okta list contains an invalid item.');
            }
            if (isset($items[$item['id']])) {
                throw new RuntimeException('Okta list contains a duplicate item.');
            }
            $items[$item['id']] = $item;
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
