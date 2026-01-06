<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Response;
use App\Settings;
use App\Database;

header('Content-Type: application/json; charset=utf-8');

// 1. Load Configuration
$pdo = Database::connect();
$baseUrl = Settings::get($pdo, 'ckan_base_url', 'https://podatki.gov.si');
$apiKey = Settings::get($pdo, 'ckan_api_key', '');

// Trim trailing slash
$baseUrl = rtrim($baseUrl, '/');

// 2. Input Validation & Whitelist
$action = $_GET['action'] ?? '';
$allowedActions = [
    'package_search',
    'package_show',
    'resource_show',
    'datastore_search'
];

if (!in_array($action, $allowedActions)) {
    Response::error('Action not allowed', 'INVALID_ACTION', 403);
}

// 3. Build Query Parameters
// We only pass specific parameters to avoid injection or abuse
$params = [];
$incoming = $_GET; // or $_POST if we supported it, prompt says use GET for 'get' actions

switch ($action) {
    case 'datastore_search':
        $params['resource_id'] = $incoming['resource_id'] ?? null;
        if (!$params['resource_id']) Response::error('Missing resource_id', 'MISSING_PARAM');

        if (isset($incoming['limit'])) $params['limit'] = (int)$incoming['limit'];
        if (isset($incoming['offset'])) $params['offset'] = (int)$incoming['offset'];
        if (isset($incoming['sort'])) $params['sort'] = $incoming['sort'];
        if (isset($incoming['q'])) $params['q'] = $incoming['q'];
        if (isset($incoming['filters'])) $params['filters'] = $incoming['filters']; // JSON string? CKAN expects object usually.
        // Note: fetch() usually sends query params as string. If frontend sends ?filters={"station":"Lj"} that is a string.
        break;

    case 'package_search':
        $params['q'] = $incoming['q'] ?? '*:*';
        $params['rows'] = (int)($incoming['rows'] ?? 10);
        $params['start'] = (int)($incoming['start'] ?? 0);
        break;

    case 'package_show':
    case 'resource_show':
        $params['id'] = $incoming['id'] ?? null;
        if (!$params['id']) Response::error('Missing id', 'MISSING_PARAM');
        break;
}

// 4. Caching Logic
$cacheDir = __DIR__ . '/../_data/cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);

// Cache Key: action + sorted params
ksort($params);
$cacheKey = 'ckan_' . $action . '_' . md5(json_encode($params));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

$now = time();
$ttl = ($action === 'datastore_search') ? 60 : 300; // 60s for data, 5m for meta

if (file_exists($cacheFile) && ($now - filemtime($cacheFile) < $ttl)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    if ($data) {
        $data['cached'] = true;
        Response::json($data);
    }
}

// 5. Proxy Request
$url = $baseUrl . '/api/3/action/' . $action . '?' . http_build_query($params);

$opts = [
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => "User-Agent: GalerijaApp/1.0\r\n"
    ]
];

if (!empty($apiKey)) {
    $opts['http']['header'] .= "Authorization: " . $apiKey . "\r\n";
    $opts['http']['header'] .= "X-CKAN-API-Key: " . $apiKey . "\r\n";
}

$context = stream_context_create($opts);
$raw = @file_get_contents($url, false, $context);
$data = $raw ? json_decode($raw, true) : null;

// 6. Handle Response
if ($data && ($data['success'] ?? false) === true) {
    file_put_contents($cacheFile, $raw);
    $data['cached'] = false;
    Response::json($data);
} else {
    // If CKAN returns 200 but success:false, we get here.
    // If file_get_contents fails (500 etc), $data is null.

    // Check if we have stale cache
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            $data['stale'] = true;
            Response::json($data);
        }
    }

    $errorMsg = $data['error']['message'] ?? 'Unknown upstream error';
    Response::json([
        'ok' => false,
        'success' => false,
        'error_message' => $errorMsg,
        'debug' => $data
    ]);
}
