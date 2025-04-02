<?php
// Register required libraries.
use Joomla\Registry\Registry;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$heading = trim('' . $this->get('heading', ''));
$message = trim('' . $this->get('message', ''));
$attribs = new Registry((array) $this->get('attribs', []));
$attribs->set('class', trim('alert alert-secondary ' . $attribs->get('class')));

$tmp = [];

$attribsArray = $attribs->toArray();

array_walk($attribsArray, function ($value, $key) use (&$tmp) { array_push($tmp, sprintf('%s="%s"', $key, $value));  return true; });

$attribs = implode(' ', $tmp);
?>
<?php if (!empty($message)) : ?>
<div role="alert" <?php echo $attribs; ?>>
	<?php if (!empty($heading)) : ?>
	<h4 class="alert-heading">
		<i class="fas fa-info-circle mr-3"></i><?php echo $heading; ?>
	</h4>
	<?php endif; ?>
	<p class="mb-0"><?php if (empty($heading)) : ?><i class="fas fa-info-circle mr-<?php echo preg_match('/alert-sm/i', $attribs) ? '2' : '3'; ?>"></i><?php endif; ?><?php echo $message; ?></p>
</div>
<?php endif; ?>

<?php // Free memory.
unset($attribsArray);
unset($tmp);
