<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;

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
$pid    = $input->getInt('pid');
?>
<?php /* Access check */
$formData = null;

if (is_a($user, 'Nematrack\Entity\User')) :
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

$this->user          = $user;
$this->formData      = $formData;
$this->organisations = $model->getInstance('organisations', ['language' => $this->language])->getList([
	'filter' => ListModel::FILTER_ALL
]);
$this->lastID = $model->getInstance('processes', ['language' => $this->language])->getLastInsertID();

echo (FTK_DEBUG ? '<pre style="color:crimson">' . print_r(sprintf('Last Article-ID: %d', $this->lastID), true) . '</pre>' : null);

// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$this->staticTechParams = (array) $model->getInstance('techparams', ['language' => $this->language])->getStaticTechnicalParameters();

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

/* the container must be positioned relative:&nbsp;&ast;/

.autocomplete {
	position: relative;
	display: inline-block;
}

.autocomplete-items {
	position: absolute;
	border: 1px solid #d4d4d4;
	border-bottom: none;
	z-index: 99;
	/* position the autocomplete items to be the same width as the container:&nbsp;&ast;/
	top: 100%;
	left: 0;
	right: 0;
	margin-top: -3px;
	margin-left: 5px;
	margin-right: 5px;
}

.autocomplete-items div {
	padding: 0.375rem 0.75rem;
	cursor: pointer;
	background-color: #fff;
	border-bottom: 1px solid #d4d4d4;
}


/* when hovering an item:&nbsp;&ast;/

.autocomplete-items div:hover {
	background-color: #e9e9e9;
}


/* when navigating through the items using the arrow keys:&nbsp;&ast;/

.autocomplete-active {
	background-color: DodgerBlue !important;
	color: #ffffff;
}
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL($formAction->toString())); ?>"
      method="post"
	  class="form form-horizontal <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> validate"
	  name="<?php echo sprintf('%s%s', $layout, ucfirst($formName)); ?>"
	  id="<?php echo sprintf('%s%s',   $layout, ucfirst($formName)); ?>"
	  enctype="multipart/form-data"
	  data-action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&service=provide&what=tparams', $this->language ))); ?>"
	  data-format="json"
	  data-load="technicalParams"
	  data-monitor-changes="false"
	  data-submit=""
	  data-toggle="loadData"
>
	<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="<?php echo $layout; ?>" />
	<input type="hidden" name="pid"      value="" />
	<input type="hidden" name="return"   value="<?php echo $input->get->getBase64('return'); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<?php echo LayoutHelper::render('toolbar.item.add', [
			'viewName'   => $view->get('name'),
			'layoutName' => $layout,
			'formName'   => sprintf('%s%s', $layout, ucfirst($formName)),
			'backRoute'  => $return,	// View::getInstance('projects', ['language' => $this->language])->getRoute(),
			'hide'       => ['back'/* ,'cancel' */],
			'user'       => $user
		],
		['language' => $this->language]
	); ?>

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => null, 'hide' => ['edited','deleted']], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<ul class="nav nav-tabs mt-md-3 mt-lg-4" id="myTab" role="tablist">
		<li class="nav-item" role="presentation">
			<a class="nav-link active" id="masterdata-tab"    data-toggle="tab" href="#masterdata"    role="tab" aria-controls="masterdata"    aria-selected="true"><?php
			echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $this->language); ?></a>
		</li>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="organisations-tab" data-toggle="tab" href="#organisations" role="tab" aria-controls="organisations" aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_PROCESS_RESPONSIBILITY_TEXT', $this->language); ?></a>
		</li>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="tech-params-tab"   data-toggle="tab" href="#tech-params"   role="tab" aria-controls="tech-params"   aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_TECHNICAL_PARAMETERS_TEXT', $this->language); ?></a>
		</li>
	</ul>

	<div class="tab-content" id="myTabContent">
		<div class="tab-pane py-4 fade show active" id="masterdata"    role="tabpanel" aria-labelledby="masterdata-tab"><?php    require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_masterdata.php',    $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="organisations" role="tabpanel" aria-labelledby="organisations-tab"><?php require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_organisations.php', $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="tech-params"   role="tabpanel" aria-labelledby="tech-params-tab"><?php   require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_tech-params.php',   $layout); ?></div>
	</div>
</form>

<?php // Free memory.
unset($this->organisations);
?>
