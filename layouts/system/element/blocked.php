<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\UriHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Process form data */
$styles  = ArrayHelper::getValue($this->get('options', []), 'style', [], 'ARRAY');
$styles  = array_merge([
	'z-index'  => '1',
	'outline'  => '0 !important;',
	'top'      => '0',
	'left'     => '0',
	'opacity'  => '0.4',
	'overflow' => 'hidden',
	'background-size'       => 'contain',
	'background-color'      => 'rgba(245,245,245,1)',
	'background-repeat'     => 'no-repeat',
	'background-image'      => 'url(' . UriHelper::osSafe( UriHelper::fixURL( '/assets/img/global/disabled-big.png' ) ) . ')',
	'background-position-x' => 'center',
	'background-position-y' => 'center'
], $styles);

$style = '';

array_walk($styles, function($value, $key) use(&$style)
{
	$style .= '; ' . $key . ':' . $value;
});

$style = trim($style, ';');
?>
<div class="position-absolute d-block w-100 h-100" style="<?php echo trim($style); ?>"></div>
