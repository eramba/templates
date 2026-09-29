<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Multi-Factor Authentication Coverage Review
 *  Technology: Zoom
 *  id: zoom-mfa-coverage        version: 0.1.0
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
 *    0  Check executed: dry-run, saved Passed/Failed, or evidence saved pending review.
 *    1  Technical error (credentials, network, permissions, eramba API).
 *       Before result save: audit unchanged. After result save: comment may
 *       be missing. Inspect the audit before retrying.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'ZOOM_ACCOUNT_ID' => '%SECRET_zoom_account_id%',
    'ZOOM_CLIENT_ID' => '%SECRET_zoom_client_id%',
    'ZOOM_CLIENT_SECRET_B64' => '%SECRET_zoom_client_secret_b64%',
];

// ─── 2. VARIABLES (README §7) ────────────────────────────────────────────────
$config = [
    'MAX_USERS' => 2000,
    'DRY_RUN' => false,
    'RESULT_PASSED_ID' => 2,
    'RESULT_FAILED_ID' => 1,
    'MAX_LOG_ITEMS' => 5,
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
$auditId = '%SECURITYSERVICEAUDIT_ID%';
const AUTOMATION_ID = 'zoom-mfa-coverage';
const AUTOMATION_VERSION = '0.1.0';

// ─── 4. HELPERS ─────────────────────────────────────────────────────────────
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
        ? "\nABORTED: $kind, no confirmed new audit result; inspect for partial writes (see STDERR).\n"
        : "\nABORTED: $kind after the audit result was saved; the comment is missing (see STDERR).\n";
}

/** One item checked. $region may be '' for technologies without regions. */
function result(string $check, string $region, string $resource, bool $passed, string $detail): array
{
    return ['check' => $check, 'region' => $region, 'resource' => $resource, 'passed' => $passed, 'detail' => $detail];
}

// ─── 5–6. COLLECT AND EVALUATE ──────────────────────────────────────────────
function validateConfig(array $c, string $auditId): void
{
    if (!ctype_digit($auditId) || (int)$auditId<1) throw new RuntimeException('Invalid audit context.');
    if (!is_bool($c['DRY_RUN'])) throw new RuntimeException('Invalid DRY_RUN.');
    foreach (['MAX_USERS'=>[1,5000],'MAX_LOG_ITEMS'=>[1,10],'RESULT_PASSED_ID'=>[1,999999],'RESULT_FAILED_ID'=>[1,999999]] as $key=>$range) {
        if (!is_int($c[$key]) || $c[$key]<$range[0] || $c[$key]>$range[1]) throw new RuntimeException('Invalid '.$key.'.');
    }
    if ($c['RESULT_PASSED_ID']===$c['RESULT_FAILED_ID']) throw new RuntimeException('Result IDs must differ.');
}

/** Only three operations are allowed; response-provided endpoints are never followed. */
function zoomRequest(string $operation, array $options): array
{
    [$method,$url]=match($operation) {
        'token'=>['POST','https://zoom.us/oauth/token'],
        'settings'=>['GET','https://api.zoom.us/v2/accounts/me/settings'],
        'users'=>['GET','https://api.zoom.us/v2/users'],
        default=>throw new RuntimeException('Unknown Zoom operation.'),
    };
    $client=new \GuzzleHttp\Client(['connect_timeout'=>5,'timeout'=>20,'http_errors'=>false,'allow_redirects'=>false,'verify'=>true,'stream'=>true]);
    for ($attempt=0;$attempt<3;$attempt++) {
        if (microtime(true)-$GLOBALS['runStarted']>170) throw new RuntimeException('Collection time budget exceeded.');
        try { $response=$client->request($method,$url,$options); }
        catch (\GuzzleHttp\Exception\GuzzleException $e) { throw new RuntimeException('Zoom connection failed; check connectivity.'); }
        $status=$response->getStatusCode(); $stream=$response->getBody();
        if (in_array($status,[429,503],true) && $attempt<2) {
            $delay=$response->getHeaderLine('Retry-After'); $stream->close();
            if ($delay!=='' && (!ctype_digit($delay) || (int)$delay>5)) throw new RuntimeException('Zoom throttled this run; retry later.');
            sleep($delay===''?1:max(1,(int)$delay)); continue;
        }
        if ($status!==200) { $stream->close(); throw new RuntimeException('Zoom '.$operation.' HTTP '.$status.'; check account plan, app activation and read scopes.'); }
        $body='';
        try {
            while (!$stream->eof()) {
                if (microtime(true)-$GLOBALS['runStarted']>170) throw new RuntimeException('Collection budget exceeded.');
                $chunk=$stream->read(min(65536,2000001-strlen($body)));
                if ($chunk==='' && !$stream->eof()) throw new RuntimeException('Incomplete response.');
                $body.=$chunk;
                if (strlen($body)>2000000) throw new RuntimeException('Response too large.');
            }
        } catch (Throwable $e) { throw new RuntimeException('Zoom response incomplete or collection limit exceeded.'); }
        finally { $stream->close(); }
        try { $data=json_decode($body,true,64,JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { throw new RuntimeException('Invalid Zoom JSON response.'); }
        if (!is_array($data) || isset($data['error']) || isset($data['code'])) throw new RuntimeException('Zoom returned an error or unexpected response.');
        return $data;
    }
    throw new RuntimeException('Zoom retry limit exceeded.');
}

function zoomToken(array $secrets): string
{
    foreach (['ZOOM_ACCOUNT_ID','ZOOM_CLIENT_ID'] as $key) {
        if (!preg_match('/^[A-Za-z0-9_-]{5,128}$/D',$secrets[$key])) throw new RuntimeException('Invalid '.$key.'.');
    }
    $secret=base64_decode($secrets['ZOOM_CLIENT_SECRET_B64'],true);
    if ($secret===false || $secret==='' || strlen($secret)>8192 || preg_match('/[\x00-\x1f\x7f]/',$secret)) throw new RuntimeException('Invalid base64 Zoom client secret.');
    $data=zoomRequest('token',['auth'=>[$secrets['ZOOM_CLIENT_ID'],$secret],
        'form_params'=>['grant_type'=>'account_credentials','account_id'=>$secrets['ZOOM_ACCOUNT_ID']]]);
    $token=$data['access_token'] ?? null;
    if (!is_string($token) || $token==='' || preg_match('/[\x00-\x20\x7f]/',$token) || strtolower($data['token_type'] ?? '')!=='bearer') throw new RuntimeException('Zoom returned no usable bearer token.');
    return $token;
}

/** Keep only authentication settings. Other security settings and personal data are discarded. */
function authenticationSettings(array $response): array
{
    if (!is_array($response['security'] ?? null)) throw new RuntimeException('Zoom security settings are unavailable; check settings access.');
    $selected=[];
    foreach ($response['security'] as $key=>$value) {
        if (str_starts_with($key,'sign_in_with_') || $key==='signin_with_sso' || $key==='otp_auth') $selected[$key]=$value;
    }
    ksort($selected);
    return $selected;
}

function collectEvidence(array $secrets, array $config): array
{
    $token=zoomToken($secrets);
    $headers=['Authorization'=>'Bearer '.$token,'Accept'=>'application/json'];
    $settings=authenticationSettings(zoomRequest('settings',['headers'=>$headers,'query'=>['option'=>'security']]));
    $users=[]; $ids=[]; $cursors=[]; $cursor=''; $total=null;
    for ($page=0;$page<100;$page++) {
        $query=['status'=>'active','page_size'=>100];
        if ($cursor!=='') $query['next_page_token']=$cursor;
        $data=zoomRequest('users',['headers'=>$headers,'query'=>$query]);
        if (!is_array($data['users'] ?? null) || !array_is_list($data['users']) || !is_string($data['next_page_token'] ?? null)
            || !is_int($data['total_records'] ?? null) || $data['total_records']<0) throw new RuntimeException('Incomplete Zoom user page.');
        if ($total!==null && $data['total_records']!==$total) throw new RuntimeException('Zoom user population changed during collection; retry.');
        $total=$data['total_records'];
        if ($total>$config['MAX_USERS']) throw new RuntimeException('MAX_USERS exceeded; no partial audit can be saved.');
        foreach ($data['users'] as $u) {
            if (!is_array($u) || !is_string($u['id'] ?? null) || $u['id']==='' || isset($ids[$u['id']])) throw new RuntimeException('Missing or duplicate Zoom user identity.');
            if (($u['status'] ?? null)!=='active') throw new RuntimeException('Unexpected status in the active-user population.');
            $ids[$u['id']]=true;
            $users[]=['id'=>$u['id'],'role_id'=>$u['role_id'] ?? null];
            if (count($users)>$config['MAX_USERS']) throw new RuntimeException('MAX_USERS exceeded.');
        }
        $cursor=$data['next_page_token'];
        if ($cursor==='') {
            if (count($users)!==$total) throw new RuntimeException('Zoom user total does not match collected records.');
            $after=authenticationSettings(zoomRequest('settings',['headers'=>$headers,'query'=>['option'=>'security']]));
            if ($after!==$settings) throw new RuntimeException('Zoom authentication settings changed during collection; retry.');
            return ['settings'=>$settings,'users'=>$users];
        }
        if (!$data['users'] || isset($cursors[$cursor])) throw new RuntimeException('Invalid Zoom pagination cursor.');
        $cursors[$cursor]=true;
    }
    throw new RuntimeException('Zoom page limit exceeded.');
}

function assessEvidence(array $evidence, array $config, int $now): array
{
    $s=$evidence['settings']; $users=$evidence['users']; $rows=[];
    $mode=$s['sign_in_with_two_factor_auth'] ?? null;
    $rows[]=result('account_mfa','','Zoom account',$mode==='all','2FA scope='.json_encode($mode).'; automatic coverage requires all users, not selected groups or roles.');
    $rows[]=result('local_sign_in','','Zoom account',($s['sign_in_with_work_email'] ?? null)===true,'Work-email sign-in='.json_encode($s['sign_in_with_work_email'] ?? null).'.');
    $paths=[
        'sign_in_with_google'=>['enable_sign_in_with_google'],
        'signin_with_sso'=>['enable'],
        'sign_in_with_fb'=>[], 'sign_in_with_apple'=>[], 'sign_in_with_microsoft'=>[],
        'sign_in_with_phone_number'=>['enable_sign_in_with_phone_number'],
        'sign_in_with_passkey'=>[], 'sign_in_with_outlook'=>['enable_sign_in_with_outlook'],
        'otp_auth'=>[],
    ];
    foreach ($paths as $name=>$nested) {
        $value=$s[$name] ?? null;
        foreach ($nested as $key) $value=is_array($value)?($value[$key] ?? null):null;
        $rows[]=result('authentication_path','',$name,$value===false,
            'Enabled='.json_encode($value).'. '.($value===false?'Not an enabled alternative.':'Requires separate enforcement/exception evidence; missing is not disabled.'));
    }
    $known=array_merge(array_keys($paths),['sign_in_with_work_email','sign_in_with_two_factor_auth','sign_in_with_two_factor_auth_groups','sign_in_with_two_factor_auth_roles']);
    foreach (array_keys($s) as $key) if (!in_array($key,$known,true)) $rows[]=result('authentication_path','',$key,false,'Unrecognized authentication setting; review before completing the audit.');
    $proven=!array_filter($rows,fn($r)=>!$r['passed']);
    foreach ($users as $u) $rows[]=result('user_coverage','',$u['id'],$proven,'Role ID='.json_encode($u['role_id']).'; '.($proven?'Covered by account-wide local-sign-in enforcement.':'Complete authentication-path coverage not established.'));
    $rows[]=result('population','','Active users',count($users)>0,count($users).' active users, including account administrators; no user-level MFA filter.');
    $pending=!$proven || !$users;
    $lines=[sprintf('Automated review by %s v%s at %s UTC.',AUTOMATION_ID,AUTOMATION_VERSION,gmdate('Y-m-d H:i',$now)),
        'Scope: active users of this Zoom account; interactive local sign-in, including the web administration interface.',
        'Account-wide local 2FA enforcement: '.($mode==='all'?'configured':'not established').'.',
        'Verified complete authentication-path coverage: '.($users?sprintf('%d/%d (%s)', $proven?count($users):0,count($users),$proven?'100%':'not established'):'not applicable (empty population)').'.',
        'Result: '.($pending?'PENDING MANUAL REVIEW. Review alternative paths, group/role scope, missing evidence and exceptions with compensating controls.':'PASSED within the stated Zoom scope.').
        ' This is configuration evidence, not MFA enrollment or sign-in event verification.'];
    foreach (array_slice(array_filter($rows,fn($r)=>!$r['passed']),0,$config['MAX_LOG_ITEMS']) as $r) $lines[]=substr($r['resource'].': '.$r['detail'],0,300);
    if (strlen(json_encode($rows,JSON_THROW_ON_ERROR))>600000) throw new RuntimeException('Evidence size limit exceeded.');
    return ['results'=>$rows,'outcome'=>['passed'=>!$pending,'pending'=>$pending,'conclusion'=>implode("\n",$lines)]];
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

    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no audit result will be saved.' : 'LIVE RUN — evidence will be saved; unresolved manual work leaves the audit pending.');
    logStep(1, 'Checking configuration');
    validateConfig($config, $auditId);
    checkSecrets($secrets);
    logInfo('Secrets present.');

    logStep(2, 'Collecting Zoom account settings and active users');
    $evidence = collectEvidence($secrets, $config);

    logStep(3, 'Evaluating');
    $review = assessEvidence($evidence, $config, time());
    $results = $review['results']; $outcome = $review['outcome'];
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
