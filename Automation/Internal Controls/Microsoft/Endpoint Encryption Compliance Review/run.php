<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Endpoint Encryption Compliance Review
 *  Technology: Microsoft Intune and Entra ID
 *  id: intune-windows-encryption        version: 0.2.0
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Internal%20Controls
 *
 *  Composer packages (paste in the automation's composer field):
 *    guzzlehttp/guzzle:^7.9
 * ============================================================================
 *
 *  Output
 *    STDOUT  Step-by-step log of what the script is doing (visible in eramba
 *            Automation Logs). eramba keeps only the first 10 KB.
 *    STDERR  Only used for technical errors. Any STDERR output marks the run
 *            as failed in eramba.
 *
 *  Exit codes
 *    0  Check executed: dry-run, saved Passed, or evidence saved pending review.
 *    1  Technical error (credentials, network, permissions, eramba API).
 *       Before result save: audit unchanged. After result save: comment may
 *       be missing. Inspect the audit before retrying.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'ENTRA_TENANT_ID' => '%SECRET_entra_tenant_id%',
    'ENTRA_CLIENT_ID' => '%SECRET_entra_client_id%',
    'ENTRA_CLIENT_SECRET_B64' => '%SECRET_entra_client_secret_b64%',
];

// ─── 2. VARIABLES (README §7) ────────────────────────────────────────────
$config = [
    'MAX_OBJECTS' => 2000, // Maximum records per API collection; never truncate
    'MAX_SYNC_AGE_HOURS' => 168, // Maximum age of encryption reports
    'DRY_RUN' => false, // True prevents all eramba writes
    'RESULT_PASSED_ID' => 2, // Passed option ID
    'RESULT_FAILED_ID' => 1, // Failed option ID
    'MAX_LOG_ITEMS' => 5, // Failure examples; full detail in CSV
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'intune-windows-encryption';
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
        ? "\nABORTED: $kind, no confirmed new result; inspect for partial writes (see STDERR).\n"
        : "\nABORTED: $kind after the audit result was saved; the comment is missing (see STDERR).\n";
}

/** One item checked. $region may be '' for technologies without regions. */
function result(string $check, string $region, string $resource, bool $passed, string $detail): array
{
    return ['check' => $check, 'region' => $region, 'resource' => $resource, 'passed' => $passed, 'detail' => $detail];
}

// ─── 5. COLLECT ──────────────────────────────────────────────────────────

/** Requests are restricted to Microsoft public-cloud endpoints; tokens never enter evidence. */
function graphRequest(string $method, string $url, array $options = []): array
{
    $host=parse_url($url,PHP_URL_HOST);
    if (parse_url($url,PHP_URL_SCHEME)!=='https' || !in_array($host,['graph.microsoft.com','login.microsoftonline.com'],true)
        || parse_url($url,PHP_URL_USER)!==null || parse_url($url,PHP_URL_PASS)!==null || parse_url($url,PHP_URL_FRAGMENT)!==null || parse_url($url,PHP_URL_PORT)!==null) {
        throw new RuntimeException('Unexpected Microsoft endpoint.');
    }
    $client=new \GuzzleHttp\Client(['connect_timeout'=>5,'timeout'=>20,'http_errors'=>false,'allow_redirects'=>false,'stream'=>true]);
    for ($attempt=0; $attempt<3; $attempt++) {
        if (microtime(true)-$GLOBALS['runStarted']>180) throw new RuntimeException('Collection time budget exceeded; narrow the scope.');
        try { $response=$client->request($method,$url,$options); }
        catch (\GuzzleHttp\Exception\GuzzleException $e) { throw new RuntimeException('Microsoft connection failed; check connectivity and credentials.'); }
        $status=$response->getStatusCode();
        if (in_array($status,[429,503],true) && $attempt<2) {
            $delay=$response->getHeaderLine('Retry-After');
            $response->getBody()->close();
            if ($delay!=='' && (!ctype_digit($delay) || (int)$delay>5)) throw new RuntimeException('Microsoft throttled this run; retry later.');
            sleep($delay===''?1:max(1,(int)$delay)); continue;
        }
        if ($status!==200) { $response->getBody()->close(); throw new RuntimeException('Microsoft API HTTP '.$status.'; check permissions, licence and configuration.'); }
        $stream=$response->getBody(); $body='';
        try {
        while (!$stream->eof()) {
            if (microtime(true)-$GLOBALS['runStarted']>180) throw new RuntimeException('Collection time budget exceeded.');
            $chunk=$stream->read(min(65536,2000001-strlen($body)));
            if ($chunk==='' && !$stream->eof()) throw new RuntimeException('Microsoft response could not be read completely.');
            $body.=$chunk;
            if (strlen($body)>2000000) throw new RuntimeException('Microsoft response too large; narrow the scope.');
        }
        } catch (Throwable $e) { throw new RuntimeException('Microsoft response incomplete or collection limit exceeded.'); }
        finally { $stream->close(); }
        try { $data=json_decode($body,true,64,JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { throw new RuntimeException('Invalid Microsoft JSON response.'); }
        if (!is_array($data)) throw new RuntimeException('Invalid Microsoft API response.');
        return $data;
    }
    throw new RuntimeException('Microsoft retry limit reached.');
}

function graphToken(array $secrets): string
{
    foreach (['ENTRA_TENANT_ID','ENTRA_CLIENT_ID'] as $key) {
        if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD',$secrets[$key])) throw new RuntimeException('Invalid '.$key.'.');
    }
    $clientSecret=base64_decode($secrets['ENTRA_CLIENT_SECRET_B64'],true);
    if ($clientSecret===false || $clientSecret==='' || strlen($clientSecret)>8192) throw new RuntimeException('Invalid base64 client secret.');
    $response=graphRequest('POST','https://login.microsoftonline.com/'.$secrets['ENTRA_TENANT_ID'].'/oauth2/v2.0/token',[
        'form_params'=>['grant_type'=>'client_credentials','client_id'=>$secrets['ENTRA_CLIENT_ID'],'client_secret'=>$clientSecret,'scope'=>'https://graph.microsoft.com/.default']]);
    if (!is_string($response['access_token'] ?? null) || $response['access_token']==='') throw new RuntimeException('Microsoft returned no access token.');
    return $response['access_token'];
}

function graphList(string $path, string $token, int $limit): array
{
    $url='https://graph.microsoft.com/v1.0/'.$path; $seen=[]; $items=[]; $ids=[];
    $collectionPath=parse_url($url,PHP_URL_PATH);
    do {
        if (!str_starts_with($url,'https://graph.microsoft.com/v1.0/') || parse_url($url,PHP_URL_PATH)!==$collectionPath || isset($seen[$url]) || count($seen)>=100) throw new RuntimeException('Invalid or excessive Graph pagination.');
        $seen[$url]=true;
        $response=graphRequest('GET',$url,['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']]);
        if (!isset($response['value']) || !is_array($response['value']) || !array_is_list($response['value'])) throw new RuntimeException('Graph collection is missing.');
        foreach ($response['value'] as $item) {
            if (!is_array($item)) throw new RuntimeException('Invalid Graph collection item.');
            if (!is_string($item['id'] ?? null) || $item['id']==='' || isset($ids[$item['id']])) throw new RuntimeException('Unidentified or duplicate Graph record.');
            $ids[$item['id']]=true;
            $items[]=$item;
            if (count($items)>$limit) throw new RuntimeException('Collection limit exceeded; split the control scope.');
        }
        $url=$response['@odata.nextLink'] ?? '';
        if (!is_string($url)) throw new RuntimeException('Invalid Graph pagination URL.');
    } while ($url!=='');
    return $items;
}

function validateConfig(array $c, string $auditId): void
{
    if (!ctype_digit($auditId) || (int)$auditId<1) throw new RuntimeException('Invalid audit context.');
    if (!is_bool($c['DRY_RUN'])) throw new RuntimeException('DRY_RUN must be true or false.');
    foreach (['MAX_OBJECTS'=>[1,10000],'MAX_LOG_ITEMS'=>[1,10],'RESULT_PASSED_ID'=>[1,999999],'RESULT_FAILED_ID'=>[1,999999]] as $key=>$range) {
        if (!is_int($c[$key]) || $c[$key]<$range[0] || $c[$key]>$range[1]) throw new RuntimeException('Invalid '.$key.'.');
    }
    if ($c['RESULT_PASSED_ID']===$c['RESULT_FAILED_ID']) throw new RuntimeException('Result IDs must differ.');
    if (isset($c['MAX_SYNC_AGE_HOURS']) && (!is_int($c['MAX_SYNC_AGE_HOURS']) || $c['MAX_SYNC_AGE_HOURS']<1 || $c['MAX_SYNC_AGE_HOURS']>720)) throw new RuntimeException('Invalid MAX_SYNC_AGE_HOURS.');
}

function collectResults(array $secrets, array $config): array
{
    $token=graphToken($secrets); $limit=$config['MAX_OBJECTS']; $results=[];
    $directory=graphList('devices?$select=id,deviceId,displayName,operatingSystem,accountEnabled',$token,$limit);
    $managed=graphList('deviceManagement/managedDevices?$select=id,deviceName,azureADDeviceId,operatingSystem,isEncrypted,lastSyncDateTime',$token,$limit);
    $keys=graphList('informationProtection/bitlocker/recoveryKeys?$select=id,deviceId,createdDateTime,volumeType',$token,$limit);
    $population=[]; $reports=[]; $recovery=[];
    foreach ($directory as $device) {
        if (($device['accountEnabled'] ?? null)===false) continue;
        if (empty($device['operatingSystem'])) { $results[]=result('inventory','',$device['id'] ?? 'Unidentified directory device',false,'Operating system missing; Windows scope cannot be determined.'); continue; }
        if (strtolower($device['operatingSystem'])!=='windows') continue;
        $id=$device['deviceId'] ?? '';
        if ($id==='') { $results[]=result('inventory','','Unidentified directory device',false,'Windows device has no deviceId.'); continue; }
        $population[$id]=$device['displayName'] ?? $id;
        if (($device['accountEnabled'] ?? null)!==true) $results[]=result('inventory','',$id,false,'Directory enabled status is unavailable.');
    }
    foreach ($managed as $device) {
        if (empty($device['operatingSystem'])) { $results[]=result('inventory','',$device['id'] ?? 'Unidentified managed device',false,'Operating system missing; Windows scope cannot be determined.'); continue; }
        if (strtolower($device['operatingSystem'])!=='windows') continue;
        $id=$device['azureADDeviceId'] ?? '';
        if ($id==='' || $id==='00000000-0000-0000-0000-000000000000') { $results[]=result('inventory','',$device['id'] ?? 'Unidentified managed device',false,'Windows managed device cannot be matched to an Entra device ID.'); continue; }
        $population[$id]=$device['deviceName'] ?? $id; $reports[$id][]=$device;
    }
    foreach ($keys as $key) {
        // Read metadata only. Never request or retain the recovery password.
        $id=$key['deviceId'] ?? ''; $created=strtotime($key['createdDateTime'] ?? '');
        if ($id!=='' && is_string($key['id'] ?? null) && $key['id']!=='' && in_array((string)($key['volumeType'] ?? ''),['1','operatingSystemVolume'],true) && $created!==false && $created<=time()) $recovery[$id][]=$key['id'] ?? '';
    }
    $encrypted=0; $now=time();
    foreach ($population as $id=>$name) {
        $deviceReports=$reports[$id] ?? [];
        $results[]=result('enrolment','',$id,count($deviceReports)>0,$name.'; Intune records: '.count($deviceReports).'.');
        $good=count($deviceReports)>0;
        foreach ($deviceReports as $report) {
            $sync=strtotime($report['lastSyncDateTime'] ?? '');
            $fresh=$sync!==false && $sync<=$now && $now-$sync<=$config['MAX_SYNC_AGE_HOURS']*3600;
            $ok=($report['isEncrypted'] ?? null)===true && $fresh; $good=$good && $ok;
            $results[]=result('encryption','',$id,$ok,'Intune record='.($report['id'] ?? 'missing').'; isEncrypted='.json_encode($report['isEncrypted'] ?? null).'; lastSync='.($report['lastSyncDateTime'] ?? 'missing').'.');
        }
        if (!$deviceReports) $results[]=result('encryption','',$id,false,'No Intune encryption report for this directory device.');
        if ($good) $encrypted++;
        $results[]=result('key_management','',$id,!empty($recovery[$id]),'Escrowed OS-volume BitLocker recovery key metadata records: '.count($recovery[$id] ?? []).'. Recovery secrets were not retrieved.');
    }
    $results[]=result('inventory','','Scope summary',count($population)>0,count($population).' Windows devices from the union of enabled Entra inventory and Intune management records.');
    foreach (['enrolment','encryption','key_management'] as $check) {
        $items=array_values(array_filter($results,fn($r)=>$r['check']===$check)); $bad=count(array_filter($items,fn($r)=>!$r['passed']));
        $results[]=result($check,'','Scope summary',count($items)>0 && $bad===0,count($items).' observations; '.$bad.' failed.');
    }
    $results[]=result('coverage','','Windows scope',$encrypted===count($population) && $encrypted>0,sprintf('%d/%d devices have fresh encrypted reports (%.1f%%). No unencrypted-device exception is accepted automatically.', $encrypted,count($population),$population?$encrypted/count($population)*100:0));
    if (strlen(json_encode($results,JSON_THROW_ON_ERROR))>600000) throw new RuntimeException('Evidence limit exceeded.');
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
    $lines[] = 'Scope: Windows devices known to Entra ID or Intune; current encryption and recovery-key metadata.';
    $lines[] = sprintf('Result: %s. %d checks, %d items checked, %d passed, %d failed.',
        $passed ? 'PASSED' : 'PENDING MANUAL REVIEW', count($checks), count($results), count($results) - count($failed), count($failed));
    if (count($results) === 0) {
        $lines[] = 'No evidence was collected; review the population before completing the audit.';
    }
    if (!$passed) $lines[]='Review missing/stale evidence, encryption gaps and documented exceptions with compensating controls before completing the audit.';
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

    return ['passed' => $passed, 'pending' => !$passed, 'conclusion' => implode("\n", $lines)];
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
        erambaCall(addCommentMacro($auditId, '[PENDING MANUAL REVIEW] '.$outcome['conclusion'], $attachments), 'add review comment');
        logInfo('Evidence and follow-up recorded. Audit result and execution dates were not changed.');
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
        fputcsv($fh, array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v) ? "'".$v : $v, [$r['check'], $r['region'] ?? '', $r['resource'], $r['passed'] ? 'PASS' : 'FAIL', $r['detail']]), ',', '"', '');
    }
    rewind($fh);
    return (string) stream_get_contents($fh);
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
$GLOBALS['runStarted'] = microtime(true);
try {
    echo sprintf("%s v%s — audit #%s\n", AUTOMATION_ID, AUTOMATION_VERSION, $auditId);

    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no audit result will be saved.' : 'LIVE RUN — evidence will be saved; unresolved exceptions leave the audit pending.');
    logStep(1, 'Checking configuration');
    validateConfig($config, $auditId);
    checkSecrets($secrets);
    logInfo('Secrets present.');

    logStep(2, 'Collecting Microsoft Graph evidence');
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
