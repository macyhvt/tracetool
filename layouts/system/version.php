<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php
echo sprintf('%s v%s', ArrayHelper::getValue($this->data, 'appName'), ArrayHelper::getValue($this->data, 'appVersion'));
