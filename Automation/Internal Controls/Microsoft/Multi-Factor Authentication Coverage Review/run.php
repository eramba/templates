<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Multi-Factor Authentication Coverage Review
 *  Technology: Microsoft Entra ID
 *  id: entra-mfa-coverage        version: 0.1.0
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
 *    0  Check executed. Dry-run, or audit updated with Passed or Failed.
 *    1  Technical error (credentials, network, permissions, eramba API).
 *       Before result save: audit unchanged. After result save: comment may
 *       be missing. Inspect the audit before retrying.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'ENTRA_TENANT_ID' => '%SECRET_entra_tenant_id%',
    'ENTRA_CLIENT_ID' => '%SECRET_entra_client_id%',
    'ENTRA_CLIENT_SECRET' => '%SECRET_entra_client_secret%',
];

// ─── 2. VARIABLES (README §7) ────────────────────────────────────────────
$config = [
    'MAX_OBJECTS' => 2000, // Maximum records per API collection; never truncate
    'DRY_RUN' => false, // True prevents all eramba writes
    'RESULT_PASSED_ID' => 2, // Passed option ID
    'RESULT_FAILED_ID' => 1, // Failed option ID
    'MAX_LOG_ITEMS' => 5, // Failure examples; full detail in CSV
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'entra-mfa-coverage';
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

/** Requests are restricted to Microsoft public-cloud endpoints; tokens never enter evidence. */
function graphRequest(string $method, string $url, array $options = []): array
{
    $host=parse_url($url,PHP_URL_HOST);
    if (parse_url($url,PHP_URL_SCHEME)!=='https' || !in_array($host,['graph.microsoft.com','login.microsoftonline.com'],true)
        || parse_url($url,PHP_URL_USER)!==null || parse_url($url,PHP_URL_PORT)!==null) {
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
            if ($delay!=='' && (!ctype_digit($delay) || (int)$delay>5)) throw new RuntimeException('Microsoft throttled this run; retry later.');
            sleep($delay===''?1:max(1,(int)$delay)); continue;
        }
        if ($status!==200) throw new RuntimeException('Microsoft API HTTP '.$status.'; check permissions, licence and configuration.');
        $stream=$response->getBody(); $body='';
        while (!$stream->eof()) {
            if (microtime(true)-$GLOBALS['runStarted']>180) throw new RuntimeException('Collection time budget exceeded.');
            $chunk=$stream->read(min(65536,2000001-strlen($body)));
            if ($chunk==='' && !$stream->eof()) throw new RuntimeException('Microsoft response could not be read completely.');
            $body.=$chunk;
            if (strlen($body)>2000000) throw new RuntimeException('Microsoft response too large; narrow the scope.');
        }
        $stream->close();
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
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
    $response=graphRequest('POST','https://login.microsoftonline.com/'.$secrets['ENTRA_TENANT_ID'].'/oauth2/v2.0/token',[
        'form_params'=>['grant_type'=>'client_credentials','client_id'=>$secrets['ENTRA_CLIENT_ID'],'client_secret'=>$secrets['ENTRA_CLIENT_SECRET'],'scope'=>'https://graph.microsoft.com/.default']]);
    if (!is_string($response['access_token'] ?? null) || $response['access_token']==='') throw new RuntimeException('Microsoft returned no access token.');
    return $response['access_token'];
}

function graphList(string $path, string $token, int $limit): array
{
    $url='https://graph.microsoft.com/v1.0/'.$path; $seen=[]; $items=[];
    do {
        if (!str_starts_with($url,'https://graph.microsoft.com/v1.0/') || isset($seen[$url]) || count($seen)>=100) throw new RuntimeException('Invalid or excessive Graph pagination.');
        $seen[$url]=true;
        $response=graphRequest('GET',$url,['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']]);
        if (!isset($response['value']) || !is_array($response['value'])) throw new RuntimeException('Graph collection is missing.');
        foreach ($response['value'] as $item) {
            if (!is_array($item)) throw new RuntimeException('Invalid Graph collection item.');
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
}

/** Conservative proof: all users/client types, no conditional restrictions or exclusions. */
function universalMfa(array $policy, array $strengths): array
{
    if (($policy['state'] ?? '')!=='enabled') return [false,'Policy is not enabled.'];
    $c=$policy['conditions'] ?? []; $users=$c['users'] ?? []; $apps=$c['applications'] ?? [];
    if (($users['includeUsers'] ?? [])!==['All']) return [false,'Not an all-users policy.'];
    foreach ($users as $key=>$value) {
        if (!in_array($key,['includeUsers','includeGroups','includeRoles','@odata.type'],true) && !empty($value)) return [false,'User exclusions or unsupported user conditions.'];
    }
    if (($c['clientAppTypes'] ?? [])!==['all']) return [false,'Not all client application types.'];
    foreach ($c as $key=>$value) {
        if (!in_array($key,['users','applications','clientAppTypes','@odata.type'],true) && !empty($value)) return [false,'Conditional restriction: '.$key.'.'];
    }
    foreach ($apps as $key=>$value) {
        if (!in_array($key,['includeApplications','@odata.type'],true) && !empty($value)) return [false,'Application exclusions, filters or user-action targeting.'];
    }
    if (empty($apps['includeApplications'])) return [false,'No included applications.'];
    $g=$policy['grantControls'] ?? []; $builtin=$g['builtInControls'] ?? []; $strengthId=$g['authenticationStrength']['id'] ?? '';
    $strengthMfa=$strengthId!=='' && ($strengths[$strengthId]['requirementsSatisfied'] ?? '')==='mfa';
    $requiresMfa=in_array('mfa',$builtin,true) || $strengthMfa;
    $controlCount=count($builtin)+count($g['customAuthenticationFactors'] ?? [])+count($g['termsOfUse'] ?? [])+($strengthId!==''?1:0);
    $operator=$g['operator'] ?? '';
    $ok=$requiresMfa && ($operator==='AND' || ($operator==='OR' && $controlCount===1));
    return [$ok,$ok?'MFA required for all users and client types.':'MFA is absent, optional or cannot be proven.'];
}

function collectResults(array $secrets, array $config): array
{
    $token=graphToken($secrets); $limit=$config['MAX_OBJECTS']; $results=[];
    $apps=graphList('servicePrincipals?$select=id,appId,displayName,accountEnabled,servicePrincipalType',$token,$limit);
    $policies=graphList('identity/conditionalAccess/policies',$token,$limit);
    $strengths=[];
    foreach (graphList('policies/authenticationStrengthPolicies',$token,$limit) as $strength) {
        if (isset($strength['id'])) $strengths[$strength['id']]=$strength;
    }
    $proof=[]; $global=false;
    foreach ($policies as $policy) {
        [$ok,$why]=universalMfa($policy,$strengths);
        $id=$policy['id'] ?? '(missing policy id)';
        if ($ok && isset($policy['id'])) {
            foreach ($policy['conditions']['applications']['includeApplications'] as $appId) {
                $proof[$appId][]=$id; if ($appId==='All') $global=true;
            }
        }
        $results[]=result('policy_evidence','',$id,true,json_encode(['name'=>$policy['displayName'] ?? '', 'state'=>$policy['state'] ?? '', 'usableForProof'=>$ok,'reason'=>$why,'conditions'=>$policy['conditions'] ?? null,'grantControls'=>$policy['grantControls'] ?? null],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    $count=0; $covered=0;
    foreach ($apps as $app) {
        if (($app['servicePrincipalType'] ?? '')==='ManagedIdentity' || ($app['accountEnabled'] ?? null)===false) continue;
        $count++; $id=$app['id'] ?? '(missing id)'; $appId=$app['appId'] ?? '';
        $ok=$appId!=='' && ($app['accountEnabled'] ?? null)===true && ($app['servicePrincipalType'] ?? '')==='Application' && ($global || isset($proof[$appId]));
        if ($ok) $covered++;
        $results[]=result('mfa_coverage','',$id,$ok,($app['displayName'] ?? 'Unnamed application').'; appId='.$appId.'; MFA policies='.implode(',',$proof['All'] ?? $proof[$appId] ?? []).'; enabled='.json_encode($app['accountEnabled'] ?? null).'.');
    }
    // A global policy is required for first-party/admin resources absent from the local inventory.
    $results[]=result('admin_coverage','','All Entra resources',$global,'An all-resources policy must require MFA for all users/client types to cover administrative and other resources beyond the discovered app list.');
    $results[]=result('mfa_coverage','','Scope summary',$count>0 && $covered===$count,sprintf('%d/%d enabled application records have proven coverage (%.1f%%). Unsupported/excluded policies are retained in evidence; they do not establish coverage.', $covered,$count,$count?$covered/$count*100:0));
    if (strlen(json_encode($results,JSON_THROW_ON_ERROR))>600000) throw new RuntimeException('Evidence limit exceeded; reduce tenant scope through a separate implementation.');
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
    $lines[] = 'Scope: Entra-authenticated user access in one tenant; current Conditional Access configuration.';
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
        fputcsv($fh, array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v) ? "'".$v : $v, [$r['check'], $r['region'] ?? '', $r['resource'], $r['passed'] ? 'PASS' : 'FAIL', $r['detail']]), ',', '"', '');
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
