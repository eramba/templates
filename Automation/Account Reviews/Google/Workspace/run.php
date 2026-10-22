<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Google Workspace Users and Assigned Access
 *  Technology: Google Workspace (Admin SDK Directory API)
 *  id: google-workspace-account-review        version: 0.1.0 (draft)
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Account%20Reviews
 *
 *  Composer packages (paste in the automation's composer field):
 *    google/apiclient:^2.18
 *    guzzlehttp/guzzle:^7.9      (transport limits for the Google client)
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
 *  Read-only: the script never changes Google Workspace and makes no review decisions.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Created in Settings / Application Configuration / Automation Secrets.
$secrets = [
    // Base64 of the service-account JSON key, shared with the Google Workspace control automations
    'GOOGLE_SERVICE_ACCOUNT_JSON_B64' => '%SECRET_google_service_account_json_b64%',
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'USER_KEY' => 'primaryEmail', // Account identifier: primaryEmail or id (no fallback)
    'ROLES' => ['groups', 'admin_roles'], // Access listed as roles: groups, admin_roles, org_unit; [] = accounts only
    'CUSTOMER_ID' => 'my_customer', // my_customer, or the customer ID (C0…) of the account
    'DELEGATED_ADMIN_EMAIL' => '', // Read-only administrator the service account acts as, e.g. eramba-reader@example.com
    'ALLOW_EMPTY_FEED' => false, // True only if zero accounts is a legitimate result
    'MAX_USERS' => 2000, // Maximum users in the feed; never truncate
    'MAX_GROUPS' => 1000, // Maximum groups read for memberships; never truncate
];

// ─── 3. SETTINGS (do not edit) ──────────────────────────────────────────────
const AUTOMATION_ID = 'google-workspace-account-review';
const AUTOMATION_VERSION = '0.1.0';
const TPL_LOG_STREAM = 'stderr'; // stdout belongs to the artifact declaration
const TPL_RUN_SECONDS = 200; // Collection budget inside the 240-second timeout

// ─── 4. SHARED CODE (copied from Automation/_shared, do not edit here) ──────
// ─── shared:core v1 (edit Automation/_shared/core.php, then run Automation/tools/sync.php) ───
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

function tpl_log(string $line): void
{
    fwrite(TPL_LOG_STREAM === 'stdout' ? STDOUT : STDERR, $line . "\n");
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
// ─── end shared:core v1 sha256:277e2f6c5d57b964 ───
// ─── shared:feed v3 (edit Automation/_shared/feed.php, then run Automation/tools/sync.php) ───
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
    return $bytes;
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
// ─── end shared:feed v3 sha256:39800df21bc1737d ───
// ─── shared:providers/google v1 (edit Automation/_shared/providers/google.php, then run Automation/tools/sync.php) ───
/**
 * The fields of a service-account JSON key that sign the token request. Credential URLs in the key
 * (token_uri, auth_provider_x509_cert_url…) are never used, so a tampered key cannot redirect the token.
 */
function tpl_googleServiceAccount(string $json): array
{
    try {
        $key = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('The service-account key is not valid JSON.');
    }
    if (!is_array($key) || ($key['type'] ?? '') !== 'service_account'
        || !is_string($key['client_email'] ?? null)
        || preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.iam\.gserviceaccount\.com$/D', $key['client_email']) !== 1
        || !is_string($key['client_id'] ?? null) || !ctype_digit($key['client_id'])
        || !is_string($key['private_key'] ?? null) || !is_string($key['private_key_id'] ?? null)) {
        throw new RuntimeException('The service-account key lacks its signing fields; download a new JSON key.');
    }
    $private = openssl_pkey_get_private($key['private_key']);
    $details = $private === false ? false : openssl_pkey_get_details($private);
    if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
        throw new RuntimeException('The service-account private key is not a valid RSA key.');
    }
    return array_intersect_key($key, array_flip(['type', 'client_email', 'client_id', 'private_key', 'private_key_id']));
}

/**
 * A Google API client signed by the service account and acting as $subject (domain-wide delegation),
 * limited to $scopes. TLS verification on, redirects off, 20-second requests, responses over 4 MB and
 * the run budget stop the transfer. The SDK retries 500/503 and rate-limit errors up to 3 times.
 */
function tpl_googleClient(array $key, string $subject, array $scopes): \Google\Client
{
    $maxBytes = 4 * 1024 * 1024;
    $http = new \GuzzleHttp\Client([
        'connect_timeout' => 5,
        'timeout' => 20,
        'allow_redirects' => false,
        'verify' => true,
        'on_headers' => static function (\Psr\Http\Message\ResponseInterface $response) use ($maxBytes): void {
            if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                throw new RuntimeException('Google response too large; narrow the scope.');
            }
        },
        'progress' => static function ($total, $downloaded) use ($maxBytes): void {
            if ($downloaded > $maxBytes) {
                throw new RuntimeException('Google response too large; narrow the scope.');
            }
            tpl_remaining();
        },
    ]);
    try {
        $client = new \Google\Client([
            'retry' => ['retries' => 3, 'initial_delay' => 1, 'max_delay' => 8],
            'universe_domain' => 'googleapis.com',
        ]);
        $client->setHttpClient($http);
        $client->setAuthConfig($key);
        $client->setSubject($subject);
        $client->setScopes($scopes);
    } catch (Throwable $e) {
        throw new RuntimeException('Google client initialization failed; check the google/apiclient package and the service-account key.');
    }
    return $client;
}

/**
 * One Google API call. Errors keep only the HTTP status and Google's one-word reason: messages and
 * bodies may contain account data. Budget and size errors raised during the transfer are kept.
 */
function tpl_googleCall(string $what, callable $call): mixed
{
    tpl_remaining();
    try {
        return $call();
    } catch (\Google\Service\Exception $e) {
        $reason = $e->getErrors()[0]['reason'] ?? '';
        $reason = is_string($reason) && preg_match('/^[A-Za-z]{1,40}$/D', $reason) === 1 ? ", $reason" : '';
        throw new RuntimeException(sprintf(
            '%s: Google returned HTTP %d%s; check domain-wide delegation, scopes and the delegated admin.',
            $what,
            $e->getCode(),
            $reason
        ));
    } catch (Throwable $e) {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            $kept = '/^(Time|Memory) budget exceeded|^Google response too large/';
            if ($cause instanceof RuntimeException && preg_match($kept, $cause->getMessage()) === 1) {
                throw new RuntimeException($cause->getMessage());
            }
        }
        throw new RuntimeException("$what: the request to Google failed; check connectivity and the service-account key.");
    }
}

/**
 * Every item of a Google list, following nextPageToken to the last page. $fetch receives the page
 * parameters (['pageToken' => …] after the first page) and returns the list response; $items names
 * its collection (users, groups, members, items). More than $limit items or 200 pages aborts.
 */
function tpl_googleList(string $what, callable $fetch, string $items, int $limit): array
{
    $getter = 'get' . ucfirst($items);
    $all = [];
    $tokens = [];
    $params = [];
    for ($page = 0; $page < 200; $page++) {
        $response = tpl_googleCall($what, static fn () => $fetch($params));
        if (!is_object($response) || !method_exists($response, $getter)) {
            throw new RuntimeException("$what returned an unexpected response.");
        }
        $batch = $response->$getter() ?? []; // Google omits the collection when it is empty
        if (!is_array($batch)) {
            throw new RuntimeException("$what returned an unexpected collection.");
        }
        array_push($all, ...$batch);
        if (count($all) > $limit) {
            throw new RuntimeException("$what exceeds the configured limit; narrow the scope or raise the limit.");
        }
        $next = $response->getNextPageToken();
        if ($next === null || $next === '') {
            return $all;
        }
        if (!is_string($next) || isset($tokens[$next])) {
            throw new RuntimeException("$what pagination token is invalid or repeats.");
        }
        $tokens[$next] = true;
        $params = ['pageToken' => $next];
    }
    throw new RuntimeException("$what exceeds 200 pages; narrow the scope.");
}
// ─── end shared:providers/google v1 sha256:ed5007602bc2175c ───

// ─── 5. COLLECT: Google Workspace ───────────────────────────────────────────

const ROLE_KINDS = ['groups', 'admin_roles', 'org_unit'];
const MAX_MEMBERSHIPS = 100000; // Direct group memberships read in one run
const MAX_ROLE_ASSIGNMENTS = 10000;
const SCOPE_PREFIX = 'https://www.googleapis.com/auth/admin.directory.';

function validateConfig(array $c): void
{
    tpl_roleKinds($c['ROLES'], ROLE_KINDS);
    if (!in_array($c['USER_KEY'], ['primaryEmail', 'id'], true)) {
        throw new RuntimeException('USER_KEY must be primaryEmail or id.');
    }
    if (!is_string($c['CUSTOMER_ID']) || preg_match('/^(?:my_customer|C[a-zA-Z0-9]{4,20})$/D', $c['CUSTOMER_ID']) !== 1) {
        throw new RuntimeException('CUSTOMER_ID must be my_customer or a customer ID such as C0abc123.');
    }
    if (!is_string($c['DELEGATED_ADMIN_EMAIL']) || filter_var($c['DELEGATED_ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Set DELEGATED_ADMIN_EMAIL to the read-only administrator the service account acts as (README §5).');
    }
    if (!is_bool($c['ALLOW_EMPTY_FEED'])) {
        throw new RuntimeException('ALLOW_EMPTY_FEED must be true or false.');
    }
    if (!is_int($c['MAX_USERS']) || $c['MAX_USERS'] < 1 || $c['MAX_USERS'] > 10000) {
        throw new RuntimeException('MAX_USERS must be a number from 1 to 10000.');
    }
    if (!is_int($c['MAX_GROUPS']) || $c['MAX_GROUPS'] < 1 || $c['MAX_GROUPS'] > 5000) {
        throw new RuntimeException('MAX_GROUPS must be a number from 1 to 5000.');
    }
}

/** Only the read-only scopes the selected role kinds need; each must be authorized in domain-wide delegation. */
function directoryScopes(array $kinds): array
{
    $scopes = [SCOPE_PREFIX . 'user.readonly'];
    if (isset($kinds['groups']) || isset($kinds['admin_roles'])) {
        $scopes[] = SCOPE_PREFIX . 'group.readonly'; // Admin roles can be assigned to groups
    }
    if (isset($kinds['admin_roles'])) {
        $scopes[] = SCOPE_PREFIX . 'rolemanagement.readonly';
    }
    return $scopes;
}

/** A Directory identifier (user, group, role, org unit or customer ID). Role IDs are int64 values. */
function directoryId(mixed $value, string $what): string
{
    if (is_int($value)) {
        $value = (string) $value;
    }
    if (!is_string($value) || preg_match('/^[A-Za-z0-9:_-]{1,100}$/D', $value) !== 1) {
        throw new RuntimeException("$what is missing or has an unexpected format.");
    }
    return $value;
}

/** YYYY-MM-DD of a Directory timestamp; null for missing values and the 1970 "never" placeholder. */
function directoryDate(mixed $value): ?string
{
    if (!is_string($value) || preg_match('/^(\d{4}-\d{2}-\d{2})T/', $value, $m) !== 1 || str_starts_with($value, '1970-')) {
        return null;
    }
    return $m[1];
}

/** Every user of the customer, active, suspended and archived, keyed by user ID. */
function collectUsers(\Google\Service\Directory $directory, array $config): array
{
    $list = tpl_googleList('Directory users', static fn (array $page) => $directory->users->listUsers($page + [
        'customer' => $config['CUSTOMER_ID'],
        'maxResults' => 500,
        'projection' => 'basic',
        'viewType' => 'admin_view',
        'fields' => 'nextPageToken,users(id,primaryEmail,name/fullName,suspended,suspensionReason,archived,'
            . 'isAdmin,isDelegatedAdmin,orgUnitPath,creationTime,lastLoginTime)',
    ]), 'users', $config['MAX_USERS']);
    $users = [];
    foreach ($list as $user) {
        $id = directoryId($user->getId(), 'User ID');
        if (isset($users[$id])) {
            throw new RuntimeException('The user list contains a duplicate user; retry later.');
        }
        $users[$id] = [
            'id' => $id,
            'primaryEmail' => tpl_feedText($user->getPrimaryEmail(), 'Primary email'),
            'name' => $user->getName()?->getFullName(),
            'suspended' => $user->getSuspended() === true,
            'reason' => $user->getSuspensionReason(),
            'archived' => $user->getArchived() === true,
            'superAdmin' => $user->getIsAdmin() === true,
            'delegatedAdmin' => $user->getIsDelegatedAdmin() === true,
            'orgUnit' => $user->getOrgUnitPath(),
            'created' => directoryDate($user->getCreationTime()),
            'lastLogin' => directoryDate($user->getLastLoginTime()),
        ];
    }
    return $users;
}

/** Every group of the customer, keyed by group ID. */
function collectGroups(\Google\Service\Directory $directory, array $config): array
{
    $list = tpl_googleList('Directory groups', static fn (array $page) => $directory->groups->listGroups($page + [
        'customer' => $config['CUSTOMER_ID'],
        'maxResults' => 200,
        'fields' => 'nextPageToken,groups(id,email)',
    ]), 'groups', $config['MAX_GROUPS']);
    $groups = [];
    foreach ($list as $group) {
        $id = directoryId($group->getId(), 'Group ID');
        if (isset($groups[$id])) {
            throw new RuntimeException('The group list contains a duplicate group; retry later.');
        }
        $groups[$id] = tpl_feedText($group->getEmail(), 'Group email');
    }
    return $groups;
}

/** Direct members of one group (users, groups, the whole customer), read once per run. */
function directMembers(\Google\Service\Directory $directory, string $groupId, array &$state): array
{
    if (isset($state['members'][$groupId])) {
        return $state['members'][$groupId];
    }
    $list = tpl_googleList('Group members', static fn (array $page) => $directory->members->listMembers($groupId, $page + [
        'maxResults' => 200,
        'fields' => 'nextPageToken,members(id,type,role)',
    ]), 'members', MAX_MEMBERSHIPS);
    $members = [];
    foreach ($list as $member) {
        $type = $member->getType();
        $role = $member->getRole();
        if (!in_array($type, ['USER', 'GROUP', 'CUSTOMER', 'EXTERNAL'], true) || !in_array($role, ['OWNER', 'MANAGER', 'MEMBER'], true)) {
            throw new RuntimeException('A group member has an unexpected type or role.');
        }
        $members[] = ['id' => $type === 'EXTERNAL' ? '' : directoryId($member->getId(), 'Member ID'), 'type' => $type, 'role' => $role];
    }
    $state['total'] += count($members);
    if ($state['total'] > MAX_MEMBERSHIPS) {
        throw new RuntimeException('More than ' . MAX_MEMBERSHIPS . ' group memberships; narrow the scope.');
    }
    return $state['members'][$groupId] = $members;
}

/**
 * Users who belong to a group directly or through nested groups: ['users' => [user ID => label], 'all' => bool].
 * A direct role (owner, manager, member) wins over a nested membership. 'all' means the whole customer is a member.
 */
function effectiveMembers(\Google\Service\Directory $directory, string $groupId, array &$state, array $path = []): array
{
    if (isset($state['effective'][$groupId])) {
        return $state['effective'][$groupId];
    }
    if (isset($path[$groupId])) {
        return ['users' => [], 'all' => false]; // Google rejects membership cycles; never loop
    }
    $path[$groupId] = true;
    $users = [];
    $all = false;
    foreach (directMembers($directory, $groupId, $state) as $member) {
        if ($member['type'] === 'USER' && isset($state['users'][$member['id']])) {
            $users[$member['id']] = strtolower($member['role']);
        } elseif ($member['type'] === 'GROUP' && isset($state['groups'][$member['id']])) {
            $nested = effectiveMembers($directory, $member['id'], $state, $path);
            foreach ($nested['users'] as $userId => $label) {
                $users[$userId] ??= 'nested';
            }
            $all = $all || $nested['all'];
        } elseif ($member['type'] === 'CUSTOMER') {
            $all = true;
        } else {
            $state['outside']++; // External users and groups: not accounts of this customer
        }
    }
    return $state['effective'][$groupId] = ['users' => $users, 'all' => $all];
}

/** Adds $role to every user the group reaches; groups that contain the whole customer go to $everyone. */
function grantThroughGroup(\Google\Service\Directory $directory, string $groupId, array &$state, callable $role): void
{
    $effective = effectiveMembers($directory, $groupId, $state);
    foreach ($effective['users'] as $userId => $label) {
        $state['roles'][$userId][] = $role($label);
    }
    if ($effective['all']) {
        $state['everyone'][] = $role('all users');
    }
}

function collectGroupRoles(\Google\Service\Directory $directory, array &$state): void
{
    foreach ($state['groups'] as $groupId => $email) {
        $groupId = (string) $groupId;
        grantThroughGroup($directory, $groupId, $state, static fn (string $label) => tpl_roleString('Group', "$email ($label)", $groupId));
    }
}

function collectAdminRoles(\Google\Service\Directory $directory, array $config, array &$state): void
{
    $roleNames = [];
    $list = tpl_googleList('Admin roles', static fn (array $page) => $directory->roles->listRoles($config['CUSTOMER_ID'], $page + [
        'maxResults' => 100,
        'fields' => 'nextPageToken,items(roleId,roleName)',
    ]), 'items', MAX_ROLE_ASSIGNMENTS);
    foreach ($list as $role) {
        $roleNames[directoryId($role->getRoleId(), 'Role ID')] = tpl_feedText($role->getRoleName(), 'Role name');
    }
    $assignments = tpl_googleList('Admin role assignments', static fn (array $page) => $directory->roleAssignments->listRoleAssignments(
        $config['CUSTOMER_ID'],
        $page + ['maxResults' => 100, 'fields' => 'nextPageToken,items(roleId,assignedTo,assigneeType,scopeType,orgUnitId,condition)']
    ), 'items', MAX_ROLE_ASSIGNMENTS);
    foreach ($assignments as $assignment) {
        $roleId = directoryId($assignment->getRoleId(), 'Role ID');
        if (!isset($roleNames[$roleId])) {
            throw new RuntimeException('A role assignment refers to a role that was not listed; roles changed during the pull, retry later.');
        }
        $name = $roleNames[$roleId];
        $id = $roleId;
        if ($assignment->getScopeType() === 'ORG_UNIT') {
            $orgUnitId = directoryId($assignment->getOrgUnitId(), 'Org unit ID');
            $name .= " (org unit $orgUnitId)";
            $id .= "/$orgUnitId";
        } elseif ($assignment->getScopeType() !== 'CUSTOMER') {
            throw new RuntimeException('A role assignment has an unexpected scope type.');
        }
        if (is_string($assignment->getCondition()) && $assignment->getCondition() !== '') {
            $name .= ' (conditional)';
        }
        $assignee = directoryId($assignment->getAssignedTo(), 'Role assignee');
        $type = is_string($assignment->getAssigneeType()) ? strtolower($assignment->getAssigneeType()) : '';
        if ($type === 'user' && isset($state['users'][$assignee])) {
            $state['roles'][$assignee][] = tpl_roleString('Admin role', $name, $id);
        } elseif ($type === 'group' && isset($state['groups'][$assignee])) {
            $via = $name . ' (via group ' . $state['groups'][$assignee] . ')';
            grantThroughGroup($directory, $assignee, $state, static fn () => tpl_roleString('Admin role', $via, $id));
        } elseif ($type === 'user' || $type === 'group') {
            $state['otherAssignees']++; // Service accounts and principals outside the user and group lists
        } else {
            throw new RuntimeException('A role assignment has an unexpected assignee type.');
        }
    }
    tpl_logInfo(count($roleNames) . ' admin roles and ' . count($assignments) . ' role assignments found.');
}

function describeUser(array $user): string
{
    $reason = is_string($user['reason']) && preg_match('/^[A-Z_]{1,40}$/D', $user['reason']) === 1 ? " ({$user['reason']})" : '';
    $states = array_merge($user['suspended'] ? ["suspended$reason"] : [], $user['archived'] ? ['archived'] : []);
    $parts = [
        is_string($user['name']) && trim($user['name']) !== '' ? mb_strcut(trim($user['name']), 0, 100, 'UTF-8') : '(no name)',
        $states === [] ? 'active' : implode(', ', $states),
    ];
    if ($user['superAdmin'] || $user['delegatedAdmin']) {
        $parts[] = $user['superAdmin'] ? 'super admin' : 'delegated admin';
    }
    $parts[] = 'org unit ' . (is_string($user['orgUnit']) ? mb_strcut($user['orgUnit'], 0, 60, 'UTF-8') : '-');
    $parts[] = $user['lastLogin'] === null ? 'never signed in' : 'last sign-in ' . $user['lastLogin'];
    $parts[] = 'created ' . ($user['created'] ?? '-');
    $parts[] = 'id=' . $user['id'];
    return tpl_feedText(implode('; ', $parts), 'Description');
}

function collectFeed(\Google\Service\Directory $directory, array $config)
{
    $kinds = tpl_roleKinds($config['ROLES'], ROLE_KINDS);
    $state = ['users' => collectUsers($directory, $config), 'groups' => [], 'members' => [], 'effective' => [], 'roles' => [],
        'everyone' => [], 'total' => 0, 'outside' => 0, 'otherAssignees' => 0];
    tpl_logInfo(count($state['users']) . ' users found.');
    if (isset($kinds['groups']) || isset($kinds['admin_roles'])) {
        $state['groups'] = collectGroups($directory, $config);
        tpl_logInfo(count($state['groups']) . ' groups found.');
    }
    if (isset($kinds['groups'])) {
        collectGroupRoles($directory, $state);
    }
    if (isset($kinds['admin_roles'])) {
        collectAdminRoles($directory, $config, $state);
    }
    if ($state['members'] !== []) {
        tpl_logInfo(sprintf('%d direct memberships read in %d groups; %d members outside the customer\'s users and groups ignored.',
            $state['total'], count($state['members']), $state['outside']));
    }
    if ($state['otherAssignees'] > 0) {
        tpl_logInfo($state['otherAssignees'] . ' role assignments to service accounts or other principals not listed.');
    }

    $feed = tpl_feedOpen();
    $keys = [];
    $bytes = 0;
    foreach ($state['users'] as $userId => $user) {
        $roles = array_merge($state['roles'][$userId] ?? [], $state['everyone']);
        if (isset($kinds['org_unit'])) {
            $roles[] = tpl_roleString('Org unit', $user['orgUnit'], 'path');
        }
        $key = tpl_feedKey($user[$config['USER_KEY']], $keys);
        $bytes = tpl_feedAdd($feed, $key, describeUser($user), $roles);
    }
    $count = count($state['users']);
    if ($count === 0 && !$config['ALLOW_EMPTY_FEED']) {
        throw new RuntimeException('No accounts found; check CUSTOMER_ID, or set ALLOW_EMPTY_FEED if zero accounts is intended.');
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
    $key = tpl_googleServiceAccount(tpl_base64Secret($secrets['GOOGLE_SERVICE_ACCOUNT_JSON_B64'], 'google_service_account_json_b64'));
    tpl_logInfo('Secrets present.');

    tpl_logStep(2, 'Collecting users, groups and admin roles');
    $scopes = directoryScopes(tpl_roleKinds($config['ROLES'], ROLE_KINDS));
    $directory = new \Google\Service\Directory(tpl_googleClient($key, $config['DELEGATED_ADMIN_EMAIL'], $scopes));
    $feed = collectFeed($directory, $config);

    tpl_logStep(3, 'Publishing the account review feed');
    tpl_feedPublish($feed);
    tpl_log("\nDone.");
    exit(0);
} catch (Throwable $e) {
    tpl_log("\nABORTED: no account review feed was published.\nERROR: " . $e->getMessage());
    exit(1);
}
