<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Response;
use App\Settings;
use App\Database;

// Basic validation or rate limiting could go here
// Currently public to allow frontend to fetch without auth if needed,
// but typically weather page is protected.
// If we want to protect it:
// use App\Auth;
// $user = Auth::user();
// if (!$user) Response::error('Unauthorized', 401);

$action = $_GET['action'] ?? '';
$pdo = Database::connect();

// Cache Config
$CACHE_DIR = __DIR__ . '/../_data/cache';
if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0777, true);

// Helper for HTTP requests
function fetchUrl($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; GalleryApp/1.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Sometimes SSL verification fails in local/dev envs
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && $data) {
            return $data;
        }
        return null;
    } else {
        // Fallback
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; GalleryApp/1.0)\r\n"
            ]
        ];
        $context = stream_context_create($opts);
        return @file_get_contents($url, false, $context);
    }
}

function fetchWithCache($url, $key, $ttl = 1800) {
    global $CACHE_DIR;
    $file = $CACHE_DIR . '/arso_' . md5($key) . '.json';

    if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
        $content = file_get_contents($file);
        $json = json_decode($content, true);
        if ($json) return $json;
    }

    $data = fetchUrl($url);

    if (!$data) {
        // If fetch fails but we have stale cache, return it?
        if (file_exists($file)) {
             return json_decode(file_get_contents($file), true);
        }
        return null;
    }

    // Save
    file_put_contents($file, $data);
    return json_decode($data, true);
}

try {
    switch ($action) {
        case 'locations':
            // Fetch list of locations from ARSO
            $url = 'https://vreme.arso.gov.si/api/1.0/location/?type=si';
            $data = fetchWithCache($url, 'locations', 86400); // 24h cache

            if (!$data || !isset($data['features'])) {
                Response::error('Failed to fetch locations');
            }

            $locs = array_map(function($f) {
                return [
                    'name' => $f['properties']['title'],
                    'id' => $f['properties']['id'] // This is NOT the station ID, but the location slug/id for forecast
                ];
            }, $data['features']);

            // Sort by name
            usort($locs, function($a, $b) { return strcmp($a['name'], $b['name']); });

            Response::json(['locations' => $locs]);
            break;

        case 'data':
            // Main Weather Data (Current + Forecast)
            // We need a location query (name or slug)
            $location = $_GET['location'] ?? '';
            if (!$location) Response::error('Missing location');

            $url = "https://vreme.arso.gov.si/api/1.0/location/?location=" . urlencode($location);
            $data = fetchWithCache($url, 'weather_' . $location, 1800); // 30m cache

            // Debug if data is empty
            if (!$data) {
                // Try logging error
                error_log("Failed to fetch weather for $location from $url");
                Response::error('Failed to fetch weather data');
            }

            Response::json($data);
            break;

        case 'history':
            // Detailed history from meteo.arso.gov.si (XML)
            // Requires a Station ID (e.g., LJUBL-ANA_BEZIGRAD)
            $stationId = $_GET['station_id'] ?? '';
            if (!$stationId) Response::error('Missing station_id');

            $url = "https://meteo.arso.gov.si/uploads/probase/www/observ/surface/text/sl/observationAms_" . urlencode($stationId) . "_history.xml";

            // We cache XML raw response but we need to process it
            global $CACHE_DIR;
            $cacheFile = $CACHE_DIR . '/arso_hist_' . md5($stationId) . '.xml';
            $xmlContent = '';

            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 1800)) {
                $xmlContent = file_get_contents($cacheFile);
            } else {
                $xmlContent = fetchUrl($url);
                if ($xmlContent) {
                    file_put_contents($cacheFile, $xmlContent);
                } else {
                    Response::error('Failed to fetch history');
                }
            }

            // Parse XML
            $xml = simplexml_load_string($xmlContent);
            if ($xml === false) Response::error('Invalid XML from ARSO');

            $history = [];
            foreach ($xml->data->metData as $node) {
                $history[] = [
                    'ts' => (string)$node->tsValid_issued_UTC, // RFC format
                    'temp' => (float)$node->t_degreesC,
                    'rh' => (float)$node->rh_percent,
                    'wind_speed' => (float)$node->ff_val,
                    'pressure' => (float)$node->p_hPa,
                    'rain' => (float)$node->tp_12h_acc_mm // Sometimes available
                ];
            }

            Response::json(['history' => $history]);
            break;

        default:
            Response::error('Invalid action');
    }
} catch (Exception $e) {
    Response::error($e->getMessage());
}
