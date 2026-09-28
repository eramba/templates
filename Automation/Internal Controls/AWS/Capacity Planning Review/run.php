<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Capacity Planning Review
 *  Technology: AWS EC2 Auto Scaling and Amazon CloudWatch
 *  id: aws-capacity-planning        version: 0.1.1
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
 *       be missing. Inspect the audit before retrying.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'AWS_ACCESS_KEY_ID' => '%SECRET_aws_access_key_id%',
    'AWS_SECRET_ACCESS_KEY' => '%SECRET_aws_secret_access_key%',
];

// ─── 2. VARIABLES (README §7) ────────────────────────────────────────────
$config = [
    'AWS_ROLE_ARN' => '', // Optional read-only role to assume
    'AWS_EXTERNAL_ID' => '', // Optional external ID for the role
    'REGIONS' => ['eu-west-1'], // AWS regions in this control's scope
    'GROUP_NAME_REGEX' => '/.*/', // All discovered groups by default
    'LOOKBACK_DAYS' => 28, // 7–28 days; keep the schedule within this window
    'MIN_METRIC_COVERAGE_PERCENT' => 90, // Reference minimum hourly CPU coverage
    'MAX_GROUPS' => 20, // Abort instead of returning a truncated population
    'DRY_RUN' => false, // True collects and evaluates without eramba writes
    'RESULT_PASSED_ID' => 2, // eramba Passed option
    'RESULT_FAILED_ID' => 1, // eramba Failed option
    'MAX_LOG_ITEMS' => 5, // Failure examples in logs; all items remain in evidence
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'aws-capacity-planning';
const AUTOMATION_VERSION = '0.1.1';

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

// ─── 5. COLLECT ──────────────────────────────────────────────────────────

/** All provider calls are read-only. Refuse incomplete collection before any write. */
function awsCall(object $client, string $operation, array $args = []): array
{
    if (microtime(true) - $GLOBALS['runStarted'] > 190) {
        throw new RuntimeException('Collection time budget exceeded; narrow the scope or split controls by region.');
    }
    try {
        return $client->$operation($args)->toArray();
    } catch (\Aws\Exception\AwsException $e) {
        // Do not print the request, headers or response body (may contain credentials).
        throw new RuntimeException($operation . ': AWS ' . ($e->getAwsErrorCode() ?: 'request failed'));
    }
}

function pages(object $client, string $operation, string $key, array $args = []): array
{
    $items = []; $seen = [];
    do {
        $page = awsCall($client, $operation, $args);
        foreach ($page[$key] ?? [] as $item) {
            $items[] = $item;
            if (count($items) > 5000) throw new RuntimeException('Collection item limit exceeded: ' . $operation);
        }
        $token = $page['NextToken'] ?? '';
        if ($token !== '' && isset($seen[$token])) throw new RuntimeException('Repeated pagination token: ' . $operation);
        $seen[$token] = true;
        $args['NextToken'] = $token;
    } while ($token !== '');
    return $items;
}

function stamp(mixed $value): ?int
{
    if ($value instanceof \DateTimeInterface) return $value->getTimestamp();
    if (is_string($value) && $value !== '') {
        $t = strtotime($value); return $t === false ? null : $t;
    }
    return null;
}

function validateConfig(array $c, string $auditId): void
{
    if (!ctype_digit($auditId) || (int)$auditId < 1) throw new RuntimeException('Invalid audit context.');
    if (!is_array($c['REGIONS']) || !$c['REGIONS'] || count($c['REGIONS']) > 20 || count($c['REGIONS']) !== count(array_unique($c['REGIONS']))) {
        throw new RuntimeException('REGIONS must be a non-empty list of at most 20 unique AWS regions.');
    }
    foreach ($c['REGIONS'] as $r) if (!is_string($r) || !preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D', $r)) throw new RuntimeException('Invalid AWS region.');
    if (!is_string($c['GROUP_NAME_REGEX']) || @preg_match($c['GROUP_NAME_REGEX'], '') === false) throw new RuntimeException('Invalid GROUP_NAME_REGEX.');
    foreach (['LOOKBACK_DAYS'=>[7,28], 'MAX_GROUPS'=>[1,100], 'MAX_LOG_ITEMS'=>[1,10], 'RESULT_PASSED_ID'=>[1,999999], 'RESULT_FAILED_ID'=>[1,999999]] as $key=>$range) {
        if (!is_int($c[$key]) || $c[$key] < $range[0] || $c[$key] > $range[1]) throw new RuntimeException('Invalid ' . $key . '.');
    }
    if (!is_bool($c['DRY_RUN']) || $c['RESULT_PASSED_ID'] === $c['RESULT_FAILED_ID']) throw new RuntimeException('Invalid output settings.');
    if (!is_numeric($c['MIN_METRIC_COVERAGE_PERCENT']) || $c['MIN_METRIC_COVERAGE_PERCENT'] <= 0 || $c['MIN_METRIC_COVERAGE_PERCENT'] > 100) throw new RuntimeException('Invalid MIN_METRIC_COVERAGE_PERCENT.');
    if (!is_string($c['AWS_ROLE_ARN']) || !is_string($c['AWS_EXTERNAL_ID']) || ($c['AWS_EXTERNAL_ID'] !== '' && $c['AWS_ROLE_ARN'] === '')) throw new RuntimeException('Invalid role settings.');
    if ($c['AWS_ROLE_ARN'] !== '' && !preg_match('/^arn:aws(?:-us-gov|-cn)?:iam::\d{12}:role\/.+$/D', $c['AWS_ROLE_ARN'])) throw new RuntimeException('Invalid AWS_ROLE_ARN.');
}

/** Only unambiguous group CPU alarms can establish CPU threshold coverage. */
function cpuAlarm(array $alarm, string $name): bool
{
    return ($alarm['Namespace'] ?? '') === 'AWS/EC2'
        && ($alarm['MetricName'] ?? '') === 'CPUUtilization'
        && count($alarm['Dimensions'] ?? []) === 1
        && ($alarm['Dimensions'][0]['Name'] ?? '') === 'AutoScalingGroupName'
        && ($alarm['Dimensions'][0]['Value'] ?? '') === $name;
}

function validThreshold(array $a): bool
{
    return !empty($a['AlarmName']) && is_numeric($a['Threshold'] ?? null) && $a['Threshold'] > 0 && $a['Threshold'] <= 100
        && in_array($a['ComparisonOperator'] ?? '', ['GreaterThanThreshold','GreaterThanOrEqualToThreshold'], true)
        && in_array($a['Statistic'] ?? '', ['Average','Maximum'], true)
        && ($a['Period'] ?? 0) > 0 && ($a['EvaluationPeriods'] ?? 0) > 0
        && !empty($a['ActionsEnabled']) && !empty($a['AlarmActions'])
        && in_array($a['StateValue'] ?? '', ['OK','ALARM'], true);
}

function collectResults(array $secrets, array $config): array
{
    $results = []; $total = 0;
    // The last complete UTC hour avoids treating an unfinished bucket as missing.
    $end = intdiv(time(), 3600) * 3600;
    $start = $end - $config['LOOKBACK_DAYS'] * 86400;
    $GLOBALS['evidenceWindow'] = gmdate('c', $start) . ' to ' . gmdate('c', $end) . ' (end exclusive)';
    $options = ['version'=>'latest', 'region'=>$config['REGIONS'][0],
        'credentials'=>['key'=>$secrets['AWS_ACCESS_KEY_ID'], 'secret'=>$secrets['AWS_SECRET_ACCESS_KEY']],
        'http'=>['connect_timeout'=>5, 'timeout'=>15], 'retries'=>1];
    if ($config['AWS_ROLE_ARN'] !== '') {
        $args = ['RoleArn'=>$config['AWS_ROLE_ARN'], 'RoleSessionName'=>'eramba-capacity-audit', 'DurationSeconds'=>900];
        if ($config['AWS_EXTERNAL_ID'] !== '') $args['ExternalId'] = $config['AWS_EXTERNAL_ID'];
        $role = awsCall(new \Aws\Sts\StsClient($options), 'assumeRole', $args);
        $creds = $role['Credentials'] ?? [];
        if (empty($creds['AccessKeyId']) || empty($creds['SecretAccessKey']) || empty($creds['SessionToken'])) throw new RuntimeException('AssumeRole returned incomplete credentials.');
        $options['credentials'] = ['key'=>$creds['AccessKeyId'], 'secret'=>$creds['SecretAccessKey'], 'token'=>$creds['SessionToken']];
    }
    foreach ($config['REGIONS'] as $region) {
        $options['region'] = $region;
        $as = new \Aws\AutoScaling\AutoScalingClient($options);
        $cw = new \Aws\CloudWatch\CloudWatchClient($options);
        $groups = pages($as, 'describeAutoScalingGroups', 'AutoScalingGroups', ['MaxRecords'=>100]);
        $groups = array_values(array_filter($groups, fn($g) => empty($g['AutoScalingGroupName']) || preg_match($config['GROUP_NAME_REGEX'], $g['AutoScalingGroupName']) === 1));
        logInfo($region . ': ' . count($groups) . ' groups in scope.');
        if (!$groups) continue;
        $total += count($groups);
        if ($total > $config['MAX_GROUPS']) throw new RuntimeException('MAX_GROUPS exceeded; split the control scope.');
        $allAlarms = pages($cw, 'describeAlarms', 'MetricAlarms', ['AlarmTypes'=>['MetricAlarm'], 'MaxRecords'=>100]);
        foreach ($groups as $g) {
            $name = $g['AutoScalingGroupName'] ?? '';
            if ($name === '') {
                $results[] = result('population', $region, '(unidentified group)', false, 'Group identity is missing; it cannot be tested.');
                continue;
            }
            $alarms = array_values(array_filter($allAlarms, fn($a)=>cpuAlarm($a, $name)));
            $valid = array_values(array_filter($alarms, 'validThreshold'));
            $results[] = result('thresholds', $region, $name, count($valid)>0,
                'CPU high-threshold alarms with actions enabled and usable state: ' . count($valid) . '. Metric-math/composite alarms cannot establish coverage in this version.');
            foreach ($alarms as $a) {
                $results[] = result('alarm_configuration', $region, $name, true, json_encode($a, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                if (empty($a['AlarmName'])) {
                    $results[] = result('alerts', $region, $name, false, 'Alarm identity is missing; history cannot be collected for this record.');
                    continue;
                }
                // History is evidence, not a requirement that an alarm must have fired.
                $history = pages($cw, 'describeAlarmHistory', 'AlarmHistoryItems', ['AlarmName'=>$a['AlarmName'], 'StartDate'=>gmdate('c',$start), 'EndDate'=>gmdate('c',$end), 'MaxRecords'=>100]);
                $results[] = result('alerts', $region, $name, true, $a['AlarmName'] . ': ' . count($history) . ' history records in the review window; zero records is valid.');
                foreach ($history as $h) {
                    $t = stamp($h['Timestamp'] ?? null);
                    $results[] = result('alerts', $region, $name, $t !== null && $t >= $start && $t < $end,
                        json_encode($h, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                }
            }
            if (!$alarms) $results[] = result('alerts', $region, $name, false, 'No group CPU alarm exists; an empty alert log does not demonstrate alert coverage.');
            $metrics = awsCall($cw, 'getMetricStatistics', ['Namespace'=>'AWS/EC2', 'MetricName'=>'CPUUtilization',
                'Dimensions'=>[['Name'=>'AutoScalingGroupName','Value'=>$name]],
                'StartTime'=>gmdate('c',$start), 'EndTime'=>gmdate('c',$end), 'Period'=>3600, 'Statistics'=>['Average','Maximum'], 'Unit'=>'Percent']);
            $buckets = []; $malformed = 0;
            foreach ($metrics['Datapoints'] ?? [] as $p) {
                $time = stamp($p['Timestamp'] ?? null);
                if ($time === null || $time < $start || $time >= $end || $time % 3600 !== 0
                    || !is_numeric($p['Average'] ?? null) || !is_numeric($p['Maximum'] ?? null)
                    || $p['Average'] < 0 || $p['Maximum'] > 100 || $p['Maximum'] < $p['Average'] || isset($buckets[$time])) {
                    $malformed++; continue;
                }
                $buckets[$time] = $p;
            }
            ksort($buckets);
            $coverage = count($buckets) / ($config['LOOKBACK_DAYS'] * 24) * 100;
            $latest = $buckets ? array_key_last($buckets) : 0;
            $ok = $coverage >= $config['MIN_METRIC_COVERAGE_PERCENT'] && $latest >= $end - 7200 && $malformed === 0;
            $results[] = result('metrics', $region, $name, $ok, sprintf('CPU hourly coverage %.2f%%; minimum %.2f%%; malformed/duplicate records %d; latest %s UTC. Window %s.', $coverage, $config['MIN_METRIC_COVERAGE_PERCENT'], $malformed, $latest ? gmdate('c',$latest) : 'unavailable', $GLOBALS['evidenceWindow']));
            $days = []; $pressure = 0;
            $threshold = $valid ? min(array_column($valid,'Threshold')) : null;
            foreach ($buckets as $time=>$p) {
                $day = gmdate('Y-m-d',$time); $days[$day][] = $p;
                if ($threshold !== null && $p['Maximum'] >= $threshold) $pressure++;
            }
            foreach ($days as $day=>$points) {
                $results[] = result('utilisation_evidence', $region, $name, true, sprintf('%s UTC: %d hourly buckets; mean of hourly CPU averages %.2f%%; peak instance CPU %.2f%%.', $day, count($points), array_sum(array_column($points,'Average'))/count($points), max(array_column($points,'Maximum'))));
            }
            $policies = pages($as, 'describePolicies', 'ScalingPolicies', ['AutoScalingGroupName'=>$name, 'MaxRecords'=>100]);
            $plan = ['MinSize'=>$g['MinSize'] ?? null,'MaxSize'=>$g['MaxSize'] ?? null,'DesiredCapacity'=>$g['DesiredCapacity'] ?? null,
                'DesiredCapacityType'=>$g['DesiredCapacityType'] ?? 'units','SuspendedProcesses'=>$g['SuspendedProcesses'] ?? [],'ScalingPolicies'=>$policies];
            $results[] = result('capacity_plan', $region, $name, count($policies)>0, 'Executable AWS capacity plan: ' . json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $forecasts = array_values(array_filter($policies, fn($p)=>($p['PolicyType'] ?? '') === 'PredictiveScaling' && ($p['Enabled'] ?? true)));
            if (!$forecasts) $results[] = result('projections', $region, $name, false, 'No enabled predictive scaling policy; capacity projection evidence is missing. ForecastOnly is sufficient.');
            foreach ($forecasts as $policy) {
                if (empty($policy['PolicyName'])) {
                    $results[] = result('projections', $region, $name, false, 'Predictive policy identity is missing; its forecast cannot be collected.');
                    continue;
                }
                $forecast = awsCall($as, 'getPredictiveScalingForecast', ['AutoScalingGroupName'=>$name, 'PolicyName'=>$policy['PolicyName'], 'StartTime'=>gmdate('c',$end), 'EndTime'=>gmdate('c',$end+86400)]);
                $created = stamp($forecast['UpdateTime'] ?? null);
                $values = $forecast['CapacityForecast']['Values'] ?? [];
                $times = $forecast['CapacityForecast']['Timestamps'] ?? [];
                $series = []; $bad = count($values) !== count($times);
                foreach ($times as $i=>$time) {
                    $ts = stamp($time); $value = $values[$i] ?? null;
                    if ($ts === null || $ts < $end || $ts >= $end+86400 || $ts%3600 !== 0 || !is_numeric($value) || $value < 0 || isset($series[$ts])) { $bad=true; continue; }
                    $series[$ts] = $value;
                }
                $fresh = $created !== null && $created <= time() && $created >= $end-86400;
                $forecastOk = !$bad && count($series) === 24 && $fresh;
                $results[] = result('projections', $region, $name, $forecastOk,
                    $policy['PolicyName'] . ': next 24 hours capacity forecast; update ' . ($created ? gmdate('c',$created) : 'unavailable') . '; ' . json_encode($forecast, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                if ($forecastOk) {
                    $max = $g['MaxSize'] ?? null;
                    $results[] = result('bottlenecks', $region, $name, is_numeric($max),
                        sprintf('Forecast peak %.2f capacity units; configured maximum %s; %s. CPU pressure in %d observed hours. Detection is evidence; this methodology does not require remediation.', max($series), $max ?? 'unavailable', is_numeric($max) && max($series) > $max ? 'CAPACITY BOTTLENECK IDENTIFIED' : 'no projected maximum-capacity bottleneck', $pressure)) + ['finding'=>$pressure > 0 || (is_numeric($max) && max($series) > $max)];
                }
            }
            if (!$forecasts) $results[] = result('bottlenecks', $region, $name, false, 'Cannot assess projected bottlenecks without a capacity forecast.');
            // A report too large for the runner must not silently lose evidence.
            if (strlen(json_encode($results, JSON_THROW_ON_ERROR)) > 600000) throw new RuntimeException('Evidence size limit exceeded; split the control scope.');
        }
    }
    $results[] = result('population', '', 'All discovered groups matching scope', $total > 0, $total . ' groups in configured regions; groups are discovered independently of metrics, alarms and forecasts.');
    $required = ['thresholds','alerts','metrics','capacity_plan','projections','bottlenecks'];
    foreach ($required as $check) {
        $items = array_values(array_filter($results, fn($r)=>$r['check']===$check));
        $bad = count(array_filter($items, fn($r)=>!$r['passed']));
        $results[] = result($check, '', 'Scope summary', count($items)>0 && $bad===0, count($items) . ' evidence items; ' . $bad . ' failed.');
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
    $lines[] = 'Scope: EC2 Auto Scaling group CPU/compute capacity. Window: ' . ($GLOBALS['evidenceWindow'] ?? 'unavailable');
    $lines[] = sprintf('Result: %s. %d checks, %d items checked, %d passed, %d failed.',
        $passed ? 'PASSED' : 'FAILED', count($checks), count($results), count($results) - count($failed), count($failed));
    if (count($results) === 0) {
        $lines[] = 'Nothing matched the configured scope, so the audit is FAILED. Review the variables.';
    }
    foreach ($checks as $check => $total) {
        $bad = count(array_filter($failed, fn ($f) => $f['check'] === $check));
        $lines[] = sprintf('  %-17s %s (%d/%d items ok)', $check, $bad === 0 ? 'OK' : 'FAILED', $total - $bad, $total);
    }
    $findings = array_values(array_filter($results, fn($r)=>!empty($r['finding'])));
    if ($findings) {
        $lines[] = count($findings) . ' capacity findings identified (detection is not a remediation test):';
        foreach (array_slice($findings, 0, min(3, $config['MAX_LOG_ITEMS'])) as $f) {
            $lines[] = '- ' . substr($f['region'] . ' ' . $f['resource'] . ': ' . $f['detail'], 0, 400);
        }
    }
    foreach (array_slice($failed, 0, $config['MAX_LOG_ITEMS']) as $f) {
        $lines[] = substr(sprintf('- [%s] %s%s: %s', $f['check'], $f['region'] !== '' ? $f['region'] . ' ' : '', $f['resource'], preg_replace('/[\r\n]+/', ' ', $f['detail'])), 0, 400);
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
$GLOBALS['runStarted'] = microtime(true);
try {
    echo sprintf("%s v%s — audit #%s\n", AUTOMATION_ID, AUTOMATION_VERSION, $auditId);

    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no audit result will be saved.' : 'LIVE RUN — audit result and evidence will be saved.');
    logStep(1, 'Checking configuration');
    validateConfig($config, $auditId);
    checkSecrets($secrets);
    logInfo('Secrets present.');

    logStep(2, 'Collecting AWS Auto Scaling and CloudWatch evidence');
    $results = collectResults($secrets, $config);

    logStep(3, 'Evaluating');
    $outcome = evaluate($results, $config);
    echo $outcome['conclusion'] . "\n";

    logStep(4, 'Writing result to eramba');
    report($auditId, $outcome, $results, $config);

    echo $config['DRY_RUN'] ? "\nSIMULATION COMPLETE — no audit result saved.\n" : "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo abortMessage('technical error');
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
