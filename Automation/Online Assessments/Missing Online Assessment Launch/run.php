<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Missing Online Assessment Launch
 *  Technology: eramba API
 *  id: oa-missing-assessment-launch        version: 0.1.0
 *  TUTORIAL TEMPLATE – example 2 of 3 of the eramba course
 *  "Online Assessments - Advanced Configurations" (https://www.eramba.org/learning/courses/81).
 *  Built for that tutorial's scenario: review and adapt before production use.
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Online%20Assessments
 *
 *  Section: Third Parties. Not recurrent: run by the Third Party notification
 *  "New Item" (Trigger Automation), once per created supplier.
 *  Composer packages: none
 * ============================================================================
 *
 *  For the Third Party that fired the notification:
 *    1. Skips it unless it is a supplier, its "Requires Online Assessment" field
 *       (set from the Finance sheet by automation 1) is Yes, it has a Third Party
 *       Contact and it has no Online Assessment yet.
 *    2. Creates and starts an Online Assessment through the eramba API:
 *       configured questionnaire, GRC group as Assessor, the contact as Recipient,
 *       magic-link access.
 *  Re-running is safe: a supplier with any assessment is never re-sent.
 *
 *  Output
 *    STDOUT  Step-by-step log (visible in eramba Automation Logs, first 10 KB).
 *    STDERR  Only technical errors. Any STDERR output marks the run as failed.
 *
 *  Exit codes
 *    0  Run completed (dry-run, created, or skipped because not needed).
 *    1  Technical error; nothing was created.
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Create them in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'ERAMBA_API_TOKEN' => '%SECRET_eramba_api_token%',
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    'QUESTIONNAIRE_NAME' => 'Supplier Security Questionnaire', // Exact questionnaire name in eramba
    'ASSESSOR_GROUP'     => 'GRC',      // Assessor of the Online Assessment
    'SUPPLIER_TYPE_ID'   => 2,          // Third Party type "Suppliers"
    'REQUIRES_OA_FIELD'  => 'Requires Online Assessment', // Third Party custom field (by name), set from the Finance sheet
    'DURATION_DAYS'      => 30,         // Days the assessment stays open
    'TITLE_PREFIX'       => 'Supplier Security Assessment – ',
    'MAX_ITEMS'          => 2000,       // Abort above this many Third Parties / assessments
    'ERAMBA_API_URL'     => '',         // Empty = runner-provided ERAMBA_BASE_URL
    'ERAMBA_API_VERIFY_TLS' => true,    // See README §9 before changing
    // Output
    'DRY_RUN'            => false,      // True = read and log only, no writes
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with the Third Party that fired the notification.
$thirdPartyId = '%THIRDPARTY_ID%';
const AUTOMATION_ID      = 'oa-missing-assessment-launch';
const AUTOMATION_VERSION = '0.1.0';
const PERIOD_DAYS        = 1; // eramba period type: 1 day, 2 week, 3 month, 4 year

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
        throw new RuntimeException("eramba $action failed: " . substr(trim($response), 0, 300));
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
        throw new RuntimeException("HTTP $status $method $path: " . substr((string)$resp, 0, 300));
    }
    return json_decode($resp, true, 64, JSON_THROW_ON_ERROR) ?? [];
}

/** eramba REST API (v2). Used because automations have no read/list helpers. */
function erambaApi(string $method, string $path, ?array $payload = null, array $query = []): array
{
    global $config, $secrets;
    $base = rtrim($config['ERAMBA_API_URL'] ?: (string)getenv('ERAMBA_BASE_URL'), '/');
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

/** Every item of a collection, bounded by MAX_ITEMS (no partial population). */
function erambaAll(string $resource, array $filter = []): array
{
    global $config;
    $items = [];
    for ($page = 1; $page <= 100; $page++) {
        $res = erambaApi('GET', "/api/v2/$resource/index", null, ['page' => $page, 'limit' => 100] + ($filter ? ['filter' => $filter] : []));
        $items = array_merge($items, $res['data'] ?? []);
        if (count($items) > $config['MAX_ITEMS']) {
            throw new RuntimeException("MAX_ITEMS exceeded for $resource.");
        }
        if (empty($res['pagination']['has_next_page'])) {
            return $items;
        }
    }
    throw new RuntimeException("Page limit exceeded for $resource.");
}

/**
 * API key ("CustomField_N") of the custom field with this name in $resource.
 * Custom field IDs differ between installations, so the scripts use names.
 */
function customField(string $resource, string $name): string
{
    static $cache = [];
    $cache[$resource] ??= erambaApi('GET', "/api/v2/$resource/custom-fields")['data'] ?? [];
    foreach ($cache[$resource] as $field) {
        if (strcasecmp(trim((string)$field['name']), trim($name)) === 0) {
            return 'CustomField_' . $field['id'];
        }
    }
    throw new RuntimeException("Custom field '$name' not found in $resource: create it (README §4) or fix its name in \$config.");
}

// ─── 5. COLLECT: the supplier and whether it already has an assessment ──────
function loadSupplier(string $thirdPartyId): array
{
    if (!ctype_digit($thirdPartyId)) {
        throw new RuntimeException('No Third Party in context: run this automation from the "New Item" notification, or Test it on an item (README §6).');
    }
    $match = array_values(array_filter(erambaAll('third-parties'), fn ($t) => (int)$t['id'] === (int)$thirdPartyId));
    return $match[0] ?? throw new RuntimeException("Third Party #$thirdPartyId not found through the API.");
}

function hasAssessment(int $thirdPartyId): bool
{
    foreach (erambaAll('vendor-assessments') as $oa) {
        foreach ($oa['third_parties'] ?? [] as $tp) {
            if ((int)$tp['id'] === $thirdPartyId) {
                return true;
            }
        }
    }
    return false;
}

// ─── 6. EVALUATE: does this supplier need an assessment? ────────────────────
/** Recipients ("User-1", "Group-2") or null with the reason it is skipped. */
function recipients(array $tp, array $config): array
{
    if ((int)($tp['third_party_type_id'] ?? 0) !== $config['SUPPLIER_TYPE_ID']) {
        return [null, 'not a supplier'];
    }
    if (trim((string)($tp[$config['REQUIRES_OA_FIELD']] ?? '')) !== 'Yes') {
        return [null, 'Requires Online Assessment is not Yes (set from the Finance sheet)'];
    }
    $recipients = array_merge(
        array_map(fn ($u) => 'User-' . $u['id'], $tp['sponsors']['users'] ?? []),
        array_map(fn ($g) => 'Group-' . $g['id'], $tp['sponsors']['groups'] ?? []),
    );
    if (!$recipients) {
        return [null, 'no Third Party Contact'];
    }
    if (hasAssessment((int)$tp['id'])) {
        return [null, 'already has an Online Assessment'];
    }
    return [$recipients, ''];
}

// ─── 7. APPLY: create and start the assessment ──────────────────────────────
function launch(array $tp, array $recipients, int $questionnaireId, string $assessor, array $config): int
{
    $res = erambaApi('POST', '/api/v2/vendor-assessments', [
        'title'                              => $config['TITLE_PREFIX'] . $tp['name'],
        'description'                        => 'Created automatically for a supplier without Online Assessments.',
        'vendor_assessment_questionnaire_id' => $questionnaireId,
        'auditors'                           => [$assessor],
        'auditees'                           => $recipients,
        'public_access'                      => 1,     // Magic-link access
        'portal_title'                       => $tp['name'] . ' – Security Assessment',
        'questions_download'                 => 0,
        'incomplete_submit'                  => 0,
        'start_after_saving'                 => 1,     // Start now
        'time_after_start'                   => $config['DURATION_DAYS'],
        'after_start_period_type'            => PERIOD_DAYS,
        'recurrence'                         => 0,
        'recurrence_auto_load'               => 0,
        'third_parties'                      => [(int)$tp['id']],
    ]);
    return (int)($res['data']['id'] ?? throw new RuntimeException('Assessment created but no ID returned.'));
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
try {
    echo sprintf("%s v%s\n", AUTOMATION_ID, AUTOMATION_VERSION);
    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no assessment will be created.' : 'LIVE RUN — the assessment will be created and sent.');

    logStep(1, 'Checking configuration');
    checkSecrets($secrets);
    $config['REQUIRES_OA_FIELD'] = customField('third-parties', $config['REQUIRES_OA_FIELD']);
    $questionnaire = erambaFindOne('vendor-assessment-questionnaires', 'name', $config['QUESTIONNAIRE_NAME'])
        ?? throw new RuntimeException("Questionnaire '{$config['QUESTIONNAIRE_NAME']}' not found.");
    $assessor = 'Group-' . ((erambaFindOne('groups', 'name', $config['ASSESSOR_GROUP'])
        ?? throw new RuntimeException("Group '{$config['ASSESSOR_GROUP']}' not found."))['id']);
    logInfo('Secrets, questionnaire and assessor group present.');

    logStep(2, "Reading Third Party #$thirdPartyId");
    $tp = loadSupplier($thirdPartyId);

    logStep(3, 'Evaluating');
    [$recipients, $reason] = recipients($tp, $config);
    if ($recipients === null) {
        logInfo("SKIPPED: {$tp['name']} – $reason.");
        echo "\nDone.\n";
        exit(0);
    }

    logStep(4, 'Creating the Online Assessment');
    if ($config['DRY_RUN']) {
        logInfo("DRY RUN: would create an assessment for {$tp['name']} -> " . implode(', ', $recipients));
        echo "\nSIMULATION COMPLETE — nothing saved.\n";
        exit(0);
    }
    $id = launch($tp, $recipients, (int)$questionnaire['id'], $assessor, $config);
    logInfo("CREATED: assessment #$id for {$tp['name']} -> " . implode(', ', $recipients));
    echo "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo "\nABORTED: technical error; no assessment was created (see STDERR).\n";
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
