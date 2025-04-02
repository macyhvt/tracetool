<?php
// Register required libraries.
use Joomla\Registry\Registry;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang       = $this->get('language');
$data       = new Registry($this->data);

$attribs    = $data->extract('attribs', new Registry);
$attribs    = $attribs ?? new Registry;
$inline     = $attribs->get('inline', false);
$id         = $attribs->get('id');
$class      = $attribs->get('class');
$required   = $attribs->get('required', false);
$tabindex   = $attribs->get('tabindex');
$inputClass = $attribs->get('inputClass');
$labelClass = $attribs->get('labelClass');
$labelPos   = $attribs->get('labelPosition');
$check      = $attribs->get('checked') ?? false;

// $autosubmit = $attribs->get('autosubmit', false);

$layout     = $data->get('layout');
$label      = $data->get('label');
$name       = $data->get('name');
$value      = $data->get('value');
$form       = $data->get('form');
$multiple   = $data->get('multiple') == 'true';
?>
<?php switch ($layout) : ?>
<?php	case 'button' : ?>
<?php		$labelClass .= ($check == 'true' ? ' btn-info' : ' btn-info disabled'); ?>
<div class="<?php echo trim('form-check form-check-inline ' . $class); ?>">
	<label for="<?php echo $id; ?>" class="<?php echo trim('form-check-label btn ' . $labelClass); ?>" style="min-width:4rem">
		<input type="checkbox"
			   class="<?php echo trim('form-check-input d-none' . $inputClass); ?>"
			   id="<?php echo $id; ?>"
			   form="<?php echo $form; ?>"
			   name="<?php echo $name; ?><?php echo ($multiple ? '[]' : ''); ?>"
			   value="<?php echo $value; ?>"
			   tabindex="<?php echo $tabindex; ?>"
			   <?php if ($check) : ?>
			   checked
			   <?php endif; ?>
			   onclick="document.<?php echo $form; ?>.submit();"
		><?php echo htmlentities($label); ?>
	</label>
</div>
<?php 	break; ?>

<?php	default : ?>
<div class="<?php echo trim('form-check form-check-inline ' . $class); ?>" style="min-width:4rem">
	<?php if ($labelPos == 'before') : ?>
	<label for="<?php echo $id; ?>" class="<?php echo trim('form-check-label ' . $labelClass); ?>">
		<?php echo htmlentities($label); ?>
	</label>
	<input type="checkbox"
		   class="<?php echo trim('form-check-input ' . $inputClass); ?>"
		   id="<?php echo $id; ?>"
		   form="<?php echo $form; ?>"
		   name="<?php echo $name; ?><?php echo ($multiple ? '[]' : ''); ?>"
		   value="<?php echo $value; ?>"
		   tabindex="<?php echo $tabindex; ?>"
		   <?php if ($check) : ?>
		   checked
		   <?php endif; ?>
		   onchange="document.<?php echo $form; ?>.submit();"
	>
	<?php else : ?>
	<input type="checkbox"
		   class="<?php echo trim('form-check-input ' . $inputClass); ?>"
		   id="<?php echo $id; ?>"
		   form="<?php echo $form; ?>"
		   name="<?php echo $name; ?><?php echo ($multiple ? '[]' : ''); ?>"
		   value="<?php echo $value; ?>"
		   tabindex="<?php echo $tabindex; ?>"
		   <?php if ($check) : ?>
		   checked
		   <?php endif; ?>
		   onchange="document.<?php echo $form; ?>.submit();"
	>
	<label for="<?php echo $id; ?>" class="<?php echo trim('form-check-label ' . $labelClass); ?>">
		<?php echo htmlentities($label); ?>
	</label>
	<?php endif; ?>
</div>
<?php 	break; ?>

<?php endswitch; ?>
