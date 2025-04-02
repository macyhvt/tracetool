<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

// Initialize variables (for use *outside* the POST block)
$data = [];
$meanValue = 0;
$standardDeviation = 0;
$upperTols = 0;
$lowerTols = 0;
$cp = 0;
$cpo = 0;
$cpu = 0;
$cpk = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mp = isset($_POST['mp']) ? trim($_POST['mp']) : '';
    $artID = isset($_POST['artID']) ? trim($_POST['artID']) : '';
    $procID = isset($_POST['pid']) ? trim($_POST['pid']) : '';

    $countL = 125;
    $measuredData = Model::getInstance('article')->getMeasuredPartsInput($artID, $procID, $mp, $countL);

    // Fetch necessary data for CPK calculation
    $lowUp = Model::getInstance('article')->getLowerUpperForCPK($artID, $procID, $mp);
    $dTCheck = Model::getInstance('article')->getDataTypeForBtn($artID, $procID, $mp);

    // Check if the measured data array is empty


    // Calculate the mean value
    $sum = array_sum(array_column($measuredData, 'mpInput'));
    $count = count($measuredData); // Get the actual count of measured data
    $meanValue = $sum / $count; // This is μ (population mean)

    // Calculate standard deviation
    $sumSquaredDiff = 0.0;
    foreach ($measuredData as $value) {
        $sumSquaredDiff += pow($value['mpInput'] - $meanValue, 2);  // This is Σ(xᵢ - μ)²
    }

    $standardDeviation = sqrt($sumSquaredDiff / $count);  // This matches σ = √(Σ(xᵢ - μ)²/N)

    // Check if the lower and upper tolerances are available


    $upperTols = $lowUp[0]['mpNominal'] + $lowUp[0]['mpUpperTol'];
    $lowerTols = $lowUp[0]['mpNominal'] - $lowUp[0]['mpLowerTol'];



    $cp = ($upperTols - $lowerTols) / (6 * $standardDeviation);  // standard deviation is the square root of the variance
    $cpo = ($upperTols - $meanValue) / (3 * $standardDeviation);
    $cpu = ($meanValue - $lowerTols) / (3 * $standardDeviation);

    $dataTemp = array($cpo, $cpu);
    $cpk = min($dataTemp);

    $outputArray = array_column($measuredData, 'mpInput');

    $responseData = [
        'cpk' => number_format($cpk, 3),
        //'upTol' => number_format($upperTols, 3),
        //'lowTol' => number_format($lowerTols, 3),
        //'NomVal' => number_format($lowUp[0]['mpNominal'], 3),
        //'measureData' => $outputArray,
        //'meanValue' => number_format($meanValue, 3),
        //'standardDeviation' => number_format($standardDeviation, 3),
        //'dtCheck' => $dTCheck[0]['mpDatatype'],

    ];

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($responseData);
    exit();
}
?>
