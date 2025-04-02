<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Model;
use Nematrack\Text;

/* // Include PEAR lib to have class 'Date_Holidays' available to detect whether a given date is a holiday.
require_once implode(DIRECTORY_SEPARATOR, ['Date', 'Holidays.php']);

// Detect path to included script.
// Code borrowed from: https://stackoverflow.com/a/2420104
$reflector  = new \ReflectionClass('Date_Holidays');
$scriptFile = $reflector->getFileName();
$scriptPath = dirname($reflector->getFileName());
$statesPath = implode(DIRECTORY_SEPARATOR, [dirname($reflector->getFileName()), 'Holidays', 'Filter', 'Germany']);

$files = @\scandir($statesPath, SCANDIR_SORT_NONE);							// Code borrowed with some modification from: {@link https://www.php.net/manual/de/function.rmdir.php#110489}
$files = (is_array($files) ? $files : []);									// if $absPath is empty, then the return value of {@link \scandir()} will be 'false'. For further processing is must be an array.
$files = array_diff($files, ['.','..']);									// exclude these subdirectories from being deleted

if (!empty($files)) :
	foreach ($files as $file) :
		require_once $statesPath . DIRECTORY_SEPARATOR . $file;
	endforeach;
endif;

$germany = Date_Holidays::factory('Germany', 2020, 'de_DE');
$germany = Date_Holidays::factory('Germany', 2020, 'en_GB');
// $germany = Date_Holidays::factory('Germany', 2020, 'fr_FR');
// $germany = Date_Holidays::factory('Germany', 2020, 'hu_HU');

if (Date_Holidays::isError($germany)) :
    die('Date_Holidays factory was unable to produce driver-object');
endif;

$easter = $germany->getHoliday('easter', 'de_DE');
$easter = $germany->getHoliday('easter', 'en_GB');
*/
/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$proID    = $input->getInt('proid');	// will in fact be empty, but populated when the project numbers dropdown list is created
$task     = $input->post->getCmd('task') ?? ($input->getCmd('task') ?? null);
$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);
?>
<?php /* Access check */
$formData = null;

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

$userID = $user->get('userID');

// Get matrix configuration from user profile.
$userProfile  = new Registry(UserHelper::getProfile($user));
$localeConfig = $userProfile->extract('user.locale');
$localeConfig = (is_a($localeConfig, 'Joomla\Registry\Registry') ? $localeConfig : new Registry);
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		case 'unbookedPartsListFilterExport' :
			$view->saveUnbookedPartsListFilter();
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
$projectNum     = $input->getCmd('project')  ?? null;
$qualityType    = $input->getWord('quality') ?? null;

$dateFrom       = null;
$dateTo         = null;
$dateObject     = new DateTime('NOW',   new DateTimeZone($localeConfig->get('timezone', FTKRULE_TIMEZONE)));
$dateInterval   = $userProfile->get('parts.booked.retrospective.limit', '5');
$dateTo         = $input->getCmd('to') ?? $dateObject->format('Y-m-d');
$dateTo         = new DateTime($dateTo, new DateTimeZone($localeConfig->get('timezone', FTKRULE_TIMEZONE)));
$dateTo         = $dateTo->format($localeConfig->get('date'));

$articlesModel  = $model->getInstance('articles',  ['language' => $lang]);
$articleModel   = $model->getInstance('article',   ['language' => $lang]);
$partsModel     = $model->getInstance('parts',     ['language' => $lang]);
$processesModel = $model->getInstance('processes', ['language' => $lang]);
$projectsModel  = $model->getInstance('projects',  ['language' => $lang]);

$allArticles    = [];
$allProcesses   = [];
$allProjects    = $projectsModel->getList();
$allUnbookedCnt = 0;

if (!empty($projectNum) && !empty($qualityType)) :
	$dateObject     = new DateTime('NOW', new DateTimeZone($localeConfig->get('timezone', FTKRULE_TIMEZONE)));
	$dateInterval   = $userProfile->get('parts.unbooked.retrospective.limit', '5');
	$dateTo         = $input->get->getCmd('to') ?? $dateObject->format('Y-m-d');
	$dateTo         = new DateTime($dateTo, new DateTimeZone($localeConfig->get('timezone', FTKRULE_TIMEZONE)));
	$dateFrom       = new DateTime($dateTo->format('Y-m-d'), new DateTimeZone($localeConfig->get('timezone', FTKRULE_TIMEZONE)));
	$dateFrom->sub(new DateInterval('P' . $dateInterval . 'D'));
	$dateDiff       = $dateTo->diff($dateFrom)->days;	// Should equal $dateInterval
	$dateTo         = $dateTo->format($localeConfig->get('date'));
	$dateFrom       = $dateFrom->format($localeConfig->get('date'));

	$allArticles    = $articlesModel->getArticlesByProjectNumber($projectNum);
	$allArticlesGrouped = [];

	foreach ($allArticles as $artID => $article) :
		$article = new Registry($article);

		// Add new property 'processIDS' to hold an article's processes to be loaded in the next step and flip value of property 'processes'.
		// TODO - change name of property in model from 'processes' to 'processIDS' and fix access to it throughout the code base!
		if (!$article->def('processIDs')) :
			$article->set('processIDs', $article->get('processes'));
		endif;

		$articleProcesses = $articleModel->getArticleProcesses($artID);

		// Inject all article processes into the article object (required for table creation below).
		$tmp = [];

		array_filter($articleProcesses, function ($articleProcess, $procID) use (&$tmp)
		{
			$tmp[$articleProcess->get('drawingnumber')] = $articleProcess->toArray();

		}, ARRAY_FILTER_USE_BOTH);

		$article->set('processes', $tmp);

		// Free memory.
		unset($tmp);

		// Get name of the project this article belongs to.
		$project = $article->get('project');

		// If this project collection doesn't exist yet, create it.
		if (!array_key_exists($project, $allArticlesGrouped)) :
			$allArticlesGrouped[$project] = [];
		endif;

		// Add this article to its corresponding project collection.
		array_push($allArticlesGrouped[$project], $article);
	endforeach;

	$allArticles  = $allArticlesGrouped;
	$allProcesses = $processesModel->getList();
endif;	// !empty($projectNum) && !empty($qualityType)
?>
<?php /* Pre-process view data */
if (!empty($projectNum) && !empty($qualityType)) :
	/* INFO:
	 *
	 * $allArticles is an associative array indexed by project numbers with every article associated to the project it relates to.
	 * Every article in $allArticles is an instance of Joomla\Registry\Registry with numerical index.
	 * Array (
	 *	[1EJ] => Array (
	 *		[0] => Joomla\Registry\Registry Object (),
	 *		...
	 *		[n] => Joomla\Registry\Registry Object ()
	 *	),
	 *	...
	 *	[N8M] => Array (
	 *		[0] => Joomla\Registry\Registry Object (),
	 *		...
	 *		[n] => Joomla\Registry\Registry Object ()
	 *	)
	 * )
	 */
	array_walk($allArticles, function (&$articles) use (&$allUnbookedCnt, &$allProcesses, &$partsModel, &$projectNum, &$qualityType, &$dateFrom, &$dateTo) {
		/* INFO:
		 *
		 * $articles is a numerically indexed array of of Joomla\Registry\Registry objects.
		 */
		$articles = array_filter((array) $articles, function ($article) use (&$allUnbookedCnt, &$allProcesses, &$partsModel, &$projectNum, &$qualityType, &$dateFrom, &$dateTo) {
			// Skip this article, if it belongs to any other project than the selected one.
			if (mb_strtolower($projectNum) !== mb_strtolower($article->get('project'))) :
				return false;
			endif;

			// Get reference to this article's processes property to fetch additional data.
			$articleProcesses      = array_filter((array) $article->get('processes'));
			$articleProcessesCount = count($articleProcesses);

			/* INFO:
			 *
			 * $articleProcesses is an associative array indexed by process numbers.
			 * Every article process is an associative array.
			 * Array (
			 *	[EDR.1EJ.CB.00100.090] => Array (
			 *		[artID] => 1,
			 *		[procID] => 123,
			 *		[drawingnumber] => EDR.1EJ.CB.00100.090,
			 *		[step] => 90,
			 *		[drawing] => Array (
			 *			...
			 *		)
			 *	),
			 *	...
			 *	[EDR.1EJ.CB.00100.080] => Array (
			 *		[artID] => 1,
			 *		[procID] => 456,
			 *		[drawingnumber] => EDR.1EJ.CB.00100.080,
			 *		[step] => 80,
			 *		[drawing] => Array (
			 *			...
			 *		)
			 *	),
			 * )
			 */
			if ($articleProcessesCount) :
				// Fetch   u n b o o k e d   parts.
				array_walk($articleProcesses, function (&$articleProcess) use (&$allUnbookedCnt, &$article, &$allProcesses, &$partsModel, &$qualityType) {
					$procID   = ArrayHelper::getValue($articleProcess, 'procID');
					$process  = ArrayHelper::getValue($allProcesses, $procID);

					$unbooked = $partsModel->getPartsUnbooked($article->get('artID'), $qualityType, [ArrayHelper::getValue($process, 'procID')]);	// return value will be numeric
					$tmpCount = count($unbooked);

					if ($tmpCount > 0) :
						$articleProcess['unbooked'] = $tmpCount;
					else :
						$articleProcess['unbooked'] = null;
					endif;

					// Increment total count.
					$allUnbookedCnt += $tmpCount;
				});

				// Drop null values.
				$articleProcesses = array_filter($articleProcesses, function ($articleProcess, $processNumber) {
					$booked   = ArrayHelper::getValue($articleProcess, 'booked', null);
					$unbooked = ArrayHelper::getValue($articleProcess, 'unbooked', null);

					return (array_key_exists('unbooked', $articleProcess) && !is_null($unbooked));

				}, ARRAY_FILTER_USE_BOTH);

				// Update count.
				$articleProcessesCount = count($articleProcesses);

				// Fetch   b o o k e d   parts.
				array_walk($articleProcesses, function (&$articleProcess) use (&$article, &$allProcesses, &$partsModel, &$qualityType) {
					$procID   = ArrayHelper::getValue($articleProcess, 'procID');
					$process  = ArrayHelper::getValue($allProcesses, $procID);

					$booked   = $partsModel->getPartsBooked($article->get('artID'), $qualityType, [ArrayHelper::getValue($process, 'procID')]);	// return value will be numeric
					$tmpCount = count($booked);

					if (count($booked)) :
						$articleProcess['booked'] = $booked;
					else :
						$articleProcess['booked'] = null;
					endif;
				});

				// Replace article property with updated object.
				$article->set('processes', ($articleProcessesCount > 0 ? $articleProcesses : []));
			endif;

			// If this article has processes keep it (return true) otherwise skip it (return false).
			return ($articleProcessesCount > 0 ? true : false);
		});

		// If this project has articles keep it (return true) otherwise skip it (return false).
		$articles = count($articles) ? $articles : null;
	});

	// Drop empty projects.
	$allArticles = array_filter($allArticles);

	// Prepare dates interval for table columns.
	$dateFromDay = (new \DateTime($dateTo, new \DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d');
	$bookDates   = [$dateFromDay];
	$skipped     = 0;

	[$year, $month, $day] = explode('-', $dateFromDay);

	$dateObject->setDate($year, $month, $day);

	for ($x = 1; $x <= $dateInterval; $x += 1) :
		$dateObject->sub(new DateInterval('P1D'));

		// Ignore weekends.
		// FIXME - ignore holidays
		/* if (in_array($dateObject->format('D'), ['Sun'])) :
			$skipped += 1;

			continue;
		endif; */

		array_push($bookDates, $dateObject->format('Y-m-d'));
	endfor;

	for ($x = 0; $x < $skipped; $x += 1) :
		$dateObject->sub(new DateInterval('P1D'));

		// Ignore weekends.
		// FIXME - ignore holidays
		/* if (in_array($dateObject->format('D'), ['Sun'])) :
			$skipped += 1;

			continue;
		endif; */

		array_push($bookDates, $dateObject->format('Y-m-d'));
	endfor;
endif;	// !empty($projectNum) && !empty($qualityType)

// Prepare dates interval for table columns.
$now   = new \DateTime('NOW', new \DateTimeZone(FTKRULE_TIMEZONE));
$today = $now->format('Y-m-d');

// Init tabindex
$tabindex = 0;

if (!empty($projectNum) && !empty($qualityType)) :
	$allProcIDs = array_keys($allProcesses);
	$userConfig = $userProfile->get('parts.unbooked.filter.processes.' . mb_strtoupper($projectNum), []);
	$userConfig = array_map('intval', $userConfig);

	// If there is no default process filter config, store it initially.
	if (empty($userConfig)) :
		$stored = $model->getInstance('user', ['language' => $lang])->updateProfile(
			$userID, [
				'uid' => $userID,
				'profile' => [
					'parts' => [
						'unbooked' => [
							'filter' => [
								'processes' => [
									$projectNum => array_map('strval', $allProcIDs)
								]
							]
						]
					]
				]
			]
		);

		// Update view data (reload user profile data).
		$userProfile  = new Registry(UserHelper::getProfile($user));

		$userConfig = $userProfile->get('parts.unbooked.filter.processes.' . mb_strtoupper($projectNum), []);
		$userConfig = array_map('intval', $userConfig);
	endif;

	$selProcIDs = $input->get('pids', []);
	$selProcIDs = (empty($selProcIDs) ? $userConfig : $selProcIDs);
	$selProcIDs = array_map('intval', $selProcIDs);
	$selProcIDs = array_values(array_filter($selProcIDs));

	$diffAB      = array_values(array_diff($allProcIDs, $selProcIDs));

	$cbsToHide  = $diffAB;
endif;
?>

<style>
.table td {
	border: 0;
}

.master-toggle {
	background-color: #d2dbe3!important;
	border-color: #d2dbe3!important;
	color: #212529!important;
}
.master-toggle:hover,
.master-toggle:focus {
	background-color: #b9c2ca!important;
	border-color: #9fa8b0!important;
	box-shadow: rgba(197, 206, 214, .5)
}
.master-toggle:active,
.master-toggle.active {
	background-color: #acb5bd!important;
}

.process-filter > label {
	text-transform: uppercase;
}
</style>

<div class="form-horizontal position-relative">
	<div class="row">
		<div class="col col-lg-1">
			<a href="javascript:void(0)"
			   role="button"
			   class="btn btn-link"
			   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
			   style="vertical-align:super; color:inherit!important"
			   onclick="window.history.back(-1)"
			>
				<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?></span>
			</a>
		</div>
		<div class="col col-lg-7 pl-0 ml-0">
			<h1 class="h4 d-inline-block mb-0 pb-0 mr-2" style="line-height:1.4"><?php
				if (empty($projectNum) && empty($qualityType)) :
					echo Text::translate('COM_FTK_HEADING_BOOK_PARTS_LABEL', $lang);
				elseif (!empty($projectNum) && empty($qualityType)) :
					echo sprintf('%s %s &ndash; %s',
						Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang),
						$projectNum,
						Text::translate('COM_FTK_HEADING_BOOK_PARTS_LABEL', $lang)
					);
				else :
					echo sprintf('%s %s:%s<small>%d %s</small>',
						Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang),
						$projectNum,
						str_repeat('&nbsp;', 3),
						$allUnbookedCnt,
						// Text::translate(str_ireplace('__', '_', 'COM_FTK_HEADING_' . mb_strtoupper($qualityType) . '_PARTS_NOT_BOOKED_TEXT'), $lang)
						Text::translate('COM_FTK_HEADING_PARTS_NOT_BOOKED_TEXT', $lang)
					);
				endif;
			?></h1>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=booked&project=%s&quality=%s', $lang, $view->get('name'), $projectNum, $qualityType ))); ?>"
			   role="button"
			   class="btn btn-link"
			   data-toggle="tooltip"
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_BOOKED_PARTS_TEXT', $lang); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_BOOKED_PARTS_TEXT', $lang); ?>"
			   style="vertical-align:baseline; color:inherit!important; text-decoration:none!important"
			>
				<i class="fas fa-list"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TITLE_BOOKED_PARTS_TEXT', $lang); ?></span>
			</a>
		</div>
		<div class="col col-lg-4">
			<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=%s', $lang, $view->get('name'), $view->get('layout') ))); ?>"
				  method="get"
				  name="partsForm"
				  class="form filterForm partsForm"
				  id="partsForm"
				  data-submit=""
			>
				<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
				<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
				<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

				<div class="form-row">
					<div class="form-group mb-lg-0 col-md-6 Xoffset-lg-2">
						<label for="project" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang);
						?>:&nbsp;&ast;</label>
						<div>
							<select name="project"
									class="form-control position-relative auto-submit"
									id="inputProject"
									form="partsForm"
									required
									data-lang="<?php echo $lang; ?>"
									data-target="#unbookedPartsList"
									data-rule-required="true"
									data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $lang); ?>"
									tabindex="<?php echo ++$tabindex; ?>"
									autofocus
							>
								<option value="">&ndash; <?php echo Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang); ?> &ndash;</option>
							<?php array_walk($allProjects, function ($project/* , $proID */) use (&$proID, &$projectNum) { ?>
								<?php $project = new Registry($project); $number = $project->get('number'); ?>
								<option value="<?php echo $number; ?>"<?php echo ($number == $projectNum ? ' selected' : ''); ?>><?php
									echo html_entity_decode($number);
								?></option>
								<?php // Populate view property '$proID' while iterating over all projects. This saves us from a separate db-query. ?>
								<?php if ($number == $projectNum) : ?>
									<?php $proID = $project->get('proID'); ?>
								<?php endif; ?>
							<?php }); ?>
							</select>
						</div>
					</div>
					<div class="form-group mb-lg-0 col-md-6">
						<label for="quality" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_QUALITY_TEXT', $lang);
						?>:&nbsp;&ast;</label>
						<div>
							<select name="quality"
									class="form-control position-relative auto-submit"
									id="inputQuality"
									form="partsForm"
									required
									data-lang="<?php echo $lang; ?>"
									data-target="#unbookedPartsList"
									data-rule-required="true"
									data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $lang); ?>"
									tabindex="<?php echo ++$tabindex; ?>"
							>
								<option value="">&ndash; <?php echo Text::translate('COM_FTK_LABEL_QUALITY_TEXT', $lang); ?> &ndash;</option>
								<option value="good"<?php echo ($qualityType == 'good' ? ' selected' : ''); ?>><?php
									echo Text::translate('COM_FTK_LABEL_GOOD_PARTS_TEXT', $lang);
								?></option>
								<option value="bad"<?php  echo ($qualityType ==  'bad' ? ' selected' : ''); ?>><?php
									echo Text::translate('COM_FTK_LABEL_BAD_PARTS_TEXT', $lang);
								?></option>
							</select>
						</div>
					</div>
					<?php if (FALSE) : ?>
					<div class="form-group mb-lg-0 col-md-2">
						<div>
							<button type="submit"
									form="partsForm"
									class="btn btn-info btn-search allow-window-unload float-right"
									title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
									aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
							>
								<i class="fas fa-search" aria-hidden="true"></i>
								<span class="sr-only ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $lang); ?></span>
							</button>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</form>
		</div>
	</div>

	<hr>

	<div class="position-relative mt-lg-4" id="unbookedPartsList">
		<div>
		<?php if ($cnt = count($allArticles)) : ?>
		<?php 	$i = 1; ?>
			<?php // Prozess-Filter ?>
			<aside class="card mb-lg-4"
				   title="<?php echo Text::translate('COM_FTK_HINT_SELECT_PROCESSES_TO_HIDE_TEXT', $lang); ?>"
				   data-toggle="tooltip"
				   <?php // data-delay='{"show":5000,"hide":250}' ?>
			>
				<div class="card-header">
					<form action=""
						  method="post"
						  name="unbookedPartsListFilterForm"
						  class="form partsForm"
						  id="unbookedPartsListFilterForm"
						  data-submit=""
						  data-monitor-changes="false"
					>
						<input type="hidden" name="user"  value="<?php echo $userID; ?>">
						<input type="hidden" name="task"  value="unbookedPartsListFilterExport">
						<input type="hidden" name="proid" value="<?php echo $proID; ?>">

						<strong class="align-middle"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_FILTER_TEXT', $lang); ?></strong>

						<div class="btn-toolbar d-inline-block float-right mr-1" role="toolbar" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLBAR_WITH_BUTTON_GROUP_TEXT', $this->language); ?>">
							<div class="btn-group btn-group-sm" role="group" aria-label="First group"><?php // TODO - translate ?>
								<?php // SAVE-Button ?>
								<button type="submit"
										form="unbookedPartsListFilterForm"
										name="task"
										value="unbookedPartsListFilterExport"
										class="btn btn-sm btn-warning btn-submit btn-save text-right"
										title="<?php echo Text::translate('COM_FTK_LABEL_SAVE_SELECTION_FOR_THIS_PROJECT_TEXT', $lang); ?>"
										aria-label="<?php echo Text::translate('COM_FTK_LABEL_SAVE_SELECTION_FOR_THIS_PROJECT_TEXT', $lang); ?>"
										data-toggle="tooltip"
										<?php // data-delay='{"show":1000}' ?>
										style="min-width:12rem"
								>
									<span class="mr-lg-1"><?php echo Text::translate('COM_FTK_LABEL_SAVE_AS_DEFAULT_TEXT', $lang); ?></span>
									<i class="fas fa-save"></i>
								</button>
							</div>
						</div>

					<?php foreach ($selProcIDs as $pid) : if (empty($pid)) : continue; endif; ?>
					<input type="hidden" name="config[parts][unbooked][filter][processes][<?php echo $projectNum; ?>][]" value="<?php echo $pid; ?>" />
					<?php endforeach; ?>
					</form>
				</div>
				<div class="card-body">
					<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=%s&project=%s&quality=%s', $lang, $view->get('name'), $view->get('layout'), $input->getCmd('project'), $input->getWord('quality') ))); ?>"
						  method="get"
						  name="processFilterForm"
						  class="form filterForm processFilterForm mx-lg-1"
						  id="processFilterForm"
						  data-submit=""
					>
						<input type="hidden" name="hl"      value="<?php echo $lang; ?>" />
						<input type="hidden" name="view"    value="<?php echo $view->get('name'); ?>" />
						<input type="hidden" name="layout"  value="<?php echo $view->get('layout'); ?>" />
						<input type="hidden" name="project" value="<?php echo $input->getCmd('project'); ?>" />
						<input type="hidden" name="quality" value="<?php echo $input->getWord('quality'); ?>" />
						<?php /***   I M P O R T A N T :   This hidden field is very important! Do not delete it! It allows a user to deselect all filters without breaking its functionality.   ***/ ?>
						<input type="hidden" name="pids[]"  value="0" />

						<div class="row">
							<div class="col-10 pl-0" style="padding-left:10px !important">
								<fieldset id="processFilters" tabindex="<?php echo ++$tabindex; ?>" style="outline:0"><?php 
								echo LayoutHelper::render('widget.controls.checkboxgroup', (object) [
									'options'  => array_combine(array_keys($allProcesses), array_column($allProcesses, 'abbreviation')),
									'uncheck'  => $cbsToHide,
									'layout'   => 'button',
									'multiple' => true,
									'form'     => 'processFilterForm',
									'tabindex' => $tabindex,
									'attribs'  => [
										'class'    => 'process-filter mx-lg-2 my-lg-1',	// for regular checkboxes skip mx-* class
										'name'     => 'pids',
										'required' => false
									],
									'sorted'   => true,
									'sort'     => 'value',
									'ordering' => 'ASC'
								], ['language' => $lang]);
								?>
								</fieldset>
							</div>
							<div class="col-2">
								<fieldset id="processFiltersMasterTogggles" tabindex="<?php echo ++$tabindex; ?>" style="outline:0">
									<div class="form-check my-lg-1 float-right">
										<label for="master-toggle-all"
											   class="form-check-label btn btn-sm btn-secondary text-right master-toggle"
											   data-toggle="tooltip"
											   <?php // data-delay='{"show":1000}' ?>
											   title="<?php echo sprintf(
													Text::translate('COM_FTK_LIST_OPTION_SHOW_ALL_X_TEXT', $lang),
													Text::translate('COM_FTK_LABEL_PROCESSES_TEXT', $lang)); ?>"
											   style="min-width:12rem"
										>
											<input type="checkbox"
												   class="form-check-input master-check-input d-none"
												   id="master-toggle-all"
												   tabindex="<?php echo ++$tabindex; ?>"
												   data-toggle="checkAllAndSubmit"
												   data-target="#processFilters"
											><span class="mr-lg-1"><?php echo Text::translate('COM_FTK_LIST_OPTION_SHOW_ALL_TEXT', $lang); ?></span>
											<i class="fas fa-eye"></i>
										</label>
									</div>
									<div class="form-check my-lg-1 float-right">
										<label for="master-toggle-none"
											   class="form-check-label btn btn-sm btn-secondary text-right master-toggle"
											   data-toggle="tooltip"
											   <?php // data-delay='{"show":1000}' ?>
											   title="<?php echo sprintf(
													Text::translate('COM_FTK_LIST_OPTION_HIDE_ALL_X_TEXT', $lang),
													Text::translate('COM_FTK_LABEL_PROCESSES_TEXT', $lang)); ?>"
											   style="min-width:12rem"
										>
											<input type="checkbox"
												   class="form-check-input master-check-input d-none"
												   id="master-toggle-none"
												   tabindex="<?php echo ++$tabindex; ?>"
												   data-toggle="uncheckAllAndSubmit"
												   data-target="#processFilters"
											><span class="mr-lg-1"><?php echo Text::translate('COM_FTK_LIST_OPTION_HIDE_ALL_TEXT', $lang); ?></span>
											<i class="fas fa-eye-slash"></i>
										</label>
									</div>
									<div class="form-check my-lg-1 float-right">
										<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=%s&project=%s&quality=%s', $lang, $view->get('name'), $view->get('layout'), $input->getCmd('project'), $input->getWord('quality') ))); ?>"
										   class="form-check-label btn btn-sm btn-secondary text-right master-toggle"
										   id="master-toggle-reset"
										   data-toggle="tooltip"
										   <?php // data-delay='{"show":1000}' ?>
										   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_RECOVER_STORED_SELECTION_TEXT', $lang); ?>"
										   style="min-width:12rem"
										>
											<span class="mr-lg-1"><?php echo Text::translate('COM_FTK_LIST_OPTION_RESET_TEXT', $lang); ?></span>
											<i class="fas fa-history"></i>
										</a>
									</div>
								</fieldset>
							</div>
						</div>
					</form>
				</div>
			</aside>
			<?php foreach ($allArticles as $projectNum => $articles) : ?>
			<div class="card mb-3" id="card-<?php echo (int) $i; ?>">
				<div class="card-header border-bottom-0 d-none" id="heading-<?php echo (int) $i; ?>">
					<h5 class="mb-0">
						<button type="button"
								class="btn btn-link text-dark text-decoration-none px-0 text-left w-100"
								data-toggle="collapse"
								data-target="#collapse-<?php echo (int) $i; ?>"
								aria-expanded="true"
								aria-controls="collapseOne"
						><?php echo sprintf('%s: <strong>%s</strong>', Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang), $projectNum); ?></button>
					</h5>
				</div>
				<div id="collapse-<?php echo (int) $i; ?>" class="collapse<?php echo ($i == '1' ? ' show' : ''); ?>" aria-labelledby="heading-<?php echo (int) $i; ?>">
					<div class="card-body" style="overflow-x:hidden">
						<table class="table mb-0 pb-0 ">
							<tbody>
								<tr>
									<td width="46%" class="border-0" style="padding-right:0">
										<?php $a1 = 1; ?>
										<?php foreach ($articles as $idx => $article) : ?>
										<?php	$articleProcesses  = (array) $article->get('processes'); ?>
										<?php	$articleProcessIDs = array_column($articleProcesses, 'procID'); //$articleProcessIDs = array_map('intval', $articleProcessIDs); ?>
										<?php	$articleProcessIDs = array_filter($articleProcessIDs, function ($pid) use (&$cbsToHide) { return !\in_array($pid, $cbsToHide); }); ?>

										<?php	if (empty($articleProcessIDs)) : continue; endif; ?>

										<table class="table table-sm mb-0 pb-0 article-processes">
											<thead>
												<tr class="table-secondary">
													<th class="align-middle border-top-0"><?php
														echo html_entity_decode($article->get('number'));
													?></th>
													<th class="align-middle border-top-0"><?php
														echo ($lang != 'de' ?
															mb_strtolower(Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $lang)) :
															Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $lang));
													?></th>
													<th class="align-middle border-top-0 text-center"><?php
														echo Text::translate('COM_FTK_STATUS_UNBOOKED_TEXT', $lang);
													?></th>
													<th class="align-middle border-top-0"></th>
												</tr>
											</thead>
											<tbody>
											<?php $b1 = 1; ?>
											<?php foreach ($articleProcesses as $processNumber => $articleProcess) : ?>
												<?php if ($skip = in_array($articleProcess['procID'], $cbsToHide)) continue; ?>
												<?php $articleProcess = new Registry($articleProcess); ?>
												<?php	     $process = new Registry(ArrayHelper::getValue($allProcesses, $articleProcess->get('procID'))); ?>
												<?php	  $partsCount = $articleProcess->get('unbooked'); // will be a number ?>

												<?php if ($partsCount > 0) : ?>
												<tr data-row-num="<?php echo (int) $b1; ?>">
													<td class="align-middle" scope="row"
														data-parts-article-id="<?php echo (int) $article->get('artID'); ?>"
														data-parts-process-id="<?php echo (int) $process->get('procID'); ?>"
													><?php echo html_entity_decode($processNumber); ?></td>
													<td class="align-middle" scope="row">
														<span class="text-uppercase" data-toggle="tooltip" title="<?php echo html_entity_decode($process->get('name')); ?>"><?php
															echo html_entity_decode($process->get('abbreviation'));
														?></span>
													</td>
													<td class="align-middle text-center"
														data-parts-article-id="<?php echo (int) $article->get('artID'); ?>"
														data-parts-process-id="<?php echo (int) $process->get('procID'); ?>"
														data-parts-book="<?php echo $partsCount; ?>"
													><?php echo $partsCount; ?></td>
													<td class="text-center">
														<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=parts&layout=report.form', $lang ))); ?>"
															  method="post"
															  class="bookPartsForm"
															  id="bookPartsForm-<?php echo (int) $article->get('artID'); ?>-<?php echo (int) $process->get('procID'); ?>"
															  name="bookPartsForm-<?php echo (int) $article->get('artID'); ?>-<?php echo (int) $process->get('procID'); ?>"
															  data-action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&service=api&task=parts.book&provide', $lang ))); ?>"
															  data-trigger="submit"
															  data-submit="ajax"
															  data-format="json"
														>
															<input type="hidden" name="aid"     value="<?php echo (int) $article->get('artID'); ?>"/>
															<input type="hidden" name="pid"     value="<?php echo (int) $process->get('procID'); ?>"/>
															<input type="hidden" name="parts"   value="<?php echo (int) $partsCount; ?>"/>
															<input type="hidden" name="quality" value="<?php echo html_entity_decode($qualityType); ?>"/>
															<input type="hidden" name="format"  value=""/>

															<button type="submit"
																	form="bookPartsForm-<?php echo (int) $article->get('artID'); ?>-<?php echo (int) $process->get('procID'); ?>"
																	class="btn btn-sm btn-info dynamic-color"
																	data-class-default="btn-info"
																	data-class-processing="btn-info"
																	data-class-success="btn-success"
																	data-class-fail="btn-danger"
																	data-bind="copyValue"
																	data-copy-from="td[data-parts-article-id='<?php echo (int) $article->get('artID'); ?>'][data-parts-process-id='<?php echo (int) $process->get('procID'); ?>'][data-parts-book='<?php echo (int) $partsCount; ?>']"
																	data-copy-to="td[data-parts-article-id='<?php echo (int) $article->get('artID'); ?>'][data-parts-process-id='<?php echo (int) $process->get('procID'); ?>'][data-parts-booked]"
																	data-copy-delete="true"
																	data-copy-disable="true"
																	title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BOOK_PARTS_NOW_TEXT', $lang); ?>"
																	style="max-width:60px"
															>
																<span class="sr-only"><?php echo Text::translate('Book', $lang); ?></span>
																<i class="fas fa-arrow-right px-3"
																   data-class-default="fas fa-arrow-right px-3"
																   data-class-processing="fas fa-spinner fa-pulse px-3"
																   data-class-success="fas fa-check px-3"
																   data-class-fail="fas fa-times px-3"
																></i>
															</button>
														</form>
													</td>
												</tr>
												<?php endif; ?>

												<?php $b1 += 1; ?>
											<?php endforeach; ?>
											</tbody>
										</table>
										<?php $a1 += 1; ?>
										<?php endforeach; ?>
									</td>
									<td width="54%" class="border-0" style="padding-left:0">
										<?php $a2 = 1; ?>
										<?php foreach ($articles as $idx => $article) : ?>
										<?php	$articleProcesses = (array) $article->get('processes'); ?>
										<?php	$articleProcessIDs = array_column($articleProcesses, 'procID'); //S$articleProcessIDs = array_map('intval', $articleProcessIDs); ?>
										<?php	$articleProcessIDs = array_filter($articleProcessIDs, function ($pid) use (&$cbsToHide) { return !\in_array($pid, $cbsToHide); }); ?>

										<?php	if (empty($articleProcessIDs)) : continue; endif; ?>

										<?php	$articlesBooked   = ArrayHelper::getValue($articleProcesses, 'booked', [], 'ARRAY'); ?>
										<?php	/* $bookDates        = [$today];
												$skipped = 0;

												list($year, $month, $day) = explode('-', $today);

												$dateObject->setDate($year, $month, $day);

												for ($x = 1; $x <= (int) $userProfile->get('parts.book.retrospective.limit', '5'); $x += 1) :
													$dateObject->sub(new DateInterval('P1D'));

													// Ignore weekends.
													// FIXME - ignore holidays
													if (in_array($dateObject->format('D'), ['Sun'])) :
														$skipped += 1;

														continue;
													endif;

													array_push($bookDates, $dateObject->format('Y-m-d'));
												endfor;

												for ($x = 0; $x < $skipped; $x += 1) :
													$dateObject->sub(new DateInterval('P1D'));

													// Ignore weekends.
													// FIXME - ignore holidays
													if (in_array($dateObject->format('D'), ['Sun'])) :
														$skipped += 1;

														continue;
													endif;

													array_push($bookDates, $dateObject->format('Y-m-d'));
												endfor; */
										?>

										<div class="" id="" data-article="<?php echo $article->get('number'); ?>">
											<table class="table table-responsive table-sm mb-0 pb-0 booking-dates">
												<thead>
													<tr class="table-secondary">
														<?php foreach ($bookDates as $date) : ?>
														<?php	[$year, $month, $day] = explode('-', $date);
																$dateObject->setDate($year, $month, $day);
														?>
														<th class="align-middle border-top-0 px-lg-3"><?php echo $dateObject->format($localeConfig->get('xdate', 'd.m.y')); ?></th>
														<?php endforeach; ?>
													</tr>
												</thead>
												<tbody>
												<?php $b2 = 1; ?>
												<?php foreach ($articleProcesses as $processNumber => $articleProcess) : ?>
													<?php if ($skip = in_array($articleProcess['procID'], $cbsToHide)) continue; ?>
													<?php $articleProcess = new Registry($articleProcess); ?>
													<?php	     $process = new Registry(ArrayHelper::getValue($allProcesses, $articleProcess->get('procID'))); ?>
													<?php	     $booked  = $articleProcess->extract('booked'); ?>

													<tr data-row-num="<?php echo (int) $b2; ?>">
														<?php foreach ($bookDates as $x => $date) : ?>
														<?php	$date = (is_a($booked, 'Joomla\Registry\Registry') ? $booked->get($date) : null); ?>

														<td class="align-middle text-center"
															<?php if ($x == '0') : ?>
															data-parts-article-id="<?php echo (int) $article->get('artID'); ?>"
															data-parts-process-id="<?php echo (int) $process->get('procID'); ?>"
															data-parts-booked=""
															<?php endif; ?>
														>
															<span class="btn btn-sm"><?php echo (!is_null($date) ? $date : ($x == '0' ? '&nbsp;' : '&nbsp;')); ?></span>
														</td>
														<?php endforeach; ?>
													</tr>
												<?php $b2 += 1; ?>
												<?php endforeach; ?>
												</tbody>
											</table>
										</div>
										<?php $a2 += 1; ?>
										<?php endforeach; ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<?php $i += 1; ?>
			<?php endforeach; ?>
		<?php else : ?>
			<?php if ($cnt == 0 && !empty($projectNum) && !empty($qualityType)) : ?>
				<?php echo LayoutHelper::render('system.alert.info', [
					'message' => Text::translate(mb_strtoupper(sprintf('COM_FTK_HINT_NO_UNBOOKED_%s_PARTS_TEXT', $qualityType)), $lang),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php else : ?>
				<?php switch (true) : ?><?php
				case  empty($projectNum) &&  empty($qualityType) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_AND_QUALITY_TYPE_TO_REPORT_PARTS_BACK_TEXT', $lang)];
				break;

				case  empty($projectNum) && !empty($qualityType) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_TO_REPORT_PARTS_BACK_TEXT', $lang)];
				break;

				case !empty($projectNum) &&  empty($qualityType) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_QUALITY_TYPE_TEXT', $lang)];
				break;

				default:
					$data = null;
				?><?php endswitch; ?>

				<?php echo LayoutHelper::render('system.alert.notice', $data); ?>
			<?php endif; ?>
		<?php endif; ?>
		</div>
	</div>
</div>

<?php // Free memory
unset($allArticles);
unset($allArticlesGrouped);
unset($allProcesses);
unset($articleModel);
unset($articleProcess);
unset($articleProcesses);
unset($articlesModel);
unset($bookDates);
unset($parts);
unset($partsModel);
unset($process);
unset($processesModel);
unset($userProfile);
unset($allProcIDs);
unset($selProcIDs);
unset($cbsToHide);
?>
