<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Remote Access Security Review
 *  Technology: AWS Client VPN and Directory Service
 *  id: aws-client-vpn-remote-access        version: 0.1.0
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
    'REGIONS' => ['eu-west-1'], // Regions included in this control
    'ENDPOINT_REGEX' => '/.*/', // All endpoints by default
    'LOOKBACK_DAYS' => 90, // Review window; schedule monthly
    'SPLIT_TUNNEL_APPROVALS' => [], // Optional endpoint ID => existing approval reference
    'MAX_ENDPOINTS' => 20, // Abort rather than truncate the population
    'MAX_LOG_EVENTS' => 5000, // Abort rather than truncate connection logs
    'DRY_RUN' => false, // True collects evidence without saving the audit
    'RESULT_PASSED_ID' => 2, // eramba Passed option
    'RESULT_FAILED_ID' => 1, // eramba Failed option
    'MAX_LOG_ITEMS' => 5, // Failure examples; full details in evidence
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'aws-client-vpn-remote-access';
const AUTOMATION_VERSION = '0.1.0';

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

function validateConfig(array $c, string $auditId): void
{
    if (!ctype_digit($auditId) || (int)$auditId < 1) throw new RuntimeException('Invalid audit context.');
    if (!is_array($c['REGIONS']) || !$c['REGIONS'] || count($c['REGIONS']) > 20) throw new RuntimeException('Configure 1–20 AWS regions.');
    foreach ($c['REGIONS'] as $r) if (!is_string($r) || !preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D',$r)) throw new RuntimeException('Invalid AWS region.');
    if (count($c['REGIONS']) !== count(array_unique($c['REGIONS']))) throw new RuntimeException('Duplicate AWS region.');
    if (!is_string($c['ENDPOINT_REGEX']) || @preg_match($c['ENDPOINT_REGEX'],'') === false) throw new RuntimeException('Invalid ENDPOINT_REGEX.');
    foreach (['LOOKBACK_DAYS'=>[1,90],'MAX_ENDPOINTS'=>[1,100],'MAX_LOG_EVENTS'=>[1,10000],'MAX_LOG_ITEMS'=>[1,10],'RESULT_PASSED_ID'=>[1,999999],'RESULT_FAILED_ID'=>[1,999999]] as $k=>$range) {
        if (!is_int($c[$k]) || $c[$k] < $range[0] || $c[$k] > $range[1]) throw new RuntimeException('Invalid '.$k.'.');
    }
    if (!is_bool($c['DRY_RUN']) || $c['RESULT_PASSED_ID'] === $c['RESULT_FAILED_ID']) throw new RuntimeException('Invalid output settings.');
    if (!is_array($c['SPLIT_TUNNEL_APPROVALS'])) throw new RuntimeException('Invalid SPLIT_TUNNEL_APPROVALS.');
    foreach ($c['SPLIT_TUNNEL_APPROVALS'] as $id=>$approval) {
        if (!preg_match('/^cvpn-endpoint-[a-f0-9]+$/D',(string)$id) || !is_string($approval) || trim($approval)==='' || strlen($approval)>500) throw new RuntimeException('Supply an endpoint ID and documented approval reference.');
    }
}

/** Stream complete pages; an incomplete collection must never save a result. */
function vpnPages(object $client, string $operation, string $key, array $args, string $tokenKey = 'NextToken'): Generator
{
    $seen = []; $pages = 0;
    do {
        if (++$pages > 100) throw new RuntimeException('Pagination limit exceeded; narrow the scope.');
        $page = awsCall($client,$operation,$args);
        foreach ($page[$key] ?? [] as $item) yield $item;
        $token = $page[$tokenKey] ?? '';
        if ($token !== '' && isset($seen[$token])) throw new RuntimeException('Repeated pagination token.');
        $seen[$token] = true;
        $args[$tokenKey] = $token;
    } while ($token !== '');
}

function collectResults(array $secrets, array $config): array
{
    $results=[]; $total=0; $eventCount=0;
    $end=time(); $start=$end-$config['LOOKBACK_DAYS']*86400;
    $GLOBALS['evidenceWindow']=gmdate('c',$start).' to '.gmdate('c',$end);
    foreach ($config['REGIONS'] as $region) {
        $options=['version'=>'latest','region'=>$region,'credentials'=>['key'=>$secrets['AWS_ACCESS_KEY_ID'],'secret'=>$secrets['AWS_SECRET_ACCESS_KEY']], 'http'=>['connect_timeout'=>5,'timeout'=>15],'retries'=>1];
        $ec2=new \Aws\Ec2\Ec2Client($options);
        $ds=new \Aws\DirectoryService\DirectoryServiceClient($options);
        $logs=new \Aws\CloudWatchLogs\CloudWatchLogsClient($options);
        foreach (vpnPages($ec2,'describeClientVpnEndpoints','ClientVpnEndpoints',['MaxResults'=>100]) as $endpoint) {
            $id=$endpoint['ClientVpnEndpointId'] ?? '';
            if ($id !== '' && preg_match($config['ENDPOINT_REGEX'],$id)!==1) continue;
            if (++$total>$config['MAX_ENDPOINTS']) throw new RuntimeException('MAX_ENDPOINTS exceeded; narrow the scope.');
            if ($id==='') { $results[]=result('population',$region,'unidentified endpoint',false,'Endpoint ID is missing.'); continue; }
            $results[]=result('endpoint',$region,$id,($endpoint['Status']['Code'] ?? '')==='available','Endpoint state: '.($endpoint['Status']['Code'] ?? 'missing').'.');
            $auth=$endpoint['AuthenticationOptions'] ?? [];
            $mfa=false; $unsupported=false; $authEvidence=[]; $directories=[];
            foreach ($auth as $method) {
                $type=$method['Type'] ?? 'missing'; $authEvidence[]=$type;
                if ($type==='directory-service-authentication') {
                    $directoryId=$method['ActiveDirectory']['DirectoryId'] ?? '';
                    if ($directoryId==='') { $unsupported=true; continue; }
                    $response=awsCall($ds,'describeDirectories',['DirectoryIds'=>[$directoryId]]);
                    $matching=array_values(array_filter($response['DirectoryDescriptions'] ?? [],fn($d)=>($d['DirectoryId'] ?? '')===$directoryId));
                    $directory=$matching[0] ?? [];
                    // Only whitelist evidence fields; never retain RadiusSettings.SharedSecret.
                    $directories[]=['DirectoryId'=>$directoryId,'Type'=>$directory['Type'] ?? 'missing','Stage'=>$directory['Stage'] ?? 'missing','RadiusStatus'=>$directory['RadiusStatus'] ?? 'missing'];
                    $enabled=in_array($directory['Type'] ?? '',['MicrosoftAD','ADConnector'],true) && ($directory['Stage'] ?? '')==='Active' && ($directory['RadiusStatus'] ?? '')==='Enabled';
                    $mfa=$mfa || $enabled;
                    if (!$enabled) $unsupported=true;
                } elseif ($type!=='certificate-authentication') {
                    $unsupported=true;
                }
            }
            $results[]=result('mfa',$region,$id,$mfa && !$unsupported,
                'Authentication: '.implode(', ',$authEvidence).'. Directory MFA evidence: '.json_encode($directories,JSON_THROW_ON_ERROR).'. SAML enforcement cannot be verified here; certificate-only authentication does not prove MFA.');
            $split=$endpoint['SplitTunnel'] ?? null; $approval=$config['SPLIT_TUNNEL_APPROVALS'][$id] ?? '';
            $results[]=result('split_tunnel',$region,$id,$split===false || ($split===true && $approval!==''),
                'SplitTunnel='.json_encode($split).'; documented approval: '.($approval ?: 'none').'.');
            $timeout=$endpoint['SessionTimeoutHours'] ?? null;
            $results[]=result('session_timeout',$region,$id,is_int($timeout) && $timeout>0,
                'Maximum session duration: '.json_encode($timeout).' hours; DisconnectOnSessionTimeout='.json_encode($endpoint['DisconnectOnSessionTimeout'] ?? null).'. This tests configured session duration, not an idle timer.');
            $protocol=$endpoint['VpnProtocol'] ?? ''; $certificate=$endpoint['ServerCertificateArn'] ?? '';
            $results[]=result('encrypted_channel',$region,$id,$protocol==='openvpn' && $certificate!=='',
                'VPN protocol: '.($protocol ?: 'missing').'; server certificate: '.($certificate ?: 'missing').'. AWS Client VPN uses TLS/OpenVPN.');
            $log=$endpoint['ConnectionLogOptions'] ?? [];
            $group=$log['CloudwatchLogGroup'] ?? ''; $stream=$log['CloudwatchLogStream'] ?? '';
            $results[]=result('logging',$region,$id,($log['Enabled'] ?? false)===true && $group!=='',
                'Connection logging enabled='.json_encode($log['Enabled'] ?? null).'; group='.$group.'; stream='.$stream.'.');
            $sessions=0; $bad=0; $counts=[];
            if (($log['Enabled'] ?? false)===true && $group!=='') {
                // Check retention before reading the complete review window.
                $groups=iterator_to_array(vpnPages($logs,'describeLogGroups','logGroups',['logGroupNamePrefix'=>$group,'limit'=>50],'nextToken'),false);
                $matching=array_values(array_filter($groups,fn($g)=>($g['logGroupName'] ?? '')===$group));
                $retention=$matching[0]['retentionInDays'] ?? null;
                $created=$matching[0]['creationTime'] ?? null;
                $historyOk=count($matching)===1 && is_numeric($created) && $created<=($start*1000) && ($retention===null || $retention>=$config['LOOKBACK_DAYS']);
                $results[]=result('log_history',$region,$id,$historyOk,'Log group creation (epoch ms): '.json_encode($created).'; retention days: '.($retention===null?'never expire':$retention).'; required window: '.$config['LOOKBACK_DAYS'].' days.');
                $args=['logGroupName'=>$group,'startTime'=>$start*1000,'endTime'=>$end*1000,'limit'=>1000];
                if ($stream!=='') $args['logStreamNames']=[$stream];
                foreach (vpnPages($logs,'filterLogEvents','events',$args,'nextToken') as $event) {
                    if (++$eventCount>$config['MAX_LOG_EVENTS']) throw new RuntimeException('MAX_LOG_EVENTS exceeded; narrow scope or review period.');
                    $data=json_decode($event['message'] ?? '',true);
                    if (!is_array($data) || empty($data['client-vpn-endpoint-id'])) { $bad++; continue; }
                    if ($data['client-vpn-endpoint-id']!==$id) continue;
                    $timestamp=$event['timestamp'] ?? null; $kind=$data['connection-log-type'] ?? ''; $state=$data['connection-attempt-status'] ?? '';
                    if (!is_numeric($timestamp) || $timestamp<$start*1000 || $timestamp>$end*1000 || !in_array($kind,['connection-attempt','connection-reset'],true) || !in_array($state,['successful','failed','waiting-for-assertion','NA'],true)) { $bad++; continue; }
                    $counts[$kind.'/'.$state]=($counts[$kind.'/'.$state] ?? 0)+1;
                    if ($kind==='connection-attempt' && $state==='successful') $sessions++;
                    // Keep session evidence without usernames, client IPs or raw messages.
                    $results[]=result('session_evidence',$region,$id,true,gmdate('c',(int)($timestamp/1000)).'; '.$kind.'; '.$state.'; connection='.substr((string)($data['connection-id'] ?? 'missing'),0,100).'.');
                    if (count($results)>2500) throw new RuntimeException('Evidence limit exceeded; narrow scope or review period.');
                }
            } else {
                $results[]=result('log_history',$region,$id,false,'Connection log history is unavailable.');
            }
            $results[]=result('access_logs',$region,$id,$sessions>0 && $bad===0,
                'Successful session records: '.$sessions.'; invalid/unattributable records: '.$bad.'; event counts: '.json_encode($counts,JSON_THROW_ON_ERROR).'. No observed sessions means insufficient activity evidence, not proven compliance.');
        }
    }
    $results[]=result('population','','Scope',$total>0,$total.' endpoints discovered independently of their logging and authentication settings.');
    foreach (['endpoint','mfa','split_tunnel','session_timeout','encrypted_channel','logging','log_history','access_logs'] as $check) {
        $items=array_values(array_filter($results,fn($r)=>$r['check']===$check));
        $bad=count(array_filter($items,fn($r)=>!$r['passed']));
        $results[]=result($check,'','Scope summary',count($items)>0 && $bad===0,count($items).' items; '.$bad.' failed.');
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
    $lines[] = 'Scope: AWS Client VPN endpoints with Directory Service MFA. Window: ' . ($GLOBALS['evidenceWindow'] ?? 'unavailable');
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

    logStep(2, 'Collecting Client VPN, Directory Service and connection log evidence');
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
