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
$proID  = $input->getInt('proid');
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

$this->user      = $user;
$this->formData = $formData;

// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl', $this->language);
$formAction->setVar('view', $view->get('name'));
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

.form-control-file {
	line-height: 1rem;
}

@supports (-webkit-appearance:none) {
	.form-control-file {
		max-width: 132px;
	}
}
</style>



<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL($formAction->toString())); ?>"
      method="post"
	  name="<?php echo ($formName = sprintf('%s%sForm', $layout, ucfirst($view->get('name')))); ?>"
	  class="form form-horizontal projectForm validate"
	  id="<?php echo $formName; ?>"
	  enctype="multipart/form-data"
	  data-submit=""
	  data-monitor-changes="false"
>
	<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="add" />
	<input type="hidden" name="proid"    value="" />
	<input type="hidden" name="return"   value="<?php echo $input->get->getBase64('return'); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<?php // View title and toolbar ?>
	<?php echo LayoutHelper::render(sprintf('toolbar.item.%s', mb_strtolower($layout)), [
			'viewName'   => $view->get('name'),
			'layoutName' => $layout,
			'formName'   => $formName,
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
			<a class="nav-link active" id="masterdata-tab"   data-toggle="tab" href="#masterdata"   role="tab" aria-controls="masterdata"   aria-selected="true"><?php
			echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $this->language); ?></a>
		</li>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="surveillance-tab" data-toggle="tab" href="#surveillance" role="tab" aria-controls="surveillance" aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_SURVEILLANCE_TEXT', $this->language); ?></a>
		</li>
	</ul>

	<div class="tab-content" id="myTabContent">
		<div class="tab-pane py-4 fade show active" id="masterdata"   role="tabpanel" aria-labelledby="masterdata-tab"><?php   require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_masterdata.php',   $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="surveillance" role="tabpanel" aria-labelledby="surveillance-tab"><?php require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_surveillance.php', $layout); ?></div>
	</div>
</form>
