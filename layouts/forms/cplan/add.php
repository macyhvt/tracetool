<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view   = $this->__get('view');
$layout = $view->get('input')->getCmd('layout');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$idKey  = $view->getIdentificationKey();
$idVal  = $view->get('input')->getInt($idKey);

// TODO - Implement ACL and make calculate editor-right from ACL
$canAdd = true;
?>
<?php /* Assign refs. */
$this->view     = $view;
$this->formData = $this->view->get('formData');
$this->item     = $this->view->get('item');
$this->user     = $this->view->get('user');
?>
<?php /* Process form data */
if (!empty($_POST)) :
	$this->view->saveAdd();
endif;
?>
<?php /* Prepare view data */
// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl',     $this->language);
$formAction->setVar('view',   mb_strtolower($this->view->get('name')));
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



<article>
	<header></header>

	<form action="<?php echo UriHelper::osSafe(UriHelper::fixURL($formAction->toString())); ?>"
		  method="post"
		  class="form form-horizontal <?php echo $this->view->get('formName'); ?> validate"
		  name="<?php echo $this->view->get('formName'); ?>"
		  id="<?php echo $this->view->get('formName'); ?>"

		  data-submit=""
		  data-monitor-changes="false"
	>
		<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
		<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
		<input type="hidden" name="lngID"    value="<?php echo (new Registry($this->view->get('model')->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
		<input type="hidden" name="task"     value="<?php echo $this->view->get('taskName', $this->view->get('layoutName')); ?>" />
		<input type="hidden" name="<?php echo $idKey; ?>" value="" />
		<input type="hidden" name="return"   value="<?php echo $this->view->get('input')->getBase64('return', $return); ?>" /><?php // previously it was base64_encode($return) ?>
		<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

		<?php // View title and toolbar ?>
		<?php echo LayoutHelper::render(sprintf('toolbar.item.%s', $this->view->get('layoutName')), [
				'viewName'   => mb_strtolower($this->view->get('name')),
				'layoutName' => $this->view->get('layoutName'),
				'taskName'   => $this->view->get('taskName'),
				'formName'   => $this->view->get('formName'),
				'heading'    => mb_strtoupper(sprintf('COM_FTK_HEADING_%s_%s_TEXT', $this->view->get('name'), $this->view->get('layoutName'))),
				'backRoute'  => $return,
				'hide'       => ['back'],
				'user'       => $this->user
			],
			['language' => $this->language]
		); ?>

		<?php if (FALSE && !$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
			<?php echo LayoutHelper::render('system.element.metadata', ['item' => null, 'hide' => ['edited','deleted']], ['language' => $this->language]); ?>
		<?php endif; ?>

		<hr>

		<ul class="nav nav-tabs mt-md-3 mt-lg-4" id="myTab" role="tablist">
			<li class="nav-item" role="presentation">
				<a class="nav-link active" id="masterdata-tab" data-toggle="tab" href="#masterdata" role="tab" aria-controls="masterdata" aria-selected="true"><?php
				echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $this->language); ?></a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="characteristics-tab" data-toggle="tab" href="#characteristics" role="tab" aria-controls="characteristics" aria-selected="true"><?php
				echo Text::translate('COM_FTK_LABEL_CHARACTERISTICS_TEXT', $this->language); ?></a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link" id="methods-tab" data-toggle="tab" href="#methods" role="tab" aria-controls="methods" aria-selected="true"><?php
				echo Text::translate('COM_FTK_LABEL_METHODS_TEXT', $this->language); ?></a>
			</li>
		</ul>

		<div class="tab-content position-relative" id="myTabContent">
			<fieldset class="tab-pane py-4 fade show active"
					  id="masterdata"
					  role="tabpanel"
					  aria-labelledby="masterdata-tab"
			>
				<legend class="sr-only"><?php echo Text::translate('COM_FTK_FIELDSET_LABEL_MASTER_DATA_TEXT', $this->language); ?></legend><?php
				require_once implode(DIRECTORY_SEPARATOR, [ __DIR__, $this->view->get('layoutName'), 'masterdata.php' ]);
			?></fieldset>
			<fieldset class="tab-pane py-4 fade"
					  id="characteristics"
					  role="tabpanel"
					  aria-labelledby="characteristics-tab"
			>
				<legend class="sr-only"><?php echo Text::translate('COM_FTK_FIELDSET_LABEL_CHARACTERISTICS_TEXT', $this->language); ?></legend><?php
				require_once implode(DIRECTORY_SEPARATOR, [ __DIR__, $this->view->get('layoutName'), 'characteristics.php' ]);
			?></fieldset>
			<fieldset class="tab-pane py-4 fade"
					  id="methods"
					  role="tabpanel"
					  aria-labelledby="methods-tab"
			>
				<legend class="sr-only"><?php echo Text::translate('COM_FTK_FIELDSET_LABEL_METHODS_TEXT', $this->language); ?></legend><?php
				require_once implode(DIRECTORY_SEPARATOR, [ __DIR__, $this->view->get('layoutName'), 'methods.php' ]);
			?></fieldset>
		</div>
	</form>
</article>
