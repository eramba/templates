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
 *  Section: Online Assessments. Recurrent (daily, after Finance Supplier Onboarding).
 *  Composer packages: none
 * ============================================================================
 *
 *  For every Third Party of the supplier type that has a Third Party Contact
 *  and no Online Assessment, creates and starts one: configured questionnaire,
 *  GRC group as Assessor, the contact as Recipient, magic-link access.
 *  Suppliers without contact are skipped (their Dynamic Status flags them).
 *  Re-running is safe: a supplier with any Online Assessment is never re-sent.
 *
 *  Output
 *    STDOUT  Step-by-step log (visible in eramba Automation Logs, first 10 KB).
 *    STDERR  Only technical errors. Any STDERR output marks the run as failed.
 *
 *  Exit codes
 *    0  Run completed (dry-run or every supplier processed).
 *    1  Technical error, or at least one assessment failed to be created.
 *       Assessments created before the error stay saved and are not duplicated.
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
    'DURATION_DAYS'      => 30,         // Days the assessment stays open
    'TITLE_PREFIX'       => 'Supplier Security Assessment – ',
    'MAX_ITEMS'          => 2000,       // Abort above this many Third Parties / assessments
    'ERAMBA_API_URL'     => '',         // Empty = runner-provided ERAMBA_BASE_URL
    'ERAMBA_API_VERIFY_TLS' => true,    // See README §9 before changing
    // Output
    'DRY_RUN'            => false,      // True = read and log only, no writes
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// None: this automation works on the whole section, not on one item.
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

// ─── 5. COLLECT: suppliers and existing assessments ─────────────────────────
function collect(array $config): array
{
    $assessed = [];
    foreach (erambaAll('vendor-assessments') as $oa) {
        foreach ($oa['third_parties'] ?? [] as $tp) {
            $assessed[(int)$tp['id']] = true;
        }
    }
    $suppliers = erambaAll('third-parties', ['third_party_type_id' => ['operator' => '$eq', 'value' => $config['SUPPLIER_TYPE_ID']]]);
    return [$suppliers, $assessed];
}

// ─── 6. EVALUATE: which suppliers need an assessment ────────────────────────
function plan(array $suppliers, array $assessed): array
{
    $plan = ['launch' => [], 'assessed' => 0, 'no_contact' => []];
    foreach ($suppliers as $tp) {
        if (isset($assessed[(int)$tp['id']])) {
            $plan['assessed']++;
            continue;
        }
        $recipients = array_merge(
            array_map(fn ($u) => 'User-' . $u['id'], $tp['sponsors']['users'] ?? []),
            array_map(fn ($g) => 'Group-' . $g['id'], $tp['sponsors']['groups'] ?? []),
        );
        if (!$recipients) {
            $plan['no_contact'][] = $tp['name'];
            continue;
        }
        $plan['launch'][] = ['id' => (int)$tp['id'], 'name' => $tp['name'], 'recipients' => $recipients];
    }
    return $plan;
}

// ─── 7. APPLY: create and start the assessments ─────────────────────────────
function launch(array $supplier, int $questionnaireId, string $assessor, array $config): void
{
    $data = [
        'title'                          => $config['TITLE_PREFIX'] . $supplier['name'],
        'description'                    => 'Created automatically for a supplier without Online Assessments.',
        'VendorAssessmentQuestionnaires' => $questionnaireId,
        'Auditors'                       => [$assessor],
        'Auditees'                       => $supplier['recipients'],
        'public_access'                  => 1,          // Magic-link access
        'portal_title'                   => $supplier['name'] . ' – Security Assessment',
        'questions_download'             => 0,
        'report_id'                      => null,
        'incomplete_submit'              => 0,
        'start_after_saving'             => 1,          // Start now
        'time_after_saving'              => 1,
        'after_saving_period_type'       => PERIOD_DAYS,
        'time_after_start'               => $config['DURATION_DAYS'],
        'after_start_period_type'        => PERIOD_DAYS,
        'recurrence'                     => 0,
        'recurrence_period'              => 1,
        'recurrence_period_type'         => PERIOD_DAYS,
        'recurrence_auto_load'           => 0,
        'ThirdParties'                   => [$supplier['id']],
    ];
    $res = erambaCall(addObjectMacro($data), "add Online Assessment for {$supplier['name']}");
    logInfo(sprintf('CREATED: assessment #%s for %s -> %s', $res['data']['id'] ?? '?', $supplier['name'], implode(', ', $supplier['recipients'])));
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
try {
    echo sprintf("%s v%s\n", AUTOMATION_ID, AUTOMATION_VERSION);
    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no assessment will be created.' : 'LIVE RUN — assessments will be created and sent.');

    logStep(1, 'Checking configuration');
    checkSecrets($secrets);
    $questionnaire = erambaFindOne('vendor-assessment-questionnaires', 'name', $config['QUESTIONNAIRE_NAME'])
        ?? throw new RuntimeException("Questionnaire '{$config['QUESTIONNAIRE_NAME']}' not found.");
    $assessor = 'Group-' . ((erambaFindOne('groups', 'name', $config['ASSESSOR_GROUP'])
        ?? throw new RuntimeException("Group '{$config['ASSESSOR_GROUP']}' not found."))['id']);
    logInfo('Secrets, questionnaire and assessor group present.');

    logStep(2, 'Collecting suppliers and Online Assessments');
    [$suppliers, $assessed] = collect($config);
    logInfo(count($suppliers) . ' suppliers, ' . count($assessed) . ' with an assessment.');

    logStep(3, 'Evaluating');
    $plan = plan($suppliers, $assessed);
    foreach ($plan['no_contact'] as $name) {
        logInfo("SKIPPED: $name – no Third Party Contact");
    }
    logInfo(count($plan['launch']) . ' supplier(s) need an assessment.');

    logStep(4, 'Creating Online Assessments');
    $errors = 0;
    foreach ($plan['launch'] as $supplier) {
        if ($config['DRY_RUN']) {
            logInfo("DRY RUN: would create an assessment for {$supplier['name']}.");
            continue;
        }
        try {
            launch($supplier, (int)$questionnaire['id'], $assessor, $config);
        } catch (Throwable $e) {
            $errors++;
            logInfo('ERROR: ' . $e->getMessage());
        }
    }

    echo sprintf("\nTo create: %d | already assessed: %d | no contact: %d | errors: %d\n",
        count($plan['launch']), $plan['assessed'], count($plan['no_contact']), $errors);
    if ($errors > 0) {
        fwrite(STDERR, "ERROR: $errors assessment(s) could not be created; see the log above.\n");
        exit(1);
    }
    echo $config['DRY_RUN'] ? "\nSIMULATION COMPLETE — nothing saved.\n" : "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo "\nABORTED: technical error; assessments created before it stay saved (see STDERR).\n";
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
