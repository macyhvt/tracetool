<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \Factory;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Helper\UserHelper;
use  \Text;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$return   = $view->getReturnPage();	// Browser back-link required for back-button.
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task', $input->getCmd('task'));
$redirect = $input->post->getString('return', $input->getString('return'));
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);
$redirect = (!is_null($input->get->getWord('project')) && !is_null($input->getWord('quality')))
	? $input->server->getUrl('REQUEST_URI')
	: $redirect;
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
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		case 'matrixConfigExport' :
			$view->saveMatrixConfig();
		break;
	endswitch;
endif;
?>
<?php /* Prepare view data */
$isAutorefresh      = $input->getInt('ar') == '1';
$isAutorefreshClass = ($isAutorefresh ? 'isAutorefresh' : '');

$isPrint      = $task === 'print';

$data         = $view->get('data');

// Get date format configuration from user profile.
$userProfile  = new Registry(UserHelper::getProfile($user));
$localeConfig = $userProfile->extract('user.locale');
$localeConfig = (is_a($localeConfig, 'Joomla\Registry\Registry') ? $localeConfig : new Registry);

$allArticles        = $data->get('articles');
$allArticlesGrouped = [];

$allProcesses       = $data->get('processes');
$allProcessSteps    = [];

foreach ($allArticles as $artID => $article) :
	$article = new Registry($article);

	$articleProcesses = $model->getInstance('article', ['language' => $lang])->getArticleProcesses($artID);

	// Build collection of all registered process steps for this article required to build the table header.
	$allProcessSteps  = array_merge($allProcessSteps, (function($articleProcesses) {
		$tmp = [];

		array_walk($articleProcesses, function($articleProcess) use(&$tmp) {
			$tmp[] = $articleProcess->get('step');
		});

		return $tmp;
	})($articleProcesses));

	// Get the name of this article's assembly collection.
	$namePcs  = explode('.', $article->get('number', ''));
	$assembly = ArrayHelper::getValue($namePcs, 2, null, 'STRING');

	// If this x-collection doesn't exist yet, create it.
	if (!array_key_exists($assembly, $allArticlesGrouped)) :
		$allArticlesGrouped[$assembly] = [];
	endif;

	// Add this article to its corresponding x-collection.
	$allArticlesGrouped[$assembly][] = $article;
endforeach;

$allArticles = $allArticlesGrouped;

$allProcessSteps = array_unique($allProcessSteps);

sort($allProcessSteps);

$allProjects  = $data->get('projects');

$project      = $data->get('project');
$projectNum   = $project->get('number') ?? null;

$isBlocked    = $project->get('blocked') == '1';
$isDeleted    = $project->get('trashed') == '1';

// Get matrix defaults from app configuration file.
$appConfig = Factory::getConfig();
$defaults  = $appConfig->extract('defaults');

$appMatrixConfig = $defaults->extract('project.matrix');
$usrMatrixConfig = (is_a($appMatrixConfig, 'Joomla\Registry\Registry') ? $appMatrixConfig : new Registry);

// Get matrix configuration from user profile
$userProfile = new Registry(UserHelper::getProfile($user));
// $usrMatrixConfig = $userProfile->extract('process.matrix');
$usrMatrixConfig = $userProfile->extract('project.matrix');
$usrMatrixConfig = (is_a($usrMatrixConfig, 'Joomla\Registry\Registry') ? $usrMatrixConfig : new Registry);

/*//@test - does the user matrix config override app defaults when merged?
$usrMatrixConfig->loadArray([
	'colors' => [
		'cell' => [
			  0 => '#FF0000',
			  1 => '#000000',
			 40 => '#000000',
			 85 => '#000000',
			100 => '#000000',
			111 => '#000000'
		],
		'font' => [
			  0 => '#FF0000',
			  1 => '#000000',
			 40 => '#000000',
			 85 => '#000000',
			100 => '#000000',
			111 => '#000000'
		]
	]
]);*/

$matrixConfig  = $appMatrixConfig->merge($usrMatrixConfig);

$cssCellBG     = (array) $matrixConfig->get('colors.cell');
$cssTxtColor   = (array) $matrixConfig->get('colors.font');
$cssTxtStyle   = (array) $matrixConfig->get('font.weight');

$appThresholds = array_keys($cssCellBG);
$usrThresholds = (array) $matrixConfig->get('thresholds');

/**
 * Function to get the matching colorizing value from range.
 *
 * @param  array $arrNumbers  The numerical representation of each colour range to consider for colour applicance.
 * @param  int   $needle      The value to find a matching number for.
 *
 * @return int  The numerical representation of the matching colour range
 */
$calcPercentage  = function($arrNumbers, $needle) {
	$arrNumbers = array_map('intval', $arrNumbers);
	$first      = current($arrNumbers);
	$last       = end($arrNumbers);
	$needle     = intval($needle);

	reset($arrNumbers);

	for ($i = 0, $cnt = count($arrNumbers); $i < $cnt; $i += 1) :
		$current = current($arrNumbers);
		$next    = next($arrNumbers);

		switch (true) :
			case ($needle <= $first) :
				$needle = $first;
			break;

			case ($needle >= $last) :
				$needle = $last;
			break;

			case ($needle > $current && $needle < $next) :
				$needle = $current;
			break;
		endswitch;
	endfor;

	return $needle;
};

$articleModel = $model->getInstance('article', ['language' => $lang]);

// Init tabindex
$tabindex = 0;
?>

<style>
#btnAutorefresh {
	opacity: 0.4;
}
#btnAutorefresh:hover {
	opacity: 0.5;
}
#btnAutorefresh:focus,
#btnAutorefresh:active {
	opacity: 0.6;
}

#btnAutorefresh.isAutorefresh {
	opacity: 1;
}

#btnAutorefresh.isAutorefresh {
	background-color: rgba(255, 68, 0, .75);	/* orangered */
	border-color:     rgba(255, 68, 0, .75);	/* orangered */
}
#btnAutorefresh.isAutorefresh:hover {
	background-color: rgba(255, 68, 0, 1) !important;	/* orangered */
	border-color:     rgba(255, 68, 0, 1) !important;	/* orangered */
}
#btnAutorefresh.isAutorefresh:focus,
#btnAutorefresh.isAutorefresh:active {
	background-color: rgba(229, 61, 0, 1) !important;	/* orangered 10% darkened */
	border-color:     rgba(229, 61, 0, 1) !important;	/* orangered 10% darkened */
}

.table-caption {
	caption-side: top;
	font-weight: bold;
}

<?php // Color legend ?>
.my-legend .legend-scale .legend-labels > li {
	width: 80px;
	max-width: 100px;
}
.my-legend .legend-scale .legend-labels > li > .legend-color {
	height: 5px;
}

<?php // Color change animation ?>
.matrix-cell {
	-moz-transition: all 350ms;
	/* WebKit */
	-webkit-transition: all 350ms;
	/* Opera */
	-o-transition: all 350ms;
	/* Standard */
	transition: all 350ms;
}

<?php foreach ($cssCellBG as $abbreviation => $value) : ?>
<?php 	echo sprintf('.matrix-cell-%s { background: %s !important }', $abbreviation, $value); ?>
<?php endforeach; ?>

<?php foreach ($cssTxtColor as $abbreviation => $value) : ?>
<?php 	echo sprintf('.matrix-cell-%s, .matrix-cell-%s .text-muted { color: %s !important }', $abbreviation, $abbreviation, $value); ?>
<?php endforeach; ?>

<?php foreach ($cssTxtStyle as $abbreviation => $value) : ?>
<?php 	echo sprintf('.matrix-cell-%s { font-weight: %s }', $abbreviation, ($value ?: 'normal')); ?>
<?php endforeach; ?>
</style>

<div class="form-horizontal position-relative">
	<div class="row">
		<div class="col col-lg-4 col-xl-5">
			<h1 class="h4 d-inline-block mb-0 pb-0 mr-2" style="line-height:1.4">
			<?php if (!$isPrint) : ?>
				<?php if (empty($projectNum)) :
					echo ucfirst(
						sprintf('%s:', Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language))
					);
				else :
					echo ucfirst(
						sprintf(
							'%s:<span class="small ml-3">%s &ndash; %s</span>',
							Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language),
							html_entity_decode($project->get('number')),
							html_entity_decode($project->get('name'))
						)
					);
				endif; ?>
			<?php endif; ?>
			</h1>

			<?php if (FALSE) : ?>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=article.matrix.config&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); // config-link ?>"
			   role="button"
			   class="btn btn-secondary"
			   data-toggle="tooltip"
			   title="<?php echo Text::translate('COM_FTK_LABEL_EXPORT_SETTINGS_TEXT', $this->language); ?>"
			   style="vertical-align:super; opacity:0.4"
			>
				<i class="fas fa-sliders-h"></i>
				<span class="d-none"><?php echo Text::translate('COM_FTK_LABEL_EXPORT_SETTINGS_TEXT', $this->language); ?></span>
			</a>
			<?php endif; ?>
		</div>

		<div class="col col-lg-8 col-xl-7 pl-0">
			<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(
					sprintf( 'index.php?hl=%s&view=%s&layout=%s&project=%s&df=%s&dt=%s&tf=%s&tt=%s&return=%s',
					$this->language,
					$view->get('name'),
					$view->get('layout'),
					$projectNum,
					$data->get('dateFrom'),
					$data->get('dateTo'),
					$data->get('timeFrom'),
					$data->get('timeTo'),
					base64_encode(urldecode(View::getURI()))
				  ))); ?>"
				  method="get"
				  name="statsForm"
				  class="form-horizontal statsForm toolbarForm text-right"
				  id="statsForm"
				  data-submit=""
				  style="padding-top:0.05rem"
			>
				<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
				<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
				<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

				<div class="form-row">
					<div class="form-group mb-lg-0 col-md-2">
						<label for="project" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div>
							<select name="project"
									class="form-control form-control-sm position-relative auto-submit"
									id="inputProject"
									form="statsForm"<?php // FIXME - replace with calculated form name ?>
									data-lang="<?php echo $lang; ?>"
									data-target="#processedPartsList"
									tabindex="<?php echo ++$tabindex; ?>"
									autofocus
							>
								<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_PROJECT_TEXT', $this->language); ?> &ndash;</option>
								<?php array_walk($allProjects, function($project) use(&$projectNum) { ?>
								<?php	$p = new Registry($project); ?>
								<option value="<?php echo $p->get('number', ''); ?>"<?php echo ($p->get('number') == $projectNum ? ' selected' : ''); ?>><?php
									echo html_entity_decode($p->get('number', ''));
								?></option>
								<?php }); ?>
							</select>
						</div>
					</div>
					<div class="form-group mb-lg-0 col-md-3">
						<label for="quality" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_QUALITY_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div>
							<select name="quality"
									class="form-control form-control-sm position-relative auto-submit"
									id="inputQuality"
									form="statsForm"<?php // FIXME - replace with calculated form name ?>
									data-lang="<?php echo $lang; ?>"
									data-target="#processedPartsList"
									tabindex="<?php echo ++$tabindex; ?>"
							>
								<option value="">&ndash; <?php echo Text::translate('COM_FTK_LABEL_QUALITY_TEXT', $this->language); ?> &ndash;</option>
								<option value="good"<?php echo ($data->get('quality') == 'good' ? ' selected' : ''); ?>><?php
									echo Text::translate('COM_FTK_LABEL_GOOD_PARTS_TEXT', $this->language);
								?></option>
								<option value="bad"<?php  echo ($data->get('quality') ==  'bad' ? ' selected' : ''); ?>><?php
									echo Text::translate('COM_FTK_LABEL_BAD_PARTS_TEXT', $this->language);
								?></option>
							</select>
						</div>
					</div>
					<?php // Datepicker for the "from"-date ?>
					<div class="form-group mb-lg-0 col-md-3">
						<label for="dateFrom" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_FROM_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div class="input-group date position-relative"
							 data-provide="datepicker"
							 data-date-autoclose="true"
							 data-date-calendar-weeks="true"
							 data-date-clear-btn="false"
							 data-date-days-of-week-disabled="[]"
							 <?php // data-date-days-of-week-highlighted="[0,6]" ?>
							 data-date-end-date="<?php echo date_create('NOW', new DateTimeZone(FTKRULE_TIMEZONE))->format($localeConfig->get('date', 'd.m.Y')); // FIXME - backend can only handle 'd.m.Y' ?>"
							 data-date-format="dd.mm.yyyy"
							 data-date-language="<?php echo $lang; ?>"
							 data-date-today-btn="linked"
							 data-date-today-highlight="true"
							 data-date-week-start="1"
						>
							<input type="text"
								   name="dateFrom"
								   value="<?php echo htmlentities($data->get('dateFrom')); ?>"
								   class="form-control form-control-sm datepicker auto-submit text-right"
								   id="ipt-dateFrom"
								   form="statsForm"<?php // FIXME - replace with calculated form name ?>
								   placeholder="dd.mm.yyyy"
								   data-target="#processedPartsList"
								   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
								   aria-described-by="btn-pick-date"
								   tabindex="<?php echo ++$tabindex; ?>"
								   readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append">
								<span class="input-group-text" id="btn-pick-date" role="button">
									<i class="fas fa-calendar-alt"></i>
								</span>
							</div>
						</div>
					</div>
					<?php // Datepicker for the "to"-date ?>
					<div class="form-group mb-lg-0 col-md-3">
						<label for="dateTo" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_TO_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div class="input-group date position-relative"
							 data-provide="datepicker"
							 data-date-autoclose="true"
							 data-date-calendar-weeks="true"
							 data-date-clear-btn="false"
							 data-date-days-of-week-disabled="[]"
							 <?php // data-date-days-of-week-highlighted="[0,6]" ?>
							 data-date-end-date="<?php echo date_create('NOW', new DateTimeZone(FTKRULE_TIMEZONE))->format($localeConfig->get('date', 'd.m.Y')); // FIXME - backend can only handle 'd.m.Y' ?>"
							 data-date-format="dd.mm.yyyy"
							 data-date-language="<?php echo $lang; ?>"
							 data-date-today-btn="linked"
							 data-date-today-highlight="true"
							 data-date-week-start="1"
						>
							<input type="text"
								   name="dateTo"
								   value="<?php echo htmlentities($data->get('dateTo')); ?>"
								   class="form-control form-control-sm datepicker auto-submit text-right"
								   id="ipt-dateTo"
								   form="statsForm"<?php // FIXME - replace with calculated form name ?>
								   placeholder="dd.mm.yyyy"
								   data-target="#processedPartsList"
								   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECTED_DATE_TEXT', $this->language); ?>"
								   aria-described-by="btn-pick-date"
								   tabindex="<?php echo ++$tabindex; ?>"
								   readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append">
								<span class="input-group-text" id="btn-pick-date" role="button"><i class="fas fa-calendar-alt"></i></span>
							</div>
						</div>
					</div>
					<div class="form-group mb-lg-0 col-md-1">
					<?php if (!empty($projectNum) && !empty($data->get('dateFrom'))) : ?>
						<button type="submit"
						        class="btn btn-sm btn-secondary btn-autorefresh<?php echo sprintf(' %s', $isAutorefreshClass); ?>"
						        id="btnAutorefresh"
						        name="ar"
						        value="<?php echo ($isAutorefresh ? '0' : '1'); ?>"
						        title="<?php echo Text::translate(
						        	mb_strtoupper(
						        		sprintf('COM_FTK_LINK_TITLE_%s_AUTOREFRESH_MODE_TEXT', ($isAutorefresh ? 'DISABLE' : 'ENABLE'))
							        ),
									$this->language
						        ); ?>"
						        data-toggle="tooltip"
								<?php //data-bind="sessionStorage" ?>
						        tabindex="<?php echo ++$tabindex; ?>"
						>
							<i class="fas fa-history"></i>
						</button>
					<?php else : ?>
						<span class="btn btn-sm btn-secondary btn-autorefresh"
						        id="btnAutorefresh"
						        title="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_AND_START_DATE_TO_SEE_PROCESSED_PARTS_TEXT', $this->language); ?>"
						        data-toggle="tooltip"
						        tabindex="<?php echo ++$tabindex; ?>"
						>
							<i class="fas fa-history"></i>
						</span>
					<?php endif; ?>
					</div>
				</div>
			</form>
		</div>
	</div>

	<hr>
    <style>
        .thead-dark tr>th:first-child {
            position: sticky;
            left: 0;
            /* background: #f5f5f5; */
        }
        .pricetag tr>td:first-child {
            position: sticky;
            left: 0;
            background: #f5f5f5;
        }
        .thead-dark tr>th:nth-child(2) {
            position: sticky;
            left: 70px;
        }
        .pricetag tr>td:nth-child(2) {
            position: sticky;
            left: 70px;
            background: #f5f5f5;
        }
    </style>
	<div class="position-relative mt-lg-4" id="processedPartsList">
		<div>
		<?php if ($cnt = count($allArticles)) : ?>
		<?php 	$i = 1; ?>

		<?php foreach ($allArticles as $assembly => $articles) : ?>
		<?php // Get first article from list and prepare table head information for rendering.
		$article     = new Registry(current($articles));
		$number      = $article->get('number');
		$numberPcs   = explode('.', $number);
		$numberPcs[] = $article->get('drawingindex');

		// Mask process number and drawing index in article name (replace with 'x')
		array_walk($numberPcs, function($no, $i) use(&$numberPcs) {
			if ($i > 3) :
				$numberPcs[$i] = str_repeat('x', mb_strlen($no));
			endif;
		});

		$basename    = implode('.', array_slice($numberPcs, 0, 3));

		// Reset the array pointer.
		reset($allArticles);
		?>
		<form action=""
			  method="post"
			  name="editArticleMatrixForm"
			  class="form statsForm validate table-responsive"
			  id="editArticleMatrixForm"
			  data-submit=""
			  data-monitor-changes="true"
		>
			<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
			<input type="hidden" name="task"     value="matrixConfigExport" />
			<input type="hidden" name="proid"    value="<?php echo (int) $project->get('proID'); ?>" />
			<input type='hidden' name='return'   value="<?php echo base64_encode($return ?? $redirect); ?>" />
			<input type='hidden' name='fragment' value='' /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

			<div class="status-overlay"><?php // required for AJAX loading simulation ?>
				<table class="table table-bordered table-sm small" id="projArticlematrix" style="font-family:Verdana, sans-serif">
					<caption class="table-caption text-left pb-2">
						<span class="d-inline-block align-middle" style="padding-top:2px"><?php
							echo sprintf('%s: %s', Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language), html_entity_decode($basename));
						?></span>

						<?php // Button to save target values ?>
						<button type="submit"
								class="btn btn-sm btn-submit btn-save px-sm-2 px-md-3 allow-window-unload"
								title="<?php echo Text::translate('COM_FTK_LABEL_SAVE_TARGET_VALUES_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_LABEL_SAVE_TARGET_VALUES_TEXT', $this->language); ?>"
								data-toggle="tooltip"
						>
							<i class="fas fa-save"></i>
							<span class="d-none ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $this->language); ?></span>
						</button>

						<?php // Color legend ?>
						<aside class="my-legend float-right"
							   title="<?php echo sprintf(
									Text::translate('COM_FTK_HEADING_LEGEND_X_TEXT', $this->language),
									Text::translate('COM_FTK_LABEL_TARGET_VALUES_TEXT', $this->language)
							   ); ?>"
							   data-toggle="tooltip"
						>
							<h6 class="sr-only legend-title"><?php echo sprintf(
								Text::translate('COM_FTK_HEADING_LEGEND_X_TEXT', $this->language),
								Text::translate('COM_FTK_LABEL_TARGET_VALUES_TEXT', $this->language)); ?></h6>
							<div class="legend-scale">
								<ul class="legend-labels list-inline list-unstyled mb-2">
									<?php if (FALSE) : ?>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#FF4500"></span>
										<small class="d-block text-center">0&nbsp;&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#FFA700"></span>
										<small class="d-block text-center">1&nbsp;&ndash;&nbsp;39&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#FFD700"></span>
										<small class="d-block text-center">40&nbsp;&ndash;&nbsp;84&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#92D050"></span>
										<small class="d-block text-center">85&nbsp;&ndash;&nbsp;99&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#3CB371"></span>
										<small class="d-block text-center">100&nbsp;&ndash;&nbsp;109&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#C5D9F1"></span>
										<small class="d-block text-center">&gt;&nbsp;109&nbsp;&#37;</small>
									</li>
									<?php endif; ?>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#FFFFFF"></span>
										<small class="d-block text-center">0&nbsp;&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#FFA700"></span>
										<small class="d-block text-center">1&nbsp;&ndash;&nbsp;99&nbsp;&#37;</small>
									</li>
									<li class="list-inline-item mx-0 px-0">
										<span class="d-block legend-color" style="background:#92D050"></span>
										<small class="d-block text-center">&gt;=&nbsp;100&nbsp;&#37;</small>
									</li>
								</ul>
							</div>
							<?php if (FALSE) : ?>
							<?php // TODO - translate ?>
							<p class="legend-source d-none">Source: <a href="#link to source">Name of source</a></p>
							<?php endif; ?>
						</aside>
					</caption>
					<thead class="thead-dark">
						<tr>
							<th class="text-center" style="width:100px">
								<abbr title="<?php echo Text::translate('COM_FTK_LABEL_TARGET_VALUE_TEXT', $this->language); ?>"><?php
									echo Text::translate('COM_FTK_LABEL_NOMINAL_TEXT', $this->language);
								?></abbr>
							</th>
							<th class="text-center" style="width:100px"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->language); /*sprintf('%s.', $basename);*/ ?></th>
							<th class="text-center" style="width:60px">000</th>
							<?php foreach ($allProcessSteps as $step) : ?>
							<th class="text-center" style="width:60px"><?php  echo html_entity_decode(str_pad($step, 3, '0', STR_PAD_LEFT)); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody class="pricetag">
					<?php foreach ($articles as $i => $article) : ?><?php
						$article         = is_a($article, 'Joomla\Registry\Registry') ? $article : new Registry($article);   // ensure it's a Registry object for further usage
						$drawing         = $article->get('drawing');   // most likely will be a JSON-string here
						$drawing         = (isset($drawing) ? new Registry(json_decode((string) $article->get('drawing', ''), null, 512, JSON_THROW_ON_ERROR)) : null);   // ensure it's a Registry object for further usage

//						$customerDrawing = $article->get('customerDrawing');   // most likely will be a JSON-string here
//						$customerDrawing = (isset($customerDrawing) ? new Registry(json_decode((string) $article->get('customerDrawing', ''), null, 512, JSON_THROW_ON_ERROR)) : null);   // ensure it's a Registry object for further usage

						$number      = $article->get('number');
						$numberPcs   = explode('.', $number);
						$numberPcs[] = $article->get('drawingindex');

						// Mask process number and drawing index in article name (replace with 'x')
						array_walk($numberPcs, function($no, $i) use(&$numberPcs) {
							if ($i > 3) :
								$numberPcs[$i] = str_repeat('x', mb_strlen($no));
							endif;
						});

//						$type      = implode('.', array_slice($numberPcs, 3));  // will render e.g. 00100.xxx.x
						$type      = $numberPcs[3]; // will render elg. 00100

						$artProcesses = (array) json_decode($article->get('processes'), null, 512, JSON_THROW_ON_ERROR);
						$artProcesses = array_reverse($artProcesses);

						$processParts = $articleModel->getTotalPartsPerProcess(
							$article->get('artID'),
							$artProcesses,
							$data->get('dateFrom', ''),
							$data->get('dateTo',   ''),
							$data->get('quality',  '')
						);

						$artProcessesDrawings = (array) json_decode($article->get('drawings'), null, 512, JSON_THROW_ON_ERROR);

						$tmp = [];

						array_walk($artProcessesDrawings, function($artProcessDrawing) use(&$tmp) {
							$tmp[(int) $artProcessDrawing->step] = $artProcessDrawing;
						});

						ksort($tmp);

						$artProcessesDrawings = $tmp;

						// Free memory.
						unset($tmp);

						$threshold = ArrayHelper::getValue($usrThresholds, $number);
					?>
						<tr>
							<td scope="row" class="text-center align-top">
								<label for="config[project][matrix][thresholds][<?php echo html_entity_decode($number); ?>]" class="sr-only"><?php
									echo sprintf('config[project][matrix][thresholds][%s]', html_entity_decode($number));
								?></label>
								<input type="text"
									   name="config[project][matrix][thresholds][<?php echo html_entity_decode($number); ?>]"
									   class="form-control form-control-sm text-left"
									   id="<?php echo $attrID = sprintf('ipt-threshold-%s', str_ireplace('.', '_', $number)); ?>"
									   value="<?php echo $threshold; ?>"
									   placeholder="0"
									   pattern="[0-9]{0,5}"
									   minlength="1"
									   maxlength="5"
									   min="0"
									   max="99999"
									   step="1"
									   title="<?php echo Text::translate('COM_FTK_HINT_DEFINE_TARGET_VALUE_TEXT', $this->language); ?>"
									   data-toggle="tooltip"
									   data-bind="dataMirroring"
									   data-rule-pattern="<?php echo '[0-9]{0,5}'; ?>"
									   data-msg-pattern="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_PATTERN_ONLY_DIGITS_TEXT', $this->language); ?>"
									   <?php // Hide tooltip on input ?>
									   onfocus="(function($) { $('<?php echo sprintf('#%s', str_ireplace('.', '_', $attrID)); ?>').tooltip('hide'); })(window.jQuery);"
								/>
							</td>
							<td scope="row" class="text-center align-top">
							<?php // If drawing file (e.g. PDF) is available, render link to drawing file... ?>
							<?php if (is_a($drawing, 'Joomla\Registry\Registry') && $drawing->get('file')) : ?>
								<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( '%s?t=%d', $drawing->get('file'), mt_rand(0, 9999999) ))); ?>"
								   class="d-block"
								   data-toggle="tooltip"
								   title="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_X_DRAWING_TEXT', $this->language), html_entity_decode($number)); ?>"
								   aria-label="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_X_DRAWING_TEXT', $this->language), html_entity_decode($number)); ?>"
								   target="_blank"
								><?php echo html_entity_decode($type); ?>
								</a>
							<?php // ...otherwise display article name as tooltip ?>
							<?php else : ?>
								<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php echo html_entity_decode($number); ?>">.<?php
									echo html_entity_decode($type);
								?></abbr>
							<?php endif; ?>
							</td>
							<td class="text-center align-top matrix-cell" data-process-abbreviation="pms">
								<abbr class="d-inline-block text-decoration-none"
									  data-toggle="tooltip"
									  title="<?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_SPECIFICATIONS_TEXT', $this->language); ?>"
								><?php echo sprintf(
									'<span class="text-muted">pms</span><br><strong class="parts-processed d-inline-block pt-2">%s</strong>',
									// (is_numeric($processPartsCountAbsolute) ? $processPartsCountAbsolute : '')
									''
								); ?></abbr>
							</td>
							<?php foreach ($allProcessSteps as $step) : ?><?php
								$artProcessesDrawing = ArrayHelper::getValue($artProcessesDrawings, (int) $step);
								$artProcessesDrawing = new Registry($artProcessesDrawing);

								$process = ArrayHelper::getValue($allProcesses, $artProcessesDrawing->get('procID'));
								$process = new Registry($process);

								$processPartsCountAbsolute = ArrayHelper::getValue($processParts, $process->get('procID'));
								$processPartsCountRelative = null;

								if (is_numeric($threshold) && $threshold > 0 && is_numeric($processPartsCountAbsolute) && $processPartsCountAbsolute > 0) :
									if ((int) $processPartsCountAbsolute == 0) :
										$processPartsCountRelative = 0;
									elseif ((int) $processPartsCountAbsolute == 1) :
										$processPartsCountRelative = 1;
									elseif ((int) $threshold > 0) :
										$processPartsCountRelative = ((int) $processPartsCountAbsolute * 100) / (int) $threshold;
									else :
										$processPartsCountRelative = 0;
									endif;
								endif;

								if (!is_null($processPartsCountRelative)) :
									$processPartsCountRelative = ceil($processPartsCountRelative);
								endif;

								// NOTE:    To change colours in legend and the colouring in here just change definitions in-app config file +
								//          number range in JavaScript event handlers '[data-bind="dataMirroring"]'.
								//			Changes to this code are not necessary.
								$percentage = $calcPercentage($appThresholds, (is_numeric($processPartsCountRelative) ? $processPartsCountRelative : 0));
							?>
							<td class="text-center align-top matrix-cell<?php
//								echo     ($process->get('abbreviation') ? sprintf(' matrix-cell-%s', $process->get('abbreviation')) : '');	// DiSABLED on 2023-08-02 - replaced by next line
								echo sprintf(' matrix-cell-%s', $process->get('abbreviation', ''));

								/*// DiSABLED on 2023-08-02 - replaced by next line
								if (is_numeric($threshold)) :
									echo ($process->get('abbreviation') ? sprintf(' matrix-cell-%s', $percentage) : '');
								else :
									echo ($process->get('abbreviation') ? sprintf(' matrix-cell-%%PERCENT%%', '') : '');
								endif;*/
								echo is_numeric($threshold) ? sprintf(' matrix-cell-%s', $percentage) : sprintf(' matrix-cell-%%PERCENT%%', '');
								?>"
								data-process-abbreviation="<?php echo $process->get('abbreviation', ''); ?>"
								data-percentage="<?php echo $percentage; ?>"
							>
								<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
										sprintf( 'index.php?hl=%s&view=statistics&layout=article.process.parts&project=%s&aid=%d&pid=%d&quality=%s&df=%s&dt=%s&tf=%s&tt=%s&return=%s',
										$lang,
										$project->get('number'),
										$article->get('artID'),
										$process->get('procID'),
										$data->get('quality'),
										$data->get('dateFrom'),
										$data->get('dateTo'),
										$data->get('timeFrom'),
										$data->get('timeTo'),
										base64_encode(urldecode(View::getURI()))
								   ))); ?>"
								   class="text-decoration-none"
								   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_LIST_TEXT', $this->language); ?>"
								><?php echo sprintf(
										'<span class="text-muted">%s</span><br><strong class="parts-processed d-inline-block pt-2">%s</strong>',
										$process->get('abbreviation', ''),
										(is_numeric($processPartsCountAbsolute) ? $processPartsCountAbsolute : ''));
								?></a>
							</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</form>
		<?php $i += 1; ?>
		<?php endforeach; ?>
		<?php else : ?>
			<?php if ($cnt == 0 && !empty($projectNum) && !empty($data->get('dateFrom'))) : ?>
				<?php echo LayoutHelper::render('system.alert.info', [
					'message' => Text::translate('COM_FTK_HINT_PROJECT_HAS_NO_PARTS_TEXT', $this->language),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php else : ?>
				<?php switch (true) : ?><?php
				case  empty($projectNum) &&  empty($data->get('dateFrom')) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_AND_START_DATE_TO_SEE_PROCESSED_PARTS_TEXT', $this->language)];
				break;

				case  empty($projectNum) && !empty($data->get('dateFrom')) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_TO_SEE_PROCESSED_PARTS_TEXT', $this->language)];
				break;

				case !empty($projectNum) &&  empty($data->get('dateFrom')) :
					$data = ['message' => Text::translate('COM_FTK_HINT_PLEASE_SELECT_START_DATE_TEXT', $this->language)];
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
    <?php echo "<script>
    var pricetagRows = document.querySelectorAll('.pricetag tr');

    // Loop through each tr element
    pricetagRows.forEach(function(row) {
        // Find all strong elements with class 'parts-processed' within the current tr
        var partsProcessedElements = row.querySelectorAll('.parts-processed');

        // Check if all the strong elements have no value
        var allEmpty = Array.from(partsProcessedElements).every(function(element) {
            return element.textContent.trim() === '';
        });
        console.log(allEmpty);

        // If all the strong elements have no value, hide the current tr element
        if (allEmpty) {
            row.style.display = 'none';
        }
    });
</script>"; ?>
<?php // Free memory
unset($allArticles);
unset($allArticlesGrouped);
unset($allProcesses);
unset($allProcessSteps);
unset($allProjects);
unset($appConfig);
unset($appMatrixConfig);
unset($appThresholds);
unset($article);
unset($articleModel);
unset($articleProcesses);
unset($articleProcessSteps);
unset($artProcessesDrawing);
unset($artProcessesDrawings);
unset($calcPercentage);
unset($data);
unset($defaults);
unset($drawing);
unset($input);
unset($matrixConfig);
unset($model);
unset($process);
unset($processParts);
unset($project);
unset($project);
unset($user);
unset($userProfile);
unset($usrMatrixConfig);
unset($view);
