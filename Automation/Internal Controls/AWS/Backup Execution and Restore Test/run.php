<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Backup Execution and Restore Test
 *  Technology: AWS Backup
 *  id: aws-backup-jobs-restore-tests        version: 0.3.2
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Internal%20Controls
 *
 *  Composer packages (paste in the automation's composer field):
 *    aws/aws-sdk-php:^3.398
 * ============================================================================
 *
 *  Output
 *    STDOUT  Step-by-step log of what the script is doing (visible in eramba
 *            Automation Logs). eramba keeps only the first 10 KB.
 *    STDERR  Only used for technical errors. Any STDERR output marks the run
 *            as failed in eramba.
 *
 *  Exit codes
 *    0  Check executed. Dry-run, or audit updated with Passed or Failed.
 *    1  Technical error (credentials, network, permissions, eramba API).
 *       Before result save: audit unchanged. After result save: comment may
 *       be missing. See the explicit abort message; do not blindly retry.
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

    // Evidence windows. Execute at least weekly; restore tests at least quarterly.
    'BACKUP_LOOKBACK_DAYS'  => 30,       // 7–30 days; overlapping backup history
    'RESTORE_LOOKBACK_DAYS' => 90,       // 1–90 days; rolling restore-test window
    'MIN_COMPLETED_BACKUP_JOBS' => 1,    // Minimum across the entire configured backup scope

    // Default coverage: all AWS Backup protected resources in scope. An explicit sample is optional.
    'REQUIRED_RESTORE_RESOURCE_ARNS' => [], // [] = discover all protected resources; optional explicit sample
    'ONLY_RESTORE_TESTING_PLANS' => false, // true excludes manual restores from sample evidence
    'MAX_RPO_HOURS'       => 24,        // Default reference RPO, hours; adapt to your recovery objective
    'MAX_RESTORE_MINUTES' => 240,       // Default reference RTO, minutes; adapt to your recovery objective

    // Output. Mandatory checks and evidence retention are not configurable.
    'DRY_RUN'          => false,        // Explicit testing option; true suppresses every eramba write
    'RESULT_PASSED_ID' => 2,            // Your audit result option ID for Passed
    'RESULT_FAILED_ID' => 1,            // Your audit result option ID for Failed
    'MAX_LOG_ITEMS'    => 20,           // Failure display limit; all records remain in the CSV

];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'aws-backup-jobs-restore-tests';
const AUTOMATION_VERSION = '0.3.2';

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

/** Reject configurations that cannot perform this control's full methodology. */
function validateConfig(array $config, string $auditId): void
{
    if (!ctype_digit($auditId) || (int) $auditId < 1) {
        throw new RuntimeException('A valid audit context is required.');
    }
    foreach (['MIN_COMPLETED_BACKUP_JOBS', 'MAX_LOG_ITEMS', 'RESULT_PASSED_ID', 'RESULT_FAILED_ID'] as $key) {
        if (!is_int($config[$key]) || $config[$key] < 1) {
            throw new RuntimeException("$key must be a positive integer.");
        }
    }
    foreach (['MAX_RPO_HOURS', 'MAX_RESTORE_MINUTES'] as $key) {
        if ((!is_int($config[$key]) && !is_float($config[$key])) || !is_finite((float) $config[$key]) || $config[$key] <= 0) {
            throw new RuntimeException("Configure $key with your positive numeric recovery objective.");
        }
    }
    if (!is_int($config['BACKUP_LOOKBACK_DAYS']) || !is_int($config['RESTORE_LOOKBACK_DAYS']) ||
        $config['BACKUP_LOOKBACK_DAYS'] < 7 || $config['BACKUP_LOOKBACK_DAYS'] > 30 ||
        $config['RESTORE_LOOKBACK_DAYS'] < 1 || $config['RESTORE_LOOKBACK_DAYS'] > 90) {
        throw new RuntimeException('Backup look-back must be 7–30 days and restore look-back 1–90 days (integers).');
    }
    foreach (['DRY_RUN', 'ONLY_RESTORE_TESTING_PLANS'] as $key) {
        if (!is_bool($config[$key])) {
            throw new RuntimeException("$key must be true or false.");
        }
    }
    foreach (['REGIONS', 'RESOURCE_TYPES', 'RESOURCE_TAGS', 'REQUIRED_RESTORE_RESOURCE_ARNS'] as $key) {
        if (!is_array($config[$key])) {
            throw new RuntimeException("$key must be an array.");
        }
    }
    if ($config['REGIONS'] === []) {
        throw new RuntimeException('Configure at least one AWS region in REGIONS.');
    }
    foreach ($config['REGIONS'] as $region) {
        if (!is_string($region) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+-\d+$/', $region)) {
            throw new RuntimeException('REGIONS must contain AWS region names.');
        }
    }
    foreach (array_merge([$config['RESOURCE_ARN_REGEX']], array_values($config['RESOURCE_TAGS'])) as $regex) {
        if (@preg_match($regex, '') === false) {
            throw new RuntimeException('Invalid scope regular expression.');
        }
    }
    foreach ($config['REQUIRED_RESTORE_RESOURCE_ARNS'] as $arn) {
        if (!is_string($arn) || !preg_match('/^arn:[^:]+:[^:]+:[^:]+:[0-9]{12}:.+$/', $arn) || !in_array(arnPart($arn, 3), $config['REGIONS'], true) || preg_match($config['RESOURCE_ARN_REGEX'], $arn) !== 1) {
            throw new RuntimeException('Each sample ARN must be valid and belong to the configured regions and ARN scope.');
        }
    }
    if ($config['RESULT_PASSED_ID'] === $config['RESULT_FAILED_ID']) {
        throw new RuntimeException('Passed and Failed result option IDs must differ.');
    }
}

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

/** Runs an SDK paginator and returns all items of $key, within the run's time and item limits. */
function paginate(Aws\AwsClient $client, string $operation, array $params, string $key): array
{
    $items = [];
    foreach ($client->getPaginator($operation, $params) as $page) {
        if (microtime(true) - $GLOBALS['runStarted'] > 190) {
            throw new RuntimeException('Collection time budget exceeded; narrow the scope or split controls by region.');
        }
        foreach ($page[$key] ?? [] as $item) {
            $items[] = $item;
            if (count($items) > 5000) {
                throw new RuntimeException("Collection item limit exceeded: $operation; split the control scope.");
            }
        }
    }
    return $items;
}

function toTs(mixed $date): ?int
{
    if ($date instanceof DateTimeInterface) {
        return $date->getTimestamp();
    }
    if (is_int($date) || is_float($date)) {
        return $date > 0 ? (int) $date : null;
    }
    if (!is_string($date) || $date === '') {
        return null;
    }
    $ts = strtotime($date);
    return $ts !== false && $ts > 0 ? $ts : null;
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
    $autoSample = $config['REQUIRED_RESTORE_RESOURCE_ARNS'] === [];
    $sample = array_fill_keys($config['REQUIRED_RESTORE_RESOURCE_ARNS'], true);
    logInfo($autoSample ? 'Restore coverage: all discovered protected resources in scope.' : 'Restore coverage: explicitly configured source ARNs.');
    $restoredSample = [];
    $totalCompleted = 0;
    $restoreCount = 0;
    $rtoFailures = 0;
    $rpoFailures = 0;
    $restoreFailures = 0;

    foreach (array_unique($config['REGIONS']) as $region) {
        logInfo("── Region $region");
        $backup = new Aws\Backup\BackupClient(awsClientOptions($region, $credentials));
        $tagged = taggedArns($credentials, $region, $config);
        if ($autoSample) {
            $resources = paginate($backup, 'ListProtectedResources', [], 'Results');
            foreach ($resources as $resource) {
                $arn = $resource['ResourceArn'] ?? '';
                if ($arn === '') {
                    $results[] = result('restore_sample', $region, 'unidentified protected resource', false,
                        'Protected resource has no ARN; restore coverage cannot be demonstrated for this resource');
                    continue;
                }
                if (inScope($arn, $resource['ResourceType'] ?? null, $config, $tagged)) {
                    $sample[$arn] = true;
                }
            }
        }


        // Full backup report for the selected scope, irrespective of the restore sample.
        $jobs = paginate($backup, 'ListBackupJobs', ['ByCreatedAfter' => $now - $config['BACKUP_LOOKBACK_DAYS'] * 86400], 'BackupJobs');
        $jobs = array_values(array_filter($jobs, fn ($j) => !($j['IsParent'] ?? false)
            && inScope($j['ResourceArn'] ?? null, $j['ResourceType'] ?? null, $config, $tagged)));
        $completed = count(array_filter($jobs, fn ($j) => ($j['State'] ?? '') === 'COMPLETED'));
        $totalCompleted += $completed;
        foreach ($jobs as $j) {
            $state = $j['State'] ?? 'UNKNOWN';
            $known = in_array($state, ['CREATED', 'PENDING', 'RUNNING', 'ABORTING', 'COMPLETED', 'FAILED', 'ABORTED', 'EXPIRED', 'PARTIAL'], true);
            $ok = $known && !in_array($state, ['FAILED', 'ABORTED', 'EXPIRED', 'PARTIAL'], true);
            $size = isset($j['BackupSizeInBytes']) ? number_format($j['BackupSizeInBytes'] / 1048576, 1) . ' MB' : 'size unavailable';
            $detail = sprintf('Backup job %s %s, created %s, completed %s, %s%s',
                $j['BackupJobId'] ?? 'unknown', $state, fmtTs($j['CreationDate'] ?? null),
                fmtTs($j['CompletionDate'] ?? null), $size,
                !empty($j['StatusMessage']) ? ': ' . $j['StatusMessage'] : '');
            if (!$known) {
                $detail .= ' (unrecognised state; cannot demonstrate success)';
            } elseif ($ok && $state !== 'COMPLETED') {
                $detail .= ' (in progress; not counted as a completed backup)';
            }
            $results[] = result('backup_jobs', $region, $j['ResourceArn'] ?? 'unknown resource', $ok, $detail);
        }
        logInfo(sprintf('Backup jobs: %d in scope, %d completed.', count($jobs), $completed));

        // Restore obligations follow source ARNs in the approved sample, across all regions.
        // Missing identity is retained as a failed evidence item, never an execution error.
        $restores = paginate($backup, 'ListRestoreJobs', ['ByCreatedAfter' => $now - $config['RESTORE_LOOKBACK_DAYS'] * 86400], 'RestoreJobs');
        $regionSample = array_filter(array_keys($sample), fn ($arn) => arnPart($arn, 3) === $region);
        foreach ($restores as $r) {
            if ($r['IsParent'] ?? false) {
                continue;
            }
            if ($config['ONLY_RESTORE_TESTING_PLANS'] && empty($r['CreatedBy']['RestoreTestingPlanArn'])) {
                continue;
            }
            $source = $r['SourceResourceArn'] ?? '';
            $jobId = $r['RestoreJobId'] ?? 'unknown';
            if ($source === '') {
                $results[] = result('restore_tests', $region, $jobId, false,
                    "Restore job $jobId has no SourceResourceArn; cannot identify the restored data or credit the sample");
                $restoreFailures++;
                continue;
            }
            if (!isset($sample[$source]) || !inScope($source, $r['ResourceType'] ?? null, $config, $tagged)) {
                continue;
            }
            $state = $r['Status'] ?? 'UNKNOWN';
            $start = toTs($r['CreationDate'] ?? null);
            $end = toTs($r['CompletionDate'] ?? null);
            if ($state !== 'COMPLETED') {
                $knownPending = in_array($state, ['PENDING', 'RUNNING'], true);
                $results[] = result('restore_tests', $region, $source, $knownPending, sprintf(
                    'Restore job %s %s, created %s%s; not counted as a completed test',
                    $jobId, $state, fmtTs($r['CreationDate'] ?? null),
                    !empty($r['StatusMessage']) ? ': ' . $r['StatusMessage'] : ''));
                $restoreFailures += $knownPending ? 0 : 1;
                continue;
            }
            $restoreCount++;
            $validation = $r['ValidationStatus'] ?? null;
            $validationOk = $validation === null || $validation === 'SUCCESSFUL';
            $results[] = result('restore_tests', $region, $source, $validationOk,
                sprintf('Restore job %s COMPLETED, created %s, completed %s, validation %s',
                    $jobId, fmtTs($r['CreationDate'] ?? null), fmtTs($r['CompletionDate'] ?? null), $validation ?? 'not reported'));
            $restoreFailures += $validationOk ? 0 : 1;

            $datesOk = $start !== null && $end !== null && $end >= $start && $end <= $now;
            $minutes = $datesOk ? ($end - $start) / 60 : null;
            $withinRto = $minutes !== null && $minutes <= $config['MAX_RESTORE_MINUTES'];
            $rtoFailures += $withinRto ? 0 : 1;
            $results[] = result('restore_time', $region, $source, $withinRto, $minutes !== null
                ? sprintf('Restore job %s took %.2f min (RTO %.2f min)', $jobId, $minutes, $config['MAX_RESTORE_MINUTES'])
                : "Restore job $jobId has missing, reversed or future timestamps; RTO cannot be verified");

            // This test's reference instant is restore job creation, not the audit execution time.
            $point = toTs($r['RecoveryPointCreationDate'] ?? null);
            $ageHours = $start !== null && $start <= $now && $point !== null && $point <= $start
                ? ($start - $point) / 3600 : null;
            $withinRpo = $ageHours !== null && $ageHours <= $config['MAX_RPO_HOURS'];
            $rpoFailures += $withinRpo ? 0 : 1;
            $results[] = result('restore_rpo', $region, $source, $withinRpo, $ageHours !== null
                ? sprintf('Restore job %s: recovery point %s, point created %s, restore created %s; age %.2f h (RPO %.2f h)',
                    $jobId, $r['RecoveryPointArn'] ?? 'not reported', fmtTs($r['RecoveryPointCreationDate']),
                    fmtTs($r['CreationDate']), $ageHours, $config['MAX_RPO_HOURS'])
                : "Restore job $jobId has missing or inconsistent recovery-point/restore creation dates; RPO cannot be verified");
            if ($validationOk && $datesOk && $withinRto && $withinRpo) {
                $restoredSample[$source] = true;
            }
        }
        logInfo(sprintf('Restore sample: %d required source resources in this region%s.', count($regionSample),
            $regionSample === [] ? '; no minimum restore count required here' : ''));
    }

    $results[] = result('backup_jobs', '', 'completed backup jobs', $totalCompleted >= $config['MIN_COMPLETED_BACKUP_JOBS'],
        sprintf('%d completed backups across the configured scope, minimum %d', $totalCompleted, $config['MIN_COMPLETED_BACKUP_JOBS']));
    $results[] = result('restore_scope', '', $autoSample ? 'automatic population' : 'explicit sample', $sample !== [],
        sprintf('%s: %d resources required; see restore_sample evidence rows for each ARN',
            $autoSample ? 'All discovered protected resources matching the scope' : 'Configured representative sample', count($sample)));
    foreach (array_keys($sample) as $arn) {
        $results[] = result('restore_sample', arnPart($arn, 3), $arn, isset($restoredSample[$arn]),
            isset($restoredSample[$arn]) ? 'Required resource has a completed restore meeting RTO/RPO in the review window'
                : 'No qualifying restore for this required resource: check source identity, scope, dates, validation and recovery objectives');
    }
    $results[] = result('restore_sample', '', 'restore population coverage', $sample !== [] && count($restoredSample) === count($sample),
        sprintf('%d of %d required resources have a qualifying restore', count($restoredSample), count($sample)));
    $results[] = result('restore_tests', '', 'restore test results', $restoreCount > 0 && $restoreFailures === 0,
        sprintf('%d completed sample restores; %d failed, unknown or unidentifiable restore records', $restoreCount, $restoreFailures));
    $results[] = result('restore_time', '', 'restore RTO', $restoreCount > 0 && $rtoFailures === 0,
        sprintf('%d completed sample restores; %d without demonstrated RTO compliance', $restoreCount, $rtoFailures));
    $results[] = result('restore_rpo', '', 'restore RPO', $restoreCount > 0 && $rpoFailures === 0,
        sprintf('%d completed sample restores; %d without demonstrated RPO compliance', $restoreCount, $rpoFailures));
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
        $lines[] = mb_substr(sprintf('- [%s] %s%s: %s', $f['check'], $f['region'] !== '' ? $f['region'] . ' ' : '', $f['resource'], preg_replace('/[\r\n]+/', ' ', $f['detail'])), 0, 400);
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
    if (strlen(evidenceCsv($results)) > 600000) {
        throw new RuntimeException('Evidence size limit exceeded; split the control scope.');
    }
    if ($config['DRY_RUN']) {
        logInfo('DRY RUN: no uploads, audit edits or comments were made.');
        return;
    }
    $attachments = [];
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
    $config['AWS_EXTERNAL_ID'] = $config['AWS_EXTERNAL_ID'] === '' ? '' : '(set)'; // Not copied into attachments
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
        fputcsv($fh, array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v) ? "'".$v : $v, [$r['check'], $r['region'] ?? '', $r['resource'], $r['passed'] ? 'PASS' : 'FAIL', $r['detail']]), ',', '"', '');
    }
    rewind($fh);
    return (string) stream_get_contents($fh);
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
$GLOBALS['runStarted'] = microtime(true);
try {
    echo sprintf("%s v%s — audit #%s\n", AUTOMATION_ID, AUTOMATION_VERSION, $auditId);

    logInfo($config['DRY_RUN'] ? 'DRY RUN — SIMULATION ONLY. This audit will NOT be completed.' : 'LIVE RUN — results and evidence will be saved to the selected audit.');
    logStep(1, 'Checking configuration');
    validateConfig($config, $auditId);
    checkSecrets($secrets);
    if (!class_exists(Aws\Sdk::class)) {
        throw new RuntimeException('AWS SDK not loaded: add "aws/aws-sdk-php:^3.398" to the automation composer packages.');
    }
    logInfo('Secrets present, AWS SDK ' . Aws\Sdk::VERSION . ' loaded.');
    if ($config['AWS_ROLE_ARN'] === '') {
        logInfo('WARNING: AWS_ROLE_ARN is empty, using the access key directly. With the recommended setup (README §5) that key can only assume the role, so AWS will deny every call.');
    }
    logInfo('Regions: ' . implode(', ', $config['REGIONS']) . '. Checks: backup_jobs, restore_scope, restore_tests, restore_sample, restore_time, restore_rpo.');

    logStep(2, 'Collecting data from AWS Backup');
    $results = collectResults($secrets, $config);

    logStep(3, 'Evaluating');
    $outcome = evaluate($results, $config);
    echo $outcome['conclusion'] . "\n";

    logStep(4, 'Writing result to eramba');
    report($auditId, $outcome, $results, $config);

    echo $config['DRY_RUN'] ? "\nSIMULATION COMPLETE — no audit result saved. Set DRY_RUN=false for scheduled execution.\n" : "\nDone.\n";
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
