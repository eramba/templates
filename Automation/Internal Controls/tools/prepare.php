<?php
// Prepares an automation for a local run the way eramba does:
//  - replaces %SECRET_<name>% and %SECURITYSERVICEAUDIT_ID% macros,
//  - inserts the includes right after "<?php declare(strict_types=1);" (eramba's CodeRunner does the same;
//    anything before the declare makes PHP fail, exactly as in eramba),
//  - optionally overrides $config values.
//
// Usage: php prepare.php <run.php> <out.php> <autoload.php|-> [--secret name=value]... [--audit 42] [--set KEY=php_literal]...
//   php prepare.php run.php /tmp/t.php vendor/autoload.php --secret aws_access_key_id=AKIATEST --set "REGIONS=['eu-west-1']"

$args = $argv;
array_shift($args);
[$in, $out, $autoload] = array_splice($args, 0, 3);
$code = file_get_contents($in);
$audit = '42';

for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '--secret') {
        [$name, $value] = explode('=', $args[++$i], 2);
        $code = str_replace("%SECRET_$name%", $value, $code);
    } elseif ($args[$i] === '--audit') {
        $audit = $args[++$i];
    } elseif ($args[$i] === '--set') {
        [$key, $literal] = explode('=', $args[++$i], 2);
        $code = preg_replace("/('" . preg_quote($key, '/') . "'\s*=>\s*)[^\n]*?,(\s*\/\/|\n)/", '${1}' . addcslashes($literal, '\\$') . ',$2', $code, 1, $n);
        if (!$n) {
            fwrite(STDERR, "Variable $key not found in \$config\n");
            exit(1);
        }
    }
}
$code = str_replace('%SECURITYSERVICEAUDIT_ID%', $audit, $code);

$includes = "\n/** Included by test harness */\n"
    . ($autoload !== '-' ? "require_once " . var_export(realpath($autoload), true) . ";\n" : '')
    . "require_once " . var_export(__DIR__ . '/eramba-stubs.php', true) . ";\n";
$rest = substr($code, 5);
$code = preg_match('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/', $rest, $m)
    ? '<?php' . $m[0] . $includes . substr($rest, strlen($m[0]))
    : '<?php' . $includes . $rest;
file_put_contents($out, $code);
echo "Prepared $out\n";
