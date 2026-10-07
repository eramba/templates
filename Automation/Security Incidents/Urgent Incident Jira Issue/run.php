<?php
declare(strict_types=1); // Keep on line 2: eramba inserts its includes right after it.

/**
 * ============================================================================
 *  Urgent Incident Jira Issue
 *  Technology: Atlassian Jira Cloud + eramba API
 *  id: incident-urgent-jira-issue        version: 0.1.0
 *  TUTORIAL TEMPLATE – automation of the eramba course
 *  "Security Incident Management in eramba" (https://www.eramba.org/learning/courses/94).
 *  Built for that tutorial's scenario: review and adapt before production use.
 *  Docs: README.md in the same folder (secrets, permissions, variables).
 *  Repository: https://github.com/eramba/templates/tree/master/Automation/Security%20Incidents
 *
 *  Section: Security Incidents. Not recurrent: run by the notification
 *  "New Item Created" (Trigger Automation), once per created incident.
 *  Composer packages: none
 * ============================================================================
 *
 *  For the incident that fired the notification:
 *    1. Skips it unless "Incident Priority" is Urgent and "Jira Issue Key" is empty.
 *    2. Reuses the Jira issue labelled for this incident, or creates one with the
 *       incident title, description and eramba link.
 *    3. Saves the issue key in "Jira Issue Key".
 *  Re-running is safe: an incident never gets a second Jira issue.
 *
 *  Output
 *    STDOUT  Step-by-step log (visible in eramba Automation Logs, first 10 KB).
 *    STDERR  Only technical errors. Any STDERR output marks the run as failed.
 *
 *  Exit codes
 *    0  Run completed (dry-run, issue created, or skipped because not needed).
 *    1  Technical error. If the issue was created but not saved, a re-run
 *       finds it by its label and saves it (see README §8).
 */

// ─── 1. SECRETS ─────────────────────────────────────────────────────────────
// Create them in Settings / Application Configuration / Automation Secrets.
$secrets = [
    'JIRA_SITE_URL'    => '%SECRET_jira_site_url%', // e.g. https://example.atlassian.net
    'JIRA_EMAIL'       => '%SECRET_jira_email%',
    'JIRA_API_TOKEN'   => '%SECRET_jira_api_token%',
    'ERAMBA_API_TOKEN' => '%SECRET_eramba_api_token%',
];

// ─── 2. VARIABLES (README §7) ───────────────────────────────────────────────
$config = [
    // Jira
    'PROJECT_KEY'        => 'KAN',               // Project where issues are created
    'ISSUE_TYPE'         => 'Task',              // Issue type name in that project
    // Custom fields, by name (their IDs differ between installations)
    'PRIORITY_FIELD'     => 'Incident Priority', // Dropdown: Normal / Urgent
    'JIRA_KEY_FIELD'     => 'Jira Issue Key',    // Short text, filled by this script
    'URGENT_VALUE'       => 'Urgent',            // Priority value that creates an issue
    // eramba
    'MAX_ITEMS'          => 5000,                // Abort above this many incidents
    'ERAMBA_API_URL'     => '',                  // Empty = runner-provided ERAMBA_BASE_URL
    'ERAMBA_API_VERIFY_TLS' => true,             // See README §9 before changing
    'ERAMBA_UI_URL'      => '',                  // eramba address for the link in the issue; empty = ERAMBA_API_URL
    // Output
    'DRY_RUN'            => false,               // True = read and log only, no writes
];

// ─── 3. ERAMBA MACROS ───────────────────────────────────────────────────────
// Replaced by eramba with the incident that fired the notification.
$incidentId = '%SECURITYINCIDENT_ID%';
const AUTOMATION_ID      = 'incident-urgent-jira-issue';
const AUTOMATION_VERSION = '0.1.0';

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

/** Every item of a collection, bounded by MAX_ITEMS (no partial population). */
function erambaAll(string $resource): array
{
    global $config;
    $items = [];
    for ($page = 1; $page <= 100; $page++) {
        $res = erambaApi('GET', "/api/v2/$resource/index", null, ['page' => $page, 'limit' => 100]);
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

/** Jira Cloud REST API v3 with an Atlassian account email and API token. */
function jiraApi(string $method, string $path, ?array $payload = null, array $query = []): array
{
    global $config, $secrets;
    $auth = base64_encode(trim($secrets['JIRA_EMAIL']) . ':' . trim($secrets['JIRA_API_TOKEN']));
    return httpJson($method, rtrim(trim($secrets['JIRA_SITE_URL']), '/') . "/rest/api/3/$path" . ($query ? '?' . http_build_query($query) : ''), [
        'Accept: application/json', 'Content-Type: application/json', "Authorization: Basic $auth",
    ], $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR));
}

// ─── 5. COLLECT: the incident and any Jira issue already created for it ─────
function loadIncident(string $incidentId): array
{
    if (!ctype_digit($incidentId)) {
        throw new RuntimeException('No incident in context: run this automation from the "New Item Created" notification, or Test it on an item (README §6).');
    }
    $match = array_values(array_filter(erambaAll('security-incidents'), fn ($i) => (int)$i['id'] === (int)$incidentId));
    return $match[0] ?? throw new RuntimeException("Incident #$incidentId not found through the API.");
}

/** Label that ties a Jira issue to its incident, so a retry finds it instead of creating another. */
function issueLabel(int $incidentId): string
{
    return 'eramba-incident-' . $incidentId;
}

function existingIssueKey(int $incidentId, array $config): ?string
{
    $res = jiraApi('GET', 'search/jql', null, [
        'jql'        => sprintf('project = "%s" AND labels = "%s"', $config['PROJECT_KEY'], issueLabel($incidentId)),
        'fields'     => 'key',
        'maxResults' => 1,
    ]);
    return $res['issues'][0]['key'] ?? null;
}

// ─── 6. EVALUATE: does this incident need a Jira issue? ─────────────────────
/** Reason to skip the incident, or null if it needs an issue. */
function skipReason(array $incident, array $config): ?string
{
    $priority = trim((string)($incident[$config['PRIORITY_FIELD']] ?? ''));
    if (strcasecmp($priority, $config['URGENT_VALUE']) !== 0) {
        return "priority is '" . ($priority ?: 'empty') . "', not {$config['URGENT_VALUE']}";
    }
    $key = trim((string)($incident[$config['JIRA_KEY_FIELD']] ?? ''));
    if ($key !== '') {
        return "Jira issue already recorded ($key)";
    }
    return null;
}

// ─── 7. APPLY: create the issue, then save its key on the incident ──────────
function createIssue(array $incident, array $config): string
{
    $base = rtrim($config['ERAMBA_UI_URL'] ?: ($config['ERAMBA_API_URL'] ?: (string)getenv('ERAMBA_BASE_URL')), '/');
    $link = "$base/security-incidents/index?id={$incident['id']}";
    $paragraph = fn (array $content) => ['type' => 'paragraph', 'content' => $content];
    $text = fn (string $value, array $marks = []) => ['type' => 'text', 'text' => $value] + ($marks ? ['marks' => $marks] : []);

    $description = substr(trim(strip_tags((string)($incident['description'] ?? ''))), 0, 5000);
    $res = jiraApi('POST', 'issue', ['fields' => [
        'project'     => ['key' => $config['PROJECT_KEY']],
        'issuetype'   => ['name' => $config['ISSUE_TYPE']],
        'summary'     => substr((string)$incident['title'], 0, 250),
        'labels'      => [issueLabel((int)$incident['id'])],
        'description' => ['type' => 'doc', 'version' => 1, 'content' => array_values(array_filter([
            $description !== '' ? $paragraph([$text($description)]) : null,
            $paragraph([
                $text("eramba security incident #{$incident['id']}: "),
                $text($link, [['type' => 'link', 'attrs' => ['href' => $link]]]),
            ]),
        ]))],
    ]]);
    return (string)($res['key'] ?? throw new RuntimeException('Jira issue created but no key returned.'));
}

function saveKey(string $incidentId, string $key, array $config): void
{
    erambaCall(editObjectMacro([$config['JIRA_KEY_FIELD'] => $key], $incidentId), "save Jira Issue Key on incident #$incidentId");
}

// ─── 8. MAIN ────────────────────────────────────────────────────────────────
try {
    echo sprintf("%s v%s\n", AUTOMATION_ID, AUTOMATION_VERSION);
    logInfo($config['DRY_RUN'] ? 'SIMULATION ONLY — no Jira issue will be created.' : 'LIVE RUN — the Jira issue will be created.');

    logStep(1, 'Checking configuration');
    checkSecrets($secrets);
    foreach (['PRIORITY_FIELD', 'JIRA_KEY_FIELD'] as $key) {
        $config[$key] = customField('security-incidents', $config[$key]);
    }
    logInfo('Secrets and custom fields present.');

    logStep(2, "Reading incident #$incidentId");
    $incident = loadIncident($incidentId);

    logStep(3, 'Evaluating');
    $reason = skipReason($incident, $config);
    if ($reason !== null) {
        logInfo("SKIPPED: \"{$incident['title']}\" – $reason.");
        echo "\nDone.\n";
        exit(0);
    }

    logStep(4, 'Creating the Jira issue');
    $key = existingIssueKey((int)$incident['id'], $config);
    if ($key !== null) {
        logInfo("FOUND: $key already exists for this incident (earlier run); it is saved, not created again.");
    }
    if ($config['DRY_RUN']) {
        logInfo($key === null
            ? "DRY RUN: would create a {$config['ISSUE_TYPE']} in {$config['PROJECT_KEY']} for \"{$incident['title']}\"."
            : "DRY RUN: would save $key on the incident.");
        echo "\nSIMULATION COMPLETE — nothing saved.\n";
        exit(0);
    }
    if ($key === null) {
        $key = createIssue($incident, $config);
        logInfo("CREATED: $key for \"{$incident['title']}\".");
    }

    logStep(5, 'Saving the issue key on the incident');
    saveKey($incidentId, $key, $config);
    logInfo("SAVED: Jira Issue Key = $key.");
    echo "\nDone.\n";
    exit(0);
} catch (Throwable $e) {
    echo "\nABORTED: technical error (see STDERR). Re-running is safe.\n";
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
