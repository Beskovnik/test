<?php
require __DIR__ . '/../app/Bootstrap.php';
use App\Settings;

$pdo = \App\Database::connect();

// Test 1: Set value
echo "Setting ui_scale to 1.2...\n";
Settings::set($pdo, 'ui_scale', '1.2');

// Test 2: Get value
$val = Settings::get($pdo, 'ui_scale', 'default');
echo "Got value: '$val' (Type: " . gettype($val) . ")\n";

if ($val !== '1.2') {
    echo "FAIL: Expected '1.2', got '$val'\n";
} else {
    echo "PASS: Saved and retrieved correctly.\n";
}

// Test 3: Simulation of admin/settings.php logic
$postVal = "1.1";
$uiScale = (float)$postVal;
if ($uiScale < 0.8) $uiScale = 0.8;
if ($uiScale > 1.2) $uiScale = 1.2;

echo "Processed Float: $uiScale\n";
$strVal = (string)$uiScale;
echo "Processed String: '$strVal'\n";

Settings::set($pdo, 'ui_scale', $strVal);
$val2 = Settings::get($pdo, 'ui_scale');
echo "Retrieved: '$val2'\n";

if ($val2 === '1.1') {
    echo "PASS: Logic chain works.\n";
} else {
    echo "FAIL: Logic chain broke.\n";
}
