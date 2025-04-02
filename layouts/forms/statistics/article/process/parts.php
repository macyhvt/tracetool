<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Model\Lizt as ListModel;
use  \Text;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view     = $this->__get('view');
$input    = $view->get('input');
$return   = $input->getBase64('return') ? $view->getReturnPage() : View::getReferer();	// Browser back-link required for back-button.
$model    = $view->get('model');
$user     = $view->get('user');

$formData = null;
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
<?php /* Process form data */
?>
<?php /* Prepare view data */
$data = $view->get('data');
$list = $data->get('parts');
?>
<?php /* Assign refs. */
$this->data     = $data;
$this->formData = $formData;
$this->view     = $view;
?>

<?php // TODO - consolidate dupe styles into styles.css (currently dupe in tracking.processes.php, tracking.process.php and here) ?>
<style>
.dropdown > .dropdown-toggle::after,
form > .dropdown > .dropdown-toggle::after {
	display: none!important;
}
.dropdown > .dropdown-menu.show,
form > .dropdown > .dropdown-menu.show {
	box-shadow: 0 7px 15px 1px rgba(128,128,128,0.5);
}
.dropdown > .dropdown-menu.show label:hover,
.dropdown > .dropdown-menu.show label:hover > input,
form > .dropdown > .dropdown-menu.show label:hover,
form > .dropdown > .dropdown-menu.show label:hover > input {
	cursor: pointer
}

/** Bootstrap Timepicker override */
.bootstrap-timepicker-widget.dropdown-menu {
	/*min-width: 9rem;*/
	min-width: 7rem;
}
.bootstrap-timepicker-widget.dropdown-menu.open,
.datepicker.dropdown-menu {
	box-shadow: 0 0 10px 1px rgba(0, 0, 0, .1);
}

.bootstrap-timepicker-widget.dropdown-menu {
	border-color: rgba(0, 0, 0, .15);
    padding: .2rem .5rem;	/* for timepickers with showMeridian set to true, which render min. 2 inputs + AM/PM selection */
}

.bootstrap-timepicker-widget.dropdown-menu.open {
	margin-top: 1px; /* fix height offset according to datepicker positioning */
}

.bootstrap-timepicker-widget.dropdown-menu table {
	width: 90%;
	margin: 0 auto;
    font-size: 80%;
}

.bootstrap-timepicker-widget.dropdown-menu table td.separator {
    padding: 0.3rem;
}

.bootstrap-timepicker-widget.dropdown-menu table td:not(.separator) {
    padding: unset;
}

.bootstrap-timepicker-widget.dropdown-menu table td a {
    text-decoration: none!important;
	border: unset;
	padding-top: unset;
	padding-bottom: unset;
}

.bootstrap-timepicker-widget.dropdown-menu table td a:hover {
    background-color: unset;
}

.bootstrap-timepicker-widget input[type] {
	border-radius: 4px;
	display: block;
	height: calc(1.5em + 0.5rem + 2px);
	margin: 0;
	min-width: 2rem;
	width: 2rem;
    background-clip: padding-box;
    background-color: #fff;
    border-radius: 0.25rem;
    border: 1px solid #ced4da;
    color: #495057;
    direction: ltr;
    display: inline-block;
    font-size: .875rem;
    font-weight: 400;
    height: calc(1.5em + 0.5rem + 2px);
    height: calc(1.5em + 0.75rem + 2px);
    line-height: 1.5;
    padding: unset;
    transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    vertical-align: middle;
    width: 2.25rem;
}

.bootstrap-timepicker-widget input[type]:focus,
.bootstrap-timepicker-widget input[type]:focus-visible,
.bootstrap-timepicker-widget input[type]:focus-within {
	/* adapt TWBS default style */
	border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgb(0 123 255 / 25%);
    outline: 0;
}

.bootstrap-timepicker-widget .glyphicon {
	font-family: "Font Awesome 5 Free";
    font-weight: 900;
    -moz-osx-font-smoothing: grayscale;
    -webkit-font-smoothing: antialiased;
    display: inline-block;
    font-style: normal;
    font-variant: normal;
    text-rendering: auto;
    line-height: 1;
}

.bootstrap-timepicker-widget .glyphicon-chevron-up:before {
    content: "\f106";
}
.bootstrap-timepicker-widget .glyphicon-chevron-down:before {
    content: "\f107";
}

/* for timepickers with showMeridian set to false, which render min. 2 inputs */
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(1) td:last-of-type .glyphicon:before,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:last-of-type   td:last-of-type .glyphicon:before {
	/*margin-right: 1.8rem;*/
}
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:last-of-type input[type] {
	/*margin-right: .35rem;*/
}
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:last-of-type:after {
	content: "Uhr";
	vertical-align: middle;
    font-size: .875rem;
}

/* RESET for timepickers with showMeridian set to true, which render min. 2 inputs + AM/PM selection */
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(1) td:nth-of-type(4) .glyphicon:before,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(1) td:nth-of-type(5) .glyphicon:before,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:last-of-type   td:nth-of-type(4) .glyphicon:before,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:last-of-type   td:nth-of-type(5) .glyphicon:before,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:nth-of-type(4) input[type],
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:nth-of-type(5) input[type] {
	margin-right: unset;
}
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:nth-of-type(4):after,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:nth-of-type(5):after {
	display: none!important;
}

.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(1) td:last-of-type .glyphicon:before,
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:last-of-type   td:last-of-type .glyphicon:before {
	/*margin-right: 1.8rem;*/
}
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:last-of-type input[type] {
	/*margin-right: .35rem;*/
}
.bootstrap-timepicker-widget.dropdown-menu table tbody tr:nth-of-type(2) td:last-of-type:after {
	display: none!important;
	content: "Uhr";
	vertical-align: middle;
    font-size: .875rem;
}

td .sample-part-badge {
	letter-spacing: 0.2rem; color: rgba(255, 215, 0, 1);
	padding: .3rem .7rem .35rem .9rem;
	background: #CDE4FF;	/* main menu color 10% lightened */
	color: #30588B;
	box-shadow: 0 0 3px 1px rgba(48, 88, 139, 0.3);
}

@media only screen and (max-width: 1199px) {
	.h3.viewTitle {
		font-size: 1.5rem
	}
}
@media only screen and (max-width: 991.98px) {
	table.table {
		font-size: 90%;
	}
}
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=statistics&layout=article.process.parts&project=%s&aid=%d&pid=%d&quality=%s&df=%s&dt=%s',
		$this->language,
		$this->data->get('project')->get('number'),
		$this->data->get('article')->get('artID'),
		$this->data->get('process')->get('procID'),
		$this->data->get('quality'),
		$this->data->get('dateFrom'),
		$this->data->get('dateTo')
	  ))); ?>"
	  method="get"
	  name="statsForm"
	  class="form-horizontal position-relative statsForm"
	  id="statsForm"
	  data-submit=""
>
	<input type="hidden" name="hl"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="view"    value="<?php echo $view->get('name'); ?>" />
	<input type="hidden" name="layout"  value="<?php echo $view->get('layout'); ?>" />
	<input type="hidden" name="project" value="<?php echo $this->data->get('project')->get('number'); ?>" />
	<input type="hidden" name="aid"     value="<?php echo $this->data->get('article')->get('artID'); ?>" />
	<input type="hidden" name="pid"     value="<?php echo $this->data->get('process')->get('procID'); ?>" />
	<input type="hidden" name="quality" value="<?php echo $this->data->get('quality'); ?>" />
	<input type="hidden" name="df"      value="<?php echo $this->data->get('dateFrom'); ?>" />
	<input type="hidden" name="dt"      value="<?php echo $this->data->get('dateTo'); ?>" />
	<input type="hidden" name="tf"      value="<?php echo $this->data->get('timeFrom'); ?>" />
	<input type="hidden" name="tt"      value="<?php echo $this->data->get('timeTo'); ?>" />

	<div class="row">
		<?php // Back-link + View title ?>
		<div class="col-md-12 col-lg-5 col-xl-6 mb-2 mb-lg-0">
			<?php // Back-link ?>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL($return) ); ?>"
			   role="button"
			   class="btn btn-sm btn-link align-bottom"
			   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
			   style="color:inherit!important"
			>
				<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_BACK_TEXT', $this->language); ?></span>
			</a>

			<?php // View title ?>
			<h1 class="h3 viewTitle d-inline-block my-0 mr-3"><?php echo sprintf('%s:<span class="small ml-3">%s</span>',
				Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language),
				html_entity_decode($this->data->get('process')->get('name'))
			); ?></h1>
		</div>

		<?php // Date- and time-picker(s) ?>
		<div class="col-md-12 col-lg-7 col-xl-6 pt-xl-1">
			<div><?php // required for AJAX loading simulation ?>
				<div class="form-row align-items-center">
					<?php // Datepicker for the "from"-date ?>
					<div class="col-7 col-md-3 offset-lg-1">
						<label for="df" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_DATE_FROM_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div class="input-group bootstrap-datepicker date"
							 data-provide="datepicker"
							 data-date-autoclose="true"
							 data-date-calendar-weeks="true"
							 data-date-clear-btn="false"
							 data-date-days-of-week-disabled="[]"
							 <?php // data-date-days-of-week-highlighted="[0,6]" ?>
							 data-date-end-date="<?php echo htmlentities($data->get('dateToday')); ?>"
							 data-date-format="dd.mm.yyyy"
							 data-date-language="<?php echo $this->language; ?>"
							 data-date-show-on-focus="false"
							 data-date-today-btn="linked"
							 data-date-today-highlight="true"
							 data-date-week-start="1"
							 data-style="margin-top:-0.25rem"
						>
							<input type="text"
								   name="df"
								   value="<?php echo htmlentities($data->get('dateFrom')); ?>"
								   class="form-control form-control-sm datepicker<?php /* auto-submit */ ?> right-radius-0 text-right"
								   id="ipt-df"
								   form="statsForm"<?php // FIXME - replace with calculated form name ?>
								   placeholder="dd.mm.yyyy"
								   data-target="#statsForm"
								   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
								   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
								   aria-described-by="btn-pick-date"
								   readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append">
								<span class="input-group-text" id="btn-pick-date" role="button">
									<i class="fas fa-calendar-alt"></i>
								</span>
							</div>
						</div>
					</div>

					<?php // The "from"-time ?>
					<div class="col-3 col-md-2">
						<label for="tf" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_TIME_FROM_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<span class="form-control form-control-sm text-lowercase text-right readonly"><?php echo htmlentities($data->get('timeFrom')); ?></span>
					</div>

					<?php // Datepicker for the "to"-date ?>
					<div class="col-7 col-md-3">
						<label for="dt" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_DATE_TO_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div class="input-group bootstrap-datepicker date"
							 data-provide="datepicker"
							 data-date-autoclose="true"
							 data-date-calendar-weeks="true"
							 data-date-clear-btn="false"
							 data-date-days-of-week-disabled="[]"
							 <?php // data-date-days-of-week-highlighted="[0,6]" ?>
							 data-date-end-date="<?php echo htmlentities($data->get('dateToday')); ?>"
							 data-date-format="dd.mm.yyyy"
							 data-date-language="<?php echo $this->language; ?>"
							 data-date-show-on-focus="false"
							 data-date-today-btn="linked"
							 data-date-today-highlight="true"
							 data-date-week-start="1"
							 data-style="margin-top:-0.25rem"
						>
							<input type="text"
								   name="dt"
								   value="<?php echo htmlentities($data->get('dateTo')); ?>"
								   class="form-control form-control-sm datepicker<?php /* auto-submit */ ?> right-radius-0 text-right"
								   id="ipt-dt"
								   form="statsForm"<?php // FIXME - replace with calculated form name ?>
								   placeholder="dd.mm.yyyy"
								   data-target="#statsForm"
								   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
								   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
								   aria-described-by="btn-pick-date"
								   readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append">
								<span class="input-group-text" id="btn-pick-date" role="button">
									<i class="fas fa-calendar-alt"></i>
								</span>
							</div>
						</div>
					</div>

					<?php // The "to"-time ?>
					<div class="col-3 col-md-2">
						<label for="tt" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_TIME_TO_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<span class="form-control form-control-sm text-lowercase text-right readonly"><?php echo htmlentities($data->get('timeTo')); ?></span>
					</div>

					<?php /* Submit button */ ?>
					<div class="col-auto">
						<button type="button"
								class="btn btn-sm btn-secondary"
								form="statsForm"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_APPLY_SELECTED_DATETIME_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_APPLY_SELECTED_DATETIME_TEXT', $this->language); ?>"
								onclick="(function(){ document.forms.statsForm.className += ' submitted'; document.forms.statsForm.submit() })();"
						>
							<i class="fas fa-sync-alt"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SUBMIT_TEXT', $this->language); ?></span>
						</button>
					</div>

					<?php /* Filter for "dead" entries */ ?>
					<?php if (FALSE) : ?>
					<div class="col-auto ml-lg-4 pl-lg-3">
						<div class="dropdown d-inline-block"
							 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
							 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
							 data-toggle="tooltip"
						>
							<button type="button"
									form="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('formName'))); ?>"
									class="btn btn-sm btn-secondary dropdown-toggle align-baseline"
									id="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('name'))); ?>Button"
									data-toggle="dropdown"
									aria-haspopup="true"
									aria-expanded="false"
									style="vertical-align:super; opacity:0.4"
									tabindex="<?php echo ++$tabindex; ?>"
							>
								<i class="fas fa-filter"></i>
								<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_FILTER_TEXT', $this->language); ?></span>
							</button>
							<div class="dropdown-menu dropdown-filter mt-0" data-multiple="false" aria-labelledby="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('name'))); ?>Button">
								<label for="filter"
									   class="d-block small mx-3"
									   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_TEXT', $this->language); ?>"
									   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_TEXT', $this->language); ?>"
									   data-toggle="tooltip"
								>
									<input type="checkbox"
										   class="align-middle mr-1 auto-submit"
										   id="cb-filter-active"
										   name="filter"
										   form="statsForm"<?php // FIXME - replace with calculated form name ?>
										   value="<?php echo ListModel::FILTER_ACTIVE; ?>"
										   autocomplete="off"
										   <?php echo ($data->get('filter') == ListModel::FILTER_ACTIVE) ? ' checked' : ''; ?>
									/>
									<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_LABEL', $this->language); ?></span>
								</label>
								<label for="filter"
									   class="d-block small mx-3"
									   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_EMPTY_ITEMS_TEXT', $this->language); ?>"
									   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_EMPTY_ITEMS_TEXT', $this->language); ?>"
									   data-toggle="tooltip"
								>
									<input type="checkbox"
										   class="align-middle mr-1 auto-submit"
										   id="cb-filter-active"
										   name="filter"
										   form="statsForm"<?php // FIXME - replace with calculated form name ?>
										   value="<?php echo ListModel::FILTER_EMPTY; ?>"
										   autocomplete="off"
										   <?php echo ($data->get('filter') == ListModel::FILTER_EMPTY) ? ' checked' : ''; ?>
									/>
									<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_EMPTY_ITEMS_LABEL', $this->language); ?></span>
								</label>

								<label for="filter"
									   class="d-block small mx-3 mb-1"
									   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_TEXT', $this->language); ?>"
									   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_TEXT', $this->language); ?>"
									   data-toggle="tooltip"
								>
									<input type="checkbox"
										   class="align-middle mr-1 auto-submit"
										   id="cb-filter-all"
										   name="filter"
										   form="statsForm"<?php // FIXME - replace with calculated form name ?>
										   value="<?php echo ListModel::FILTER_ALL; ?>"
										   autocomplete="off"
										   <?php echo ($data->get('filter') == ListModel::FILTER_ALL) ? ' checked' : ''; ?>
									/>
									<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language); ?></span>
								</label>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<hr>

	<?php /* Results list */ ?>
	<div class="status-overlay"><?php // required for AJAX loading simulation ?>
		<table class="table table-hover table-sm mt-4 position-relative clearable sortable" id="trkProcesses">
			<thead class="thead-dark">
				<tr>
					<th scope="col" class="pl-2" data-defaultsort="disabled"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->language); ?></th>
					<?php if (FALSE) : ?><th scope="col" data-defaultsort="disabled"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language); ?></th><?php endif; ?>
					<th scope="col filterable"><?php echo Text::translate('COM_FTK_LABEL_PART_TEXT', $this->language); ?></th>
					<th scope="col"></th>
					<th scope="col" class="pr-5 filterable">
						<span class="d-block text-right"><?php echo Text::translate('COM_FTK_LABEL_TRACKED_TEXT', $this->language); ?></span>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php $article   = $this->data->get('article'); ?>
				<?php $process   = $this->data->get('process'); ?>
				<?php $partIdKey = $view->getInstance('part', ['language' => $this->language])->getIdentificationKey(); ?>
				<?php $procID    = $process->get($process->getPrimaryKeyName()); ?>

				<?php foreach ($list as $partID => $part) : ?><?php
						$part         = new Registry($part);
						$trackingDate = date_create($part->get('timestamp'));
				?>
				<tr>
					<td><?php echo html_entity_decode($article->get('number')); ?></td>
					<?php if (FALSE) : ?><td><?php echo html_entity_decode($process->get('name')); ?></td><?php endif; ?>
					<td>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=part&layout=item&%s=%d&pid=%d&return=%s#p-%s',
								$this->language,
								$partIdKey,
								$partID,
								$procID,
								base64_encode(urldecode($return)),
								hash('MD5', $procID)
						   ))); ?>"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $this->language); ?>"
						><?php echo html_entity_decode($part->get('trackingcode')); ?></a>
					</td>
					<td>
						<span class="badge badge-dark sample-part-badge align-text-top text-uppercase"
							  data-toggle="tooltip"
							  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_IS_SAMPLE_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_IS_SAMPLE_TEXT', $this->language); ?>"
						><?php echo $part->get('sample') ? Text::translate('COM_FTK_LABEL_SAMPLE_PART_TEXT', $this->language) : '';
						?></span>
					</td>
					<td class="text-right"><?php echo sprintf(Text::translate('COM_FTK_DATE_ON_AT_MONTH_SHORT_TEXT', $this->language),
						$trackingDate->format('d'),
						$trackingDate->format('M'),
						$trackingDate->format('Y'),
						$trackingDate->format(in_array($this->language, ['de','fr']) ? 'H' : 'g'), // H for de, fr and g for en, hu
						$trackingDate->format('i'),
						$trackingDate->format(in_array($this->language, ['en','hu']) ? 'a' :  '')  // required with en, hu
					); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</form>

<?php if (empty($list)) : ?>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_PROCESS_NOT_HANDLED_ON_THIS_DATE_TEXT', $this->language),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>
<?php endif; ?>

<?php // Free memory
unset($project);
unset($article);
unset($process);
unset($list);
?>
