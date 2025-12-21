<?php
declare(strict_types=1);

/**
 * Eramba Automation - AWS EBS Volume Encryption Audit
 *
 * Dual-mode inputs:
 *  - Local testing: reads from environment variables (export ...)
 *  - Eramba: falls back to literal placeholders {$VARNAME} which Eramba replaces with secrets before execution
 *
 * Required:
 *  - AWS_ACCESS_KEY_ID
 *  - AWS_SECRET_ACCESS_KEY
 *  - AWS_REGIONS (comma-separated)
 *
 * Optional:
 *  - AWS_SESSION_TOKEN
 *  - EBS_VOLUME_NAME_REGEX (default .*)  // applied to ANY tag key/value pair "Key=Value"
 */

require __DIR__ . '/vendor/autoload.php';

use Aws\Ec2\Ec2Client;

function input(string $envName, string $placeholder): string {
    $v = getenv($envName);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $placeholder;
}

function isUnreplacedPlaceholder(string $v): bool {
    // Detect literal "{$VARNAME}" strings (i.e., not replaced by Eramba and no env var provided)
    return (bool)preg_match('/^\{\$[A-Z0-9_]+\}$/', $v);
}

function buildRegex(string $pattern): string {
    $pattern = trim($pattern);
    if ($pattern === '' || isUnreplacedPlaceholder($pattern)) {
        $pattern = '.*';
    }

    // If user supplied delimiters like /.../ or ~...~, keep as-is
    $delim = $pattern[0];
    $last  = substr($pattern, -1);
    if (strlen($pattern) >= 3 && $delim === $last && !ctype_alnum($delim) && $delim !== '\\') {
        return $pattern;
    }

    // Otherwise wrap with a safe delimiter
    $delimiter = '~';
    $escaped = str_replace($delimiter, '\\' . $delimiter, $pattern);
    return $delimiter . $escaped . $delimiter;
}

function getTagValue(array $tags, string $key): ?string {
    foreach ($tags as $t) {
        if (isset($t['Key'], $t['Value']) && $t['Key'] === $key) {
            return (string)$t['Value'];
        }
    }
    return null;
}


function matchesTagPairRegex(array $tags, string $regex): bool {
    // Build strings "Key=Value" for each tag and test the user regex.
    // If there are no tags, test against empty string so ".*" includes untagged volumes.
    if (empty($tags)) {
        return @preg_match($regex, '') === 1;
    }

    foreach ($tags as $t) {
        if (!isset($t['Key'])) {
            continue;
        }
        $k = (string)$t['Key'];
        $v = isset($t['Value']) ? (string)$t['Value'] : '';
        $pair = $k . '=' . $v;
        if (@preg_match($regex, $pair) === 1) {
            return true;
        }
    }
    return false;
}

function normalizeRegions(string $regionsRaw): array {
    $regionsRaw = trim($regionsRaw);
    if ($regionsRaw === '' || isUnreplacedPlaceholder($regionsRaw)) {
        return [];
    }
    $parts = array_map('trim', explode(',', $regionsRaw));
    $parts = array_values(array_filter($parts, fn($r) => $r !== ''));
    return $parts;
}

// Inputs (env first; then Eramba placeholders)
$awsKey       = input('AWS_ACCESS_KEY_ID', '{$AWS_ACCESS_KEY_ID}');
$awsSecret    = input('AWS_SECRET_ACCESS_KEY', '{$AWS_SECRET_ACCESS_KEY}');
$awsToken     = input('AWS_SESSION_TOKEN', '{$AWS_SESSION_TOKEN}');
$regionsRaw   = input('AWS_REGIONS', '{$AWS_REGIONS}');
$labelSource  = input('EBS_LABEL_SOURCE', '{$EBS_LABEL_SOURCE}');
$regexPattern = input('EBS_VOLUME_NAME_REGEX', '{$EBS_VOLUME_NAME_REGEX}');

// Defaults
if ($labelSource === '' || isUnreplacedPlaceholder($labelSource)) {
    $labelSource = 'tag:Name';
}

$regions = normalizeRegions($regionsRaw);
if (empty($regions)) {
    echo json_encode([
        'error' => 'Missing AWS_REGIONS. Provide a comma-separated list via environment variable AWS_REGIONS (local) or Eramba secret {$AWS_REGIONS}.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

if ($awsKey === '' || $awsSecret === '' || isUnreplacedPlaceholder($awsKey) || isUnreplacedPlaceholder($awsSecret)) {
    echo json_encode([
        'error' => 'Missing AWS credentials. For local testing export AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY. In Eramba, set secrets {$AWS_ACCESS_KEY_ID} and {$AWS_SECRET_ACCESS_KEY}.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$regex = buildRegex($regexPattern);

// Validate regex early
set_error_handler(function($errno, $errstr) {
    throw new RuntimeException($errstr);
});
try {
    @preg_match($regex, '');
} catch (Throwable $e) {
    restore_error_handler();
    echo json_encode([
        'error' => 'Invalid EBS_VOLUME_NAME_REGEX.',
        'details' => $e->getMessage(),
        'provided' => $regexPattern,
        'compiled' => $regex
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
restore_error_handler();

$report = [
    'summary' => [
        'generated_at' => gmdate('c'),
        'label_source' => 'any_tag_pair',
        'regex' => $regexPattern === '' || isUnreplacedPlaceholder($regexPattern) ? '.*' : $regexPattern,
        'regions' => []
    ],
    'regions' => []
];

foreach ($regions as $region) {
    $clientConfig = [
        'version' => 'latest',
        'region'  => $region,
        'credentials' => [
            'key'    => $awsKey,
            'secret' => $awsSecret
        ]
    ];
    if ($awsToken !== '' && !isUnreplacedPlaceholder($awsToken)) {
        $clientConfig['credentials']['token'] = $awsToken;
    }

    $ec2 = new Ec2Client($clientConfig);

    $volumesOut = [];
    $total = 0;
    $enc = 0;
    $notEnc = 0;

    try {
        $nextToken = null;
        do {
            $args = [];
            if ($nextToken) {
                $args['NextToken'] = $nextToken;
            }
            $res = $ec2->describeVolumes($args);

            $volumes = $res->get('Volumes') ?? [];
            foreach ($volumes as $v) {
                $tags = $v['Tags'] ?? [];

// Match against ANY tag key/value "pair" (tag name is not special).
// We test the regex against strings like "Key=Value" for each tag.
// If there are no tags, we test against an empty string (so default ".*" includes untagged volumes).
if (!matchesTagPairRegex($tags, $regex)) {
    continue;
}

// "name" in the report is the common Name tag value when present (empty otherwise).
$nameTag = getTagValue($tags, 'Name');

                $encrypted = (bool)($v['Encrypted'] ?? false);

                $total++;
                if ($encrypted) { $enc++; } else { $notEnc++; }

                $volumesOut[] = [
                    'volume_id' => (string)($v['VolumeId'] ?? ''),
                    'name' => ($nameTag ?? ''),
                    'encrypted' => $encrypted ? true : false
                ];
            }

            $nextToken = $res->get('NextToken');
        } while (!empty($nextToken));

        $report['summary']['regions'][$region] = [
            'total' => $total,
            'encrypted' => $enc,
            'not_encrypted' => $notEnc
        ];
        $report['regions'][$region] = $volumesOut;

    } catch (Throwable $e) {
        $report['summary']['regions'][$region] = [
            'total' => 0,
            'encrypted' => 0,
            'not_encrypted' => 0,
            'error' => $e->getMessage()
        ];
        $report['regions'][$region] = [];
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(0);
