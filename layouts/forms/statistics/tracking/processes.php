<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view  = $this->__get('view');
$input = $view->get('input');
$model = $view->get('model');
$user  = $view->get('user');
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
?>
<?php /* Process form data */
?>
<?php /* Prepare view data */
$data = $view->get('data');
$list = $data->get('processes');

// Get date format configuration from user profile.
$userProfile  = new Registry(UserHelper::getProfile($user));
$localeConfig = $userProfile->extract('user.locale');
$localeConfig = (is_a($localeConfig, 'Joomla\Registry\Registry') ? $localeConfig : new Registry);

// Init tabindex
$tabindex = 0;
?>
<?php /* Assign refs. */
$this->data     = $data;
$this->formData = $formData;
?>

<?php // TODO - consolidate dupe styles into styles.css (currently dupe here, tracking.process.php and article.process.parts.php) ?>
<style>
/* BEGiN: Bootstrap table plugin overrides */
.pull-right {
	float: right!important;
}
.bootstrap-table {
	margin-bottom: 1rem!important;
}
.fixed-table-container {
	border: unset!important;
}
.fixed-table-body .table {
	margin-top: unset!important;
}
/* END: Bootstrap table plugin overrides */
</style>
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

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=%s',
		$this->language,
		$view->get('name'),
		$view->get('layout')
	  ))); ?>"
	  method="get"
	  name="statsForm"
	  class="form-horizontal position-relative statsForm"
	  id="statsForm"
	  data-submit=""
>
	<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
	<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
	<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

	<div class="row">
		<?php // Back-link + View title ?>
		<div class="col-md-12 col-lg-4 mb-2 mb-lg-0">
			<?php // View title ?>
			<h1 class="h3 viewTitle d-inline-block my-0 mr-3"><?php
				echo sprintf('%s <small class="text-muted ml-1 d-lg-none d-xl-inline" ' .
					   'data-toggle="tooltip" ' .
					   'title="'      . sprintf('%d Prozesse', $listCount = count($this->data->get('processes'))) . '" ' .
					   'aria-label="' . sprintf('%d Prozesse', $listCount) . '" ' .
					'>(%s)</small>',
					Text::translate('COM_FTK_LABEL_STATISTICS_PROCESS_OUTPUT_TEXT', $this->language),
					$listCount
				); ?></h1>
		</div>

		<?php // Date- and time-picker(s) ?>
		<div class="col-md-12 col-lg-8 pt-xl-1">
			<div><?php // required for AJAX loading simulation ?>
				<div class="form-row align-items-center">
					<?php // Datepicker for the "from"-date ?>
					<div class="col-6 col-md-3">
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

					<?php // Timepicker for the "from"-time ?>
					<div class="col-3 col-md-2 col-lg-2">
						<label for="tf" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_TIME_FROM_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div class="input-group bootstrap-timepicker timepicker"
							 data-style="margin-top:-0.25rem"
							 <?php // data-provide="timepicker" ?>
							 data-append-widget-to="body"
							 data-template="dropdown"
							 <?php // data-modal-backdrop="false" ?>
							 <?php // data-default-time="false" ?>
							 data-disable-focus="true"
							 data-disable-mousewheel="false"
							 data-explicit-mode="false"
							 data-max-hours="24"
							 data-minute-step="5"
							 data-second-step="15"
							 data-show-inputs="true"
							 data-show-meridian="false"
							 data-show-seconds="false"
							 data-snap-to-step="false"
						><?php // TODO - fix shared parameters ?>
							<input type="text"
								   name="tf"
								   value="<?php echo htmlentities($data->get('timeFrom')); ?>"
								   class="form-control form-control-sm<?php /* auto-submit */ ?> right-radius-0 text-lowercase text-right"
								   id="ipt-tf"
								   form="statsForm"<?php // FIXME - replace with calculated form name ?>
								   placeholder=""
								   data-target="#statsForm"
								   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_TIME_TEXT', $this->language); ?>"
								   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_TIME_TEXT', $this->language); ?>"
								   aria-described-by="btn-pick-time"
								   readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append input-group-addon">
								<span class="input-group-text" id="btn-pick-time" role="button" style="padding-top:0.4rem; padding-bottom:0.35rem">
									<i class="far fa-clock"></i>
								</span>
							</div>
						</div>
					</div>

					<?php // Datepicker for the "to"-date ?>
					<div class="col-6 col-md-3">
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

					<?php // Timepicker for the "to"-time ?>
					<div class="col-3 col-md-2 col-lg-2">
						<label for="tt" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_TIME_TO_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<div class="input-group bootstrap-timepicker timepicker"
							 data-style="margin-top:-0.25rem"
							 <?php // data-provide="timepicker" ?>
							 data-append-widget-to="body"
							 data-template="dropdown"
							 <?php // data-modal-backdrop="false" ?>
							 <?php // data-default-time="false" ?>
							 data-disable-focus="true"
							 data-disable-mousewheel="false"
							 data-explicit-mode="false"
							 data-max-hours="24"
							 data-minute-step="5"
							 data-second-step="15"
							 data-show-inputs="true"
							 data-show-meridian="false"
							 data-show-seconds="false"
							 data-snap-to-step="false"
						><?php // TODO - fix shared parameters ?>
							<input type="text"
								   name="tt"
								   value="<?php echo htmlentities($data->get('timeTo')); ?>"
								   class="form-control form-control-sm<?php /* auto-submit */ ?> right-radius-0 text-lowercase text-right"
								   id="ipt-tt"
								   form="statsForm"<?php // FIXME - replace with calculated form name ?>
								   placeholder=""
								   data-target="#statsForm"
								   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_TIME_TEXT', $this->language); ?>"
								   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_TIME_TEXT', $this->language); ?>"
								   aria-described-by="btn-pick-time"
								   readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append input-group-addon">
								<span class="input-group-text" id="btn-pick-time" role="button" style="padding-top:0.4rem; padding-bottom:0.35rem">
									<i class="far fa-clock"></i>
								</span>
							</div>
						</div>
					</div>

					<?php // Submit button ?>
					<div class="col-auto ml-md-4 ml-lg-2 ml-xl-3">
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

					<?php // Filter for "dead" entries ?>
					<div class="col-auto ml-md-1 ml-md-0">
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
								
								<!-- Custom Filters for organisation -->
								<label for="filter"
                                       class="d-block small mx-3 mb-1"
                                       title="<?php echo Text::translate('COM_FTK_LIST_OPTION_NEMA_ITEMS_TEXT', $this->language); ?>"
                                       aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_NEMA_ITEMS_TEXT', $this->language); ?>"
                                       data-toggle="tooltip"
                                >
                                    <input type="checkbox"
                                           class="align-middle mr-1 auto-submit"
                                           id="cb-filter-nema"
                                           name="filter"
                                           form="statsForm"<?php // FIXME - replace with calculated form name ?>
                                           value="<?php echo ListModel::FILTER_NEMA; ?>"
                                           autocomplete="off"
                                           data-se="<?php echo $data->get('filter');?>"
                                        <?php echo ($data->get('filter') == ListModel::FILTER_NEMA) ? ' checked' : ''; ?>
                                    />
                                    <span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_NEMA_ITEMS_LABEL', $this->language); ?></span>
                                </label>
                                <label for="filter"
                                       class="d-block small mx-3 mb-1"
                                       title="<?php echo Text::translate('COM_FTK_LIST_OPTION_FRO_ITEMS_TEXT', $this->language); ?>"
                                       aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_FRO_ITEMS_TEXT', $this->language); ?>"
                                       data-toggle="tooltip"
                                >
                                    <input type="checkbox"
                                           class="align-middle mr-1 auto-submit"
                                           id="cb-filter-fro"
                                           name="filter"
                                           form="statsForm"<?php // FIXME - replace with calculated form name ?>
                                           value="<?php echo ListModel::FILTER_FRO; ?>"
                                           autocomplete="off"
                                        <?php echo ($data->get('filter') == ListModel::FILTER_FRO) ? ' checked' : ''; ?>
                                    />
                                    <span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_FRO_ITEMS_LABEL', $this->language); ?></span>
                                </label>
                                <label for="filter"
                                       class="d-block small mx-3 mb-1"
                                       title="<?php echo Text::translate('COM_FTK_LIST_OPTION_NEMEC_ITEMS_TEXT', $this->language); ?>"
                                       aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_NEMEC_ITEMS_TEXT', $this->language); ?>"
                                       data-toggle="tooltip"
                                >
                                    <input type="checkbox"
                                           class="align-middle mr-1 auto-submit"
                                           id="cb-filter-nemec"
                                           name="filter"
                                           form="statsForm"<?php // FIXME - replace with calculated form name ?>
                                           value="<?php echo ListModel::FILTER_NEMEC; ?>"
                                           autocomplete="off"
                                        <?php echo ($data->get('filter') == ListModel::FILTER_NEMEC) ? ' checked' : ''; ?>
                                    />
                                    <span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_NEMEC_ITEMS_LABEL', $this->language); ?></span>
                                </label>

								<!-- Custom Filters for organisation -->
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<hr>

	<?php /* Results list */ ?>
	<div class="status-overlay"><?php // required for AJAX loading simulation ?>
		<table class="table table-sm mt-4 position-relative clearable sortable"
				id="trkProcesses"
				<?php if (FALSE) : ?>
				data-toggle="table"
				data-search="true"
				data-show-columns="true"
				data-buttons-prefix="btn-sm btn"	<?php // not evaluated ?>
				data-buttons-class="secondary"
				data-buttons-align="right"
				<?php endif; ?>
		>
			<thead class="thead-dark">
				<tr>
					<th scope="col" class="pl-2 filterable"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language); ?></th>
					<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_FIRST_ENTRY_TEXT', $this->language); ?></th>
					<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_LAST_ENTRY_TEXT', $this->language); ?></th>
					<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_IDLING_TEXT', $this->language); ?></th>
					<th scope="col" class="pr-5"><?php echo Text::translate('COM_FTK_LABEL_PARTS_TEXT', $this->language); ?></th>
					<th scope="col"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_BAD_PARTS_TEXT', $this->language)); ?></th>
					<!-- Oragnisation -->
					<th scope="col"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_ORGANISATION_PARTS_TEXT', $this->language)); ?></th>
					<!-- Oragnisation -->
				</tr>
			</thead>
			<tbody>
				<?php $i = 1; ?>
				<?php foreach ($list as $process) : ?><?php
						$process = new Registry($process);
						$stats   = new Registry($process->get('stats'));
				?>
				<tr id="<?php //echo trim($stats->get('orgls'));?>">
					<td class="text-left">
					<?php // Link name to stats details ?>
					<?php if ($stats->get('total', 0) > 0) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=tracking.process&pid=%d&df=%s&tf=%s&dt=%s&tt=%s&return=%s',
								$this->language,
								mb_strtolower($view->get('name')),
								$process->get('procID'),
								$data->get('dateFrom'),
								$data->get('timeFrom'),
								$data->get('dateTo'),
								$data->get('timeTo'),
								base64_encode(urldecode(View::getURI()))
						   ))); ?>"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_PROCESSED_PARTS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_PROCESSED_PARTS_TEXT', $this->language); ?>"
						><?php echo html_entity_decode($process->get('name', '')); ?></a>
					<?php else : ?>
						<?php 	echo html_entity_decode($process->get('name', '')); ?>
					<?php endif; ?>
					</td>
					<td><?php echo is_null($dateFirst = $stats->get('first',  null))
						? '&ndash;'
						: (new DateTime($dateFirst, new DateTimeZone(FTKRULE_TIMEZONE)))->format(sprintf('%s %s', $localeConfig->get('date'), $localeConfig->get('time')));
					?></td>
					<td><?php echo is_null($dateLast  = $stats->get('last',   null))
						? '&ndash;'
						: (new DateTime($dateLast,  new DateTimeZone(FTKRULE_TIMEZONE)))->format(sprintf('%s %s', $localeConfig->get('date'), $localeConfig->get('time')));
					?></td>
					<td><?php echo is_null($breakTime = $stats->get('breaks', null))
						? '&ndash;'
						: sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_MINUTES_%s_TEXT', ($breakTime == 1 ? 'N_1' : 'N'))), $this->language), $breakTime);
					?></td>
					<td>
					<?php // Link parts count to stats details ?>
					<?php if (is_null($total = $stats->get('total', null))) : ?>
						&ndash;
					<?php else : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=tracking.process&pid=%d&df=%s&tf=%s&dt=%s&tt=%s&ft=%s&return=%s',
								$this->language,
								mb_strtolower($view->get('name')),
								$process->get('procID'),
								$data->get('dateFrom'),
								$data->get('timeFrom'),
								$data->get('dateTo'),
								$data->get('timeTo'),
                                $data->get('filter'),
								base64_encode(urldecode(View::getURI()))
						   ))); ?>"
						   data-toggle="tooltip"
						   title="<?php echo sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_PROCESS_VIEW_PROCESSED_PARTS_%s_TEXT', ($total == 1 ? 'N_1' : 'N'))), $this->language), $total); ?>"
						   aria-label="<?php echo sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_PROCESS_VIEW_PROCESSED_PARTS_%s_TEXT', ($total == 1 ? 'N_1' : 'N'))), $this->language), $total); ?>"
						><?php echo $total; ?></a>
					<?php endif; ?>
					</td>
					<td>
					<?php if (is_null($tb=$stats->get('bad', null)) || $stats->get('bad') ==0 ) : ?>
						&ndash;
					<?php else : ?>
                        <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=tracking.process&pid=%d&df=%s&tf=%s&dt=%s&tt=%s&ft=%s&return=%s',
                            $this->language,
                            mb_strtolower($view->get('name')),
                            $process->get('procID'),
                            $data->get('dateFrom'),
                            $data->get('timeFrom'),
                            $data->get('dateTo'),
                            $data->get('timeTo'),
                            $data->get('filter'),
                            base64_encode(urldecode(View::getURI()))
                        ))); ?>"
                           data-toggle="tooltip"
                           title="<?php echo sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_PROCESS_VIEW_PROCESSED_PARTS_%s_TEXT', ($tb == 1 ? 'N_1' : 'N'))), $this->language), $tb); ?>"
                           aria-label="<?php echo sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_PROCESS_VIEW_PROCESSED_PARTS_%s_TEXT', ($tb == 1 ? 'N_1' : 'N'))), $this->language), $tb); ?>"
                        >
                            <?php echo $stats->get('bad'); ?></a>
					<?php endif; ?>
					</td>

					<!-- Organisation -->
					<td class="orgdat" id="<?php //echo trim($stats->get('orgls'));?>" data-mk="<?php //echo trim($stats->get('orgls'));?>">
                        <?php if (is_null($stats->get('orgls', null))) : ?>
                            &ndash;
                        <?php else : ?>
                            <?php //echo $stats->get('orgls'); ?>
                        <?php endif; ?>
                    </td>
					<!-- Organisation -->
				</tr>
				<?php $i += 1; endforeach; ?>
			</tbody>
		</table>
	</div>
</form>

<?php if (empty($list)) : ?>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_NO_RESULT_TEXT', $this->language),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>
<?php endif; ?>

<?php // Free memory
unset($list);
unset($process);
?>
