<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Cryptographic Key and Certificate Lifecycle Review
 *  Technology: AWS KMS, ACM and Elastic Load Balancing
 *  id: aws-key-certificate-lifecycle        version: 0.1.0
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
    'REGIONS' => ['eu-west-1'], // Regions in scope
    'EXPIRY_WARNING_DAYS' => 30, // At least 30 days as required by the methodology
    'MAX_RESOURCES' => 100, // Abort rather than truncate the inventory
    'DRY_RUN' => false, // True prevents all eramba writes
    'RESULT_PASSED_ID' => 2, // Passed option ID
    'RESULT_FAILED_ID' => 1, // Failed option ID
    'MAX_LOG_ITEMS' => 5, // Failure examples; full detail in CSV
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS (identical in every automation, do not edit) ───────────────
const AUTOMATION_ID = 'aws-key-certificate-lifecycle';
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
    if (!ctype_digit($auditId) || (int)$auditId<1) throw new RuntimeException('Invalid audit context.');
    if (!is_array($c['REGIONS']) || !$c['REGIONS'] || count($c['REGIONS'])>20) throw new RuntimeException('Configure 1–20 regions.');
    foreach ($c['REGIONS'] as $r) if (!is_string($r) || !preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D',$r)) throw new RuntimeException('Invalid region.');
    if (count(array_unique($c['REGIONS']))!==count($c['REGIONS'])) throw new RuntimeException('Duplicate region.');
    foreach (['MAX_RESOURCES'=>[1,500],'EXPIRY_WARNING_DAYS'=>[30,365],'MAX_LOG_ITEMS'=>[1,10],'RESULT_PASSED_ID'=>[1,999999],'RESULT_FAILED_ID'=>[1,999999]] as $k=>$range) {
        if (!is_int($c[$k]) || $c[$k]<$range[0] || $c[$k]>$range[1]) throw new RuntimeException('Invalid '.$k.'.');
    }
    if (!is_bool($c['DRY_RUN']) || $c['RESULT_PASSED_ID']===$c['RESULT_FAILED_ID']) throw new RuntimeException('Invalid output settings.');
}

function cryptoPages(object $client, string $operation, string $field, array $args=[], string $inputToken='NextToken', string $outputToken='NextToken'): Generator
{
    $seen=[]; $pages=0;
    do {
        if (++$pages>100) throw new RuntimeException('Pagination limit exceeded.');
        $page=awsCall($client,$operation,$args);
        foreach ($page[$field] ?? [] as $item) yield $item;
        $token=$page[$outputToken] ?? '';
        if ($token!=='' && isset($seen[$token])) throw new RuntimeException('Repeated pagination token.');
        if (($page['Truncated'] ?? false) && $token==='') throw new RuntimeException('Incomplete KMS pagination.');
        $seen[$token]=true; $args[$inputToken]=$token;
    } while ($token!=='');
}

function awsTime(mixed $v): ?int
{
    if ($v instanceof \DateTimeInterface) return $v->getTimestamp();
    if (is_string($v) && $v!=='') { $t=strtotime($v); return $t===false?null:$t; }
    return null;
}

function collectResults(array $secrets, array $config): array
{
    $results=[]; $resources=0; $now=time();
    $keySpecs=['SYMMETRIC_DEFAULT','RSA_2048','RSA_3072','RSA_4096','ECC_NIST_P256','ECC_NIST_P384','ECC_NIST_P521','ECC_SECG_P256K1','HMAC_224','HMAC_256','HMAC_384','HMAC_512','SM2','ML_DSA_44','ML_DSA_65','ML_DSA_87'];
    $signatures=['SHA256WITHRSA','SHA384WITHRSA','SHA512WITHRSA','SHA256WITHECDSA','SHA384WITHECDSA','SHA512WITHECDSA'];
    foreach ($config['REGIONS'] as $region) {
        $opts=['version'=>'latest','region'=>$region,'credentials'=>['key'=>$secrets['AWS_ACCESS_KEY_ID'],'secret'=>$secrets['AWS_SECRET_ACCESS_KEY']],'http'=>['connect_timeout'=>5,'timeout'=>15],'retries'=>1];
        $kms=new \Aws\Kms\KmsClient($opts); $acm=new \Aws\Acm\AcmClient($opts); $elb=new \Aws\ElasticLoadBalancingV2\ElasticLoadBalancingV2Client($opts);
        $reserve=function() use (&$resources,$config): void { if (++$resources>$config['MAX_RESOURCES']) throw new RuntimeException('MAX_RESOURCES exceeded; split the regions into separate controls.'); };
        foreach (cryptoPages($kms,'listKeys','Keys',['Limit'=>100],'Marker','NextMarker') as $key) {
            $reserve(); $id=$key['KeyId'] ?? '';
            if ($id==='') { $results[]=result('key_inventory',$region,'unidentified key',false,'KeyId missing.'); continue; }
            $meta=awsCall($kms,'describeKey',['KeyId'=>$id])['KeyMetadata'] ?? [];
            $state=$meta['KeyState'] ?? '';
            if (in_array($state,['Disabled','PendingDeletion','PendingReplicaDeletion'],true)) {
                $results[]=result('inactive_key',$region,$id,true,'Not active: '.$state.'.'); continue;
            }
            $spec=$meta['KeySpec'] ?? ''; $created=awsTime($meta['CreationDate'] ?? null);
            $results[]=result('key_inventory',$region,$id,$state==='Enabled' && $created!==null && $created<=$now && in_array($spec,$keySpecs,true),
                'State='.$state.'; key spec='.$spec.'; creation='.($created?gmdate('c',$created):'missing').'; origin='.($meta['Origin'] ?? 'missing').'. SYMMETRIC_DEFAULT is AES-256; numbered RSA/ECC/HMAC specs encode key size.');
            if ($state==='Enabled' && $spec==='SYMMETRIC_DEFAULT' && ($meta['Origin'] ?? '')==='AWS_KMS') {
                $rotation=awsCall($kms,'getKeyRotationStatus',['KeyId'=>$id]);
                $next=awsTime($rotation['NextRotationDate'] ?? null);
                $results[]=result('key_rotation',$region,$id,($rotation['KeyRotationEnabled'] ?? null)===true && $next!==null && $next>$now,
                    'Automatic rotation='.json_encode($rotation['KeyRotationEnabled'] ?? null).'; period days='.json_encode($rotation['RotationPeriodInDays'] ?? null).'; next rotation='.($next?gmdate('c',$next):'unavailable').'.');
            } else {
                $results[]=result('key_rotation',$region,$id,false,'An active AWS_KMS symmetric key with automatic rotation evidence is required. Manual/imported/asymmetric-key rotation cannot be verified by this integration.');
            }
        }
        $certs=[];
        $certTypes=['RSA_1024','RSA_2048','RSA_3072','RSA_4096','EC_prime256v1','EC_secp384r1','EC_secp521r1'];
        foreach (cryptoPages($acm,'listCertificates','CertificateSummaryList',['Includes'=>['keyTypes'=>$certTypes],'MaxItems'=>100]) as $summary) {
            $reserve(); $arn=$summary['CertificateArn'] ?? '';
            if ($arn==='') { $results[]=result('certificate_inventory',$region,'unidentified certificate',false,'Certificate ARN missing.'); continue; }
            $cert=awsCall($acm,'describeCertificate',['CertificateArn'=>$arn])['Certificate'] ?? [];
            $certs[$arn]=$cert;
            $status=$cert['Status'] ?? ''; $expiry=awsTime($cert['NotAfter'] ?? null); $start=awsTime($cert['NotBefore'] ?? null);
            if (in_array($status,['PENDING_VALIDATION','VALIDATION_TIMED_OUT','FAILED'],true)) {
                $results[]=result('unissued_certificate',$region,$arn,true,'Certificate not issued: '.$status.'.'); continue;
            }
            $renewal=$cert['RenewalSummary']['RenewalStatus'] ?? '';
            $renewalTime=awsTime($cert['RenewalSummary']['UpdatedAt'] ?? null);
            $inProgress=in_array($renewal,['PENDING_AUTO_RENEWAL','PENDING_VALIDATION'],true) && $renewalTime!==null && $renewalTime<=$now && $renewalTime>=$now-$config['EXPIRY_WARNING_DAYS']*86400;
            $expiryOk=$status==='ISSUED' && $start!==null && $start<=$now && $expiry!==null && $expiry>$now && ($expiry>$now+$config['EXPIRY_WARNING_DAYS']*86400 || $inProgress);
            $results[]=result('certificate_expiry',$region,$arn,$expiryOk,'Status='.$status.'; expires='.($expiry?gmdate('c',$expiry):'missing').'; renewal='.($renewal ?: 'none').'; renewal update='.($renewalTime?gmdate('c',$renewalTime):'missing').'.');
            $signature=strtoupper($cert['SignatureAlgorithm'] ?? '');
            $results[]=result('certificate_algorithms',$region,$arn,in_array($signature,$signatures,true),'Key algorithm='.($cert['KeyAlgorithm'] ?? 'missing').'; signature='.$signature.'. Unknown signatures do not pass.');
        }
        foreach (cryptoPages($elb,'describeLoadBalancers','LoadBalancers',['PageSize'=>100],'Marker','NextMarker') as $lb) {
            $reserve(); $arn=$lb['LoadBalancerArn'] ?? '';
            if ($arn==='') { $results[]=result('tls_policy',$region,'unidentified load balancer',false,'LoadBalancerArn missing.'); continue; }
            foreach (cryptoPages($elb,'describeListeners','Listeners',['LoadBalancerArn'=>$arn,'PageSize'=>100],'Marker','NextMarker') as $listener) {
                $reserve(); $protocol=$listener['Protocol'] ?? ''; $id=$listener['ListenerArn'] ?? '(missing listener ARN)';
                if (in_array($protocol,['HTTP','TCP','UDP','TCP_UDP'],true)) {
                    $results[]=result('non_tls_listener',$region,$id,true,'Protocol='.$protocol.'; not a TLS termination point. Downstream TLS is outside this integration.'); continue;
                }
                if (!in_array($protocol,['HTTPS','TLS'],true) || empty($listener['SslPolicy'])) { $results[]=result('tls_policy',$region,$id,false,'Unknown protocol or missing TLS policy.'); continue; }
                $policy=awsCall($elb,'describeSSLPolicies',['Names'=>[$listener['SslPolicy']]])['SslPolicies'][0] ?? [];
                $protocols=$policy['SslProtocols'] ?? []; $ciphers=array_column($policy['Ciphers'] ?? [],'Name');
                $old=array_filter($ciphers,fn($c)=>preg_match('/DES|RC4|MD5/i',$c));
                $results[]=result('tls_policy',$region,$id,!empty($protocols) && !array_diff($protocols,['TLSv1.2','TLSv1.3']) && !empty($ciphers) && !$old,
                    'Policy='.$listener['SslPolicy'].'; protocols='.implode(',',$protocols).'; deprecated ciphers='.implode(',',$old).'. SHA-1 message authentication in cipher names is not treated as SHA-1 certificate signing.');
                $attached=0;
                foreach (cryptoPages($elb,'describeListenerCertificates','Certificates',['ListenerArn'=>$id,'PageSize'=>100],'Marker','NextMarker') as $attachment) {
                    $attached++; $ca=$attachment['CertificateArn'] ?? ''; $cert=$certs[$ca] ?? [];
                    $results[]=result('listener_certificates',$region,$id,$ca!=='' && isset($certs[$ca]) && ($cert['Status'] ?? '')==='ISSUED','Attached certificate='.$ca.'; must be an issued ACM certificate included in this region\'s inventory.');
                }
                if (!$attached) $results[]=result('listener_certificates',$region,$id,false,'No attached certificate returned.');
            }
        }
        if (strlen(json_encode($results,JSON_THROW_ON_ERROR))>600000) throw new RuntimeException('Evidence size limit exceeded.');
    }
    foreach (['key_inventory','key_rotation','certificate_expiry','certificate_algorithms','tls_policy','listener_certificates'] as $check) {
        $items=array_values(array_filter($results,fn($r)=>$r['check']===$check)); $failed=count(array_filter($items,fn($r)=>!$r['passed']));
        $results[]=result($check,'','Scope summary',count($items)>0 && $failed===0,count($items).' items; '.$failed.' failed. Empty required evidence does not pass.');
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
    $lines[] = 'Scope: AWS KMS, ACM and ELB endpoints with Directory Service MFA. Window: ' . ($GLOBALS['evidenceWindow'] ?? 'unavailable');
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

    logStep(2, 'Collecting KMS, ACM and load balancer evidence');
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
