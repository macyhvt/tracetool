<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$proID    = $input->getInt('proid');
$task     = $input->post->getCmd('task')      ?? ($input->getCmd('task')       ?? null);
$redirect = $input->post->getString('return') ?? ( $input->getString('return') ?? null);
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
$allBookedCnt   = 0;

if (!empty($projectNum) && !empty($qualityType)) :
	$dateObject     = new DateTime('NOW', new DateTimeZone($localeConfig->get('timezone', FTKRULE_TIMEZONE)));
	$dateInterval   = $userProfile->get('parts.booked.retrospective.limit', '5');
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

		\array_filter($articleProcesses, function($articleProcess, $procID) use(&$tmp)
		{
			$tmp[$articleProcess->get('drawingnumber')] = $articleProcess->toArray();

		}, ARRAY_FILTER_USE_BOTH);

		$article->set('processes', $tmp);

		// Free memory.
		unset($tmp);

		// Get name of the project this article belongs to.
		$project = $article->get('project');

		// If this project collection doesn't exist yet, create it.
		if (!\array_key_exists($project, $allArticlesGrouped)) :
			$allArticlesGrouped[$project] = [];
		endif;

		// Add this article to its corresponding project collection.
		\array_push($allArticlesGrouped[$project], $article);
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
	\array_walk($allArticles, function(&$articles) use(&$allBookedCnt, &$allProcesses, &$partsModel, &$projectNum, &$qualityType, &$dateFrom, &$dateTo) {
		/* INFO:
		 *
		 * $articles is a numerically indexed array of of Joomla\Registry\Registry objects.
		 */
		$articles = \array_filter((array) $articles, function($article) use(&$allBookedCnt, &$allProcesses, &$partsModel, &$projectNum, &$qualityType, &$dateFrom, &$dateTo) {
			// Skip this article, if it belongs to any other project than the selected one.
			if (mb_strtolower($projectNum) !== mb_strtolower($article->get('project'))) :
				return false;
			endif;

			// Get reference to this article's processes property to fetch additional data.
			$articleProcesses      = \array_filter((array) $article->get('processes'));
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
				// Fetch   b o o k e d   parts.
				\array_walk($articleProcesses, function(&$articleProcess) use(&$allBookedCnt, &$article, &$allProcesses, &$partsModel, &$qualityType, &$dateTo, &$dateFrom) {
					$procID   = ArrayHelper::getValue($articleProcess, 'procID');
					$process  = ArrayHelper::getValue($allProcesses, $procID);

					$booked   = $partsModel->getPartsBooked($article->get('artID'), $qualityType, [ArrayHelper::getValue($process, 'procID')]/* , $dateFrom, $dateTo */);	// return value will be numeric
					$tmpCount = \array_sum($booked);

					if (count($booked)) :
						$articleProcess['booked'] = $booked;
					else :
						$articleProcess['booked'] = null;
					endif;

					// Increment total count.
					$allBookedCnt += $tmpCount;
				});

				// Drop null values.
				$articleProcesses = \array_filter($articleProcesses, function($articleProcess, $processNumber) {
					$booked = ArrayHelper::getValue($articleProcess, 'booked', null);


					return (\array_key_exists('booked', $articleProcess) && !is_null($booked));

				}, ARRAY_FILTER_USE_BOTH);

				// Update count.
				$articleProcessesCount = count($articleProcesses);

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
	$allArticles = \array_filter($allArticles);

	// Prepare dates interval for table columns.
	$dateFromDay = (new \DateTime($dateTo, new \DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d');
	$bookDates   = [$dateFromDay];
	$skipped     = 0;

	list($year, $month, $day) = explode('-', $dateFromDay);

	$dateObject->setDate($year, $month, $day);

	// for ($x = 1; $x <= (int) $userProfile->get('parts.book.retrospective.limit', $dateInterval); $x += 1) :
	for ($x = 1; $x <= $dateInterval; $x += 1) :
		$dateObject->sub(new DateInterval('P1D'));

		// Ignore weekends.
		// FIXME - ignore holidays
		/* if (in_array($dateObject->format('D'), ['Sun'])) :
			$skipped += 1;

			continue;
		endif; */

		\array_push($bookDates, $dateObject->format('Y-m-d'));
	endfor;

	for ($x = 0; $x < $skipped; $x += 1) :
		$dateObject->sub(new DateInterval('P1D'));

		// Ignore weekends.
		// FIXME - ignore holidays
		/* if (in_array($dateObject->format('D'), ['Sun'])) :
			$skipped += 1;

			continue;
		endif; */

		\array_push($bookDates, $dateObject->format('Y-m-d'));
	endfor;
endif;	// !empty($projectNum) && !empty($qualityType)

// Init tabindex
$tabindex = 0;

// Prepare dates interval for table columns.
$now   = new \DateTime('NOW', new \DateTimeZone(FTKRULE_TIMEZONE));
$today = $now->format('Y-m-d');
?>

<style>
.table td {
	border: 0;
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
		<div class="col col-lg-5 ml-0 pl-0">
			<h1 class="h4 d-inline-block mb-0 pb-0 mr-2" style="line-height:1.4"><?php
				if (empty($projectNum) && empty($qualityType)) :
					echo Text::translate('COM_FTK_HEADING_BOOKED_PARTS_LABEL', $lang);
				elseif (!empty($projectNum) && empty($qualityType)) :
					echo sprintf('%s %s &ndash; %s',
						Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang),
						$projectNum,
						Text::translate('COM_FTK_HEADING_BOOKED_PARTS_LABEL', $lang)
					);
				else :
					echo sprintf('%s %s:%s<small>%d %s</small>',
						Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $lang),
						$projectNum,
						str_repeat('&nbsp;', 3),
						$allBookedCnt,
						// Text::translate(str_ireplace('__', '_', 'COM_FTK_HEADING_COM_FTK_HEADING_' . mb_strtoupper($qualityType) . '_PARTS_BOOKED_TEXT'), $lang)
						Text::translate('COM_FTK_HEADING_PARTS_BOOKED_TEXT', $lang)
					);
				endif;
			?></h1>
		</div>
		<div class="col col-lg-6">
			<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=%s&date=%s', $lang, $view->get('name'), $view->get('layout'), htmlentities($dateTo) ))); ?>"
				  method="get"
				  name="partsForm"
				  class="form partsForm"
				  id="partsForm"
				  data-submit=""
			>
				<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
				<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
				<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

				<div class="form-row">
					<div class="form-group mb-lg-0 col-lg-3">
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
									data-target="#bookedPartsList"
									data-rule-required="true"
									data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $lang); ?>"
									tabindex="<?php echo ++$tabindex; ?>"
									autofocus
							>
								<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_PROJECT_TEXT', $lang); ?> &ndash;</option>
							<?php \array_walk($allProjects, function($project, $proID) use(&$projectNum) { ?>
								<?php $project = new Registry($project); ?>
								<option value="<?php echo $project->get('number'); ?>"<?php echo ($project->get('number') == $projectNum ? ' selected' : ''); ?>><?php
									echo html_entity_decode($project->get('number'));
								?></option>
							<?php }); ?>
							</select>
						</div>
					</div>
					<div class="form-group mb-lg-0 col-md-4">
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
									data-target="#bookedPartsList"
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
					<div class="form-group mb-lg-0 col-lg-5">
						<label for="to" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_DATE_TEXT', $lang);
						?>:&nbsp;&ast;</label>
						<div>
							<div class="input-group date position-relative"
								 Xdata-provide="name"
								 data-date-language="<?php echo $lang; ?>"
								 data-date-week-start="1"
								 data-date-days-of-week-disabled="[6,0]"
								 data-date-format="dd.mm.yyyy"
								 data-date-autoclose="true"
								 data-date-calendar-weeks="true"
								 data-date-clear-btn="false"
								 data-date-today-highlight="true"
								 data-date-today-btn="false"
								 data-date-end-date="<?php echo date_create('NOW', new \DateTimeZone(FTKRULE_TIMEZONE))->format($localeConfig->get('date')); ?>"
							>
								<input type="text"
									   name="to"
									   value="<?php echo htmlentities($dateTo); ?>"
									   class="form-control datepicker rounded-left text-center auto-submit"
									   id=""
									   form="partsForm"
									   placeholder="dd.mm.yyyy"
									   data-target="#bookedPartsList"
									   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $lang); ?>"
									   aria-described-by="btn-pick-date"
									   data-rule-required="true"
									   tabindex="<?php echo ++$tabindex; ?>"
								>
								<div class="input-group-append">
									<span class="input-group-text rounded-right" id="btn-pick-date" role="button">
										<i class="fas fa-calendar-alt"></i>
									</span>
								</div>
								<input type="hidden" name="from" value="<?php echo htmlentities($dateFrom); ?>" readonly>
							</div>
						</div>
					</div>
					<?php if (FALSE) : ?>
					<div class="form-group mb-lg-0 col-lg-2">
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

	<div class="position-relative mt-lg-4" id="bookedPartsList">
		<div>
		<?php if ($cnt = count($allArticles)) : ?>
		<?php 	$i = 1; ?>
			<?php foreach ($allArticles as $projectNum => $articles) : ?>
			<div class="card mb-3" id="card-<?php echo (int) $i; ?>">
				<div class="card-header border-bottom-0" id="heading-<?php echo (int) $i; ?>">
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
									<td width="25%" class="border-0" style="padding-right:0">
										<?php $a1 = 1; ?>
										<?php foreach ($articles as $idx => $article) : ?>
										<?php	$articleProcesses = (array) $article->get('processes'); ?>

										<table class="table mb-0 pb-0 article-processes">
											<thead>
												<tr class="table-secondary">
													<th class="align-middle border-top-0"><?php
														echo html_entity_decode($article->get('number'));
													?></th>
												</tr>
											</thead>
											<tbody>
											<?php $b1 = 1; ?>
											<?php foreach ($articleProcesses as $processNumber => $articleProcess) : ?>
												<?php $articleProcess = new Registry($articleProcess); ?>
												<?php	     $process = new Registry(ArrayHelper::getValue($allProcesses, $articleProcess->get('procID'))); ?>

												<tr data-row-num="<?php echo (int) $b1; ?>">
													<td class="align-middle" scope="row"
														data-parts-article-id="<?php echo (int) $article->get('artID'); ?>"
														data-parts-process-id="<?php echo (int) $process->get('procID'); ?>"
													>
														<abbr class="text-decoration-none"
															  data-toggle="tooltip"
															  title="<?php echo $process->get('name'); ?>"
														><?php echo html_entity_decode($processNumber); ?></abbr>
													</td>
												</tr>

												<?php $b1 += 1; ?>
											<?php endforeach; ?>
											</tbody>
										</table>
										<?php $a1 += 1; ?>
										<?php endforeach; ?>
									</td>
									<td width="75%" class="border-0" style="padding-left:0">
										<?php $a2 = 1; ?>
										<?php foreach ($articles as $idx => $article) : ?>
										<?php	$articleProcesses = (array) $article->get('processes'); ?>

										<div class="" id="" data-article="<?php echo $article->get('number'); ?>">
											<table class="table table-responsive mb-0 pb-0 booking-dates">
												<thead>
													<tr class="table-secondary">
														<?php $x2 = 1; $cnt = count((array) $bookDates); ?>
														<?php foreach ($bookDates as $date) : ?>
														<?php	list($year, $month, $day) = explode('-', $date);
																$dateObject->setDate($year, $month, $day);
														?>
														<th class="align-middle border-top-0 <?php echo ($x2 < $cnt ? 'px-lg-3' : 'px-lg-3'); ?>"><?php
															echo $dateObject->format($localeConfig->get('date'));
														?></th>
														<?php $x2 += 1 ?>
														<?php endforeach; ?>
													</tr>
												</thead>
												<tbody>
												<?php $b2 = 1; ?>
												<?php foreach ($articleProcesses as $processNumber => $articleProcess) : ?>
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
															<span class=""><?php echo (!is_null($date) ? $date : ($x == '0' ? '&nbsp;' : '&nbsp;')); ?></span>
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
					'message' => Text::translate('COM_FTK_HINT_NO_BOOKED_PARTS_TEXT', $lang),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php else : ?>
				<?php switch (true) : ?><?php
				case  empty($projectNum) &&  empty($qualityType) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_AND_QUALITY_TYPE_TO_SEE_BOOKED_PARTS_TEXT', $lang)];
				break;

				case  empty($projectNum) && !empty($qualityType) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_TO_SEE_BOOKED_PARTS_TEXT', $lang)];
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
unset($userProfile);
?>
