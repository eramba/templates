<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Nonconformity and Corrective Action Tracking
 *  Technology: Linear
 *  id: linear-corrective-actions        version: 0.1.0
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
    'LINEAR_API_KEY_B64' => '%SECRET_linear_api_key_b64%',
];

// ─── 2. VARIABLES (README §7) ────────────────────────────────────────────
$config = [
    'TEAM_ID' => '', // UUID of the team holding the authoritative register
    'REGISTER_LABEL' => 'nonconformity', // Exact register label name
    'REVIEWER_IDS' => [], // Linear user UUIDs authorized to verify corrective-action evidence
    'DEADLINE_TIMEZONE' => 'UTC', // Timezone of agreed due dates
    'MAX_ISSUES' => 200, // Complete register, including completed and archived records
    'DRY_RUN' => false,
    'RESULT_PASSED_ID' => 2,
    'RESULT_FAILED_ID' => 1,
    'MAX_LOG_ITEMS' => 5,
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with values of the audit record the automation runs on.
$auditId = '%SECURITYSERVICEAUDIT_ID%';

// ─── 4. HELPERS ───────────────
const AUTOMATION_ID = 'linear-corrective-actions';
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
        ? "\nABORTED: $kind, no confirmed new audit result; inspect for partial writes (see STDERR).\n"
        : "\nABORTED: $kind after the audit result was saved; the comment is missing (see STDERR).\n";
}

/** One item checked. $region may be '' for technologies without regions. */
function result(string $check, string $region, string $resource, bool $passed, string $detail): array
{
    return ['check' => $check, 'region' => $region, 'resource' => $resource, 'passed' => $passed, 'detail' => $detail];
}

// ─── 5–6. COLLECT AND EVALUATE ──────────────────────────────────────────
function validateConfig(array $c, string $auditId): void
{
    if (!ctype_digit($auditId) || (int)$auditId < 1) throw new RuntimeException('Invalid audit context.');
    if (!is_string($c['TEAM_ID']) || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD',$c['TEAM_ID'])) throw new RuntimeException('Set TEAM_ID to the Linear team UUID.');
    if (!is_string($c['REGISTER_LABEL']) || trim($c['REGISTER_LABEL'])==='' || strlen($c['REGISTER_LABEL'])>100) throw new RuntimeException('Invalid REGISTER_LABEL.');
    if (!in_array($c['DEADLINE_TIMEZONE'], DateTimeZone::listIdentifiers(), true)) throw new RuntimeException('Invalid DEADLINE_TIMEZONE.');
    if (!is_bool($c['DRY_RUN'])) throw new RuntimeException('Invalid DRY_RUN.');
    foreach (['MAX_ISSUES'=>[1,2000], 'MAX_LOG_ITEMS'=>[1,10], 'RESULT_PASSED_ID'=>[1,999999], 'RESULT_FAILED_ID'=>[1,999999]] as $key=>$range) {
        if (!is_int($c[$key]) || $c[$key]<$range[0] || $c[$key]>$range[1]) throw new RuntimeException('Invalid '.$key.'.');
    }
    if ($c['RESULT_PASSED_ID']===$c['RESULT_FAILED_ID']) throw new RuntimeException('Result IDs must differ.');
    if (!is_array($c['REVIEWER_IDS']) || !array_is_list($c['REVIEWER_IDS'])) throw new RuntimeException('Invalid REVIEWER_IDS.');
    foreach ($c['REVIEWER_IDS'] as $id) if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/iD',$id)) throw new RuntimeException('Invalid reviewer UUID.');

}

/** Fixed endpoint and read-only query; variables are JSON values, never interpolated GraphQL. */
function linearRequest(array $variables, array $secrets, ?string $query = null): array
{
    $query ??= <<<'GRAPHQL'
query CorrectiveActionRegister($teamId: String!, $label: String!, $after: String) {
  team(id: $teamId) {
    id
    issues(first: 100, after: $after, includeArchived: true,
           filter: {labels: {name: {eq: $label}}}) {
      nodes {
        id identifier createdAt updatedAt completedAt dueDate priority archivedAt description
        state { name type }
        assignee { id }
        team { id }
      }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;
    $key=base64_decode($secrets['LINEAR_API_KEY_B64'],true);
    if (!is_string($key) || $key==='' || strlen($key)>8192 || preg_match('/[\r\n]/',$key)) throw new RuntimeException('Invalid base64 API key.');
    $client=new \GuzzleHttp\Client(['connect_timeout'=>5,'timeout'=>20,'read_timeout'=>20,'verify'=>true,'http_errors'=>false,'allow_redirects'=>false,'stream'=>true]);
    for ($attempt=0; $attempt<3; $attempt++) {
        if (microtime(true)-$GLOBALS['runStarted']>170) throw new RuntimeException('Collection time budget exceeded.');
        try {
            $response=$client->request('POST','https://api.linear.app/graphql',[
                'headers'=>['Authorization'=>$key,'Accept'=>'application/json'],
                'json'=>['query'=>$query,'variables'=>$variables]]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) { throw new RuntimeException('Linear connection failed.'); }
        $status=$response->getStatusCode(); $stream=$response->getBody();
        if (in_array($status,[429,503],true) && $attempt<2) {
            $delay=$response->getHeaderLine('Retry-After'); $stream->close();
            if ($delay!=='' && (!ctype_digit($delay) || (int)$delay>5)) throw new RuntimeException('Linear throttled this run; retry later.');
            sleep($delay===''?1:max(1,(int)$delay)); continue;
        }
        if ($status!==200) { $stream->close(); throw new RuntimeException('Linear API HTTP '.$status.'; check key permissions, scope and rate limits.'); }
        $body='';
        while (!$stream->eof()) {
            if (microtime(true)-$GLOBALS['runStarted']>170) throw new RuntimeException('Collection time budget exceeded.');
            $chunk=$stream->read(min(65536,2000001-strlen($body)));
            if ($chunk==='' && !$stream->eof()) throw new RuntimeException('Incomplete Linear response.');
            $body.=$chunk;
            if (strlen($body)>2000000) throw new RuntimeException('Linear response size limit exceeded.');
        }
        $stream->close();
        try { $data=json_decode($body,true,64,JSON_THROW_ON_ERROR); }
        catch (Throwable $e) { throw new RuntimeException('Invalid Linear JSON response.'); }
        // GraphQL can return HTTP 200 with partial data AND errors. Never evaluate that data.
        if (!is_array($data) || !empty($data['errors']) || !is_array($data['data'] ?? null)) throw new RuntimeException('Linear GraphQL error or incomplete response; no partial audit can be saved.');
        return $data['data'];
    }
    throw new RuntimeException('Linear retry limit exceeded.');
}

function collectIssues(array $secrets, array $config): array
{
    $issues=[]; $ids=[]; $tokens=[]; $after=null;
    for ($page=0; $page<100; $page++) {
        $data=linearRequest(['teamId'=>$config['TEAM_ID'],'label'=>$config['REGISTER_LABEL'],'after'=>$after],$secrets);
        if (($data['team']['id'] ?? null)!==$config['TEAM_ID']) throw new RuntimeException('Linear team is inaccessible or differs from TEAM_ID.');
        $connection=$data['team']['issues'] ?? null;
        if (!is_array($connection) || !is_array($connection['nodes'] ?? null) || !array_is_list($connection['nodes']) || !is_bool($connection['pageInfo']['hasNextPage'] ?? null)) throw new RuntimeException('Incomplete Linear issue page.');
        foreach ($connection['nodes'] as $issue) {
            if (!is_array($issue) || !is_string($issue['id'] ?? null) || $issue['id']==='' || isset($ids[$issue['id']])) throw new RuntimeException('Unidentified or duplicate Linear issue; collection cannot be trusted.');
            if (($issue['team']['id'] ?? null)!==$config['TEAM_ID']) throw new RuntimeException('Linear returned an issue outside the requested team.');
            $ids[$issue['id']]=true;
            $issue['reviewHash']=reviewHash($issue);
            $issue['hasDescription']=is_string($issue['description'] ?? null) && trim($issue['description'])!=='';
            unset($issue['description']); // Do not retain or export free-text content.
            $issue['reviews']=collectReviews($issue['id'],$secrets,$config);
            $issues[]=$issue;
            if (count($issues)>$config['MAX_ISSUES']) throw new RuntimeException('MAX_ISSUES exceeded; split the control scope.');
        }
        if (!$connection['pageInfo']['hasNextPage']) return $issues;
        $cursor=$connection['pageInfo']['endCursor'] ?? null;
        if (!is_string($cursor) || $cursor==='' || isset($tokens[$cursor]) || !$connection['nodes']) throw new RuntimeException('Invalid Linear pagination cursor.');
        $tokens[$cursor]=true; $after=$cursor;
    }
    throw new RuntimeException('Linear page limit exceeded.');
}

/** Bind reviewer evidence to the exact fields evaluated, not to a mutable label or status alone. */
function reviewHash(array $issue): string
{
    $fields=[];
    foreach (['id','createdAt','completedAt','dueDate','priority','archivedAt','description'] as $key) $fields[$key]=$issue[$key] ?? null;
    $fields['state']=$issue['state'] ?? null;
    $fields['assignee']=$issue['assignee'] ?? null;
    $fields['team']=$issue['team'] ?? null;
    return hash('sha256',json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function collectReviews(string $issueId, array $secrets, array $config): array
{
    $query= <<<'GRAPHQL'
query CorrectiveActionReviews($issueId: String!, $after: String) {
  issue(id: $issueId) {
    id
    comments(first: 100, after: $after) {
      nodes { id body createdAt updatedAt user { id } }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL;
    $after=null; $cursors=[]; $ids=[]; $reviews=[];
    do {
        $data=linearRequest(['issueId'=>$issueId,'after'=>$after],$secrets,$query);
        if (($data['issue']['id'] ?? null)!==$issueId) throw new RuntimeException('Issue review scope mismatch.');
        $page=$data['issue']['comments'] ?? null;
        if (!is_array($page) || !is_array($page['nodes'] ?? null) || !array_is_list($page['nodes']) || !is_bool($page['pageInfo']['hasNextPage'] ?? null)) throw new RuntimeException('Incomplete review collection.');
        foreach ($page['nodes'] as $comment) {
            $id=$comment['id'] ?? null;
            if (!is_string($id) || $id==='' || isset($ids[$id])) throw new RuntimeException('Invalid or duplicate review comment.');
            $ids[$id]=true;
            if (count($ids)>1000) throw new RuntimeException('Comment limit exceeded.');
            $body=$comment['body'] ?? null;
            if (!is_string($body)) throw new RuntimeException('Missing comment body.');
            $author=$comment['user']['id'] ?? null;
            if (in_array($author,$config['REVIEWER_IDS'],true) && preg_match('/^eramba-corrective-action-reviewed: ([a-f0-9]{64})$/D',trim($body),$m)) {
                $reviews[]=['id'=>$id,'author'=>$author,'hash'=>$m[1],'createdAt'=>$comment['createdAt'] ?? null,'updatedAt'=>$comment['updatedAt'] ?? null];
            }
        }
        if (!$page['pageInfo']['hasNextPage']) return $reviews;
        $after=$page['pageInfo']['endCursor'] ?? null;
        if (!is_string($after) || $after==='' || isset($cursors[$after]) || !$page['nodes']) throw new RuntimeException('Invalid review pagination.');
        $cursors[$after]=true;
    } while (count($cursors)<100);
    throw new RuntimeException('Review page limit exceeded.');
}

function timestamp(mixed $value): ?int
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T.*(?:Z|[+-]\d{2}:?\d{2})$/D',$value)) return null;
    $p=date_parse($value); $t=strtotime($value);
    return $t!==false && $p['warning_count']===0 && $p['error_count']===0 ? $t : null;
}

function assessIssues(array $issues, array $config, int $now): array
{
    $rows=[]; $pending=false; $open=0; $closed=0; $overdue=0; $historical=0;
    $periodStart=(new DateTimeImmutable(gmdate('Y-m-01',$now).'T00:00:00Z'))->modify('-1 month')->getTimestamp();
    $zone=new DateTimeZone($config['DEADLINE_TIMEZONE']);
    foreach ($issues as $issue) {
        $key=$issue['identifier'] ?? null;
        if (!is_string($key) || $key==='' || ($issue['team']['id'] ?? null)!==$config['TEAM_ID']) throw new RuntimeException('Invalid issue identity.');
        $state=$issue['state']['type'] ?? null;
        $complete=$state==='completed';
        $created=timestamp($issue['createdAt'] ?? null);
        $completed=timestamp($issue['completedAt'] ?? null);
        if ($complete && $created!==null && $completed!==null && $completed>=$created && $completed<$periodStart) { $historical++; continue; }
        if ($complete) $closed++; else $open++;
        $known=in_array($state,['triage','backlog','unstarted','started','completed'],true);
        if (!$known || (!$complete && !empty($issue['archivedAt']))) $pending=true;
        $rows[]=result('register','',$key,$known && ($issue['hasDescription'] ?? false)===true && $created!==null && $created<=$now,
            'State='.json_encode($state).'; description present='.json_encode($issue['hasDescription'] ?? false).'; created='.json_encode($issue['createdAt'] ?? null).'.');
        if (!$complete) {
            $owner=$issue['assignee']['id'] ?? null;
            $due=$issue['dueDate'] ?? null;
            $date=is_string($due)?DateTimeImmutable::createFromFormat('!Y-m-d',$due,$zone):false;
            $valid=$date!==false && $date->format('Y-m-d')===$due;
            $deadline=$valid?$date->modify('+1 day')->getTimestamp():null;
            $rows[]=result('owner_and_target','',$key,is_string($owner) && $owner!=='' && $deadline!==null && $created!==null && $deadline>$created,
                'Owner='.json_encode($owner).'; target date='.json_encode($due).'; timezone='.$config['DEADLINE_TIMEZONE'].'.');
            if ($deadline!==null && $now>=$deadline) {
                $overdue++;
                // Methodology asks to document overdue items, not impose an additional automatic failure rule.
                $rows[]=result('overdue','',$key,true,'OVERDUE: target date '. $due .'. Follow up with the assigned owner.');
            }
        } else {
            $rows[]=result('closure_date','',$key,$created!==null && $completed!==null && $completed>=$created && $completed<=$now,
                'Completed at='.json_encode($issue['completedAt'] ?? null).'.');
        }
        $review=null;
        foreach ($issue['reviews'] ?? [] as $r) {
            $t=timestamp($r['updatedAt'] ?? null); $first=timestamp($r['createdAt'] ?? null);
            if (in_array($r['author'] ?? null,$config['REVIEWER_IDS'],true) && ($r['hash'] ?? null)===($issue['reviewHash'] ?? '')
                && $first!==null && $t!==null && $created!==null && $first>=$created && $t>=$first && $t<=$now
                && (!$complete || ($completed!==null && $t>=$completed))) $review=$r;
        }
        if ($review===null) $pending=true;
        $rows[]=result('evidence_review','',$key,$review!==null,$review!==null
            ? 'Authorized review comment='.$review['id'].'; reviewer='.$review['author'].'; reviewed='.$review['updatedAt'].'.'
            : 'PENDING: verify description, action record, root-cause analysis for significant nonconformities and effectiveness evidence for closed items. Review fingerprint: '.($issue['reviewHash'] ?? 'missing').'.');
    }
    if (!$issues) $pending=true;
    $rows[]=result('population','','Register',count($issues)>0,count($issues).' records including archives; '.$open.' open/unresolved, '.$closed.' completed in period; '.$historical.' earlier completions outside period.');
    $failed=count(array_filter($rows,fn($r)=>!$r['passed']));
    $lines=[sprintf('%s v%s at %s UTC.',AUTOMATION_ID,AUTOMATION_VERSION,gmdate('Y-m-d H:i',$now)),
        'Scope: team '.$config['TEAM_ID'].'; label '.$config['REGISTER_LABEL'].'; all open items and closures since '.gmdate('Y-m-d',$periodStart).' UTC.',
        $open.' open/unresolved; '.$closed.' completed; '.$overdue.' overdue. Overdue items are documented, not an additional failure threshold.',
        'Result: '.($pending?'PENDING MANUAL REVIEW':($failed?'FAILED':'PASSED')).'.'];
    foreach (array_slice(array_filter($rows,fn($r)=>!$r['passed']),0,$config['MAX_LOG_ITEMS']) as $r) $lines[]=substr($r['resource'].': '.$r['detail'],0,400);
    if (strlen(json_encode($rows,JSON_THROW_ON_ERROR))>600000) throw new RuntimeException('Evidence size limit exceeded.');
    return ['results'=>$rows,'outcome'=>['passed'=>!$pending && $failed===0,'pending'=>$pending,'conclusion'=>implode("\n",$lines)]];
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

    logStep(2, 'Collecting Linear evidence');
    $issues = collectIssues($secrets, $config);

    logStep(3, 'Evaluating');
    $review = assessIssues($issues, $config, time());
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
