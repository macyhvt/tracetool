<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Entity\Process;
use Nematrack\Helper\FilesystemHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\MediaHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Model\Techparams;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$debug  = $input->getCmd('auth') === 'dev-op';

//$return = $view->getReturnPage();	// Browser back-link required for back-button.
$return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');	// the user who requested this page --- Note: In addition to this user, there are also
								// the operator (author of an existing tracking) and
								// the quality manager (required for the release of a tracking for editing) on this page.
								// There are dependencies and interactions between these users, which are calculated and evaluated in the further course.
$debug  = $debug && $user->isProgrammer();
$debug  = false;

$layout = $input->getCmd('layout');

$pid    = $input->getInt('pid');

$task    = $input->post->getCmd('task',    $input->getCmd('task'));
$format  = $input->post->getWord('format', $input->getWord('format'));
$isPrint = $task === 'print' || current(explode('.', $task)) === 'print';

// Was this layout loaded after a tracking approval?
$isApproval = $task === 'approve';	// ADDED on 2023-07-20
?>

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

	header('Location: ' . View::getInstance('parts', ['language' => $lang])->getRoute());
	exit;
endif;

$this->item       = $item;

$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';
$this->isApproval = $isApproval;
$this->isSample   = $this->item->get('sample')   == '1';
?>
<?php /* Load more view data - required when not in print mode */ ?>
<?php if (!$isPrint) :
// Get hands on processes this user's organisation is responsible for. It's required for access control.
$orgModel         = $this->view->get('model')->getInstance('organisation', ['language' => $this->language]);
$userOrg          = $orgModel->getItem((int) $user->get('orgID'));	// the organisation to which the currently logged in user belongs
$userOrgID        = $userOrg->get('orgID');
$userOrgName      = $userOrg->get('name');
$userOrgProcesses = (array) $userOrg->get('processes', []);			// the processes for which the organisation to which the currently logged-in user belongs is responsible
/*$userOrgAdmins    = $orgModel->getAdmins([
	$userOrg->getPrimaryKeyName() => $userOrg->get($userOrg->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);*/
/*$userOrgQualityManagers = $orgModel->getQualityManagers([
	$userOrg->getPrimaryKeyName() => $userOrg->get($userOrg->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);*/
$userOrgQualityResponsibles = $orgModel->getQualityResponsibles([
	$userOrg->getPrimaryKeyName() => $userOrg->get($userOrg->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);

$processes     = $model->getInstance('processes', ['language' => $lang])->getList(['params' => true]);
// Extract abbreviations.
$abbreviations = array_column($processes, 'abbreviation', 'procID'); array_map('mb_strtolower', $abbreviations);

// Extract abbreviations belonging to SCF-processes.
// These processes must not be editable. Press-in data is sent to the TrackingTool by the Press-in machine.
// There was too much mess created by Jermaine and Manuel that made Tino decide on this preventive intervention.
$mntORscfProcs = array_filter($abbreviations, function($abbreviation) { return preg_match('/^(scf)\d{1,2}$/', $abbreviation); });

// Grant edit right for SCF-processes to privileged users to allow tracking of bad parts.
// Quality responsibles and Developers should be able to edit an automatic tracking.
/*if ($user->isQualityAssurance() || $user->isQualityManager() || $user->isProgrammer()) :
	$mntORscfProcs = [];
endif;*/

// Get lot this part belongs to (if any).
$itemLot        = $model->getInstance('lot', ['language' => $lang])->getItem((int) $this->item->get('lotID'));

// Get reference to the list of processes an article must run through.
$itemProcesses  = (array) $this->item->get('processes');
$itemProcessIDs = array_keys($itemProcesses);

// Get reference to tracking data for each of these processes.
$trackings = (array) $this->item->get('trackingData');	// List of all tracking entries for this part (every tracking entry is an object representing the table row)

// Separate untracked processes from previously tracked processes and
// dump previously tracked pid as well as next pid to be tracked.
$trackedPids                         = array_keys($trackings);
$untrackedPids                       = [];
$diffProcessIDsVsProcessIDsUntracked = [];
$itemProcessIDsUntracked             = array_filter($itemProcessIDs, function($id) use(&$trackedPids) {	// becomes $untrackedPids further below
	return !in_array($id, $trackedPids);
});
$diffProcessIDsVsProcessIDsUntracked = array_diff($itemProcessIDs, $itemProcessIDsUntracked);   // required to find the previous tracked process

// Dump pids of previous and next process.
$firstPid       = current($itemProcessIDs);                    // the very first process in line
$lastPidTracked = end($diffProcessIDsVsProcessIDsUntracked);   // the last tracked process
$nextPid        = current($itemProcessIDsUntracked);           // the next trackable process

// Skip first untracked process id to enable it for editing.
array_shift($itemProcessIDsUntracked);

// Calculate first process to be overlayed to prevent edit.
if (empty($lastPidTracked)) :
	$untrackedPids = array_slice($itemProcessIDs, 1);
else :
	$untrackedPids = $itemProcessIDsUntracked;

	/*
	// ADDED on 2023-08-25 - fix conflict where the last process is not tracked but was not accessible
	if (!count($untrackedPids)) :
		$untrackedPids[] = $nextPid;
	endif;
	*/
endif;

// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$staticTechParams = (array) $model->getInstance('techparams', ['language' => $lang])->getStaticTechnicalParameters(true);

// Retrieve the error catalogue for all processes that this part has to run through.
/*// CHANGED on 2023-11-17 - moved to \Nematrack\View\Part::prepareErrorList()
$errors   = $model->getInstance('errors', ['language' => $lang])->getErrorsByLanguage(
	(new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($this->language)))->get('lngID'),
	$itemProcessIDs
);*/
$errors = $this->view->get('errors');

//$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));	// DiSABLED on 2023-08-15 - no longer required at all
//$dateNow  = $now->format('Y-m-d');	// DiSABLED on 2023-08-15 - no longer required here. re-defined in line 1041
//$timeNow  = $now->format('H:i:s');	// DiSABLED on 2023-08-15 - no longer required at all

$errNum   = null;
$errText  = null;
$errColor = null;

$isAutotrack  = $input->getInt('at') == '1';
$isAutofill   = $input->getInt('af') == '1';
$isAutosubmit = $input->getInt('as') == '1';

// Detect whether this part is a good part or bad part.
// This info is required for the badge.
$itemHasBadProcess = false;
$badProcID         = null;	// will receive the process id to link to on click onto the quality classifier badge

// If item is a bad part, fetch ID of process this part failed.
if ($this->item->isBad()) :
	array_walk($trackings, function($data, $procID) use(&$itemHasBadProcess, &$badProcID)
	{
		if ($itemHasBadProcess) :
			return;
		endif;

		$data  = (array) $data;

		$match = array_filter($data, function($entry, $paramID) use(&$user)
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

// @debug
//echo '<pre><strong>itemProcessIDs: </strong>' . print_r(json_encode($itemProcessIDs), true) . '</pre>';
//echo '<pre><strong>trackedPids: </strong>'    . print_r(json_encode($trackedPids), true) . '</pre>';
//echo '<pre><strong>itemProcessIDsUntracked aka untrackedPids: </strong>' . print_r(json_encode($itemProcessIDsUntracked), true) . '</pre>';
//echo '<pre><strong>untrackedPids: </strong>'  . print_r(json_encode($untrackedPids), true) . '</pre>';
//echo '<pre><strong>firstPid: </strong>'       . print_r($firstPid, true) . '</pre>';
//echo '<pre><strong>lastPidTracked: </strong>' . print_r($lastPidTracked, true) . '</pre>';
//echo '<pre><strong>nextPid: </strong>'        . print_r($nextPid, true) . '</pre>';
//die;
?>
<?php /* Banderole */
// Get reference to measuring definitions and measured data for each of these processes.
$articleModel = $model->getInstance('article');
$measurementDefinitions = $articleModel->getDefinedMeasuringPoints($this->item->get('artID'));
$measuredData = (array) $this->item->get('measuredData');

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
endforeach;

// Init vars related to this part's process banderole.
$banderoleData = [];
$showBanderole = false; // Shall the banderole be displayed at all?

// Loop over item processes to pick and count all entries that shall be banderolable.
array_walk($itemProcesses, function($itemProcess, $procID) use(
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
});

/* Detect whether to display the banderole at all.
 * If only one process has this parameter configured, then the banderole is to be displayed.
 *
 * Not until at least 1 opaque process is found is the banderole rendered.
 */
//$showBanderole = count($banderoleData) && array_search('opaque', $banderoleData, TRUE);

endif; //-> !isPrint
?>

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

.tracking-hint {
	margin-left: 15px;
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
	min-height: 35%;
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
fieldset.part-process-tracking:focus {
    background: rgba(0, 123, 255, 0.1);
    box-shadow: 0 0 10px 10px rgba(0, 123, 255, 0.1);
}
<?php if (FALSE) : ?>
.untracked.process > fieldset.part-process-tracking:before {
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
.untracked.process > fieldset.part-process-tracking:before {
	display: block;
	z-index: 1;
}
.untracked.process > fieldset.part-process-tracking:before {
	content: "<?php echo Text::translate('COM_FTK_HINT_PROCESS_TRACKING_ONLY_AFTER_PREV_PROCESSES_TRACKED_TEXT', $lang); ?>";
}
<?php endif; ?>

.form-control.bg-success {
	background-color: rgba(  1, 163, 28, 0.2) !important;
}
.form-control.bg-warning {
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
	<?php if ($user->get('userID') == '1' || $user->get('userID') == '102') : ?>
	<pre class="text-bold">layout: <span class="text-danger"><?php echo $layout; ?>_new</span></pre>
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

			<?php // @debug
//				echo '<pre>mntORscfProcs: ' . print_r(json_encode($mntORscfProcs), true) . '</pre>'; ?>

			<?php // Process list ?>
			<div class="col-<?php echo ($showBanderole ? '11 pl-0' : '12'); ?>">
				<?php if (count($itemProcesses)) : $j = 0; ?>
				<div class="timeline position-relative <?php echo ($showBanderole ? 'border-0' : ''); ?>" id="partProcesses">
					<?php // Iterate over the item process list and load and render process by process ?>
					<?php foreach ($itemProcesses as $artProcID => $artProc) : ?><?php
						// @debug
//						echo '<pre><strong>artProcID: </strong>' . print_r($artProcID, true) . '</pre>';

						// Load process definition data into a process entity object for reliable data access.
						$process = (new Process(['language' => $lang]))->bind( ArrayHelper::getValue($processes, $artProcID, []) );

						// Calculation of whether this process is accessible to the user's organisation.
						// Tracking is only allowed for organisations that manage this process.
						$isOrgProcess  = in_array($artProcID, $userOrgProcesses);

						// Get reference to its technical paramaters (as defined in the TT master data section "processes").
						$techParams    = (array) $process->get('tech_params');
						$hasTechParams = count($techParams) > 0;

						// Is this process is managed by the user's organisation?
						// Tracking is only allowed for users who belong to an organisation that manages this process.
						$process->__set('isManagedByUserOrganisation', in_array($artProcID, $userOrgProcesses));

						// Is this process a press-in process (MNT/SCF) ?
						$process->__set('isPressinProcess', array_key_exists($artProcID, $mntORscfProcs));

						// @debug
//						echo '<pre><strong>isPressinProcess: </strong>' . print_r($process->__get('isPressinProcess'), true) . '</pre>';

						// Is this process automatically tracked by a machine via the TrackingTool API?
						// A process must not be directly editable if it is tracked automatically by a machine via API.
						$process->__set('isTrackedByMachine', $process->__get('isPressinProcess'));

						// @debug
//						echo '<pre><strong>isTrackedByMachine: </strong>' . print_r($process->__get('isTrackedByMachine'), true) . '</pre>';

						// Is this process already tracked?
						// A process can only be tracked if the requested process ID (variable $pid in URI) matches the calculated ID of the next trackable process.
						$process->__set('isTracked', in_array($artProcID, $trackedPids));

						// Is this process trackable?
						/*// DiSABLED on 2023-08-25 - replaced with next 2 lines.
						// A process is only trackable if the requested process ID (variable $pid in URI) belongs to a process that has not already been tracked (is in the list of untracked process IDs).
						$process->__set('isTrackable',  in_array($artProcID, $untrackedPids));*/
						// A process is only trackable if it is not yet tracked.
						$process->__set('isTrackable', !$process->__get('isTracked'));

						// Is this process approvable?
						// A process can be approved if there is at least one entry in its approval history.
						$process->__set('isApproved',  (is_array($artProc->get('approval')) && count($artProc->get('approval'))));

						/* Is this a bad part?
						 * If one of the part's processes is BAD, all subsequent processes must be blocked from tracking.
						 * A process can only be tracked if it has not yet been tracked and its ID is not identical to $nextPid.
						 */
						if ($itemHasBadProcess) :
							$process->__set('isTrackable', ($process->__get('isTrackable') && ($artProcID != $nextPid)));
						endif;

						// Get hands on the tracked data for this process if there is any.
						// Extract the tracking data specific to this process from the tracking data list.
						$trackedProcess = new Registry(ArrayHelper::getValue($trackings, $artProcID));

						// Add tracking data specific to this process to make data access more comfortable further below.
						$process->__set('tracking', new Registry([
							'organisation' => $trackedProcess->get(Techparams::STATIC_TECHPARAM_ORGANISATION),
							'operator'     => $trackedProcess->get(Techparams::STATIC_TECHPARAM_OPERATOR),
							'date'         => $trackedProcess->get(Techparams::STATIC_TECHPARAM_DATE),
							'time'         => $trackedProcess->get(Techparams::STATIC_TECHPARAM_TIME),
							'status'       => $trackedProcess->get(Techparams::STATIC_TECHPARAM_ERROR),
							// TODO - add tracked technical parameters
							'measuredData' => ArrayHelper::getValue($measurementDefinitions, $artProcID, [], 'ARRAY')
						]));

						/* Check whether a process is already tracked and editing should therefore be restricted or blocked completely.
						 * A process is assumed as "tracked" it it is already tracked +
						 * it was automatically tracked by a machine via the TrackingTool API or
						 * when the tracking data contains the following metadata:
						 *	- Operator name
						 *	- Tracking date
						 *	- Tracking time
						 *	- Tracking status + (error) status
						 */
						$process->__set('isTracked', (
							$process->__get('isTracked')
							&& ((
								!empty($process->__get('tracking')->get('operator')) &&
								!empty($process->__get('tracking')->get('date'))     &&
								!empty($process->__get('tracking')->get('time'))     &&
							is_numeric($process->__get('tracking')->get('status')))
								/* Prevent manual tracking of press-in process(es) (NOTE: identifiable via process abbreviation MNTx/SCFx)
								 * to prevent the tracking data from being messed like it happened in the past.
								 * For this purpose, the process ID is looked up in the list of press-in process IDs.
								 * If it is found in it, it is an autmatically tracked process and the process is classified as "tracked".
								 */
//								|| $process->__get('isTrackedByMachine')
							)
						));

						// Can the tracked data still be edited?
						// Calculation of the expiry date in relation to the creation date of the tracking if there is any.
						$dateNow             = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
						$dateTrackingCreated = $process->__get('isTracked')
													// If the process is already tracked, the creation date and time are the tracked date and time.
													? date_create(sprintf('%s %s',
														$process->__get('tracking')->get('date'),
														$process->__get('tracking')->get('time')
													))
													: null;	// FIXME - test whether the null can be replaced with $dateNow

						$dateTrackingExpires = is_a($dateTrackingCreated, 'DateTime')
													// If the process is already tracked, the initial value for the expiration date and time is the creation date and time.
													// This value is corrected in the next step in relation to a specific time window.
													? clone($dateTrackingCreated)
													: null;	// FIXME - test whether the null can be replaced with $dateTrackingCreated

						/* Add up the expiry time window defined in the Defines.php file.
						 * Only add up if the editing time window has not yet expired. An expired time window requires an approval.
						 * An editing time window created by an approval is valid only until the editing is saved or aborted.
						 * Afterwards it must be approved again.
						 */
						if (is_a($dateTrackingExpires, 'DateTime')) :
							$dateTrackingExpires->add(DateInterval::createFromDateString(FTKRULE_EDIT_TRACKING_EXPIRES));
						endif;

						// Add editability information of this process to make data access more comfortable further below.
						$process->__set('editability',  new Registry([
							'begin'     => (is_a($dateTrackingCreated, 'DateTime') ? $dateTrackingCreated->format('Y-m-d H:i:s') : null),
							'end'       => (is_a($dateTrackingExpires, 'DateTime') ? $dateTrackingExpires->format('Y-m-d H:i:s') : null),
							'now'       => (is_a($dateNow,             'DateTime') ? $dateNow->format('Y-m-d H:i:s')             : null),
							// Init remaining days/hours/minutes/seconds. This is going to be fixed in the next step.
							'remaining' => [
								'days'         => 0,
								'hours'        => 0,
								'minutes'      => 0,
								'seconds'      => 0,
								'microseconds' => 0,
							],
							'isEditable'   => 0,
							'isExpired'    => 0,
							'isApprovable' => 0,
						]));

						/* If there is remaining time for editing, we have to update the editing time window details.
						 * A value >  0 for 'invert' means "There is still some time left for editing.", where as
						 * a value <= 0 means "Editing time has elapsed."
						 *
						 * @see https://www.php.net/manual/de/datetime.diff.php        for how to compare dates
						 * @see https://www.php.net/manual/de/class.dateinterval.php   for the meaning of DateTime property 'invert'
						 */
						$diff = null;

						// Calculate difference.
						if (is_a($dateTrackingExpires, 'DateTime')) :
							$diff = $dateNow->diff($dateTrackingExpires);
						endif;

						// Update editability information of this process.
						// A date interval indicates that there's a previous tracking for this process.
						if (is_a($diff, 'DateInterval')) :
							// There is remaining editing time. Update initial values.
							if ($diff->invert == 0) :
								$process->__get('editability')->set('remaining.days',          $diff->d);
								$process->__get('editability')->set('remaining.hours',         $diff->h);
								$process->__get('editability')->set('remaining.minutes',       $diff->i);
								$process->__get('editability')->set('remaining.seconds',       $diff->s);
								$process->__get('editability')->set('remaining.microseconds',  $diff->f);

								// In principle, the editability is to be set to "true" here. However, the responsibility of the organisation must also be taken into account.
								$process->__get('editability')->set('isEditable', ($process->__get('isTracked') && $process->__get('isManagedByUserOrganisation')));
								// The editability has expired when editing is no longer possible.
								$process->__get('editability')->set('isExpired',  !$process->__get('editability')->get('isEditable'));
							// There is NO further editing time.
							else :
								$process->__get('editability')->set('isExpired',  true);
								$process->__get('editability')->set('isEditable', !$process->__get('editability')->get('isExpired') && $process->__get('isManagedByUserOrganisation') && $userCanEdit);
							endif;
						// No date interval indicates missing tracking creation date and/or tracking expiry date.
						else :
							$process->__get('editability')->set('isEditable', ($process->__get('isTracked') && $process->__get('isManagedByUserOrganisation')));
						endif;

						/*// If editability is expired, a higher privileged user like e.g. a quality manager can grant editing a tracking via an explicite approval.
						if ($process->__get('isTracked') && $process->__get('editability')->get('isExpired')) :
							// @debug
							if ($user->get('userID') == '1' || $user->get('userID') == '102') :
//								echo '<pre><em>' . print_r('The final chance for editability is the release by a quality manager.', true) . '</em></pre>';
							endif;
						else :
							// @debug
							if ($user->get('userID') == '1' || $user->get('userID') == '102') :
//								echo '<pre><em>' . print_r('The worker can track himself/herself.', true) . '</em></pre>';
							endif;
						endif;*/

						// Init more vars.
						$operator             = $trackedProcess->get(Techparams::STATIC_TECHPARAM_OPERATOR);
						$operatorOrg          = $trackedProcess->get(Techparams::STATIC_TECHPARAM_ORGANISATION);
						$operatorOrg          = $orgModel->getOrganisationByName('' . $operatorOrg);
						$operatorOrgID        = $operatorOrg->get($operatorOrg->getPrimaryKeyName());	// the organisation to which the original author (operator) belongs
						$operatorOrgName      = $operatorOrg->get('name');
//						$operatorOrgProcesses = (array) $operatorOrg->get('processes', []);				// the processes for which the original authors organisation is responsible

						$approverOrg          = &$userOrg;												// the organisation to which the approver (quality manager) belongs
						$approverOrgID        = $approverOrg->get($approverOrg->getPrimaryKeyName());
						$approverOrgName      = $approverOrg->get('name');
//						$approverOrgProcesses = (array) $operatorOrg->get('processes', []);				// the processes for which the approvers organisation is responsible

						$userIsInOperatorOrganisation = in_array($userOrgID, $process->get('organisations'));
						$userIsOriginalAuthor = false;	// is updated few lines below
						$userIsQualityManager = false;	// is updated few lines below

						// FIXME - drop these variables !!!
						// Determination of the current user's basic rights.
						$userCanTrack         = false;	// is updated few lines below
						$userCanEdit          = false;	// is updated few lines below
						$userCanApprove       = false;	// is updated few lines below

						/* A user is the original author of a tracking if:
						 *	- its full name matches the full operator name in the existing tracking data
						 */
						if ($process->__get('isTracked')) :
							$uFullName = mb_strtolower(trim($user->get('fullname', '')));
							$oFullName = mb_strtolower(trim($process->__get('tracking')->get('operator', '')));

							$userIsOriginalAuthor = ($uFullName !== '' && $oFullName !== '') && ($uFullName === $oFullName);

							// Free memory.
							unset($uFullName);
							unset($oFullName);
						endif;

						/* A user is a quality manager if:
						 *	- its email address is in the list of quality managers of the logged in user's organisation
						 */
						$userIsQualityManager = in_array($user->get('email'), array_keys($userOrgQualityResponsibles));

						/* A user can track a process, if:
						 *	- the process is managed by the user's organisation
						 *	- its acount is not blocked
						 *	- the process is open for tracking (NOTE: some processes are automatically tracked like press-in processes and therefore not manually trackable)
						 */
						$userCanTrack = !$user->get('blocked');
						$userCanTrack = ($userCanTrack &&  $process->__get('isManagedByUserOrganisation'));
						$userCanTrack = ($userCanTrack &&  $user->getFlags() >= User::ROLE_WORKER);

						/* A user can edit a previously tracked process, if:
						 *	- the process is managed by the user's organisation
						 *	- its acount is not blocked
						 *	- the user is the original author
						 *	- editing is approved by a quality manager, in case the editing time window is expired
						 */
						if ($process->__get('isTracked')) :
							//$userCanEdit = ($userCanTrack && $userIsOriginalAuthor && !$process->__get('editability')->get('expired'));	// DiSABLED on 2023-08-25 - replaced with next line
							$userCanEdit = ($userCanTrack && $userIsOriginalAuthor);
						endif;

						//-> BEGiN: different to edit_new
						if ($layout == 'item') :
							/* When the editing time window is closed, only authorised users
							 * with appropriate rights can approve another editing.
							 *
							 * A user is authorized when it has either of the roles
							 * Quality Assurance or Quality manager.
							 *
							 * Check 1:   the user must be a quality manager +
							 *            the process must be managed by the organisation of the currently logged in user +
							 *            the process must be already tracked
							 */
							$userCanApprove = ($user->isQualityManager() && $process->__get('isManagedByUserOrganisation') && $process->__get('isTracked'));

							/* A tracking must be editable by the original author within a defined time window.
							 * This time window is firmly defined in the file Defines.php.
							 *
							 * After this time window has expired, further editing can only be done by releasing
							 * an authorised employee. To do this, another user with process release authorisation,
							 * e.g. shift supervisor, production manager or other person responsible for quality,
							 * must release the process for further editing. However, a user with this authorisation
							 * must not be able to release a process for himself. If an authorised user is the original
							 * author, another user with the necessary authorisation must release the process in question to this user.
							 *
							 * It must be ensured that an approver belongs to the same organisation as the original author.
							 * The release authorisation alone is not sufficient, as an approver who does not belong
							 * to the same organisation would thereby be able to manipulate data from other organisations.
							 */
							if ($userCanApprove) :
								$userCanApprove = ($operatorOrgID === $approverOrgID);	// the original author is a quality manager in the organisation responsible for this process

								/*// The user is a quality manager, but belongs to a different organisation than the original author.
								// Therefore, he   h a s   n o   editing rights.
								if ($approverOrgID !== $operatorOrgID) :
//									$userCanEdit            = false;
//									$userCanApprove = false;
								// The user is a quality manager and belongs to the same organisation as the content creator.
								// Therefore, he   h a s   editing rights.
								else :
//									$userCanEdit    = false;
//									$userCanApprove = true;
								endif;*/
							endif;
						endif;

						// ADDED 2023-08-25 after heavy re-thinking and changing the accessibility control mechanism in this script.
						// Update accessibility flags.
						$process->__set('isTrackable',                       $process->__get('isTrackable')                    && $userCanTrack);
						$process->__get('editability')->set('isEditable',   ($process->__get('editability')->get('isEditable') && $userCanEdit));
						$process->__get('editability')->set('isApprovable', (
							   $process->__get('isTracked') &&
							  !$process->__get('editability')->get('isEditable') &&
							   $process->__get('isManagedByUserOrganisation') &&
							  ($userOrgID == $operatorOrgID))
						);

						//<-END: different to edit_new

						/* The full name of the tracking author must be visible to selected users only (HINT: data privacy).
						 * Only FRÖTEK and NEMATECH users are allowed to see such information + others if
						 * they belong to the organisation managing the process.
						 */
						$displayOperatorName = UserHelper::isFroetekOrNematechMember($user);
						$displayOperatorName = $displayOperatorName ?: $userIsInOperatorOrganisation;

						// Prepare process drawing thumbnail + PDF for display.
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
//										: 'https://via.placeholder.com/280x198/E9ECED/F00.png?text=' . Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang));
										: sprintf('https://%s/280x198/E9ECED/F00.png?text=%s', FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER, Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang)));

						// Extract error catalogue items to be selectable for this process.
						$errorList = array_slice($errors, 0, ($j + 1), true);

						// Calculate HTML attribute for blocked processes.
						$htmlStatusAttr = (!$process->__get('isTrackable')) ? ' disabled' : '';

						/*// @debug
//						echo '<pre>process: ' . print_r($process, true) . '</pre>';
						echo '<pre><strong>isManagedByUserOrganisation: </strong>' . print_r($process->__get('isManagedByUserOrganisation')      ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>is userOrganisation == operatorOrganisation: </strong>' . print_r(($userOrgID == $operatorOrgID)      ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>isTrackedByMachine: </strong>'          . print_r($process->__get('isTrackedByMachine')               ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>isPressinProcess: </strong>'            . print_r($process->__get('isPressinProcess')                 ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>isTracked: </strong>'                   . print_r($process->__get('isTracked')                        ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>isTrackable: </strong>'                 . print_r($process->__get('isTrackable')                      ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>isEditable: </strong>'                  . print_r($process->__get('editability')->get('isEditable')   ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre><strong>isApprovable: </strong>'                . print_r($process->__get('editability')->get('isApprovable') ? 'YES' : 'NO', true) . '</pre>';
//						die;*/
					?>
					<div class="<?php echo trim(sprintf('list-item dynamic-content event process position-relative %s', ($process->__get('isTracked') ? 'tracked' : 'untracked'))); ?>"
						 id="p-<?php echo hash('MD5', $artProcID); ?>" data-proc-id="<?php echo (int) $artProcID; ?>"
					>
<?php //-> BEGiN: Process masterdata + technical parameters ?>
						<fieldset class="<?php echo trim(sprintf('part-process-tracking position-relative %s', ($this->isApproval ? ($artProcID == $pid ? '' : 'disabled') : ''))); ?>"
								  id="process-<?php echo (int) $artProcID; ?>"
								  style="min-height:250px"
						>
							<legend class="sr-only"><?php echo sprintf('Master data, drawing and technical parameters of process <em>%s</em>', $process->get('name')); ?></legend>

							<?php // Process name + Current process drawing number ?>
							<h5 class="h6 text-uppercase d-inline-block pt-lg-1 mr-lg-3 mb-3" style="margin-top:1px"><?php echo html_entity_decode($process->get('name')); ?></h5>
							<small class="d-none d-md-inline mr-3 text-muted" style="vertical-align:text-top">( <?php echo html_entity_decode($processNo); ?> )</small>

<?php //-> BEGiN: Process toolbar ?>
							<?php // A process' accompanying files must be accessible at any time - no matter of the part's status. ?>
							<?php // BUTTON: media-box ?>
							<?php if ($process->__get('isManagedByUserOrganisation')) : ?><?php
									// Fetch + Prepare HTML mark-up. This is required by the toggle.
									$modalForm = [ LayoutHelper::render('widget.controls.mediabox',
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
							<?php // END: BUTTON: media-box ?>

							<?php // BUTTON: Open/Edit Tracking ?>

							<?php //if (!$this->isBlocked) : //-> BEGiN: Interaction is only possible if the part is not locked ?>
									<?php //-> BEGiN: Button ?>
									<?php /*
									Show button if ...
										- process is managed by the user's organisation
										- process is not tracked
										- process is tracked + process is editable + user is author
										- process is tracked + process is editable + user is not author --> render approval widget

									*/
									?>
									<?php if ($process->__get('editability')->get('isApprovable')) : ?>
										<!--span class="text-danger">render approval-widget</span-->
										<div class="btn-group float-right">
											<?php // Dropdown menu toggle ?>
											<button type="button"
													class="btn btn-sm btn-info dropdown-toggle"
													id="btn-dropdownMenu-toggle-1"
													data-toggle="dropdown"
													data-display="static"
													aria-haspopup="true"
													aria-expanded="false"
											>
												<span title="<?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_BUTTON_TITLE_EDITING_%s_TEXT', ($userIsOriginalAuthor ? 'EXPIRED' : 'LOCKED'))), $lang); ?>"
													  data-toggle="tooltip"
												>
													<i class="fas fa-lock text-danger"></i>
													<span class="btn-text d-none d-md-inline ml-lg-2"><?php
														echo Text::translate(mb_strtoupper(sprintf('COM_FTK_BUTTON_TEXT_EDITING_%s_TEXT', ($userIsOriginalAuthor ? 'EXPIRED' : 'LOCKED'))), $lang);
													?></span>
												</span>
											</button>

											<?php // Dropdown menu item(s) ?>
											<div class="dropdown-menu dropdown-menu-left dropdown-menu-lg-right py-1" aria-labelledby="btn-dropdownMenu-toggle-1">
												<?php // Button: Process approval if the user's organisation manages this process ?>
												<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d&pid=%d&task=approve',
														$lang,
														$layout,
														$this->item->get('partID'),
														$artProcID,
												   ))); ?>"
												   class="dropdown-item px-3"
												   data-bind="windowOpen"
												   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d&pid=%d&task=approve',
														$lang,
														$layout,
														$this->item->get('partID'),
														$artProcID,
												   ))); ?>#p-<?php echo hash('MD5', $artProcID); ?>"
												   data-location-target="_self"
												>
													<span class="d-block"
														  title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_APPROVAL_BY_A_QUALITY_OFFICER_TEXT', $lang); ?>"
														  data-toggle="tooltip"
													>
														<span class="btn-text d-block text-right"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_APPROVE_TEXT', $lang); // COM_FTK_BUTTON_TITLE_QA_APPROVAL_TEXT ?></span>
													</span>
												</a>
											</div>
										</div>
									<?php elseif ($process->__get('editability')->get('isEditable') || ($process->__get('isTrackable')/* && all-preceding-processes-are-tracked */)) : // FIXME ?><?php
											// Calculate expiration time window.
											$daysLeft    = $process->__get('editability')->get('remaining.days',    0);
											$hoursLeft   = $process->__get('editability')->get('remaining.hours',   0);
											$minutesLeft = $process->__get('editability')->get('remaining.minutes', 0);
											$secondsLeft = $process->__get('editability')->get('remaining.seconds', 0);

											$hoursLeftTotal   = ($daysLeft         / 24) + $hoursLeft;
											$minutesLeftTotal = ($hoursLeft        * 60) + $minutesLeft;
											$secondsLeftTotal = ($minutesLeftTotal * 60) + $secondsLeft;
											$timeLeft    = ($minutesLeftTotal < 1
												? $secondsLeft
												: $minutesLeftTotal);

											$unit = ($minutesLeftTotal < 1
												? ($secondsLeft == 1 ? 'COM_FTK_TIME_UNIT_SECOND' : 'COM_FTK_TIME_UNIT_SECONDS')
												: ($minutesLeftTotal == 1 ? 'COM_FTK_TIME_UNIT_MINUTE' : 'COM_FTK_TIME_UNIT_MINUTES'));
										?>
										<!--span class="text-secondary">render edit-tracking-button</span-->
										<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d&pid=%d%s',
												$lang,
												$this->item->get('partID'),
												$artProcID,
												($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '')
										   ))); ?>#p-<?php echo hash('CRC32', $artProcID); ?>"
										   role="button"
										   class="btn btn-sm btn-info float-right"
										   <?php if ($process->__get('editability')->get('isEditable')) : ?>
										   title="<?php echo sprintf(Text::translate('COM_FTK_HINT_EDITABILITY_EXPIRATION_TIME_TEXT', $lang), $timeLeft, Text::translate($unit, $lang)); ?>"
										   data-title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_EDIT_THIS_TEXT', $lang); ?>"
										   <?php endif; ?>
										   data-toggle="tooltip"
										   style="vertical-align:baseline"
										>
											<i class="fas fa-pencil-alt"></i>
											<span class="d-none d-md-inline ml-lg-1"><?php
												echo !$process->__get('isTracked')
													? Text::translate('COM_FTK_LABEL_PART_PROCESS_TRACK_TEXT', $lang)
													: Text::translate('COM_FTK_LABEL_EDIT_TEXT', $lang);
											?></span>
											<?php // Render timer displaying the remaining editing time. ?>
											<?php if ($process->__get('isTracked')) : ?>
											<i class="fas fa-history ml-2 fa-lg text-gold">
												<small class="d-inline-block align-middle ml-1 <?php echo ($hoursLeft == 0 && $minutesLeft == 0) ? 'text-bold text-gold' : 'text-white'; ?>"
													   data-bind="countdown"
													   data-count-days="<?php    echo $daysLeft;    ?>"
													   data-count-hours="<?php   echo $hoursLeft;   ?>"
													   data-count-minutes="<?php echo $minutesLeft; ?>"
													   data-count-seconds="<?php echo $secondsLeft; ?>"
													   style="font-family:Consolas"
												><?php echo sprintf('%s:%s:%s',
													($hoursLeft   < 10 ? '0' . $hoursLeft   : $hoursLeft),
													($minutesLeft < 10 ? '0' . $minutesLeft : $minutesLeft),
													($secondsLeft < 10 ? '0' . $secondsLeft : $secondsLeft));
												?></small>
											</i>
											<?php endif; ?>
										</a>
									<?php elseif (FALSE && $process->__get('isTrackable')) : ?>
										<!--span class="text-primary">render track-now-button</span-->
									<?php endif; ?>
									<?php //<- END: Button ?>

									<?php //-> BEGiN: Hint(s)
									      // They are placed here, because the button is absolutely positioned and brakes the layout when put afterwards. ?>
									<?php // A completely different organisation manages this process. Editing not possible for the user currently logged in. ?>
									<?php if (! $process->__get('isManagedByUserOrganisation')) : ?>
										<?php // HINT: Inform user that its organisation is not managing this process. ?>
										<?php echo LayoutHelper::render('system.alert.notice', [
											'message' => Text::translate('COM_FTK_HINT_PROCESS_IS_NOT_MANAGED_BY_YOUR_ORGANISATION_TEXT', $lang),
											'attribs' => [
												'class' => 'alert-sm'
											]
										]); ?>
									<?php endif; ?>

									<?php /* The process is jointly managed by the organisation of the original author
										   * and the organisation to which the currently logged-in user belongs, but
										   * the tracking can only be edited by the original author's organisation.
										   */ ?>
									<?php if (  $process->__get('isManagedByUserOrganisation') && $process->__get('isTracked') && ($userOrgID != $operatorOrgID)) : ?>
										<?php // HINT: Inform user that the process was tracked in a different organisation. ?>
										<?php echo LayoutHelper::render('system.alert.secondary', [
											'message' => Text::translate('COM_FTK_HINT_PROCESS_WAS_TRACKED_IN_A_DIFFERENT_ORGANISATION_TEXT', $lang),
											'attribs' => [
												'class' => 'alert-sm'
											]
										]); ?>
									<?php endif; ?>

									<?php // A process can not be tracked until all preceding processes are tracked. ?>
									<?php if (FALSE &&  $process->__get('isManagedByUserOrganisation') && ! $process->__get('isTrackable')) : ?>
										<?php // HINT: Inform user that its organisation is not authorised to track this process. ?>
										<?php echo LayoutHelper::render('system.alert.secondary', [
											'message' => Text::translate('COM_FTK_HINT_PROCESS_TRACKING_ONLY_AFTER_PREV_PROCESSES_TRACKED_TEXT', $lang),
											'attribs' => [
												'class' => 'alert-sm'
											]
										]); ?>
									<?php endif; ?>

									<?php // Process is auto-tracked by a machine via API. ?>
									<?php if (  $process->__get('isTrackedByMachine')) : ?>
										<?php // HINT: Inform user regarding machine-tracking. ?>
										<?php echo LayoutHelper::render('system.alert.info', [
											'message' => sprintf('%s %s',
												Text::translate('COM_FTK_HINT_MANUAL_TRACKING_IS_DISABLED_TEXT', $lang),
												Text::translate('COM_FTK_HINT_PROCESS_IS_AUTO_TRACKED_BY_THE_MACHINE_TEXT', $lang)
											),
											'attribs' => [
												'class' => 'alert-sm'
											]
										]); ?>
									<?php endif; ?>
									<?php //<- END: Button ?>

							<?php //endif; //<- END: Interaction is only possible if the part is not locked ?>

							<?php // END: BUTTON: Open/Edit Tracking ?>
<?php //<- END: Process toolbar ?>


<?php //-> BEGiN: Process details ?>
							<div class="row">
<?php //-> BEGiN: Process metadata and technical parameters ?>
								<div class="col col-6 col-sm-8">
								<?php //-> BEGiN: Technical parameters input fields ?>
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

								<?php 	// Display static technical parameters.
										// NOTE:  We do not output all static params at this stage due to the layout. ?>
								<?php 	$i = 1; $cnt = count($staticTechParams); ?>
								<?php	for (; $i <= Techparams::STATIC_TECHPARAM_DRAWING; $i += 1) : ?><?php
											if ($i == Techparams::STATIC_TECHPARAM_ERROR) :
												break;
											endif;

											$param = new Registry($techParams[$i]);
											$paramIsDrawingNumber = ($i == Techparams::STATIC_TECHPARAM_DRAWING);

											// Init default value.
											$output = '';
								?>
									<?php //-> Begin: The tracking parameter line ?>
									<div class="row form-group mb-0 mb-lg-1" data-param-id="<?php echo html_entity_decode($i); ?>">
										<?php //-> Begin: The parameter label ?>
										<label for="procParams"
											   class="col-form-label-sm col col-6 col-md-5 col-lg-4 mb-0 mb-md-1"
										><?php echo html_entity_decode($param->get('name')); ?>:
											<?php if ($paramIsDrawingNumber) : ?>
											<i class="fas fa-info-circle text-muted ml-1"
											   title="<?php echo Text::translate('COM_FTK_HINT_ALL_ACTIVE_DRAWING_NUMBER_WHEN_TRACKING', $lang); ?>"
											   data-title="<?php echo Text::translate('COM_FTK_HINT_ALL_ACTIVE_DRAWING_NUMBER_WHEN_TRACKING', $lang); ?>"
											   data-toggle="tooltip"
											></i>
											<?php endif; ?>
										</label>
										<?php //<- End: The parameter label ?>

										<?php //-> Begin: The parameter value (static system data or user input) ?>
										<div class="input-group col">
											<?php // Prepare output of static parameter value (defined at the process) like date, time, worker name, etc. ?>
											<?php if (ArrayHelper::getValue($staticTechParams, $i)) : ?><?php
												// If parameter is the operator name and the currently logged in user has no right to see it, mask parameter value.
												if ($i == Techparams::STATIC_TECHPARAM_OPERATOR && !$displayOperatorName) :
													$output = str_repeat('*', 10);
												// If parameter is the drawing number, calculate which layout to use for rendering and pass rendering to the layout helper.
												elseif ($paramIsDrawingNumber) : ?><?php
														$currentDrawingNumber = html_entity_decode($processNo);
														$trackedDrawingNumber = html_entity_decode($trackedProcess->get($i));
														$drawingNumber = $trackedDrawingNumber;
														$numberLayout  = 'default';
														$number        = '';

														// Drawing number has changed. Highlight tracked drawing number.
														if ($trackedDrawingNumber != $currentDrawingNumber) :
															$numberLayout = 'highlighting';
														endif;

														// Dump rendered HTML markup.
														$number = LayoutHelper::render('system.element.drawingnumber',
															[
																'layout'       => $numberLayout,	// can be one of 'plain', 'highlighting' and will use 'default' if not passed
																'number'       => $drawingNumber,
																'drawing'      => $drawing->toArray(),
																'tooltip'      => Text::translate('COM_FTK_LINK_TITLE_VIEW_TRACKED_DRAWING_TEXT',     $lang),
																'icon-tooltip' => Text::translate('COM_FTK_HINT_PROCESS_TRACKED_WITH_DRAWING_NUMBER', $lang)
															],
															['language' => $lang]
														);

														$output = $number ?: '&ndash;';
												else :
													$output = (is_a($trackedProcess, 'Joomla\Registry\Registry') ? html_entity_decode($trackedProcess->get($i, '&ndash;')) : '&ndash;');
												endif;
											endif; ?>

											<?php // Output static parameter value. ?>
											<span class="form-control form-control-sm form-control-plaintext"><?php echo $output; ?></span>
										</div>
									</div>
									<?php //<- End: The tracking parameter line ?>
								<?php	endfor; ?>

								<?php endif; ?>
								<?php //-> END: Technical parameters input fields ?>
								</div>
<?php //<- END: Process metadata and technical parameters ?>


<?php //-> BEGiN: Article drawing ?>
								<div class="col col-6 col-sm-4">
									<div class="Xposition-absolute process-drawing mt-0" id="drawing-<?php echo (int) $artProcID; ?>">
										<figure class="figure bg-white m-md-auto float-right">
										<?php // TODO - integrate rendering of article image or placeholder image like is done in edit.php ... pass required data to the layout for output preparation ?>
										<?php if (!is_null($filePDF)) : ?>
											<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( $filePDF )); ?>"
											   class="chocolat-image d-block"
											   target="_blank"
											>
												<img src="<?php echo $image; ?>"
													 class="figure-img img-fluid"
													 alt=""
													 width=""
													 height=""
													 data-toggle="tooltip"
													 data-offest="20"
													 title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CURRENT_DRAWING_TEXT', $lang); ?>"
													 Xstyle="box-shadow:0 0 1px 1px #ced4da"
												/>
												<?php if (FALSE) : ?><figcaption class="figure-caption h5 pt-5 sr-only"></figcaption><?php endif; ?>
											</a>
										<?php else : ?>
											<?php // render drawing placeholder image ?>
											<?php // echo LayoutHelper::render('image.placeholder.article', new stdclass, ['language' => $lang]); ?>
											<div class="chocolat-image d-block">
												<img src="<?php echo $image; ?>"
													 width="" height=""
													 alt=""
													 Xstyle="box-shadow:0 0 1px 1px #ced4da"
												/>
												<?php if (FALSE) : ?><figcaption class="figure-caption h5 pt-5 sr-only"></figcaption><?php endif; ?>
											</div>
										<?php endif; ?>
										</figure>
									</div>
								</div>
<?php //<- END: Article drawing ?>

<?php //-> BEGiN: Technical parameters ?>
								<div class="col col-12 mt-2">
								<?php 	// Display static technical parameters.
										// NOTE:  We did not output all static params above due to the layout. ?>
								<?php	for (; $i <= $cnt; $i += 1) : ?><?php
											$param = new Registry($techParams[$i]);

											// Prepare output of static parameter value (defined at the process) like date, time, worker name, etc.
											$output = (is_a($trackedProcess, 'Joomla\Registry\Registry') ? html_entity_decode($trackedProcess->get($i, '')) : '');

											// If param is the tracking status/error (one of the process' error catalogue items)
											// calculate error colour and fetch error text.
											if ($i == Techparams::STATIC_TECHPARAM_ERROR) :
												/* If <var>$errNum</var> is already set, stick with it.
												 * Otherwise, get the tracking status number (error number) from the tracked data.
												 */
												$errNum   = ( (!is_null($errNum) && (int) $errNum > 0)
													? $errNum
													: (new Registry(ArrayHelper::getValue($trackings, $artProcID, [])))->get($i) );

												// Calculate the error colour.
												$errColor = ( (!empty($errNum) && !empty($errText))
													? 'bg-danger text-white'
													: ( !is_null($errNum)
														? ( $errNum == '0' ? 'bg-success' : 'bg-warning' )
														: null ) );

												// ADDED on 2023-11-22 - A flag that indicates whether an error text could not be found using the error code and the error code shall be displayed instead.
												// In this case the form field shall render a notification symbol that provides further information to the user why that is.
												$isFallbackErrorText = false;

												// Take the error code and find the corresponding error text if not available yet here.
												if (empty($errText) && !empty($errNum)) :
													array_walk($errorList, function($list) use(&$errNum, &$errText)
													{
														foreach ($list as $errID => $text) :
															if (intval($errID) == intval($errNum)) :	/* CHANGED on 2023-11-06 - while merging the old and new error catalogs the old tracked error numbers are
																										 *							a combination of number (old) and string (new) like "1 CUT05".
																										 *							To not break old error resolving and rendering we force the number portion to be the value of $errNum.
																										 *							Once the new error catalog will be active, the already modified tracked errors must be modified a last time: drop the leading number. */
																$errText = $text;

																return;
															endif;
														endforeach;

														return true;
													});

													// ADDED on 2023-11-22 - Interim solution until the new error catalogue is fully implemented:  If no error text was found, display the error code and/or the error number
													if (empty($errText)) :
														// Update flag var.
														$isFallbackErrorText = true;

														// No proper error text found, fall back to the error code to prevent the UI from lacking required information.
														$errText = $errNum;
													endif;
												endif;

												// Dump error text in output value.
												$output = ( $errNum == '0'
														? Text::translate('COM_FTK_LIST_OPTION_PASSED_LABEL', $lang)
														: html_entity_decode('' . $errText) );
											endif;
								?>
									<?php //-> Begin: The tracking parameter line ?>
									<div class="row form-group mb-0 mb-lg-1" data-param-id="<?php echo html_entity_decode($i); ?>">
										<?php //-> Begin: The parameter label ?>
										<label for="procParams"
											   class="col-form-label col col-6 col-md-5 col-lg-3 mb-0 mb-md-1"
											   style="max-width:22.25%"<?php // FIXME - this is relative to TWBS break point lg , check for md, sm, xs, ... ?>
										><?php echo html_entity_decode($param->get('name')); ?>:
										</label>
										<?php //<- End: The parameter label ?>

										<?php //-> Begin: The parameter value (static system data or user input) ?>
										<div class="input-group col">
											<?php // Output static parameter value. ?>
											<span class="form-control h-auto mb-1<?php echo ($i == Techparams::STATIC_TECHPARAM_ERROR ? ' ' . $errColor : ''); ?>"
												  readonly
											><?php echo $output; ?></span>
										</div>
									</div>
									<?php //<- End: The tracking parameter line ?>
								<?php	endfor; ?>

								<?php // Update data object (skip static params). ?>
								<?php $techParams = array_slice($techParams, $i, null, true); ?>

								<?php 	// Display dynamic technical parameters.
										// NOTE: We can no longer use $i as we don't know the ID of a dynamic techparam.
										//       Therefore, we switch to foreach-loop. ?>
								<?php 	foreach ($techParams as $i => $param) : ?><?php
											$param = new Registry($param);

											/* The parameter name is provided by the $param object in the $techParams list.
											 * The parameter value, however, is provided by the tracked data of this process.
											 * Therefore, we must read the value from that object.
											 */
											$output = (is_a($trackedProcess, 'Joomla\Registry\Registry')) ? html_entity_decode($trackedProcess->get($i, '')) : '';
											$output = !empty($output)
												? $view->convertCodesToHyperlinks($output)	// It is assumed that any tracked information may contain a tracking code.
																							// Tracking codes must be converted to hyperlinks pointing to the related part.
												: $output;
								?>
									<?php //-> Begin: The tracking parameter line ?>
									<div class="row form-group mb-0 mb-lg-1" data-param-id="<?php echo html_entity_decode($i); ?>">
										<?php //-> Begin: The parameter label ?>
										<label for="procParams"
											   class="col-form-label col col-6 col-md-5 col-lg-3 mb-0 mb-md-1"
											   style="max-width:22.25%"<?php // FIXME - this is relative to TWBS break point lg , check for md, sm, xs, ... ?>
										><?php echo html_entity_decode($param->get('name')); ?>:
										</label>
										<?php //<- End: The parameter label ?>

										<?php //-> Begin: The parameter value (static system data or user input) ?>
										<div class="input-group col">
											<?php // Output static parameter value. ?>
											<span class="form-control h-auto mb-1"
												  readonly
											><?php echo $output; ?></span>
										</div>
									</div>
									<?php //<- End: The tracking parameter line ?>
								<?php 	endforeach; ?>
								</div>
<?php //<- END: Technical parameters ?>
							</div>
<?php //-> END: Process details ?>
						</fieldset>
<?php //-> END: Process masterdata + technical parameters ?>


						<?php //-> BEGiN: Process tree ?>
						<?php if ($process->__get('isTrackable')) : // Means that this process is visible and accessible and not covered by a blocking overlay ?>

						<?php // Prepare measuring definitions table.
						// Init vars.
						$measurementTrackingWidgetRenderOptions = json_encode([]);
						$measurementTrackingInfo = $measurementTrackingWidget = '';
						?>

						<?php // Process has measuring definitions + measured data. Render table. ?>
						<?php if (count($processMeasuredData = ArrayHelper::getValue($measuredData, $artProcID, [], 'ARRAY'))) : ?>
						<?php 	$measurementTrackingWidget = LayoutHelper::render('forms.article.process_measurement', [
									'isTracking' => true,	// flags this request as "tracking" mode (part process is tracked), which makes only limited fields editable rather than in "compositing" mode (article process definitions))
									'isReadonly' => true,	// flags this request as "read" mode (part is viewed)
									'item'       => $this->item,
									'pid'        => $artProcID,
									'hide'       => ['mpDatatype','mpToolbar'],
								], ['language'   => $lang]);
							?>
						<?php // Process has measuring definitions but NO measured data. Display hint. ?>
						<?php elseif (count($process->__get('tracking')->get('measuredData', []))) : ?>
						<?php 	$measurementTrackingWidget = null;
								$measurementTrackingInfo = LayoutHelper::render(sprintf('system.alert.%s', ($process->__get('tracking')->get('operator')) ? 'danger' : 'secondary'), [
									'message' => Text::translate(($process->__get('tracking')->get('operator'))
										? 'COM_FTK_HINT_PROCESS_MEASUREMENT_NOT_CARRIED_OUT_TEXT'   // displayed when not editing the tracking
										: 'COM_FTK_HINT_PROCESS_HAS_NO_TRACKING_DATA_TEXT', $lang   // displayed when editing the tracking or when viewing a process with no defs.
									),
									'attribs' => [
										'class' => 'alert-sm tracking-hint my-0'
									]
								]);
						?>
						<?php // Process has neither measuring definitions nor measured data. Display info. ?>
						<?php else : ?>
						<?php 	$measurementTrackingWidget = null;
								$measurementTrackingInfo   = LayoutHelper::render(sprintf('system.alert.%s', ($process->__get('tracking')->get('operator')) ? 'info' : 'secondary'), [
									'message' => Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_MEASUREMENT_DEFINITIONS_TEXT', $lang),
									'attribs' => [
										'class' => 'alert-sm tracking-hint my-0'
									]
								]);
						?>
						<?php endif; ?>

						<?php if (!empty($measurementTrackingWidget) && $measurementTrackingWidget !== '') : ?>
						<?php	$measurementTrackingWidgetRenderOptions = json_encode([
									'element'    => 'div',
									'html'       => base64_encode($measurementTrackingWidget),
									'attributes' => [
										'class'       => 'collapse collapse-sm',
										'id'          => 'card-' . (int) $artProcID,
										'data-parent' => '#p-' . hash('MD5', $artProcID)
									]
								], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
						?>
						<?php endif; ?>

						<?php // Render measuring data table ?>
						<fieldset class="part-process-tracking part-process-measuring-tracking pt-0">
							<div class="row form-group mb-0">
								<label class="col-form-label col col-4 col-md-3 mb-0"
								       style="max-width:21%"<?php // FIXME - this is relative to TWBS break point lg , check for md, sm, xs, ... ?>
								><?php echo Text::translate('COM_FTK_LABEL_MEASURMENT_DATA_TEXT', $lang); ?>:</label>
								<div class="col">
								<?php if (empty($measurementTrackingWidget) || $measurementTrackingWidget === '') : ?>
									<?php echo $measurementTrackingInfo; ?>
								<?php else : ?>
									<button type="button"
											class="btn btn-sm btn-secondary tracking-hint px-3"
											id="btn-card-toggle-1"
											data-bind="switchButtonText"
											data-text-initial="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_SHOW_TEXT', $lang); ?>"
											data-text-replacement="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_HIDE_TEXT', $lang); ?>"
											data-toggle="collapse"
											data-target="#card-<?php echo (int) $artProcID; ?>"
									>
										<span>
											<i class="fas fa-caret-down"></i>
											<span class="btn-text ml-2 align-top d-none d-md-inline"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SHOW_TEXT', $lang); ?></span>
										</span>
									</button>
								<?php endif; ?>
								</div>
							</div>

							<?php if (!is_null($measurementTrackingWidget) && $measurementTrackingWidget !== '') : ?>
							<span id="dynamic-table-<?php echo $artProcID; ?>"
								  data-toggle="replaceElement"
								  data-target="#dynamic-table-<?php echo $artProcID; ?>"
								  data-replace="true"
								  data-replacement-options='<?php echo preg_replace('/%ID%/', $artProcID, $measurementTrackingWidgetRenderOptions); ?>'
							></span>
							<?php endif; ?>
						</fieldset>

						<?php endif; //->END process isTrackable ?>
						<?php //-> END: Process tree ?>

						<?php // QA approval widget ?>
						<?php if ($this->isApproval /* && qualityManagerBelongsToUserOrganisation */ && $artProcID == $pid) : $dataFormat = 'jsonp'; ?>
						<div class="position-absolute h-100 w-100 widget-user-auth">
							<?php // FIXME - this CSS may be dupe to the login widget in forms/user/login ?>
							<style>
							.widget-user-auth {
								z-index: 1;
								top:  0;
								left: 0;
								width:  calc(100% + 10px) !important;
								height: calc(100% - 15px) !important;
								margin-left: -5px;
								background: rgba(255, 192, 203, 0.5);
								background: #F5F5F5;
							}
							.widget-user-auth > .form-container {
								margin: 0 auto;
							}
							.widget-user-auth > .form-container button.close {
								top:   0;
								right: 0;
							}

							.form-authenticate {
								width: 100%;
								max-width: 330px;
								padding: 15px;
								margin: auto;
								background-color: #F2F2F2; <?php // 1% darker than body bg-color ?>
								box-shadow: 0 0 20px 1px rgba(204, 204, 204, 0.5);
							}
							.form-authenticate .form-control {
								position: relative;
								box-sizing: border-box;
								height: auto;
								font-size: 16px;
							}
							.form-authenticate .form-control:focus {
								z-index: 2;
							}
							.form-authenticate [name="email"] {
								border-bottom: 0;
								border-bottom-right-radius: 0;
								border-bottom-left-radius: 0;
							}
							.form-authenticate input[type="password"]:not(.validation-result) {
								margin-bottom: 10px;
								border-top-left-radius: 0;
								border-top-right-radius: 0;
							}
							<?php /* Override bottom margin of inline error message */ ?>
							.form-control.text-danger + .text-danger {
								margin-bottom: 1rem;
							}

							<?php /* Override global config in index.php */ ?>
							.form-authenticate .custom-select.filterable+.chosen-container-single>.chosen-single {
								border-bottom: 0;
								border-bottom-right-radius: 0 !important;
								border-bottom-left-radius:  0 !important;
							}
							</style>

							<?php // Fields: e-mail + password of the approver ?>
							<div class="w-50 form-container text-center">
								<form action="index.php?hl=<?php echo $lang; ?>"
									  method="post"
									  name="userAuthForm"
									  class="form form-horizontal form-authenticate userForm validate position-relative rounded px-4 py-3 py-lg-4 py-xl-5"
									  id="userAuthForm"
									  data-action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&service=api&task=process.approve&format=%s', $lang, $dataFormat ))); ?>"
									  data-trigger="submit"
									  data-submit="ajax"
									  data-format="<?php echo $dataFormat; ?>"
								>
									<input type="hidden" name="ptid" value="<?php echo $item->get('partID'); ?>" />
									<input type="hidden" name="pid"  value="<?php echo $artProcID; ?>" />
									<input type="hidden" name="uid"  value="<?php echo $user->get('userID'); ?>" />
									<?php if (FALSE) : ?>
									<?php /* TODO - decide on the integration of a unique form token that is stored
										   * with the auth information in order to be able to identify a specific
										   * login process retrospectively at any time.
										   */
									?>
									<input type="hidden" name="token"  value="<?php echo base64_encode(sprintf('%s:%s', date_create('NOW')->format('c'), $input->server->get('REMOTE_ADDR', '0', 'STRING'))); ?>" />
									<?php endif; ?>

									<?php // BUTTON: Close widget and redirect to referrer URI ?>
									<button type="button"
											class="close position-absolute mt-1 mt-lg-2 mt-xl-3 mr-3"
											data-dismiss="modal"
											title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CLOSE_TEXT', $lang ); ?>"
											aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CLOSE_TEXT', $lang ); ?>"
											onclick="window.location.replace('<?php echo UriHelper::osSafe( UriHelper::fixURL( $view->getReferer())); ?>');"
									><span aria-hidden="true">&times;</span></button>

									<?php // Fields: e-mail + password of the approver ?>
									<fieldset>
										<legend class="h5 font-weight-normal mb-2 mb-lg-3"><?php echo Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_AUTHENTICATE_TEXT', $lang); ?></legend>

										<label for="email"
											   class="sr-only<?php // the label is not displaying. hence we hide it using a TWBS-class ?>"
										><?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_TEXT', $lang); ?></label>
										<?php echo LayoutHelper::render('widget.controls.select',
											[
												'context' => 'part',	// can be one of 'article', 'part', 'tracking'
												'options' => array_column($userOrgQualityResponsibles, 'fullname', 'email'),
												'attribs' => [
													'name'        => 'email',
													'class'       => 'custom-select filterable selectQualityManagers',
													'id'          => 'ipt-qualityManager',
													'required'    => true,
													'data-attribs' => [
														'placeholder' =>  Text::translate('COM_FTK_INPUT_PLACEHOLDER_SELECT_APPROVER_TEXT', $lang),
														// jQuery chosen plugin configuration parameters
														'chosen' => [
															'allow-single-deselect'           => true,
															'case-sensitive-search'           => true,
															'disable-search'                  => false,
															'disable-search-threshold'        => 2,
															'display-disabled-options'        => false,
															'display-selected-options'        => true,
															'enable-split-word-search'        => false,
															'group-search'                    => false,
															'hide-results-on-select'          => true,
															'include-group-label-in-selected' => true,
															'inherit-select-classes'          => false,
															'max-selected-options'            => 1,
//															'max-shown-results'               => 1,
															'no-results-text'                 => Text::translate('COM_FTK_LIST_OPTION_NOT_FOUND_TEXT', $lang),
															'placeholder-text-multiple'       => Text::translate('COM_FTK_HINT_MAKE_AT_LEAST_SELECTION_N_1_TEXT', $lang),
															'placeholder-text-single'         => Text::translate('COM_FTK_LIST_OPTION_PLEASE_SELECT_TEXT', $lang),
															'placeholder_text'                => 'Foobar',
															'rtl'                             => false,
															'search-contains'                 => true,
															'single-backstroke-delete'        => true,
														],
													],
												],
												'debug' => false
											],
											['language' => $this->language]
										); ?>

										<label for="inputPassword"
											   class="sr-only<?php // the label is not displaying. hence we hide it using a TWBS-class ?>"
										><?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $lang); ?></label>
										<input type="password"
											   name="password"
											   class="form-control"
											   id="inputPassword"
											   minlength="10"
											   placeholder="<?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $lang); ?>"
											   required
											   autocapitalize="off"
											   autocomplete="off"
											   autocorrect="off"
											   spellcheck="false"
											   data-rule-required="true"
											   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $lang); ?>"
										/>
									</fieldset>

									<button type="submit" class="btn btn-block btn-primary btn-submit btn-ftk-shadow btn-ftk-blue">
										<span class="btn-text mr-2"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SUBMIT_TEXT', $lang); ?></span>
										<i class="fas fa-paper-plane"></i>
									</button>
								</form>
							</div>
						</div>
						<?php endif; ?>
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
unset($itemLot);
unset($itemProcesses);
unset($itemProcessIDs);
unset($measuredData);
unset($measurementDefinitions);
unset($mntORscfProcsel);
unset($model);
unset($process);
unset($processes);
unset($staticTechParams);
unset($trackings);
unset($user);
unset($userOrgProcesses);
unset($view);
?>

<?php if ($isPrint) : ?>
<script>window.print();</script>
<?php endif; ?>
