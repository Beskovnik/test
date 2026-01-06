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
    // Custom error format as per prompt: { ok: false, ... }
    echo json_encode(['ok' => false, 'success' => false, 'error_message' => 'Action not allowed', 'debug' => null]);
    exit;
}

// 3. Build Query Parameters
$params = [];
$incoming = $_GET;

switch ($action) {
    case 'datastore_search':
        $params['resource_id'] = $incoming['resource_id'] ?? null;
        if (!$params['resource_id']) {
            echo json_encode(['ok' => false, 'success' => false, 'error_message' => 'Missing resource_id', 'debug' => null]);
            exit;
        }

        if (isset($incoming['limit'])) $params['limit'] = (int)$incoming['limit'];
        if (isset($incoming['offset'])) $params['offset'] = (int)$incoming['offset'];
        // Strict sort validation
        if (isset($incoming['sort'])) {
             // Allow alphanumeric, space, underscore, desc, asc
             if (preg_match('/^[a-zA-Z0-9_ ]+$/', $incoming['sort'])) {
                 $params['sort'] = $incoming['sort'];
             }
        }
        if (isset($incoming['q'])) $params['q'] = substr($incoming['q'], 0, 100);

        // Filters must be a valid JSON string if provided, or sanitized
        if (isset($incoming['filters'])) {
            $params['filters'] = $incoming['filters'];
        }
        // Fields for efficiency
        if (isset($incoming['fields'])) {
             $params['fields'] = preg_replace('/[^a-zA-Z0-9_,]/', '', $incoming['fields']);
        }
        break;

    case 'package_search':
        $params['q'] = $incoming['q'] ?? '*:*';
        $params['rows'] = (int)($incoming['rows'] ?? 10);
        $params['start'] = (int)($incoming['start'] ?? 0);
        if ($params['rows'] > 50) $params['rows'] = 50; // Rate limit protection
        break;

    case 'package_show':
    case 'resource_show':
        $params['id'] = $incoming['id'] ?? null;
        if (!$params['id']) {
            echo json_encode(['ok' => false, 'success' => false, 'error_message' => 'Missing id', 'debug' => null]);
            exit;
        }
        break;
}

// 4. Caching Logic
$cacheDir = __DIR__ . '/../_data/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
    // Attempt to set permissions if possible
    @chmod($cacheDir, 0777);
}

// Cache Key
ksort($params);
$cacheKey = 'ckan_' . $action . '_' . md5(json_encode($params));
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

$now = time();
$ttl = ($action === 'datastore_search') ? 60 : 300;

// Serve Cache
if (file_exists($cacheFile) && ($now - filemtime($cacheFile) < $ttl)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    if ($data) {
        $data['cached'] = true;
        // Prompt implies checking 'success' always.
        echo json_encode($data);
        exit;
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
    echo json_encode($data);
} else {
    // Stale Cache Fallback
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            $data['stale'] = true;
            echo json_encode($data);
            exit;
        }
    }

    $errorMsg = $data['error']['message'] ?? 'Unknown upstream error';
    // Return specific format requested: { ok: false, error_message: ..., debug: ... }
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error_message' => $errorMsg,
        'debug' => $data
    ]);
}
