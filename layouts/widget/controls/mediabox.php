<?php
// Register required libraries.
use Nematrack\Helper\LayoutHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$context = $this->get('context');
?>
<?php
if (isset($context)) :
	echo LayoutHelper::render(sprintf('widget.controls.mediabox.%s', $context), $this->data, ['language' => $this->get('language')]);
else :
	echo '<p class="alert alert-danger">Something went wrong</p>';
endif;
