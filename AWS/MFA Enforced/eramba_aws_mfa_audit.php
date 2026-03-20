<?php
declare(strict_types=1);

/**
 * Eramba single-run automation: AWS IAM "enabled accounts" without MFA.
 *
 * Inputs via environment variables:
 *   AWS_ACCESS_KEY_ID        (required)
 *   AWS_SECRET_ACCESS_KEY    (required)
 *   AWS_SESSION_TOKEN        (optional)
 *   AWS_REGION               (required; e.g. eu-central-1)
 *
 * Output:
 *   STDOUT: "Accounts without MFA: <n>"
 *   STDERR: errors
 *
 * Exit codes:
 *   0 = success
 *   1 = failure
 */

require __DIR__ . '/vendor/autoload.php';

use Aws\Iam\IamClient;
use Aws\Exception\AwsException;

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return $v;
}

function stderr(string $msg): void {
    fwrite(STDERR, $msg);
    if (!str_ends_with($msg, "\n")) {
        fwrite(STDERR, "\n");
    }
}

function isEnabledIamUser(IamClient $iam, string $userName): bool {
    // "Enabled" is not a native IAM user attribute. We interpret enabled as:
    // - has a console login profile, OR
    // - has at least one ACTIVE access key.
    // If neither is true, we treat the user as "not enabled" and exclude it.
    $hasConsole = false;
    try {
        $iam->getLoginProfile(['UserName' => $userName]);
        $hasConsole = true;
    } catch (AwsException $e) {
        // If there's no login profile, AWS returns NoSuchEntity.
        // Any other error should bubble up.
        $code = (string)($e->getAwsErrorCode() ?? '');
        if ($code !== 'NoSuchEntity') {
            throw $e;
        }
    }

    $hasActiveKey = false;
    $marker = null;
    do {
        $params = ['UserName' => $userName];
        if ($marker !== null) {
            $params['Marker'] = $marker;
        }
        $resp = $iam->listAccessKeys($params);
        foreach (($resp['AccessKeyMetadata'] ?? []) as $meta) {
            if (($meta['Status'] ?? '') === 'Active') {
                $hasActiveKey = true;
                break 2;
            }
        }
        $marker = ($resp['IsTruncated'] ?? false) ? ($resp['Marker'] ?? null) : null;
    } while ($marker !== null);

    return $hasConsole || $hasActiveKey;
}

function userHasMfaDevice(IamClient $iam, string $userName): bool {
    // We consider MFA "enforced" as "user has at least one MFA device assigned".
    $resp = $iam->listMFADevices(['UserName' => $userName]);
    $devices = $resp['MFADevices'] ?? [];
    return count($devices) > 0;
}

$accessKeyId     = "%SECRET_aws_key%";
$secretAccessKey = "%SECRET_aws_secret%";
$sessionToken    = null;
$region          = "%SECRET_aws_region%";

if (!$accessKeyId || !$secretAccessKey || !$region) {
    stderr("Missing required environment variables. Required: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_REGION.");
    exit(1);
}

try {
    $config = [
        'version'     => 'latest',
        'region'      => $region,
        'credentials' => [
            'key'    => $accessKeyId,
            'secret' => $secretAccessKey,
        ],
    ];
    if ($sessionToken) {
        $config['credentials']['token'] = $sessionToken;
    }

    $iam = new IamClient($config);

    // Quick sanity check for credentials.
    $iam->getUser();

    $withoutMfa = 0;

    $marker = null;
    do {
        $params = [];
        if ($marker !== null) {
            $params['Marker'] = $marker;
        }

        $result = $iam->listUsers($params);

        foreach (($result['Users'] ?? []) as $user) {
            $userName = $user['UserName'] ?? null;
            if (!$userName) {
                continue;
            }

            // Filter to "enabled" users (see interpretation above)
            if (!isEnabledIamUser($iam, $userName)) {
                continue;
            }

            if (!userHasMfaDevice($iam, $userName)) {
                $withoutMfa++;
            }
        }

        $marker = ($result['IsTruncated'] ?? false) ? ($result['Marker'] ?? null) : null;
    } while ($marker !== null);

    fwrite(STDOUT, "Accounts without MFA: {$withoutMfa}\n");
    exit(0);

} catch (AwsException $e) {
    $awsMsg  = $e->getAwsErrorMessage();
    $awsCode = $e->getAwsErrorCode();
    $msg = "AWS error";
    if ($awsCode) $msg .= " ({$awsCode})";
    if ($awsMsg)  $msg .= ": {$awsMsg}";
    stderr($msg);
    exit(1);
} catch (Throwable $e) {
    stderr("Unhandled error: " . $e->getMessage());
    exit(1);
}
