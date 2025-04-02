<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Messager;
use  \Text;
use  \View;

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
$canEdit    = true;
// $canEditArt = (!is_null($proID) && $canEdit);
$canEditArt = true;
// $isEditArt  = $canEditArt;
$isEditArt  = true;
?>
<?php /* Process form data */
if (!empty($_POST)) :
	switch ($input->getWord('button')) :
		case 'submit' :
			$view->saveEdit();
		break;

		case 'submitAndClose' :
			$view->saveAndCloseEdit();
		break;

		default :
			$view->closeEdit();
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing project.
if (!is_a($item, ' \Entity\Project') || (is_a($item, ' \Entity\Project') && is_null($item->get('proID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PROJECT_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $proID)
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('projects', ['language' => $this->language])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->item      = $item;
$this->user      = $user;
$this->lastID    = $model->getInstance('processes', ['language' => $this->language])->getLastInsertID();
$this->isBlocked = $this->item->get('blocked') == '1';
$this->isDeleted = $this->item->get('trashed') == '1';

echo (FTK_DEBUG ? '<pre style="color:crimson">' . print_r(sprintf('Last Article-ID: %d', $this->lastID), true) . '</pre>' : null);

// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl', $this->language);
$formAction->setVar('view', $view->get('name'));
$formAction->setVar('layout', $layout);
$formAction->setVar('proid', (int) $this->item->get('proID'));

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

.form-control:disabled,
.form-control[readonly],
.btn:disabled {
	cursor: not-allowed;
	opacity: .60;
}
.btn:disabled {
	opacity: .40;
}
.form-control[readonly]:not(:disabled) {
	cursor: default;
	opacity: 1;
}

@supports (-webkit-appearance:none) {
	.form-control-file {
		max-width: 132px;
	}
}
</style>

<?php $untranslated = (array) $this->item->get('incomplete.translation'); ?>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL($formAction->toString())); ?>"
      method="post"
	  name="<?php echo ($formName = sprintf('%s%sForm', $layout, ucfirst($view->get('name')))); ?>"
	  class="form form-horizontal projectForm validate"
	  id="<?php echo $formName; ?>"
	  enctype="multipart/form-data"
	  data-submit=""
	  data-monitor-changes="true"
>
	<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="edit" />
	<input type="hidden" name="proid"    value="<?php echo (int) $item->get('proID'); ?>" />
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
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php if (is_countable($this->techParams) && !count($this->techParams)) : ?>
		<?php if ($item->get('incomplete') && is_countable($untranslated = (array) $item->get('incomplete')->get('translation'))) : ?>
			<?php foreach ($untranslated as $property => $this->languageuages) : ?>
				<?php echo LayoutHelper::render('system.alert.notice', [
					'message' => sprintf(Text::translate('COM_FTK_HINT_PLEASE_TRANSLATE_X_INTO_Y_TEXT', $this->language),
						Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $property)), $this->language),
						implode(', ', array_map('mb_strtoupper', array_keys((array) $this->languageuages)))
					),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>

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

<?php // Free memory.
unset($input);
unset($item);
unset($model);
unset($user);
unset($view);
