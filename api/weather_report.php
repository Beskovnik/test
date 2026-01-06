<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Response;

header('Content-Type: application/json; charset=utf-8');

// 1. Input Validation
$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

// Default to Ljubljana if missing
if ($lat === null || $lon === null) {
    $lat = 46.0569;
    $lon = 14.5058;
}

$lat = (float)$lat;
$lon = (float)$lon;

if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    Response::error('Invalid coordinates', 'INVALID_COORDS');
}

// 2. Caching Setup
$cacheDir = __DIR__ . '/../_data/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// Sanitize filename
$cacheKey = 'weather_' . number_format($lat, 4, '.', '') . '_' . number_format($lon, 4, '.', '');
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

$now = time();
$ttl = 180; // 3 minutes

// 3. Serve Cache if Fresh
if (file_exists($cacheFile)) {
    $mtime = filemtime($cacheFile);
    if (($now - $mtime) < $ttl) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            $data['cached'] = true; // Debug flag
            Response::json($data);
        }
    }
}

// 4. Fetch from Upstream
$url = "https://opendata.si/vreme/report/?lat={$lat}&lon={$lon}";
$opts = [
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'header' => "User-Agent: GalerijaApp/1.0\r\n"
    ]
];
$context = stream_context_create($opts);

$raw = @file_get_contents($url, false, $context);
$data = $raw ? json_decode($raw, true) : null;

// 5. Handle Success
if ($data) {
    file_put_contents($cacheFile, $raw);
    $data['cached'] = false;
    Response::json($data);
}

// 6. Handle Failure (Stale Cache Fallback)
if (file_exists($cacheFile)) {
    $mtime = filemtime($cacheFile);
    // Allow stale cache up to 1 hour
    if (($now - $mtime) < 3600) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            $data['stale'] = true;
            $data['cached'] = true;
            Response::json($data);
        }
    }
}

// 7. Error Response
Response::error('Failed to fetch weather data', 'UPSTREAM_ERROR', 502);
