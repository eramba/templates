<?php
require __DIR__ . '/vendor/autoload.php';

function err($msg) {
    fwrite(STDERR, $msg . PHP_EOL);
    exit(1);
}

$saJson = getenv('GOOGLE_SERVICE_ACCOUNT_JSON');
$admin  = getenv('GOOGLE_WORKSPACE_ADMIN_EMAIL');
$sheetId = getenv('GOOGLE_SHEET_ID');
$range  = getenv('GOOGLE_SHEET_RANGE') ?: 'Sheet1!A:F';

if (!$saJson || !$admin || !$sheetId) {
    err('Missing required environment variables');
}

$client = new Google_Client();
$client->setAuthConfig(json_decode($saJson, true));
$client->setSubject($admin);
$client->setScopes([
    Google_Service_Sheets::SPREADSHEETS_READONLY,
    Google_Service_Drive::DRIVE_READONLY
]);

$sheets = new Google_Service_Sheets($client);

try {
    $response = $sheets->spreadsheets_values->get($sheetId, $range);
} catch (Exception $e) {
	echo "here gaild";
    err($e->getMessage());
}

$rows = $response->getValues();
$out = [];

foreach ($rows as $i => $row) {
    if ($i === 0) continue; // header

    if (count($row) < 5) {
        err("Invalid row at index $i");
    }

    $out[] = [
        'name'        => $row[0],
        'surname'     => $row[1],
        'login'       => $row[2],
        'roles'       => array_map('trim', explode('|', $row[3])),
        'worker_type' => $row[4],
        'os'          => $row[5] ?? null
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT) . PHP_EOL;
