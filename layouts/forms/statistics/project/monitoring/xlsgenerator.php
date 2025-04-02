<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang    = $this->get('language');
$view    = $this->__get('view');
$input   = $view->get('input');
$model   = $view->get('model');
$dbo     = $model->get('db');
$user    = $view->get('user');

$layout  = $input->getCmd('layout');
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

// TODO - Implement ACL and make calculate editor-right from ACL
$canDo   = true;
?>
<?php /* Load view data */
$data        = $view->get('data');

$timeZone    = new DateTimeZone(FTKRULE_TIMEZONE);
$dateFormat  = 'd-m-Y';
$today       = (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format($dateFormat);
$dateFrom    = $data->get('dateFrom');
$dateFrom    = date_create($dateFrom, $timeZone);
$dateFrom->setTime(0,0,0); // Necessary to ensure this date is included
$dateTo      = $data->get('dateTo');
$dateTo      = date_create($dateTo, $timeZone);
$dateTo->setTime(23,59,59); // Necessary to ensure this date is included
$dateRange   = new DatePeriod($dateFrom, new DateInterval('P1D'), $dateTo);

$selProjects  = $data->get('selProjects', []);  // List of selected project IDs
$projects     = array_filter($data->get('projects', []), function($project, $proID) use(&$selProjects) {
	return in_array($proID, $selProjects);
}, ARRAY_FILTER_USE_BOTH); sort($projects); // All registered and active projects
$projectsCnt  = count($projects);

$selProcesses = $data->get('selProcesses', []); // List of selected process IDs
$processes    = $data->get('processes', []);    // All registered and active processes
$processesCnt = count($selProcesses);
$processIDs   = &$selProcesses;
$processIDs   = array_map('intval', $processIDs);

$pidAbbrMap   = array_combine($processIDs, array_fill(0, $processesCnt, null)); // List of process abbreviations as used in Excel to identify a project
$pidAbbrMapCount = count($pidAbbrMap);

foreach ($pidAbbrMap as $pid => &$value) :
	$value = mb_strtoupper(ArrayHelper::getValue((array) ArrayHelper::getValue($processes, $pid), 'abbreviation'));
endforeach;

// Consolidate processes like mrk1, mrk2, mrk..., mrk99 to process group MRK.
// Ensure the ordering is:  CUT, MRK, BND, GLV, PWD, PCK
$processGroups = array_map(function(&$elem) { return trim(preg_replace('/\d+/i', '', mb_strtoupper('' . $elem))); }, $pidAbbrMap);
$processGroups = array_flip(array_flip($processGroups)); // Workaround to make array unique without changing value ordering (see {@link https://stackoverflow.com/questions/5350080/array-unique-without-sorting/5350142})
$processGroupsCount = count($processGroups);

// Update monitoring data table.
$statsModel = $model->getInstance('statistics', ['language' => $this->language]);
$statsModel->populateMonitorDataTables();

$XLSX = new SimpleXLSXGen;
$XLSX->setDefaultFont('Calibri')->setDefaultFontSize(11);

// Add as the first datasheet the date interval dates.
$data = [
	['<b>from</b>','<b>to</b>'], [$dateFrom->format($dateFormat), $dateTo->format($dateFormat)]
];

$XLSX->addSheet($data, 'Dates');

foreach ($projects as $project)
{
	// Load project details.
	$proID = ArrayHelper::getValue($project, 'proID');

	// Ensure that the project exists.
//	if (!is_a($project, 'Nematrack\Entity\Project') || !$project->get('number'))
	if (!$proID)
	{
		// TODO - translate
		throw new \Exception(sprintf('Unknown project ID: %d', $proID), 404);
	}

	// Init column titles.
	$data = [
		['<b>Date</b>'] + array_map(function($elem) { return sprintf('<b>%s</b>', $elem); }, $processGroups)
	];

	$numbers = $statsModel->fetchMonitorDataTables(
		$proID,
		$dateFrom->format($dateFormat),
		$dateTo->format($dateFormat),
		$pidAbbrMap
	);

	array_filter($numbers, function($line, $date) use(&$data)
	{
		$line = explode('|', $line);

		array_unshift($line, $date);

		array_push($data, $line);

	}, ARRAY_FILTER_USE_BOTH);

	$XLSX->addSheet($data, mb_strtoupper(ArrayHelper::getValue($project, 'number')));
}

// Data source is now created and ready to be sent to the user.
$XLSX->downloadAs('Output Monitoring_datasource.xlsx');

// I M P O R T A N T :   'exit()' must be called or the Excel file import will fail!
exit;
