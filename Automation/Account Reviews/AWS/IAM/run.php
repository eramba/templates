<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  AWS IAM – Accounts
 *  Technology: AWS Identity and Access Management (one AWS account)
 *  id: aws-iam-account-review        version: 0.1.1 (draft)
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Account%20Reviews
 *
 *  Composer packages (paste in the automation's composer field):
 *    aws/aws-sdk-php:^3.398
 * ============================================================================
 *
 *  Output
 *    STDOUT  Reserved for the Account Review artifact declaration written by
 *            writeAccountReviewArtifact(). Nothing else is ever printed there.
 *    STDERR  Step-by-step log with counts only, and technical errors.
 *            Account Review feeds accept STDERR when the run exits 0.
 *
 *  Exit codes
 *    0  Complete account list collected and published.
 *    1  Technical error (credentials, network, permissions, limits). Nothing
 *       is published, so the pull fails instead of returning a partial list.
 *
 *  Read-only: the script never changes AWS and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
$secrets = [
    'AWS_ACCESS_KEY_ID' => '%SECRET_aws_access_key_id%', // Same names as the AWS control automations
    'AWS_SECRET_ACCESS_KEY' => '%SECRET_aws_secret_access_key%',
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'UserName', // Account identifier: UserName, Arn or UserId (no fallback)
    'ROLES' => ['groups', 'policies', 'credentials'], // Access listed as roles; [] = accounts only (README §7)
    'INCLUDE_ROOT' => true, // Also list the account root user and its credentials
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum IAM users and groups; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'aws-iam-account-review';
const AUTOMATION_VERSION = '0.1.1';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout

// ─── 4. SHARED CODE (copied from Automation/_shared, do not edit here) ──────
// ─── shared:core v2 (edit Automation/_shared/core.php, then run Automation/tools/sync.php) ───
/**
 * Turns every PHP warning or notice into an exception, so it fails the run
 * instead of being printed on stdout (where it would corrupt a feed or hide a problem).
 * Call it first in MAIN.
 */
function tpl_installErrorHandler(): void
{
    ini_set('display_errors', 'stderr');
    set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
}

/** Step log. The script chooses the stream: 'stdout' for control audits, 'stderr' for feeds. */
function tpl_logStep(int $n, string $title): void
{
    tpl_log(sprintf("\n[%s] STEP %d: %s", gmdate('H:i:s'), $n, $title));
}

function tpl_logInfo(string $message): void
{
    tpl_log('  ' . $message);
}

/**
 * eramba keeps only the first 10,000 bytes of a run's output, so step lines stop after 8,000 bytes
 * and the rest is left for the result. $keep writes the line regardless (the final result).
 */
function tpl_log(string $line, bool $keep = false): void
{
    static $bytes = 0;
    static $shortened = false;
    $text = $line . "\n";
    if (!$keep && $bytes + strlen($text) > 8000) {
        if ($shortened) {
            return;
        }
        $shortened = true;
        $text = "  [Further steps not logged: eramba keeps only the first 10,000 bytes of output.]\n";
    }
    $bytes += strlen($text);
    fwrite(TPL_LOG_STREAM === 'stdout' ? STDOUT : STDERR, $text);
}

/** Seconds since the first call. MAIN calls it once at start. */
function tpl_elapsed(): float
{
    static $start = null;
    $start ??= microtime(true);
    return microtime(true) - $start;
}

/** Seconds left of TPL_RUN_SECONDS; throws when the time or memory budget is spent. */
function tpl_remaining(): float
{
    $remaining = TPL_RUN_SECONDS - tpl_elapsed();
    if ($remaining <= 0) {
        throw new RuntimeException('Time budget exceeded; narrow the scope.');
    }
    if (memory_get_usage(true) > 48 * 1024 * 1024) {
        throw new RuntimeException('Memory budget exceeded; narrow the scope.');
    }
    return $remaining;
}

/** A secret that eramba did not replace is still "%SECRET_<name>%": it does not exist. */
function tpl_checkSecrets(array $secrets): void
{
    foreach ($secrets as $key => $value) {
        if (!is_string($value) || $value === '' || preg_match('/^%SECRET_([^%]+)%$/', $value, $m) === 1) {
            $secretName = $m[1] ?? strtolower((string) $key);
            throw new RuntimeException(
                "Secret '$secretName' is missing: create it with exactly that name in Settings / Application Configuration / Automation Secrets (see README §6)."
            );
        }
    }
}

/** Secrets that may contain quotes or backslashes are stored base64-encoded (encoding, not encryption). */
function tpl_base64Secret(string $value, string $name): string
{
    $decoded = base64_decode($value, true);
    if ($decoded === false || $decoded === '' || strlen($decoded) > 8192 || base64_encode($decoded) !== $value) {
        throw new RuntimeException("Secret '$name' is not valid single-line base64.");
    }
    return $decoded;
}
// ─── end shared:core v2 sha256:54b06b7248971a34 ───
// ─── shared:providers/aws v1 (edit Automation/_shared/providers/aws.php, then run Automation/tools/sync.php) ───
/** Client options for one region: static access keys, bounded timeouts, the SDK's standard retries. */
function tpl_awsOptions(string $accessKeyId, string $secretAccessKey, string $region): array
{
    if (preg_match('/^(AKIA|ASIA)[A-Z0-9]{12,124}$/D', $accessKeyId) !== 1 || strlen($secretAccessKey) < 20) {
        throw new RuntimeException('The AWS access key ID or secret access key is invalid.');
    }
    if (preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D', $region) !== 1) {
        throw new RuntimeException('Invalid AWS region.');
    }
    return [
        'version' => 'latest',
        'region' => $region,
        'credentials' => ['key' => $accessKeyId, 'secret' => $secretAccessKey],
        'http' => ['connect_timeout' => 5, 'timeout' => 20],
        'retries' => 3,
    ];
}

/** One API call. AWS errors are reduced to their code: requests and responses may contain credentials. */
function tpl_awsCall(object $client, string $operation, array $args = []): array
{
    tpl_remaining();
    try {
        return $client->$operation($args)->toArray();
    } catch (\Aws\Exception\AwsException $e) {
        throw new RuntimeException($operation . ': AWS ' . ($e->getAwsErrorCode() ?: 'request failed') . '; check permissions and credentials.');
    }
}

/**
 * Every page of an IAM-style list (Marker / IsTruncated). Returns the merged lists named in $keys,
 * e.g. tpl_awsPages($iam, 'getAccountAuthorizationDetails', ['Filter' => ['User']], ['UserDetailList'], 2000).
 */
function tpl_awsPages(object $client, string $operation, array $args, array $keys, int $limit): array
{
    $lists = array_fill_keys($keys, []);
    $markers = [];
    do {
        if (count($markers) >= 200) {
            throw new RuntimeException("$operation exceeds 200 pages; narrow the scope.");
        }
        $page = tpl_awsCall($client, $operation, $args);
        foreach ($keys as $key) {
            if (!is_array($page[$key] ?? [])) {
                throw new RuntimeException("$operation returned an unexpected $key.");
            }
            array_push($lists[$key], ...($page[$key] ?? []));
            if (count($lists[$key]) > $limit) {
                throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
            }
        }
        $truncated = ($page['IsTruncated'] ?? false) === true;
        if ($truncated) {
            $marker = $page['Marker'] ?? null;
            if (!is_string($marker) || $marker === '' || isset($markers[$marker])) {
                throw new RuntimeException("$operation pagination marker is missing or repeats.");
            }
            $markers[$marker] = true;
            $args['Marker'] = $marker;
        }
    } while ($truncated);
    return $lists;
}
// ─── end shared:providers/aws v1 sha256:4fa124f9a6f94e04 ───
// ─── shared:feed v6 (edit Automation/_shared/feed.php, then run Automation/tools/sync.php) ───
/** A required feed value: trimmed, valid UTF-8, at most 255 bytes (the eramba field size). */
function tpl_feedText(mixed $value, string $what): string
{
    if (!is_string($value) || preg_match('//u', $value) !== 1) {
        throw new RuntimeException("$what is missing or not valid text.");
    }
    $value = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value));
    if ($value === '') {
        throw new RuntimeException("$what is missing.");
    }
    if (strlen($value) > 255) {
        throw new RuntimeException("$what is longer than 255 bytes.");
    }
    return $value;
}

/**
 * The role kinds a script collects, from its ROLES variable. Empty means accounts only: reviews then
 * compare which accounts exist (added, deleted, creeping accounts) and never report role changes.
 * Returns the selection as a set, e.g. ['groups' => true].
 */
function tpl_roleKinds(mixed $selected, array $allowed): array
{
    if (!is_array($selected) || !array_is_list($selected) || count($selected) !== count(array_unique($selected))
        || array_diff($selected, $allowed) !== []) {
        throw new RuntimeException('ROLES must be a list chosen from: ' . implode(', ', $allowed) . ' (or empty for accounts only).');
    }
    return array_fill_keys($selected, true);
}

/**
 * One role string: "<kind>: <name> [<id>]". The ID is what keeps two roles with the same name apart,
 * so only the human-readable name is shortened (at a UTF-8 boundary) when the string would exceed 255 bytes.
 */
function tpl_roleString(string $kind, mixed $name, string $id): string
{
    $name = tpl_feedText($name, "$kind name");
    $fixed = strlen($kind) + strlen($id) + 5; // ": " + " [" + "]"
    if ($fixed + 3 > 255) {
        throw new RuntimeException("$kind identifier is too long for a role value.");
    }
    if ($fixed + strlen($name) > 255) {
        $name = mb_strcut($name, 0, 255 - $fixed - 3, 'UTF-8') . '…';
    }
    return sprintf('%s: %s [%s]', $kind, $name, $id);
}

/**
 * The key eramba matches between pulls and between feeds. eramba compares keys ignoring case,
 * so two rows must never share a key ignoring case.
 */
function tpl_feedKey(mixed $value, array &$seen, bool $allowSpaces = false): string
{
    $key = tpl_feedText($value, 'Account key');
    if (!$allowSpaces && preg_match('/\s/u', $key) === 1) {
        throw new RuntimeException('An account key contains spaces; choose another key field.');
    }
    $folded = mb_strtolower($key, 'UTF-8');
    if (isset($seen[$folded])) {
        throw new RuntimeException('Two accounts share the same key (ignoring case); choose a unique key field.');
    }
    $seen[$folded] = true;
    return $key;
}

/**
 * Accounts are staged in a private temporary file and published once, after the whole population
 * has been collected: a partial list must never reach eramba (a differential review would report
 * the missing accounts as removed).
 */
function tpl_feedOpen()
{
    $feed = tmpfile();
    if ($feed === false) {
        throw new RuntimeException('Private staging file is unavailable.');
    }
    return $feed;
}

/** Adds one row. $roles may be empty. Returns the staged size in bytes. */
function tpl_feedAdd($feed, string $user, string $description, array $roles): int
{
    static $bytes = 0;
    $roles = array_values(array_unique($roles));
    sort($roles, SORT_STRING);
    $line = json_encode(['user' => $user, 'description' => $description, 'roles' => $roles],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $bytes += strlen($line);
    if ($bytes > 8 * 1024 * 1024 || fwrite($feed, $line) !== strlen($line)) {
        throw new RuntimeException('Feed larger than 8 MiB or staging write failed; narrow the scope.');
    }
    tpl_feedStats($roles);
    return $bytes;
}

/**
 * Running counts of the staged rows for the run summary: rows, rows without roles and roles per kind
 * (the "<kind>" of tpl_roleString). Called with the roles of each new row; without arguments it only reads.
 */
function tpl_feedStats(?array $roles = null): array
{
    static $stats = ['rows' => 0, 'without roles' => 0, 'kinds' => []];
    if ($roles !== null) {
        $stats['rows']++;
        $stats['without roles'] += $roles === [] ? 1 : 0;
        foreach ($roles as $role) {
            $kind = strstr($role, ': ', true);
            $kind = $kind === false ? 'Other' : $kind;
            $stats['kinds'][$kind] = ($stats['kinds'][$kind] ?? 0) + 1;
        }
    }
    return $stats;
}

/** Publishes every staged row through eramba's Account Review artifact helper. */
function tpl_feedPublish($feed): void
{
    if (!function_exists('writeAccountReviewArtifact')) {
        throw new RuntimeException('writeAccountReviewArtifact() is unavailable; create the automation from the Account Review feed form.');
    }
    if (!fflush($feed) || !rewind($feed)) {
        throw new RuntimeException('Could not finalize the staged feed.');
    }
    writeAccountReviewArtifact((static function () use ($feed): Generator {
        while (($line = fgets($feed)) !== false) {
            yield json_decode($line, true, 64, JSON_THROW_ON_ERROR);
        }
        if (!feof($feed)) {
            throw new RuntimeException('Could not read the complete staged feed.');
        }
    })());
}

/**
 * Thrown when the provider returns no rows and the script must not publish an empty list. It is not an
 * error of the run: tpl_logFailure() reports it as NOT PUBLISHED, with the reason in the message.
 */
final class TplEmptyFeedException extends RuntimeException
{
}

/**
 * Ends a successful run with a block people can read at a glance in eramba's Test output.
 * $what names the rows ("accounts", "current employees"); $details adds script-specific counts.
 * Counts only, never names: the output may be shared or screenshotted.
 */
function tpl_logSummary(string $what, array $details = []): void
{
    $stats = tpl_feedStats();
    tpl_log("\n──────────── SUMMARY ────────────", true);
    tpl_log(sprintf('RESULT: OK. Published the complete list of %d %s.', $stats['rows'], $what), true);
    foreach ($details as $detail) {
        tpl_log('  ' . $detail, true);
    }
    if ($stats['kinds'] !== []) {
        ksort($stats['kinds'], SORT_STRING);
        $kinds = [];
        foreach ($stats['kinds'] as $kind => $count) {
            $kinds[] = "$count $kind";
        }
        tpl_log('  Roles listed: ' . implode(', ', $kinds) . '.', true);
        tpl_log(sprintf('  %s without any role: %d.', ucfirst($what), $stats['without roles']), true);
    }
}

/** Ends a run that published nothing: eramba records a failed pull and keeps the last successful one. */
function tpl_logFailure(string $what, Throwable $e): void
{
    tpl_log("\n──────────── SUMMARY ────────────", true);
    if ($e instanceof TplEmptyFeedException) {
        tpl_log("RESULT: NOT PUBLISHED. The provider returned an empty list, so no $what was sent. REASON: " . $e->getMessage(), true);
        return;
    }
    tpl_log("RESULT: FAILED. No $what was published; eramba keeps the last successful pull. ERROR: " . $e->getMessage(), true);
}
// ─── end shared:feed v6 sha256:124b2c93fef309f8 ───

// ─── 5. COLLECT: AWS IAM ────────────────────────────────────────────────────

const ROLE_KINDS = ['groups', 'policies', 'credentials'];

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['UserName', 'Arn', 'UserId'], true)) {
        throw new RuntimeException('USER_KEY must be UserName, Arn or UserId.');
    }
    foreach (['INCLUDE_ROOT', 'ALLOW_EMPTY_FEED'] as $key) {
        if (!is_bool($c[$key])) {
            throw new RuntimeException("$key must be true or false.");
        }
    }
    if (!is_int($c['MAX_USERS']) || $c['MAX_USERS'] < 1 || $c['MAX_USERS'] > 5000) {
        throw new RuntimeException('MAX_USERS must be a number from 1 to 5000.');
    }
}

/** IAM credential report rows keyed by user name (includes "<root_account>"). */
function credentialReport(object $iam): array
{
    for ($attempt = 0; ($state = tpl_awsCall($iam, 'generateCredentialReport')['State'] ?? '') !== 'COMPLETE'; $attempt++) {
        if ($attempt >= 10 || !in_array($state, ['STARTED', 'INPROGRESS'], true)) {
            throw new RuntimeException('The IAM credential report was not generated in time; retry later.');
        }
        sleep(2);
    }
    $csv = tpl_awsCall($iam, 'getCredentialReport')['Content'] ?? null;
    if (!is_string($csv) || $csv === '') {
        throw new RuntimeException('The IAM credential report is empty.');
    }
    $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", $csv)), fn ($l) => $l !== ''));
    $header = str_getcsv(array_shift($lines), ',', '"', '');
    foreach (['user', 'password_enabled', 'mfa_active', 'access_key_1_active', 'access_key_2_active'] as $column) {
        if (!in_array($column, $header, true)) {
            throw new RuntimeException("The IAM credential report has no '$column' column.");
        }
    }
    $rows = [];
    foreach ($lines as $line) {
        $values = str_getcsv($line, ',', '"', '');
        if (count($values) !== count($header)) {
            throw new RuntimeException('The IAM credential report is malformed.');
        }
        $row = array_combine($header, $values);
        $rows[$row['user']] = $row;
    }
    return $rows;
}

/** Console password, active access keys and MFA from one credential report row. */
function credentials(array $report): array
{
    $roles = [];
    if ($report['password_enabled'] === 'true') {
        $roles[] = tpl_roleString('Credential', 'Console password', 'password');
    }
    foreach ([1, 2] as $n) {
        if ($report["access_key_{$n}_active"] === 'true') {
            $roles[] = tpl_roleString('Credential', 'Access key', "key $n");
        }
    }
    return [$roles, $report['mfa_active'] === 'true' ? 'MFA' : 'no MFA'];
}

function policyName(array $policy): mixed
{
    return $policy['PolicyName'] ?? null;
}

function collectFeed(object $iam, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $details = tpl_awsPages($iam, 'getAccountAuthorizationDetails', ['Filter' => ['User', 'Group'], 'MaxItems' => 1000],
        ['UserDetailList', 'GroupDetailList'], $config['MAX_USERS']);
    $groups = [];
    foreach ($details['GroupDetailList'] as $group) {
        $name = tpl_feedText($group['GroupName'] ?? null, 'Group name');
        $arn = tpl_feedText($group['Arn'] ?? null, 'Group ARN');
        $roles = isset($kinds['groups']) ? [tpl_roleString('Group', $name, $arn)] : [];
        foreach (isset($kinds['policies']) ? $group['AttachedManagedPolicies'] ?? [] : [] as $policy) {
            $roles[] = tpl_roleString('Group policy', $name . ' / ' . tpl_feedText(policyName($policy), 'Policy name'), tpl_feedText($policy['PolicyArn'] ?? null, 'Policy ARN'));
        }
        foreach (isset($kinds['policies']) ? $group['GroupPolicyList'] ?? [] : [] as $policy) {
            $roles[] = tpl_roleString('Group inline policy', $name . ' / ' . tpl_feedText(policyName($policy), 'Policy name'), 'inline');
        }
        $groups[$name] = $roles;
    }
    tpl_logInfo(count($details['UserDetailList']) . ' users and ' . count($groups) . ' groups found.');

    $report = credentialReport($iam);
    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    $count = 0;
    foreach ($details['UserDetailList'] as $user) {
        $name = tpl_feedText($user['UserName'] ?? null, 'User name');
        $roles = [];
        foreach ($user['GroupList'] ?? [] as $groupName) {
            if (!isset($groups[$groupName])) {
                throw new RuntimeException('A user belongs to a group that was not collected; IAM changed during the pull, retry later.');
            }
            array_push($roles, ...$groups[$groupName]);
        }
        foreach (isset($kinds['policies']) ? $user['AttachedManagedPolicies'] ?? [] : [] as $policy) {
            $roles[] = tpl_roleString('Policy', policyName($policy), tpl_feedText($policy['PolicyArn'] ?? null, 'Policy ARN'));
        }
        foreach (isset($kinds['policies']) ? $user['UserPolicyList'] ?? [] : [] as $policy) {
            $roles[] = tpl_roleString('Inline policy', policyName($policy), 'inline');
        }
        if (isset($kinds['policies'], $user['PermissionsBoundary']['PermissionsBoundaryArn'])) {
            $arn = tpl_feedText($user['PermissionsBoundary']['PermissionsBoundaryArn'], 'Boundary ARN');
            $roles[] = tpl_roleString('Permissions boundary', substr(strrchr($arn, '/') ?: $arn, 1) ?: $arn, $arn);
        }
        if (!isset($report[$name])) {
            throw new RuntimeException('A user is missing from the credential report; IAM changed during the pull, retry later.');
        }
        [$credentialRoles, $mfa] = credentials($report[$name]);
        if (isset($kinds['credentials'])) {
            array_push($roles, ...$credentialRoles);
        }
        $created = ($user['CreateDate'] ?? null) instanceof DateTimeInterface ? $user['CreateDate']->format('Y-m-d') : '-';
        $userId = tpl_feedText($user['UserId'] ?? null, 'User ID');
        $key = tpl_feedKey($user[$config['USER_KEY']] ?? null, $keys);
        $description = sprintf('%s%s; %s; created %s; id=%s', $user['Path'] ?? '/', mb_strcut($name, 0, 120, 'UTF-8'), $mfa, $created, $userId);
        $bytes = tpl_feedAdd($feed, $key, tpl_feedText($description, 'Description'), $roles);
        $count++;
    }
    if ($config['INCLUDE_ROOT']) {
        if (!isset($report['<root_account>'])) {
            throw new RuntimeException('The credential report has no root account row.');
        }
        [$credentialRoles, $mfa] = credentials($report['<root_account>']);
        $credentialRoles = isset($kinds['credentials']) ? $credentialRoles : [];
        $key = tpl_feedKey($config['USER_KEY'] === 'Arn' ? ($report['<root_account>']['arn'] ?? null) : '<root_account>', $keys);
        $bytes = tpl_feedAdd($feed, $key, "AWS account root user; $mfa", $credentialRoles);
        $count++;
    }
    if ($count === 0 && !$config['ALLOW_EMPTY_FEED']) {
        throw new TplEmptyFeedException('No accounts found; set ALLOW_EMPTY_FEED if zero accounts is intended.');
    }
    tpl_logInfo(sprintf('%d accounts staged (%d bytes).', $count, $bytes));
    return $feed;
}

// ─── 6. MAIN ────────────────────────────────────────────────────────────────
tpl_installErrorHandler();
tpl_elapsed();
try {
    tpl_log(sprintf('%s v%s', AUTOMATION_ID, AUTOMATION_VERSION));

    tpl_logStep(1, 'Checking configuration');
    validateConfig($config);
    tpl_checkSecrets($secrets);
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Collecting IAM users, groups, policies and credentials');
    // IAM is a global service; its endpoint is in us-east-1.
    $iam = new \Aws\Iam\IamClient(tpl_awsOptions($secrets['AWS_ACCESS_KEY_ID'], $secrets['AWS_SECRET_ACCESS_KEY'], 'us-east-1'));
    $feed = collectFeed($iam, $config);

    tpl_logStep(3, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
