<?php

function time_group_label(int $timestamp): string {
    $date = (new DateTimeImmutable())->setTimestamp($timestamp);
    $now = new DateTimeImmutable('now');

    // Compare dates (midnight to midnight)
    $dateYMD = $date->format('Y-m-d');
    $todayYMD = $now->format('Y-m-d');
    $yesterdayYMD = $now->modify('-1 day')->format('Y-m-d');

    if ($dateYMD === $todayYMD) return 'Danes';
    if ($dateYMD === $yesterdayYMD) return 'Včeraj';

    return $date->format('d. m. Y');
}

// Tests
$now = time();
$yesterday = strtotime('-1 day');
$twoDaysAgo = strtotime('-2 days');
$lastMonth = strtotime('-1 month');

$tests = [
    ['label' => 'Today', 'ts' => $now, 'expected' => 'Danes'],
    ['label' => 'Yesterday', 'ts' => $yesterday, 'expected' => 'Včeraj'],
    ['label' => 'Two Days Ago', 'ts' => $twoDaysAgo, 'expected' => date('d. m. Y', $twoDaysAgo)],
    ['label' => 'Last Month', 'ts' => $lastMonth, 'expected' => date('d. m. Y', $lastMonth)],
];

$failed = false;
foreach ($tests as $test) {
    $actual = time_group_label($test['ts']);
    if ($actual !== $test['expected']) {
        echo "FAIL: {$test['label']} - Expected '{$test['expected']}', got '$actual'\n";
        $failed = true;
    } else {
        echo "PASS: {$test['label']} - '$actual'\n";
    }
}

if ($failed) {
    exit(1);
}
echo "All tests passed.\n";
