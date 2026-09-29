<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Endpoint Encryption Compliance Review
 *  Technology: Amazon WorkSpaces Personal and AWS KMS
 *  id: aws-workspaces-encryption        version: 0.1.0
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
 *    0  Check executed: dry-run, saved Passed/Failed, or evidence saved pending review.
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
    'REGIONS' => ['eu-west-1'],
    'MAX_WORKSPACES' => 200,
    'DRY_RUN' => false,
    'RESULT_PASSED_ID' => 2,
    'RESULT_FAILED_ID' => 1,
    'MAX_LOG_ITEMS' => 5,
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'aws-workspaces-encryption';
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
        ? "\nABORTED: $kind, no confirmed new result; inspect for partial writes (see STDERR).\n"
        : "\nABORTED: $kind after the audit result was saved; the comment is missing (see STDERR).\n";
}

/** One item checked. $region may be '' for technologies without regions. */
function result(string $check, string $region, string $resource, bool $passed, string $detail): array
{
    return ['check' => $check, 'region' => $region, 'resource' => $resource, 'passed' => $passed, 'detail' => $detail];
}

/** Required evidence unavailable to this integration: retain the item without deciding compliance. */
function pendingResult(string $check, string $region, string $resource, string $detail): array
{
    return result($check,$region,$resource,false,$detail) + ['pending'=>true];
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
    if (!ctype_digit($auditId) || (int)$auditId<1) throw new RuntimeException('Invalid audit context.');
    if (!is_array($c['REGIONS']) || !$c['REGIONS'] || count($c['REGIONS'])>20) throw new RuntimeException('Configure 1–20 regions.');
    foreach ($c['REGIONS'] as $r) if (!is_string($r) || !preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D',$r)) throw new RuntimeException('Invalid region.');
    if (count(array_unique($c['REGIONS']))!==count($c['REGIONS'])) throw new RuntimeException('Duplicate region.');
    foreach (['MAX_WORKSPACES'=>[1,1000],'MAX_LOG_ITEMS'=>[1,10],'RESULT_PASSED_ID'=>[1,999999],'RESULT_FAILED_ID'=>[1,999999]] as $k=>$range) {
        if (!is_int($c[$k]) || $c[$k]<$range[0] || $c[$k]>$range[1]) throw new RuntimeException('Invalid '.$k.'.');
    }
    if (!is_bool($c['DRY_RUN']) || $c['RESULT_PASSED_ID']===$c['RESULT_FAILED_ID']) throw new RuntimeException('Invalid output settings.');
}

function awsPages(object $client, string $operation, string $field, array $args=[], string $inputToken='NextToken', string $outputToken='NextToken'): Generator
{
    $seen=[]; $pages=0;
    do {
        if (++$pages>100) throw new RuntimeException('Pagination limit exceeded.');
        $page=awsCall($client,$operation,$args);
        if (!is_array($page[$field] ?? null) || !array_is_list($page[$field])) throw new RuntimeException('Incomplete AWS collection.');
        foreach ($page[$field] as $item) { if (!is_array($item)) throw new RuntimeException('Invalid AWS record.'); yield $item; }
        $token=$page[$outputToken] ?? '';
        if (!is_string($token)) throw new RuntimeException('Invalid pagination token.');
        if ($token!=='' && isset($seen[$token])) throw new RuntimeException('Repeated pagination token.');
        if (($page['Truncated'] ?? false) && $token==='') throw new RuntimeException('Incomplete AWS pagination.');
        $seen[$token]=true; $args[$inputToken]=$token;
    } while ($token!=='');
}

function collectResults(array $secrets, array $config): array
{
    $workspaces=[]; $keys=[]; $ids=[];
    foreach ($config['REGIONS'] as $region) {
        $opts=['version'=>'latest','region'=>$region,'credentials'=>['key'=>$secrets['AWS_ACCESS_KEY_ID'],'secret'=>$secrets['AWS_SECRET_ACCESS_KEY']],
            'http'=>['connect_timeout'=>5,'timeout'=>15,'verify'=>true,'allow_redirects'=>false],'retries'=>1];
        $client=new \Aws\WorkSpaces\WorkSpacesClient($opts);
        $kms=new \Aws\Kms\KmsClient($opts);
        foreach (awsPages($client,'describeWorkspaces','Workspaces',['Limit'=>25]) as $w) {
            $id=$w['WorkspaceId'] ?? null;
            if (!is_string($id) || !preg_match('/^ws-[a-z0-9]+$/D',$id) || isset($ids[$region.'/'.$id])) throw new RuntimeException('Missing or duplicate WorkSpace identity.');
            $ids[$region.'/'.$id]=true;
            if (count($ids)>$config['MAX_WORKSPACES']) throw new RuntimeException('MAX_WORKSPACES exceeded; no partial population can be evaluated.');
            // Minimize retained evidence: no user, hostname, IP, directory or subnet information.
            $record=['id'=>$id,'region'=>$region,'state'=>$w['State'] ?? null,
                'root'=>$w['RootVolumeEncryptionEnabled'] ?? null,'user'=>$w['UserVolumeEncryptionEnabled'] ?? null,
                'key'=>$w['VolumeEncryptionKey'] ?? null];
            $workspaces[]=$record;
            if ($record['state']==='TERMINATED') continue;
            $arn=$record['key'];
            if (!is_string($arn) || !preg_match('/^arn:aws(?:-us-gov|-cn)?:kms:'.preg_quote($region,'/').':\d{12}:key\/[A-Za-z0-9-]+$/D',$arn)) continue;
            if (!isset($keys[$arn])) {
                $meta=awsCall($kms,'describeKey',['KeyId'=>$arn])['KeyMetadata'] ?? null;
                if (!is_array($meta) || ($meta['Arn'] ?? null)!==$arn) throw new RuntimeException('Missing or mismatched KMS metadata.');
                $keys[$arn]=array_intersect_key($meta,array_flip(['Arn','KeyState','KeyUsage','KeySpec','KeyManager']));
            }
        }
    }
    return assessWorkspaces($workspaces,$keys);
}

function assessWorkspaces(array $workspaces, array $keys): array
{
    $rows=[]; $active=0; $encrypted=0; $terminated=0;
    // Retain stopped and unhealthy desktops: inactive compute is not disposed sensitive storage.
    $states=['PENDING','AVAILABLE','IMPAIRED','UNHEALTHY','REBOOTING','STARTING','REBUILDING','RESTORING','MAINTENANCE','ADMIN_MAINTENANCE','TERMINATING','ERROR','UPDATING','STOPPING','STOPPED','SUSPENDED'];
    foreach ($workspaces as $w) {
        $id=$w['id']; $region=$w['region'];
        if ($w['state']==='TERMINATED') { $terminated++; $rows[]=result('inactive_workspace',$region,$id,true,'Terminated; excluded from current desktop population.'); continue; }
        $active++;
        if (!in_array($w['state'],$states,true)) $rows[]=pendingResult('inventory',$region,$id,'Unknown lifecycle state; review population membership.');
        $ok=$w['root']===true && $w['user']===true;
        $detail='Root volume encrypted='.json_encode($w['root']).'; user volume encrypted='.json_encode($w['user']).'; state='.json_encode($w['state']).'.';
        $rows[]=$ok?result('volume_encryption',$region,$id,true,$detail):pendingResult('volume_encryption',$region,$id,$detail.' Review missing evidence or a documented exception with compensating controls.');
        if ($ok) $encrypted++;
        $key=is_string($w['key'])?($keys[$w['key']] ?? []):[];
        $managed=($key['KeyState'] ?? null)==='Enabled' && ($key['KeyUsage'] ?? null)==='ENCRYPT_DECRYPT' && ($key['KeySpec'] ?? null)==='SYMMETRIC_DEFAULT'
            && in_array($key['KeyManager'] ?? null,['AWS','CUSTOMER'],true);
        $detail='Key ARN='.(is_string($w['key'])?$w['key']:'missing').'; state='.json_encode($key['KeyState'] ?? null).'; manager='.json_encode($key['KeyManager'] ?? null).'. KMS metadata only; key material is never retrieved.';
        $rows[]=$managed?result('key_management',$region,$id,true,$detail):pendingResult('key_management',$region,$id,$detail.' Key management evidence needs review.');
    }
    $detail=$active.' non-terminated WorkSpaces; '.$terminated.' terminated records excluded. Both-volume encryption coverage: '.($active?sprintf('%.1f%% (%d/%d)',100*$encrypted/$active,$encrypted,$active):'not applicable (empty population)').'.';
    $rows[]=$active>0?result('population','','Selected AWS regions',true,$detail):pendingResult('population','','Selected AWS regions',$detail.' Confirm applicability; an empty account is not a passed endpoint control.');
    if (strlen(json_encode($rows,JSON_THROW_ON_ERROR))>600000) throw new RuntimeException('Evidence size limit exceeded.');
    return $rows;
}

// ─── 6. EVALUATE: turn raw results into Passed/Failed + human conclusion ───
function evaluate(array $results, array $config): array
{
    $failed = array_values(array_filter($results, fn ($r) => !$r['passed']));
    $pending = (bool)array_filter($results,fn($r)=>($r['pending'] ?? false)===true);
    $passed = count($results) > 0 && count($failed) === 0 && !$pending;
    $checks = array_count_values(array_column($results, 'check'));

    $lines   = [];
    $lines[] = sprintf('Automated audit by %s v%s on %s UTC.', AUTOMATION_ID, AUTOMATION_VERSION, gmdate('Y-m-d H:i'));
    $lines[] = 'Scope: WorkSpaces Personal desktops in the configured regions; root/user volume encryption and KMS key metadata. Physical endpoints and other desktop services are outside this scope.';
    $lines[] = sprintf('Result: %s. %d checks, %d items checked, %d passed, %d failed.',
        $pending ? 'PENDING MANUAL REVIEW' : ($passed ? 'PASSED' : 'FAILED'), count($checks), count($results), count($results) - count($failed), count($failed));
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

    if ($pending) $lines[]='Required evidence remains unresolved. Findings are retained, but no final result or completion dates will be saved.';
    return ['passed' => $passed, 'pending' => $pending, 'conclusion' => implode("\n", $lines)];
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

    if ($outcome['pending']) {
        erambaCall(addCommentMacro($auditId,'[PENDING MANUAL REVIEW] '.$outcome['conclusion'],$attachments),'add review comment');
        logInfo('Evidence saved. Result and completion dates unchanged pending review.');
        return;
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
        fputcsv($fh, [$r['check'], $r['region'] ?? '', $r['resource'], ($r['pending'] ?? false) ? 'PENDING' : ($r['passed'] ? 'PASS' : 'FAIL'), $r['detail']], ',', '"', '');
    }
    rewind($fh);
    return (string) stream_get_contents($fh);
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
$GLOBALS['runStarted'] = microtime(true);
try {
    echo sprintf("%s v%s — audit #%s\n", AUTOMATION_ID, AUTOMATION_VERSION, $auditId);

    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no audit result will be saved.' : 'LIVE RUN — evidence will be saved; unresolved requirements leave the audit pending.');
    logStep(1, 'Checking configuration');
    validateConfig($config, $auditId);
    checkSecrets($secrets);
    logInfo('Secrets present.');

    logStep(2, 'Collecting WorkSpaces encryption and KMS metadata');
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
