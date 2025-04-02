<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Utilities\ArrayHelper;

// No direct script access
defined('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars  */
$debug  = isset($_GET['auth']) && $_GET['auth'] == 'dev-op';
$data   = $this->get('data', []);
$number = ArrayHelper::getValue($data, 'number', '', 'CMD'); // tracked number

/*// @debug
if ($debug) :
//	echo '<pre><small class="text-bold">data: </small>'    . print_r($data,    true) . '</pre>';
//	echo '<pre><small class="text-bold">number: </small>'  . print_r($number,  true) . '</pre>';
//	echo '<pre><small class="text-bold">drawing: </small>' . print_r($drawing, true) . '</pre>';
//	die;
endif;*/

echo OutputFilter::cleanText($number);