<?php
require 'vendor/autoload.php';

$dateFrom = '2026-08-01';
$dateTo = '2026-08-24';
$carbonFrom = $dateFrom ? \Carbon\Carbon::parse($dateFrom)->startOfDay() : null;
$carbonTo = $dateTo ? \Carbon\Carbon::parse($dateTo)->endOfDay() : null;

$rowDated = '08/13';

$parsedRowDate = null;
$formats = ['d/m', 'm/d', 'n/j', 'j/n', 'd/m/Y', 'm/d/Y', 'n/j/Y', 'j/n/Y', 'd M', 'M d', 'd-m-Y', 'Y-m-d', 'd-M'];
foreach ($formats as $fmt) {
    if (\Carbon\Carbon::hasFormat(trim($rowDated), $fmt)) {
        try {
            $parsedRowDate = \Carbon\Carbon::createFromFormat($fmt, trim($rowDated));
            echo "Format matched: $fmt\n";
            break;
        } catch (\Exception $e) {
            $parsedRowDate = null;
        }
    }
}

if ($parsedRowDate) {
    echo "Parsed Date: " . $parsedRowDate->toDateTimeString() . "\n";
    if ($carbonFrom && $parsedRowDate->copy()->endOfDay()->lt($carbonFrom)) {
        echo "Filtered out by from\n";
    } elseif ($carbonTo && $parsedRowDate->copy()->startOfDay()->gt($carbonTo)) {
        echo "Filtered out by to\n";
    } else {
        echo "Matched!\n";
    }
} else {
    echo "Failed to parse\n";
}
