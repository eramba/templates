<?php
declare(strict_types=1);
// version: 1 — AWS SDK for PHP (Composer package aws/aws-sdk-php): client options, bounded calls and Marker pagination

/** Client options for one region: static access keys, bounded timeouts, the SDK's standard retries. */
function tpl_awsOptions(string $accessKeyId, string $secretAccessKey, string $region): array
{
    if (preg_match('/^(AKIA|ASIA)[A-Z0-9]{12,124}$/D', $accessKeyId) !== 1 || strlen($secretAccessKey) < 20) {
        throw new RuntimeException('The AWS access key ID or secret access key is invalid.');
    }
    if (preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D', $region) !== 1) {
        throw new RuntimeException('Invalid AWS region.');
    }
    return [
        'version' => 'latest',
        'region' => $region,
        'credentials' => ['key' => $accessKeyId, 'secret' => $secretAccessKey],
        'http' => ['connect_timeout' => 5, 'timeout' => 20],
        'retries' => 3,
    ];
}

/** One API call. AWS errors are reduced to their code: requests and responses may contain credentials. */
function tpl_awsCall(object $client, string $operation, array $args = []): array
{
    tpl_remaining();
    try {
        return $client->$operation($args)->toArray();
    } catch (\Aws\Exception\AwsException $e) {
        throw new RuntimeException($operation . ': AWS ' . ($e->getAwsErrorCode() ?: 'request failed') . '; check permissions and credentials.');
    }
}

/**
 * Every page of an IAM-style list (Marker / IsTruncated). Returns the merged lists named in $keys,
 * e.g. tpl_awsPages($iam, 'getAccountAuthorizationDetails', ['Filter' => ['User']], ['UserDetailList'], 2000).
 */
function tpl_awsPages(object $client, string $operation, array $args, array $keys, int $limit): array
{
    $lists = array_fill_keys($keys, []);
    $markers = [];
    do {
        if (count($markers) >= 200) {
            throw new RuntimeException("$operation exceeds 200 pages; narrow the scope.");
        }
        $page = tpl_awsCall($client, $operation, $args);
        foreach ($keys as $key) {
            if (!is_array($page[$key] ?? [])) {
                throw new RuntimeException("$operation returned an unexpected $key.");
            }
            array_push($lists[$key], ...($page[$key] ?? []));
            if (count($lists[$key]) > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        }
        $truncated = ($page['IsTruncated'] ?? false) === true;
        if ($truncated) {
            $marker = $page['Marker'] ?? null;
            if (!is_string($marker) || $marker === '' || isset($markers[$marker])) {
                throw new RuntimeException("$operation pagination marker is missing or repeats.");
            }
            $markers[$marker] = true;
            $args['Marker'] = $marker;
        }
    } while ($truncated);
    return $lists;
}
