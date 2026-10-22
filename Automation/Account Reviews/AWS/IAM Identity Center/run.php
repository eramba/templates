<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  AWS IAM Identity Center – Accounts
 *  Technology: AWS IAM Identity Center (one organization instance)
 *  id: aws-identity-center-account-review        version: 0.1.1 (draft)
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
    'REGION' => '', // Region where IAM Identity Center is enabled, e.g. 'eu-west-1' (required)
    'INSTANCE_ARN' => '', // '' = the only organization instance; or 'arn:aws:sso:::instance/ssoins-...'
    'USER_KEY' => 'email', // Account identifier: email (primary email), UserName or UserId (no fallback)
    'ROLES' => ['groups', 'permission_sets'], // Access listed as roles; [] = accounts only (README §7)
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum users; never truncate
    'MAX_GROUPS' => 2000, // Maximum groups and maximum permission sets
    'MAX_ASSIGNMENTS' => 20000, // Maximum account assignments and permission set / account pairs
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'aws-identity-center-account-review';
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

// ─── 5. COLLECT: AWS IAM Identity Center ────────────────────────────────────

const ROLE_KINDS = ['groups', 'permission_sets'];
const PAGE_SIZE = 100; // Maximum MaxResults of the Identity Store and SSO Admin list operations

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!is_string($c['REGION']) || preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d+$/D', $c['REGION']) !== 1) {
        throw new RuntimeException("REGION must be the AWS region where IAM Identity Center is enabled, e.g. 'eu-west-1'.");
    }
    if (!is_string($c['INSTANCE_ARN'])
        || ($c['INSTANCE_ARN'] !== '' && preg_match('~^arn:aws[a-z-]*:sso:::instance/[A-Za-z0-9.-]{1,64}$~D', $c['INSTANCE_ARN']) !== 1)) {
        throw new RuntimeException("INSTANCE_ARN must be '' or an IAM Identity Center instance ARN.");
    }
    if (!in_array($c['USER_KEY'], ['email', 'UserName', 'UserId'], true)) {
        throw new RuntimeException('USER_KEY must be email, UserName or UserId.');
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    foreach (['MAX_USERS' => 5000, 'MAX_GROUPS' => 10000, 'MAX_ASSIGNMENTS' => 100000] as $key => $max) {
        if (!is_int($c[$key]) || $c[$key] < 1 || $c[$key] > $max) {
            throw new RuntimeException("$key must be a number from 1 to $max.");
        }
    }
}

/**
 * Every page of a NextToken list (Identity Store, SSO Admin). The shared tpl_awsPages() only knows
 * IAM's Marker / IsTruncated. Returns the merged list in $key; aborts on repeating tokens or limits.
 */
function tokenPages(object $client, string $operation, array $args, string $key, int $limit): array
{
    $items = [];
    $tokens = [];
    $args['MaxResults'] = PAGE_SIZE;
    while (true) {
        if (count($tokens) >= 1000) {
            throw new RuntimeException("$operation exceeds 1000 pages; narrow the scope.");
        }
        $page = tpl_awsCall($client, $operation, $args);
        if (!is_array($page[$key] ?? [])) {
            throw new RuntimeException("$operation returned an unexpected $key.");
        }
        array_push($items, ...array_values($page[$key] ?? []));
        if (count($items) > $limit) {
            throw new RuntimeException('Collection limit exceeded; narrow the scope or raise the limit.');
        }
        $token = $page['NextToken'] ?? null;
        if ($token === null || $token === '') {
            return $items;
        }
        if (!is_string($token) || isset($tokens[$token])) {
            throw new RuntimeException("$operation pagination token is invalid or repeats.");
        }
        $tokens[$token] = true;
        $args['NextToken'] = $token;
    }
}

/** The instance ARN and identity store ID: the configured instance, or the only organization instance. */
function identityCenterInstance(object $sso, string $instanceArn): array
{
    $instances = tokenPages($sso, 'listInstances', [], 'Instances', 100);
    $matches = array_values(array_filter($instances, static fn ($i): bool => is_array($i)
        && ($instanceArn === '' || ($i['InstanceArn'] ?? null) === $instanceArn)
        && ($i['Status'] ?? null) === 'ACTIVE'));
    if (count($matches) !== 1) {
        throw new RuntimeException($instanceArn === ''
            ? count($matches) . ' active IAM Identity Center instances visible in this region; set INSTANCE_ARN (or check REGION).'
            : 'INSTANCE_ARN was not found as an active instance in REGION.');
    }
    $arn = tpl_feedText($matches[0]['InstanceArn'] ?? null, 'Instance ARN');
    $storeId = tpl_feedText($matches[0]['IdentityStoreId'] ?? null, 'Identity store ID');
    return [$arn, $storeId];
}

/** The primary email: the one marked primary, or the only one. Null when the user has none. */
function primaryEmail(array $user): ?string
{
    $emails = array_values(array_filter($user['Emails'] ?? [], static fn ($e): bool => is_array($e) && is_string($e['Value'] ?? null)));
    $primary = array_values(array_filter($emails, static fn (array $e): bool => ($e['Primary'] ?? false) === true));
    $chosen = $primary[0] ?? (count($emails) === 1 ? $emails[0] : null);
    return $chosen === null ? null : trim($chosen['Value']);
}

/**
 * Account assignments inverted to principals: principal ID => list of "account / permission set" entries.
 * Lists the accounts each permission set is provisioned to, then the assignments of each pair.
 */
function permissionSetAssignments(object $sso, string $instanceArn, array $config): array
{
    $arns = tokenPages($sso, 'listPermissionSets', ['InstanceArn' => $instanceArn], 'PermissionSets', $config['MAX_GROUPS']);
    $byPrincipal = ['USER' => [], 'GROUP' => []];
    $pairs = 0;
    $assignments = 0;
    foreach ($arns as $arn) {
        $arn = tpl_feedText($arn, 'Permission set ARN');
        $set = tpl_awsCall($sso, 'describePermissionSet', ['InstanceArn' => $instanceArn, 'PermissionSetArn' => $arn])['PermissionSet'] ?? null;
        $name = tpl_feedText($set['Name'] ?? null, 'Permission set name');
        $accounts = tokenPages($sso, 'listAccountsForProvisionedPermissionSet',
            ['InstanceArn' => $instanceArn, 'PermissionSetArn' => $arn], 'AccountIds', $config['MAX_ASSIGNMENTS']);
        foreach ($accounts as $accountId) {
            if (!is_string($accountId) || preg_match('/^\d{12}$/D', $accountId) !== 1) {
                throw new RuntimeException('listAccountsForProvisionedPermissionSet returned an unexpected account ID.');
            }
            if (++$pairs > $config['MAX_ASSIGNMENTS']) {
                throw new RuntimeException('More permission set / account pairs than MAX_ASSIGNMENTS; raise the limit.');
            }
            $list = tokenPages($sso, 'listAccountAssignments',
                ['InstanceArn' => $instanceArn, 'AccountId' => $accountId, 'PermissionSetArn' => $arn],
                'AccountAssignments', $config['MAX_ASSIGNMENTS']);
            foreach ($list as $assignment) {
                $type = $assignment['PrincipalType'] ?? null;
                if (!isset($byPrincipal[$type]) || !is_string($assignment['PrincipalId'] ?? null) || $assignment['PrincipalId'] === '') {
                    throw new RuntimeException('An account assignment has an unexpected principal.');
                }
                if (++$assignments > $config['MAX_ASSIGNMENTS']) {
                    throw new RuntimeException('More account assignments than MAX_ASSIGNMENTS; raise the limit.');
                }
                $byPrincipal[$type][$assignment['PrincipalId']][] = ['name' => "$accountId / $name", 'arn' => $arn];
            }
        }
    }
    tpl_logInfo(sprintf('%d permission sets, %d provisioned account pairs and %d account assignments found.', count($arns), $pairs, $assignments));
    return $byPrincipal;
}

/** Group ID => user IDs, for the given groups only (each group is expanded once). */
function groupMembers(object $store, string $storeId, array $groupIds, array $config): array
{
    $members = [];
    $total = 0;
    foreach ($groupIds as $groupId) {
        $list = tokenPages($store, 'listGroupMemberships', ['IdentityStoreId' => $storeId, 'GroupId' => $groupId],
            'GroupMemberships', $config['MAX_USERS']);
        $members[$groupId] = [];
        foreach ($list as $membership) {
            $userId = $membership['MemberId']['UserId'] ?? null;
            if (!is_string($userId) || $userId === '') {
                throw new RuntimeException('A group membership has an unexpected member.');
            }
            $members[$groupId][] = $userId;
        }
        $total += count($list);
    }
    tpl_logInfo(sprintf('%d groups expanded, %d memberships.', count($members), $total));
    return $members;
}

function collectFeed(object $sso, object $store, string $instanceArn, string $storeId, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $users = tokenPages($store, 'listUsers', ['IdentityStoreId' => $storeId], 'Users', $config['MAX_USERS']);
    $userRoles = [];
    foreach ($users as $user) {
        $userRoles[tpl_feedText($user['UserId'] ?? null, 'User ID')] = [];
    }
    if (count($userRoles) !== count($users)) {
        throw new RuntimeException('listUsers returned the same user twice; retry later.');
    }
    tpl_logInfo(count($users) . ' users found.');

    if ($kinds !== []) {
        $groups = [];
        foreach (tokenPages($store, 'listGroups', ['IdentityStoreId' => $storeId], 'Groups', $config['MAX_GROUPS']) as $group) {
            $groups[tpl_feedText($group['GroupId'] ?? null, 'Group ID')] = tpl_feedText($group['DisplayName'] ?? null, 'Group name');
        }
        tpl_logInfo(count($groups) . ' groups found.');
        $assigned = isset($kinds['permission_sets']) ? permissionSetAssignments($sso, $instanceArn, $config) : ['USER' => [], 'GROUP' => []];
        foreach (array_keys($assigned['GROUP']) as $groupId) {
            if (!isset($groups[$groupId])) {
                throw new RuntimeException('A permission set is assigned to a group that was not collected; retry later.');
            }
        }
        // Expand every group for group roles; otherwise only the groups that hold account assignments.
        $expand = isset($kinds['groups']) ? array_keys($groups) : array_keys($assigned['GROUP']);
        foreach (groupMembers($store, $storeId, $expand, $config) as $groupId => $userIds) {
            foreach ($userIds as $userId) {
                if (!isset($userRoles[$userId])) {
                    throw new RuntimeException('A group member was not collected as a user; Identity Center changed during the pull, retry later.');
                }
                if (isset($kinds['groups'])) {
                    $userRoles[$userId][] = tpl_roleString('Group', $groups[$groupId], $groupId);
                }
                foreach ($assigned['GROUP'][$groupId] ?? [] as $access) {
                    $userRoles[$userId][] = tpl_roleString('Permission set', $access['name'] . ' via ' . $groups[$groupId], $access['arn']);
                }
            }
        }
        foreach ($assigned['USER'] as $userId => $accesses) {
            if (!isset($userRoles[$userId])) {
                throw new RuntimeException('A permission set is assigned to a user that was not collected; retry later.');
            }
            foreach ($accesses as $access) {
                $userRoles[$userId][] = tpl_roleString('Permission set', $access['name'], $access['arn']);
            }
        }
    }

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    $count = 0;
    foreach ($users as $user) {
        $userId = $user['UserId'];
        $key = match ($config['USER_KEY']) {
            'email' => primaryEmail($user) ?? throw new RuntimeException('A user has no primary email; set USER_KEY to UserName or UserId.'),
            default => $user[$config['USER_KEY']] ?? null,
        };
        $status = match ($user['UserStatus'] ?? null) {
            'ENABLED' => 'enabled',
            'DISABLED' => 'disabled',
            default => 'status not reported',
        };
        $created = ($user['CreatedAt'] ?? null) instanceof DateTimeInterface ? $user['CreatedAt']->format('Y-m-d') : '-';
        $name = is_string($user['DisplayName'] ?? null) && $user['DisplayName'] !== '' ? $user['DisplayName'] : ($user['UserName'] ?? '-');
        $description = sprintf('%s (%s); %s; created %s; id=%s', mb_strcut((string) $name, 0, 80, 'UTF-8'),
            mb_strcut((string) ($user['UserName'] ?? '-'), 0, 80, 'UTF-8'), $status, $created, $userId);
        $bytes = tpl_feedAdd($feed, tpl_feedKey($key, $keys), tpl_feedText($description, 'Description'), $userRoles[$userId]);
        $count++;
    }
    if ($count === 0 && !$config['ALLOW_EMPTY_FEED']) {
        throw new TplEmptyFeedException('No accounts found; check REGION and INSTANCE_ARN, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
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

    tpl_logStep(2, 'Finding the IAM Identity Center instance');
    $options = tpl_awsOptions($secrets['AWS_ACCESS_KEY_ID'], $secrets['AWS_SECRET_ACCESS_KEY'], $config['REGION']);
    $sso = new \Aws\SSOAdmin\SSOAdminClient($options);
    $store = new \Aws\IdentityStore\IdentityStoreClient($options);
    [$instanceArn, $storeId] = identityCenterInstance($sso, $config['INSTANCE_ARN']);
    tpl_logInfo('Instance found.');

    tpl_logStep(3, 'Collecting users, groups and permission set assignments');
    $feed = collectFeed($sso, $store, $instanceArn, $storeId, $config);

    tpl_logStep(4, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_logSummary('accounts');
    exit(0);
} catch (Throwable $e) {
    tpl_logFailure('account review feed', $e);
    exit(1);
}
