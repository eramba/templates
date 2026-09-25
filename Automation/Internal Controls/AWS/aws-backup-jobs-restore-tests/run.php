<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  AWS Backup – Backup Execution and Restore Test
 *  id: aws-backup-jobs-restore-tests        version: 0.2.0
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Internal%20Controls
 *
 *  Composer packages (paste in the automation's composer field):
 *    aws/aws-sdk-php:^3.300
 * ============================================================================
 *
 *  Output
 *    STDOUT  Step-by-step log of what the script is doing (visible in eramba
 *            Automation Logs). eramba keeps only the first 10 KB.
 *    STDERR  Only used for technical errors. Any STDERR output marks the run
 *            as failed in eramba.
 *
 *  Exit codes
 *    0  Check executed. The audit was updated with Passed or Failed.
 *    1  Technical error (credentials, network, permissions, eramba API).
 *       The audit is NOT updated, it keeps no result and eramba emails the
 *       administrators.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Create them in Settings / Application Configuration / Automation Secrets.
// eramba replaces %SECRET_<name>% with the secret value before running.
$secrets = [
    'AWS_ACCESS_KEY_ID'     => '%SECRET_aws_access_key_id%',
    'AWS_SECRET_ACCESS_KEY' => '%SECRET_aws_secret_access_key%',
];

// ─── 2. VARIABLES (edit to adjust scope and thresholds) ─────────────────────
// Every variable is documented in README.md §7.
$config = [
    // Connection
    'AWS_ROLE_ARN'     => '',             // Role to assume (recommended). '' = use the access key directly
    'AWS_EXTERNAL_ID'  => '',             // External ID required by the role trust policy ('' = none)
    'REGIONS'          => ['eu-west-1'],  // Regions where AWS Backup is checked

    // Scope: which resources are audited (applies to all checks)
    'RESOURCE_TYPES'     => [],           // AWS Backup resource types, e.g. ['EBS', 'RDS', 'EFS']. [] = all
    'RESOURCE_ARN_REGEX' => '/.*/',       // Regex on the resource ARN. '/.*/' = all
    'RESOURCE_TAGS'      => [],           // Tag key => regex on value, e.g. ['Backup' => '/^(daily|yes)$/i']. [] = no tag filter

    // Check A – backup jobs completed without failures (control: "no backup jobs have failed silently")
    'CHECK_BACKUP_JOBS'         => true,
    'BACKUP_LOOKBACK_DAYS'      => 30,    // Period reviewed. AWS returns at most 30 days of backup jobs: audit monthly
    'BACKUP_FAILED_STATES'      => ['FAILED', 'ABORTED', 'EXPIRED', 'PARTIAL'],
    'IGNORE_RECOVERED_FAILURES' => false, // true = ignore a failed job if a later job for the same resource completed
    'MIN_COMPLETED_BACKUP_JOBS' => 1,     // Fewer completed jobs in the period = FAILED

    // Check B – every protected resource has a recent backup (policy: backups consistent with RPO)
    'CHECK_BACKUP_FRESHNESS' => true,
    'MAX_BACKUP_AGE_HOURS'   => 24,       // RPO: last successful backup must be newer than this

    // Check C – restore tests performed (control: restore test at minimum quarterly)
    'CHECK_RESTORE_TESTS'             => true,
    'RESTORE_LOOKBACK_DAYS'           => 90,
    'MIN_RESTORE_TESTS'               => 1,     // Completed restore jobs required in the period
    'ONLY_RESTORE_TESTING_PLANS'      => false, // true = only count restores created by AWS Backup restore testing plans
    'REQUIRED_RESTORE_RESOURCE_TYPES' => [],    // Each listed type needs >= 1 completed restore, e.g. ['RDS', 'EBS']

    // Check D – restore time within RTO (control: "restore time meets defined RTO")
    'CHECK_RESTORE_TIME'  => true,
    'MAX_RESTORE_MINUTES' => 240,         // RTO in minutes

    // Check E – off-site copy (policy step 6: copy in a geographically separate location)
    'CHECK_OFFSITE_COPY' => false,        // Needs AWS Backup copy rules to another region or account

    // Output
    'ATTACH_EVIDENCE'  => true,           // Attach the evidence CSV and the configuration used
    'RESULT_PASSED_ID' => 2,              // Audit result option "Passed"
    'RESULT_FAILED_ID' => 1,              // Audit result option "Failed"
    'MAX_LOG_ITEMS'    => 20,             // Max failures listed in STDOUT/conclusion (full list in evidence)
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'aws-backup-jobs-restore-tests';
const AUTOMATION_VERSION = '0.2.0';

function logStep(int $n, string $title): void
{
    echo sprintf("\n[%s] STEP %d: %s\n", gmdate('H:i:s'), $n, $title);
}

function logInfo(string $message): void
{
    echo '  ' . $message . "\n";
}

/** eramba wrappers return "ERROR: …"/"WARNING: …" strings instead of throwing. */
function erambaCall(string $response, string $action): string
{
    if (str_starts_with($response, 'ERROR:') || str_starts_with($response, 'WARNING:')) {
        throw new RuntimeException("eramba $action failed: $response");
    }
    return $response;
}

/** A secret that eramba did not replace is still "%SECRET_<name>%": it does not exist. */
function checkSecrets(array $secrets): void
{
    foreach ($secrets as $key => $value) {
        if ($value === '' || preg_match('/^%SECRET_([^%]+)%$/', $value, $m) === 1) {
            $secretName = $m[1] ?? strtolower($key);
            throw new RuntimeException(
                "Secret '$secretName' is missing: create it with exactly that name in Settings / Application Configuration / Automation Secrets (see README §6)."
            );
        }
    }
}

function abortMessage(string $kind): string
{
    return empty($GLOBALS['auditWritten'])
        ? "\nABORTED: $kind, audit left without result (see STDERR).\n"
        : "\nABORTED: $kind after the audit result was saved; the comment is missing (see STDERR).\n";
}

/** One item checked. $region may be '' for technologies without regions. */
function result(string $check, string $region, string $resource, bool $passed, string $detail): array
{
    return ['check' => $check, 'region' => $region, 'resource' => $resource, 'passed' => $passed, 'detail' => $detail];
}

// ─── 5. COLLECT: AWS Backup ─────────────────────────────────────────────────

/** Returns SDK credentials: temporary role credentials if AWS_ROLE_ARN is set. */
function awsCredentials(array $secrets, array $config): array|Aws\Credentials\Credentials
{
    $base = ['key' => $secrets['AWS_ACCESS_KEY_ID'], 'secret' => $secrets['AWS_SECRET_ACCESS_KEY']];
    if ($config['AWS_ROLE_ARN'] === '') {
        logInfo('Authenticating with the access key directly (no role configured).');
        return $base;
    }

    $sts = new Aws\Sts\StsClient(awsClientOptions($config['REGIONS'][0], $base));
    $params = [
        'RoleArn'         => $config['AWS_ROLE_ARN'],
        'RoleSessionName' => 'eramba-' . AUTOMATION_ID,
        'DurationSeconds' => 900,
    ];
    if ($config['AWS_EXTERNAL_ID'] !== '') {
        $params['ExternalId'] = $config['AWS_EXTERNAL_ID'];
    }
    $c = $sts->assumeRole($params)['Credentials'];
    logInfo('Assumed role ' . $config['AWS_ROLE_ARN'] . ' (temporary credentials, 15 min).');

    return new Aws\Credentials\Credentials($c['AccessKeyId'], $c['SecretAccessKey'], $c['SessionToken']);
}

function awsClientOptions(string $region, array|Aws\Credentials\Credentials $credentials): array
{
    return [
        'region'      => $region,
        'version'     => 'latest',
        'credentials' => $credentials,
        'retries'     => 3,
        'http'        => ['connect_timeout' => 5, 'timeout' => 20],
    ];
}

/** Runs an SDK paginator and returns all items of $key. */
function paginate(Aws\AwsClient $client, string $operation, array $params, string $key): array
{
    $items = [];
    foreach ($client->getPaginator($operation, $params) as $page) {
        foreach ($page[$key] ?? [] as $item) {
            $items[] = $item;
        }
    }
    return $items;
}

function toTs(mixed $date): ?int
{
    if ($date instanceof DateTimeInterface) {
        return $date->getTimestamp();
    }
    return $date ? (int) strtotime((string) $date) : null;
}

function fmtTs(mixed $date): string
{
    $ts = toTs($date);
    return $ts ? gmdate('Y-m-d H:i', $ts) : 'unknown date';
}

/** Field of an ARN: arn:aws:backup:eu-west-1:111111111111:backup-vault:x → [3] region, [4] account. */
function arnPart(string $arn, int $index): string
{
    return explode(':', $arn)[$index] ?? '';
}

/** ARNs allowed by the RESOURCE_TAGS filter in one region, or null when no tag filter is set. */
function taggedArns(array|Aws\Credentials\Credentials $credentials, string $region, array $config): ?array
{
    if ($config['RESOURCE_TAGS'] === []) {
        return null;
    }
    $tagging = new Aws\ResourceGroupsTaggingAPI\ResourceGroupsTaggingAPIClient(awsClientOptions($region, $credentials));
    $allowed = null;
    foreach ($config['RESOURCE_TAGS'] as $key => $regex) {
        $matches = [];
        $resources = paginate($tagging, 'GetResources', ['TagFilters' => [['Key' => $key]]], 'ResourceTagMappingList');
        foreach ($resources as $r) {
            foreach ($r['Tags'] as $tag) {
                if ($tag['Key'] === $key && preg_match($regex, $tag['Value']) === 1) {
                    $matches[$r['ResourceARN']] = true;
                }
            }
        }
        // All tag conditions must match (AND)
        $allowed = $allowed === null ? $matches : array_intersect_key($allowed, $matches);
    }
    logInfo(sprintf('Tag filter: %d resources match in %s.', count($allowed), $region));
    return $allowed;
}

function inScope(?string $arn, ?string $type, array $config, ?array $tagged): bool
{
    if ($config['RESOURCE_TYPES'] !== [] && !in_array($type, $config['RESOURCE_TYPES'], true)) {
        return false;
    }
    if ($arn === null || $arn === '') {
        // Some restore jobs do not report their source: keep them only without ARN/tag filters
        return $config['RESOURCE_ARN_REGEX'] === '/.*/' && $tagged === null;
    }
    if (preg_match($config['RESOURCE_ARN_REGEX'], $arn) !== 1) {
        return false;
    }
    return $tagged === null || isset($tagged[$arn]);
}

function collectResults(array $secrets, array $config): array
{
    $credentials = awsCredentials($secrets, $config);
    $now = time();
    $results = [];
    if ($config['BACKUP_LOOKBACK_DAYS'] > 30) {
        logInfo('Note: AWS Backup only returns backup and copy jobs of the last 30 days.');
    }

    foreach ($config['REGIONS'] as $region) {
        logInfo("── Region $region");
        $backup = new Aws\Backup\BackupClient(awsClientOptions($region, $credentials));
        $tagged = taggedArns($credentials, $region, $config);

        // A. Backup jobs in the period
        $completedJobs = [];
        if ($config['CHECK_BACKUP_JOBS'] || $config['CHECK_OFFSITE_COPY']) {
            $since = $now - $config['BACKUP_LOOKBACK_DAYS'] * 86400;
            $jobs = paginate($backup, 'ListBackupJobs', ['ByCreatedAfter' => $since], 'BackupJobs');
            $jobs = array_values(array_filter($jobs, fn ($j) => !($j['IsParent'] ?? false)
                && inScope($j['ResourceArn'] ?? null, $j['ResourceType'] ?? null, $config, $tagged)));
            $completedJobs = array_values(array_filter($jobs, fn ($j) => $j['State'] === 'COMPLETED'));
            $failedJobs = array_values(array_filter($jobs, fn ($j) => in_array($j['State'], $config['BACKUP_FAILED_STATES'], true)));
            logInfo(sprintf('Backup jobs (last %d days): %d in scope, %d completed, %d failed.',
                $config['BACKUP_LOOKBACK_DAYS'], count($jobs), count($completedJobs), count($failedJobs)));
        }

        if ($config['CHECK_BACKUP_JOBS']) {
            // Last successful completion per resource, to detect recovered failures
            $lastOk = [];
            foreach ($completedJobs as $j) {
                $lastOk[$j['ResourceArn']] = max($lastOk[$j['ResourceArn']] ?? 0, toTs($j['CompletionDate'] ?? null) ?? 0);
            }
            foreach ($failedJobs as $j) {
                $recovered = ($lastOk[$j['ResourceArn']] ?? 0) > (toTs($j['CreationDate'] ?? null) ?? 0);
                if ($recovered && $config['IGNORE_RECOVERED_FAILURES']) {
                    continue;
                }
                $results[] = result('backup_jobs', $region, $j['ResourceArn'], false, sprintf(
                    'Backup job %s %s on %s%s%s',
                    $j['BackupJobId'], $j['State'], fmtTs($j['CreationDate'] ?? null),
                    !empty($j['StatusMessage']) ? ': ' . $j['StatusMessage'] : '',
                    $recovered ? ' (a later backup completed)' : ''
                ));
            }
            $okCount = count($completedJobs);
            $results[] = result('backup_jobs', $region, 'completed backup jobs', $okCount >= $config['MIN_COMPLETED_BACKUP_JOBS'],
                sprintf('%d completed backup jobs, minimum %d', $okCount, $config['MIN_COMPLETED_BACKUP_JOBS']));
            foreach ($completedJobs as $j) {
                $results[] = result('backup_jobs', $region, $j['ResourceArn'], true, sprintf(
                    'Backup job %s COMPLETED on %s, %s MB',
                    $j['BackupJobId'], fmtTs($j['CompletionDate'] ?? null),
                    number_format(($j['BackupSizeInBytes'] ?? 0) / 1048576, 1)
                ));
            }
        }

        // B. Freshness of the last backup per protected resource (RPO)
        if ($config['CHECK_BACKUP_FRESHNESS']) {
            $resources = paginate($backup, 'ListProtectedResources', [], 'Results');
            $resources = array_values(array_filter($resources, fn ($r) => inScope($r['ResourceArn'], $r['ResourceType'] ?? null, $config, $tagged)));
            $stale = 0;
            foreach ($resources as $r) {
                $last = toTs($r['LastBackupTime'] ?? null);
                $ageH = $last ? ($now - $last) / 3600 : INF;
                $ok = $ageH <= $config['MAX_BACKUP_AGE_HOURS'];
                $stale += $ok ? 0 : 1;
                $results[] = result('backup_freshness', $region, $r['ResourceArn'], $ok, $last
                    ? sprintf('Last backup %s (%.1f h ago, max %d h)', gmdate('Y-m-d H:i', $last), $ageH, $config['MAX_BACKUP_AGE_HOURS'])
                    : 'No backup recorded');
            }
            $results[] = result('backup_freshness', $region, 'protected resources', count($resources) > 0 && $stale === 0, count($resources) > 0
                ? sprintf('%d protected resources in scope, %d with a last backup older than %d h', count($resources), $stale, $config['MAX_BACKUP_AGE_HOURS'])
                : 'No protected resources in scope: nothing to verify');
            logInfo(sprintf('Protected resources: %d in scope, %d without a backup in the last %d h.',
                count($resources), $stale, $config['MAX_BACKUP_AGE_HOURS']));
        }

        // C/D. Restore tests and restore time
        if ($config['CHECK_RESTORE_TESTS'] || $config['CHECK_RESTORE_TIME']) {
            $since = $now - $config['RESTORE_LOOKBACK_DAYS'] * 86400;
            $restores = paginate($backup, 'ListRestoreJobs', ['ByCreatedAfter' => $since], 'RestoreJobs');
            $restores = array_values(array_filter($restores, fn ($r) => !($r['IsParent'] ?? false)
                && (!$config['ONLY_RESTORE_TESTING_PLANS'] || !empty($r['CreatedBy']['RestoreTestingPlanArn']))
                && inScope($r['SourceResourceArn'] ?? null, $r['ResourceType'] ?? null, $config, $tagged)));
            $completed = array_values(array_filter($restores, fn ($r) => $r['Status'] === 'COMPLETED'));
            logInfo(sprintf('Restore jobs (last %d days): %d in scope, %d completed.',
                $config['RESTORE_LOOKBACK_DAYS'], count($restores), count($completed)));

            $measured = 0;
            $overRto = 0;
            foreach ($restores as $r) {
                $name = $r['SourceResourceArn'] ?? $r['CreatedResourceArn'] ?? $r['RestoreJobId'];
                $when = fmtTs($r['CreationDate'] ?? null);
                if ($r['Status'] !== 'COMPLETED') {
                    if (in_array($r['Status'], ['FAILED', 'ABORTED'], true) && $config['CHECK_RESTORE_TESTS']) {
                        $results[] = result('restore_tests', $region, $name, false, sprintf('Restore job %s %s on %s%s',
                            $r['RestoreJobId'], $r['Status'], $when, !empty($r['StatusMessage']) ? ': ' . $r['StatusMessage'] : ''));
                    }
                    continue;
                }
                $start = toTs($r['CreationDate'] ?? null);
                $end = toTs($r['CompletionDate'] ?? null);
                $minutes = ($start && $end) ? ($end - $start) / 60 : null;
                $validation = $r['ValidationStatus'] ?? null;
                $validationOk = !in_array($validation, ['FAILED', 'TIMED_OUT'], true);
                if ($config['CHECK_RESTORE_TESTS']) {
                    $results[] = result('restore_tests', $region, $name, $validationOk, sprintf(
                        'Restore job %s COMPLETED on %s (%s)%s', $r['RestoreJobId'], $when, $r['ResourceType'] ?? '?',
                        $validation ? ", validation $validation" : ''));
                }
                if ($config['CHECK_RESTORE_TIME']) {
                    $withinRto = $minutes !== null && $minutes <= $config['MAX_RESTORE_MINUTES'];
                    $measured++;
                    $overRto += $withinRto ? 0 : 1;
                    $results[] = result('restore_time', $region, $name, $withinRto, $minutes !== null
                        ? sprintf('Restore job %s took %.0f min (RTO %d min)', $r['RestoreJobId'], $minutes, $config['MAX_RESTORE_MINUTES'])
                        : sprintf('Restore job %s: start or completion time unknown, RTO cannot be verified', $r['RestoreJobId']));
                }
            }

            if ($config['CHECK_RESTORE_TIME']) {
                $results[] = result('restore_time', $region, 'restores within RTO', $measured > 0 && $overRto === 0, $measured > 0
                    ? sprintf('%d completed restores measured, %d over the RTO of %d min or without timing', $measured, $overRto, $config['MAX_RESTORE_MINUTES'])
                    : 'No completed restore in the period to measure against the RTO');
            }

            if ($config['CHECK_RESTORE_TESTS']) {
                $results[] = result('restore_tests', $region, 'completed restore tests', count($completed) >= $config['MIN_RESTORE_TESTS'],
                    sprintf('%d completed restore jobs in %d days, minimum %d', count($completed), $config['RESTORE_LOOKBACK_DAYS'], $config['MIN_RESTORE_TESTS']));
                $types = array_unique(array_map(fn ($r) => $r['ResourceType'] ?? '', $completed));
                foreach ($config['REQUIRED_RESTORE_RESOURCE_TYPES'] as $type) {
                    $results[] = result('restore_tests', $region, "resource type $type", in_array($type, $types, true),
                        in_array($type, $types, true) ? "At least one $type restore completed" : "No completed $type restore in the period");
                }
            }
        }

        // E. Off-site copy of each backed-up resource
        if ($config['CHECK_OFFSITE_COPY']) {
            $since = $now - $config['BACKUP_LOOKBACK_DAYS'] * 86400;
            $copies = paginate($backup, 'ListCopyJobs', ['ByCreatedAfter' => $since, 'ByState' => 'COMPLETED'], 'CopyJobs');
            $offsite = [];
            foreach ($copies as $c) {
                $src = $c['SourceBackupVaultArn'] ?? '';
                $dst = $c['DestinationBackupVaultArn'] ?? '';
                if (arnPart($src, 3) !== arnPart($dst, 3) || arnPart($src, 4) !== arnPart($dst, 4)) {
                    $offsite[$c['ResourceArn']] = $dst;
                }
            }
            $resourcesBacked = array_unique(array_map(fn ($j) => $j['ResourceArn'], $completedJobs));
            foreach ($resourcesBacked as $arn) {
                $results[] = result('offsite_copy', $region, $arn, isset($offsite[$arn]), isset($offsite[$arn])
                    ? 'Copied to ' . $offsite[$arn]
                    : sprintf('No completed copy to another region/account in the last %d days', $config['BACKUP_LOOKBACK_DAYS']));
            }
            $copied = count(array_intersect($resourcesBacked, array_keys($offsite)));
            $results[] = result('offsite_copy', $region, 'resources copied off-site', count($resourcesBacked) > 0 && $copied === count($resourcesBacked), count($resourcesBacked) > 0
                ? sprintf('%d of %d backed-up resources copied to another region/account', $copied, count($resourcesBacked))
                : 'No backed-up resources in the period: nothing to verify');
            logInfo(sprintf('Off-site copies: %d of %d backed-up resources copied to another region/account.', $copied, count($resourcesBacked)));
        }
    }

    return $results;
}

// ─── 6. EVALUATE: turn raw results into Passed/Failed + human conclusion ───
function evaluate(array $results, array $config): array
{
    $failed = array_values(array_filter($results, fn ($r) => !$r['passed']));
    $passed = count($results) > 0 && count($failed) === 0;
    $checks = array_count_values(array_column($results, 'check'));

    $lines   = [];
    $lines[] = sprintf('Automated audit by %s v%s on %s UTC.', AUTOMATION_ID, AUTOMATION_VERSION, gmdate('Y-m-d H:i'));
    $lines[] = sprintf('Result: %s. %d checks, %d items checked, %d passed, %d failed.',
        $passed ? 'PASSED' : 'FAILED', count($checks), count($results), count($results) - count($failed), count($failed));
    if (count($results) === 0) {
        $lines[] = 'Nothing matched the configured scope, so the audit is FAILED. Review the variables.';
    }
    foreach ($checks as $check => $total) {
        $bad = count(array_filter($failed, fn ($f) => $f['check'] === $check));
        $lines[] = sprintf('  %-17s %s (%d/%d items ok)', $check, $bad === 0 ? 'OK' : 'FAILED', $total - $bad, $total);
    }
    foreach (array_slice($failed, 0, $config['MAX_LOG_ITEMS']) as $f) {
        $lines[] = sprintf('- [%s] %s%s: %s', $f['check'], $f['region'] !== '' ? $f['region'] . ' ' : '', $f['resource'], $f['detail']);
    }
    if (count($failed) > $config['MAX_LOG_ITEMS']) {
        $lines[] = sprintf('… and %d more (see evidence attachment).', count($failed) - $config['MAX_LOG_ITEMS']);
    }

    return ['passed' => $passed, 'conclusion' => implode("\n", $lines)];
}

// ─── 7. REPORT: write the outcome to eramba ────────────────────────────────
// Order matters: evidence first (if it fails, the audit stays untouched),
// then the audit result, then the comment linking the evidence.
function report(string $auditId, array $outcome, array $results, array $config): void
{
    $attachments = [];
    if ($config['ATTACH_EVIDENCE']) {
        // eramba accepts csv/txt attachments and checks the real content type (JSON is rejected)
        $date = gmdate('Y-m-d');
        $files = [
            sprintf('evidence-%s-%s.csv', AUTOMATION_ID, $date) => ['text/csv', evidenceCsv($results)],
            sprintf('config-%s-%s.txt', AUTOMATION_ID, $date)   => ['text/plain', configText($config)],
        ];
        foreach ($files as $name => [$mime, $content]) {
            $attachments[] = erambaCall(
                uploadAttachmentMacro($auditId, "data:$mime;base64," . base64_encode($content), $name),
                "upload $name"
            );
            logInfo("Evidence uploaded: $name");
        }
    }

    $data = [
        'start_date'                              => gmdate('Y-m-d'),
        'end_date'                                => gmdate('Y-m-d'),
        'security_service_audit_result_option_id' => $outcome['passed'] ? $config['RESULT_PASSED_ID'] : $config['RESULT_FAILED_ID'],
        'result_description'                      => $outcome['conclusion'],
    ];
    erambaCall(editObjectMacro($data, $auditId), 'edit audit');
    $GLOBALS['auditWritten'] = true;
    logInfo('Audit result and conclusion saved.');

    $comment = sprintf('[%s v%s] %s', AUTOMATION_ID, AUTOMATION_VERSION, $outcome['passed'] ? 'PASSED' : 'FAILED');
    erambaCall(addCommentMacro($auditId, $comment, $attachments), 'add comment');
    logInfo('Comment added' . ($attachments ? ' with the evidence attached.' : '.'));
}

/** Configuration used in this run, as plain "KEY = value" lines. */
function configText(array $config): string
{
    $lines = [sprintf('%s v%s, run at %s UTC', AUTOMATION_ID, AUTOMATION_VERSION, gmdate('Y-m-d H:i:s')), ''];
    foreach ($config as $key => $value) {
        $lines[] = sprintf('%s = %s', $key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    return implode("\n", $lines) . "\n";
}

/** One row per item checked: opens directly in Excel for the auditor. */
function evidenceCsv(array $results): string
{
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, ['check', 'region', 'resource', 'result', 'detail'], ',', '"', '');
    foreach ($results as $r) {
        fputcsv($fh, [$r['check'], $r['region'] ?? '', $r['resource'], $r['passed'] ? 'PASS' : 'FAIL', $r['detail']], ',', '"', '');
    }
    rewind($fh);
    return (string) stream_get_contents($fh);
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
try {
    echo sprintf("%s v%s — audit #%s\n", AUTOMATION_ID, AUTOMATION_VERSION, $auditId);

    logStep(1, 'Checking configuration');
    checkSecrets($secrets);
    if (!class_exists(Aws\Sdk::class)) {
        throw new RuntimeException('AWS SDK not loaded: add "aws/aws-sdk-php:^3.300" to the automation composer packages.');
    }
    logInfo('Secrets present, AWS SDK ' . Aws\Sdk::VERSION . ' loaded.');
    if ($config['AWS_ROLE_ARN'] === '') {
        logInfo('WARNING: AWS_ROLE_ARN is empty, using the access key directly. With the recommended setup (README §5) that key can only assume the role, so AWS will deny every call.');
    }
    logInfo('Regions: ' . implode(', ', $config['REGIONS']) . '. Checks: ' . implode(', ', array_keys(array_filter([
        'backup_jobs'      => $config['CHECK_BACKUP_JOBS'],
        'backup_freshness' => $config['CHECK_BACKUP_FRESHNESS'],
        'restore_tests'    => $config['CHECK_RESTORE_TESTS'],
        'restore_time'     => $config['CHECK_RESTORE_TIME'],
        'offsite_copy'     => $config['CHECK_OFFSITE_COPY'],
    ]))) . '.');

    logStep(2, 'Collecting data from AWS Backup');
    $results = collectResults($secrets, $config);

    logStep(3, 'Evaluating');
    $outcome = evaluate($results, $config);
    echo $outcome['conclusion'] . "\n";

    logStep(4, 'Writing result to eramba');
    report($auditId, $outcome, $results, $config);

    echo "\nDone.\n";
    exit(0);
} catch (Aws\Exception\AwsException $e) {
    echo abortMessage('AWS error');
    fwrite(STDERR, sprintf("ERROR: AWS %s %s: %s\n", $e->getAwsErrorCode() ?? '', $e->getCommand()->getName(), $e->getAwsErrorMessage() ?? $e->getMessage()));
    if ($e->getAwsErrorCode() === 'AccessDeniedException' || $e->getAwsErrorCode() === 'AccessDenied') {
        fwrite(STDERR, $config['AWS_ROLE_ARN'] === ''
            ? "HINT: AWS_ROLE_ARN is empty. Set it to the role from README §5.1.\n"
            : "HINT: compare the IAM permissions with README §5.2.\n");
    }
    exit(1);
} catch (Throwable $e) {
    echo abortMessage('technical error');
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
