<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Finance Supplier Onboarding
 *  Technology: Google Sheets (Finance supplier list) + eramba API
 *  id: oa-finance-supplier-onboarding        version: 0.1.1
 *  TUTORIAL TEMPLATE – example 1 of 3 of the eramba course
 *  "Online Assessments - Advanced Configurations" (https://www.eramba.org/learning/courses/81).
 *  Built for that tutorial's scenario: review and adapt before production use.
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Online%20Assessments
 *
 *  Section: Third Parties. Recurrent (daily).
 *  Composer packages: none
 * ============================================================================
 *
 *  For every row of the Finance supplier sheet:
 *    1. Skips it if a Third Party already has its Finance Supplier ID
 *       (or has the same name: then the ID is stored on it instead of duplicating).
 *    2. Finds the supplier contact by email or creates the account
 *       (Online Assessment portal only, magic-link access). An existing internal
 *       user (main app access) is never used as supplier contact: the row fails.
 *    3. Creates the Third Party with that account as Third Party Contact and
 *       "Requires Online Assessment" = the sheet's Requies OA (Yes/No). Creating it
 *       fires the "New Item" notification used by automation 2.
 *  Rows without contact email create the Third Party without contact; its
 *  Dynamic Status flags it until the details are completed.
 *
 *  Output
 *    STDOUT  Step-by-step log (visible in eramba Automation Logs, first 10 KB).
 *    STDERR  Only technical errors. Any STDERR output marks the run as failed.
 *
 *  Exit codes
 *    0  Run completed (dry-run or every row processed).
 *    1  Technical error, or at least one row failed. Rows processed before
 *       the error stay saved; re-running is safe (no duplicates).
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Create them in Settings / Application Configuration / Automation Secrets.
// The Google key is pasted as-is (JSON); the nowdoc keeps its quotes and "\n" intact.
$secrets = [
    'GOOGLE_SERVICE_ACCOUNT_JSON' => <<<'JSON'
%SECRET_google_service_account%
JSON,
    'ERAMBA_API_TOKEN'                => '%SECRET_eramba_api_token%',
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    // Finance sheet
    'SPREADSHEET_ID'    => '',            // ID in the sheet URL: /spreadsheets/d/<ID>/edit
    'SHEET_RANGE'       => 'Sheet1!A:Z',
    'MAX_ROWS'          => 500,           // Abort above this size instead of a partial sync
    // Sheet column headers (case-insensitive)
    'COL_NAME'          => 'Supplier Name',
    'COL_TYPE'          => 'Type',
    'COL_CONTACT_NAME'  => 'Supplier Contact Name',
    'COL_CONTACT_SURNAME' => 'Supplier Contact Surname',
    'COL_CONTACT_EMAIL' => 'Supplier Contact Email',
    'COL_FINANCE_ID'    => 'Supplier ID',
    'COL_REQUIRES_OA'   => 'Requies OA',    // As spelled in the tutorial sheet; Yes/No
    // eramba
    // Third Party custom fields, by name (their IDs differ between installations)
    'FINANCE_ID_FIELD'  => 'Finance Supplier ID',                   // Text
    'REQUIRES_OA_FIELD' => 'Requires Online Assessment',            // Dropdown: Undefined / Yes / No
    'SUPPLIER_GROUPS'   => ['No Allowed Permissions', 'Suppliers'], // Groups of new supplier accounts
    'GRC_GROUP'         => 'GRC',                                   // Third Party "GRC Contact"
    'TYPE_MAP'          => ['customer' => 1, 'supplier' => 2, 'suppliers' => 2, 'regulator' => 3],
    'DEFAULT_TYPE_ID'   => 2,                                       // Third Party type "Suppliers"
    'ERAMBA_API_URL'    => '',            // HTTPS address of your eramba; empty = runner-provided ERAMBA_BASE_URL (must also be https://)
    'ERAMBA_API_VERIFY_TLS' => true,      // See README §9 before changing
    // Output
    'DRY_RUN'           => false,         // True = read and log only, no writes
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// None: this automation works on the whole section, not on one item.
const AUTOMATION_ID      = 'oa-finance-supplier-onboarding';
const AUTOMATION_VERSION = '0.1.1';

// ─── 4. HELPERS ─────────────────────────────────────────────────────────────
function logStep(int $n, string $title): void
{
    echo sprintf("\n[%s] STEP %d: %s\n", gmdate('H:i:s'), $n, $title);
}

function logInfo(string $message): void
{
    echo '  ' . $message . "\n";
}

/** eramba wrappers return a JSON string on success and "ERROR: …"/"WARNING: …" text otherwise. */
function erambaCall(string $response, string $action): array
{
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || empty($decoded['success'])) {
        // Only the names of the rejected fields: their values may hold personal data.
        $fields = is_array($decoded['errors'] ?? null) ? implode(', ', array_keys($decoded['errors'])) : '';
        throw new RuntimeException("eramba $action failed" . ($fields !== '' ? " (invalid fields: $fields)" : '') . '.');
    }
    return $decoded;
}

/** A secret that eramba did not replace is still "%SECRET_<name>%": it does not exist. */
function checkSecrets(array $secrets): void
{
    foreach ($secrets as $key => $value) {
        if (trim($value) === '' || preg_match('/^%SECRET_([^%]+)%$/', trim($value), $m) === 1) {
            $secretName = $m[1] ?? strtolower($key);
            throw new RuntimeException(
                "Secret '$secretName' is missing: create it with exactly that name in Settings / Application Configuration / Automation Secrets (see README §6)."
            );
        }
    }
}

/** Bounded HTTPS JSON request: 20 s timeout, no redirects, 2 MB response cap. */
function httpJson(string $method, string $url, array $headers, ?string $body = null, bool $verifyTls = true): array
{
    $ctx = stream_context_create([
        'http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
                   'timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0],
        'ssl'  => ['verify_peer' => $verifyTls, 'verify_peer_name' => $verifyTls],
    ]);
    $resp = @file_get_contents($url, false, $ctx, 0, 2000000);
    preg_match('#^HTTP/\S+\s+(\d+)#', $http_response_header[0] ?? '', $m);
    $status = (int)($m[1] ?? 0);
    $path = (string)parse_url($url, PHP_URL_HOST) . (string)parse_url($url, PHP_URL_PATH);
    if ($resp === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP $status $method $path"); // Body not logged: it may hold personal data
    }
    return json_decode($resp, true, 64, JSON_THROW_ON_ERROR) ?? [];
}

/** eramba REST API (v2). Used because automations have no read/list helpers. */
function erambaApi(string $method, string $path, ?array $payload = null, array $query = []): array
{
    global $config, $secrets;
    $base = rtrim($config['ERAMBA_API_URL'] ?: (string)getenv('ERAMBA_BASE_URL'), '/');
    if (preg_match('#^https://#i', $base) !== 1) {
        throw new RuntimeException("The eramba API URL must use https:// (got $base). Set ERAMBA_API_URL to the HTTPS address of your eramba, e.g. https://yourcompany.cloud.eramba.org.");
    }
    return httpJson($method, $base . $path . ($query ? '?' . http_build_query($query) : ''), [
        'Accept: application/json', 'Content-Type: application/json',
        'Authorization: Bearer ' . trim($secrets['ERAMBA_API_TOKEN']),
    ], $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR), $config['ERAMBA_API_VERIFY_TLS']);
}

function erambaFindOne(string $resource, string $field, string $value): ?array
{
    return erambaApi('GET', "/api/v2/$resource/index", null, [
        'limit' => 1, 'filter' => [$field => ['operator' => '$eq', 'value' => $value]],
    ])['data'][0] ?? null;
}

function groupId(string $name): int
{
    $group = erambaFindOne('groups', 'name', $name)
        ?? throw new RuntimeException("Group '$name' not found in eramba.");
    return (int)$group['id'];
}

/**
 * API key ("CustomField_N") of the custom field with this name in $resource.
 * Custom field IDs differ between installations, so the scripts use names.
 * A value that is already a key ("CustomField_6") is used as is.
 */
function customField(string $resource, string $name): string
{
    if (preg_match('/^CustomField_\d+$/', trim($name)) === 1) {
        return trim($name);
    }
    static $cache = [];
    $cache[$resource] ??= erambaApi('GET', "/api/v2/$resource/custom-fields")['data'] ?? [];
    foreach ($cache[$resource] as $field) {
        if (strcasecmp(trim((string)$field['name']), trim($name)) === 0) {
            return 'CustomField_' . $field['id'];
        }
    }
    throw new RuntimeException("Custom field '$name' not found in $resource: create it (README §4) or fix its name in \$config.");
}

// ─── 5. COLLECT: read the Finance supplier sheet ────────────────────────────
function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Only service-account signing fields are used; credential URLs are never trusted. */
function googleAccessToken(string $json): string
{
    $key = json_decode(trim($json), true);
    if (!is_array($key) || ($key['type'] ?? '') !== 'service_account'
        || !is_string($key['client_email'] ?? null) || !is_string($key['private_key'] ?? null)) {
        throw new RuntimeException('Invalid service-account JSON in secret google_service_account.');
    }
    $now = time();
    $unsigned = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . b64url(json_encode([
        'iss'   => $key['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    if (!openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Cannot sign the Google token request: check the private key.');
    }
    $token = httpJson('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . b64url($signature)]));
    return $token['access_token'] ?? throw new RuntimeException('Google returned no access token.');
}

/** Rows as [lowercase header => value]; rows without supplier name are ignored. */
function collectRows(array $secrets, array $config): array
{
    $url = sprintf('https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s',
        rawurlencode($config['SPREADSHEET_ID']), rawurlencode($config['SHEET_RANGE']));
    $values = httpJson('GET', $url, ['Authorization: Bearer ' . googleAccessToken($secrets['GOOGLE_SERVICE_ACCOUNT_JSON'])])['values'] ?? [];
    if (!$values) {
        throw new RuntimeException('The sheet range is empty: check SPREADSHEET_ID and SHEET_RANGE.');
    }
    $header = array_map(fn ($h) => strtolower(trim((string)$h)), array_shift($values));
    foreach (['COL_NAME', 'COL_CONTACT_EMAIL', 'COL_FINANCE_ID'] as $col) {
        if (!in_array(strtolower($config[$col]), $header, true)) {
            throw new RuntimeException("Column '{$config[$col]}' not found in the sheet header.");
        }
    }
    $rows = [];
    foreach ($values as $line) {
        $line = array_pad(array_map(fn ($v) => trim((string)$v), $line), count($header), '');
        $row  = array_combine($header, array_slice($line, 0, count($header)));
        if (($row[strtolower($config['COL_NAME'])] ?? '') !== '') {
            $rows[] = $row;
        }
    }
    if (count($rows) > $config['MAX_ROWS']) {
        throw new RuntimeException('MAX_ROWS exceeded: no partial sync is made.');
    }
    return $rows;
}

// ─── 6. EVALUATE + 7. APPLY: one row at a time ──────────────────────────────
function cell(array $row, array $config, string $col): string
{
    return $row[strtolower($config[$col])] ?? '';
}

function findOrCreateContact(array $row, array $config, array $groupIds): ?int
{
    $email = cell($row, $config, 'COL_CONTACT_EMAIL');
    if ($email === '') {
        return null; // Missing details: the Third Party Dynamic Status flags it.
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("Invalid contact email '$email'.");
    }
    if ($user = erambaFindOne('users', 'email', $email)) {
        // Only a supplier account (no access to the main eramba app) becomes the contact:
        // a sheet row must not make an internal user the recipient of supplier assessments.
        if (!array_key_exists('main_portal', $user) || (int)$user['main_portal'] !== 0) {
            throw new RuntimeException("$email is an internal eramba user (main app access), not a supplier account: fix the contact email in the sheet.");
        }
        return (int)$user['id'];
    }
    if ($config['DRY_RUN']) {
        logInfo("DRY RUN: would create supplier account $email.");
        return null;
    }
    $res = erambaApi('POST', '/api/v2/users', [
        'name'                      => cell($row, $config, 'COL_CONTACT_NAME') ?: '-',
        'surname'                   => cell($row, $config, 'COL_CONTACT_SURNAME') ?: '-',
        'email'                     => $email,
        'login'                     => $email,
        'status'                    => 1,
        'local_account'             => 0, // Magic-link access, no password
        'api_allow'                 => 0,
        'main_portal'               => 0,
        'vendor_assessments_portal' => 1, // Online Assessment portal only
        'account_reviews_portal'    => 0,
        'awareness_portal'          => 0,
        'policy_portal'             => 0,
        'groups'                    => $groupIds,
    ]);
    return (int)($res['data']['id'] ?? throw new RuntimeException('User created but no ID returned.'));
}

/** "Yes" or "No" from the sheet's Requies OA cell (yes, y, si, sí, true, 1, x = Yes). */
function requiresOa(array $row, array $config): string
{
    $value = mb_strtolower(trim(cell($row, $config, 'COL_REQUIRES_OA')));
    return in_array($value, ['yes', 'y', 'si', 'sí', 'true', '1', 'x'], true) ? 'Yes' : 'No';
}

/** Returns CREATED, LINKED or SKIPPED. */
function syncRow(array $row, array $config, array $groupIds, string $grcContact): string
{
    $name      = cell($row, $config, 'COL_NAME');
    $financeId = cell($row, $config, 'COL_FINANCE_ID');
    $idField   = $config['FINANCE_ID_FIELD'];

    if ($financeId !== '' && erambaFindOne('third-parties', $idField, $financeId)) {
        return 'SKIPPED';
    }
    if ($existing = erambaFindOne('third-parties', 'name', $name)) {
        if ($financeId === '' || !empty($existing[$idField])) {
            return 'SKIPPED';
        }
        if (!$config['DRY_RUN']) {
            erambaApi('PUT', "/api/v2/third-parties/{$existing['id']}", [$idField => $financeId]);
        }
        return 'LINKED';
    }

    $userId = findOrCreateContact($row, $config, $groupIds);
    $data = [
        'name'                => $name,
        'description'         => 'Created from the Finance supplier list.',
        'third_party_type_id' => $config['TYPE_MAP'][mb_strtolower(cell($row, $config, 'COL_TYPE'))] ?? $config['DEFAULT_TYPE_ID'],
        'Sponsors'            => $userId ? ["User-$userId"] : [],
        'GrcContacts'         => [$grcContact],
        $idField              => $financeId,
        $config['REQUIRES_OA_FIELD'] => requiresOa($row, $config), // read by automation 2
    ];
    if (!$config['DRY_RUN']) {
        erambaCall(addObjectMacro($data), "add Third Party $name"); // fires the "New Item" notification
    }
    return 'CREATED';
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
try {
    echo sprintf("%s v%s\n", AUTOMATION_ID, AUTOMATION_VERSION);
    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — nothing will be created.' : 'LIVE RUN — accounts and Third Parties will be created.');

    logStep(1, 'Checking configuration');
    checkSecrets($secrets);
    if (!preg_match('/^[A-Za-z0-9_-]{20,}$/', $config['SPREADSHEET_ID'])) {
        throw new RuntimeException('Set SPREADSHEET_ID (README §7).');
    }
    foreach (['FINANCE_ID_FIELD', 'REQUIRES_OA_FIELD'] as $key) {
        $config[$key] = customField('third-parties', $config[$key]);
    }
    $groupIds   = array_map('groupId', $config['SUPPLIER_GROUPS']);
    $grcContact = 'Group-' . groupId($config['GRC_GROUP']);
    logInfo('Secrets and groups present.');

    logStep(2, 'Reading the Finance supplier sheet');
    $rows = collectRows($secrets, $config);
    logInfo(count($rows) . ' suppliers in the sheet.');

    logStep(3, 'Syncing suppliers');
    $count = ['CREATED' => 0, 'LINKED' => 0, 'SKIPPED' => 0, 'ERROR' => 0];
    foreach ($rows as $row) {
        $name = cell($row, $config, 'COL_NAME');
        try {
            $action = syncRow($row, $config, $groupIds, $grcContact);
            if ($action !== 'SKIPPED') {
                logInfo($action === 'LINKED'
                    ? "LINKED: $name (Finance Supplier ID stored; matched by name only: check it is the same supplier)"
                    : "CREATED: $name (Requires OA: " . requiresOa($row, $config) . ')' . (cell($row, $config, 'COL_CONTACT_EMAIL') === '' ? ' (no contact email)' : ''));
            }
        } catch (Throwable $e) {
            $action = 'ERROR';
            logInfo("ERROR: $name -> " . $e->getMessage());
        }
        $count[$action]++;
    }

    echo sprintf("\nCreated: %d | linked: %d | already in eramba: %d | errors: %d\n",
        $count['CREATED'], $count['LINKED'], $count['SKIPPED'], $count['ERROR']);
    if ($count['ERROR'] > 0) {
        fwrite(STDERR, "ERROR: {$count['ERROR']} row(s) failed; see the log above.\n");
        exit(1);
    }
    echo $config['DRY_RUN'] ? "\nSIMULATION COMPLETE — nothing saved.\n" : "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo "\nABORTED: technical error; rows processed before it stay saved (see STDERR).\n";
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
