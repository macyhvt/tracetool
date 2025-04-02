<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$data        = new Registry($this->data);
$debug       = $data->get('debug', false);

$attribs     = $data->extract('attribs');
$attribs     = $attribs ?? new Registry;
$form        = $attribs->get('form');
$name        = $attribs->get('name');
$class       = $attribs->get('class');
$id          = $attribs->get('id');
$required    = $attribs->get('required', false);
$tabindex    = $attribs->get('tabindex');
$autosubmit  = $attribs->get('autosubmit', false);

$options     = (array) $data->get('options');

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

$dataAttribs = [];
$extractDataAttribs = function (array $list, string $prefix = '') use (&$dataAttribs, &$extractDataAttribs, &$debug)
{
	if (is_countable($list) && count($list))
	{
		foreach ($list as $key => $val)
		{
			// Recursion
			if (is_countable($val) && count($val))
			{
				$extractDataAttribs($val, $key);	// Recursion: referencing the closure
			}
			else
			{
				// Build data attribute name.
				$key = empty($prefix) ? sprintf('data-%s', $key) : sprintf('data-%s-%s', $prefix, $key);

				// Convert boolean values to string.
				if (is_bool($val))
				{
					$val = $val ? "true" : "false";
				}

				$dataAttribs[$key] = $val;
			}
		}
	}
};

// Extract passed data attributes.
$extractDataAttribs( $attribs->extract('data-attribs')->toArray() );
?>

<?php if (!empty($options)) : ?>
<select name="<?php echo $name; ?>"
		class="<?php echo trim('form-control ' . $class); ?>"
		<?php if ($id) : ?>
		id="<?php echo $id; ?>"
		<?php endif; ?>
		<?php if ($form) : ?>
		form="<?php echo $form; ?>"
		<?php endif; ?>
		<?php if ($tabindex) : ?>
		tabindex="<?php echo (int) $tabindex; ?>"
		<?php endif; ?>
		<?php if ($required) : ?>
		required
		data-rule-required="true"
		data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
		<?php endif; ?>

		<?php if (is_countable($dataAttribs) && count($dataAttribs)) : ?>
		<?php 	foreach ($dataAttribs as $key => $val) : ?>
		<?php 		echo sprintf('%s="%s"', $key, $val); ?>
		<?php 	endforeach; ?>
		<?php endif; ?>
>
	<?php //if (!$attribs->get('data-attribs.placeholder')) : ?>
	<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); ?> &ndash;</option>
	<?php //endif; ?>
	<?php foreach ($options as $key => $value) : ?>
	<option value="<?php echo $key; ?>"><?php echo htmlentities($value); ?></option>
	<?php endforeach; ?>
</select>
<?php endif; ?>
