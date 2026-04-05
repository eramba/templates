\
<?php
/**
 * Eramba single-run automation: Compare Calendly users vs active employees JSON
 *
 * Required env vars (Eramba secrets use {$...} placeholders):
 *  - {$CALENDLY_ACCESS_TOKEN}  -> CALENDLY_ACCESS_TOKEN
 *  - {$EMPLOYEES_JSON_PATH}    -> EMPLOYEES_JSON_PATH
 *
 * Optional:
 *  - {$CALENDLY_API_BASE_URL}  -> CALENDLY_API_BASE_URL (default https://api.calendly.com)
 *  - {$CALENDLY_PAGE_SIZE}     -> CALENDLY_PAGE_SIZE (default 100, max 100)
 *
 * Matching rules (in order):
 *  1) Email: exact match Calendly account email == employee.login (case-insensitive)
 *  2) Name/Surname: exact match on name + surname after accent-insensitive folding
 *
 * Output (STDOUT):
 * {
 *   "Total Calendly Accounts": <int>,
 *   "Pass": <int>,
 *   "Failed": <int>,
 *   "results": [
 *     {
 *       "calendly_email": "...",
 *       "calendly_name": "...",
 *       "calendly_surname": "...",
 *       "calendly_raw_name": "...",
 *       "result": "Pass|Fail",
 *       "match_method": "Email|Name/Surname|null",
 *       "matched_employee_login": "...|null"
 *     }, ...
 *   ]
 * }
 *
 * Errors go to STDERR and exit non-zero.
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function stderr_line(string $msg): void { fwrite(STDERR, $msg . PHP_EOL); }
function fail(string $msg, int $code = 1): void { stderr_line($msg); exit($code); }
function env_or_default(string $k, ?string $d=null): ?string {
  $v = getenv($k);
  return ($v === false || $v === '') ? $d : $v;
}
function norm_email(string $s): string {
  return trim(mb_strtolower($s, 'UTF-8'));
}
function fold(string $s): string {
  // Lowercase + trim
  $s = trim(mb_strtolower($s, 'UTF-8'));
  // Transliterate accents away (best-effort)
  $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  if ($t !== false && $t !== '') $s = $t;
  // Keep letters/numbers/spaces only
  $s = preg_replace('/[^a-z0-9\s]/', ' ', $s) ?? $s;
  // Collapse whitespace
  $s = preg_replace('/\s+/', ' ', $s) ?? $s;
  return trim($s);
}
function split_name(string $full): array {
  $full = trim($full);
  if ($full === '') return ['', ''];
  $parts = preg_split('/\s+/u', $full) ?: [];
  if (count($parts) === 1) return [$parts[0], ''];
  $surname = array_pop($parts);
  return [implode(' ', $parts), $surname];
}

/** Accepts: array, {employees:[...]}, single object, NDJSON */
function load_employees(string $path): array {
  if (!is_file($path)) fail("Employees JSON file not found at path: {$path}");
  $rawText = file_get_contents($path);
  if ($rawText === false) fail("Failed to read employees JSON file: {$path}");
  $rawTrim = trim($rawText);

  $employeesRaw = null;

  $decoded = json_decode($rawTrim, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    if (is_array($decoded) && isset($decoded['employees']) && is_array($decoded['employees'])) {
      $employeesRaw = $decoded['employees'];
    } elseif (is_array($decoded) && isset($decoded['login'], $decoded['name'], $decoded['surname'])) {
      $employeesRaw = [$decoded];
    } elseif (is_array($decoded) && array_is_list($decoded)) {
      $employeesRaw = $decoded;
    }
  }

  if ($employeesRaw === null) {
    // NDJSON fallback
    $employeesRaw = [];
    $lines = preg_split('/\R/u', $rawTrim) ?: [];
    foreach ($lines as $ln => $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#')) continue;
      $obj = json_decode($line, true);
      if (json_last_error() !== JSON_ERROR_NONE || !is_array($obj)) {
        fail("Employees JSON is not valid JSON/NDJSON. First bad line: " . ($ln + 1));
      }
      $employeesRaw[] = $obj;
    }
  }

  $employees = [];
  foreach ($employeesRaw as $e) {
    if (!is_array($e)) continue;
    $login = isset($e['login']) ? trim((string)$e['login']) : '';
    $name = isset($e['name']) ? trim((string)$e['name']) : '';
    $surname = isset($e['surname']) ? trim((string)$e['surname']) : '';
    if ($login === '' || $name === '' || $surname === '') continue;

    $employees[] = [
      'login' => $login,
      'login_email_norm' => norm_email($login),
      'name' => $name,
      'surname' => $surname,
      'name_fold' => fold($name),
      'surname_fold' => fold($surname),
    ];
  }
  return $employees;
}

function calendly_get(Client $http, string $url, array $query = []): array {
  try {
    $resp = $http->get($url, ['query' => $query]);
    $body = (string)$resp->getBody();
    $data = json_decode($body, true);
    if (!is_array($data)) fail("Calendly API returned non-JSON response for {$url}");
    return $data;
  } catch (GuzzleException $e) {
    $msg = "Calendly API request failed for {$url}: " . $e->getMessage();
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
      $resp = $e->getResponse();
      $msg .= " (HTTP " . $resp->getStatusCode() . ") body=" . substr((string)$resp->getBody(), 0, 300);
    }
    fail($msg);
  }
}

function calendly_get_soft(Client $http, string $url, array $query = []): array {
  try {
    $resp = $http->get($url, ['query' => $query]);
    $body = (string)$resp->getBody();
    $data = json_decode($body, true);
    if (!is_array($data)) return [null, "non-json response: " . substr($body, 0, 200)];
    return [$data, null];
  } catch (GuzzleException $e) {
    $msg = $e->getMessage();
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
      $resp = $e->getResponse();
      $msg .= " (HTTP " . $resp->getStatusCode() . ") body=" . substr((string)$resp->getBody(), 0, 200);
    }
    return [null, $msg];
  }
}

function membership_user_uri(array $m): ?string {
  if (isset($m['user']) && is_string($m['user']) && $m['user'] !== '') return $m['user'];
  if (isset($m['user']) && is_array($m['user']) && isset($m['user']['uri']) && is_string($m['user']['uri']) && $m['user']['uri'] !== '') return $m['user']['uri'];
  if (isset($m['user_uri']) && is_string($m['user_uri']) && $m['user_uri'] !== '') return $m['user_uri'];
  return null;
}

// -------- main --------
$baseUrl = rtrim(env_or_default('CALENDLY_API_BASE_URL', 'https://api.calendly.com'), '/');
$token   = env_or_default('CALENDLY_ACCESS_TOKEN');
$pageSize = (int)(env_or_default('CALENDLY_PAGE_SIZE', '100'));
if ($pageSize <= 0 || $pageSize > 100) $pageSize = 100;

$employeesPath = env_or_default('EMPLOYEES_JSON_PATH');
if (!$employeesPath) {
  $default = __DIR__ . '/eramba_employee_list.json';
  if (is_file($default)) $employeesPath = $default;
}

if (!$token) fail("Missing required env var: CALENDLY_ACCESS_TOKEN (Eramba secret {$CALENDLY_ACCESS_TOKEN})");
if (!$employeesPath) fail("Missing required env var: EMPLOYEES_JSON_PATH (Eramba secret {$EMPLOYEES_JSON_PATH})");

$employees = load_employees($employeesPath);

// Indexes
$byEmail = []; // normalized employee login-as-email => [employees]
$byNameSurname = []; // folded "name|surname" => [employees]
foreach ($employees as $e) {
  $byEmail[$e['login_email_norm']][] = $e;
  $key = $e['name_fold'] . '|' . $e['surname_fold'];
  $byNameSurname[$key][] = $e;
}

$http = new Client([
  'base_uri' => $baseUrl,
  'headers' => [
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json',
  ],
  'http_errors' => true,
  'timeout' => 30,
]);

$me = calendly_get($http, '/users/me');
$meRes = $me['resource'] ?? null;
if (!is_array($meRes)) fail("Unexpected Calendly /users/me response (missing resource)");
$orgUri = $meRes['current_organization'] ?? null;
if (!is_string($orgUri) || $orgUri === '') fail("Unexpected Calendly /users/me response (missing current_organization)");

// memberships
$memberships = [];
$next = null;
do {
  $query = ['organization' => $orgUri, 'count' => $pageSize];
  if ($next) $query['page_token'] = $next;
  $page = calendly_get($http, '/organization_memberships', $query);

  if (isset($page['collection']) && is_array($page['collection'])) {
    foreach ($page['collection'] as $m) if (is_array($m)) $memberships[] = $m;
  }

  $next = $page['pagination']['next_page_token'] ?? ($page['pagination']['next_page'] ?? null);
  if (is_string($next) && $next === '') $next = null;
} while ($next);

$results = [];
$pass = 0;
$failCount = 0;

foreach ($memberships as $m) {
  $userUri = membership_user_uri($m);

  $entry = [
    "calendly_email" => null,
    "calendly_name" => null,
    "calendly_surname" => null,
    "calendly_raw_name" => null,
    "result" => "Fail",
    "match_method" => null,
    "matched_employee_login" => null,
    // Keep closest_employee (helpful) but remove the fields you requested
    "closest_employee" => null,
  ];

  if (!$userUri) {
    $failCount++;
    $results[] = $entry;
    continue;
  }

  // normalize to path if full URL
  $path = $userUri;
  if (str_starts_with($userUri, $baseUrl)) {
    $path = substr($userUri, strlen($baseUrl));
    if ($path === '') $path = '/';
  }

  [$u, $err] = calendly_get_soft($http, $path);
  if ($u === null) {
    // Treat as Fail; we intentionally don't include notes
    $failCount++;
    $results[] = $entry;
    continue;
  }

  $r = $u['resource'] ?? null;
  if (!is_array($r)) {
    $failCount++;
    $results[] = $entry;
    continue;
  }

  $email = isset($r['email']) ? trim((string)$r['email']) : '';
  $rawName = isset($r['name']) ? trim((string)$r['name']) : '';
  [$first, $last] = split_name($rawName);

  $entry["calendly_email"] = $email !== '' ? $email : null;
  $entry["calendly_raw_name"] = $rawName !== '' ? $rawName : null;
  $entry["calendly_name"] = $first !== '' ? $first : null;
  $entry["calendly_surname"] = $last !== '' ? $last : null;

  // Determine closest employee (debug aid; not used for Pass)
  if (count($employees) > 0) {
    $best = null; $bestScore = -1;
    $emailNorm = norm_email($email);
    $nameFold = fold($first);
    $surnameFold = fold($last);
    foreach ($employees as $e) {
      $score = 0;
      if ($emailNorm !== '' && $e['login_email_norm'] === $emailNorm) $score += 100;
      if ($nameFold !== '' && $e['name_fold'] === $nameFold) $score += 40;
      if ($surnameFold !== '' && $e['surname_fold'] === $surnameFold) $score += 60;
      if ($score > $bestScore) { $bestScore = $score; $best = $e; }
    }
    if ($best) $entry["closest_employee"] = $best['name'] . ' ' . $best['surname'] . ' (' . $best['login'] . ')';
  }

  // Option 1: exact email match (Calendly email == employee.login), case-insensitive
  $emailKey = norm_email($email);
  if ($emailKey !== '' && isset($byEmail[$emailKey]) && count($byEmail[$emailKey]) === 1) {
    $entry["result"] = "Pass";
    $entry["match_method"] = "Email";
    $entry["matched_employee_login"] = $byEmail[$emailKey][0]['login'];
    $pass++;
    $results[] = $entry;
    continue;
  }

  // Option 2: exact name+surname match, accent-insensitive
  $key = fold($first) . '|' . fold($last);
  if ($key !== '|' && isset($byNameSurname[$key]) && count($byNameSurname[$key]) === 1) {
    $entry["result"] = "Pass";
    $entry["match_method"] = "Name/Surname";
    $entry["matched_employee_login"] = $byNameSurname[$key][0]['login'];
    $pass++;
    $results[] = $entry;
    continue;
  }

  // Everything else is Fail
  $entry["result"] = "Fail";
  $failCount++;
  $results[] = $entry;
}

$output = [
  "Total Calendly Accounts" => count($memberships),
  "Pass" => $pass,
  "Failed" => $failCount,
  "results" => $results
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(0);
