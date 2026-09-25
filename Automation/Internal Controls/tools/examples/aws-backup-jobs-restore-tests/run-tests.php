<?php
// Usage: php run-tests.php <path/to/run.php> <path/to/vendor/autoload.php>
// Runs the AWS Backup automation against fake-aws.php with one scenario per test.
$SCRIPT = $argv[1] ?? die("usage: php run-tests.php <run.php> <autoload.php>\n");
$AUTO = realpath($argv[2] ?? die("usage: php run-tests.php <run.php> <autoload.php>\n"));
$STUBS = dirname(__DIR__, 2) . '/eramba-stubs.php';
$now = time(); $h = 3600;
$vol = fn($n) => "arn:aws:ec2:eu-west-1:111111111111:volume/vol-$n";
$rds = "arn:aws:rds:eu-west-1:111111111111:db:prod-db";
$job = fn($id,$arn,$type,$state,$agoH,$msg='') => ['BackupJobId'=>$id,'ResourceArn'=>$arn,'ResourceType'=>$type,'State'=>$state,'StatusMessage'=>$msg,'CreationDate'=>$now-$agoH*$h,'CompletionDate'=>$now-$agoH*$h+600,'BackupSizeInBytes'=>1073741824,'BackupVaultArn'=>'arn:aws:backup:eu-west-1:111111111111:backup-vault:Default'];
$res = fn($arn,$type,$agoH) => ['ResourceArn'=>$arn,'ResourceType'=>$type,'LastBackupTime'=>$now-$agoH*$h];
$rst = fn($id,$src,$type,$status,$agoH,$min,$val=null,$plan=true) => array_filter(['RestoreJobId'=>$id,'SourceResourceArn'=>$src,'ResourceType'=>$type,'Status'=>$status,'CreationDate'=>$now-$agoH*$h,'CompletionDate'=>$now-$agoH*$h+$min*60,'ValidationStatus'=>$val,'CreatedBy'=>$plan?['RestoreTestingPlanArn'=>'arn:aws:backup:eu-west-1:1:restore-testing-plan:p']:null], fn($v)=>$v!==null);

$base = [
  'BackupJobs' => [$job('j1',$vol(1),'EBS','COMPLETED',20), $job('j2',$rds,'RDS','COMPLETED',10)],
  'Results' => [$res($vol(1),'EBS',20), $res($rds,'RDS',10)],
  'RestoreJobs' => [$rst('r1',$rds,'RDS','COMPLETED',24*30,35,'SUCCESSFUL')],
];
$tests = [
 'pass_with_role' => [$base + ['external_id'=>'ext-1'], ['AWS_ROLE_ARN'=>"'arn:aws:iam::111111111111:role/eramba-audit'", 'AWS_EXTERNAL_ID'=>"'ext-1'"], [], 0, 'PASSED'],
 'fail_mixed' => [[
    'BackupJobs' => [$job('j1',$vol(1),'EBS','COMPLETED',20), $job('j3',$vol(2),'EBS','FAILED',30,'Snapshot limit exceeded'), $job('j2',$rds,'RDS','COMPLETED',10)],
    'Results' => [$res($vol(1),'EBS',20), $res($vol(2),'EBS',80), $res($rds,'RDS',10)],
    'RestoreJobs' => [$rst('r1',$vol(1),'EBS','COMPLETED',24*30,400,'SUCCESSFUL'), $rst('r2',$rds,'RDS','FAILED',24*10,0)],
  ], ['REQUIRED_RESTORE_RESOURCE_TYPES'=>"['RDS', 'EBS']"], [], 0, 'FAILED'],
 'recovered_failure_ignored' => [[
    'BackupJobs' => [$job('j3',$vol(1),'EBS','FAILED',40), $job('j1',$vol(1),'EBS','COMPLETED',20)],
    'Results' => [$res($vol(1),'EBS',20)],
    'RestoreJobs' => [$rst('r1',$vol(1),'EBS','COMPLETED',24*30,30)],
  ], ['IGNORE_RECOVERED_FAILURES'=>'true'], [], 0, 'PASSED'],
 'scope_type_filter' => [[
    'BackupJobs' => [$job('j1',$vol(1),'EBS','FAILED',20), $job('j2',$rds,'RDS','COMPLETED',10)],
    'Results' => [$res($vol(1),'EBS',200), $res($rds,'RDS',10)],
    'RestoreJobs' => [$rst('r1',$rds,'RDS','COMPLETED',24*30,30)],
  ], ['RESOURCE_TYPES'=>"['RDS']"], [], 0, 'PASSED'],
 'tag_filter' => [[
    'BackupJobs' => [$job('j1',$vol(1),'EBS','FAILED',20), $job('j2',$rds,'RDS','COMPLETED',10)],
    'Results' => [$res($vol(1),'EBS',200), $res($rds,'RDS',10)],
    'RestoreJobs' => [$rst('r1',$rds,'RDS','COMPLETED',24*30,30)],
    'tags' => [['ResourceARN'=>$rds,'Tags'=>[['Key'=>'Env','Value'=>'prod']]], ['ResourceARN'=>$vol(1),'Tags'=>[['Key'=>'Env','Value'=>'dev']]]],
  ], ['RESOURCE_TAGS'=>"['Env' => '/^prod$/']"], [], 0, 'PASSED'],
 'no_restore_tests' => [['BackupJobs'=>$base['BackupJobs'],'Results'=>$base['Results'],'RestoreJobs'=>[$rst('r9',$rds,'RDS','COMPLETED',24*5,20,null,false)]], ['ONLY_RESTORE_TESTING_PLANS'=>'true'], [], 0, 'FAILED'],
 'offsite_copy' => [$base + ['CopyJobs'=>[['ResourceArn'=>$rds,'State'=>'COMPLETED','SourceBackupVaultArn'=>'arn:aws:backup:eu-west-1:111111111111:backup-vault:Default','DestinationBackupVaultArn'=>'arn:aws:backup:eu-central-1:111111111111:backup-vault:DR']]], ['CHECK_OFFSITE_COPY'=>'true'], [], 0, 'FAILED'],
 'restore_no_completion_date' => [['BackupJobs'=>[$job('j1',$vol(1),'EBS','COMPLETED',20)],'Results'=>[$res($vol(1),'EBS',20)],'RestoreJobs'=>[array_diff_key($rst('r1',$vol(1),'EBS','COMPLETED',24*30,30),['CompletionDate'=>1])]], [], [], 0, 'FAILED'],
 'no_protected_resources' => [['BackupJobs'=>[$job('j1',$vol(1),'EBS','COMPLETED',20)],'Results'=>[],'RestoreJobs'=>[$rst('r1',$vol(1),'EBS','COMPLETED',24*20,30)]], [], [], 0, 'FAILED'],
 'access_denied' => [$base + ['deny'=>['/backup-jobs/']], [], [], 1, null],
 'wrong_external_id' => [$base + ['external_id'=>'right'], ['AWS_ROLE_ARN'=>"'arn:aws:iam::1:role/r'", 'AWS_EXTERNAL_ID'=>"'wrong'"], [], 1, null],
 'missing_secret' => [$base, [], ['nosecret'=>1], 1, null],
 'eramba_edit_error' => [$base, [], ['ERAMBA_FAIL'=>'edit'], 1, 'PASSED'],
 'many_failures_output_size' => [['BackupJobs'=>array_map(fn($i)=>$job("j$i",$vol($i),'EBS','FAILED',5,'Some long AWS error message about snapshots and quotas'),range(1,600)),'Results'=>[],'RestoreJobs'=>[]], [], [], 0, 'FAILED'],
];
$port = 18080; $allOk = true;
foreach ($tests as $name => [$sc, $over, $opt, $expExit, $expRes]) {
    $dir = sys_get_temp_dir()."/t_$name"; @mkdir($dir);
    file_put_contents("$dir/sc.json", json_encode($sc));
    foreach (['req.log','eramba.log'] as $f) @unlink("$dir/$f");
    $code = file_get_contents($SCRIPT);
    if (!isset($opt['nosecret'])) $code = str_replace(['%SECRET_aws_access_key_id%','%SECRET_aws_secret_access_key%'], ['AKIATEST','secret'], $code);
    $code = str_replace('%SECURITYSERVICEAUDIT_ID%', '42', $code);
    foreach ($over as $k => $v) { $code = preg_replace("/('".preg_quote($k)."'\s*=>\s*)[^\n]*?,(\s*\/\/|\n)/", '${1}'.$v.',$2', $code, 1, $n); if(!$n) die("override $k not applied\n"); }
    // Mimic eramba CodeRunner::prepareCode(): includes go right after "<?php declare(strict_types=1);"
    $inc = "\n/** Included by Eramba */\nrequire_once '$AUTO';\nrequire_once '$STUBS';\n";
    $rest = substr($code, 5);
    $code = preg_match('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/', $rest, $mm)
        ? '<?php' . $mm[0] . $inc . substr($rest, strlen($mm[0])) : '<?php' . $inc . $rest;
    file_put_contents("$dir/script.php", $code);
    $srv = proc_open(['php','-S',"127.0.0.1:$port",__DIR__.'/fake-aws.php'], [1=>['file','/dev/null','w'],2=>['file','/dev/null','w']], $pp, null, ['SCENARIO_FILE'=>"$dir/sc.json",'REQ_LOG'=>"$dir/req.log"]);
    usleep(300000);
    $env = ['AWS_ENDPOINT_URL'=>"http://127.0.0.1:$port",'ERAMBA_LOG'=>"$dir/eramba.log",'PATH'=>getenv('PATH'),'AWS_EC2_METADATA_DISABLED'=>'true'] + (isset($opt['ERAMBA_FAIL'])?['ERAMBA_FAIL'=>$opt['ERAMBA_FAIL']]:[]);
    $p = proc_open(['php','-d','memory_limit=64M',"$dir/script.php"], [1=>['pipe','w'],2=>['pipe','w']], $pipes, $dir, $env);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); $exit = proc_close($p);
    proc_terminate($srv); proc_close($srv); $port++;
    $elog = @file_get_contents("$dir/eramba.log") ?: '';
    $gotRes = preg_match('/"security_service_audit_result_option_id":(\d)/', $elog, $m) ? ($m[1]=='2'?'PASSED':'FAILED') : null;
    $ok = $exit === $expExit && $gotRes === $expRes && ($expExit===1 ? $err!=='' : $err==='') && strlen($out) < 10000;
    $allOk = $allOk && $ok;
    printf("%-28s %s exit=%d result=%s stdout=%dB stderr=%s\n", $name, $ok?'OK  ':'FAIL', $exit, $gotRes??'-', strlen($out), trim(strtok($err, "\n")) ?: '-');
    file_put_contents("$dir/stdout.txt", $out);
}
echo $allOk ? "\nALL TESTS PASSED\n" : "\nSOME TESTS FAILED\n";
