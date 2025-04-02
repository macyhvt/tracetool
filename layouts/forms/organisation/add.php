<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$oid    = $input->getInt('oid');
?>
<?php /* Access check */
$formData = null;

if (is_a($user, ' \Entity\User')) :
	try
	{
		$formData = $user->__get('formData');
		$formData = (is_array($formData)) ? $formData : [];
	}
	catch (Exception $e)
	{
		$formData = null;
	}
endif;

// TODO - Implement ACL and make calculate editor-right from ACL
$canAdd = true;
?>
<?php /* Process form data */
if (!empty($_POST)) :
	$view->saveAdd();
endif;
?>
<?php /* Load view data */
$item  = $view->get('item');
$roles = $model->getInstance('roles', ['language' => $this->language])->getList();

$this->item     = $item;
$this->user     = $user;
$this->formData = $formData;
$this->roles    = $roles;
$this->lastID   = $model->getInstance('processes', ['language' => $this->language])->getLastInsertID();

echo (FTK_DEBUG ? '<pre style="color:crimson">' . print_r(sprintf('Last Organisation-ID: %d', $this->lastID), true) . '</pre>' : null);

// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl',     $this->language);
$formAction->setVar('view',   $view->get('name'));
$formAction->setVar('layout', $layout);

// Init tabindex
$this->tabindex = 0;
?>


<style>
.nav-tabs {
	overflow: hidden;
}
.col-form-label,
.nav-tabs .nav-link {
	background-color: #e8edf3;
	border-color: #dee3e9 #dee3e9 #dee2e6;
	color: #30588B;
}
.nav-tabs .nav-link.active {
	background-color: #d9dee4;
	background-color: #d1e8ff;
	border-color: #cfd4da #cfd4da #dee2e6;
	color: #264E81;
}
.nav-tabs .nav-item > .nav-link.active {
	box-shadow: 0 0 6px 0 rgba(128, 128, 128, 0.5);
	box-shadow: 0 3px 9px 2px rgba(169, 192, 223, 0.8);
}
.nav-tabs .nav-item:first-of-type > .nav-link.active {
	box-shadow: 3px 0 6px -3px rgba(128, 128, 128, 0.5);
	box-shadow: 3px 3px 9px -1px rgba(169, 192, 223, 0.8);
}
.nav-tabs .nav-item:last-of-type > .nav-link.active {
	box-shadow: -3px 0 6px -3px rgba(128, 128, 128, 0.5);
	box-shadow: -3px 3px 9px -1px rgba(169, 192, 223, 0.8);
}

.form-horizontal.blocked-item {
	overflow-x: hidden;
}
.form-horizontal.blocked-item .status-badge {
	left: 0;
	line-height: 2;
}

.status-badge {
	letter-spacing: 1px;
}

.input-group-append .btn,
.input-group-append .btn:focus,
.input-group-append .btn:active,
.input-group-prepend .btn,
.input-group-prepend .btn:focus,
.input-group-prepend .btn:active {
	outline: 0 !important;
}
</style>


<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL($formAction->toString())); ?>"
      method="post"
	  class="form form-horizontal <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> validate"
	  name="<?php echo sprintf('%s%s', $layout, ucfirst($formName)); ?>"
	  id="<?php echo sprintf('%s%s',   $layout, ucfirst($formName)); ?>"
	  enctype="multipart/form-data"
	  data-submit=""
	  data-monitor-changes="false"
>
	<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="<?php echo $layout; ?>" />
	<input type="hidden" name="oid"      value="" />
	<input type="hidden" name="return"   value="<?php echo $input->get->getBase64('return'); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<?php // View title and toolbar ?>
	<?php echo LayoutHelper::render(sprintf('toolbar.item.%s', mb_strtolower($layout)), [
			'viewName'   => $view->get('name'),
			'layoutName' => $layout,
			'formName'   => sprintf('%s%s', $layout, ucfirst($formName)),
			'backRoute'  => $return,	// View::getInstance('projects', ['language' => $this->language])->getRoute(),
			'hide'       => ['back'/* ,'cancel' */],
			'user'       => $this->user
		],
		['language' => $this->language]
	); ?>

	<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => null, 'hide' => ['edited','deleted']], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<ul class="nav nav-tabs mt-md-3 mt-lg-4" id="myTab" role="tablist">
		<li class="nav-item" role="presentation">
			<a class="nav-link active" id="masterdata-tab" data-toggle="tab" href="#masterdata" role="tab" aria-controls="masterdata" aria-selected="true"><?php
			echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $this->language); ?></a>
		</li>
		<?php if (FALSE) : ?>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="processes-tab"  data-toggle="tab" href="#processes"  role="tab" aria-controls="processes"  aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_PROCESS_RESPONSIBILITY_TEXT', $this->language); ?></a>
		</li>
		<?php endif; ?>
	</ul>

	<div class="tab-content" id="myTabContent">
		<div class="tab-pane py-4 fade show active" id="masterdata" role="tabpanel" aria-labelledby="masterdata-tab"><?php require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_masterdata.php', $layout); ?></div>
		<?php if (FALSE) : ?>
		<div class="tab-pane py-4 fade"             id="processes"  role="tabpanel" aria-labelledby="processes-tab"><?php  require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_processes.php',  $layout); ?></div>
		<?php endif; ?>
	</div>
</form>
