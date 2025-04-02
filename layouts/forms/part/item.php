<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Entity\Process;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Model\Techparams;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* conditional include */

// @info - this is a load-switch
//if (TRUE) :

?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');

//$return = $view->getReturnPage();	// Browser back-link required for back-button.
$return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');	// the user who requested this page --- Note: In addition to this user, there are also
								// the operator (author of an existing tracking) and
								// the quality manager (required for the release of a tracking for editing) on this page.
								// There are dependencies and interactions between these users, which are calculated and evaluated in the further course.

$layout = $input->getCmd('layout');
$urlviewname = $input->getCmd('view');
$pid    = $input->getInt('pid');
if($urlviewname == "part" && $layout == "item"){
?>
    <style>
        .frmp, span.frmps, #th-mike1, #th-miketwo{
            display: none;
        }
        .knowBparts{
            left: 30%;
            z-index: 1;
        }
    </style>
<?php }?>
<?php /* Access check */
if (is_a($user, 'Nematrack\Entity\User')) :
	try
	{
		$formData = $user->__get('formData');
		$formData = (is_array($formData)) ? $formData : [];
	}
	catch (Exception $e)
	{
		$formData = null;
	}
endif;

// TODO - Implement ACL and make calculate editor-right from ACL
$canDelete = true;
?>
<?php /* Process form data */
$task    = $input->post->getCmd('task',    $input->getCmd('task'));
$format  = $input->post->getWord('format', $input->getWord('format'));
$isPrint = $task === 'print' || current(explode('.', $task)) === 'print';

if (!is_null($task)) :
	switch ($task) :
	endswitch;
endif;
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing part.
if (!is_a($item, 'Nematrack\Entity\Part') || (is_a($item, 'Nematrack\Entity\Part') && is_null($item->get('partID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PART_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $item->get('partID'))
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('parts', ['language' => $lang])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->lngID      = (new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($this->language)))->get('lngID');
$this->item       = $item;
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';
$this->isApproval = $task === 'approve';
$this->isSample   = $this->item->get('sample')   == '1';
?>
<?php /* Load more view data - required when not in print mode */ ?>
<?php if (!$isPrint) :
// Get hands on processes this user's organisation is responsible for. It's required for access control.
$orgModel         = $this->view->get('model')->getInstance('organisation', ['language' => $this->language]);
$userOrganisation = $orgModel->getItem((int) $user->get('orgID'));
$orgID            = $userOrganisation->get('orgID');
$orgName          = $userOrganisation->get('name');
$orgProcesses     = (array) $userOrganisation->get('processes', []);
/*$orgAdmins          = $orgModel->getAdmins([
	$userOrganisation->getPrimaryKeyName() => $userOrganisation->get($userOrganisation->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);*/
/*$orgQualityManagers = $orgModel->getQualityManagers([
	$userOrganisation->getPrimaryKeyName() => $userOrganisation->get($userOrganisation->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);*/

$processes     = $model->getInstance('processes', ['language' => $lang])->getList(['params' => true]);
// Extract abbreviations.
$abbreviations = array_column($processes, 'abbreviation', 'procID'); array_map('mb_strtolower', $abbreviations);

// Extract abbreviations belonging to SCF-processes.
// These processes must not be editable. Press-in data is sent to the TrackingTool by the Press-in machine.
// There was too much mess created by Jermaine and Manuel that made Tino decide on this preventive intervention.
$mntORscfProcs = array_filter($abbreviations, function($abbreviation) { return preg_match('/^(scf)\d{1,2}$/', $abbreviation); });

// Grant edit right for SCF-processes to privileged users to allow tracking of bad parts.
// Quality responsibles and Developers should be able to edit an automatic tracking.
if ($user->isQualityManager() || $user->isProgrammer()) :
	$mntORscfProcs = [];
endif;

// Get lot this part belongs to (if any).
$itemLot = $model->getInstance('lot', ['language' => $lang])->getItem((int) $this->item->get('lotID'));

// Get reference to the list of processes an article must run through.
$itemProcesses  = (array) $this->item->get('processes');
    /* --- @unset process which is deactivated --- */
    $unwantedProcesses = array_filter($itemProcesses, function($value) {
        // Check if 'processState' is set and is equal to 0
        return $value->get('processState') === 0;
    });

$disableProcess = array_keys($unwantedProcesses);
//echo "<pre>";print_r($disableProcess);
    /* --- @unset process which is deactivated --- */
$itemProcessIDs = array_keys($itemProcesses);

// Get reference to tracking data for each of these processes.
$trackingData = (array) $this->item->get('trackingData');	// List of all tracking entries for this part (every tracking entry is an object representing the table row)
//echo "<pre>";print_r($trackingData);
$fArraytoTrack = array_diff($itemProcessIDs, $disableProcess);
//echo "<pre>";print_r($fArraytoTrack);
// Separate untracked processes from previously tracked processes and
// dump previously tracked pid as well as next pid to be tracked.
$trackedPids = array_keys($trackingData);

$diffPidsToBeTrackedVSuntrackedPids = [];
//echo $pid;
$z                                  = array_filter($fArraytoTrack, function($pid) use(&$trackedPids) {	// becomes $untrackedPids further below
	return !in_array($pid, $trackedPids);
});


$diffPidsToBeTrackedVSuntrackedPids = array_diff($fArraytoTrack, $z);

// Dump pids of previous and next process.
$firstPid       = current($fArraytoTrack);	// the very first process to be tracked
$lastPidTracked = end($diffPidsToBeTrackedVSuntrackedPids);	// the last process tracked
$nextPid        = current($z);	// the process to be tracked next

// Skip first untracked process id to enable it for editing.
array_shift($z);

// Calculate first process to be overlayed to prevent edit.
if (empty($lastPidTracked)) :
	$untrackedPids = array_slice($fArraytoTrack, 1);
else :
	$untrackedPids = $z;
endif;


    // Find the indexes of values in $total that are present in $tracked
    $trackedPidsAssoc = array_flip($trackedPids);

// Create an array with the keys to be removed
    $keysToRemove = array();
    foreach ($itemProcessIDs as $key => $value) {
        if (isset($trackedPidsAssoc[$value])) {
            $keysToRemove[$key] = true;
            if ($key > 0) {
                $keysToRemove[$key - 1] = true;
            }
        }
    }

// Use array_diff_key to remove the specified keys
    $total = array_diff_key($itemProcessIDs, $keysToRemove);

// Reset the array keys to maintain a consecutive numeric index
    $total = array_values($total);

// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$staticTechParams = (array) $model->getInstance('techparams', ['language' => $lang])->getStaticTechnicalParameters(true);

// Retrieve the error catalogue for all processes that this part has to run through.
// NOTE: The next command is outsourced into \Nematrack\View\Part::prepareErrorList() in TT-DEV
//       so that <var>$errors</var> will be available from this part via this->item->get('errors').
$errors   = $model->getInstance('errors', ['language' => $lang])->getErrorsByLanguage(
	$this->lngID,
	$itemProcessIDs
);

$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
$dateNow  = $now->format('Y-m-d');
$timeNow  = $now->format('H:i:s');

$errNum   = null;
$errText  = null;
$errColor = null;

// Detect whether this part is a good part or bad part.
// This info is required for the badge.
$itemHasBadProcess = false;
$badProcID         = null;	// will receive the process id to link to on click onto the quality classifier badge

$isAutotrack  = $input->getInt('at') == '1';
$isAutofill   = $input->getInt('af') == '1';
$isAutosubmit = $input->getInt('as') == '1';

// If item is a bad part, fetch ID of process this part failed.
if ($this->item->isBad()) :
	array_walk($trackingData, function($trackingData, $procID) use(&$itemHasBadProcess, &$badProcID)
	{
		if ($itemHasBadProcess) :
			return;
		endif;

		$match = array_filter((array) $trackingData, function($entry, $paramID) use(&$user)
		{
			return ( ($paramID == Techparams::STATIC_TECHPARAM_ERROR) && ((int) $entry > 0) );

		}, ARRAY_FILTER_USE_BOTH);

		if ($match) :
			// Update flag.
			$itemHasBadProcess = true;
			// Dump ID of bad process.
			$badProcID = $procID;
		endif;

		return true;
	});
endif;
?>
<?php /* Banderole */
// Get reference to measuring definitions and measured data for each of these processes.
/*$articleModel = $model->getInstance('article');
$measurementDefinitions = $articleModel->getDefinedMeasuringPoints($this->item->get('artID'));
$measuredData = (array) $this->item->get('measuredData');
//echo "<pre>";print_r($measuredData);exit;
// Filter measured data for empty measuring definitions (no user input).
// This step is necessary for the process visibility in the banderole.
foreach ($measuredData as $procID => &$mpoints) :
	foreach ($mpoints as $mp => $data) :
		$userInput = ArrayHelper::getValue($data, 'mpInput');

		if (empty($userInput)) :
			unset($mpoints[$mp]);
		endif;
	endforeach;

	if (empty($mpoints)) :
		unset($measuredData[$procID]);
	endif;

endforeach;*/

// Init vars related to this part's process banderole.
$banderoleData = [];
$showBanderole = false; // Shall the banderole be displayed at all?

// Loop over item processes to pick and count all entries that shall be banderolable.
/*array_walk($itemProcesses, function($itemProcess, $procID) use(
	&$articleModel,
	&$processes,
	&$banderoleData,
	&$measurementDefinitions,
	&$measuredData,
	&$showBanderole
) {
	// Get process object.
	$process      = ArrayHelper::getValue($processes, $procID);
	$abbreviation = ArrayHelper::getValue($process, 'abbreviation');

	// Skip processes that do not have the 'hasBanderole' flag set or set to '0', which means 'must not appear in banderole'.
	if ($itemProcess->get('hasBanderole')) :
		// Get process measuring definitions.
		$definitions    = ArrayHelper::getValue($measurementDefinitions, $procID, [], 'ARRAY');
		$cntDefinitions = count($definitions);

		// If there are no measuring definitions at all skip this process.
		if (!$cntDefinitions) return;

		// Count defined measuring definitions and compare to tracked measuring definitions.
		// If none of the definitions was tracked then this process must not appear on the banderole.
		$data    = ArrayHelper::getValue($measuredData, $procID, [], 'ARRAY');
		$cntData = count($data);

		if (!$procID) return;

		// If no measuring definition is tracked this process must not appear in the banderole.
		if ($cntData == 0) :
		// If measuring definitions are not completely tracked this process shall appear mutedly in the banderole.
		elseif ($cntData < $cntDefinitions) :
			$banderoleData[$procID] = ['abbreviation' => $abbreviation, 'transparency' => 'semi'];
		// If all measuring definitions are completely tracked this process shall appear in the banderole.
		elseif (count($definitions) > 0 == count($data) > 0 && $cntDefinitions == $cntData) :
			$banderoleData[$procID] = ['abbreviation' => $abbreviation, 'transparency' => 'opaque'];

			$showBanderole = true;
		endif;
	endif;

	return true;
});*/

/* Detect whether to display the banderole at all.
 * If only one process has this parameter configured then the banderole is to be displayed.
 *
 * Not until at least 1 opaque process is found is the banderole rendered.
 */
//$showBanderole = count($banderoleData) && array_search('opaque', $banderoleData, TRUE);

endif; //-> !isPrint
?>
<style>
    .disableProcess{
        backdrop-filter: blur(10px);
    }
    .disableProcess:before{
        display: block !important;
        position: absolute;
        z-index: 999;
        top: 0;
        left: 0;
        margin-left: -5px;
        margin-top: 0%;
        width: 100%;
        height: 95%;
        padding-top: 150px;
        background-color: rgb(245 245 245 / 72%);
        box-shadow: 0 0 10px 10px rgba(245,245,245,0.7);
        backdrop-filter: blur(4px);
        content: "";
    }
    .blurprocess {
        display: none;
    }
    .blurprocess:before {
        backdrop-filter: blur(11px);
        /* background: #ffffffd4; */
        display: block !important;
        position: absolute;
        z-index: 999;
        top: 0;
        left: 0;
        margin-left: -5px;
        margin-top: 0%;
        width: 100%;
        height: 95%;
        padding-top: 150px;
        background-color: rgb(245 245 245);
        box-shadow: 0 0 10px 10px rgba(245,245,245,0.7);
        backdrop-filter: blur(90px);
        content: "";
    }
    /*.disableProcess:after{
        content: "You can skip this process";
    }*/
</style>
<?php if (!$isPrint) : ?>
<style>
.viewTitle {
	line-height: 1 !important;
}

.quality-badge,
.sample-part-badge {
	padding-left:  0.75rem;
    padding-right: 0.75rem;
}

.sample-part-badge {
	letter-spacing: 0.2rem;
	background: #CDE4FF;	/* main menu color 10% lightened */
	color: #30588B;
	box-shadow: 0 0 3px 1px rgba(48, 88, 139, 0.4);
}

.status-badge {
	letter-spacing: 1px;
}

.btn-link-pdf {
	padding-top: 2px;
	padding-bottom: 2px;
}

.collapse-sm .card {
	border: 0 none !important;
}
.collapse-sm .card-body {
	padding: 0!important;
}
.collapse-sm.show .card-body {
	background: unset !important;
}
.collapse-sm .card-body tbody tr td:first-of-type {
	padding-left: 0!important;
}
.collapse-sm .card-body tbody tr td:last-of-type {
	padding-right: 0!important;
}

.form-horizontal.archived-item,
.form-horizontal.blocked-item,
.form-horizontal.deleted-item {
	overflow-x: hidden;
}
.form-horizontal.archived-item .status-badge,
.form-horizontal.blocked-item  .status-badge,
.form-horizontal.deleted-item  .status-badge {
	left: 0;
	line-height: 2;
}

.form-control:disabled,
.form-control[readonly],
.btn:disabled {
	cursor: not-allowed;
	opacity: .60;
}
.btn:disabled {
	opacity: .40;
}
.form-control[readonly]:not(:disabled) {
	cursor: default;
	opacity: 1;
}

.table.process-measuring-data tbody > tr > td:first-of-type {
	padding-left: 0;
}
.table.process-measuring-data tbody > tr > td:last-of-type {
	padding-right: 0;
}
.table.process-measuring-data tbody > tr:last-of-type > td {
	padding-bottom: 0;
}
.table.process-measuring-data tbody > tr > td > input[type],
.table.process-measuring-data tbody > tr > td > select {
	font-size: 95%!important;
}
.table.process-measuring-data .mpValidity.text-danger {
	color: #dc3545!important;
	box-shadow: none!important;
}
.table.process-measuring-data .mpValidity.text-warning {
	color: #ffc107!important;
	box-shadow: none!important;
}

.timeline {
    border-left: 4px solid #ced4da;
    letter-spacing: 0.5px;
    line-height: 1.4em;
	padding: 15px 0 0 30px;
    list-style: none;
    text-align: left;
}
.timeline .event {
    border-bottom: 1px dashed #ced4da;
    padding-top: 20px;
    padding-bottom: 17px;
}
.timeline .event:first-of-type {
	padding-top: 0;
}
.timeline .event:last-of-type {
	border-bottom: none;
	margin-bottom: 0;

}

.timeline .event:after {
    content: "";
    box-shadow: 0 0 0 4px #ced4da;
    left: -37px;
    background: #b3cae9;
    border-radius: 50%;
    height: 11px;
    width: 11px;
    top: 7px;
}
.timeline .event:before,
.timeline .event:after {
    position: absolute;
    display: block;
	top: -5px;
}
.timeline .event:first-of-type:after {
	display: none;
	top: -15px;
}

.timeline .event .process-drawing {
	z-index: 1;
	right: 0;
	margin-right: 1px;
	margin-top: 0.5rem
}
.timeline .event .process-drawing + .form-group {
	margin-top: 0.5rem
}

/* Override for parts with banderole */
#processTree.isBanderole .event:first-of-type:before,
#processTree.isBanderole .event:first-of-type:after {
	display: none;
}
/* Adapt vertical timeline style from non-bandrole process tree */
#processTree > .row > .col-1  {
	border-right: 4px solid #ced4da;
	flex: 0 0 7%;
	max-width: 7%;
}
#processTree > .row > .col-11  {
	flex: 0 0 93%;
	max-width: 93.0%;
}
#processTree > .row > .col-1 > #process-banderole {
	top: 0;
	min-height: 100%;
	max-width: 2rem;
	background: #e2e3e5;
}
#processTree > .row > .col-1 > #process-banderole > i {
	margin-left: -25%;
}
/* Required to render vertically parallel border from rotated child element */
#processTree > .row > .col-1 > #process-banderole > .banderole-stripe-wrapper {
	overflow: hidden;
	left: 0;
	height: 1.5rem;
	width: 110%;
	margin-left: -5%;
}
#processTree > .row > .col-1 > #process-banderole > .banderole-stripe-wrapper > .banderole-stripe {
	transform: rotate(-10deg);
	height: 0.75rem;
	width: 108%;
	margin-left: -4%;
}
#processTree > .row > .col-1 > #process-banderole > .banderole-abbreviation {
	left: 0;
	width: 2rem;
}
#processTree > .row > .col-1 > #process-banderole > .banderole-abbreviation > span {
	line-height: 1.5rem;
}

fieldset.part-process-tracking {
    outline: 0 !important;
}
fieldset.part-process-tracking.disabled:before {
	display: none;
	position: absolute;
	z-index: 0;
	top: 0;
	left: 0;
	margin-left: -5px;
    margin-top: 4%;
    width: 101%;
    height: 95%;
	padding-top: 150px;
	padding-left: 30%;
    padding-right: 30%;
	background-color: rgba(245,245,245,0.7);
	box-shadow: 0 0 10px 10px rgba(245,245,245,0.7);
	<?php // Code friendly borrowed from https://stackoverflow.com/a/58083568 ?>
	backdrop-filter: blur(4px);
	content: "<?php //echo Text::translate('COM_FTK_HINT_PROCESS_TRACKING_ONLY_AFTER_PREV_PROCESSES_TRACKED_TEXT', $lang); ?>";
    text-align: center;
    vertical-align: middle;
    line-height: 2;
    font-weight: bold;
	color: #b94a48;

    margin-top: -10px;
    height: calc(100% + 55px);
}

fieldset.part-process-tracking.disabled:before,
fieldset.part-process-tracking:disabled:before {
	display: block;
	z-index: 1;
}
fieldset.part-process-tracking:focus {
    background: rgba(0, 123, 255, 0.1);
    box-shadow: 0 0 10px 10px rgba(0, 123, 255, 0.1);
}

input.bg-success {
	background-color: rgba(1, 163, 28, 0.2) !important;
}
input.bg-warning {
	background-color: rgba(255, 215, 0, 0.3) !important;
}

@media (max-width: 767.98px) {
	.form-horizontal.d-inline .btn-lock,
	.form-horizontal.d-inline-block .btn-lock {
		padding-left: 0.6rem;
		padding-right: 0.6rem;
	}
}
</style>

<article class="<?php echo trim(preg_replace('/[\s|\t|\n]+/', ' ', sprintf('form-horizontal position-relative %s %s %s %s %s',
		($this->isDeleted  ? 'deleted-item'  : ''),
		($this->isBlocked  ? 'blocked-item'  : ''),
		($this->isArchived ? 'archived-item' : ''),
		($this->isSample   ? 'sample-item'   : ''),
		($showBanderole    ? 'isBanderole'   : '')))); ?>"
	id="<?php echo sprintf('%sForm', mb_strtolower($view->get('name'))); ?>"
>
	<?php // View title and toolbar ?>
	<?php // TODO - implement toolbar ... it is very tricky because in this view showing buttons depends on item status and user access rights ?>
	<div class="row" style="overflow:hidden">
		<div class="col-12<?php echo ($this->isDeleted ? ' badge-danger' : ($this->isBlocked  ? ' badge-warning' : ($this->isArchived  ? ' badge-info' : ''))); ?>">
			<?php // B A C K - button ?>
			<?php if ($user->isCustomer() || $user->isSupplier()) : ?>
			<a href="javascript:void(0)"
			   role="button"
			   class="btn btn-link outline-0 pl-1 pr-3 allow-window-unload<?php echo ($this->isArchived || $this->isBlocked || $this->isDeleted) ? ' text-light' : ' text-dark'; ?>"
			   data-bind="windowClose"
			   data-force-reload="true"
			   style="vertical-align:text-bottom"
			>
				<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
					  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
					  data-toggle="tooltip"
				>
					<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
					<span class="btn-text sr-only"><?php
						echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $lang) );
					?></span>
				</span>
			</a>
			<?php endif; ?>

			<h1 class="h3 viewTitle d-inline-block my-0 mr-3<?php echo ($this->isArchived || $this->isBlocked || $this->isDeleted) ? ' text-light' : ''; ?>"><?php
				echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $lang);
			?></h1>

			<?php if ($this->isArchived || $this->isBlocked || $this->isDeleted) : ?>
			<span class="<?php echo trim(implode(' ', [
					'status-badge',
					'position-absolute',
					'btn',
					'd-inline-block',
					'h-100',
					'text-center',
					'text-white',
					'text-uppercase',
					'font-weight-bold'
				  ])); ?>"
				  style="background-color:unset; z-index:0; left:55px; width:90%; line-height:0.9"
			>
				<span class="d-inline-block align-middle"><?php
					switch (true) :
						case ( $this->isDeleted) :
							echo Text::translate('COM_FTK_STATUS_DELETED_TEXT', $lang);
						break;

						case ( $this->isBlocked) :
							echo Text::translate('COM_FTK_STATUS_LOCKED_TEXT', $lang);
						break;

						case ( $this->isArchived) :
							echo Text::translate('COM_FTK_STATUS_ARCHIVED_TEXT', $lang);
						break;
					endswitch;
				?></span>
			</span>
			<?php endif; ?>

			<?php if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
			<?php // N O   E D I T - button ?>
			<?php endif; // END: !$this->isArchived && !$this->isDeleted && !$this->isBlocked ?>

			<?php // C A N C E L - button ?>
			<div class="d-inline-block align-top ml-md-2 ml-lg-3">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text">
							<i class="fas fa-times text-red"></i>
						</span>
					</div>
					<a href="javascript:void(0)"
					   role="button"
					   class="btn btn-sm btn-custom btn-cancel left-radius-0 pr-md-3 allow-window-unload"
					   data-bind="windowClose"
					   data-force-reload="true"
					>
						<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
								echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $lang) );
							?></span>
						</span>
					</a>
				</div>
			</div>

			<?php /*   T O O L B A R   */ ?>
			<?php if ($user->get('orgID') == '1' && $user->getFlags() >= User::ROLE_MANAGER) : // Management is granted to privileged FRÖTEK-users only ?>
			<div class="position-absolute" style="z-index:1; top:0; right:0; padding-right:15px">
				<div class="align-middle text-right">
					<?php // (U N) B L O C K - button ?>
					<?php if ($user->getFlags() >= User::ROLE_ADMINISTRATOR) : // (Un)Publishing is granted to higher privileged users only ?>
					<?php	if (!$this->isDeleted && !$this->isArchived) : ?>
					<form action="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?>"
						  method="post"
						  name="<?php echo sprintf('%s%sForm', ($this->isBlocked ? 'unblock' : 'block'), ucfirst($view->get('name'))); ?>"
						  class="form-horizontal d-inline-block"
						  data-submit=""
					>
						<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
						<input type="hidden" name="task"     value="<?php echo ($this->isBlocked ? 'unlock' : 'lock'); ?>" />
						<input type="hidden" name="state"    value="<?php echo ($this->isBlocked ? '0' : '1'); ?>" />
						<input type="hidden" name="ptid"     value="<?php echo (int) $this->item->get('partID'); ?>" />
						<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
						<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

						<button type="submit"
								name="button"
								value="<?php echo ($this->isBlocked ? 'unlock' : 'lock'); ?>"
								class="btn btn-sm btn-<?php echo ($this->isArchived ? 'secondary' : 'dark'); ?> btn-lock align-super px-lg-3"
						>
							<span title="<?php      echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isBlocked ? 'UNLOCK' : 'LOCK')), $lang); ?>"
								  aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isBlocked ? 'UNLOCK' : 'LOCK')), $lang); ?>"
								  data-toggle="tooltip"
							>
								<i class="fas fa-lock<?php echo ($this->isBlocked ? '-open' : ''); ?>"></i>
								<span class="btn-text d-none d-md-inline ml-lg-2"><?php
									echo mb_strtolower(
										Text::translate(
											sprintf('COM_FTK_BUTTON_TEXT_%s_TEXT', ($this->isBlocked ? 'UNLOCK' : 'LOCK')
										), $lang)
									);
								?></span>
							</span>
						</button>
					</form>
					<?php	endif; // END: !isDeleted && !isArchived ?>
					<?php endif; // END: ACL-Check ?>

					<?php // (U N) A R C H I V A T E - button ?>
					<?php if (FALSE && $user->getFlags() >= User::ROLE_ADMINISTRATOR) : // (Un)Publishing is granted to higher privileged users only ?>
					<?php	if (!$this->isDeleted) : ?>
					<form action="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?>"
						  method="post"
						  name="<?php echo sprintf('%s%sForm', ($this->isArchived ? 'restore' : 'archive'), ucfirst($view->get('name'))); ?>"
						  class="form-horizontal d-inline-block"
						  data-submit=""
					>
						<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
						<input type="hidden" name="task"     value="<?php echo ($this->isArchived ? 'restore' : 'archive'); ?>" />
						<input type="hidden" name="state"    value="<?php echo ($this->isArchived ? '0' : '1'); ?>" />
						<input type="hidden" name="ptid"     value="<?php echo (int) $this->item->get('partID'); ?>" />
						<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
						<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

						<button type="submit"
								name="button"
								value="<?php echo ($this->isArchived ? 'restore' : 'archive'); ?>"
								class="btn btn-sm btn-<?php echo ($this->isArchived ? 'secondary' : 'dark'); ?> btn-archive align-super px-lg-3"
								onclick="return confirm('<?php echo Text::translate(
									sprintf('COM_FTK_DIALOG_%s_CONFIRM_%s_TEXT',
										mb_strtoupper($view->get('name')),
										mb_strtoupper($this->isArchived ? 'restoration' : 'archivation')
									), $lang);
								?>')"
						>
							<span title="<?php      echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isArchived ? 'RESTORE' : 'ARCHIVATE')), $lang); ?>"
								  aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isArchived ? 'RESTORE' : 'ARCHIVATE')), $lang); ?>"
								  data-toggle="tooltip"
							>
								<i class="fas fa-archive"></i>
								<span class="btn-text d-none d-md-inline ml-lg-2"><?php
									echo mb_strtolower(
										Text::translate(
											sprintf('COM_FTK_BUTTON_TEXT_%s_TEXT', ($this->isArchived ? 'RESTORE' : 'ARCHIVATE')
										), $lang)
									);
								?></span>
							</span>
						</button>
					</form>
					<?php	endif; // END: !isDeleted ?>
					<?php endif; // END: ACL-Check ?>

					<?php // (U N) D E L E T E - button ?>
					<?php if ($user->getFlags() >= User::ROLE_ADMINISTRATOR) : // (Un)Deleting is granted to higher privileged users only ?>
					<form action="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?>"
						  method="post"
						  name="<?php echo sprintf('delete%sForm', ucfirst($view->get('name'))); ?>"
						  name="<?php echo sprintf('%s%sForm', ($this->isDeleted ? 'recover' : 'delete'), ucfirst($view->get('name'))); ?>"
						  class="form-horizontal d-inline-block"
						  data-submit=""
					>
						<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
						<input type="hidden" name="task"     value="<?php echo ($this->isDeleted ? 'recover' : 'delete'); ?>" />
						<input type="hidden" name="state"    value="<?php echo ($this->isDeleted ? '0' : '1'); ?>" />
						<input type="hidden" name="ptid"     value="<?php echo (int) $this->item->get('partID'); ?>" />
						<input type="hidden" name="return"   value="<?php echo (($this->isDeleted) ? base64_encode( $return ) : base64_encode( View::getInstance('parts', ['language' => $lang])->getRoute() )); ?>" />
						<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

						<button type="submit"
								name="button"
								value="<?php echo ($this->isDeleted ? 'recover' : 'delete'); ?>"
								class="btn btn-sm btn-<?php echo ($this->isDeleted ? 'dark' : 'danger'); ?> btn-trashbin align-super px-lg-3"
								onclick="return confirm('<?php echo Text::translate(
									sprintf('COM_FTK_DIALOG_%s_CONFIRM_%s_TEXT',
										mb_strtoupper($view->get('name')),
										mb_strtoupper($this->isDeleted ? 'RECOVERY' : 'DELETION')
									), $lang);
								?>')"
						>
							<span title="<?php      echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isDeleted ? 'RECOVER' : 'DELETE')), $lang); ?>"
								  aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isDeleted ? 'RECOVER' : 'DELETE')), $lang); ?>"
								  data-toggle="tooltip"
							>
								<i class="far fa-trash-alt"></i>
								<span class="btn-text d-none d-md-inline ml-lg-2"><?php
									echo mb_strtolower(
										Text::translate(
											sprintf('COM_FTK_BUTTON_TEXT_%s_TEXT', ($this->isDeleted ? 'RECOVER' : 'DELETE')
										), $lang)
									);
								?></span>
							</span>
						</button>
					</form>
					<?php endif; // END: ACL-Check ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $lang]); ?>
	<hr>
	<?php // Part master data ?>
	<section class="position-relative" id="masterdata">
		<h2 class="h4 mb-4 mt-lg-4"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?>
			<?php /* GOOD/BAD part badge */ ?>
			<?php if ($itemHasBadProcess) : ?>
			<a href="#process-<?php echo (int) $badProcID; ?>" class="btn btn-link badge badge-danger quality-badge float-right"><?php
				echo Text::translate('COM_FTK_LABEL_BAD_PART_TEXT', $lang);
			?></a>
			<?php else : ?>
			<span class="badge badge-success quality-badge float-right"><?php
				echo Text::translate('COM_FTK_LABEL_GOOD_PART_TEXT', $lang);
			?></span>
			<?php endif; ?>

			<?php /* SAMPLE part badge */ ?>
			<?php if ($this->isSample) : ?>
			<span class="badge badge-dark sample-part-badge text-uppercase float-right mr-2 px-3 px-lg-4"
				  data-toggle="tooltip"
				  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_IS_SAMPLE_TEXT', $lang); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_IS_SAMPLE_TEXT', $lang); ?>"
			><?php
				echo Text::translate('COM_FTK_LABEL_SAMPLE_PART_TEXT', $lang);
			?></span>
			<?php endif; ?>
		</h2>

		<fieldset class="part-params">
			<legend class="sr-only fieldset-title"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?></legend>
			<div class="row form-group">
				<label for="type" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $lang); ?>:</label>
				<div class="col input-group">
					<input type="text"
						   name="type"
						   value="<?php echo html_entity_decode($this->item->get('type')); ?>"
						   class="form-control"
					       readonly
					/>
					<?php if ($this->item->isComponent() && count($isComponentOf = $this->item->get('isComponentOf'))) : ?>
					<?php $parentPart = current($isComponentOf); ?>
						<div class="input-group-append">
							<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d&pid=%d#p-%s',
									$lang,
									$layout,
									ArrayHelper::getValue($parentPart, 'partID', 0, 'INT'),
									ArrayHelper::getValue($parentPart, 'procID', 0, 'INT'),
									hash('md5', ArrayHelper::getValue($parentPart, 'procID', 0, 'INT')),
							   ))); ?>"
							   role="button"
							   class="btn btn-outline-info"
							   id="link-to-lot"
							   data-toggle="tooltip"
							   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_PARENT_ELEMENT_VIEW_THIS_TEXT', $lang); ?>"
							>
								<i class="fas fa-level-up-alt pl-1" style="padding-right:2px"></i>
								<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_PART_PARENT_ELEMENT_SHOW_THIS_TEXT', $lang); ?></span>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<div class="row form-group">
				<label for="code" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_TRACKING_CODE_TEXT', $lang); ?>:</label>
				<div class="col input-group">
					<div class="input-group-prepend">
						<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=part&layout=%s&task=print.masterdata&ptid=%d',
								$lang,
								$layout,
								$this->item->get('partID')
						   ))); ?>"
						   role="button"
						   class="btn btn-outline-secondary"
						   id="link-to-masterdata-print"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_PRINT_MASTERDATA_TEXT', $lang); ?>"
						   target="_blank"
						>
							<i class="fas fa-qrcode"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_PRINT_MASTERDATA_TEXT', $lang); ?></span>
						</a>
					</div>
					<input type="text"
						   name="code"
						   value="<?php echo html_entity_decode($this->item->get('trackingcode')); ?>"
						   class="form-control"
						   readonly
						   aria-describedby="link-to-lot"
						   style="letter-spacing:1px"
					/>
					<?php if ((!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier())
							&& (int) $itemLot->get('lotID') > 0 && ($itemLot->get('lotID') === $this->item->get('lotID'))
					) : ?>
					<div class="input-group-append">
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=parts&layout=lot&lid=%s', $lang, $itemLot->get('number') ))); ?>"
						   role="button"
						   class="btn btn-outline-info"
						   id="link-to-lot"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_VIEW_THIS_TEXT', $lang); ?>"
						   target="_blank"
						>
							<i class="fas fa-layer-group"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_VIEW_THIS_TEXT', $lang); ?></span>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</fieldset>
	</section>
	<hr>
	<?php // @debug ?>
	<?php if ($user->isProgrammer()) : ?>
	    <pre class="text-bold">layout: <span class="text-danger"><?php echo $layout; ?></span></pre>
	<?php endif; ?>

	<?php // Banderole + Process list ?>
	<section class="position-relative pb-4<?php echo ($showBanderole ? ' isBanderole' : ''); ?>" id="processTree">
		<h3 class="h5 d-inline-block mt-lg-4 mb-lg-4"><?php
			echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $lang);
		?></h3>

		<div class="row">
			<?php // Banderole (display only if there are processes + $showBanderole is true ?>
			<?php if (count($itemProcesses) && $showBanderole) : ?>
			<div class="col-1"><?php echo LayoutHelper::render('widget.banderole', ['banderoleData' => $banderoleData], ['language' => $lang]); ?></div>
			<?php endif; ?>

			<?php // Process list ?>
			<div class="col-<?php echo ($showBanderole ? '11 pl-0' : '12'); ?>">

		<?php if (count($itemProcesses)) : $j = 0; ?>
		<div class="timeline position-relative <?php echo ($showBanderole ? 'border-0' : ''); ?>" id="partProcesses">
			<?php // Iterate over the item process list znd load and render process by process ?>
			<?php foreach ($itemProcesses as $artProcID => $artProc) : ?><?php
				// Get reference to this process from the article processes list to get hands on the drawing
				// and load process information into a process entity object for reliable data access.
				$process = (new Process(['language' => $lang]))->bind (ArrayHelper::getValue($processes, $artProcID, []) );

				// Tracking is only allowed for authorised organisations or users with higher privileges.
				// Calculate whether this process is accessible by the user's organisation.
				$isOrgProcess = in_array($artProcID, $orgProcesses);

				// Calculate whether this process is a press-in process (MNT/SCF).
				$isPressinProcess   = array_key_exists($artProcID, $mntORscfProcs);
				$isTrackedByMachine = $isPressinProcess;

				// Get reference to technical paramaters assigned to this process.
				$techParams    = (array) $process->get('tech_params');
				$hasTechParams = count($techParams) > 0;

				// A process can be tracked only if the requested process ID (pid in URI) was not already tracked (is in the list of untracked process IDs).
                $isTrackable = !in_array($artProcID, $untrackedPids);
                $isDisableProcess = in_array($artProcID, $disableProcess);

                //print_r($disableProcess);
                //$matchingValues = array_intersect($disableProcess, $total);
                $notTrackable = !in_array($artProcID, $total);
                //print_r($itemProcesses);
				// A process can be tracked only if the requested process ID (pid in URI) matches the calculated next editable process ID.
				$isTracked   =  in_array($artProcID, $trackedPids);
                /*print_r($trackedPids);
                 echo $artProcID;*/
                //echo $isTracked;

				// If any of the item's processes is BAD, block follow-up processes from being tracked.
				// A process can only be edited when it was already tracked. Hence, its ID must come prior $nextPid.
				if ($itemHasBadProcess) :
					$isTrackable = $isTrackable && ($artProcID != $nextPid);
				endif;

				// Load corresponding user input from this part's tech parameters user input data.
				$trackedProcess         = new Registry(ArrayHelper::getValue($trackingData, $artProcID));

				// Load master data for this process if it is already tracked.

				$trackedProcessOperator = $trackedProcess->get(Techparams::STATIC_TECHPARAM_OPERATOR);
				$trackedProcessDate     = $trackedProcess->get(Techparams::STATIC_TECHPARAM_DATE);
				$trackedProcessTime     = $trackedProcess->get(Techparams::STATIC_TECHPARAM_TIME);
				$trackedProcessStatus   = $trackedProcess->get(Techparams::STATIC_TECHPARAM_ERROR);



				/* Operator names must be visible to selected members only (NOTE: data privacy).
				 * Only members of FRÖTEK and NEMATECH must see such information + other members if
				 * they belong to the organisation managing this process.
				 */
				$displayOperatorName = UserHelper::isFroetekOrNematechMember($user);
				$displayOperatorName = $displayOperatorName ?: in_array($user->get('orgID'), $process->get('organisations'));

				/* Check whether a process is already tracked and access is therefore blocked.
				 * A process is classified as "blocked" if the tracking data contains:
				 *	- Operator name
				 *	- Tracking date
				 *	- Tracking time
				 *	- Tracking status + (error) status
				 */
				$isTracked = (
					!empty($trackedProcessOperator) &&
					!empty($trackedProcessDate)     &&
					!empty($trackedProcessTime)     &&
					is_numeric($trackedProcessStatus)
				)
				/* Prevent manual tracking of press-in process(es) (NOTE: identifiable via process abbreviation MNTx/SCFx)
				 * to prevent the tracking data from being messed like happened in the past.
				 * For this purpose, the process ID is looked up in the list of press-in process IDs.
				 * If it is found in it, it is an autmatically tracked process and the process is classified as "blocked".
				 */
				|| $isTrackedByMachine;


				// Calculation of whether a tracking can still be edited or not.
				$dateNow               = date_create('NOW');
				$dateTrackingCreated   = $isTracked ? date_create(sprintf('%s %s', $trackedProcessDate, $trackedProcessTime)) : null;	// FIXME - test whether the null can be replace with $dateNow

				$dateTrackingExpires   = is_a($dateTrackingCreated, 'DateTime')
											// If the process is already tracked, the initial value for the expiration data/time is the creation date/time.
											// This value is going to be fixed in relation to a defined time window in the next step.
											? clone($dateTrackingCreated)
											: null;					// FIXME - test whether the null can be replace with $dateTrackingCreated

				// Get expiration time as defined in Defines.
				$timeFrame             = DateInterval::createFromDateString(FTKRULE_EDIT_TRACKING_EXPIRES);

				// Set expiration time (is defined in Defines.php as FTKRULE_EDIT_TRACKING_EXPIRES)
				(is_a($dateTrackingExpires, 'DateTime')) ? $dateTrackingExpires->add($timeFrame) : null;	// FIXME - test whether the null can be dropped

				// Calculate the remaining time to edit a previous tracking.
				$isEditExpired = !is_null($dateTrackingCreated) && !is_null($dateTrackingExpires) && $dateNow > $dateTrackingExpires;

				// Ensure every date variable is a proper date.
				$dateTrackingCreated = is_null($dateTrackingCreated) ? $dateNow : $dateTrackingCreated;	// FIXME - remove after fixing lines 1038, 1039 and 1046

				/* Determination of the current user's basic right to track. */

				/* A user is the original author of a tracking if:
				 *	- its full name matches the full operator name in the existing tracking data
				 */
				$userIsAuthor   = $trackedProcessOperator == $user->get('fullname');
				$userIsQManager = $user->isQualityManager();

				/* A user can track a process, if:
				 *	- the user access is not blocked
				 *	- the process is open for tracking (NOTE: some processes are automatically tracked like press-in processes and therefore not manually trackable)
				 *	- the process is managed by the user's organisation
				 */
				$userCanTrack = !$user->get('blocked');
				$userCanTrack = $userCanTrack && !$isTracked;	// MODDED on 2023-06-20 - "$userCanTrack && " prepended
				$userCanTrack = $userCanTrack &&  $isOrgProcess;

				/* A user can edit a previously tracked process, if:
				 *	- the user access is not blocked
				 */
				$userCanEdit  = $isTracked && $userIsAuthor && !$isEditExpired;
				/* When the editing time window is closed, only authorised users
				 * with appropriate rights can still enable editing (Quality manager).
				 */
//				$userCanEdit  = (!$isEditExpired) ? $userCanEdit : $user->getFlags() >= \Nematrack\Access\User::ROLE_PROGRAMMER;

				// A quality responsible can re-open an expired editing time window.
				$userCanReleaseEditing = false;

				/* A tracking must be editable by the original author within a defined time window.
				 * After the editable time window has expired, another user with process release authorisation, e.g.
				 * a quality manager, must release the process for editing and/or edit it himself/herself, if this user
				 * is not the original author. A user with this authorisation may not release a process to himself/herself.
				 *
				 * It must be ensured that the user authorised to release data belongs to the same organisation as the original author.
				 * Authorisation alone is not sufficient, as this would enable a user with authorisation to share who is not
				 * a member of the organisation to manipulate other people's data.
				 */
				if ($isTracked && $userIsQManager) :
					$author          = $trackedProcess->get(Techparams::STATIC_TECHPARAM_OPERATOR);
					$authorOrg       = $trackedProcess->get(Techparams::STATIC_TECHPARAM_ORGANISATION);
					$authorOrg       = $orgModel->getOrganisationByName('' . $authorOrg);
					$authorOrgID     = $authorOrg->get($authorOrg->getPrimaryKeyName());
					$authorOrgName   = $authorOrg->get('name');
					$qManagerOrg     = &$userOrganisation;
					$qManagerOrgID   = $qManagerOrg->get($qManagerOrg->getPrimaryKeyName());
					$qManagerOrgName = $qManagerOrg->get('name');

					if ($qManagerOrgID !== $authorOrgID) :
						$userCanEdit           = false;
						$userCanReleaseEditing = false;
					else :
						$userCanEdit           = false;
						$userCanReleaseEditing = true;
					endif;
				endif;

				// Prepare process drawing pic + PDF for display and download.
				$drawing   = new Registry($artProc->get('drawing'));
				$processNo = sprintf('%s.%s', $drawing->get('number'), $drawing->get('index'));

				$filePDF   = UriHelper::osSafe( UriHelper::fixURL($drawing->get('file')) );
				$filePDF   = (is_file(FTKPATH_BASE . $filePDF) && is_readable(FTKPATH_BASE . $filePDF))
								? $filePDF . '?t=' . mt_rand(0, 9999999)
								: null;

				$images    = (array) $drawing->get('images');
				$image     = count($images) ? current($images) : null;
				$image     = isset($image)  ? UriHelper::osSafe( UriHelper::fixURL($image) ) : null;
				$image     = ((!is_null($image) && is_file(FTKPATH_BASE . $image) && is_readable(FTKPATH_BASE . $image))
								? $image . '?t=' . mt_rand(0, 9999999)
//								: 'https://via.placeholder.com/280x198/E9ECED/F00.png?text=' . Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang));
								: sprintf('https://%s/280x198/E9ECED/F00.png?text=%s', FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER, Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang)));

				// Extract error catalogue items to be selectable for this process.
				$errorList = array_slice($errors, 0, $j+1, true);

				//$processMeasuringfinitions = ArrayHelper::getValue($measurementDefinitions, $artProcID, [], 'ARRAY');

				// Calculate HTML attribute for blocked processes.
				$htmlStatusAttr = (!$isTrackable) ? ' disabled' : '';
			?>
			<div class="<?php echo trim(sprintf('list-item dynamic-content event process position-relative %s', ($isTracked ? 'tracked' : 'untracked'))); ?>"
				 id="p-<?php echo hash('MD5', $artProcID); ?>" data-proc-id="<?php echo (int) $artProcID; ?>"
			>
				<?php //-> BEGiN: Process masterdata + technical parameters ?>
				<fieldset class="part-process-tracking position-relative<?php //echo $cssStatusClass; ?>"
						  id="process-<?php echo (int) $artProcID; ?>"
				>
					<legend></legend>
                <?php //-> BEGiN: TOOLBAR ?>
					<?php // Process name + Current process drawing number ?>
					<h5 class="h6 text-uppercase d-inline-block pt-lg-1 mr-lg-3 mb-4" style="margin-top:1px"><?php echo html_entity_decode($process->get('name')); ?></h5>
					<small class="d-none d-md-inline mr-3 text-muted" style="vertical-align:text-top">( <?php echo html_entity_decode($processNo); ?> )</small>

					<?php // BUTTON: media-box ?>
					<?php if ($isTrackable && in_array($artProcID, $trackedPids)) : // Media is only available if all preceding processes have been tracked. ?>
					<?php	$modalForm = [ LayoutHelper::render('widget.controls.mediabox',
							[
								'context' => 'tracking',	// can be one of 'article', 'part', 'tracking'
								'item'    => $this->item,
								'process' => $process,
								'attribs' => []
							],
							['language' => $lang]
					)];
					?>
					<button type="button"
					        role="button"
					        class="btn btn-sm btn-secondary btn-mediabox ml-2 float-right"
							id="btn-camera-<?php echo $artProcID; ?>"
					        title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_VIEW_PROCESS_SUPPORT_MEDIA_TEXT', $lang); ?>"
							data-toggle="modal"
							data-size="lg"
							data-backdrop="static"
							data-target="#mainModal"
							data-modal-title="<?php echo Text::translate('COM_FTK_HEADING_PROCESS_MEDIA_FILES_TEXT', $lang); ?>"
							data-modal-content="<?php echo base64_encode(implode($modalForm)); ?>"
							data-modal-submittable="false"
							aria-haspopup="true"
					>
						<i class="fab fa-dropbox fa-lg"></i>
						<span class="d-none ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_OPEN_TEXT', $lang); ?></span>
					</button>
					<?php endif; ?>

					<?php // BUTTON: Start/Edit Tracking ?>
<style>
    .disbtnpro {
        background: grey !important;
    }
    .disbbtt{
        display: none;
    }
</style>
					<?php if ($isTrackable) : // Tracking is only possible if all preceding processes have been tracked. ?>


					<?php 	if (!$this->isBlocked) : ?>
					<?php	if (($userCanTrack || $userCanEdit) || !$isTracked) : ?>
					            <?php // userCanTrack when not isBlocked , userCanEdit when isBlocked + userIsAuthor + not isEditExpired
									// Calculate expiration time window.
									if ($userCanEdit) : ?>
									<?php
										$hoursLeft   = $dateTrackingExpires->diff($dateNow)->h;
										$minutesLeft = $dateTrackingExpires->diff($dateNow)->i;
										$secondsLeft = $dateTrackingExpires->diff($dateNow)->s;
										$timeLeft    = ($minutesLeft < 1 ?  $secondsLeft : $minutesLeft);
										$unit        = ($minutesLeft < 1
											? ($secondsLeft == 1 ? 'COM_FTK_TIME_UNIT_SECOND' : 'COM_FTK_TIME_UNIT_SECONDS')
											: ($minutesLeft == 1 ? 'COM_FTK_TIME_UNIT_MINUTE' : 'COM_FTK_TIME_UNIT_MINUTES') );
					?><?php			endif; ?>

					<?php		// BUTTON: Add/Edit Tracking ?>

					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d&pid=%d%s&artid=%d',
							$lang,
							$this->item->get('partID'),
							$artProcID,
							($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : ''),
                        $this->item->get('artID')
					   ))); ?>#p-<?php echo hash('CRC32', $artProcID); ?>"
					   role="button"
					   class="btn btn-sm btn-info float-right <?php if($isDisableProcess){ echo "disbtnpro"; if($notTrackable){echo " disbbtt";}} ?> <?php ?>"
					   <?php if ($userCanEdit) : ?>
					   title="<?php echo sprintf(Text::translate('COM_FTK_HINT_EDITABILITY_EXPIRATION_TIME_TEXT', $lang), $timeLeft, Text::translate($unit, $lang)); ?>"
					   data-title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_EDIT_THIS_TEXT', $lang); ?>"
					   <?php endif; ?>
					   data-toggle="tooltip"
					   style="vertical-align:baseline"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="d-none d-md-inline ml-lg-1"><?php echo !$isTracked
							? Text::translate('COM_FTK_LABEL_PART_PROCESS_TRACK_TEXT', $lang)
							: Text::translate('COM_FTK_LABEL_PART_PROCESS_EDIT_TRACKING_TEXT', $lang);
						?></span>
						<?php // Render editability expiration icon as long as this process remains editable ?>
						<?php if ($userCanEdit) : ?>
						<i class="fas fa-history ml-2 fa-lg text-gold">
							<small class="d-inline-block align-middle ml-1 <?php echo ($hoursLeft == 0 && $minutesLeft == 0) ? 'text-bold text-gold' : 'text-white'; ?>"
								   data-bind="countdown"
								   data-count-hours="<?php   echo $hoursLeft; ?>"
								   data-count-minutes="<?php echo $minutesLeft; ?>"
								   data-count-seconds="<?php echo $secondsLeft; ?>"
								   style="font-family:Consolas"
							><?php
								echo sprintf('%s:%s', ($minutesLeft < 10 ? '0' . $minutesLeft : $minutesLeft), ($secondsLeft < 10 ? '0' . $secondsLeft : $secondsLeft));
							?></small>
						</i>
						<?php endif; ?>
					</a>

					<?php 		else : // ... otherwise this process is read only (as discussed between Tino and Sebastian and decided by Sebastian on 2020-03-19 ?>
					<?php	if ($isPressinProcess) : // If process is a press-in process render hint regarding auto-tracking via API ?>
					<span><?php echo LayoutHelper::render('system.alert.info', [
						'message' => sprintf('%s %s',
							Text::translate('COM_FTK_HINT_MANUAL_TRACKING_IS_DISABLED_TEXT', $this->language),
							Text::translate('COM_FTK_HINT_PROCESS_IS_AUTO_TRACKED_BY_THE_MACHINE_TEXT', $this->language)
						),
						'attribs' => [
							'class' => 'alert-sm'
						]
					]);
					?></span>
					<?php 			else : ?>
					<?php		// BUTTON: Tracking is blocked ?>
					<span role="button"
					      class="btn btn-sm btn-info float-right disabled"
					      data-toggle="tooltip"
					      title="<?php echo sprintf('%s %s',
							Text::translate('COM_FTK_HINT_EDITING_IS_LOCKED_TEXT', $lang),
							Text::translate('COM_FTK_HINT_PLEASE_CONTACT_AN_ADMINISTRATOR_FOR_ASSISTANCE_TEXT', $lang)
					      ); ?>"
					      style="vertical-align:baseline"
					>
						<i class="fas fa-lock text-danger"></i>
						<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_EDITING_LOCKED_TEXT', $lang); ?></span>
					</span>
					<?php 			endif; ?>

					<?php 		endif; // END: (userCanTrack || userCanEdit) || !isTracked ?>

					<?php 	// Hint: Organisation is not authorised to track this process ?>

					<?php 	elseif (!$isOrgProcess && $hasTechParams) : // ELSE: !this->isBlocked ?>
					<div class="mb-2"><?php echo LayoutHelper::render('system.alert.notice', [
						'message' => Text::translate('COM_FTK_HINT_PROCESS_IS_NOT_MANAGED_BY_YOUR_ORGANISATION_TEXT', $lang),
						'attribs' => [
							'class' => 'alert-sm'
						]
					]);
					?></div>
					<?php 	endif; // END: !this->isBlocked ?>
					<?php endif; // END: isTrackable ?>
                    <?php //<- END: toolbar / buttons ?>


                    <?php //-> BEGiN: Drawing preview ?>

                    <?php //<- END: Drawing preview ?>

					<?php // Iterate over all technical parameters assigned to a process and take its ID to read a potential input from the parts technical parameters data provided via user input ?>
					<?php if (!$hasTechParams) : ?>

						<?php // Display notification that the process has no technical parameters and can therefore not be tracked. ?>
						<?php if ($user->isWorker() || $user->getFlags() >= User::ROLE_MANAGER) : ?>
							<?php echo LayoutHelper::render('system.alert.info', [
								'message' => Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_TECHNICAL_PARAMETERS_TEXT', $lang),
								'attribs' => [
									'class' => 'alert-sm mt-md-2 mt-lg-3 mb-0'
								]
							]); ?>
							<?php continue; ?>
						<?php endif; ?>

					<?php else : ?>


					<?php endif; ?>
                    <?php if ($isTracked == 'tracked') : // Means that this process is visible and accessible and not covered by a blocking overlay ?>
                        <a
                                data-artProcID="<?php echo $artProcID; ?>"
                                data-pid="<?php echo $pid; ?>"
                                data-partid="<?php echo $this->item->get('partID');?>"
                                data-lotid="<?php echo $this->item->get('lotID');?>"

                                id="data-process-<?php echo $artProcID; ?>"
                                class="view-process-details btn btn-sm btn-info float-right"
                                href="#"
                        >View Process Details</a>
                    <?php endif;?>

				</fieldset>

                    <div id="process-details-<?php echo $artProcID;?>" class=""></div>

			</div>
			<?php	 $j += 1; ?>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
			<?php if ($user->getFlags() >= User::ROLE_MANAGER) : ?>
				<?php echo LayoutHelper::render('system.alert.info', [
					'message' => Text::translate('COM_FTK_HINT_ARTICLE_HAS_NO_PROCESSES_TEXT', $lang),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php endif; ?>
		<?php endif; ?>

			</div>
		</div>
	</section>

	<?php /*// Overlay to block user interaction ?>
	<?php if ($this->isBlocked && $user->getFlags() < User::ROLE_ADMINISTRATOR) : ?>
	<?php 	echo LayoutHelper::render('system.element.blocked', new \stdclass, ['language' => $lang]); ?>
	<?php endif;*/ ?>
</article>
<?php else : ?>

<style>body {margin:0 !important}</style>
<?php 	include_once __DIR__ . sprintf('/%s/print/portrait.php', strtolower($layout)); ?>

<?php endif; //-> !isPrint ?>

<?php // Free memory.
unset($abbreviations);
unset($artProcID);
unset($drawing);
unset($errColor);
unset($errNum);
unset($errText);
unset($input);
unset($item);
unset($itemHasBadProcess);
unset($isDisableProcess);
unset($notTrackable);
unset($total);
unset($itemLot);
unset($itemProcesses);
unset($itemProcessIDs);
unset($measuredData);
unset($measurementDefinitions);
unset($mntORscfProcsel);
unset($model);
unset($orgProcesses);
unset($process);
unset($processes);
unset($staticTechParams);
unset($trackingData);
unset($user);
unset($view);
?>

<?php if ($isPrint) : ?>
<script>window.print();</script>
<?php endif; ?>
<style>
    .view-process-details{
        background: #6c757d;
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 12px;
        margin: 0 5px;

    }
    .view-process-details:hover {
        text-decoration: none;
        color: #fff;
        background: #3a3a3a;
    }
</style>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script>
    $(document).ready(function() {
        // Delegate click event to only trigger for the clicked .view-process-details link
        $(document).on('click', '.view-process-details', function(event) {
            event.preventDefault(); // Prevent default link navigation

            const artProcID = $(this).data('artprocid');
            const partID = $(this).data('partid');
            const pID = $(this).data('pid');
            const lotID = $(this).data('lotid');
            const lang = <?php echo json_encode($lang); ?>

            var processDetailsDiv  = $('#process-details-' + artProcID);   //$('#process-details-'+artProcID)
            /*if (!processDetailsDiv.data('loaded')) {
                processDetailsDiv.data('loaded', true); // Mark as loaded
                processDetailsDiv.html('<img src="https://track.vendeglatoeszkozok.com/assets/img/global/spinner.gif" alt="Loading...">');
            } else {
                processDetailsDiv.html('<img src="https://track.vendeglatoeszkozok.com/assets/img/global/spinner.gif" alt="Loading...">');
                processDetailsDiv.show();
                $.ajax({
                    url: 'https://track.vendeglatoeszkozok.com/index.php?hl=hu&view=part&layout=itemSpace', // PHP script for processing
                    type: 'POST',
                    data: {
                        artProcID: artProcID,
                        partID: partID,
                        pID: pID,
                        lotID: lotID,
                        lang: lang,
                    },
                    success: function (response) {
                        // Display response in the process details container
                        $('#process-details-' + artProcID).html(response);
                        processDetailsDiv.show();
                    },
                    error: function (xhr, status, error) {
                        console.error('Error fetching data:', error);
                    }
                });
            }*/
            if (!processDetailsDiv.data('loaded')) { // Check if data is already loaded
                processDetailsDiv.data('loaded', true); // Mark as loaded
                processDetailsDiv.html('<img src="https://track.vendeglatoeszkozok.com/assets/img/global/spinner.gif" alt="Loading...">');
                processDetailsDiv.show();

                $.ajax({
                    url: 'https://track.vendeglatoeszkozok.com/index.php?hl=hu&view=part&layout=itemSpace', // PHP script for processing
                    type: 'POST',
                    data: {
                        artProcID: artProcID,
                        partID: partID,
                        pID: pID,
                        lotID: lotID,
                        lang: lang,
                    },
                    success: function(response) {
                        // Display response in the process details container
                        processDetailsDiv.html(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching data:', error);
                        processDetailsDiv.html('<p>Error loading details. Please try again later.</p>');
                    }
                });
            } else {
                processDetailsDiv.toggle(); // Toggle visibility if already loaded
            }
        });
    });
</script>