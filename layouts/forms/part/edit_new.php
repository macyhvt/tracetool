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

$return = $view->getReturnPage();	// Browser back-link required for back-button.
//$return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');
$debug  = $debug && $user->isProgrammer();
$debug  = false;

$layout = $input->getCmd('layout');
$ptid   = $input->getInt('ptid');
$pid    = $input->post->getInt('pid', $input->getInt('pid'));

$task    = $input->post->getCmd('task',    $input->getCmd('task'));
$format  = $input->post->getWord('format', $input->getWord('format'));
$isPrint = $task === 'print' || current(explode('.', $task)) === 'print';

// Was this layout loaded after a tracking approval?
$isApproval   = !is_null($input->getWord('approved'));	// ADDED on 2023-07-20
$isApproved   = $input->getWord('approved') == 'true';	// ADDED on 2023-07-20

$isAutotrack  = $input->getInt('at') == '1';
$isAutofill   = $input->getInt('af') == '1';
$isAutosubmit = $input->getInt('as') == '1';

// NEW as of 2023-06-27
$uri     = $view->getURI(true);		// Get fully qualified (arg "true" is set) URI of this view, extract URI-vars for comparison
$curURI  = new URI($uri);
$referer = $view->getReferer(true);	// Get fully qualified (arg "true" is set) referer URI, extract URI-vars for comparison with expected vars (view=part&layout=item(_new))
$refURI  = new URI($referer);
$diff    = array_diff($curURI->getQuery(true), $refURI->getQuery(true));

/*
// @debug
if ($user->get('userID') == '102') :
	// echo '<pre><small class="text-bold">layout: </small>'          . print_r($layout, true) . '</pre>';
	// echo '<pre><small class="text-bold">return: </small>'          . print_r($return, true) . '</pre>';
	// echo '<pre><small class="text-bold">isAutotrack: </small>'     . print_r($isAutotrack, true) . '</pre>';
	// echo '<pre><small class="text-bold">isAutofill: </small>'      . print_r($isAutofill, true) . '</pre>';
	// echo '<pre><small class="text-bold">isAutosubmit: </small>'    . print_r($isAutosubmit, true) . '</pre>';
	// echo '<pre><small class="text-bold">Referer: </small>'         . print_r($referer, true) . '</pre>';
	// echo '<pre><small class="text-bold">requested URI: </small>'       . print_r($curURI->toString(), true) . '</pre>';
	// echo '<pre><small class="text-bold">requested URI query: </small>' . print_r($curURI->getQuery(true), true) . '</pre>';
	// echo '<pre><small class="text-bold">refURI: </small>'       . print_r($refURI->toString(), true) . '</pre>';
	// echo '<pre><small class="text-bold">refURI query: </small>' . print_r($refURI->getQuery(true), true) . '</pre>';
	// echo '<pre><small class="text-bold">refURI-view: </small>'  . print_r($refURI->getVar('view'), true) . '</pre>';
	// echo '<pre><small class="text-bold">URIs-diff: </small>'    . print_r($diff, true) . '</pre>';
	// echo '<pre><small class="text-bold">layout: </small>'       . print_r($layout, true) . '</pre>';
	// echo '<pre><small class="text-bold">task: </small>'         . print_r($task, true) . '</pre>';
	// echo '<pre><small class="text-bold">$pid exists in $diff? </small>' . print_r(array_key_exists('pid', $diff) ? 'YES' : 'NO', true) . '</pre>';
	// echo '<pre><small class="text-bold">GET: </small>'          . print_r($_GET, true) . '</pre>';
	// echo '<pre><small class="text-bold">POST: </small>'         . print_r($_POST, true) . '</pre>';
	// echo '<pre><small class="text-bold">SERVER: </small>'       . print_r($_SERVER, true) . '</pre>';
	// die;
endif;
*/
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
$canEdit     = true;
$canEditPart = true;
$canEditProc = true;	// TODO - consider user role(s)

// TODO - drop ' || $layout === edit_new' once the script is no longer buggy
$isEditPart  = (($layout === 'edit' || $layout === 'edit_new') && $ptid > 0 && $canEditPart);					// ADDED condition '$ptid > 0' on 2021-10-21
$isEditProc  = (($layout === 'edit' || $layout === 'edit_new') && $pid  > 0 && $canEditProc && $isEditPart);	// ADDED condition '$pid  > 0' on 2021-10-21
?>
<?php /* Process form data */
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
<?php /* Block attempt to activate 'edit' mode via URL manipulation. */
switch (true) :
	// In case AutoTrack is enabled, the view-name in the referer-URI must be 'parts', because with AutoTrack enabled a part code search directly opens the tracking-form.
	// Hence, when the referer is not the parts-view, direct access to view 'edit' is not allowed and must therefore be blocked.
	case (FALSE && $isAutotrack && $refURI->getVar('view') != 'parts') :
		/*
		// @debug
		if ($user->get('userID') == '102') :
			echo '<pre>Direct access check - CASE 1: ' . print_r(Text::translate('COM_FTK_ERROR_APPLICATION_DIRECT_ACCESS_IS_NOT_ALLOWED_TEXT', $lang), true) . '</pre>';
			die;
		endif;
		*/

		Messager::setMessage([
			'type' => 'error',
			'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DIRECT_ACCESS_IS_NOT_ALLOWED_TEXT', $lang), null)
		]);

		$redirect = new Uri($input->server->getUrl('HTTP_REFERER', '/index.php'));

		header('Location: ' . $redirect->toString());
		exit;

	/* When coming from view=item the process ID is added as a new URI-variable + the layout is changed from 'item' to 'edit'.
	 * On the previous page the process ID is no required information. Therefore, both information are expected in $diff.
	 *
	 *	/index.php?hl=de&view=part&layout=item&ptid=1			// layout=item must not contain the process ID
	 *	/index.php?hl=de&view=part&layout=edit&ptid=1&pid=1		// layout=edit must     contain the process ID
	 *
	 * So if these URI variables remain in $diff, it is very likely that
	 * the partial view "item" is not the referring page, which is not acceptable.
	 */
	case (!$isAutotrack && array_key_exists('view', $diff)) :
	case (!$isAutotrack && array_key_exists('ptid', $diff)) :
		/*
		// @debug
		if ($user->get('userID') == '102') :
			echo '<pre>Direct access check - CASE 2: ' . print_r(Text::translate('COM_FTK_ERROR_APPLICATION_DIRECT_ACCESS_IS_NOT_ALLOWED_TEXT', $lang), true) . '</pre>';
			die;
		endif;
		*/

		Messager::setMessage([
			'type' => 'error',
			'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DIRECT_ACCESS_IS_NOT_ALLOWED_TEXT', $lang), null)
		]);

		$redirect = new Uri($input->server->getUrl('HTTP_REFERER', '/index.php'));

		header('Location: ' . $redirect->toString());
		exit;

	/* The process ID must be available. If it is not the request is rejected and the user redirected back to where it came from.
	 */
	case (!array_key_exists('pid', $curURI->getQuery(true))) :
		/*
		// @debug
		if ($user->get('userID') == '102') :
			echo '<pre>Direct access check - CASE 3: ' . print_r(Text::translate('COM_FTK_ERROR_APPLICATION_PROCESS_TO_BE_TRACKED_NOT_DETECTED_TEXT', $lang), true) . '</pre>';
			die;
		endif;
		*/

		Messager::setMessage([
			'type' => 'error',
			'text' => Text::translate('COM_FTK_ERROR_APPLICATION_PROCESS_TO_BE_TRACKED_NOT_DETECTED_TEXT', $lang)
		]);

		$redirect = new Uri($input->server->getUrl('HTTP_REFERER', '/index.php'));

		header('Location: ' . $redirect->toString());
		exit;
endswitch;


/*if (empty($ptid) || empty($pid)) :	// TEMP disabled on 2021-09-22 to reduce access barriers while implementing "edit tracking" ... re-enabled on 2022-05-31 after bug reported by CS regarding post-tracking editing not possible
	Messager::setMessage([
		'type' => 'warning',
		'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DIRECT_ACCESS_IS_NOT_ALLOWED_TEXT', $lang), null)
	]);

	$redirect = new Uri($input->server->getUrl('HTTP_REFERER', $input->server->getUrl('REQUEST_URI')));
	$redirect->setVar('layout', 'item');

	header('Location: ' . $redirect->toString());
	exit;
endif;*/

/*// @debug
die('good2go');*/
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing part.
if (!is_a($item, 'Nematrack\Entity\Part') || (is_a($item, 'Nematrack\Entity\Part') && is_null($item->get('partID')))) :
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
$this->isApproval = $isApproval;
$this->isApproved = $isApproved;
$this->isSample   = $this->item->get('sample')   == '1';
?>
<?php /* Load more view data - required when not in print mode */ ?>
<?php
// Get hands on processes this user's organization is responsible for. It's required for access control.
$orgModel         = $this->view->get('model')->getInstance('organisation', ['language' => $this->language]);
$userOrganisation = $model->getInstance('organisation', ['language' => $lang])->getItem((int) $user->get('orgID'));
//$userOrgID        = $userOrganisation->get('orgID');
//$userOrgName      = $userOrganisation->get('name');
$userOrgProcesses     = (array) $userOrganisation->get('processes', []);
/*$userOrgAdmins    = $orgModel->getAdmins([
	$userOrganisation->getPrimaryKeyName() => $userOrganisation->get($userOrganisation->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);*/
/*$userOrgQualityManagers = $orgModel->getQualityManagers([
	$userOrganisation->getPrimaryKeyName() => $userOrganisation->get($userOrganisation->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);*/
$userOrgQualityResponsibles = $orgModel->getQualityResponsibles([
	$userOrganisation->getPrimaryKeyName() => $userOrganisation->get($userOrganisation->getPrimaryKeyName()),
	'filter' => ListModel::FILTER_ACTIVE
]);

/*// @debug
if ($user->get('userID') == '102') :
	echo '<pre>userOrgQualityManagers: ' . print_r($userOrgQualityManagers, true) . '</pre>';
	echo '<pre>userOrgQualityResponsibles: ' . print_r($userOrgQualityResponsibles, true) . '</pre>';
	die;
endif;*/

$processes     = $model->getInstance('processes', ['language' => $lang])->getList(['params' => true]);
// Extract abbreviations.
$abbreviations = array_column($processes, 'abbreviation', 'procID'); array_map('mb_strtolower', $abbreviations);

// Extract abbreviations belonging to SCF-processes.
// These processes must not be editable. Press-in data is sent to the TrackingTool by the Press-in machine.
// There was too much mess created by Jermaine and Manuel that made Tino decide on this preventive intervention.
$mntORscfProcs = array_filter($abbreviations, function($abbreviation) { return preg_match('/^(scf)\d{1,2}$/', $abbreviation); });

// Grant edit right for SCF-processes to privileged users to allow tracking of bad parts.
// Quality responsibles and Developers should be able to edit an automatic tracking.
if ($user->isQualityAssurance() || $user->isQualityManager() || $user->isProgrammer()) :
	$mntORscfProcs = [];
endif;

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
endif;

/*
// @debug
if ($user->get('userID') == '102') :
	echo '<pre><small class="text-bold">untrackedPids 0: </small>'  . print_r(json_encode($untrackedPids), true) . '</pre>';
endif;
*/

// Calculate first process to be overlaid to prevent editing.
$isProcEditable = ($pid === $nextPid);	// DiSABLED on 2021-09-22 to reduce access barriers while implementing "edit tracking"
										// RE-ENABLED on 2022-05-30 after bypassing untrackable processes via AT has been reported by JH
/*
// @debug
if ($user->get('userID') == '102') :
	echo '<pre><small class="text-bold">isProcEditable 1: </small>' . print_r($isProcEditable, true) . '</pre>';
	echo '<pre><small class="text-bold">untrackedPids 1: </small>'  . print_r(json_encode($untrackedPids), true) . '</pre>';
endif;
*/

if (false === $isProcEditable) :	// FIXME - why is $untrackedPids empty here?
	$isProcEditable = !in_array($pid, $itemProcessIDsUntracked)/* && $userIsOriginalAuthor || $userIsHighPrivileged*/;	// FIXME
endif;

/*
// @debug
if ($user->get('userID') == '102') :
	echo '<pre><small class="text-bold">isProcEditable 2: </small>' . print_r($isProcEditable, true) . '</pre>';
	echo '<pre><small class="text-bold">untrackedPids 2: </small>'  . print_r(json_encode($untrackedPids), true) . '</pre>';
endif;
*/

// ADDED On 2023-08-16 - the next 2 lines in combination with adding a new function to the part-model fix a bug where it was possible to override an existing tracking when AutoTrack is enabled.
$isProcAlreadyTracked = $model->getTrackingData($ptid, $pid);
$isProcAlreadyTracked = is_array($isProcAlreadyTracked) && count($isProcAlreadyTracked);

/*
// @debug
if ($user->get('userID') == '102') :
	echo '<pre>isProcAlreadyTracked: ' . print_r($isProcAlreadyTracked, true) . '</pre>';
	die;
endif;
*/

//$isProcEditable = $isProcEditable && ($isAutotrack && !$isProcAlreadyTracked);	// ADDED on 2023-08-16 to prevent overwriting an already tracked process when AutoTrack is active
$isProcEditable = $isAutotrack ? ($isProcEditable && !$isProcAlreadyTracked) : $isProcEditable;	// ADDED on 2023-08-16 to prevent overwriting an already tracked process when AutoTrack is active

/*
// @debug
if ($user->get('userID') == '102') :
	echo '<pre><small class="text-bold">isProcEditable 3: </small>' . print_r($isProcEditable, true) . '</pre>';
	echo '<pre><small class="text-bold">untrackedPids 3: </small>'  . print_r(json_encode($untrackedPids), true) . '</pre>';

	echo '<pre><small class="text-bold">isEditPart: </small>' . print_r($isEditPart, true) . '</pre>';
endif;
*/
?>
<?php /* If the process chain up to the process to be edited is incomplete prevent editing and return with user notification. */
if (!$isProcEditable) :	// TEMP disabled on 2021-09-22 to reduce access barriers while implementing "edit tracking" ... re-enabled on 2022-05-30 after bypassing untrackable processes via AT has been reported by JH
	if ($isProcAlreadyTracked) :
		// @debug
//		echo '<pre>' . print_r('Trap 1! Bitte Tino mit Screenshot + Teilecode informieren', true) . '</pre>';
//		die;

		$return = new Uri( $referer );

		$messageType = 'notice';

		$message = sprintf('%s %s',
			ucfirst(mb_strtolower(sprintf('%s %s', Text::translate('COM_FTK_LABEL_PART_TEXT', $lang), Text::translate('COM_FTK_HINT_SKIPPED_TEXT', $lang)))),
			Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_IS_ALREADY_TRACKED_TEXT', $lang)
		);
	else :
		// @debug
//		echo '<pre>' . print_r('Trap 2! Bitte Tino mit Screenshot + Teilecode informieren', true) . '</pre>';
//		die;

//		$return = new Uri( sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d&pid=%d', $lang, $this->item->get('partID'), $pid ) );
		$return = new Uri( sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d&pid=%d', $lang, $layout, $this->item->get('partID'), $pid ) );
		$return->setFragment('p-' . hash('MD5', $pid));

		$messageType = 'notice';

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
	endif;

	Messager::setMessage([
		'type' => $messageType,
		'text' => $message
	]);

//	header('Location: ' . UriHelper::osSafe( UriHelper::fixURL( preg_replace('/\blayout=edit\b/iu', 'layout=item', $input->server->getUrl('HTTP_REFERER', $input->server->getUrl('REQUEST_URI'))))));
//	header('Location: ' . UriHelper::osSafe( UriHelper::fixURL( preg_replace('/\blayout=edit\b/iu', 'layout=item', $return))));
	header('Location: ' . UriHelper::osSafe(UriHelper::fixURL($return->toString())));
	exit;
endif;
?>
<?php /* Load view data (continuation) */
// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$staticTechParams = (array) $model->getInstance('techparams', ['language' => $lang])->getStaticTechnicalParameters(true);

// Get error catalogue for this part's processes.
// TODO - move to \Nematrack\View\Part::prepareDocument()
$errors   = $model->getInstance('errors', ['language' => $lang])->getErrorsByLanguage(
	(new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($this->language)))->get('lngID'),
	$itemProcessIDs
);

//$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));	// DiSABLED on 2023-08-15 - no longer required at all
//$dateNow  = $now->format('Y-m-d');	// DiSABLED on 2023-08-15 - no longer required here. re-defined in line 855
//$timeNow  = $now->format('H:i:s');	// DiSABLED on 2023-08-15 - no longer required at all

$errNum   = null;
$errText  = null;
$errColor = null;





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

// Detect whether this part is a good part or bad part.
// This info is required for the badge.
$hasError     = false;
$errProcID    = null;		// will receive the process id to link to on click onto the quality classifier badge

// If part is bad, fetch errorous process' ID.
if ($this->item->isBad()) :
	array_filter($trackings, function($data, $procID) use(&$hasError, &$errProcID)
	{
		if ($hasError) :
			return;
		endif;

		$data  = (array) $data;

		$match = array_filter($data, function($entry, $paramID)
		{
			return ( ($paramID == Techparams::STATIC_TECHPARAM_ERROR) && ((int) $entry > 0) );

		}, ARRAY_FILTER_USE_BOTH);

		if ($match) :
			$hasError  = true;
			$errProcID = $procID;
		endif;

	}, ARRAY_FILTER_USE_BOTH);
endif;

// Get reference to measured data for each of these processes.
$measuredData = (array) $this->item->get('measuredData');

// Init tabindex
$tabindex = 0;
?>

<style>
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
	padding: 15px 0 5px 30px;
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
.timeline .event:first-of-type:after {

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
fieldset.part-process-tracking:focus {
    background: rgba(0, 123, 255, 0.1);
    box-shadow: 0 0 10px 10px rgba(0, 123, 255, 0.1);
}

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



}
.untracked.process > fieldset.part-process-tracking:before {
	display: block;
	z-index: 1;
}
.untracked.process > fieldset.part-process-tracking:before {
	content: "<?php echo Text::translate('COM_FTK_HINT_PROCESS_TRACKING_ONLY_AFTER_PREV_PROCESSES_TRACKED_TEXT', $lang); ?>";
}

.form-control.bg-success {
	background-color: rgba(  1, 163, 28, 0.2) !important;
}
.form-control.bg-warning {
	background-color: rgba(255, 215, 0, 0.3) !important;
}
</style>

<article>
	<header>
		<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d%s',
				$lang,
				str_ireplace('edit', 'item', $layout),
				$this->item->get('partID'),
				($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '')
		   ))); ?>"
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

	<?php // @debug ?>
	<?php if ($user->isProgrammer() || $user->get('userID') == '102') : ?>
	<pre class="text-bold">layout: <span class="text-danger"><?php echo $layout; ?></span></pre>
	<?php endif; ?>

	<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d', $lang, $this->item->get('partID') ))); ?>"
		  method="post"
		  name="editPartForm"
		  <?php /* I M P O R T A N T   N O T E :
				 *
				 *	 At this point it must be clear that if AutoTrack is activated, the process to be tracked must not have been tracked yet,
				 *	 otherwise the previous tracking will be overwritten unintentionally ! ! !
				 */
		  ?>
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
		<pre class="text-bold">layout: <span class="text-danger"><?php echo $layout; ?>_new</span></pre>
		<?php endif; ?>

		<?php /*   P R O C E S S   T R E E   */ ?>
		<section id="processTree">
			<h3 class="h5 d-inline-block mt-lg-4 mb-lg-4 mr-3"><?php
				echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $lang);
			?></h3>

			<?php if (!$isEditPart) : ?>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d%s',
					$lang,
					str_ireplace('edit', 'item', $layout),
					$this->item->get('partID'),
					($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '')
			   ))); ?>"
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
				<?php // Iterate over the item process list and load and render process by process ?>
				<?php foreach ($itemProcesses as $artProcID => $artProc) : ?><?php
					// Load process definition data into a process entity object for reliable data access.
					$process = (new Process(['language' => $lang]))->bind( ArrayHelper::getValue($processes, $artProcID, []) );

					/*
					// @debug
					if ($user->get('userID') == '102') :
						echo '<pre><small class="text-bold">artProcID: </small>'      . print_r($artProcID, true) . '</pre>';
						echo '<pre><small class="text-bold">isProcEditable: </small>' . print_r($isProcEditable, true) . '</pre>';
						// echo '<pre>artProc: ' . print_r($artProc, true) . '</pre>';
						// echo '<pre>process-entity: ' . print_r($process, true) . '</pre>';
						// die;
					endif;
					*/

					// Tracking is only allowed for authorised organizations or users with higher privileges.
					// Calculate whether this process is accessible by the user's organization.
					$isOrgProcess  = in_array($artProcID, $userOrgProcesses);

					// Get reference to its technical paramaters (as defined in the TT master data section "processes").
					$techParams    = (array) $process->get('tech_params');
					$hasTechParams = count($techParams) > 0;

					// Tracking is only allowed for authorised organizations or users with higher privileges.
					// Is this process is managed by the user's organization?
					$process->__set('isManagedByUserOrganisation', in_array($artProcID, $userOrgProcesses));

					// Is this process a press-in process (MNT/SCF) ?
					$process->__set('isPressinProcess', array_key_exists($artProcID, $mntORscfProcs));

					// A process must not be directly editable if it is tracked automatically by a machine via API.
					$process->__set('isTrackedByMachine', $process->__get('isPressinProcess'));

					/*
					// @debug
					if ($user->get('userID') == '102') :
						echo '<pre><small class="text-bold">untrackedPids: </small>' . print_r(json_encode($untrackedPids), true) . '</pre>';
					endif;
					*/

					// A process can be tracked only if the requested process ID (pid in URI) was not already tracked (is in the list of untracked process IDs).
					$process->__set('isTrackable', !in_array($artProcID, $untrackedPids));


					$process->__set('isTracked', in_array($artProcID, $trackedPids));

					// An expired editing time window requires every new editing request to be approved by a quality responsible.
//					$process->__set('isApproved', (is_array($artProc->get('approval')) && count($artProc->get('approval'))));
					$process->__set('isApproved', $this->isApproval && $this->isApproved);

					// If any of the item's processes is BAD, block follow-up processes from being tracked.
					// A process can only be edited when it was already tracked. Hence, its ID must come prior $nextPid.
					if ($itemHasBadProcess) :
						$process->__set('isTrackable', ($process->__get('isTrackable') && ($artProcID != $nextPid)));
					endif;

					// Get hands on the tracked data for this process if there is any.
					$trackedProcess = new Registry(ArrayHelper::getValue($trackings, $artProcID));

					// Load master data for this process if it is already tracked.
					$process->__set('tracking', new Registry([
						'organisation' => $trackedProcess->get(Techparams::STATIC_TECHPARAM_ORGANISATION),
						'operator'     => $trackedProcess->get(Techparams::STATIC_TECHPARAM_OPERATOR),
						'date'         => $trackedProcess->get(Techparams::STATIC_TECHPARAM_DATE),
						'time'         => $trackedProcess->get(Techparams::STATIC_TECHPARAM_TIME),
						'status'       => $trackedProcess->get(Techparams::STATIC_TECHPARAM_ERROR),
						// TODO - add tracked technical parameters
//						'measuredData' => ArrayHelper::getValue($measurementDefinitions, $artProcID, [], 'ARRAY')	// FIXME - $measurementDefinitions is undefined
					]));

					/* Check whether a process has actually already been tracked and access should therefore be restricted or completely blocked.
					 * A process is classified as "blocked" if the tracking data contains:
					 *	- Operator name
					 *	- Tracking date
					 *	- Tracking time
					 *	- Tracking status + (error) status
					 */
					$process->__set('isTracked', (
						$process->__get('isTracked')
						&& (
							!empty($process->__get('tracking')->get('operator')) &&
							!empty($process->__get('tracking')->get('date'))     &&
							!empty($process->__get('tracking')->get('time'))     &&
						is_numeric($process->__get('tracking')->get('status'))
						)
						/* Prevent manual tracking of press-in process(es) (NOTE: identifiable via process abbreviation MNTx/SCFx)
						 * to prevent the tracking data from being messed like happened in the past.
						 * For this purpose, the process ID is looked up in the list of press-in process IDs.
						 * If it is found in it, it is an autmatically tracked process and the process is classified as "blocked".
						 */
						|| $process->__get('isTrackedByMachine')
					));

					// Can the data already tracked still be edited?
					// Calculation of the expiry date in relation to the creation date of the tracking if there is any.
					$dateNow             = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
					$dateTrackingCreated = $process->__get('isTracked')
												// If the process is already tracked, the creation date/time are the tracked date/time.
												? date_create(sprintf('%s %s',
													$process->__get('tracking')->get('date'),
													$process->__get('tracking')->get('time')
												))
												: null;	// FIXME - test whether the null can be replaced with $dateNow

					$dateTrackingExpires = is_a($dateTrackingCreated, 'DateTime')
												// If the process is already tracked, the initial value for the expiration data/time is the creation date/time.
												// This value is going to be fixed in relation to a defined time window in the next step.
												? clone($dateTrackingCreated)
												: null;	// FIXME - test whether the null can be replace with $dateTrackingCreated

					// Add expiration time window which is defined in the application's Defines.
					// Add only if the operation has never been released. Once the processing time window has expired, it can no longer be extended.
					// A processing approval is valid only once. After the edited data has been saved, it must be released again.
					if (is_a($dateTrackingExpires, 'DateTime')) :
						$dateTrackingExpires->add(DateInterval::createFromDateString(FTKRULE_EDIT_TRACKING_EXPIRES));	// MODDED on 2023-06-23 - consolidates preceding 2 commands
					endif;

					// Init dataset for editability time window.
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
						'isEditable' => 0,
						'isExpired'  => 0
					]));

					/* If there is remaining time for editing, we have to update information.
					 * A value >  0 for 'invert' means "There is still some time left for editing.", where as
					 * a value <= 0 means "Editing time has elapsed."
					 *
					 * @see https://www.php.net/manual/de/datetime.diff.php        for how to compare dates
					 * @see https://www.php.net/manual/de/class.dateinterval.php   for the meaning of DateTime property 'invert'
					 */
					$diff = null;

					if (is_a($dateTrackingExpires, 'DateTime')) :
						$diff = $dateNow->diff($dateTrackingExpires);
					endif;

					// A date interval indicates a previous tracking for this process.
					if (is_a($diff, 'DateInterval')) :
						// There is remaining edit time, update initial values.
						if ($diff->invert == 0) :
							$process->__get('editability')->set('remaining.days',          $diff->d);
							$process->__get('editability')->set('remaining.hours',         $diff->h);
							$process->__get('editability')->set('remaining.minutes',       $diff->i);
							$process->__get('editability')->set('remaining.seconds',       $diff->s);
							$process->__get('editability')->set('remaining.microseconds',  $diff->f);
							// In principle, the editability is to be set to "true" here. However, the responsibility of the organization must also be taken into account.
							$process->__get('editability')->set('isEditable',             ($process->__get('isTracked') && $process->__get('isManagedByUserOrganisation')));
							// The editability has expired as soon as editing is no longer possible.
							$process->__get('editability')->set('isExpired',              !$process->__get('editability')->get('isEditable'));
						else :
							$process->__get('editability')->set('isExpired',  true);
							$process->__get('editability')->set('isEditable', !$process->__get('editability')->get('isExpired') && $process->__get('isManagedByUserOrganisation'));
						endif;
					// No date interval indicates missing tracking creation date and/or tracking expiry date.
					else :
						$process->__get('editability')->set('isEditable', ($process->__get('isTracked') && $process->__get('isManagedByUserOrganisation')));
					endif;

					/*// @debug
					// If editability is expired only higher privileged users like Quality Officer can edit or reset a tracking.
					if ($process->__get('isTracked') && $process->__get('editability')->get('isExpired')) :
						// @debug
						if ($user->isProgrammer()) :
							echo '<pre><em>' . print_r('The final chance for editability is the release by a quality manager.', true) . '</em></pre>';
						endif;
					else :
						// @debug
						if ($user->isProgrammer()) :
							echo '<pre><em>' . print_r('The worker can track himself/herself.', true) . '</em></pre>';
						endif;
					endif;*/

					/* Determination of the current user's basic right to track. */

					// Init more vars.
					$userIsAuthor = $userCanTrack = $userCanEdit = $userCanReleaseTracking = null;

					/* A user is the original author of a tracking if:
					 *	- its full name matches the full operator name in the existing tracking data
					 */
					if ($process->__get('isTracked')) :
						$userIsAuthor = $process->__get('tracking')->get('operator') == $user->get('fullname');
					endif;

					/* A user can track a process, if:
					 *	- the user access is not blocked
					 *	- the process is open for tracking (NOTE: some processes are automatically tracked like press-in processes and therefore not manually trackable)
					 *	- the process is managed by the user's organization
					 */
					$userCanTrack = !$user->get('blocked');
					$userCanTrack = ($userCanTrack &&  $process->__get('isManagedByUserOrganisation'));
					$userCanTrack = ($userCanTrack &&  $user->getFlags() >= User::ROLE_WORKER);
//					$userCanTrack = ($userCanTrack && !$process->__get('isTracked'));	// MODDED on 2023-06-20 - "$userCanTrack && " prepended

					/* A user can edit a previously tracked process, if:
					 *	- the user access is not blocked
					 *	- the user is the original author or
					 *	- the editing is approved by a quality responsible (e. g. after regular editing time is expired)
					 */
					if ($process->__get('isTracked')) :
						$userCanEdit = ($userCanTrack && !$process->__get('editability')->get('expired') && $userIsAuthor);
					endif;

					// BEGiN: different to edit_new
						// stuff regarding userCanReleaseTracking
					// END: different to edit_new

					/* Operator names must be visible to selected members only (HINT: data privacy).
					 * Only FRÖTEK and NEMATECH members are allowed to see such information + other members if
					 * they belong to the organization responsible for this process.
					 */
					$displayOperatorName = UserHelper::isFroetekOrNematechMember($user);
					$displayOperatorName = $displayOperatorName ?: in_array($user->get('orgID'), $process->get('organisations'));

					// Prepare process drawing pic + PDF for display and download.
					$drawing    = new Registry($artProc->get('drawing'));
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
					$errorList = array_slice($errors, 0, ($j + 1), true);

					// Calculate HTML attribute for blocked processes.
					$htmlStatusAttr = (!$process->__get('isTrackable')) ? ' disabled' : '';

					//->BEGiN: different to item_new
					$isCurrentlyEdited = ((int) $process->get('procID') === (int) $pid);

					// Get hands on the tracked data for this specific process.
					$tracking = ArrayHelper::getValue($trackings, $artProcID);
					$tracking = is_object($tracking) && !is_a($tracking, 'Joomla\Registry\Registry') ? new Registry($tracking) : new Registry;
					$tracking2 = $process->__get('tracking');
					//<-END: different to item_new

					/*
					// @debug
					if ($user->get('userID') == '102') :
						echo '<pre>process-entity: '            . print_r($process, true) . '</pre>';
						echo '<pre>all trackings: '             . print_r($trackings, true) . '</pre>';
						echo '<pre>tracking for process #'      . $artProcID. ': ' . print_r($tracking, true) . '</pre>';
						echo '<pre>process isCurrentlyEdited: ' . print_r($isCurrentlyEdited ? 'YES' : 'NO', true) . '</pre>';
						echo '<pre>process isApproved: '        . print_r($process->__get('isApproved'), true) . '</pre>';
						die;
					endif;
					*/
				?>

				<div class="<?php echo trim(sprintf('list-item dynamic-content event process position-relative %s',
						($isEditPart && $pid == $artProcID ? 'tracking' : ($process->__get('isTracked') ? 'tracked' : 'untracked'))
					 )); ?>"
					 id="p-<?php echo hash('CRC32', $artProcID); ?>"
					 data-proc-id="<?php echo (int) $artProcID; ?>"
				>
					<fieldset class="<?php echo trim(sprintf('part-process-tracking position-relative %s %s',
								((!$process->__get('isTrackable') || $this->isApproval)  ? 'disabled'     : ''),	// CHANGED on 2023-07-20 - condition extended to take approval into account
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

<?php if ($process->__get('isTrackable') && $pid == $artProcID) : // Some controls must only be available to the currently edited process ?>
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
						if (is_a($helpFile, '\Nematrack\Entity\Document') && $helpFile->get('path')) :

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
						<?php if (in_array($artProcID, $userOrgProcesses)) : // Editing is permitted to responsible organization(s) and privileged user(s) only ?>
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
						<?php if (in_array($artProcID, $userOrgProcesses)) : // Editing is permitted to responsible organization(s) and privileged user(s) only ?>
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
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=%s&ptid=%d&pid=%d%s',
								$lang,
								str_ireplace('edit', 'item', $layout),
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
									&$lang,
									&$pid,
									&$process,
									&$processes,
									&$processNo,
									&$staticTechParams,
									&$tabindex,
									&$tracking,		// holds tracking data indexednumerically numerically
									&$tracking2,	// holds tracking data indexed by fieldnames
									&$trackings,
									&$user,
									&$userOrganisation,
									&$userOrgProcesses
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
										: (new Registry(ArrayHelper::getValue($trackings, $process->get('procID'), [])))->get($id) );
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

									<?php echo ($canEditPart && (in_array($artProcID, $userOrgProcesses)) && $artProcID == $pid) ? '' : ' disabled readonly'; ?>
									value="<?php echo html_entity_decode($tracking->get($id, '')); ?>"

								<?php // Field is one of the staticTechParam fields ?>
								<?php else : ?>

									<?php if ($isCurrentlyEdited && $tparam->get('fieldname') !== 'annotation') :		// Allow user input to the annotation-field ?>
										<?php echo 'readonly'; ?>
									<?php elseif (!$isCurrentlyEdited) : ?>
										<?php echo 'readonly'; ?>
										<?php echo 'disabled';																// the 'disabled' attribute prevents field data from being submitted and stored ?>
									<?php elseif ($isCurrentlyEdited) : ?>
										<?php echo (!in_array($artProcID, $userOrgProcesses) ? 'readonly disabled' : '');	// refers to annotation-field ?>
									<?php endif; ?>

									<?php if (!$isCurrentlyEdited) : ?>
									value="<?php echo html_entity_decode($tracking->get($id, '')); ?>"
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
//											echo $process->__get('isApproved') ? $tracking2->get('date') : $dateNow->format('Y-m-d');	// if this is an approved editing then the previous date must not be modified to prevent an unintended editing time frame extension
											echo $this->isApproval ? $tracking2->get('date') : $dateNow->format('Y-m-d');	// if this is an approved editing then the previous date must not be modified to prevent an unintended editing time frame extension
										break;

										case 'time' :
//											echo $process->__get('isApproved') ? $tracking2->get('time') : $dateNow->format('H:i:s');	// if this is an approved editing then the previous date must not be modified to prevent an unintended editing time frame extension
											echo $this->isApproval ? $tracking2->get('time') : $dateNow->format('H:i:s');	// if this is an approved editing then the previous date must not be modified to prevent an unintended editing time frame extension
										break;

										case 'drawing' :
											// Autofill currently active process drawing number only if none has been stored previously
											if (is_object($tracking)) :
												echo !empty($tracking->get($id))
													? $tracking->get($id)
													: html_entity_decode($processNo);
											else :
												echo  html_entity_decode($processNo);
											endif;
										break;

										case 'annotation' :
											echo html_entity_decode($tracking->get($id, ''));
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
					<?php if ($this->isApproval && $this->isApproved) : ?>
					<input type="hidden" name="approved" value="<?php echo ($this->isApproval && $this->isApproved); ?>" /><?php // ADDED on 2023-07-20 ?>
					<?php endif; ?>
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
unset($trackings);
unset($tracking);
unset($staticTechParams);
unset($user);
unset($view);
