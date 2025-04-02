<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Access\User;
use  \Entity\Process;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Helper\UserHelper;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Model\Techparams;
use  \Text;
use  \View;
use  \App;
use  \Model;
/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* conditional include */

// @info - this is a load-switch
//if (TRUE) :

?>

<?php
//$item = $view->get('item');

/*$this->lngID      = (new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($this->language)))->get('lngID');
$this->item       = $item;
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';
$this->isApproval = $task === 'approve';
$this->isSample   = $this->item->get('sample')   == '1';*/

$artProcID = $_POST['artProcID'] ?? '';
$partID = $_POST['partID'] ?? '';

$pID = $_POST['pID'] ?? '';
$lotID = $_POST['lotID'] ?? '';
$lang = $_POST['lang'] ?? '';
/*$techParams = $_POST['techParams'] ?? '';
$errorList = $_POST['errorList'] ?? '';
$staticTechParams = $_POST['staticTechParams'] ?? '';
$trackingData = $_POST['trackingData'] ?? '';
$debug = $_POST['debug'] ?? '';
$displayOperatorName = $_POST['displayOperatorName'] ?? '';
$drawing = $_POST['drawing'] ?? '';
$errColor = $_POST['errColor'] ?? '';
$errNum = $_POST['errNum'] ?? '';
$errText = $_POST['errText'] ?? '';
$processNo = $_POST['processNo'] ?? '';
$itemProcesses = $_POST['itemProcesses'] ?? '';
$measuredData = $_POST['measuredData'] ?? '';*/

//print_r($staticTechParams = $_POST['staticTechParams'] ?? '');
//echo $lang;


?>

<?php
$bandRole = Model::getInstance('part')->getBandRoleAjax($artProcID, $partID);


$originalDrawing = Model::getInstance('part')->getOriginalDrawing($artProcID, $partID);
$drawingData = json_decode($originalDrawing[0]['drawing'], true);
$originalDrawingImage = $drawingData['images'];
$originalDrawingFile = $drawingData['file'];
$originalDrawingFileIndex = $drawingData['index'];
//print_r($drawingData);
$trackingData = Model::getInstance('part')->viewPartParams($artProcID, $partID);

$filteredTrackingData = array_filter($trackingData, function($item) {
    return $item['paramID'] < 1 || $item['paramID'] > 6;
});

$paramIDs = array_column($filteredTrackingData, 'paramID');
$paramIDList = implode(',', array_map('intval', $paramIDs));
$paramNames = Model::getInstance('part')->viewParamsNames($lang, $paramIDList);
//echo "<pre>";print_r($trackingData);

$measuredData = Model::getInstance('part')->viewPartMeasuredData($artProcID, $partID);
$mpInputs = array_column($measuredData, 'mpInput');
$nonBlankInputs = array_filter($mpInputs);
//echo "<pre>";print_r($paramNames);
if($lang == 'de'){
    $lang = '1';
}elseif ($lang == 'en'){
    $lang = '2';
}elseif ($lang == 'hu'){
    $lang = '4';
}elseif ($lang == 'uk'){
    $lang = '5';
}
?>
    <div class="position-absolute process-drawing" id="drawing-3">
        <figure class="figure m-md-auto">
            <a href="https://track.vendeglatoeszkozok.com<?php echo UriHelper::osSafe( UriHelper::fixURL( $originalDrawingFile)); ?>" class="chocolat-image d-block" target="_blank">

                <img src="https://track.vendeglatoeszkozok.com<?php echo UriHelper::osSafe( UriHelper::fixURL( $originalDrawingImage[0])); ?>" alt="imae" width="" height="" data-toggle="tooltip" data-offest="20" title="" style="background: #fff;width: 92%; box-shadow:0 0 1px 1px #ced4da" data-original-title="view current drawing">
            </a>
        </figure>
    </div>

<?php foreach($trackingData as $userTrack): ?>
<?php
    //print_r($userTrack);
    ?>
<div class="row form-group mb-1">
    <?php if ($userTrack['paramID'] >= 1 && $userTrack['paramID'] <= 5){ ?><label for="procParams" class="col col-4 col-form-label-sm"><?php }else{ echo '<label for="procParams" class="col col-4 col-form-label">'; }?>
            <?php
                if ($userTrack['paramID'] >= 1 && $userTrack['paramID'] <= 7){
                    switch ($userTrack['paramID']) {
                        case 1:
                            $customVariable = 'Organisation';
                            break;
                        case 2:
                            $customVariable = 'Operator';
                            break;
                        case 3:
                            $customVariable = 'Date';
                            break;
                        case 4:
                            $customVariable = 'Time';
                            break;
                        case 5:
                            $customVariable = 'Drawing number';
                            break;
                        case 6:
                            $customVariable = 'Status';
                            break;
                        case 7:
                            $customVariable = 'Annotation';
                            break;
                    }
                    echo $customVariable;
                }elseif($userTrack['paramID']>=10) {
                    $techParamLang = $paramNames[$userTrack['paramID']]['name'];
                    echo $techParamLang;
                }
            ?>
        </label>

    <?php if ($userTrack['paramID'] >= 1 && $userTrack['paramID'] <= 5){ ?><div class="col input-group col-lg-4"> <?php }else{ echo '<div class="col input-group">'; }?>
        <?php
            if ($userTrack['paramID'] >= 1 && $userTrack['paramID'] <= 7){
                switch ($userTrack['paramID']) {
                    case 2:
                    case 3:
                    case 4:

                    case 1:
                        echo $userTrack['paramValue'];
                        break;
                    case 5:
                        $lastChar1 = substr($userTrack['paramValue'], -1);
                        if($lastChar1 != $originalDrawingFileIndex){
                             $textBeforeLastChar = substr($userTrack['paramValue'], 0, -1).'<span style="color: red; font-weight: bold">'.$lastChar1.'</span>';
                             echo $textBeforeLastChar;
                        }else{
                            echo $userTrack['paramValue'];
                        }
                        break;
                    case 6:
                        if($userTrack['paramValue'] != 0){
                             $errorList = Model::getInstance('part')->getErrorName($lang, $userTrack['paramValue']);
                            echo '<input type="text" class="form-control bg-danger text-white" value="'.$errorList[0]['name'].'" readonly="">';
                        }else{
                            echo '<input type="text" class="form-control bg-success" value="passed" readonly="">';
                        }
                        break;
                    case 7:
                        echo '<span class="form-control h-auto" readonly="">'.$userTrack['paramValue'].'</span>';
                        break;
                }
                //echo $customVariable;
            }elseif($userTrack['paramID']>=10) {
                echo '<span class="form-control h-auto" readonly="">'.$userTrack['paramValue'].'</span>';
            }
        ?>
    </div>
</div>
<style>
    .itemShow thead tr th{
        padding-bottom: 6px;
        width: 130px;
        font-size: 12px;
    }
    .itemShow tbody tr td{
        font-size: 12px;
    }
    .pronotcarried{
        color: #721c24;
        background-color: #f8d7da !important;
        border-color: #f5c6cb;
    }
</style>


<?php endforeach; ?>
    <!--Measurement Table Below-->
<?php
if(empty($measuredData)) {
    echo "<div class='row form-group mb-1'><label for='procParams' class='col col-4 col-form-label-sm'>Measurement Data:</label><div class='col input-group'><span class='form-control h-auto' readonly=''>This process has no measurement specifications.</span></div></div>";
}else{
if(empty($nonBlankInputs)){
    echo "<div class='row form-group mb-1'><label for='procParams' class='col col-4 col-form-label-sm'>Measurement Data:</label><div class='col input-group'><span class='pronotcarried form-control h-auto' readonly=''>The process audit was not carried out.</span></div></div>";
}else{
    ?>
    <div class="table-responsive itemShow">
        <table class="table table-bordered table-striped">
            <thead class="thead-dark">
            <tr>
                <th>Meas. Point</th>
                <th>Specification</th>
                <th>Measured Value</th>
                <th>Nominal</th>
                <th>Tolerance -</th>
                <th>Tolerance +</th>
                <th>Factor</th>
                <th>Validity</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $data = [
                // Array data here...
            ];

            foreach ($measuredData as $row) {
                // Only display rows where `mpInput` has a value (skip if empty)

                ?>
                <tr>
                    <td><?= htmlspecialchars($row['mp']) ?></td>
                    <td><?= htmlspecialchars($row['mpDescription']) ?></td>
                    <td><?= htmlspecialchars($row['mpInput']) ?></td>
                    <td><?= htmlspecialchars($row['mpNominal']) ?></td>
                    <td><?= htmlspecialchars($row['mpLowerTol'] ?? '0') ?></td>
                    <td><?= htmlspecialchars($row['mpUpperTol'] ?? '0') ?></td>
                    <td><?= htmlspecialchars($row['mpToleranceFactor'] ?? '1.00') ?></td>
                    <td style="padding: .75rem;" class="<?php
                    if($row['mpValidity']== 'valid'){
                        echo 'text-success';
                    }elseif ($row['mpValidity']=='invalid'){
                        echo 'text-danger';
                    }
                    ?>"><?= htmlspecialchars($row['mpValidity'] ?? '') ?></td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php }
}
?>
<?php exit; ?>