<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Submitted Assessment Risk Review
 *  Technology: OpenAI (optional) + eramba API
 *  id: oa-submitted-risk-review        version: 0.1.0
 *  TUTORIAL TEMPLATE – example 3 of 3 of the eramba course
 *  \"Online Assessments - Advanced Configurations\" (https://www.eramba.org/learning/courses/81).
 *  Built for that tutorial's scenario: review and adapt before production use.
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Online%20Assessments
 *
 *  Section: Online Assessments. Recurrent (hourly or daily).
 *  Composer packages: none
 * ============================================================================
 *
 *  For every submitted Online Assessment without a risk level yet:
 *    1. Reads its answers, score and open findings.
 *    2. Reviews it with OpenAI (secret openai_api_key) or, without the secret,
 *       with the score/findings rule in README §2.
 *    3. Saves the risk level and the conclusion (Review Notes) on the assessment.
 *    4. Saves "Supplier Risk Level" and "Last Review Date" on its Third Parties.
 *  Re-running is safe: reviewed assessments already have a risk level.
 *
 *  Output
 *    STDOUT  Step-by-step log (visible in eramba Automation Logs, first 10 KB).
 *    STDERR  Only technical errors. Any STDERR output marks the run as failed.
 *
 *  Exit codes
 *    0  Run completed (dry-run or every pending assessment reviewed).
 *    1  Technical error, or at least one review failed. If the assessment was
 *       saved but a Third Party update failed, fix it manually (see README §8).
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Create them in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'ERAMBA_API_TOKEN' => '%SECRET_eramba_api_token%',
];
// Optional: without it the score/findings rule is used.
$openAiApiKey = '%SECRET_openai_api_key%';

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    // eramba fields
    'OA_RISK_FIELD'      => 'CustomField_2', // Assessment: "Post Assessment Risk Level"
    'TP_RISK_FIELD'      => 'CustomField_4', // Third Party: "Supplier Risk Level"
    'TP_REVIEW_DATE'     => 'CustomField_5', // Third Party: "Last Review Date"
    'UNREVIEWED_VALUES'  => ['', 'Undefined'], // Risk level values that mean "not reviewed yet"
    // Rule used without AI (score in %)
    'HIGH_BELOW_PCT'     => 50,              // Any open finding is also High
    'MEDIUM_BELOW_PCT'   => 80,
    // AI review
    'OPENAI_MODEL'       => 'gpt-6.1-luna',
    'OPENAI_REASONING'   => 'low',
    // Run
    'FORCE_OA_IDS'       => [],              // e.g. [3] to review an assessment again while testing
    'MAX_ITEMS'          => 2000,
    'ERAMBA_API_URL'     => '',              // Empty = runner-provided ERAMBA_BASE_URL
    'ERAMBA_API_VERIFY_TLS' => true,         // See README §9 before changing
    'DRY_RUN'            => false,           // True = review and log only, no writes
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// None: this automation works on the whole section, not on one item.
const AUTOMATION_ID      = 'oa-submitted-risk-review';
const AUTOMATION_VERSION = '0.1.0';
const LEVELS             = ['Low', 'Medium', 'High'];

// ─── 4. HELPERS ─────────────────────────────────────────────────────────────
function logStep(int $n, string $title): void
{
    echo sprintf("\n[%s] STEP %d: %s\n", gmdate('H:i:s'), $n, $title);
}

function logInfo(string $message): void
{
    echo '  ' . $message . "\n";
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

function secretExists(string $value): bool
{
    return trim($value) !== '' && preg_match('/^%SECRET_[^%]+%$/', trim($value)) !== 1;
}

/** Bounded HTTPS JSON request: no redirects, 2 MB response cap. */
function httpJson(string $method, string $url, array $headers, ?string $body = null, bool $verifyTls = true, int $timeout = 20): array
{
    $ctx = stream_context_create([
        'http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
                   'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0],
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

// ─── 5. COLLECT: pending assessments and their answers/findings ─────────────
function pendingAssessments(array $config): array
{
    return array_values(array_filter(erambaAll('vendor-assessments'), function ($oa) use ($config) {
        $level = trim((string)($oa[$config['OA_RISK_FIELD']] ?? ''));
        return in_array((int)$oa['id'], $config['FORCE_OA_IDS'], true)
            || ((int)($oa['submited'] ?? 0) === 1 && in_array($level, $config['UNREVIEWED_VALUES'], true));
    }));
}

/** Items of $resource that belong to assessment $oaId. */
function forAssessment(string $resource, int $oaId): array
{
    try {
        $items = erambaAll($resource, ['vendor_assessment_id' => ['operator' => '$eq', 'value' => $oaId]]);
    } catch (RuntimeException) {
        $items = erambaAll($resource); // Filter not supported by this eramba version
    }
    return array_values(array_filter($items, fn ($i) => (int)($i['vendor_assessment_id'] ?? 0) === $oaId));
}

function openFindings(int $oaId): array
{
    return array_values(array_filter(forAssessment('vendor-assessment-findings', $oaId),
        fn ($f) => (int)($f['status'] ?? 1) !== 0 && empty($f['closed'] ?? null)));
}

// ─── 6. EVALUATE: risk level + conclusion ───────────────────────────────────
function reviewByScore(array $oa, array $findings, array $config): array
{
    $max   = (float)($oa['max_total_score'] ?? 0);
    $pct   = $max > 0 ? round(100 * (float)($oa['total_score'] ?? 0) / $max) : null;
    $level = match (true) {
        count($findings) > 0 || ($pct !== null && $pct < $config['HIGH_BELOW_PCT']) => 'High',
        $pct !== null && $pct < $config['MEDIUM_BELOW_PCT']                         => 'Medium',
        default                                                                      => 'Low',
    };
    return [$level, sprintf('Automatic review by %s v%s: score %s%%, %d open finding(s). Risk level: %s.',
        AUTOMATION_ID, AUTOMATION_VERSION, $pct ?? 'n/a', count($findings), $level)];
}

function reviewByAi(array $oa, array $feedbacks, array $findings, string $apiKey, array $config): array
{
    $answers = array_map(fn ($f) => sprintf("Q%s %s\nA: %s", $f['question_number'] ?? '', $f['question_title'] ?? '',
        trim((string)($f['answer'] ?? '')) ?: '(no answer)'), $feedbacks);
    $prompt = "You are a GRC analyst reviewing a supplier security questionnaire.\n"
        . "Score: " . ($oa['total_score'] ?? 'n/a') . ' / ' . ($oa['max_total_score'] ?? 'n/a') . ". Open findings: "
        . ($findings ? implode('; ', array_column($findings, 'title')) : 'none') . "\n\n"
        . implode("\n\n", $answers)
        . "\n\nReply ONLY with JSON: {\"risk_level\": \"Low|Medium|High\", \"conclusion\": \"<max 600 chars>\"}";

    $res = httpJson('POST', 'https://api.openai.com/v1/chat/completions', [
        'Content-Type: application/json', 'Authorization: Bearer ' . trim($apiKey),
    ], json_encode([
        'model'                 => $config['OPENAI_MODEL'],
        'reasoning_effort'      => $config['OPENAI_REASONING'],
        'max_completion_tokens' => 4000,
        'response_format'       => ['type' => 'json_object'],
        'messages'              => [['role' => 'user', 'content' => $prompt]],
    ], JSON_THROW_ON_ERROR), true, 90);

    $json  = json_decode((string)($res['choices'][0]['message']['content'] ?? ''), true);
    $level = ucfirst(strtolower((string)($json['risk_level'] ?? '')));
    if (!in_array($level, LEVELS, true) || trim((string)($json['conclusion'] ?? '')) === '') {
        throw new RuntimeException('Unexpected AI answer; the assessment was left unreviewed.');
    }
    return [$level, sprintf('AI review by %s v%s (%s): %s', AUTOMATION_ID, AUTOMATION_VERSION,
        $config['OPENAI_MODEL'], substr(trim((string)$json['conclusion']), 0, 600))];
}

// ─── 7. APPLY: assessment first, then its suppliers ─────────────────────────
function save(array $oa, string $level, string $conclusion, array $config): void
{
    erambaApi('PUT', "/api/v2/vendor-assessments/{$oa['id']}", [
        $config['OA_RISK_FIELD'] => $level,
        'review_notes'           => $conclusion,
    ]);
    foreach ($oa['third_parties'] ?? [] as $tp) {
        erambaApi('PUT', "/api/v2/third-parties/{$tp['id']}", [
            $config['TP_RISK_FIELD']  => $level,
            $config['TP_REVIEW_DATE'] => gmdate('Y-m-d'),
        ]);
    }
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
try {
    echo sprintf("%s v%s\n", AUTOMATION_ID, AUTOMATION_VERSION);
    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no review will be saved.' : 'LIVE RUN — reviews will be saved.');

    logStep(1, 'Checking configuration');
    checkSecrets($secrets);
    $useAi = secretExists($openAiApiKey);
    logInfo('Review mode: ' . ($useAi ? "AI ({$config['OPENAI_MODEL']}, reasoning {$config['OPENAI_REASONING']})" : 'score/findings rule (no openai_api_key secret)'));

    logStep(2, 'Collecting submitted Online Assessments');
    $pending = pendingAssessments($config);
    logInfo(count($pending) . ' assessment(s) to review.');

    logStep(3, 'Reviewing and saving');
    $reviewed = $errors = 0;
    foreach ($pending as $oa) {
        try {
            $findings = openFindings((int)$oa['id']);
            [$level, $conclusion] = $useAi
                ? reviewByAi($oa, forAssessment('vendor-assessment-feedbacks', (int)$oa['id']), $findings, $openAiApiKey, $config)
                : reviewByScore($oa, $findings, $config);
            $suppliers = implode(', ', array_column($oa['third_parties'] ?? [], 'name')) ?: 'none';
            if ($config['DRY_RUN']) {
                logInfo("DRY RUN: #{$oa['id']} \"{$oa['title']}\" would be $level | suppliers: $suppliers");
            } else {
                save($oa, $level, $conclusion, $config);
                logInfo("REVIEWED: #{$oa['id']} \"{$oa['title']}\" -> $level | suppliers: $suppliers");
            }
            logInfo('  ' . $conclusion);
            $reviewed++;
        } catch (Throwable $e) {
            $errors++;
            logInfo("ERROR: #{$oa['id']} -> " . $e->getMessage());
        }
    }

    echo sprintf("\nReviewed: %d | errors: %d\n", $reviewed, $errors);
    if ($errors > 0) {
        fwrite(STDERR, "ERROR: $errors review(s) failed; see the log above.\n");
        exit(1);
    }
    echo $config['DRY_RUN'] ? "\nSIMULATION COMPLETE — nothing saved.\n" : "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo "\nABORTED: technical error; reviews saved before it are kept (see STDERR).\n";
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
