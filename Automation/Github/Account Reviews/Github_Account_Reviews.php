<?php
declare(strict_types=1);

/**
 * Eramba Automation - GitHub Accounts vs Active Employees
 *
 * Variables MUST support:
 * - Eramba placeholders: {$VAR}
 * - Local env for testing: export VAR=...
 *
 * Output schema mirrors your example keys:
 * - Total System Accounts / Pass / Failed / results[]
 * - Each result includes calendly_* keys (kept for consistency across automations)
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function getVar(string $name, string $placeholder, ?string $default = null): ?string {
    $env = getenv($name);
    if ($env !== false && trim($env) !== '') return $env;

    $v = $placeholder;
    if (preg_match('/^\{\$[A-Z0-9_]+\}$/', $v)) return $default; // not injected
    $v = trim($v);
    return $v === '' ? $default : $v;
}

function normalizeName(?string $s): string {
    $s = $s ?? '';
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = mb_strtolower($s, 'UTF-8');

    if (class_exists('Normalizer')) {
        $s = Normalizer::normalize($s, Normalizer::FORM_D) ?: $s;
        $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
    } else {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($converted !== false) $s = $converted;
    }

    $s = preg_replace('/[^a-z0-9 ]+/i', ' ', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

function splitName(?string $full): array {
    $full = trim((string)$full);
    if ($full === '') return ['', ''];

    // "Surname, Name"
    if (strpos($full, ',') !== false) {
        [$last, $first] = array_map('trim', explode(',', $full, 2));
        return [$first ?? '', $last ?? ''];
    }

    $parts = preg_split('/\s+/u', $full) ?: [];
    if (count($parts) === 1) return [$parts[0], ''];
    $surname = array_pop($parts);
    $name = implode(' ', $parts);
    return [$name, $surname];
}

function jsonFail(string $message, array $extra = []): void {
    echo json_encode(array_merge(['error' => $message], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

// Required inputs
$employeesJsonPath = getVar('EMPLOYEES_JSON_PATH', '{$EMPLOYEES_JSON_PATH}');
$employeeEmailJsonKey = getVar('EMPLOYEE_EMAIL_JSON_KEY', '{$EMPLOYEE_EMAIL_JSON_KEY}', 'login'); // default keeps compatibility with earlier assumption
$githubOrg = getVar('GITHUB_ORG', '{$GITHUB_ORG}');
$githubToken = getVar('GITHUB_TOKEN', '{$GITHUB_TOKEN}');
$githubApiBase = getVar('GITHUB_API_BASE_URL', '{$GITHUB_API_BASE_URL}', 'https://api.github.com');
$nameNormalization = getVar('NAME_MATCH_NORMALIZATION', '{$NAME_MATCH_NORMALIZATION}', 'true');

if (!$employeesJsonPath || !is_file($employeesJsonPath)) jsonFail('Missing or invalid EMPLOYEES_JSON_PATH (must be a readable file).', ['EMPLOYEES_JSON_PATH' => $employeesJsonPath]);
if (!$githubOrg) jsonFail('Missing GITHUB_ORG.');
if (!$githubToken) jsonFail('Missing GITHUB_TOKEN.');

$useNormalization = in_array(mb_strtolower((string)$nameNormalization), ['1','true','yes','y'], true);

// Load employees
$employeesRaw = file_get_contents($employeesJsonPath);
if ($employeesRaw === false) jsonFail('Failed to read employees JSON file.');
$employees = json_decode($employeesRaw, true);
if (!is_array($employees)) jsonFail('Employees JSON must be a JSON array of employee objects.');

$employeesByEmail = []; // email -> employee
$employeesByName = [];  // "name|surname" -> list
$employeeList = [];

foreach ($employees as $e) {
    if (!is_array($e)) continue;

    $name = (string)($e['name'] ?? '');
    $surname = (string)($e['surname'] ?? '');
    $email = (string)($e[$employeeEmailJsonKey] ?? '');

    $emailNorm = mb_strtolower(trim($email), 'UTF-8');
    if ($emailNorm !== '') $employeesByEmail[$emailNorm] = $e;

    $n = $useNormalization ? normalizeName($name) : trim($name);
    $s = $useNormalization ? normalizeName($surname) : trim($surname);
    $k = $n . '|' . $s;
    if (!isset($employeesByName[$k])) $employeesByName[$k] = [];
    $employeesByName[$k][] = $e;

    $employeeList[] = $e;
}

$client = new Client([
    'base_uri' => rtrim($githubApiBase, '/') . '/',
    'headers' => [
        'Authorization' => 'Bearer ' . $githubToken,
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28',
        'User-Agent' => 'Eramba-Automation-GitHub-Audit'
    ],
    'timeout' => 30,
]);

function ghGet(Client $client, string $path, array $query = []): array {
    try {
        $resp = $client->get(ltrim($path, '/'), ['query' => $query]);
        $data = json_decode((string)$resp->getBody(), true);
        return is_array($data) ? $data : [];
    } catch (GuzzleException $ex) {
        jsonFail('GitHub API request failed: ' . $ex->getMessage(), ['path' => $path]);
    }
}

function closestEmployee(array $employeeList, string $targetNameNorm, string $targetEmailNorm): ?array {
    $best = null;
    $bestScore = PHP_INT_MAX;

    foreach ($employeeList as $e) {
        if (!is_array($e)) continue;

        $ename = (string)($e['name'] ?? '');
        $esurname = (string)($e['surname'] ?? '');
        $efullNorm = normalizeName(trim($ename . ' ' . $esurname));

        $elogin = (string)($e['login'] ?? '');
        $eEmailNorm = mb_strtolower(trim($elogin), 'UTF-8');

        $score = 999999;
        if ($targetEmailNorm !== '' && $eEmailNorm !== '') {
            $score = levenshtein($targetEmailNorm, $eEmailNorm);
        } elseif ($targetNameNorm !== '' && $efullNorm !== '') {
            $score = levenshtein($targetNameNorm, $efullNorm);
        }

        if ($score < $bestScore) {
            $bestScore = $score;
            $best = $e;
        }
    }
    return $best;
}

// List org members
$members = [];
$page = 1; $perPage = 100;

while (true) {
    $batch = ghGet($client, "/orgs/{$githubOrg}/members", ['per_page' => $perPage, 'page' => $page]);
    if (!is_array($batch) || count($batch) === 0) break;
    foreach ($batch as $m) {
        if (is_array($m) && isset($m['login'])) $members[] = $m;
    }
    if (count($batch) < $perPage) break;
    $page++;
    if ($page > 1000) break;
}

// Compare
$results = [];
$pass = 0; $fail = 0;

foreach ($members as $m) {
    $login = (string)($m['login'] ?? '');
    $profile = $login !== '' ? ghGet($client, "/users/{$login}") : [];

    $acctEmail = (string)($profile['email'] ?? ''); // often empty unless public
    $acctNameRaw = (string)($profile['name'] ?? '');

    [$acctFirst, $acctLast] = splitName($acctNameRaw);

    $acctEmailNorm = mb_strtolower(trim($acctEmail), 'UTF-8');

    $matchMethod = null;
    $matchedEmployee = null;

    // 1) Email match (employee email vs account email)
    if ($acctEmailNorm !== '' && isset($employeesByEmail[$acctEmailNorm])) {
        $matchedEmployee = $employeesByEmail[$acctEmailNorm];
        $matchMethod = 'Email';
    } else {
        // 2) Name/Surname match
        $nFirst = $useNormalization ? normalizeName($acctFirst) : trim($acctFirst);
        $nLast  = $useNormalization ? normalizeName($acctLast) : trim($acctLast);
        $key = $nFirst . '|' . $nLast;

        if ($nFirst !== '' && isset($employeesByName[$key]) && count($employeesByName[$key]) > 0) {
            $matchedEmployee = $employeesByName[$key][0];
            $matchMethod = 'Name/Surname';
        }
    }

    $isPass = $matchedEmployee !== null;
    if ($isPass) $pass++; else $fail++;

    $matchedEmployeeEmail = null;
    if ($matchedEmployee) {
        $matchedEmployeeEmail = (string)($matchedEmployee[$employeeEmailJsonKey] ?? '');
        if (trim($matchedEmployeeEmail) === '') $matchedEmployeeEmail = (string)($matchedEmployee['login'] ?? '');
    }

    $closestStr = null;
    if (!$isPass) {
        $targetNameNorm = $useNormalization ? normalizeName(trim($acctFirst . ' ' . $acctLast)) : trim($acctFirst . ' ' . $acctLast);
        $closest = closestEmployee($employeeList, $targetNameNorm, $acctEmailNorm);
        if (is_array($closest)) {
            $cName = trim(((string)($closest['name'] ?? '')) . ' ' . ((string)($closest['surname'] ?? '')));
            $cEmail = (string)($closest[$employeeEmailJsonKey] ?? '');
            if (trim($cEmail) === '') $cEmail = (string)($closest['login'] ?? '');
            $closestStr = trim($cName) !== '' ? ($cName . ' (' . $cEmail . ')') : $cEmail;
        }
    }

    // Output keys kept as in your example (calendly_*), but values come from GitHub account
    $results[] = [
        'calendly_email' => $acctEmail !== '' ? $acctEmail : null,
        'calendly_name' => $acctFirst !== '' ? $acctFirst : null,
        'calendly_surname' => $acctLast !== '' ? $acctLast : null,
        'calendly_raw_name' => $acctNameRaw !== '' ? $acctNameRaw : null,
        'result' => $isPass ? 'Pass' : 'Fail',
        'match_method' => $matchMethod,
        'matched_employee_login' => $matchedEmployeeEmail ?: null,
        'closest_employee' => $closestStr,
    ];
}

$out = [
    'Total System Accounts' => count($results),
    'Pass' => $pass,
    'Failed' => $fail,
    'results' => $results,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
