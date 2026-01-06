<?php
// Verification of the new Admin Settings Logic
// Simulates the POST processing in admin/settings.php

function test_logic($input, $expected, $desc) {
    // 1. Normalize
    $uiScale = str_replace(',', '.', (string)$input);

    // 2. Validate
    $uiScaleVal = (float)$uiScale;
    if ($uiScaleVal < 0.8) $uiScale = '0.8';
    elseif ($uiScaleVal > 1.2) $uiScale = '1.2';

    if ($uiScale === $expected) {
        echo "PASS: $desc (Input: '$input' -> Output: '$uiScale')\n";
    } else {
        echo "FAIL: $desc (Input: '$input' -> Expected: '$expected', Got: '$uiScale')\n";
    }
}

echo "Running Verification Logic...\n";
test_logic('1.0', '1.0', 'Standard Dot');
test_logic('1,0', '1.0', 'Comma Locale');
test_logic('1.1', '1.1', 'Decimal Dot');
test_logic('1,1', '1.1', 'Decimal Comma');
test_logic('0.5', '0.8', 'Min Clamp');
test_logic('1.5', '1.2', 'Max Clamp');
test_logic('1,5', '1.2', 'Max Clamp Comma');
test_logic('invalid', '0.8', 'Invalid String'); // (float)"invalid" -> 0 -> clamp to 0.8
