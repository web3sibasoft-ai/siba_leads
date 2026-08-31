<?php

defined('BASEPATH') or define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/application/config/app-config.php';

$prefix = defined('APP_DB_PREFIX') ? APP_DB_PREFIX : 'tbl';
$table  = $prefix . 'leads';

$mysqli = new mysqli(APP_DB_HOSTNAME, APP_DB_USERNAME, APP_DB_PASSWORD, APP_DB_NAME);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset(defined('APP_DB_CHARSET') ? APP_DB_CHARSET : 'utf8mb4');

$exclude = [
    'id',
    'assigned',
    'dateadded',
    'last_status_change',
    'addedfrom',
    'leadorder',
    'date_converted',
    'lost',
    'junk',
    'is_imported_from_email_integration',
    'email_integration_uid',
    'is_public',
    'dateassigned',
    'client_id',
    'lastcontact',
    'last_lead_status',
    'from_form_id',
    'default_language',
    'hash',
    'converted_by_lead_manager',
];

$result = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
if (!$result) {
    fwrite(STDERR, 'Cannot read table columns: ' . $mysqli->error . PHP_EOL);
    exit(1);
}

$fields = [];
while ($row = $result->fetch_assoc()) {
    $field = $row['Field'];
    if (!in_array($field, $exclude, true)) {
        $fields[] = $field;
    }
}
$fields[] = 'tags';

$rows = [
    [
        'name'        => 'لید تست ۱',
        'email'       => 'lead.test.1@example.com',
        'phonenumber' => '09120000001',
        'company'     => 'شرکت آلفا',
        'description' => 'تست واردسازی ۱',
        'city'        => 'Tehran',
        'country'     => 'IR',
        'website'     => 'https://alpha.example.com',
        'tags'        => 'test,siba,sales',
    ],
    [
        'name'        => 'لید تست ۲',
        'email'       => 'lead.test.2@example.com',
        'phonenumber' => '09120000002',
        'company'     => 'شرکت بتا',
        'description' => 'تست واردسازی ۲',
        'city'        => 'Shiraz',
        'country'     => 'IR',
        'website'     => 'https://beta.example.com',
        'tags'        => 'test,import,roundrobin',
    ],
    [
        'name'        => 'Lead Test 3',
        'email'       => 'lead.test.3@example.com',
        'phonenumber' => '09120000003',
        'company'     => 'Gamma LLC',
        'description' => 'Import smoke test 3',
        'city'        => 'Tabriz',
        'country'     => 'IR',
        'website'     => 'https://gamma.example.com',
        'tags'        => 'test,backlog',
    ],
];

$outPath = __DIR__ . '/test_leads_import_safe.csv';
$fp      = fopen($outPath, 'wb');
if (!$fp) {
    fwrite(STDERR, 'Cannot open output file: ' . $outPath . PHP_EOL);
    exit(1);
}

// UTF-8 BOM for Excel/Persian compatibility.
fwrite($fp, "\xEF\xBB\xBF");
fputcsv($fp, $fields);

foreach ($rows as $source) {
    $line = [];
    foreach ($fields as $field) {
        $line[] = $source[$field] ?? '';
    }
    fputcsv($fp, $line);
}

fclose($fp);
$mysqli->close();

echo "Generated: {$outPath}" . PHP_EOL;
