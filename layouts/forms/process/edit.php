<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use  \Access\User;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Messager;
use  \Model\Lizt as ListModel;
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
$pid    = $input->getInt('pid');
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
$canEdit     = true;
// $canEditProc = (!is_null($pid) && $canEdit);
$canEditProc = true;
// $isEditProc  = $canEditProc;
$isEditProc  = true;
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

// Block the attempt to open a non-existing process.
if (!is_a($item, ' \Entity\Process') || (is_a($item, ' \Entity\Process') && is_null($item->get('procID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PROCESS_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $pid)
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('processes', ['language' => $this->language])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->item              = $item;
$this->user              = $user;
$this->isBlocked         = $this->item->get('blocked') == '1';
$this->isDeleted         = $this->item->get('trashed') == '1';
$this->techParams        = $this->item->__get('techParams', []);	// This gets the stripped-down techParams (without static techParams)
$this->organisations     = $model->getInstance('organisations', ['language' => $this->language])->getList([
	'filter' => ListModel::FILTER_ALL
]);
$this->itemOrganisations = (array) $this->item->get('organisations');
$this->lastID    = $model->getInstance('processes', ['language' => $this->language])->getLastInsertID();

echo (FTK_DEBUG ? '<pre style="color:crimson">' . print_r(sprintf('Last Article-ID: %d', $this->lastID), true) . '</pre>' : null);

// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$this->staticTechParams  = (array) $model->getInstance('techparams', ['language' => $this->language])->getStaticTechnicalParameters();

// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl',        $this->language);
$formAction->setVar('view',      $view->get('name'));
$formAction->setVar('layout',    $layout);
$formAction->setVar('pid', (int) $this->item->get('procID'));

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

.form-control:disabled, .form-control[readonly] {
	cursor: not-allowed;
}
</style>

<?php $untranslated = (array) $this->item->get('incomplete.translation'); ?>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL($formAction->toString())); ?>"
      method="post"
	  class="form form-horizontal <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> validate"
	  name="<?php echo sprintf('%s%s', $layout, ucfirst($formName)); ?>"
	  id="<?php echo sprintf('%s%s',   $layout, ucfirst($formName)); ?>"

	  data-submit=""
	  data-monitor-changes="true"
	  data-action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&service=provide&what=tparams', $this->language ))); ?>"
	  data-format="json"
	  data-load="technicalParams"
	  data-toggle="loadData"
>
	<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="<?php echo $layout; ?>" />
	<input type="hidden" name="pid"      value="<?php echo (int) $this->item->get('procID'); ?>" />
	<input type="hidden" name="return"   value="<?php echo $input->get->getBase64('return'); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<?php // View title and toolbar ?>
	<?php echo LayoutHelper::render(sprintf('toolbar.item.%s', mb_strtolower($layout)), [
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
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php if ($user->getFlags() >= User::ROLE_MANAGER) : ?>
		<?php if ($this->item->get('incomplete') && is_countable($untranslated = (array) $this->item->get('incomplete')->get('translation'))) : ?>
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
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="config-tab"        data-toggle="tab" href="#config"        role="tab" aria-controls="config"        aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_CONFIGURATION_TEXT', $this->language); ?></a>
		</li>
	</ul>

	<div class="tab-content" id="myTabContent">
		<div class="tab-pane py-4 fade show active" id="masterdata"    role="tabpanel" aria-labelledby="masterdata-tab"><?php    require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_masterdata.php',    $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="organisations" role="tabpanel" aria-labelledby="organisations-tab"><?php require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_organisations.php', $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="tech-params"   role="tabpanel" aria-labelledby="tech-params-tab"><?php   require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_tech-params.php',   $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="config"        role="tabpanel" aria-labelledby="config-tab"><?php        require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_config.php',        $layout); ?></div>
	</div>
</form>

<?php // Free memory.
unset($input);
unset($item);
unset($model);
unset($organisations);
unset($techParams);
unset($user);
unset($view);
