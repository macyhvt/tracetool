<?php
// Register required libraries.;
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Nematrack\Helper\LayoutHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang       = $this->get('language');
$data       = new Registry($this->data);
$layout     = $data->get('layout');
$multiple   = $data->get('multiple', false);
$form       = $data->get('form');
$tabindex   = $data->get('tabindex');

$attribs    = $data->extract('attribs', new Registry);
$attribs    = $attribs ?? new Registry;
$name       = $attribs->get('name');
$class      = $attribs->get('class');
$required   = $attribs->get('required', false);
$autosubmit = $attribs->get('autosubmit', false);

$options    = (array) $data->get('options');
$uncheck    = (array) $data->get('uncheck');

if ($data->get('sorted', false) == 'true') :
	switch ($data->get('sort')) :
		case 'key' :
		case 'keys' :
			if (mb_strtolower($data->get('ordering', 'ASC')) == 'desc') :
				krsort($options);
			else :
				ksort($options);
			endif;
		break;

		case 'value' :
		case 'values' :
			if (mb_strtolower($data->get('ordering', 'ASC')) == 'desc') :
				arsort($options);
			else :
				asort($options);
			endif;
		break;

		default :
			sort($options);
	endswitch;
endif;
?>

<?php foreach ($options as $value => $label) : ?>
<?php	$check = !\in_array($value, $uncheck); ?>

<?php	echo LayoutHelper::render('widget.controls.checkbox', (object) [
			'form'       => $form,
			'multiple'   => $multiple,
			'layout'     => $layout,
			'name'       => $name,
			'value'      => $value,
			'label'      => $label,
			'attribs'    => [
				'id'         => mb_strtolower('cb-' . OutputFilter::cleanText($label)),
				'class'      => $class,	// class for wrapping container
				'labelClass' => ($layout == 'button' ? 'btn-sm' : ''),
				// 'inputClass' => '',
				// 'required' => false
				'tabindex'   => ++$tabindex,
				'checked'    => $check
			]
		], ['language' => $lang]
	); ?>
<?php endforeach; ?>
