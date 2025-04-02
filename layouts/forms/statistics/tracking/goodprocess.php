<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Helper\UserHelper;
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
$list = $data->get('articles');

// Get date format configuration from user profile.
$userProfile  = new Registry(UserHelper::getProfile($user));
$localeConfig = $userProfile->extract('user.locale');
$localeConfig = (is_a($localeConfig, 'Joomla\Registry\Registry') ? $localeConfig : new Registry);
?>
<?php /* Assign refs. */
$this->data     = $data;
$this->formData = $formData;
?>

<?php // TODO - consolidate dupe styles into styles.css (currently dupe in tracking.processes.php, here and article.process.parts.php) ?>
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
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=%s&pid=%d&df=%s&dt=%s',
		$this->language,
		$view->get('name'),
		$view->get('layout'),
		$this->data->get('process')->get($this->data->get('process')->getPrimaryKeyName()),
		$data->get('dateFrom'),
		$data->get('dateTo')
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
	<input type="hidden" name="pid"    value="<?php echo $this->data->get('process')->get($this->data->get('process')->getPrimaryKeyName()); ?>" />
	<input type="hidden" name="tf"     value="<?php echo $this->data->get('timeFrom'); ?>" />
	<input type="hidden" name="tt"     value="<?php echo $this->data->get('timeTo'); ?>" />

	<div class="row">
		<?php // Back-link + View title ?>
		<div class="col-md-4">
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
			<h1 class="h3 viewTitle d-inline-block my-0 mr-3"><?php echo sprintf('%s:<span class="small ml-3" title="%s" data-toggle="tooltip">%s</span>',
				Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language),
				html_entity_decode($this->data->get('process')->get('name')),
				mb_strtoupper(html_entity_decode($this->data->get('process')->get('abbreviation')))
			); ?></h1>
		</div>

		<?php // Date- and time-picker(s) ?>
		<div class="col-md-8 pt-1">
			<div><?php // required for AJAX loading simulation ?>
				<div class="form-row align-items-center">
					<?php // Datepicker for the "from"-date ?>
					<div class="col-md-3">
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
					<div class="col-md-2">
						<label for="tf" class="sr-only"><?php
							echo Text::translate('COM_FTK_LABEL_TIME_FROM_TEXT', $this->language);
						?>:&nbsp;&ast;</label>
						<span class="form-control form-control-sm text-lowercase text-right readonly"><?php echo htmlentities($data->get('timeFrom')); ?></span>
					</div>

					<?php // Datepicker for the "to"-date ?>
					<div class="col-md-3">
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
					<div class="col-md-2">
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
					<th scope="col" class="pl-2 filterable"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->language); ?></th>
					<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_GOOD_PARTS_TEXT', $this->language); ?></th>
					<!--<th scope="col" class="pr-5"><?php echo Text::translate('COM_FTK_LABEL_BAD_PARTS_TEXT', $this->language); ?></th>-->
				</tr>
			</thead>
			<tbody>
				<?php foreach ($list as $type => $arr) : ?>
				<?php 	$arr     = new Registry($arr);
						$drawing = $arr->get('drawing');   // initially this is an object
						$drawing = new Registry($drawing);
						$number  = $drawing->get('number', '');	// identical to article number/type
				?>
				<tr>
					<?php if (0) : ?>
					<td>
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
						<?php 	echo StringHelper::entityDecode($type); ?>
						<?php endif; ?>
					</td>
					<?php else : ?>
					<td>
						<?php echo StringHelper::entityDecode($type); ?>
					</td>
					<?php endif; ?>
					<td>
					<?php if (count((array) $arr) > 0 && (int) $arr->get('good') > 0) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
						   sprintf( 'index.php?hl=%s&view=statistics&layout=article.process.parts&aid=%d&pid=%d&quality=good&df=%s&tf=%s&dt=%s&tt=%s&return=%s',
								$this->language,
								$arr->get('artID'),
								$this->data->get('process')->get($this->data->get('process')->getPrimaryKeyName()),
								$data->get('dateFrom'),
								$data->get('timeFrom'),
								$data->get('dateTo'),
								$data->get('timeTo'),
								base64_encode(urldecode(View::getURI()))
						   ))); ?>"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_GOOD_PARTS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_GOOD_PARTS_TEXT', $this->language); ?>"
						><?php echo (int) $arr->get('good');//echo ''; ?></a>
					<?php else : ?>
						&ndash;
					<?php endif; ?>
					</td>
					<!--<td>
					<?php if (count((array) $arr) > 0 && (int) $arr->get('bad') > 0) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
						   sprintf( 'index.php?hl=%s&view=statistics&layout=article.process.parts&aid=%d&pid=%d&quality=bad&df=%s&tf=%s&dt=%s&tt=%s&return=%s',
								$this->language,
								$arr->get('artID'),
								$this->data->get('process')->get($this->data->get('process')->getPrimaryKeyName()),
								$data->get('dateFrom'),
								$data->get('timeFrom'),
								$data->get('dateTo'),
								$data->get('timeTo'),
								base64_encode(urldecode(View::getURI()))
						   ))); ?>"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_BAD_PARTS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_BAD_PARTS_TEXT', $this->language); ?>"
						><?php echo (int) $arr->get('bad'); ?></a>
					<?php else : ?>
						&ndash;
					<?php endif; ?>
					</td>-->
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
unset($list);
unset($part);
?>
