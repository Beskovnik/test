<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Response;

header('Content-Type: application/json; charset=utf-8');

$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

if ($lat === null || $lon === null) {
    $lat = 46.0569;
    $lon = 14.5058;
}

$lat = (float)$lat;
$lon = (float)$lon;

if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    Response::error('Invalid coordinates', 'INVALID_COORDS');
}

// Cache Configuration
$cacheDir = __DIR__ . '/../_data/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}
// Use a prefix to distinguish from old cache
$cacheKey = 'om_weather_' . number_format($lat, 4, '.', '') . '_' . number_format($lon, 4, '.', '');
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$now = time();
$ttl = 300; // 5 minutes

// Serve Fresh Cache
if (file_exists($cacheFile) && ($now - filemtime($cacheFile) < $ttl)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    if ($data) {
        $data['cached'] = true;
        Response::json($data);
    }
}

// Fetch from Open-Meteo
$url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=rain,showers,cloud_cover,weather_code&hourly=rain,showers,cloud_cover,precipitation_probability&timezone=auto&forecast_days=2";

$opts = [
    'http' => [
        'method' => 'GET',
        'timeout' => 5,
        'header' => "User-Agent: GalerijaApp/1.0\r\n"
    ]
];
$context = stream_context_create($opts);
$raw = @file_get_contents($url, false, $context);
$omData = $raw ? json_decode($raw, true) : null;

if (!$omData || isset($omData['error'])) {
     // Fallback to stale cache if available
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            $data['stale'] = true;
            Response::json($data);
        }
    }
    Response::error('Failed to fetch weather data from OpenMeteo', 'UPSTREAM_ERROR', 502);
}

// Transform Data to Internal Format
$current = $omData['current'];
$hourly = $omData['hourly'];

// Find current hour index based on ISO time string
$currentIndex = 0;
// OpenMeteo hourly times are like "2023-10-27T10:00"
// We want to find the hour that matches "current.time" roughly, or just use the one that matches local time.
// Since we used timezone=auto, the times are local.
// Let's match based on the hour prefix "YYYY-MM-DDTHH"
$currentIsoPrefix = substr($current['time'], 0, 13);

foreach ($hourly['time'] as $idx => $t) {
    if (strpos($t, $currentIsoPrefix) === 0) {
        $currentIndex = $idx;
        break;
    }
}

// Radar / Current Data
$rainMm = ($current['rain'] ?? 0) + ($current['showers'] ?? 0);
$prob = $hourly['precipitation_probability'][$currentIndex] ?? 0;

$radar = [
    'rain_intensity_mmph' => $rainMm,
    'rain_probability' => $prob,
    'time' => $current['time']
];

// Hail (Inferred from Weather Code)
// WMO Codes: 96, 99 are thunderstorm with hail
$code = $current['weather_code'] ?? 0;
$hailLevel = 0;
$hailProb = 0;
if ($code == 96) { $hailLevel = 2; $hailProb = 50; }
if ($code == 99) { $hailLevel = 3; $hailProb = 80; }

$hailprob = [
    'hail_level' => $hailLevel,
    'hail_probability' => $hailProb,
    'time' => $current['time']
];

// Forecast (Next 24h)
$items = [];
$maxItems = 24;
$start = $currentIndex;
for ($i = $start; $i < $start + $maxItems; $i++) {
    if (!isset($hourly['time'][$i])) break;
    $items[] = [
        'time' => $hourly['time'][$i],
        'rain' => ($hourly['rain'][$i] ?? 0) + ($hourly['showers'][$i] ?? 0),
        'clouds' => $hourly['cloud_cover'][$i] ?? 0
    ];
}

$finalData = [
    'radar' => $radar,
    'hailprob' => $hailprob,
    'forecast' => ['items' => $items],
    'updated' => $current['time'],
    'copyright' => 'Open-Meteo.com'
];

file_put_contents($cacheFile, json_encode($finalData));
Response::json($finalData);
