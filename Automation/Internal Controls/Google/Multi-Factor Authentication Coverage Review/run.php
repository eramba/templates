<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Multi-Factor Authentication Coverage Review
 *  Technology: Google Workspace
 *  id: google-workspace-mfa        version: 0.1.0
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Internal%20Controls
 *
 *  Composer packages (paste in the automation's composer field):
 *    google/apiclient:^2.18
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
$secrets = ['GOOGLE_SERVICE_ACCOUNT_JSON_B64' => '%SECRET_google_service_account_json_b64%'];

// ─── 2. VARIABLES (README §7) ────────────────────────────────────────────────
$config = [
    'CUSTOMER_ID' => 'my_customer',
    'DELEGATED_ADMIN_EMAIL' => '', // Read-only user administrator to impersonate
    'MAX_USERS' => 2000,
    'DRY_RUN' => false,
    'RESULT_PASSED_ID' => 2,
    'RESULT_FAILED_ID' => 1,
    'MAX_LOG_ITEMS' => 5,
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
$auditId = '%SECURITYSERVICEAUDIT_ID%';
const AUTOMATION_ID = 'google-workspace-mfa';
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
    if (!is_string($c['CUSTOMER_ID']) || !preg_match('/^(?:my_customer|C[a-zA-Z0-9]+)$/D',$c['CUSTOMER_ID'])) throw new RuntimeException('Invalid CUSTOMER_ID.');
    if (!is_string($c['DELEGATED_ADMIN_EMAIL']) || !filter_var($c['DELEGATED_ADMIN_EMAIL'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Set DELEGATED_ADMIN_EMAIL.');
    if (!is_bool($c['DRY_RUN'])) throw new RuntimeException('Invalid DRY_RUN.');
    foreach (['MAX_USERS'=>[1,5000],'MAX_LOG_ITEMS'=>[1,10],'RESULT_PASSED_ID'=>[1,999999],'RESULT_FAILED_ID'=>[1,999999]] as $key=>$range) {
        if (!is_int($c[$key]) || $c[$key]<$range[0] || $c[$key]>$range[1]) throw new RuntimeException('Invalid '.$key.'.');
    }
    if ($c['RESULT_PASSED_ID']===$c['RESULT_FAILED_ID']) throw new RuntimeException('Result IDs must differ.');
}

/** Only service-account signing fields are imported; credential URLs are never trusted. */
function serviceAccount(array $secrets): array
{
    $encoded=$secrets['GOOGLE_SERVICE_ACCOUNT_JSON_B64'];
    if (strlen($encoded)>30000) throw new RuntimeException('Credential size limit exceeded.');
    $json=base64_decode($encoded,true);
    try { $key=$json===false?null:json_decode($json,true,16,JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { throw new RuntimeException('Invalid base64 service-account JSON.'); }
    if (!is_array($key) || ($key['type'] ?? '')!=='service_account'
        || !is_string($key['client_email'] ?? null) || !preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.iam\.gserviceaccount\.com$/D',$key['client_email'])
        || !is_string($key['client_id'] ?? null) || !ctype_digit($key['client_id'])
        || !is_string($key['private_key'] ?? null) || !is_string($key['private_key_id'] ?? null)) throw new RuntimeException('Invalid service-account signing fields.');
    $private=openssl_pkey_get_private($key['private_key']);
    $details=$private===false?false:openssl_pkey_get_details($private);
    if ($details===false || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048) throw new RuntimeException('Invalid service-account RSA key.');
    return array_intersect_key($key,array_flip(['type','client_email','client_id','private_key','private_key_id']));
}

function collectUsers(array $secrets, array $config): array
{
    $key=serviceAccount($secrets);
    // Bound both OAuth and Directory requests; SDK retries are disabled to preserve the run budget.
    $http=new \GuzzleHttp\Client([
        'connect_timeout'=>5,'timeout'=>20,'allow_redirects'=>false,'verify'=>true,
        'on_headers'=>function ($response): void {
            if ((int)$response->getHeaderLine('Content-Length')>2000000) throw new RuntimeException('Response too large.');
        },
        'progress'=>function ($total,$downloaded): void {
            if ($downloaded>2000000 || microtime(true)-$GLOBALS['runStarted']>170) throw new RuntimeException('Collection budget exceeded.');
        },
    ]);
    try {
        $client=new \Google\Client(['retry'=>['retries'=>0],'universe_domain'=>'googleapis.com']);
        $client->setHttpClient($http);
        $client->setAuthConfig($key);
        $client->setSubject($config['DELEGATED_ADMIN_EMAIL']);
        $client->setScopes(['https://www.googleapis.com/auth/admin.directory.user.readonly']);
        $directory=new \Google\Service\Directory($client);
    } catch (Throwable $e) { throw new RuntimeException('Google client initialization failed; check the official Composer packages and credential configuration.'); }
    $users=[]; $ids=[]; $tokens=[]; $token=null;
    for ($page=0;$page<100;$page++) {
        if (microtime(true)-$GLOBALS['runStarted']>170) throw new RuntimeException('Collection time budget exceeded.');
        $params=['customer'=>$config['CUSTOMER_ID'],'projection'=>'full','viewType'=>'admin_view','maxResults'=>100,
            'fields'=>'nextPageToken,users(id,suspended,archived,isAdmin,isDelegatedAdmin,isEnrolledIn2Sv,isEnforcedIn2Sv)'];
        if ($token!==null) $params['pageToken']=$token;
        try { $response=$directory->users->listUsers($params); }
        catch (Throwable $e) { throw new RuntimeException('Google Directory request failed; check delegation, user-read permission, quotas and connectivity. No provider response is logged.'); }
        $batch=$response->getUsers();
        if ($batch===null) $batch=[]; // Directory omits users for an empty population.
        if (!is_array($batch)) throw new RuntimeException('Invalid Directory user collection.');
        foreach ($batch as $user) {
            $id=$user->getId();
            if (!is_string($id) || $id==='' || isset($ids[$id])) throw new RuntimeException('Missing or duplicate user identity.');
            $ids[$id]=true;
            $users[]=['id'=>$id,'suspended'=>$user->getSuspended(),'archived'=>$user->getArchived(),
                'admin'=>$user->getIsAdmin(),'delegatedAdmin'=>$user->getIsDelegatedAdmin(),
                'enrolled'=>$user->getIsEnrolledIn2Sv(),'enforced'=>$user->getIsEnforcedIn2Sv()];
            if (count($users)>$config['MAX_USERS']) throw new RuntimeException('MAX_USERS exceeded; no partial audit can be saved.');
        }
        $next=$response->getNextPageToken();
        if ($next===null || $next==='') return $users;
        if (!is_string($next) || isset($tokens[$next]) || !$batch) throw new RuntimeException('Invalid Directory pagination token.');
        $tokens[$next]=true; $token=$next;
    }
    throw new RuntimeException('Directory page limit exceeded.');
}

function assessUsers(array $users, array $config, int $now): array
{
    $rows=[]; $active=0; $covered=0; $inactive=0; $admins=0; $pending=false;
    foreach ($users as $u) {
        if (($u['suspended'] ?? null)===true || ($u['archived'] ?? null)===true) { $inactive++; continue; }
        $active++;
        if (($u['admin'] ?? null)===true || ($u['delegatedAdmin'] ?? null)===true) $admins++;
        $known=($u['suspended'] ?? null)===false && ($u['archived'] ?? null)===false
            && is_bool($u['admin'] ?? null) && is_bool($u['delegatedAdmin'] ?? null)
            && is_bool($u['enrolled'] ?? null) && is_bool($u['enforced'] ?? null);
        $pass=$known && $u['enrolled'] && $u['enforced'];
        if ($pass) $covered++; else $pending=true;
        $rows[]=result('mfa_enforcement','',$u['id'],$pass,
            'Enrolled='.json_encode($u['enrolled'] ?? null).'; enforced='.json_encode($u['enforced'] ?? null)
            .'; admin='.json_encode($u['admin'] ?? null).'; delegated admin='.json_encode($u['delegatedAdmin'] ?? null)
            .($pass?'.':'; review missing evidence or exception and compensating controls.'));
    }
    $rows[]=result('population','','Directory',$active>0,count($users).' accounts; '.$active.' active/unknown; '.$inactive.' suspended or archived excluded; '.$admins.' active administrators.');
    if (!$active) $pending=true;
    $rate=$active?sprintf('%.1f%% (%d/%d)',100*$covered/$active,$covered,$active):'not applicable (no active accounts)';
    $lines=[sprintf('Automated review by %s v%s at %s UTC.',AUTOMATION_ID,AUTOMATION_VERSION,gmdate('Y-m-d H:i',$now)),
        'Scope: Google-native interactive user authentication in customer '.$config['CUSTOMER_ID'].'.',
        'MFA enrollment and enforcement coverage: '.$rate.'.',
        'Result: '.($pending?'PENDING MANUAL REVIEW — review gaps, exceptions and compensating controls.':'PASSED within the configured Google-native scope.').
        ' SSO, app passwords, API/service accounts and other systems require separate evidence.'];
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
    unset($config['DELEGATED_ADMIN_EMAIL']); // No delegated identity email in attachments.
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

    logStep(2, 'Collecting Google Workspace evidence');
    $users = collectUsers($secrets, $config);

    logStep(3, 'Evaluating');
    $review = assessUsers($users, $config, time());
    $results = $review['results']; $outcome = $review['outcome'];
    echo $outcome['conclusion'] . "\n";

    logStep(4, 'Writing result to eramba');
    report($auditId, $outcome, $results, $config);

    echo $config['DRY_RUN'] ? "\nSIMULATION COMPLETE — no audit result saved.\n" : "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo abortMessage('technical error');
    fwrite(STDERR, 'ERROR: ' . ($e instanceof RuntimeException && $e->getCode() === 0 ? $e->getMessage() : 'Unexpected execution error; no provider response is logged.') . "\n");
    exit(1);
}
