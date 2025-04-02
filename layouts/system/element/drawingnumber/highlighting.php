<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\UriHelper;

// No direct script access
defined('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars  */
$debug       = isset($_GET['auth']) && $_GET['auth'] == 'dev-op';
$data        = $this->get('data', []);
$number      = ArrayHelper::getValue($data, 'number',  '', 'CMD'); // tracked number
$linkTooltip = ArrayHelper::getValue($data, 'tooltip', '', 'STRING');
$iconTooltip = ArrayHelper::getValue($data, 'icon-tooltip', '', 'STRING');
// Drawing object
$drawing     = ArrayHelper::getValue($data, 'drawing', [], 'ARRAY');
$drawing     = new Registry($drawing);
$currentNo   = sprintf('%s.%s', $drawing->get('number'), $drawing->get('index'));  // latest number
// PDF-file
$filePDF     = UriHelper::osSafe( UriHelper::fixURL($drawing->get('file')) );
$filePDF     = str_ireplace($currentNo, $number, $filePDF);
$filePDF     = (is_file(FTKPATH_BASE . $filePDF) && is_readable(FTKPATH_BASE . $filePDF))
	? $filePDF . '?t=' . mt_rand(0, 9999999)
	: null;

/*// @debug
if ($debug) :
//	echo '<pre><small class="text-bold">data: </small>'      . print_r($data,      true) . '</pre>';
//	echo '<pre><small class="text-bold">number: </small>'    . print_r($number,    true) . '</pre>';
//	echo '<pre><small class="text-bold">drawing: </small>'   . print_r($drawing,   true) . '</pre>';
//	echo '<pre><small class="text-bold">currentNo: </small>' . print_r($currentNo, true) . '</pre>';
//	echo '<pre><small class="text-bold">filePDF: </small>'   . print_r($filePDF,   true) . '</pre>';
//	die;
endif;*/
?>
<?php if ($filePDF) : ?><?php
$number = explode('.', $number);
$index  = array_pop($number);
$number = implode('.', $number);
?>
<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL($filePDF)); ?>"
   class="text-reset text-decoration-none"
   data-toggle="tooltip"
   title="<?php echo OutputFilter::cleanText($linkTooltip); ?>"
   target="_blank"
><?php echo sprintf('%s.<strong class="text-danger" style="font-size:104%%">%s</strong>',
	OutputFilter::cleanText($number),
	OutputFilter::cleanText($index)
); ?></a>
<i class="d-inline-block align-sub ml-2 ml-lg-3"
      data-toggle="tooltip"
      title="<?php echo OutputFilter::cleanText($iconTooltip); ?>"
      style="background:transparent url('/assets/img/global/warning_24x22.png') 50% 50% no-repeat; background-size:85%; width:24px; height:22px"
></i>
<?php endif; ?>
