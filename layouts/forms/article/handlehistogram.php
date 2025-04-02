<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Model;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php

if ($SERVER["REQUEST_METHOD"] == "POST") {
$mp = isset($POST['mp']) ? trim($_POST['mp']) : '';
$artID = isset($POST['artid']) ? trim($_POST['artid']) : '';
$procID = isset($POST['pid']) ? trim($_POST['pid']) : '';
$intervalType = $POST['intervalType'];
// Get measurement data based on interval type
if ($intervalType == 'dateRange') {
$fromDate = $POST['fromDate'];
$toDate = $POST['toDate'];
$measuredData = Model::getInstance('article')->getMeasuredPartsInputByDates($artID, $procID, $mp, $fromDate, $toDate);
} elseif ($intervalType == 'pieceCount') {
$pieceCount = $POST['pieceCount'];
$measuredData = Model::getInstance('article')->getMeasuredPartsInput($artID, $procID, $mp, $pieceCount);
} else {
$countLimit = 1000;
$measuredData = Model::getInstance('article')->getMeasuredPartsInput($artID, $procID, $mp, $countLimit);
}
// Get tolerance values
$lowUp = Model::getInstance('article')->getLowerUpperForCPK($artID, $procID, $mp);
// Calculate statistics
$measurements = array_column($measuredData, 'mpInput');
$mean = array_sum($measurements) / count($measurements);
$stdDev = sqrt(array_sum(array_map(function($x) use ($mean) {
return pow($x - $mean, 2);
}, $measurements)) / count($measurements));
// Generate histogram data
$min = min($measurements);
$max = max($measurements);
$binCount = 50;
$binWidth = ($max - $min) / $binCount;
$histogramData = array_fill(0, $binCount, 0);
$labels = [];
// Create bins
for ($i = 0; $i < $binCount; $i++) {
$binStart = $min + ($i $binWidth);
$labels[] = number_format($binStart, 3);
// Count measurements in this bin
foreach ($measurements as $measurement) {
if ($measurement >= $binStart && $measurement < ($binStart + $binWidth)) {
$histogramData[$i]++;
}
}
}
// Convert counts to percentages
$totalCount = count($measurements);
$histogramData = array_map(function($count) use ($totalCount, $binWidth) {
return ($count / $totalCount / $binWidth) 100;
}, $histogramData);
// Prepare response data
$response = [
'labels' => $labels,
'values' => $histogramData,
'lowerTol' => $lowUp[0]['mpNominal'] - $lowUp[0]['mpLowerTol'],
'upperTol' => $lowUp[0]['mpNominal'] + $lowUp[0]['mpUpperTol'],
'sigma3minus' => $mean - (3 $stdDev),
'sigma3plus' => $mean + (3 $stdDev)
];
echo json_encode($response);
exit();
}
?
