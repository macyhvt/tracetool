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
    $artID= isset($_POST['artid']) ? trim($_POST['artid']) : '';
    $procID = isset($_POST['pid']) ? trim($_POST['pid']) : '';

    $intervalType = $_POST['intervalType'];

    if ($intervalType == 'dateRange') {
        $fromDate = $_POST['fromDate'];
        $toDate = $_POST['toDate'];
        $measuredData = Model::getInstance('article')->getMeasuredPartsInputByDates($artID, $procID, $mp, $fromDate,$toDate );
    } elseif ($intervalType == 'pieceCount') {
        $pieceCount = $_POST['pieceCount'];
        $measuredData = Model::getInstance('article')->getMeasuredPartsInput($artID, $procID, $mp, $pieceCount);
    } elseif ($intervalType == 'last1000') {
        $countLimit = 1000;
        $measuredData = Model::getInstance('article')->getMeasuredPartsInput($artID, $procID, $mp, $countLimit);
    } else {
        echo "Invalid interval type."; // Handle potential errors
        exit();  // Stop processing if invalid type.  No need to continue.
    }

    // Fetch necessary data for CPK calculation
    $lowUp = Model::getInstance('article')->getLowerUpperForCPK($artID, $procID, $mp);

    $dTCheck = Model::getInstance('article')->getDataTypeForBtn($artID, $procID, $mp);

    // Check if the measured data array is empty
    if (empty($measuredData)) {
        echo "No measured data found for the given criteria.";
        exit();
    }

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
    if (empty($lowUp) || !isset($lowUp[0]['mpUpperTol']) || !isset($lowUp[0]['mpLowerTol'])) {
        echo "Lower and/or upper tolerance values are missing.";
        exit();
    }

    $upperTols = $lowUp[0]['mpNominal']+$lowUp[0]['mpUpperTol'];
    $lowerTols = $lowUp[0]['mpNominal']-$lowUp[0]['mpLowerTol'];

    // Avoid division by zero
    if ($standardDeviation == 0) {
        echo "Variance is zero, cannot calculate CPK.";
        exit();
    }

    $cp = ($upperTols - $lowerTols) / (6 * $standardDeviation);  // standard deviation is the square root of the variance
    $cpo = ($upperTols  - $meanValue) / (3 * $standardDeviation);
    $cpu = ($meanValue - $lowerTols) / (3 * $standardDeviation);

    $dataTemp = array($cpo, $cpu);
    $cpk = min($dataTemp);

    $outputArray = array_column($measuredData, 'mpInput');

    $responseData = [
        'cpk' => number_format($cpk, 3),
        'upTol' => number_format($upperTols, 3),
        'lowTol' => number_format($lowerTols, 3),
        'NomVal' => number_format($lowUp[0]['mpNominal'], 3),
        'measureData' => $outputArray,
        'meanValue' => number_format($meanValue, 3),
        'standardDeviation' => number_format($standardDeviation, 3),
        //'dtCheck' => $dTCheck[0]['mpDatatype'],

    ];

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($responseData);

    //Outputting
//    echo $cpk;
    exit();
}
?>
