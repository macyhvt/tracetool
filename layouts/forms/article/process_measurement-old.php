<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Text;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view  = $this->__get('view');
$input = (is_a($view, ' \View') ? $view->get('input') : App::getInput());
//$debug = $input->getCmd('auth') === 'dev-op';
$model = (is_a($view, ' \View') ? $view->get('model') : null);
$user  = (is_a($view, ' \View') ? $view->get('user')  : App::getAppUser());

// N O T E :
// The next two lines appeared to be necessary when requesting this template via AJAX.
// It is supposed that the view initialization which happens in index.php is bypassed when requesting content this way.
$view  = (is_null($view) ? ($input->post->getWord('view') ?? ($input->getWord('view') ?? null)) : $view);
$view  = (is_a($view, ' \View') ? $view : View::getInstance($view, ['language' => $this->language]));
$input = (is_a($view, ' \View') ? $view->get('input') : $input);
$model = (is_a($view, ' \View') ? $view->get('model') : $model);

$task  = $this->get('task');
$item  = $this->get('article', ($input->getCmd('article') ?? null));	// Attempt to fetch article name from layout data first and fall back to $_GET
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
$canCreate = true;
?>
<?php /* Load view data */
$item          = $this->get('item');
$processes     = $item->get('processes');

$hide          = array_map('mb_strtolower', $this->get('hide'));
$pid           = $this->get('pid', '%ID%');
$process       = ArrayHelper::getValue($processes, $pid);   // The process for which to render the table, type: Joomla\Registry
$hasBanderole  = $process->get('hasBanderole');
$isTracking    = $this->get('isTracking', false);
$isReadonly    = $this->get('isReadonly', false);

$isAutoTrackm =  $input->getInt('at') == '1';
// Get details about the project this article belongs to.
$projectNum    = $model->getInstance('article', ['language' => $this->language])->getProjectnumber($item->get('artID'));
$project       = $model->getInstance('project', ['language' => $this->language])->getProjectByNumber($projectNum);
$projectStatus = (is_object($project) && method_exists($project, 'get')) ? $project->get('status') : null;
$projectConf   = $project->get('config');
$spreadFactor  = (new Registry($project->get('config')))->get('factors.' . $project->get('status'), 1);

// Mock object (required to render at least one empty row if there is existing configuration).
$mockObject = [
	[
		'mp'                => null,
		'mpDescription'     => null,
		'mpDatatype'        => null,
		'mpInput'           => null,
		'mpNominal'         => null,
		'mpLowerTol'        => null,
		'mpUpperTol'        => null,
		'mpToleranceFactor' => null,
		'mpValidity'        => null,
		'mpFrequency'       => null,
		'mpFrequencyScope'  => null,
	]
];

// Fetch defined measuring points.
// When in tracking mode (part view) without having them nothing will be displayed - neither the empty form nor any previously measured data,
// because the form is generated from the measuring points and the measured data is bound to the form.
$definedMPs   = $model->getInstance('article', ['language' => $this->language])->getDefinedMeasuringPoints($item->get('artID'), $pid);
$hasMeasurementDefinitions = is_countable($definedMPs) && count($definedMPs) > 0;
$measuredData = null;

// When in "tracking mode" and there are no measuring definitions stop right here and return.
if ($isTracking && empty($definedMPs)) :
	return;
endif;

// Fetch what has previously been measured for the defined process and populate the form from that data.
$measuredData = (true == $isTracking ? $item->get('measuredData') : []);
?>

<?php /* Prepare template markup */
$thead = $tbody = $tfoot = null;

// Calculate the banderole highlighting CSS class(es).
$cardHighlightCSS = ['card','card-body'];
if (false == $isTracking && $hasBanderole) :
	array_push($cardHighlightCSS, 'alert-success');
endif;
$cardHighlightCSS = implode(' ', $cardHighlightCSS);

// TODO - translate
$tclass   = !$isTracking ? 'measuring-definitions' : 'measuring-data';
$tcaption = Text::translate('Table caption', $this->language);

$frmMeasurementDefinitions = <<<HTML
<div class="$cardHighlightCSS">
	<table class="table table-sm table-borderless process-$tclass small my-0"
		   id="p-%PID%-table"
		   data-current-tabindex="{$this->get('tabindex')}"
		   data-is-tracking="$isTracking"
		   data-is-readonly="$isReadonly"
	>
		<caption class="sr-only">%TCAPTION%</caption>
		%THEAD%
		%TFOOT%
		%TBODY%
	</table>
</div>
HTML;
?>
<?php /* Prepare table head markup */
$thead = [];
//$thead['th-0'] = '<thead id="p-%PID%-thead" class="thead-' . ($isTracking ? 'dark' : 'light') . '"><tr>';
$thead['th-0'] = '<thead id="p-%PID%-thead" class="thead-dark' . ($hasMeasurementDefinitions ? ' banderolable' : '') . '"><tr>';

$thead['th-mp'               ] = '<th class="text-center align-middle" id="th-mp"                style="padding-bottom:6px; width:130px">' .
((false == $isTracking) ? '<abbr title="%ABRV1%" data-toggle="tooltip" data-html="true">%TH1%</abbr>' : '%TH1%') .
'</th>';
$thead['th-mpDescription'    ] = '<th class="text-center align-middle" id="th-mpDescription"     style="padding-bottom:6px; width:148px">' .
((false == $isTracking) ? '<abbr title="%ABRV2%" data-toggle="tooltip" data-html="true">%TH2%</abbr>' : '%TH2%') .
'</th>';
$thead['th-mpDatatype'       ] = '<th class="text-center align-middle' . ($isTracking ? ' d-none' : '') . '" id="th-mpDatatype" style="padding-bottom:6px; width:140px">' .
((false == $isTracking) ? '<abbr title="%ABRV3%" data-toggle="tooltip" data-html="true">%TH3%</abbr>' : '%TH3%') .
'</th>';
if (true == $isTracking) :
$thead['th-mpInput'          ] = '<th class="text-center align-middle" id="th-mpInput"           style="padding-bottom:6px; width:140px">' .
((true == $isTracking) ? '<abbr title="%ABRV4%" data-toggle="tooltip" data-html="true">%TH4%</abbr>' : '%TH4%') .
'</th>';
endif;
//$thead['th-mpNominal'        ] = '<th class="text-center align-middle" id="th-mpNominal"         style="padding-bottom:6px; width:' . ($isTracking ? '120' : '140') . 'px">' .
$thead['th-mpNominal'        ] = '<th class="text-center align-middle" id="th-mpNominal"         style="padding-bottom:6px; width:120px">' .
'<abbr title="%ABRV5%" data-toggle="tooltip" data-html="true">%TH5%</abbr>' .
'</th>';
$thead['th-mpLowerTol'       ] = '<th class="text-center align-middle" id="th-mpLowerTol"        style="padding-top:5px;    width:120px">' .
'<abbr title="%ABRV6%" data-toggle="tooltip" data-html="true">%TH6%</abbr>' .
'</th>';
$thead['th-mpUpperTol'       ] = '<th class="text-center align-middle" id="th-mpUpperTol"        style="padding-top:5px;    width:120px">' .
'<abbr title="%ABRV7%" data-toggle="tooltip" data-html="true">%TH7%</abbr>' .
'</th>';

$thead['th-mpToleranceFactor'] = '<th class="text-center align-middle" id="th-mpToleranceFactor" style="padding-bottom:6px; width:80px">' .
'<abbr title="%ABRV8%" data-toggle="tooltip" data-html="true">%TH8%</abbr>' .
'</th>';

if (true == $isTracking) :
$thead['th-mpValidity'       ] = '<th class="text-center align-middle" id="th-mpValidity"        style="padding-bottom:6px; width:120px">' .
((true == $isTracking) ? '<abbr title="%ABRV9%" data-toggle="tooltip" data-html="true">%TH9%</abbr>' : '%TH9%') .
'</th>';
endif;

if (false == $isTracking) :
$thead['th-mpFrequency'      ] = '<th class="text-center align-middle" id="th-mpFrequency"       style="padding-bottom:6px; width:80px">' .
((false == $isTracking) ? '<abbr title="%ABRV10%" data-toggle="tooltip" data-html="true">%TH10%</abbr>' : '%TH10%') .
'</th>';
endif;

if (false == $isTracking) :
$thead['th-mpFrequencyScope' ] = '<th class="text-center align-middle" id="th-mpFrequencyScope"  style="padding-bottom:6px; width:90px">' .
((false == $isTracking) ? '<abbr title="%ABRV11%" data-toggle="tooltip" data-html="true">%TH11%</abbr>' : '%TH11%') .
'</th>';
endif;

// TODO - translate button title and text
if (false == $isTracking) :
$thead['th-mpToolbar'        ] = '<th class="text-center align-middle" id="th-mpToolbar">' .
	'<button type="button" class="btn btn-sm btn-info btn-add" id="btn-addRow"' .
			'data-toggle="addMeasurementDefinition" ' .
			'data-target="#p-%PID%-tbody"' .
	'>' .
		'<span title="' . Text::translate('COM_FTK_LABEL_DEFINITION_ADD_TEXT', $this->language) . '" ' .
			  'aria-label="' . Text::translate('COM_FTK_LABEL_DEFINITION_ADD_TEXT', $this->language) . '" ' .
			  'data-toggle="tooltip"' .
		'>' .
			'<i class="fas fa-plus"></i>' .
			'<span class="btn-text sr-only">' . Text::translate('COM_FTK_LABEL_DEFINITION_ADD_TEXT', $this->language) . '</span>' .
		'</span>' .
	'</button>' .
'</th>';
endif;

if (false == $isTracking) :
	$thead['th-mpBanderole'        ] = '<th class="text-center align-middle" id="th-mpBanderole">' .
		'<div class="btn-group-toggle position-relative" data-toggle="buttons" data-parent=".card">' .
			'<label class="btn btn-sm btn-dark btn-checkbox btn-banderole label my-0' . ($hasBanderole ? ' active' : '') . '"
					for="procMeasurementBanderole_%PID%"
					role="button"
					title="' . Text::translate('COM_FTK_BUTTON_TITLE_DISPLAY_IN_BANDEROLE', $this->language) . '"
					data-toggle="tooltip"
					data-target="> .label"
					data-label-unchecked="Unchecked"
					data-label-checked="Checked"
			>' .
				'<input type="checkbox"
						id="procMeasurementBanderole_%PID%"
						name="procMeasurementBanderole[%PID%]"
						value="1"
						autocomplete="off"' .
						($hasBanderole ? ' checked' : '') .
				'>' .
				'<i class="fas fa-medal"></i>' .
			'</label>' .
		'</div>' .
	'</th>';
endif;

if (false == $isTracking) :
$thead['th-1'] = '</tr></thead>';
endif;

// Skip columns that shall be hidden (defined in $this->hide);
$thead = array_filter($thead, function ($markup, $key) use (&$hide)
{
	return !in_array(mb_strtolower(mb_substr($key, 3)), $hide);

}, ARRAY_FILTER_USE_BOTH);

// Join array data.
$thead = implode('', $thead);
?>
<?php /* Prepare table footer markup */
$tfoot = [];
$tfoot['tf-0'] = '<tfoot id="p-%PID%-tfoot">';
$tfoot['tf-1'] = '</tfoot>';

// Join array data.
$tfoot = implode('', $tfoot);
?>

<?php /* Prepare table body markup (new empty row dummy for JavaScript) */
// Table row dummy - required for dynamic injection via JavaScript	// FIXME - find solution to generate this markup from tbody data below
// TODO - translate
$rowDummy = '' .
'<tr id="p-%PID%-%CNT%-tr">' .
	'<td id="p-%PID%-%CNT%-td-mp">' .
		'<input type="text" ' .
			   'class="form-control form-control-sm mp text-left" ' .
			   'id="ipt-mp-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mp]" ' .
			   'value="%VALUE%" ' .
//			   'placeholder="%PH1%" ' .
			   'title="%REQUIRED1%" ' .
			   ($isTracking ? 'readonly ' : ' ') .
			   '%REQUIRED%' .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpDescription">' .
		'<input type="text" ' .
			   'class="form-control form-control-sm mpDescription" ' .
			   'id="ipt-mpDescription-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpDescription]" ' .
			   'value="%VALUE%" ' .
//			   'placeholder="%PH2%" ' .
			   'title="%VALUE%" ' .
			   ($isTracking ? ' data-toggle="tooltip" title="%VALUE%" ' : '') .
			   ($isTracking ? 'readonly disabled' : '') .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpDatatype">' .
		'<select name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpDatatype]" ' .
			    'class="form-control form-control-sm custom-select custom-select-sm mpDatatype text-left" ' .
			    'id="lst-mpDatatype-%PID%-%CNT%" ' .
			    'size="1" ' .
			    'title="%REQUIRED3%" ' .
			    'data-bind="dataTypeSelected" ' .
			    'data-toggle-elements="#p-%PID%-%CNT%-td-mpNominal > .interchangeable" ' .
			    'data-target="#ipt-mpLowerTol-%PID%-%CNT%, #ipt-mpUpperTol-%PID%-%CNT%, #ipt-mpToleranceFactor-%PID%-%CNT%" ' .
			    '%REQUIRED%' .
		'>' .
			'<option value="">&ndash; %OPT1% &ndash;</option>' .
			'<option value="boolval">%OPT10%</option>' .
			'<option value="number" selected>%OPT11%</option>' .    // the numeric input type is most probably the type to start a new entry with
			'<option value="string">%OPT12%</option>' .
		'</select>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpNominal">' .
		'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
			   'class="form-control form-control-sm mpNominal text-right interchangeable" ' .
			   'id="ipt-mpNominal-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpNominal]" ' .
			   'value="%VALUE%" ' .
			   'step="0.001" ' .
//			   'placeholder="%PH5%" ' .
			   'title="%REQUIRED5%" ' .
			   'data-bind="fixDecimal"' .
//			   '%REQUIRED%' .
			   (!$isTracking ? ' ' : ' readonly') .
			   (!$isTracking ? ' %REQUIRED% ' : ' ') .
			   'aria-invalid="false"' .
		'>' .
		'<select name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpNominal]" ' .
			    'class="form-control form-control-sm custom-select custom-select-sm mpNominal text-right interchangeable" ' .
			    'id="lst-mpNominal-%PID%-%CNT%" ' .
			    'size="1" ' .
			    'title="%REQUIRED5%" ' .
			    '%REQUIRED% ' .
			    'disabled ' .
			    'aria-invalid="false"' .
			    'style="display:none"' .
		'>' .
			'<option value="">&ndash; %OPT1% &ndash;</option>' .
			'<option value="true">%OPT2%</option>' .
			'<option value="false">%OPT3%</option>' .
		'</select>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpLowerTol">' .
		'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
			   'class="form-control form-control-sm mpLowerTol text-right" ' .
			   'id="ipt-mpLowerTol-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpLowerTol]" ' .
			   'value="%VALUE%" ' .
			   'step="0.001" ' .
//			   'placeholder="%PH6%" ' .
			   'title="%REQUIRED6%" ' .
			   'data-dump="" ' .
			   'data-bind="fixDecimal" ' .
//			   (!$isTracking ? '%DISABLED%' : 'readonly') .
			   ($isTracking ? 'readonly' : '') .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpUpperTol">' .
		'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
			   'class="form-control form-control-sm mpUpperTol text-right" ' .
			   'id="ipt-mpUpperTol-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpUpperTol]" ' .
			   'value="%VALUE%" ' .
			   'step="0.001" ' .
//			   'placeholder="%PH7%" ' .
			   'title="%REQUIRED7%" ' .
			   'data-dump="" ' .
			   'data-bind="fixDecimal" ' .
//			   (!$isTracking ? '%DISABLED%' : 'readonly') .
			   ($isTracking ? 'readonly' : '') .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpToleranceFactor">' .
		'<input type="' . ($isTracking || $isReadonly ? 'text' : 'number') . '" ' .	// Is always a read only (the multiplier aka tolerance spread factor) is read from project setting "status" and filled in. Hence, a user must not fill in anything.
			   'class="form-control form-control-sm mpToleranceFactor text-right" ' .
			   'id="ipt-mpToleranceFactor-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpToleranceFactor]" ' .
			   'value="' . $spreadFactor .'" ' .	// initial display - may be altered depending on the selected data type (boolval and string don't need a tolerance factor - it's a calculation variable)
//			   'min="1" ' .
//			   'max="1000" ' .
//			   'step="0.001" ' .
//			   'title="%REQUIRED8%" ' .
			   'data-dump="' . $spreadFactor . '" ' .
			   'readonly ' .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpFrequency">' .
		'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
			   'class="form-control form-control-sm mpFrequency text-right" ' .
			   'id="ipt-mpFrequency-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpFrequency]" ' .
			   'value="%VALUE%" ' .
			   'min="1" ' .
			   'max="1000" ' .
			   'step="1" ' .
//			   'placeholder="%PH9%" ' .
			   'title="%REQUIRED9%" ' .
			   'data-bind="fixDecimal"' .
			   '%REQUIRED% ' .
//			   (!$isTracking ? '%DISABLED%' : 'readonly') .
			   ($isTracking ? 'readonly' : '') .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpFrequencyScope">' .
		'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
			   'class="form-control form-control-sm mpFrequencyScope text-right" ' .
			   'id="ipt-mpFrequencyScope-%PID%-%CNT%" ' .
			   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][mpFrequencyScope]" ' .
			   'value="%VALUE%" ' .
			   'min="1" ' .
			   'max="10000" ' .
			   'step="1" ' .
//			   'placeholder="%PH10%" ' .
			   'title="%REQUIRED10%" ' .
			   'data-bind="fixDecimal"' .
			   '%REQUIRED% ' .
//			   (!$isTracking ? '%DISABLED%' : 'readonly') .
			   ($isTracking ? 'readonly' : '') .
		'>' .
	'</td>' .
	'<td id="p-%PID%-%CNT%-td-mpToolbar">' .
		'<div class="btn-toolbar ml-0" id="" role="toolbar" aria-label="">' .
			'<div class="btn-group" role="group" aria-label="">' .
				'<button type="button"' .
					' class="btn btn-sm btn-trashbin"' .
					' title="' . Text::translate('COM_FTK_LABEL_DEFINITION_DELETE_THIS_TEXT', $this->language) . '"' .
					' data-bind="deleteListItem"' .
					' data-target="#p-%PID%-%CNT%-tr"' .
					' data-confirm-delete="true"' .
					' data-confirm-delete-empty="false"' .
					' data-confirm-delete-message="' . sprintf("%s\r\n%s",
						Text::translate('COM_FTK_DIALOG_MEASURING_DEFINITION_CONFIRM_DELETION_TEXT', $this->language),
						Text::translate('COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT', $this->language)
					) . '"' .
				'>' .
					'<span title="' . Text::translate('COM_FTK_LABEL_DEFINITION_DELETE_THIS_TEXT', $this->language) . '"' .
						' aria-label="' . Text::translate('COM_FTK_LABEL_DEFINITION_DELETE_THIS_TEXT', $this->language) . '"' .
						' data-toggle="tooltip"' .
					'>' .
						'<i class="far fa-trash-alt"></i>' .
						'<span class="btn-text sr-only">' . Text::translate('COM_FTK_LABEL_DELETE_TEXT', $this->language) . '</span>' .
					'</span>' .
				'</button>' .
			'</div>' .
		'</div>' .
	'</td>' .
	'<td></td>' .
'</tr>';

// In read only mode there is no need for this dummy. Hence, we unset it.
if ($isReadonly) :
	$rowDummy = '';
endif;
?>
<?php /* Substitute row dummy placeholders */	// TODO - translate
$rowDummy = preg_replace(['/%PID%/i', '/%PH\d?%/i', '/%VALUE%/'], [$pid, '', ''], $rowDummy);
$rowDummy = preg_replace('/(\n+|\r+|\s{2,}|\t+)/', ' ', $rowDummy);
$rowDummy = preg_replace('/%REQUIRED%/i', 'required', $rowDummy);
$rowDummy = preg_replace(
	[
		'/%ABRV1%/'    ,'/%ABRV2%/'    ,'/%ABRV3%/'    ,'/%ABRV4%/'    ,'/%ABRV5%/'    ,'/%ABRV6%/'    ,'/%ABRV7%/'    ,'/%ABRV8%/'    ,'/%ABRV9%/'    ,'/%ABRV10%/'    ,'/%ABRV11%/',
		'/%TH1%/'      ,'/%TH2%/'      ,'/%TH3%/'      ,'/%TH4%/'      ,'/%TH5%/'      ,'/%TH6%/'      ,'/%TH7%/'      ,'/%TH8%/'      ,'/%TH9%/'      ,'/%TH10%/'      ,'/%TH11%/',
		'/%PH1%/'      ,'/%PH2%/'      ,'/%PH3%/'      ,'/%PH4%/'      ,'/%PH5%/'      ,'/%PH6%/'      ,'/%PH7%/'      ,'/%PH8%/'      ,'/%PH9%/'      ,'/%PH10%/'      ,'/%PH11%/',
		'/%OPT1%/'     ,'/%OPT2%/'     ,'/%OPT3%/'     ,'/%OPT4%/'     ,'/%OPT5%/'     ,'/%OPT6%/'     ,'/%OPT10%/'    ,'/%OPT11%/'    ,'/%OPT12%/',
		'/%REQUIRED1%/','/%REQUIRED2%/','/%REQUIRED3%/','/%REQUIRED4%/','/%REQUIRED5%/','/%REQUIRED6%/','/%REQUIRED7%/','/%REQUIRED8%/','/%REQUIRED9%/','/%REQUIRED10%/','/%REQUIRED11%/'
	],
	[
		// Abbreviations %ABRVx%
		/* col  1 - mp                */ Text::translate('COM_FTK_ABBREV_MEASURING_POINT_TEXT',                        $this->language),
		/* col  2 - mpDescription     */ Text::translate('COM_FTK_ABBREV_MEASURING_POINT_DESC_TEXT',                   $this->language),
		/* col  3 - mpDatatype        */ Text::translate('COM_FTK_ABBREV_USER_INPUT_EXPECTATION_TEXT',                 $this->language),
		/* col  4 - mpInput           */ Text::translate('COM_FTK_ABBREV_MEASURED_VALUE_TEXT',                         $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_ABBREV_NOMINAL_VALUE_TEXT',                          $this->language),
		/* col  6 - mpLowerTol        */ Text::translate('COM_FTK_ABBREV_LOWER_TOLERANCE_TO_TARGET_VALUE_TEXT',        $this->language),
		/* col  7 - mpUpperTol        */ Text::translate('COM_FTK_ABBREV_UPPER_TOLERANCE_TO_TARGET_VALUE_TEXT',        $this->language),
		/* col  8 - mpToleranceFactor */ Text::translate('COM_FTK_ABBREV_TOLERANCE_FACTOR_TEXT',                       $this->language),
		/* col  9 - mpValidity        */ Text::translate('COM_FTK_ABBREV_IS_MEASURING_RESULT_VALID_TEXT',              $this->language),
		/* col 10 - mpFrequency       */ Text::translate('COM_FTK_ABBREV_MEASURING_FREQUENCY_TEXT',                    $this->language),
		/* col 11 - mpFrequencyScope  */ Text::translate('COM_FTK_ABBREV_MEASURING_FREQUENCY_SCOPE_TEXT',              $this->language),

		// Table heads %THx%
		/* col  1 - mp                */ Text::translate('COM_FTK_LABEL_MEASURING_POINT_SHORT_TEXT',                   $this->language),
		/* col  2 - mpDescription     */ Text::translate('COM_FTK_LABEL_SPECIFICATION_TEXT',                           $this->language),
		/* col  3 - mpDatatype        */ Text::translate('COM_FTK_LABEL_DATA_TYPE_TEXT',                               $this->language),
		/* col  4 - mpInput           */ Text::translate('COM_FTK_LABEL_MEASURED_VALUE_TEXT',                          $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_LABEL_NOMINAL_VALUE_TEXT',                           $this->language),
		/* col  6 - mpLowerTol        */ sprintf('%s <span class="d-inline-block ml-1 align-middle font-size-150" style="margin-bottom:5px;">&ndash;</span>', Text::translate('COM_FTK_LABEL_TOLERANCE_TEXT', $this->language)),
		/* col  7 - mpUpperTol        */ sprintf('%s <span class="d-inline-block ml-1 align-middle font-size-150" style="margin-bottom:5px;">+</span>', Text::translate('COM_FTK_LABEL_TOLERANCE_TEXT', $this->language)),
		/* col  8 - mpToleranceFactor */ Text::translate('COM_FTK_LABEL_FACTOR_TEXT',                                  $this->language),
		/* col  9 - mpValidity        */ Text::translate('COM_FTK_LABEL_VALIDITY_TEXT',                                $this->language),
		/* col 10 - mpFrequency       */ Text::translate('COM_FTK_LABEL_FREQUENCY_SHORT_TEXT',                         $this->language),
		/* col 11 - mpFrequencyScope  */ Text::translate('COM_FTK_LABEL_FREQUENCY_SCOPE_TEXT',                         $this->language),

		// Placeholders %PHx%
		/* col  1 - mp                */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_POINT_TEXT',             $this->language),
		/* col  2 - mpDescription     */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_POINT_DESCRIPTION_TEXT', $this->language),
		/* col  3 - mpDatatype        */ null,	// it's a dropdown
		/* col  4 - mpInput           */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_USER_INPUT_TEXT',                  $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_SETPOINT_TEXT',                    $this->language),
		/* col  6 - mpLowerTol        */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_LOWER_TOLERANCE_TEXT',             $this->language),
		/* col  7 - mpUpperTol        */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_UPPER_TOLERANCE_TEXT',             $this->language),
		/* col  8 - mpToleranceFactor */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_TOLERANCE_FACTOR_TEXT',            $this->language),
		/* col  9 - mpValidity        */ null,	// it's a masked dropdown
		/* col 10 - mpFrequency       */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_FREQUENCY_TEXT',         $this->language),
		/* col 11 - mpFrequencyScope  */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_FREQUENCY_SCOPE_TEXT',   $this->language),

		// List options %OPTx%
		Text::translate('COM_FTK_LABEL_SELECT_TEXT',                    $this->language),	// %OPT1%
		Text::translate('COM_FTK_LIST_OPTION_TRUE_TEXT',                $this->language),	// %OPT2%
		Text::translate('COM_FTK_LIST_OPTION_FALSE_TEXT',               $this->language),	// %OPT3%
		Text::translate('COM_FTK_LIST_OPTION_VALID_TEXT',               $this->language),	// %OPT4%
		Text::translate('COM_FTK_LIST_OPTION_CONDITIONALLY_VALID_TEXT', $this->language),	// %OPT5%
		Text::translate('COM_FTK_LIST_OPTION_INVALID_TEXT',             $this->language),	// %OPT6%
		Text::translate('COM_FTK_LIST_OPTION_BOOLVAL_TEXT',             $this->language),	// %OPT10%
		Text::translate('COM_FTK_LIST_OPTION_NUMBER_TEXT',              $this->language),	// %OPT11%
		Text::translate('COM_FTK_LIST_OPTION_STRING_TEXT',              $this->language),	// %OPT12%

		// Required field-messages %REQUIREDx%
		/* col  1 - mp                */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  2 - mpDescription     */ null,
		/* col  3 - mpDatatype        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  4 - mpInput           */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  6 - mpLowerTol        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  7 - mpUpperTol        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  8 - mpValidity        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  9 - mpFrequency       */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col 10 - mpFrequencyScope  */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col 11 - mpToleranceFactor */ Text::translate('COM_FTK_HINT_MEASURED_VALUE_REQUIRED_TEXT', $this->language),
	],
	$rowDummy
);
?>

<?php /* Prepare table body markup */
$fields = [];
?>
<?php // column mp
$fields['mp'] = '' .
'<td id="p-%PID%-%CNT%-td-mp">' .
	'<input type="text" ' .
		   'class="form-control form-control-sm mp text-left" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mp-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
//			  'placeholder="%PH1%" ' .
			  'title="%REQUIRED1%" ' .
			  'required '
			: ''
			) .
		   ($isTracking ? 'readonly' : ($isReadonly ? 'readonly' : '')) .
	'>' .
'</td>';
?>
<?php // column mpDescription
$fields['mpDescription'] = '<td id="p-%PID%-%CNT%-td-mpDescription">' .
	'<input type="text" ' .
		   'class="form-control form-control-sm mpDescription" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpDescription-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
//			  'placeholder="%PH2%" ' .
			  ($isTracking ? 'data-toggle="tooltip" title="%VALUE%" ' : '') .
			  ($isTracking ? 'disabled ' : '')
			: ''
			) .
		   ($isTracking ? 'readonly ' : ($isReadonly ? 'readonly ' : '')) .
	'>' .
'</td>';
?>
<?php // column mpDatatype
$fields['mpDatatype'] = [
'number'  => '<td id="p-%PID%-%CNT%-td-mpDatatype"' . ($isTracking ? ' class="d-none"' : '') . '>' .
	'<input type="text" ' .
		   'class="form-control form-control-sm mpDatatype text-left" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpDatatype-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
//			  'placeholder="%PH3%" ' .
			  'title="%REQUIRED3%" ' .
			  ($isTracking ? 'required ' : '')
			: ''
		   ) .
		   ($isTracking ? '%DISABLED% readonly' : ($isReadonly ? 'readonly ' : '')) .
	'>' .
'</td>'
];
// In compositing mode the field must be changeable. Hence, a list element is required. Otherwise, the value is displayed as is no matter of the datatype.
if (false == $isTracking) :
$fields['mpDatatype']['boolval'] = '<td id="p-%PID%-%CNT%-td-mpDatatype">' .
	'<select name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
		    'class="form-control form-control-sm custom-select custom-select-sm mpDatatype text-left" ' .
		    'id="lst-mpDatatype-%PID%-%CNT%" ' .
		    'size="1" ' .
		    'title="%REQUIRED3%" ' .
		    '%BINDTO% ' .
		    'required ' .
	'>' .
		'<option value="">&ndash; %OPT1% &ndash;</option>' .
		'<option value="boolval">%OPT10%</option>' .
		'<option value="number">%OPT11%</option>' .
		'<option value="string">%OPT12%</option>' .
	'</select>' .
'</td>';
endif;
?>
<?php // column mpInput (receives user input in tracking mode)
if (true == $isTracking) :
$fields['mpInput'] = array(
	'number'  => '<td id="p-%PID%-%CNT%-td-mpInput">' .
		'<input type="text" ' .
			   'class="form-control form-control-sm ' . ($isTracking ? 'measured-value' : '') . ' mpInput text-right" ' .
			   'value="%VALUE%" ' .
			   (!$isReadonly
				? 'id="ipt-mpInput-%PID%-%CNT%" ' .
				  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
				  'title="%REQUIRED4%" ' .
				  'data-bind="fixDecimal" ' .
				  'data-toggle="calculateMeasurementResultValidity" ' .		// a JS-function executes a realtime calc and selects the matching validity option in field 'mpValidity' automatically
				  'data-nominal="#ipt-mpNominal-%PID%-%CNT%" ' .
				  'data-tolerance-upper="#ipt-mpUpperTol-%PID%-%CNT%" ' .
				  'data-tolerance-lower="#ipt-mpLowerTol-%PID%-%CNT%" ' .
				  'data-tolerance-factor="#ipt-mpToleranceFactor-%PID%-%CNT%" ' .
				  'data-target="#lst-mpValidity-%PID%-%CNT%" '
//				  . 'required '           // enable this depending on Frequenzabbildung-Intervall
				: 'readonly disabled'
			   ) .
		'>' .
	'</td>',
	'string'  => '<td id="p-%PID%-%CNT%-td-mpInput">' .
		'<input type="text" ' .
			   'class="form-control form-control-sm ' . ($isTracking ? 'measured-value' : '') . ' mpInput text-right" ' .
			   'value="%VALUE%" ' .
			   (!$isReadonly
				? 'id="ipt-mpInput-%PID%-%CNT%" ' .
				  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
				  'title="%REQUIRED4%" ' .
				  'data-bind="fixDecimal" '
//				  . 'required '           // enable this depending on Frequenzabbildung-Intervall
				: 'readonly disabled'
			   ) .
		'>' .
	'</td>',
	'boolval' => '<td id="p-%PID%-%CNT%-td-mpInput">' .
		'<select name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
				'class="form-control form-control-sm ' . ($isTracking ? 'measured-value' : '') . ' custom-select custom-select-sm mpInput text-right" ' .
				(!$isReadonly
				 ? 'id="lst-mpInput-%PID%-%CNT%" ' .
				   'title="%REQUIRED5%" ' .
				   'size="1" ' .
				   'data-bind="fixDecimal" ' .
				   'data-toggle="calculateMeasurementResultValidity" ' .	// a JS-function executes a realtime calc and selects the matching validity option in this field automatically
				   'data-nominal="#ipth-mpNominal-%PID%-%CNT%" ' .
				   'data-tolerance-upper="#ipt-mpUpperTol-%PID%-%CNT%" ' .
				   'data-tolerance-lower="#ipt-mpLowerTol-%PID%-%CNT%" ' .
				   'data-tolerance-factor="#ipt-mpToleranceFactor-%PID%-%CNT%" ' .
				   'data-target="#lst-mpValidity-%PID%-%CNT%" '
//				   . 'required '           // enable this depending on Frequenzabbildung-Intervall
				: 'readonly disabled'
			   ) .
		'>' .
			'<option value="">&ndash; %OPT1% &ndash;</option>' .
			'<option value="true">%OPT2%</option>' .
			'<option value="false">%OPT3%</option>' .
		'</select>' .
	'</td>'
);
endif;

// mpInput field is needed only at tracking view but not at definition view.
if (false == $isTracking && true == $isReadonly && isset($fields['mpInput']) && is_array($fields['mpInput'])) :
	array_pop($fields['mpInput']);
endif;
?>
<?php // column mpNominal (aka Target value)
$fields['mpNominal'] = array(
	'number'  => '<td id="p-%PID%-%CNT%-td-mpNominal">' .
		'<input type="' . ($isTracking ? 'text' : ($isReadonly ? 'text' : 'number')) . '" ' .
			   'class="form-control form-control-sm mpNominal text-right interchangeable" ' .
			   'value="%VALUE%" ' .
			   (!$isReadonly
				? 'id="ipt-mpNominal-%PID%-%CNT%" ' .
				  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
				  'title="%REQUIRED5%" ' .
				  (!$isTracking ? 'step="0.001" ' : ' ') .
				  'data-bind="fixDecimal" ' .
				  'required ' .
				  (!$isTracking ? '%DISABLED%' : '')
				: ''
				) .
			   ($isTracking ? 'readonly' : ($isReadonly ? 'readonly' : '')) .
		'>' .
	'</td>',
	'string'  => '<td id="p-%PID%-%CNT%-td-mpNominal">' .
		'<input type="text" ' .
			   'class="form-control form-control-sm mpNominal text-right interchangeable" ' .
			   'value="%VALUE%" ' .
			   (!$isReadonly
				? 'id="ipt-mpNominal-%PID%-%CNT%" ' .
				  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
				  (!$isTracking ? '%DISABLED%' : '')
				: ''
				) .
			   ($isTracking ? 'readonly' : ($isReadonly ? 'readonly' : '')) .
		'>' .
	'</td>',
);
// In compositing mode the field must be changeable. Hence, a list element is required. Otherwise, the value is displayed as is no matter of the datatype.
if (false == $isTracking) :
$fields['mpNominal']['boolval'] = '<td id="p-%PID%-%CNT%-td-mpNominal">' .
	'<select name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
		    'class="form-control form-control-sm custom-select custom-select-sm mpNominal text-right interchangeable" ' .
		    'id="lst-mpNominal-%PID%-%CNT%" ' .
		    'size="1" ' .
		    'title="%REQUIRED5%" ' .
		    'required ' .
		    ($isTracking ? 'disabled' : '') .
	'>' .
		'<option value="">&ndash; %OPT1% &ndash;</option>' .
		'<option value="true">%OPT2%</option>' .
		'<option value="false">%OPT3%</option>' .
	'</select>' .
'</td>';
endif;
?>
<?php // column mpLowerTol
$fields['mpLowerTol'] = '<td id="p-%PID%-%CNT%-td-mpLowerTol">' .
	'<input type="' . ($isTracking ? 'text' : ($isReadonly ? 'text' : 'number')) . '" ' .
		   'class="form-control form-control-sm mpLowerTol text-right" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpLowerTol-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
			  'title="%REQUIRED6%" ' .
			  (!$isTracking ? 'step="0.001" ' : '') .
			  'data-dump="%VALUE%" ' .
			  'data-bind="fixDecimal" '
			: ''
			) .
		   // (!$isTracking ? '%DISABLED%' : 'readonly') .
		   ($isTracking ? 'readonly' : ($isReadonly ? 'readonly' : '%DISABLED%')) .
	'>' .
'</td>';
?>
<?php // column mpUpperTol
$fields['mpUpperTol'] = '<td id="p-%PID%-%CNT%-td-mpUpperTol">' .
	'<input type="' . ($isTracking ? 'text' : ($isReadonly ? 'text' : 'number')) . '" ' .
		   'class="form-control form-control-sm mpUpperTol text-right" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpUpperTol-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
			  'title="%REQUIRED7%" ' .
			  (!$isTracking ? 'step="0.001" ' : '') .
			  'data-dump="%VALUE%" ' .
			  'data-bind="fixDecimal" '
			: ''
			) .
		   ($isTracking ? 'readonly' : ($isReadonly ? 'readonly' : '%DISABLED%')) .
	'>' .
'</td>';
?>
<?php // column mpToleranceFactor
$fields['mpToleranceFactor'] = '<td id="p-%PID%-%CNT%-td-mpToleranceFactor">' .
	'<input type="' . ($isTracking || $isReadonly ? 'text' : 'number') . '" ' .	// Is always a read only (the multiplier aka tolerance spread factor) is read from project setting "status" and filled in. Hence, a user must not fill in anything.
		   'class="form-control form-control-sm mpToleranceFactor text-right" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpToleranceFactor-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
			  'title="%REQUIRED8%" ' .
//			  'min="1" ' .
//			  'max="1000" ' .
//			  'step="0.001" ' .
			  'data-dump="' . $spreadFactor . '" '
//			  'required '
			: ''
			) .
		   'readonly ' .
	'>' .
'</td>';
?>
<?php // column mpFrequency
if (false == $isTracking) :
$fields['mpFrequency'] = '<td id="p-%PID%-%CNT%-td-mpFrequency">' .
	'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
		   'class="form-control form-control-sm mpFrequency text-right" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpFrequency-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
//			  'placeholder="%PH9%" ' .
			  'title="%REQUIRED9%" ' .
			  'min="1" ' .
			  'max="1000" ' .
			  'step="1" ' .
			  'data-bind="fixDecimal" ' .
			  'required '
			: ''
			) .
		   ($isReadonly ? 'readonly' : '') .
	'>' .
'</td>';
endif;
?>
<?php // column mpFrequencyScope
if (false == $isTracking) :
$fields['mpFrequencyScope'] = '<td id="p-%PID%-%CNT%-td-mpFrequencyScope">' .
	'<input type="' . ($isReadonly ? 'text' : 'number') . '" ' .
		   'class="form-control form-control-sm mpFrequencyScope text-right" ' .
		   'value="%VALUE%" ' .
		   (!$isReadonly
			? 'id="ipt-mpFrequencyScope-%PID%-%CNT%" ' .
			  'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
//			  'placeholder="%PH10%" ' .
			  'title="%REQUIRED10%" ' .
			  'min="1" ' .
			  'max="10000" ' .
			  'step="1" ' .
			  'data-bind="fixDecimal" ' .
			  'required '
			: ''
			) .
		   ($isReadonly ? 'readonly' : '') .
	'>' .
'</td>';
endif;
?>
<?php // column mpValidity (is autopopulated with calculated value)
if (true == $isTracking) :
$fields['mpValidity'] = '<td id="p-%PID%-%CNT%-td-mpValidity">' .
	'<div class="position-relative"> ' .
		'<span class="select-decorator position-absolute" style="z-index:1; background:#e9ecef; right:0; height:auto; padding:0 .5rem; margin-top:1px; margin-right:2px">&nbsp;</span>' .	// Overlay to hide the dropdown arrow(s)
		'<span class="select-decorator position-absolute" style="z-index:2; background:transparent; left:0; height:100%; width:100%">&nbsp;</span>' .										// Overlay to prevent the element from being accessed and interacted with
		'<select name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
				'class="form-control form-control-sm custom-select custom-select-sm mpValidity text-left" ' .
				'id="lst-mpValidity-%PID%-%CNT%" ' .	// Do not move this property, since it is required for text matching via regex to inject another Bootstrap attribute for text colouring
				(!$isReadonly
				 ? 'title="%REQUIRED11%" ' .
				   'size="1" '
				 : ''
				) .
				'readonly ' .
				'style="padding-top:0.2rem; font-size:90%"' .
		'>' .
			'<option value=""></option>' .
			'<option value="valid">%OPT4%</option>' .
			'<option value="conditionally_valid">%OPT5%</option>' .
			'<option value="invalid">%OPT6%</option>' .
		'</select>' .
	'</div>' .
'</td>';
endif;
/*
$fields['mpValidity'] = '<td id="p-%PID%-%CNT%-td-mpValidity">' .
	'<input type="text" ' .
		   'class="form-control form-control-sm text-right" ' .
		   'id="ipt-mpValidity-%PID%-%CNT%" ' .
		   'name="procMeasurement' . (!$isTracking ? 'Definition' : 'Data') . '[%PID%][%CNT%][%KEY%]" ' .
		   'value="%VALUE%" ' .
		   'required ' .
		   'readonly ' .
	'>' .
'</td>';
*/
?>
<?php // column mpToolbar (add/delete new row in authoring mode)
if (false == $isTracking) :
$fields['mpToolbar'] = '<td id="p-%PID%-%CNT%-td-mpToolbar">' .
//	'<div class="btn-toolbar ml-0" id="" role="toolbar" aria-label="">' .
//		'<div class="btn-group" role="group" aria-label="">' .
			'<button type="button"' .
				' class="btn btn-sm btn-trashbin"' .
				' title="' . Text::translate('COM_FTK_LABEL_DEFINITION_DELETE_THIS_TEXT', $this->language) . '"' .
				' data-toggle="tooltip"' .
				' data-bind="deleteListItem"' .
				' data-target="#p-%PID%-%CNT%-tr"' .
				' data-confirm-delete="true"' .
				' data-confirm-delete-empty="false"' .
				' data-confirm-delete-message="' . sprintf("%s\r\n%s",
					Text::translate('COM_FTK_DIALOG_MEASURING_DEFINITION_CONFIRM_DELETION_TEXT', $this->language),
					Text::translate('COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT', $this->language)
				) . '"' .
			'>' .
				'<span' .
//					' Xtitle="' . Text::translate('COM_FTK_LABEL_DEFINITION_DELETE_THIS_TEXT', $this->language) . '"' .
//					' Xaria-label="' . Text::translate('COM_FTK_LABEL_DEFINITION_DELETE_THIS_TEXT', $this->language) . '"' .
//					' Xdata-toggle="tooltip"' .
				'>' .
					'<i class="far fa-trash-alt"></i>' .
					'<span class="btn-text sr-only">' . Text::translate('COM_FTK_LABEL_DELETE_TEXT', $this->language) . '</span>' .
				'</span>' .
			'</button>' .
//		'</div>' .
//	'</div>' .
'</td>';
endif;
?>
<?php // column mpBanderole (enable/disable display of the banderole)
if (false == $isTracking) :
	$fields['mpBanderole'] = '<td></td>';
endif;
?>

<?php /** Function to fetch a form field from the above fields collection.
 *
 * @param  string $fieldName    The configuration field name used as array index.
 * @param  string $value        The configuration field value to be filled in or selected. (Default: empty string)
 * @param  string $status       The user input data validation status (represents TWBS text-class to control text displayed in green/orange/red)
 * @param  string $dataType     The data type of the desired form field and user input/selection (currently: boolean or number). (Default: number)
 * @param  int $procID          The id of the process. (Default: 0)
 * @param  int $cnt             The iteration count required on form field name generation. (Default: 0)
 * @param  string $placeholder  The form field placeholder attribute value. (Default: empty string)
 * @param  string $title        The form field title attribute value. (Default: empty string)
 *
 * @return string  The pre-configured form field markup or an empty string if the requested field is not existing
 */
$getField = function(
	$fieldName,
	$value = '',
	$status = '',
	$dataType = 'number',
	$procID = '0',
	$cnt = '0',
	$placeholder = '',
	$title = ''
)
use (&$debug, &$isTracking,&$isAutoTrackm, &$isReadonly, &$fields, &$measuredData, &$user, &$lang): string
{
	// If the requested field name is not available, skip it to prevent PHP warning for Undefined array key.
	if (!isset($fields[$fieldName])) :
		return '';
	endif;

	// Fetch field depending on datatype and whether we are in compositing mode (form creation).
	switch (true) :
		case ($fieldName == 'mpInput') :
			if (true == $isReadonly) :
				$field = $fields[$fieldName]['number'];
			else :
				$field = $fields[$fieldName][$dataType];
			endif;
		break;

		case ($fieldName == 'mpDatatype' && false == $isTracking) :
			if (true == $isReadonly) :
				$field = $fields[$fieldName]['number'];
			else :
				$field = $fields[$fieldName]['boolval'];
			endif;
		break;

		case ($fieldName == 'mpNominal' && false == $isTracking) :
			$field = $fields[$fieldName][$dataType];

			if (true == $isReadonly) :
				// $field = $fields[$fieldName]['number'];	// disabled on 2022-05-06
				$field = $fields[$fieldName]['string'];		// added on 2022-05-06
			else :
			// Fetch the same field as text field, hide and disable it and append it.
			// That field will be required as mpDatatype is changeable and the related form field must be displayed after datatype changed.
			if ($dataType == 'boolval') :
				$shadowField = $fields[$fieldName]['number'];
				// Remove wrapping <td></td> tags.
				$shadowField = preg_replace('~</?td([^>]*)>~', '',   $shadowField);
				// Remove field value. This must not be set.
				$shadowField = preg_replace('~value="([^"]*)"~', '', $shadowField);
				// Disable and hide this element. (Note: The inline style declaration is required for jQuery.hide() or jQuery.show() to work)
				$shadowField = str_ireplace('>', ' disabled style="display:none">', $shadowField);
			else :
				// Inject select
				$shadowField = $fields[$fieldName]['boolval'];
				// Remove wrapping <td></td> tags.
				$shadowField = preg_replace('~</?td([^>]*)>~', '',   $shadowField);
				// Disable and hide this element. (Note: The inline style declaration is required for jQuery.hide() or jQuery.show() to work)
				$shadowField = preg_replace('/<select([^>]+)>/i', "<select$1 disabled style=\"display:none\">", $shadowField);
			endif;

			// Append hidden field to $field.
			$field = str_ireplace('</td>', $shadowField . '</td>', $field);
			endif;
		break;

		// Inject hidden field to override the visible field's value and send a valid list option value rather than the translated option text.
		case ($fieldName == 'mpNominal' && true == $isTracking && $dataType == 'boolval') :
			$field  = $fields[$fieldName]['number'];

			// Fetch the same field as text field, hide and disable it and append it.
			// This field is required as mpDatatype is changeable and the related form field must be displayed after datatype changed.
			// Note, in read only mode there is no need for a hidden field that holds the real value.
			if (false == $isReadonly) :
				$shadowField = $fields[$fieldName]['number'];
				// Remove wrapping <td></td> tags.
				$shadowField = preg_replace('~</?td([^>]*)>~', '',   $shadowField);
				// A hidden field does not need most of the available element attribute.
				$shadowField = preg_replace('~(class|data-[^=]+|placeholder|readonly|required|selected|title)(="([^"]*)")?\s?~', '', $shadowField);
				// Change field type to "hidden".
				$shadowField = preg_replace('~type="([^"]*)"~', 'type="hidden"', $shadowField);
				// Inject additional letter to "id" attribute to make this ID unique.
				$shadowField = preg_replace('~id="ipt-([^"]*)"~', "id=\"ipth-$1\"", $shadowField);
				// Change field value to either valid list option.
				$shadowField = preg_replace('~value="([^"]*)"~', 'value="' . Text::translate(Text::untranslate($value, $this->get('language')), 'en') . '"', $shadowField);
				// Disable and hide this element. (Note: The inline style declaration is required for jQuery.hide() or jQuery.show() to work)
				$shadowField = preg_replace('~(\s+)?>(\s+)?~', '>', $shadowField);

				// Append hidden field to $field.
				$field = str_ireplace('</td>', $shadowField . '</td>', $field);
			endif;
		break;

		case (in_array($fieldName, ['mpDatatype','mpNominal'])) :
//		case ($fieldName == 'mpDatatype') :
			$field = $fields[$fieldName][$fieldName == 'mpNominal' ? 'string' : 'number'];
		break;

		default :
			$field = $fields[$fieldName];
	endswitch;

	// If field is a dropdown list, find the list option that represents $value and inject the 'selected' attribute to pre-select this option.
	if ( in_array($fieldName, ['mpInput','mpDatatype','mpNominal','mpValidity']) && strpos($field, '<select ') !== false && isset($value) ) :
		if ($fieldName == 'mpValidity') :
			$find    = '<option value="' . $value  . '"';
			$replace = '<option value="' . $value  . '" selected';
			$field   = str_ireplace($find, $replace, $field);

			switch ($value)
			{
				case 'invalid' :
					$field = str_ireplace('" id="lst-mpValidity-', ' text-danger" id="lst-mpValidity-',  $field);
				break;

				case 'conditionally_valid' :
					$field = str_ireplace('" id="lst-mpValidity-', ' text-warning" id="lst-mpValidity-',  $field);
				break;

				case 'valid' :
					$field = str_ireplace('" id="lst-mpValidity-', ' text-success" id="lst-mpValidity-', $field);
				break;
			}
		else :
			if ($value) :
				$field = preg_replace("/($value\")>/", "$1 selected>", $field);
			endif;
		endif;
	endif;

	// If field the mpDatatype field and compositing mode is active, add field attributes to bind a datatype change handler.
	// Additionally, $value to output either the field value or that datatype.
	if ($fieldName == 'mpDatatype') :
		// In tracking mode this field must be a readonly input field, as it must not be changeable.
		if (true == $isTracking) :
			switch ($dataType) :
				case 'boolval':
					// Delete 'data-bind' placeholder
					$field = str_ireplace('%BINDTO%', '', $field);

					// Set placeholder for datatype translation string to "true/false"
					$value = '%OPT10%';
				break;

				case 'number':
				case 'string':
				default:
					// Set placeholder for datatype translation string to "number"
					$value = '%OPT11%';
				break;
			endswitch;
		// In composition mode this field must be a dropdown list, as it must be changeable.
		else :
			if (true == $isReadonly) :
				/*$value = ($dataType == 'boolval') ? '%OPT10%' : '%OPT11%';*/

				switch ($dataType) :
					case 'boolval':
						$value = '%OPT10%';
					break;

					case 'number':
						$value = '%OPT11%';
					break;

					case 'string':
						$value = '%OPT12%';
					break;
				endswitch;
			else :
				// FIXME - consider new %OPT12%
				$field = str_ireplace('%BINDTO%', 'data-bind="dataTypeSelected" ' .
												  'data-toggle-elements="#p-%PID%-%CNT%-td-mpNominal > .interchangeable"' .
												  'data-target="#ipt-mpLowerTol-%PID%-%CNT%, #ipt-mpUpperTol-%PID%-%CNT%, #ipt-mpToleranceFactor-%PID%-%CNT%" ',
												  $field);
			endif;
		endif;
	endif;

	// If the nominal value field is to be rendered in non compositing mode where the field is read only its value must be translated.
	// Hence, the value is to be replaced by the related dropdown list option, which is going to be translated.
	if ($fieldName == 'mpNominal') :
		if (true == $isTracking || true == $isReadonly) :
			$value = ($value == 'true' ? '%OPT2%' : ($value == 'false' ? '%OPT3%' : $value));
		endif;
	endif;
	/*if ($isAutoTrackm || $isTracking) :
		for($i=0; $i<=10; $i++) {
			echo "<script>
              $ = jQuery; 
              $('#lst-mpInput-14-{$i}').val('true');

              </script>";
		}
	endif;*/
	// If datatype is a boolean some fields must not be editable and are flagged with a placeholder.
	// Hence, that placeholder must be activated or removed.
	if ( in_array($fieldName, ['mpNominal','mpLowerTol','mpUpperTol']) ) :
		if (in_array($dataType, ['boolval','string'])) :
			$field = str_ireplace('%DISABLED%', 'disabled', $field);
		else :
			$field = str_ireplace('%DISABLED%', '', $field);
		endif;
	else :
		$field = str_ireplace('%DISABLED%', 'disabled', $field);
	endif;

	// In non-compositing mode most of the fields are disabled. Hence, they do not require a placeholder nor a title attribute.
	if (true == $isTracking && in_array($fieldName, ['mp','mpDatatype','mpNominal','mpLowerTol','mpUpperTol'])) :
		$field = preg_replace('~\s(placeholder)="[^"]*"~', '', $field);
	endif;

	// At tracking view translate boolval.
	if (true == $isTracking && true == $isReadonly && in_array($fieldName, ['mpInput']) && in_array($value, ['true', 'false'])) :
		$value = Text::translate(Text::untranslate($value), $this->language);
	endif;

	// Final step: placeholder substitution.
	return preg_replace(
		['/%PID%/', '/%CNT%/', '/%KEY%/', '/%VALUE%/', '/%PH%/',     '/%TITLE%/', '/%REQUIRED%/'],
		[$procID  , $cnt,      $fieldName,      $value,      $placeholder, $title,      'required'],
		$field
	);
};
?>
<?php /* Pre-process table body markup */  // TODO - translate
$tbody    = [];
$tbody['tb-0']  = '<tbody id="p-%PID%-tbody"' .
	' data-next-id="%TOTAL%"' . ( (!$isTracking) ? ' data-row-template="' . base64_encode($rowDummy) . '"' : '' ) .
'>';

// If this process has no measuring definitions, assign the mock object to generate at least 1 empty row.
if (empty($definedMPs)) :
	$tbody['tb-0'] = str_ireplace('%TOTAL%', '0', $tbody['tb-0']);
endif;

foreach ($definedMPs as $pid => $MPs) :
	$tbody['tb-0'] = str_ireplace('%TOTAL%', count($MPs), $tbody['tb-0']);

	// Fetch measured data for this measuring point if there is any.
	$data = ArrayHelper::getValue($measuredData, $pid, [], 'ARRAY');

	// Iterate of the all defined measuring points for the given process and generate the corresponding form field
	$i = 0;
	foreach ($MPs as $idx => $MP) :
		// Update measurement tolerance factor to current value as defined in project configuration.
		$MP['mpToleranceFactor'] = ($MP['mpDatatype'] == 'number')
			? number_format($spreadFactor, 2)
			: $MP['mpToleranceFactor'];

		/* A stored definition may not cover all options as defined in the above mock object.
		 * Hence, to ensure we start with a complete object the mock object serves as a base object.
		 * With array_merge() we ensure that no existing data will be changed, but only missing fields will be injected.
		 */
		$MP = array_merge(current($mockObject), $MP);

		$mp = ArrayHelper::getValue($MP, 'mp', null, 'STRING');

		$MP['mpToolbar'] = null;

		$tmp = [];
		$tmp['tr-0' ] = '<tr id="' . sprintf('p-%d-%d-tr', $pid, $i) . '">';

		// Now iterate over the definition object properties and generate the corresponding form fields.
		$j = 1;
		$MP = array_filter($MP, function ($targetValue, $fieldName) use (&$mp, &$MP, &$getField, &$isTracking, &$i, &$j, &$pid, &$hide, &$data, &$tmp, &$debug, &$user)
		{
			$values = ArrayHelper::getValue($data, $mp, [], 'ARRAY');

			// Skip every array item that is no measuring point (key begins with "mp").
			if (!preg_match('/^mp.*/', $fieldName)) :
				return false;
			endif;

			// Skip every measuring point property that was declared to be hidden (passed in via render options).
			if (in_array(mb_strtolower($fieldName), $hide)) :
				$j += 1;
				return false;
			endif;

			$attrTitle = '';
			if ($fieldName == 'mpDescription') :
				$attrTitle = $targetValue;
			endif;

			/* As we provide both dropdown lists and text fields we must know when to render what.
			 * This information is provided via measuring point property "mpDatatype".
			 */
			$dataType = ArrayHelper::getValue($MP, 'mpDatatype');

			// Prepare placholder attribute value for injection. This is going to be substituted at a later stage.
			$attrPlaceholder = "%PH$j%";

			// Define variable that holds previous user input for current property.
			$userValue = $targetValue;

			// Define user input validation status.
			$status = ArrayHelper::getValue($data, 'status', '', 'STRING');

			// When in 'tracking mode' and for selected properties replace $userValue with
			// previously tracked information or fall back to compositing (default) value.
			if (true == $isTracking && !in_array($fieldName, ['mp','mpDescription','mpDatatype'])) :
				$userValue = ArrayHelper::getValue($values, $fieldName, $targetValue);
			endif;

			// Generate field using matching field skeleton from fields list defined above.
			$tmp[sprintf('p-%d-td-%s', $pid, $fieldName)] = $getField('' . $fieldName, '' . $userValue, '' . $status, '' . $dataType, (int) $pid, (int) $i, '' . $attrPlaceholder, '' . $attrTitle);

			$j += 1;

			return true;

		}, ARRAY_FILTER_USE_BOTH);

		$tmp['tr-1' ] = '</tr>';

		// Skip columns that shall be hidden (defined in $this->hide);
		$tmp = array_filter($tmp, function ($markup, $key) use (&$hide)
		{
			return !in_array(mb_strtolower(mb_substr($key, 3)), $hide);

		}, ARRAY_FILTER_USE_BOTH);

		$tbody[] = implode('', $tmp);

		$i += 1;
	endforeach;
endforeach;

$tbody['tb-1'] = '</tbody>';

$tbody = implode('', $tbody);
?>
<?php /* Merge table components */
$frmMeasurementDefinitions = preg_replace(
	[ '/%THEAD%/', '/%TCAPTION%/', '/%TFOOT%/', '/%TBODY%/' ],
	[   $thead,      $tcaption,      $tfoot,      $tbody    ],
	$frmMeasurementDefinitions
);
?>
<?php /* Substitute placeholders */	// TODO - translate
$frmMeasurementDefinitions = preg_replace('/(\n+|\r+|\s{2,}|\t+)/', ' ', $frmMeasurementDefinitions);
$frmMeasurementDefinitions = preg_replace(
	[
		'/%ABRV1%/'    ,'/%ABRV2%/'    ,'/%ABRV3%/'    ,'/%ABRV4%/'    ,'/%ABRV5%/'    ,'/%ABRV6%/'    ,'/%ABRV7%/'    ,'/%ABRV8%/'    ,'/%ABRV9%/'    ,'/%ABRV10%/'    ,'/%ABRV11%/',
		'/%TH1%/'      ,'/%TH2%/'      ,'/%TH3%/'      ,'/%TH4%/'      ,'/%TH5%/'      ,'/%TH6%/'      ,'/%TH7%/'      ,'/%TH8%/'      ,'/%TH9%/'      ,'/%TH10%/'      ,'/%TH11%/',
		'/%PH1%/'      ,'/%PH2%/'      ,'/%PH3%/'      ,'/%PH4%/'      ,'/%PH5%/'      ,'/%PH6%/'      ,'/%PH7%/'      ,'/%PH8%/'      ,'/%PH9%/'      ,'/%PH10%/'      ,'/%PH11%/',
		'/%OPT1%/'     ,'/%OPT2%/'     ,'/%OPT3%/'     ,'/%OPT4%/'     ,'/%OPT5%/'     ,'/%OPT6%/'     ,'/%OPT10%/'    ,'/%OPT11%/'    ,'/%OPT12%/',
		'/%REQUIRED1%/','/%REQUIRED2%/','/%REQUIRED3%/','/%REQUIRED4%/','/%REQUIRED5%/','/%REQUIRED6%/','/%REQUIRED7%/','/%REQUIRED8%/','/%REQUIRED9%/','/%REQUIRED10%/','/%REQUIRED11%/',
	],
	[
		// Abbreviations %ABRVx%
		/* col  1 - mp                */ Text::translate('COM_FTK_ABBREV_MEASURING_POINT_TEXT',                        $this->language),
		/* col  2 - mpDescription     */ Text::translate('COM_FTK_ABBREV_MEASURING_POINT_DESC_TEXT',                   $this->language),
		/* col  3 - mpDatatype        */ Text::translate('COM_FTK_ABBREV_USER_INPUT_EXPECTATION_TEXT',                 $this->language),
		/* col  4 - mpInput           */ Text::translate('COM_FTK_ABBREV_MEASURED_VALUE_TEXT',                         $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_ABBREV_NOMINAL_VALUE_TEXT',                          $this->language),
		/* col  6 - mpLowerTol        */ Text::translate('COM_FTK_ABBREV_LOWER_TOLERANCE_TO_TARGET_VALUE_TEXT',        $this->language),
		/* col  7 - mpUpperTol        */ Text::translate('COM_FTK_ABBREV_UPPER_TOLERANCE_TO_TARGET_VALUE_TEXT',        $this->language),
		/* col  8 - mpToleranceFactor */ Text::translate('COM_FTK_ABBREV_TOLERANCE_FACTOR_TEXT',                       $this->language),
		/* col  9 - mpValidity        */ Text::translate('COM_FTK_ABBREV_IS_MEASURING_RESULT_VALID_TEXT',              $this->language),
		/* col 10 - mpFrequency       */ Text::translate('COM_FTK_ABBREV_MEASURING_FREQUENCY_TEXT',                    $this->language),
		/* col 11 - mpFrequencyScope  */ Text::translate('COM_FTK_ABBREV_MEASURING_FREQUENCY_SCOPE_TEXT',              $this->language),

		// Table heads %THx%
		/* col  1 - mp                */ Text::translate('COM_FTK_LABEL_MEASURING_POINT_SHORT_TEXT',                   $this->language),
		/* col  2 - mpDescription     */ Text::translate('COM_FTK_LABEL_SPECIFICATION_TEXT',                           $this->language),
		/* col  3 - mpDatatype        */ Text::translate('COM_FTK_LABEL_DATA_TYPE_TEXT',                               $this->language),
		/* col  4 - mpInput           */ Text::translate('COM_FTK_LABEL_MEASURED_VALUE_TEXT',                          $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_LABEL_NOMINAL_VALUE_TEXT',                           $this->language),
		/* col  6 - mpLowerTol        */ sprintf('%s <span class="d-inline-block ml-1 align-middle font-size-150" style="margin-bottom:5px;">&ndash;</span>', Text::translate('COM_FTK_LABEL_TOLERANCE_TEXT', $this->language)),
		/* col  7 - mpUpperTol        */ sprintf('%s <span class="d-inline-block ml-1 align-middle font-size-150" style="margin-bottom:5px;">+</span>', Text::translate('COM_FTK_LABEL_TOLERANCE_TEXT', $this->language)),
		/* col  8 - mpToleranceFactor */ Text::translate('COM_FTK_LABEL_FACTOR_TEXT',                                  $this->language),
		/* col  9 - mpValidity        */ Text::translate('COM_FTK_LABEL_VALIDITY_TEXT',                                $this->language),
		/* col 10 - mpFrequency       */ Text::translate('COM_FTK_LABEL_FREQUENCY_SHORT_TEXT',                         $this->language),
		/* col 11 - mpFrequencyScope  */ Text::translate('COM_FTK_LABEL_FREQUENCY_SCOPE_TEXT',                         $this->language),

		// Placeholders %PHx%
		/* col  1 - mp                */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_POINT_TEXT',             $this->language),
		/* col  2 - mpDescription     */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_POINT_DESCRIPTION_TEXT', $this->language),
		/* col  3 - mpDatatype        */ null,	// it's a dropdown
		/* col  4 - mpInput           */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_USER_INPUT_TEXT',                  $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_SETPOINT_TEXT',                    $this->language),
		/* col  6 - mpLowerTol        */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_LOWER_TOLERANCE_TEXT',             $this->language),
		/* col  7 - mpUpperTol        */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_UPPER_TOLERANCE_TEXT',             $this->language),
		/* col  8 - mpToleranceFactor */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_TOLERANCE_FACTOR_TEXT',            $this->language),
		/* col  9 - mpValidity        */ null,	// it's a masked dropdown
		/* col 10 - mpFrequency       */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_FREQUENCY_TEXT',         $this->language),
		/* col 11 - mpFrequencyScope  */ Text::translate('COM_FTK_INPUT_PLACEHOLDER_MEASURING_FREQUENCY_SCOPE_TEXT',   $this->language),

		// List options %OPTx%
		Text::translate('COM_FTK_LABEL_SELECT_TEXT',                    $this->language),	// %OPT1%
		Text::translate('COM_FTK_LIST_OPTION_TRUE_TEXT',                $this->language),	// %OPT2%
		Text::translate('COM_FTK_LIST_OPTION_FALSE_TEXT',               $this->language),	// %OPT3%
		Text::translate('COM_FTK_LIST_OPTION_VALID_TEXT',               $this->language),	// %OPT4%
		Text::translate('COM_FTK_LIST_OPTION_CONDITIONALLY_VALID_TEXT', $this->language),	// %OPT5%
		Text::translate('COM_FTK_LIST_OPTION_INVALID_TEXT',             $this->language),	// %OPT6%
		Text::translate('COM_FTK_LIST_OPTION_BOOLVAL_TEXT',             $this->language),	// %OPT10%
		Text::translate('COM_FTK_LIST_OPTION_NUMBER_TEXT',              $this->language),	// %OPT11%
		Text::translate('COM_FTK_LIST_OPTION_STRING_TEXT',              $this->language),	// %OPT12%

		// Required field-messages %REQUIREDx%
		/* col  1 - mp                */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  2 - mpDescription     */ null,
		/* col  3 - mpDatatype        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  4 - mpInput           */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  5 - mpNominal         */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  6 - mpLowerTol        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  7 - mpUpperTol        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  8 - mpValidity        */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col  9 - mpFrequency       */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col 10 - mpFrequencyScope  */ Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT',		  $this->language),
		/* col 11 - mpToleranceFactor */ Text::translate('COM_FTK_HINT_MEASURED_VALUE_REQUIRED_TEXT', $this->language),
	],
	$frmMeasurementDefinitions
);
?>

<?php /* Merge table markup into template markup */
$frmMeasurementDefinitions = preg_replace('~%PID%~i', $pid, $frmMeasurementDefinitions);
?>

<?php // Render markup
echo $frmMeasurementDefinitions;
