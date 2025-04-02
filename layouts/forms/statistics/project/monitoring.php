<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task')  ?? ($input->getCmd('task') ?? null);
$format   = $input->post->getWord('format', $input->getWord('format'));
/*$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);*/

$isPrint  = $task === 'print';
?>
<?php /* Access check */
$formData = null;

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
<?php /* Load view data */
$data         = $view->get('data');
$tmp          = [];
$dateFrom     = $data->get('dateFrom');
$dateTo       = $data->get('dateTo');
$projects     = $data->get('projects',  []);
$projects     = array_filter($projects, function($project, $proid) use(&$tmp) {
	$tmp[$proid] = ArrayHelper::getValue($project, 'number');

	return true;
}, ARRAY_FILTER_USE_BOTH); asort($tmp);
$projects     = $tmp; $tmp = [];
$processes    = $data->get('processes', []);
$processes    = array_filter($processes, function($process, $pid) use(&$tmp) {
	$tmp[$pid] = ArrayHelper::getValue($process, 'abbreviation');

	return true;
}, ARRAY_FILTER_USE_BOTH);  asort($tmp);
$processes    = $tmp; $tmp = [];
$selProjects  = $input->get('proids', [], 'ARRAY');
$selProcesses = $input->get('pids',   [], 'ARRAY');

// Init tabindex
$tabindex     = 0;
?>

<style>
.col-form-label {
	background-color: #e8edf3;
	border-color: #dee3e9 #dee3e9 #dee2e6;
	color: #30588B;
}

.stats-monitor-dates .validation-result.text-danger {
	background: #e9ecef;
	padding-bottom: .3rem;
}
.stats-monitor-processes .validation-result.text-danger,
.stats-monitor-projects .validation-result.text-danger {
	top: 0!important;
	width: auto;
	margin-top: -2rem!important;
	background: crimson;
	color: white!important;
	font-size: 1rem;
	margin-left: -2px;
	/*padding-left: .75rem;
	padding-right: .75rem;*/
	right: 0!important; /* this will cause the element to span over full parent element width */
	padding: .25rem .75rem;
}

.projectForm.submitted:before {
	content: url('data:image/gif;base64,R0lGODlhKwALAPEAAAAAAP///3x8fP///yH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAKwALAAACMoSOCMuW2diD88UKG95W88uF4DaGWFmhZid93pq+pwxnLUnXh8ou+sSz+T64oCAyTBUAACH5BAkKAAAALAAAAAArAAsAAAI9xI4IyyAPYWOxmoTHrHzzmGHe94xkmJifyqFKQ0pwLLgHa82xrekkDrIBZRQab1jyfY7KTtPimixiUsevAAAh+QQJCgAAACwAAAAAKwALAAACPYSOCMswD2FjqZpqW9xv4g8KE7d54XmMpNSgqLoOpgvC60xjNonnyc7p+VKamKw1zDCMR8rp8pksYlKorgAAIfkECQoAAAAsAAAAACsACwAAAkCEjgjLltnYmJS6Bxt+sfq5ZUyoNJ9HHlEqdCfFrqn7DrE2m7Wdj/2y45FkQ13t5itKdshFExC8YCLOEBX6AhQAADsAAAAAAAAAAAA=');
	position: absolute;
	z-index: 1;
    left: -1%;
	width: 102%;
    height: 102%;
	padding-top: 5%;
	background-color: rgba(245,245,245,0.7);
	box-shadow: 0 0 10px 10px rgb(245 245 245 / 70%);
	backdrop-filter: blur(4px);
	text-align: center;
}
</style>

<div class="form-horizontal position-relative">
	<div class="row">
		<div class="col col-lg-7">
			<h1 class="h4 d-inline-block mb-0 pb-0 mr-2" style="line-height:1.4"><?php
				echo ucfirst(
					sprintf('%s:<span class="small ml-3">%s</span>',
						Text::translate('COM_FTK_HEADING_PROJECT_MONITORING_TEXT', $lang),
						Text::translate('COM_FTK_HEADING_COMPOSE_DATA_FILE_TEXT',  $lang)
					)
				);
			?></h1>
		</div>
		<div class="col col-lg-5 pl-0">
			<button type="submit"
					class="btn btn-info btn-submit allow-window-unload float-right"
					form="projectMonitoringForm"
					data-bind="toggleSubmitted"
					data-target="#projectMonitoringForm"
			>
				<i class="fas fa-download"></i>
				<span class="d-none d-md-inline ml-lg-1"><?php
					echo Text::translate('COM_FTK_BUTTON_TEXT_CREATE_DATA_FILE_TEXT', $lang);
				?></span>
			</button>
		</div>
	</div>

	<hr>

	<div class="position-relative mt-lg-4">
		<form method="get"
			  name="projectMonitoringForm"
			  class="form projectForm toolbarForm validate"
			  id="projectMonitoringForm"
			  data-submit=""
			  data-monitor-changes="false"
		>
			<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
			<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
			<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

			<!--div class="fields"--><?php // This wrapper is required. It'll create the visual feedback on form submitted. Do NOT USE,
			// 'cuz the XLS-Generator exits the app after execution causing the loader icon to display forever thus blocking the form ?>
				<?php // Date picker ?>
				<fieldset class="stats-monitor stats-monitor-dates mb-sm-3 mb-lg-4 mb-xl-5">
					<legend class="h6"></legend>
					<div class="form-row">
						<div class="col col-lg-4 form-group mb-lg-0">
							<label for="dateFrom" class="h6"><?php echo Text::translate('COM_FTK_LABEL_SELECT_START_DATE_TEXT', $lang); ?>:&nbsp;&ast;</label>
							<div class="input-group date position-relative"
							     data-provide="datepicker"
							     data-date-language="<?php echo $lang; ?>"
							     data-date-week-start="1"
							     data-date-days-of-week-disabled="[]"
	                             <?php // data-date-days-of-week-highlighted="[0,6]" ?>
	                             data-date-format="dd.mm.yyyy"
	                             data-date-autoclose="true"
	                             data-date-calendar-weeks="true"
	                             data-date-clear-btn="false"
	                             data-date-today-highlight="true"
	                             data-date-today-btn="linked"
	                             data-date-end-date="<?php echo date_create('NOW', new DateTimeZone(FTKRULE_TIMEZONE))->format('d.m.Y'); ?>"
							>
								<input type="text"
								       name="dateFrom"
								       value="<?php echo htmlentities($data->get('dateFrom')); ?>"
								       class="form-control datepicker"
								       id="ipt-dateFrom"
								       form="projectMonitoringForm"
								       placeholder="dd.mm.yyyy"
								       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $lang); ?>"
								       aria-described-by="btn-pick-date"
								       tabindex="<?php echo ++$tabindex; ?>"
								       readonly
								       required
								       data-rule-required="true"
								       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_START_DATE_TEXT', $lang); ?>"
								/>
								<div class="input-group-append">
									<span class="input-group-text" id="btn-pick-date" role="button"><i class="fas fa-calendar-alt"></i></span>
								</div>
							</div>
						</div>
						<div class="col col-lg-4 form-group mb-lg-0">
							<label for="dateTo" class="h6"><?php
								echo sprintf('%s <em class="text-muted">(%s: %s)</em>',
									Text::translate('COM_FTK_LABEL_SELECT_END_DATE_TEXT', $lang),
									Text::translate('COM_FTK_LABEL_STANDARD_TEXT', $lang),
									Text::translate('COM_FTK_DATE_TODAY_TEXT', $lang)
								);
							?>:</label>
							<div class="input-group date position-relative"
							     data-provide="datepicker"
							     data-date-language="<?php echo $lang; ?>"
							     data-date-week-start="1"
							     data-date-days-of-week-disabled="[]"
	                             <?php // data-date-days-of-week-highlighted="[0,6]" ?>
	                             data-date-format="dd.mm.yyyy"
	                             data-date-autoclose="true"
	                             data-date-calendar-weeks="true"
	                             data-date-clear-btn="false"
	                             data-date-today-highlight="true"
	                             data-date-today-btn="linked"
	                             data-date-end-date="<?php echo date_create('NOW', new DateTimeZone(FTKRULE_TIMEZONE))->format('d.m.Y'); ?>"
							>
								<input type="text"
								       name="dateTo"
								       value="<?php echo htmlentities($data->get('dateTo')); ?>"
								       class="form-control datepicker"
								       id="ipt-dateTo"
								       form="projectMonitoringForm"
								       placeholder="dd.mm.yyyy"
								       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECTED_DATE_TEXT', $lang); ?>"
								       aria-described-by="btn-pick-date"
								       tabindex="<?php echo ++$tabindex; ?>"
								       readonly
								       data-rule-required="false"
								       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_END_DATE_TEXT', $lang); ?>"
								/>
								<div class="input-group-append">
									<span class="input-group-text" id="btn-pick-date" role="button"><i class="fas fa-calendar-alt"></i></span>
								</div>
							</div>
						</div>
					</div>
				</fieldset>
				<?php // Projects list ?>
				<fieldset class="stats-monitor stats-monitor-projects mb-sm-3 mb-lg-4 mb-xl-5" style="padding-left:1px">
					<legend class="h6"><?php echo Text::translate('COM_FTK_LABEL_SELECT_PROJECTS_TEXT', $lang); ?></legend>
					<div class="input-group position-relative" style="margin-left:-10px<?php // Fix the offset created by the button margin ?>">
					<?php array_walk($projects, function($number, $proID) use(&$selProjects) { ?>
						<div class="btn btn-checkbox btn-<?php echo (($checked = in_array($proID, $selProjects)) == '1') ? 'info' : 'secondary'; ?> p-0"
						     data-bind="toggleChecked"
						     data-toggle="tooltip"
						     title="add this project"
						     style="min-width:80px; margin:10px"
						>
							<label class="checkbox-label text-center m-0 px-3 py-4"
							       role="button"
							       style="flex-grow:1; flex-shrink:1; flex-basis:0"
							>
								<input type="checkbox"
								       class="form-control monitor-project sr-only"
								       id="<?php echo sprintf('proID-%d', $proID); ?>"
								       name="proids[]"
								       value="<?php echo (int) $proID; ?>"
								       <?php echo ($checked == '1') ? 'checked' : ''; ?>
								/>
								<?php echo mb_strtoupper($number); ?>
							</label>
						</div>
					<?php }); ?>
					</div>
				</fieldset>
				<?php // Processes list ?>
				<fieldset class="stats-monitor stats-monitor-processes mb-2" style="padding-left:1px">
					<legend class="h6"><?php echo Text::translate('COM_FTK_LABEL_SELECT_PROCESSES_TEXT', $lang); ?></legend>
					<div class="input-group position-relative" style="margin-left:-10px<?php // Fix the offset created by the button margin ?>">
					<?php array_walk($processes, function($abbreviation, $procID) use(&$selProcesses) { ?>
						<div class="btn btn-checkbox btn-<?php echo (($checked = in_array($procID, $selProcesses)) == '1') ? 'info' : 'secondary'; ?> p-0"
						     data-bind="toggleChecked"
						     data-toggle="tooltip"
						     title="add this process"
						     style="min-width:80px; margin:10px"
						>
							<label class="checkbox-label text-center m-0 px-3 py-4"
							       role="button"
							       style="flex-grow:1; flex-shrink:1; flex-basis:0"
							>
								<input type="checkbox"
								       class="form-control monitor-process sr-only"
								       id="<?php echo sprintf('procID-%d', $procID); ?>"
								       name="pids[]"
								       value="<?php echo (int) $procID; ?>"
								       <?php echo ($checked == '1') ? 'checked' : ''; ?>
								/>
								<?php echo mb_strtoupper($abbreviation); ?>
							</label>
						</div>
					<?php }); ?>
					</div>
				</fieldset>
			<!--/div-->
		</form>
	</div>
</div>

<?php // Free memory
// unset($processes);
// unset($projects);
// unset($tmp);
?>

<?php // Add user selection to the view data object.
$data->set('selProjects',  $selProjects);
$data->set('selProcesses', $selProcesses);

// Include Excel file generator.
if (isset($dateFrom) && count($selProjects) && count($selProcesses)) : ?>
	<?php include_once __DIR__ . DIRECTORY_SEPARATOR . 'monitoring' . DIRECTORY_SEPARATOR . 'xlsgenerator.php'; ?>
<?php endif; ?>
