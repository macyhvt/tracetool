<?php
// Register required libraries.
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* init vars */
die('Test 2');

$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
//$user   = $view->get('user');
$layout = $input->getCmd('layout');

// SELECT 1 INTO @userID;
// SELECT `orgID` INTO @orgID FROM `organisation_user` WHERE `userID` = @userID;
// UPDATE `users` SET `hash` = CONCAT_WS(':', @orgID, @userID, `fullname`)

//$usersModel = $model->getInstance('users');
//$dbo        = Factory::getDbo();
//$users      = Factory::getDbo()
