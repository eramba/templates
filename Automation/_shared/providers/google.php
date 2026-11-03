<?php
declare(strict_types=1);
// version: 1 — Google APIs (Composer package google/apiclient): service-account key, delegated client, complete page-token lists

/**
 * The fields of a service-account JSON key that sign the token request. Credential URLs in the key
 * (token_uri, auth_provider_x509_cert_url…) are never used, so a tampered key cannot redirect the token.
 */
function tpl_googleServiceAccount(string $json): array
{
    try {
        $key = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('The service-account key is not valid JSON.');
    }
    if (!is_array($key) || ($key['type'] ?? '') !== 'service_account'
        || !is_string($key['client_email'] ?? null)
        || preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.iam\.gserviceaccount\.com$/D', $key['client_email']) !== 1
        || !is_string($key['client_id'] ?? null) || !ctype_digit($key['client_id'])
        || !is_string($key['private_key'] ?? null) || !is_string($key['private_key_id'] ?? null)) {
        throw new RuntimeException('The service-account key lacks its signing fields; download a new JSON key.');
    }
    $private = openssl_pkey_get_private($key['private_key']);
    $details = $private === false ? false : openssl_pkey_get_details($private);
    if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
        throw new RuntimeException('The service-account private key is not a valid RSA key.');
    }
    return array_intersect_key($key, array_flip(['type', 'client_email', 'client_id', 'private_key', 'private_key_id']));
}

/**
 * A Google API client signed by the service account and acting as $subject (domain-wide delegation),
 * limited to $scopes. TLS verification on, redirects off, 20-second requests, responses over 4 MB and
 * the run budget stop the transfer. The SDK retries 500/503 and rate-limit errors up to 3 times.
 */
function tpl_googleClient(array $key, string $subject, array $scopes): \Google\Client
{
    $maxBytes = 4 * 1024 * 1024;
    $http = new \GuzzleHttp\Client([
        'connect_timeout' => 5,
        'timeout' => 20,
        'allow_redirects' => false,
        'verify' => true,
        'on_headers' => static function (\Psr\Http\Message\ResponseInterface $response) use ($maxBytes): void {
            if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                throw new RuntimeException('Google response too large; narrow the scope.');
            }
        },
        'progress' => static function ($total, $downloaded) use ($maxBytes): void {
            if ($downloaded > $maxBytes) {
                throw new RuntimeException('Google response too large; narrow the scope.');
            }
            tpl_remaining();
        },
    ]);
    try {
        $client = new \Google\Client([
            'retry' => ['retries' => 3, 'initial_delay' => 1, 'max_delay' => 8],
            'universe_domain' => 'googleapis.com',
        ]);
        $client->setHttpClient($http);
        $client->setAuthConfig($key);
        $client->setSubject($subject);
        $client->setScopes($scopes);
    } catch (Throwable $e) {
        throw new RuntimeException('Google client initialization failed; check the google/apiclient package and the service-account key.');
    }
    return $client;
}

/**
 * One Google API call. Errors keep only the HTTP status and Google's one-word reason: messages and
 * bodies may contain account data. Budget and size errors raised during the transfer are kept.
 */
function tpl_googleCall(string $what, callable $call): mixed
{
    tpl_remaining();
    try {
        return $call();
    } catch (\Google\Service\Exception $e) {
        $reason = $e->getErrors()[0]['reason'] ?? '';
        $reason = is_string($reason) && preg_match('/^[A-Za-z]{1,40}$/D', $reason) === 1 ? ", $reason" : '';
        throw new RuntimeException(sprintf(
            '%s: Google returned HTTP %d%s; check domain-wide delegation, scopes and the delegated admin.',
            $what,
            $e->getCode(),
            $reason
        ));
    } catch (Throwable $e) {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            $kept = '/^(Time|Memory) budget exceeded|^Google response too large/';
            if ($cause instanceof RuntimeException && preg_match($kept, $cause->getMessage()) === 1) {
                throw new RuntimeException($cause->getMessage());
            }
        }
        throw new RuntimeException("$what: the request to Google failed; check connectivity and the service-account key.");
    }
}

/**
 * Every item of a Google list, following nextPageToken to the last page. $fetch receives the page
 * parameters (['pageToken' => …] after the first page) and returns the list response; $items names
 * its collection (users, groups, members, items). More than $limit items or 200 pages aborts.
 */
function tpl_googleList(string $what, callable $fetch, string $items, int $limit): array
{
    $getter = 'get' . ucfirst($items);
    $all = [];
    $tokens = [];
    $params = [];
    for ($page = 0; $page < 200; $page++) {
        $response = tpl_googleCall($what, static fn () => $fetch($params));
        if (!is_object($response) || !method_exists($response, $getter)) {
            throw new RuntimeException("$what returned an unexpected response.");
        }
        $batch = $response->$getter() ?? []; // Google omits the collection when it is empty
        if (!is_array($batch)) {
            throw new RuntimeException("$what returned an unexpected collection.");
        }
        array_push($all, ...$batch);
        if (count($all) > $limit) {
            throw new RuntimeException("$what exceeds the configured limit; narrow the scope or raise the limit.");
        }
        $next = $response->getNextPageToken();
        if ($next === null || $next === '') {
            return $all;
        }
        if (!is_string($next) || isset($tokens[$next])) {
            throw new RuntimeException("$what pagination token is invalid or repeats.");
        }
        $tokens[$next] = true;
        $params = ['pageToken' => $next];
    }
    throw new RuntimeException("$what exceeds 200 pages; narrow the scope.");
}
