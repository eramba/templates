<?php
// Fake AWS endpoint for tests. Scenario JSON in env SCENARIO_FILE.
$sc = json_decode(file_get_contents(getenv('SCENARIO_FILE')), true);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');
file_put_contents(getenv('REQ_LOG'), $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' '.($_SERVER['HTTP_X_AMZ_TARGET'] ?? '').' '.substr($body,0,200)."\n", FILE_APPEND);
if (str_contains($body, 'Action=AssumeRole')) {
    parse_str($body, $p);
    if (($sc['external_id'] ?? null) !== null && ($p['ExternalId'] ?? '') !== $sc['external_id']) {
        http_response_code(403); header('Content-Type: text/xml');
        echo '<ErrorResponse><Error><Type>Sender</Type><Code>AccessDenied</Code><Message>not authorized to perform sts:AssumeRole</Message></Error><RequestId>x</RequestId></ErrorResponse>'; exit;
    }
    header('Content-Type: text/xml');
    $exp = gmdate('Y-m-d\TH:i:s\Z', time()+900);
    echo "<AssumeRoleResponse xmlns=\"https://sts.amazonaws.com/doc/2011-06-15/\"><AssumeRoleResult><Credentials><AccessKeyId>ASIATEST</AccessKeyId><SecretAccessKey>s</SecretAccessKey><SessionToken>tok</SessionToken><Expiration>$exp</Expiration></Credentials><AssumedRoleUser><Arn>arn:aws:sts::1:assumed-role/r/s</Arn><AssumedRoleId>x:s</AssumedRoleId></AssumedRoleUser></AssumeRoleResult><ResponseMetadata><RequestId>x</RequestId></ResponseMetadata></AssumeRoleResponse>";
    exit;
}
header('Content-Type: application/json');
if (($_SERVER['HTTP_X_AMZ_TARGET'] ?? '') === 'ResourceGroupsTaggingAPI_20170126.GetResources') {
    echo json_encode(['ResourceTagMappingList' => $sc['tags'] ?? []]); exit;
}
if (in_array($path, $sc['deny'] ?? [], true)) {
    http_response_code(403); header('x-amzn-ErrorType: AccessDeniedException');
    echo json_encode(['Message' => 'User is not authorized to perform: backup:ListBackupJobs']); exit;
}
$map = ['/backup-jobs/' => 'BackupJobs', '/resources/' => 'Results', '/restore-jobs/' => 'RestoreJobs', '/copy-jobs/' => 'CopyJobs'];
if (isset($map[$path])) { echo json_encode([$map[$path] => $sc[$map[$path]] ?? []]); exit; }
http_response_code(404); echo '{"Message":"unknown '.$path.'"}';
