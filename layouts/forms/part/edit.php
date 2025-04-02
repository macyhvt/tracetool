<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Access\User;
use  \Helper\FilesystemHelper;
use  \Helper\LayoutHelper;
use  \Helper\MediaHelper;
use  \Helper\UriHelper;
use  \Messager;
use  \Model\Techparams;
use  \Text;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use voku\helper\HtmlMin;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* conditional include */

?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$debug  = $input->getCmd('auth') === 'dev-op';

$return = $view->getReturnPage();	// Browser back-link required for back-button.
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$ptid   = $input->getInt('ptid');
$pid    = $input->post->getInt('pid', $input->getInt('pid'));
?>
<?php /* Block attempt to activate 'edit' mode via URL manipulation. */
if (empty($ptid) || empty($pid)) :	// TEMP disabled on 2021-09-22 to reduce access barriers while implementing "edit tracking" ... re-enabled on 2022-05-31 after bug reported by CS regarding post-tracking editing not possible
	Messager::setMessage([
		'type' => 'warning',
		'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DIRECT_ACCESS_IS_NOT_ALLOWED_TEXT', $lang), null)
	]);

	$redirect = new Uri($input->server->getUrl('HTTP_REFERER', $input->server->getUrl('REQUEST_URI')));
	$redirect->setVar('layout', 'item');

	header('Location: ' . $redirect->toString());
	exit;
endif;
?>
<?php /* Access check */
if (is_a($user, ' \Entity\User')) :
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
$canEdit     = true;
$canEditPart = true;
$canEditProc = true;																// TODO - consider user role(s)
$isEditPart  = ($layout === 'edit' && $ptid > 0 && $canEditPart);					// Added condition '$ptid > 0' on 2021-10-21
$isEditProc  = ($layout === 'edit' && $pid  > 0 && $canEditProc && $isEditPart);	// Added condition '$pid  > 0' on 2021-10-21
?>
<?php /* Process form data */
$task    = $input->post->getCmd('task',    $input->getCmd('task'));
$format  = $input->post->getWord('format', $input->getWord('format'));

if (!empty($_POST)) :
	if (!is_null($task)) :
		switch ($task) :
			case 'edit' :
				$view->saveEdit();
			break;

			case 'editPart' :
			case 'editProc' :
				$view->saveEditProcess();
			break;

			case 'gencode' :
				$view->genCode();
			break;

			case 'handlePictureUpload' :
				$view->getInstance('tools', ['language' => $this->language])->handleUploadedPicture($view->get('input')->server->getUrl('REQUEST_URI'));
			break;
		endswitch;
	endif;
endif;
?>
<?php /* Load view data */
$browser = ArrayHelper::getValue($GLOBALS, 'session')->get('navigator', []);
$item    = $view->get('item');
//echo "<pre>";print_r($item);exit;
// Block the attempt to open a non-existing part.
if (!is_a($item, ' \Entity\Part') || (is_a($item, ' \Entity\Part') && is_null($item->get('partID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PART_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $ptid)
    ]);

	header('Location: ' . View::getInstance('parts', ['language' => $lang])->getRoute());
	exit;
endif;

$this->item       = $item;
$this->lngID      = (new Registry($this->view->get('model')->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID');
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';

$isAutotrack  = $input->getInt('at') == '1';
$isAutofill   = $input->getInt('af') == '1';
$isAutosubmit = $input->getInt('as') == '1';
?>
<?php /* Load more view data - required when not in print mode */ ?>
<?php
// Get hands on processes this user's organization is responsible for. It's required for access control.
$userOrganisation = $model->getInstance('organisation', ['language' => $lang])->getItem((int) $user->get('orgID'));
$orgName          = $userOrganisation->get('name');
$orgProcesses     = (array) $userOrganisation->get('processes', []);

$processes        = $model->getInstance('processes', ['language' => $lang])->getList(['params' => true]);

// Get lot this part belongs to (if any).
$itemLot          = $model->getInstance('lot', ['language' => $lang])->getItem((int) $this->item->get('lotID'));

// Get reference to the list of processes an article must run through.
$itemProcesses  = (array) $this->item->get('processes');
    $unwantedProcesses = [];
    foreach ($itemProcesses as $key => $value) {
        // Check if the processState is 0
        if ($value->get('processState') === 0) {
            // Remove the element from the array
            $unwantedProcesses[$key] = $itemProcesses[$key];
            //echo "<pre>";print_r($itemProcesses[$key]);
            //unset($itemProcesses[$key]);
        }
    }
$disableProcess = array_keys($unwantedProcesses);

$itemProcessIDs = array_keys($itemProcesses);

// Get reference to all tracking entries for this part (every tracking entry is an object
// representing the table row) for each of these processes.
$trackingData   = (array) $this->item->get('trackingData');

$fArraytoTrack = array_diff($itemProcessIDs, $disableProcess);
// Separate untracked processes from previously tracked processes and
// dump previously tracked pid as well as next pid to be tracked.
$trackedPids    = array_keys($trackingData);
$untrackedPids  = [];
$itemProcessIDsUntracked = array_filter($fArraytoTrack, function($id) use(&$trackedPids) {	// Filter $itemProcessIDs for tracked processes (variable becomes $untrackedPids further below)
	return !in_array($id, $trackedPids);
});
$diffProcessIDsVsProcessIDsUntracked = array_diff($fArraytoTrack, $itemProcessIDsUntracked);   // required to find the previous tracked process

/*// @debug
if ($debug) :
//	echo '<pre>item process chain: '       . print_r(json_encode($itemProcessIDs), true) . '</pre>';
//	echo '<pre>tracked processes: '        . print_r(json_encode($trackedPids), true) . '</pre>';
//	echo '<pre>requested process: '        . print_r($pid, true) . '</pre>';
//	echo '<pre>untracked item processes: ' . print_r(($itemProcessIDsUntracked), true) . '</pre>';
//	echo '<pre>diffProcessIDsVsProcessIDsUntracked: ' . print_r(json_encode($diffProcessIDsVsProcessIDsUntracked), true) . '</pre>';
//	echo '<pre>' . print_r(str_repeat('_', 50), true) . '</pre>';
//	die;
endif;*/

// Dump pids of the previous and next process.
$firstPid       = current($fArraytoTrack);                    // the very first process in line
$prevTrackedPid = end($diffProcessIDsVsProcessIDsUntracked);   // the previously tracked process
$nextTrackedPid = current($itemProcessIDsUntracked);           // the next trackable process
// Skip first untracked process id to enable it for editing.
array_shift($itemProcessIDsUntracked);

/*// @debug
if ($debug) :
//	echo '<pre>the very first process in line: ' . print_r($firstPid, true) . '</pre>';
//	echo '<pre>the previously tracked process: ' . print_r($prevTrackedPid, true) . '</pre>';
//	echo '<pre>the next trackable process: '     . print_r($nextTrackedPid, true) . '</pre>';
//	die;
endif;*/

// Calculate first process to be overlaid to prevent edit.
if (empty($prevTrackedPid)) :
	/*// @debug
	if ($debug) :
//		echo '<pre>' . print_r('IF', true) . '</pre>';
	endif;*/

	$untrackedPids = array_slice($fArraytoTrack, 1);
else :
	/*// @debug
	if ($debug) :
//		echo '<pre>' . print_r('ELSE', true) . '</pre>';
	endif;*/

	$untrackedPids = $itemProcessIDsUntracked;
endif;

// Retrieve the error catalogue for all processes that this part has to run through.
// NOTE: The next command is outsourced into \ \View\Part::prepareErrorList() in TT-DEV
//       so that <var>$errors</var> will be available from this part via this->item->get('errors').
$errors = $model->getInstance('errors', ['language' => $lang])->getErrorsByLanguage(
	$this->lngID,
	$itemProcessIDs
);

/*// @debug
if ($user->get('userID') == '1') :
	echo '<pre><strong>itemProcessIDs: </strong>' . print_r($itemProcessIDs, true) . '</pre>';
	echo '<pre><strong>errors: </strong>' . print_r($errors, true) . '</pre>';
//	die;
endif;*/

// Calculate first process to be overlaid to prevent editing.
$isProcEditable = ($pid === $nextTrackedPid);	// TEMP disabled on 2021-09-22 to reduce access barriers while implementing "edit tracking" ... re-enabled on 2022-05-30 after bypassing untrackable processes via AT has been reported by JH

if (false === $isProcEditable) :	// FIXME - why is $untrackedPids empty here?
	$isProcEditable = !in_array($pid, $itemProcessIDsUntracked)/* && $userIsOriginalAuthor || $userIsHighPrivileged*/;	// FIXME
endif;

/*// @debug
if ($debug) :
//	echo '<pre>Is process editable? ' . print_r($isProcEditable ? 'YES' : 'NO', true) . '</pre>';
//	die;
endif;*/
?>
<?php /* If the process chain up to the process to be edited is incomplete prevent editing and return with user notification. */
if (false == $isProcEditable) :	// TEMP disabled on 2021-09-22 to reduce access barriers while implementing "edit tracking" ... re-enabled on 2022-05-30 after bypassing untrackable processes via AT has been reported by JH
//	if ($user->getFlags() <= User::ROLE_ADMINISTRATOR) :
		$return = new Uri( sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d&pid=%d', $lang, $this->item->get('partID'), $pid ) );
		$return->setFragment('p-' . hash('MD5', $pid));

		$message = sprintf(
			Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_X_TRACKING_ONLY_AFTER_PREV_PROCESSES_TRACKED_TEXT', $lang),
			(new Registry(ArrayHelper::getValue($processes, $pid, [], 'ARRAY')))->get('name')
		);
		$message = $isAutotrack
			? sprintf('%s<br>%s',
				Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT', $lang),
				$message
			)
			: $message;

		Messager::setMessage([
			'type' => 'warning',
			'text' => $message
		]);

		// header('Location: ' . preg_replace('/\blayout=edit\b/iu', 'layout=item', $input->server->getUrl('HTTP_REFERER', $input->server->getUrl('REQUEST_URI'))));
		header('Location: ' . UriHelper::osSafe( UriHelper::fixURL( $return )));
		exit;
//	endif;
endif;
?>
<?php /* Load view data (continuation) */
// Get reference to measured data for each of these processes.
$measuredData     = (array) $this->item->get('measuredData');

// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$staticTechParams = (array) $model->getInstance('techparams', ['language' => $lang])->getStaticTechnicalParameters(true);

$now       = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
$dateNow   = $now->format('Y-m-d');
$timeNow   = $now->format('H:i:s');

$errNum    = null;
$errText   = null;
$errColor  = null;

// Detect whether this part is a good part or bad part.
// This info is required for the badge.
$hasError     = false;
$errProcID    = null;		// will receive the process id to link to on click onto the quality classifier badge

// If part is bad, fetch errorous process' ID.
if ($this->item->isBad()) :
	array_filter($trackingData, function($trackingData, $procID) use(&$hasError, &$errProcID)
	{
		if ($hasError) :
			return;
		endif;

		$match = array_filter((array) $trackingData, function($entry, $paramID)
		{
			return ( ($paramID == Techparams::STATIC_TECHPARAM_ERROR) && ((int) $entry > 0) );

		}, ARRAY_FILTER_USE_BOTH);

		if ($match) :
			$hasError  = true;
			$errProcID = $procID;
		endif;

	}, ARRAY_FILTER_USE_BOTH);
endif;

// Init tabindex
$tabindex = 0;
?>

<style>
#__btn-camera-13 {
	position: absolute;
    z-index: 1;
    top: 380px;
    right: 0;
}

.quality-badge {
	padding: 0.25em 0.75rem 0.35em;
}

.btn-link-pdf {
	padding-top: 2px;
	padding-bottom: 2px;
}

.collapse-sm.show .card {
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
    border-bottom-right-radius: 4px;
    border-top-right-radius: 4px;
	margin-left: 30px;
    letter-spacing: 0.5px;
    line-height: 1.4em;
	padding: 15px 0  5px 30px;
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
	padding-bottom: 0;
}
.timeline .event:last-of-type .row.form-group:last-of-type {
	margin-bottom: 0;
	padding-bottom: 0;
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
.timeline .event:first-child:after {
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

.timeline .tracking.submitted:before {
	<?php // Animated gif to indicate there's something going on ?>
	content: url('data:image/gif;base64,R0lGODlhKwALAPEAAAAAAP///3x8fP///yH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAKwALAAACMoSOCMuW2diD88UKG95W88uF4DaGWFmhZid93pq+pwxnLUnXh8ou+sSz+T64oCAyTBUAACH5BAkKAAAALAAAAAArAAsAAAI9xI4IyyAPYWOxmoTHrHzzmGHe94xkmJifyqFKQ0pwLLgHa82xrekkDrIBZRQab1jyfY7KTtPimixiUsevAAAh+QQJCgAAACwAAAAAKwALAAACPYSOCMswD2FjqZpqW9xv4g8KE7d54XmMpNSgqLoOpgvC60xjNonnyc7p+VKamKw1zDCMR8rp8pksYlKorgAAIfkECQoAAAAsAAAAACsACwAAAkCEjgjLltnYmJS6Bxt+sfq5ZUyoNJ9HHlEqdCfFrqn7DrE2m7Wdj/2y45FkQ13t5itKdshFExC8YCLOEBX6AhQAADsAAAAAAAAAAAA=');
	position: absolute;
	z-index: 1;
    left: -1%;
	width: 102%;
    height: 102%;
	padding-top: 15%;
	background-color: rgba(245,245,245,0.7);
	box-shadow: 0 0 10px 10px rgb(245 245 245 / 70%);
	backdrop-filter: blur(4px);
	text-align: center;
}

fieldset.part-process-tracking {
    outline: 0 !important;
}
fieldset.part-process-tracking:before {
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
    content: "";
    text-align: center;
    vertical-align: middle;
    line-height: 2;
    font-weight: bold;
	color: #b94a48;
}
.tracked.process   > .part-process-tracking:before,
.untracked.process > .part-process-tracking:before {
	display: block;
	z-index: 1;
}
.untracked.process > .part-process-tracking:before {
	content: "<?php echo Text::translate('COM_FTK_HINT_PROCESS_TRACKING_ONLY_AFTER_PREV_PROCESSES_TRACKED_TEXT', $lang); ?>";
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
</style>

<article>
	<header>
		<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d%s', $lang, $this->item->get('partID'), ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '') ))); ?>"
		   role="button"
		   class="btn btn-link"
		   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
		   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
		   style="vertical-align:super; color:inherit!important"
		>
			<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
			<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?></span>
		</a>

		<h1 class="h3 d-inline-block mr-3"><?php echo Text::translate('COM_FTK_LABEL_PART_EDIT_TEXT', $lang); ?></h1>

		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $lang]); ?>
	</header>

	<hr>

	<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d', $lang, $this->item->get('partID') ))); ?>"
		  method="post"
		  name="editPartForm"
		  class="form form-horizontal partForm validate autoSerializable<?php echo ($isAutotrack  ? ' autoTrackable'   : '') .
																				   ($isAutofill   ? ' autoFillable'    : '') .
																				   ($isAutosubmit ? ' autoSubmittable' : ''); ?>"
		  id="editPartForm"
		  enctype="multipart/form-data"
		  data-submit=""
		  data-monitor-changes="true"
		  data-require="sessionStorage"
	>
		<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
		<input type="hidden" name="lng"      value="<?php echo $lang; ?>" />
		<input type="hidden" name="lngID"    value="<?php echo $this->lngID; ?>" />
        <input type="hidden" name="artiID"     value="<?php echo $this->item->get('artID'); ?>" />
        <input type="hidden" name="lotID"    value="<?php echo $this->item->get('lotID'); ?>" />
		<input type="hidden" name="task"     value="<?php echo $isEditPart ? 'editPart' : 'edit'; ?>" />
		<?php if (!empty($input->get->getCmd('at'))) : ?>
		<input type="hidden" name="at"       value="<?php echo $input->get->getCmd('at'); ?>" />
		<?php endif; ?>
		<?php if (!$isEditPart) : ?>
		<input type="hidden" name="ptid"     value="<?php echo (int) $this->item->get('partID'); ?>" />
		<?php else : ?>
		<input type="hidden" name="type"     value="<?php echo (int) $this->item->get('artID'); ?>" />
		<?php endif; ?>
		<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
		<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

		<?php /*   M A S T E R D A T A   */ ?>
		<section id="masterdata">
			<h2 class="h4 mb-4 mt-lg-4"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?>
				<?php if ($hasError) : ?>
				<a href="#process-<?php echo (int) $errProcID; ?>" class="btn btn-link badge badge-danger quality-badge float-right"><?php
					echo Text::translate('COM_FTK_LABEL_BAD_PART_TEXT', $lang);
				?></a>
				<?php else : ?>
				<span class="badge badge-success quality-badge float-right"><?php
					echo Text::translate('COM_FTK_LABEL_GOOD_PART_TEXT', $lang);
				?></span>
				<?php endif; ?>
			</h2>

			<fieldset class="part-params">
				<legend class="sr-only fieldset-title"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?></legend>
				<div class="row form-group">
					<label for="type" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $lang); ?>:</label>
					<div class="col">
						<input type="text"
							   name="type"<?php // Changed from 'name' to 'type' on 2021-06-22 ?>
							   class="form-control"
							   value="<?php echo html_entity_decode($this->item->get('type')); ?>"
							   tabindex="<?php echo ++$tabindex; ?>"
							   required
							   readonly
						/>
					</div>
				</div>
				<div class="row form-group">
					<label for="code" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_TRACKING_CODE_TEXT', $lang); ?>:</label>
					<div class="col input-group">
						<input type="text"
							   name="code"
							   value="<?php echo html_entity_decode($this->item->get('trackingcode')); ?>"
							   class="form-control"
							   aria-describedby="link-to-lot"
							   style="letter-spacing:1px"
							   tabindex="<?php echo ++$tabindex; ?>"
							   readonly
							   required
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
							</a>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</fieldset>
		</section>

		<hr>

		<?php // @debug ?>
		<?php if ($user->isProgrammer() || $user->get('userID') == '102') : ?>
		<pre class="text-bold">layout: <span class="text-danger"><?php echo $layout; ?></span></pre>
		<?php endif; ?>

		<?php /*   P R O C E S S   T R E E   */ ?>
		<section id="processTree">
			<h3 class="h5 d-inline-block mt-lg-4 mb-lg-4 mr-3"><?php
				echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $lang);
			?></h3>

			<?php if (!$isEditPart) : ?>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d%s', $lang, $this->item->get('partID'), ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '') ))); ?>"
			   role="button"
			   class="btn btn-sm btn-info"
			   title="<?php echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_EDIT_THIS_TEXT', $lang); ?>"
			   style="vertical-align:unset"
			>
				<i class="fas fa-pencil-alt"></i>
				<span class="d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $lang); ?></span>
			</a>
			<?php endif; ?>

			<?php // Output associated processes as readonly input fields ?>
			<?php if (count($itemProcesses)) : ?>
			<?php 	if ($canEditPart) : ?>

			<?php 	$j = 0; ?>
			<div class="timeline position-relative mb-4" id="partProcesses">
				<?php // Iterate over the list of article processes and load the related process from the processes list using the article process' id ?>
				<?php foreach ($itemProcesses as $artProcID => $artProc) : ?><?php
					// Get reference to this process from the article processes list to get hands on the drawing.
					$process    = ArrayHelper::getValue($processes, $artProcID, new stdclass);
					// Load full process information into a registry object for reliable data access.
					$process    = new Registry($process);

					$isProcTracked  =  in_array($artProcID, $trackedPids);
					$isProcEditable = !in_array($artProcID, $untrackedPids);
//					$cssStatusClass = (!$isProcEditable) ? ' disabled' : '';
//					$cssStatusClass = (!$isProcEditable || $pid != $artProcID) ? ' disabled' : '';
					$htmlStatusAttr = (!$isProcEditable) ? ' disabled' : '';

					// Get reference to technical paramaters assigned to this process.
					$techParams = (array) $process->get('tech_params');

					// Load corresponding user input from this part's tech parameters user input data.
					$itemProc   = ArrayHelper::getValue($trackingData, $artProcID);
					$itemProc   = is_object($itemProc) && !is_a($itemProc, 'Joomla\Registry\Registry') ? new Registry($itemProc) : new Registry;

					$drawing    = new Registry($artProc->get('drawing'));
					// Prepare process drawing pic + PDF for display and download.
					$processNo  = sprintf('%s.%s', $drawing->get('number'), $drawing->get('index'));

					$filePDF = UriHelper::osSafe( UriHelper::fixURL($drawing->get('file')) );
					$filePDF = (is_file(FTKPATH_BASE . $filePDF) && is_readable(FTKPATH_BASE . $filePDF))
							? $filePDF . '?t=' . mt_rand(0, 9999999)
							: null;

					$images  = (array) $drawing->get('images');
					$image   = count($images) ? current($images) : null;
					$image   = isset($image)  ? UriHelper::osSafe( UriHelper::fixURL($image) ) : null;
					$image   = ((!is_null($image) && is_file(FTKPATH_BASE . $image) && is_readable(FTKPATH_BASE . $image))
							? $image . '?t=' . mt_rand(0, 9999999)
//							: 'https://via.placeholder.com/280x198/E9ECED/F00.png?text=' . Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang));
							: sprintf('https://%s/280x198/E9ECED/F00.png?text=%s', FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER, Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang)));

					// Extract error catalogue items to be selectable for this process.
					$errorList = array_slice($errors, 0, $j+1, true);

					$isCurrentlyEdited = ((int) $process->get('procID') === (int) $pid);
				?>

				<div class="<?php echo trim(sprintf('list-item dynamic-content event process position-relative %s',
						($isEditPart && $pid == $artProcID ? 'tracking' : ($isProcTracked ? 'tracked' : 'untracked'))
					 )); ?>"
					 id="p-<?php echo hash('CRC32', $artProcID); ?>"
					 data-proc-id="<?php echo (int) $artProcID; ?>"
				>
					<fieldset class="<?php echo trim(sprintf('part-process-tracking position-relative %s %s',
								(!$isProcEditable                   ? 'disabled'     : ''),
								($isAutotrack && $isCurrentlyEdited ? 'highlighting' : '')
							  )); ?>"
							  <?php if (isset($pid) && $isAutotrack && $isAutofill) : ?>
							  title="<?php echo sprintf('%s %s',
								Text::translate('COM_FTK_HINT_AUTOTRACK_IS_ACTIVE_TEXT', $lang),
								Text::translate('COM_FTK_HINT_INPUT_IS_DUPLICATED_FOR_AUTOTRACK_TEXT', $lang)
							  ); ?>"
							  data-toggle="tooltip"
							  data-placement="top"
							  <?php endif; ?>
					>
						<h5 class="h6 text-uppercase d-inline-block mr-lg-3 mb-4" style="margin-top:-1px"><?php   echo html_entity_decode($process->get('name')); ?></h5>
						<small class="d-none d-md-inline mr-3 text-muted" style="vertical-align:text-top">( <?php echo html_entity_decode($processNo); ?> )</small>

<?php if ($isProcEditable && $pid == $artProcID) : // Some controls must only be available to the currently edited process ?>
						<?php // Button open drawing PDF ?>
						<?php if (0) : ?>
						<?php if (!is_null($filePDF)) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $filePDF, mt_rand(0, 9999999) ))); ?>"
						   class="btn btn-sm btn-link btn-link-pdf py-0 pr-0 float-right"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_OPEN_PROCESS_DRAWING_TEXT', $lang); ?>"
						   data-toggle="tooltip"
						   target="_blank"
						   rel="nofollow noreferrer"
						>
							<i class="far fa-file-pdf icon-pdf" style="vertical-align:middle; font-size:1.95rem; margin-top:-1px"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_OPEN_PROCESS_DRAWING_TEXT', $lang); ?></span>
						</a>
						<?php else : ?>
						<span class="ml-3 px-2 py-1 text-muted float-right"
							  title="<?php echo Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $lang); ?>"
						>
							<i class="far fa-file" style="opacity:.5; vertical-align:bottom; font-size:1.5rem"></i>
						</span>
						<?php endif; ?>
						<?php endif; ?>

						<?php // Get camera button markup (not available on mobile devices) ?>
						<?php if ($isEditProc) : ?>
						<?php   if ($this->view->get('browser')->get('ismobiledevice')) : ?>
						<?php	    $modalForm = [ LayoutHelper::render('widget.device.camera.mobile',
										[
											'partID'  => $this->item->get('partID'),
											'procID'  => $artProcID,
											'view'    => $this->view,
											'attribs' => []
										],
										['language' => $lang]
									)];
						?>
						<?php else : ?>
						<?php	    $modalForm = [ LayoutHelper::render('widget.device.camera.stationary',
								[
										'partID'  => $this->item->get('partID'),
										'procID'  => $artProcID,
										'view'    => $this->view,
										'attribs' => []
								],
								['language' => $lang]
						)];
						?>
						<?php endif; ?>

						<?php // Fetch required information for Camera widget.
						$modalTitle = Text::translate('COM_FTK_LINK_TITLE_TAKE_PICTURE_TEXT', $lang);

						// Fetch document properties from database.
						$helpFiles    = MediaHelper::getHelpFiles([
							'context'  => 'part',
							'section'  => 'help',
							'topic'    => 'camera',
							'mobile'   => true,
							'language' => $lang,
							'lngID'    => $this->lngID
						]);
						$helpFile     = current($helpFiles);
						$helpFilePath = 'javascript:void(-1)" onclick="alert(&quot;' . Text::translate('COM_FTK_HINT_FILE_NOT_FOUND_TEXT', $lang) . '&quot;)';

						// On mobile devices append link to mobile upload guide to popup window title.
						if (is_a($helpFile, '\ \Entity\Document') && $helpFile->get('path')) :

							$helpFilePath = FilesystemHelper::relPath($helpFile->get('path'));
							$modalTitle   = !$this->view->get('browser')->get('ismobiledevice') ? $modalTitle : htmlentities($modalTitle .
							'<a href="' . $helpFilePath . '" class="ml-3 text-muted" title="' . Text::translate('COM_FTK_LINK_TITLE_HELP_TEXT', $lang) . '" target="_blank">' .
								'<i class="fas fa-question-circle text-muted"></i>' .
								'<span class="sr-only ml-1">' . Text::translate('COM_FTK_HEADING_HELP_TEXT', $this->language) . '</span>' .
							'</a>', ENT_COMPAT, 'UTF-8');
						endif;
						?>

						<?php // Button toggle camera ?>
						<button type="button"
						        role="button"
						        class="btn btn-sm btn-secondary btn-camera ml-2 float-right"
								id="btn-camera-<?php echo $artProcID; ?>"
						        title="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_OPEN_CAMERA_WIDGET_TEXT', $lang); ?>"
								data-toggle="modal"
								data-size="lg"
								data-backdrop="static"
								data-target="#mainModal"
								data-modal-title="<?php echo $modalTitle; ?>"
								data-modal-content="<?php echo base64_encode(implode($modalForm)); ?>"
								data-modal-submittable="false"
								aria-haspopup="true"
						>
							<i class="fas fa-camera"></i>
							<span class="d-none ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_OPEN_TEXT', $lang); ?></span>
						</button>
						<?php endif; ?>

						<?php // Button Track/Edit process ?>
						<?php if (!$isEditPart) : ?>
						<?php if (in_array($artProcID, $orgProcesses)) : // Editing is permitted to responsible organization(s) and privileged user(s) only ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d&pid=%d%s',
								$lang,
								$this->item->get('partID'),
								$process->get('procID'),
								($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '')


						   ))); ?>#<?php echo hash('CRC32', $artProcID); ?>"
						   role="button"
						   class="btn btn-sm btn-info float-right"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_EDIT_THIS_TEXT', $lang); ?>"
						   style="vertical-align:baseline"
						>
							<i class="fas fa-pencil-alt"></i>
							<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_EDIT_TEXT', $lang); ?></span>
						</a>
						<?php endif; ?>
						<?php endif; ?>
<?php endif; ?>

						<?php if ($isEditPart && $pid == $artProcID) : ?>
						<?php if (in_array($artProcID, $orgProcesses)) : // Editing is permitted to responsible organization(s) and privileged user(s) only ?>
						<?php // Button Save changes ?>
						<button type="submit"
								form="editPartForm"<?php // FIXME - replace with calculated form name ?>
								name="button"
								value="submit"
								class="btn btn-sm btn-warning btn-submit btn-save allow-window-unload"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
								data-bind="toggleSubmitted"
								data-target="#p-<?php echo hash('CRC32', $artProcID); ?>"
								data-proc-id="<?php echo (int) $artProcID; ?>"
								style="vertical-align:baseline"
						>
							<i class="fas fa-save"></i>
							<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $lang); ?></span>
						</button>
						<?php // Button close out and redirect to item view ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d&pid=%d%s',
								$lang,
								$this->item->get('partID'),
								$artProcID,
								($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '')


						   ))); ?>#p-<?php echo hash('MD5', $artProcID); ?>"
						   role="button"
						   class="btn btn-sm btn-info ml-lg-2 float-right"
						   title="<?php echo Text::translate('COM_FTK_LABEL_FINISH_EDIT_TEXT', $lang); ?>"
						   style="vertical-align:baseline"
						>
							<i class="fas fa-times"></i>
							<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $lang); ?></span>
						</a>
						<?php endif; ?>
						<?php endif; ?>

						<div class="position-absolute process-drawing" id="drawing-<?php echo (int) $artProcID; ?>">
							<figure class="figure bg-white m-md-auto">
							<?php // TODO - integrate rendering of article image or a Placeholder image like is done in edit.php ... pass required data to the layout for output preparation ?>
							<?php if (FALSE) : ?>
								<?php // render drawing placeholder image ?>
								<?php // echo LayoutHelper::render('image.placeholder.article', new \stdclass, ['language' => $lang]); ?>
							<?php else : ?>
								<a href="<?php echo (is_null($filePDF)) ? 'javascript:void(0)' : UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $filePDF, mt_rand(0, 9999999) ))); ?>"
								   class="chocolat-image d-block"
								   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_DRAWING_TEXT', $lang); ?>"
								   target="_blank"
								>
									<img src="<?php echo UriHelper::osSafe($image); ?>"
										 width=""
										 height=""
										 alt=""
										 style="box-shadow:0 0 1px 1px #ced4da"
									>
									<!--figcaption class="figure-caption pt-5 h5"></figcaption-->
								</a>
							<?php endif; ?>
							</figure>
						</div>

						<?php // Iterate over all technical parameters assigned to a process and take its ID to read a potential input from the parts technical parameters data provided via user input ?>
						<?php if (count($techParams)) : ?>

						<?php 	array_filter($techParams, function($tparam, $id) use(
									&$artProcID,
									&$canEdit,
									&$canEditPart,
									&$dateNow,
									&$errColor,
									&$errNum,
									&$errorList,
									&$errText,
									&$isCurrentlyEdited,
									&$isEditPart,
									&$itemProc,
									&$lang,
									&$orgProcesses,
									&$pid,
									&$process,
									&$processes,
									&$processNo,
									&$staticTechParams,
									&$tabindex,
									&$timeNow,
									&$trackingData,
									&$user,
									&$userOrganisation
								) { ?>
						<?php		$cntStaticParams = count($staticTechParams); ?>
						<?php		$tparam = new Registry($tparam); ?>

						<div class="row form-group<?php echo (int) $id < ($cntStaticParams - 2) ? ' mb-1' : ((int) $id >= ($cntStaticParams - 1) ? ' mb-1' : ''); ?>" data-param-id="<?php echo (int) $id; ?>">
							<label for="procParams" class="col col-4 col-form-label<?php echo (int) $id < ($cntStaticParams - 1) ? '-sm' : ''; ?>"><?php
								echo html_entity_decode($tparam->get('name'));
							?>:</label>
							<div class="col<?php echo (int) $id < ($cntStaticParams - 1) ? ' col-lg-4' : ''; ?>">
							<?php // If form field is the status field, render the error catalogue for this process. ?>
							<?php if ($id == Techparams::STATIC_TECHPARAM_ERROR) : ?><?php
									$errNum   = ( (!is_null($errNum) && (int) $errNum > 0)
										? $errNum
										: (new Registry(ArrayHelper::getValue($trackingData, $process->get('procID'), [])))->get($id) );
									$errColor = ( (!empty($errNum) && !empty($errText))
										? 'bg-danger text-white'
										: ( is_null($errNum)
											? ''
											: ( $errNum == '0' ? 'bg-success' : 'bg-warning' ) ) );

									// Take the error code and find the corresponding error text if not available yet here.
									if (empty($errText) && !empty($errNum)) :
										array_walk($errorList, function($list) use(&$errNum, &$errText)
										{
											foreach ($list as $errID => $text) :
//												if ($errID == $errNum) :					// DiSABLED on 2023-11-06 - replaced with next line
												if (intval($errID) == intval($errNum)) :	/* ADDED    on 2023-11-06 - while merging the old and new error catalogs the old tracked error numbers are
																							*							a combination of number (old) and string (new) like "1 CUT05".
																							*							To not break old error resolving and rendering we force the number portion to be the value of $errNum.
																							*							Once the new error catalog will be active, the already modified tracked errors must be modified a last time: drop the leading number. */
													$errText = $text;

													return;
												endif;
											endforeach;
										});
									endif;
							?>
								<?php // Process is currently tracked. Render dropdown list. ?>
								<?php if ($isCurrentlyEdited) : ?>
								<select name="procParams[<?php echo (int) $process->get('procID'); ?>][<?php echo (int) $id; ?>]"
										class="FOO1 form-control selectError"
										tabindex="<?php echo ++$tabindex; ?>"
										required
										autocapitalize="off"
										<?php // autocomplete="off" // DiSABLED on 2023-04-27 as requested by SM to improve speed ?>
										autocorrect="off"
										spellcheck="false"
										data-rule-required="true"
										data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROCESS_STATUS_TEXT', $lang); ?>"
								>
									<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $lang); ?> &ndash;</option>
									<option value="0"<?php echo ((int) $errNum == '0' ? ' selected' : ''); ?>><?php echo Text::translate('COM_FTK_LIST_OPTION_PASSED_LABEL', $lang); ?></option>
									<option value=""><?php echo str_repeat('_', 90); ?></option>
									<?php foreach ($errorList as $procID => $list) : ?>
									<?php $p = ArrayHelper::getValue($processes, $procID, []); ?>
									<optgroup label="<?php echo html_entity_decode(ArrayHelper::getValue($p, 'name', '', 'STRING')); ?>">
									<?php 	foreach ($list as $errID => $error) : ?>
									<?php 		// Skip untranslated error. ?>
									<?php 		if ($error == Text::translate('COM_FTK_NA_TEXT', $this->language)) : continue; endif; ?>
									<?php		echo sprintf('<option value="%d"%s>%s</option>',
												$errID,
												((int) $errID == (int) $errNum ? ' selected' : ''),
												html_entity_decode($error)
										); ?>
									<?php 	endforeach; ?>
									<?php endforeach; ?>
								</select>
								<?php // Process is already tracked. Render status field. ?>
								<?php else : ?>
								<input type="text"
									   class="FOO2 form-control <?php echo $errColor; ?>"
									   value="<?php echo ( $errNum == '0' ? Text::translate('COM_FTK_LIST_OPTION_PASSED_LABEL', $lang) : html_entity_decode($errText) ); ?>"
									   autocapitalize="off"
									   <?php // autocomplete="off" // DiSABLED on 2023-04-27 as requested by SM to improve speed ?>
									   autocorrect="off"
									   spellcheck="false"
									   readonly
									   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
								>
								<?php endif; ?>

							<?php else : ?>

							<?php // Render text field ?>
							<input type="text"
								   name="procParams[<?php echo (int) $process->get('procID'); ?>][<?php echo (int) $id; ?>]"
								   data-fieldname="<?php echo $tparam->get('fieldname'); ?>"
								   data-is-currently-edited="<?php echo (int) $tparam->get('isCurrentlyEdited'); ?>"
								   class="FOO3 form-control<?php echo (int) $id < ($cntStaticParams - 1) ? ' form-control-sm no-autofillable' : ''; ?>"
								   autocapitalize="on"
								   <?php // autocomplete="off" // DiSABLED on 2023-04-27 as requested by SM to improve speed ?>
								   autocorrect="off"
								   spellcheck="false"
								   tabindex="<?php echo ++$tabindex; ?>"

								<?php // Field is a custom tech parameter ?>
								<?php if (!property_exists($tparam, 'fieldname') && !array_key_exists($id, $staticTechParams)) : ?>

									<?php echo ($canEditPart && (in_array($artProcID, $orgProcesses)) && $artProcID == $pid) ? '' : ' disabled readonly'; ?>
									value="<?php echo html_entity_decode($itemProc->get($id, '')); ?>"

								<?php // Field is one of the staticTechParam fields ?>
								<?php else : ?>

									<?php if ($isCurrentlyEdited && $tparam->get('fieldname') !== 'annotation') :   // Allow user input to the annotation-field ?>
										<?php echo 'readonly'; ?>
									<?php elseif (!$isCurrentlyEdited) : ?>
										<?php echo 'readonly'; ?>
										<?php echo 'disabled'; 																// the 'disabled' attribute prevents field data from being submitted and stored ?>
									<?php elseif ($isCurrentlyEdited) : ?>
										<?php echo (!in_array($artProcID, $orgProcesses) ? 'readonly disabled' : '');       // refers to annotation-field ?>
									<?php endif; ?>

									<?php if (!$isCurrentlyEdited) : ?>
									value="<?php echo html_entity_decode($itemProc->get($id, '')); ?>"
									<?php else : ?>
									value="<?php
									switch ($tparam->get('fieldname')) : ?><?php
										case 'operator' :
											echo html_entity_decode($user->get('fullname'));
										break;

										case 'organisation' :
											echo html_entity_decode($userOrganisation->get('name'));
										break;

										case 'date' :
											echo $dateNow;
										break;

										case 'time' :
											echo $timeNow;
										break;

										case 'drawing' :
											// Autofill currently active process drawing number only if none has been stored previously
											if (is_object($itemProc)) :
												echo !empty($itemProc->get($id))
													? $itemProc->get($id)
													: html_entity_decode($processNo);
											else :
												echo  html_entity_decode($processNo);
											endif;
										break;

										case 'annotation' :
											echo html_entity_decode($itemProc->get($id, ''));
										break;
									endswitch; ?>"
									<?php if ($tparam->get('fieldname') == 'annotation') : ?>
										<?php echo 'autofocus'; ?>
									<?php endif; ?>
									<?php endif; ?>

								<?php endif; ?>
							>
							<?php endif; ?>
							</div>
						</div>

						<?php 	}, ARRAY_FILTER_USE_BOTH); ?>

						<?php endif; ?>
					</fieldset>

					<?php if ($isEditPart && $pid == $artProcID) : $measurementTrackingWidgetRenderOptions = ''; ?>
					<?php $measurementTrackingWidget = LayoutHelper::render('forms.article.process_measurement', [
							'isTracking' => true,	// flags this request as "tracking" mode, which makes only limited fields editable rather than in "compositing" mode
							'item'       => $this->item,
							'pid'        => $pid,
							'hide'       => ['mpDatatype','mpToolbar'],
							'tabindex'   => ++$tabindex
						], ['language'   => $lang]);
					?>

					<?php $measurementTrackingInfo   = LayoutHelper::render('system.alert.info', [
						'message' => Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_MEASUREMENT_DEFINITIONS_TEXT', $lang),
						'attribs' => [
							'class' => 'alert-sm my-0'
						]
					]); ?>

					<?php	if (!empty($measurementTrackingWidget) && $measurementTrackingWidget !== '') : ?>
					<?php	$measurementTrackingWidgetRenderOptions = json_encode([
						'element'    => 'div',
						'html'       => base64_encode($measurementTrackingWidget),
						'attributes' => [
							'class'       => 'collapse collapse-sm show',
							'id'          => 'card-' . (int) $pid,
							'data-parent' => '#p-'   . hash('CRC32', $artProcID)
						]
					], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); ?>
					<?php	endif; ?>

					<?php // Render measuring data input form ?>
					<fieldset class="part-process-tracking part-process-measuring-tracking pt-0">
						<div class="row form-group mb-1">
							<label class="col col-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_MEASURMENT_DATA_TEXT', $lang); ?>:</label>
							<div class="col">
								<?php if (empty($measurementTrackingWidget) || $measurementTrackingWidget === '') : ?>
									<?php echo $measurementTrackingInfo; ?>
								<?php else : ?>
									<span class="loading-indicator form-control font-italic border-0 ml-0 pl-0 text-muted" style="background:unset">
										<small><?php echo sprintf('%s . . .',
										Text::translate('COM_FTK_HINT_MEASURING_DATA_TABLE_IS_LOADED', $lang)
									); ?></small>
									</span>
								<?php endif; ?>
							</div>
						</div>

						<?php if ($debug) : ?>
						<?php // Wrapping element to render a loading indicator prior content replacement. ?>
						<span class="d-block"
						      style="background:url(<?php echo sprintf('%s/ico/ajax-loader.gif', FilesystemHelper::relPath(FTKPATH_ASSETS)); ?>) top left no-repeat">
						<?php endif; ?>

						<?php if (!is_null($measurementTrackingWidget) && $measurementTrackingWidget !== '') : ?>
						<span id="dynamic-table-<?php echo $pid; ?>"
							  data-toggle="replaceElement"
							  data-target="#dynamic-table-<?php echo $pid; ?>"
							  data-replace="true"
							  data-replacement-options='<?php echo preg_replace('/%ID%/', $pid, $measurementTrackingWidgetRenderOptions); ?>'
						></span>
						<?php endif; ?>

						<?php if ($debug) : ?>
						</span>
						<?php endif; ?>
					</fieldset>
					<?php endif; ?>

					<?php // hidden form fields must be output only to the process currently edited ?>
					<?php if ($isEditPart && $isCurrentlyEdited) : ?>
					<input type="hidden" name="ptid"     value="<?php echo (int) $this->item->get('partID'); ?>" />
					<input type="hidden" name="pid"      value="<?php echo (int) $artProcID; ?>" />
					<input type="hidden" name="fragment" value="p-<?php echo hash('MD5', $artProcID); ?>" />
					<?php endif; ?>
				</div>

				<?php 	$j += 1; ?>
				<?php endforeach; ?>
			</div>
			<?php 	else : ?>
			<div id="artProcesses" style="padding-inline-start:1.9rem">
			<?php if (is_array($this->item->get('processes'))) : ?>
				<?php foreach ($this->item->get('processes') as $artProcID) : ?>
				<div class="list-item process py-1"><?php echo html_entity_decode( ArrayHelper::getValue($processes, $artProcID, new Registry())->get('name') ); ?></div>
				<?php endforeach; ?>
			<?php endif; ?>
			</div>
			<?php 	endif; ?>

			<?php else : ?>
				<?php echo LayoutHelper::render('system.alert.info', [
					'message' => Text::translate('COM_FTK_HINT_ARTICLE_HAS_NO_PROCESSES_TEXT', $lang),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php endif; ?>
		</section>
	</form>
</article>

<?php // Free memory.
unset($artProcID);
unset($drawing);
unset($errColor);
unset($errNum);
unset($errText);
unset($hasError);
unset($input);
unset($item);
unset($itemLot);
unset($itemProcesses);
unset($model);
unset($process);
unset($processes);
unset($trackingData);
unset($staticTechParams);
unset($user);
unset($view);
?>
