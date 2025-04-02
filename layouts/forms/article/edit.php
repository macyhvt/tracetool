<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use  \Access\User;
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
$task   = $input->getWord('task');
$aid    = $input->getInt('aid');

?>
<?php /* Access check */
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
// $canEditArt = (!is_null($aid) && $canEdit);
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

// Block the attempt to open a non-existing article.
if (!is_a($item, ' \Entity\Article') || (is_a($item, ' \Entity\Article') && is_null($item->get('artID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_ARTICLE_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $aid)
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('articles', ['language' => $this->language])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->item         = $item;
$this->user         = $user;
$this->processes    = $model->getInstance('processes', ['language' => $this->language])->getList();
$this->artProcesses = $this->item->get('processes', []);
$this->lastID       = $model->getInstance('processes', ['language' => $this->language])->getLastInsertID();

echo (FTK_DEBUG ? '<pre style="color:crimson">' . print_r(sprintf('Last Article-ID: %d', $this->lastID), true) . '</pre>' : null);

// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl',        $this->language);
$formAction->setVar('view',      $view->get('name'));
$formAction->setVar('layout',    $layout);
$formAction->setVar('aid', (int) $this->item->get('artID'));

// Init tabindex
$this->tabindex = 0;
?>

<style>
.btn-banderole {
	color: #fff;
}
.btn-banderole:not(:disabled):not(.disabled):hover,
.btn-banderole:not(:disabled):not(.disabled):focus,
.btn-banderole:not(:disabled):not(.disabled).active {
	background-color: initial;
	border-color: #c3e6cb;
	color: #d4edda;
}

.col.position-relative > .form-control-input-file + .validation-result {
	position: absolute;
	z-index: 1;
	top: 0;	
}

.upload-hint {
	cursor: pointer;
	max-width: 288px;
    width: 100%;
    height: 99%;
	padding: 0;
	border: 1px solid #ced4da;
}
.upload-hint:not(.file-selected) {
	background: inherit;
}
.upload-hint:before {
	background: #fff;
	padding: 25% 30px;
	text-align: center;
	font-size: 120%;
    line-height: 1.5;
	color: #bcbcbc;
	display: none;
    width: 100%;
    height: 100%;
}
.upload-hint#drawing-filenumber-ftk:before {
	<?php if (!is_object($this->drawing)) : ?>
	content: "<?php echo (sprintf('%s', Text::translate('COM_FTK_HINT_DRAWING_UPLOAD_TEXT', $this->language))); ?>";
	<?php endif; ?>
}
.upload-hint#drawing-filenumber-cust:before {
	<?php if (!is_object($this->drawing)) : ?>
	content: "<?php echo (sprintf('%s', Text::translate('COM_FTK_HINT_CUSTOMER_DRAWING_UPLOAD_TEXT', $this->language))); ?>";
	<?php endif; ?>
}
<?php // Fix padding top for customer drawing hint in these languages ?>
html[lang="de"] .upload-hint#drawing-filenumber-cust:before,
html[lang="de"] .upload-hint#drawing-filenumber-ftk:before,
html[lang="fr"] .upload-hint#drawing-filenumber-cust:before,
html[lang="fr"] .upload-hint#drawing-filenumber-ftk:before,
html[lang="hu"] .upload-hint#drawing-filenumber-cust:before,
html[lang="hu"] .upload-hint#drawing-filenumber-ftk:before {
	padding-top: 18%;
}
.upload-hint:hover:before {
	display: block;
}
.upload-hint > button {
	font-size: 120%;
}
.upload-hint:hover > button {
	font-size: 0 !important;
}
.upload-hint.loading:before {
	content: "<?php echo sprintf('%s ...', Text::translate('COM_FTK_HINT_READING_SELECTED_FILE_TEXT', $this->language)); ?>";
	color: #495057;
	display: block;
}
.upload-hint.file-selected > .file-metadata > .file-metadata-item > label {
	min-width: 2.5rem;
}
.upload-hint.file-selected:before {
	content: "";
	color: #495057;
	display: none;
}
<?php // Style when hidden button is pressed ?>
#fileArticleDrawing-toggle:active {
	background: #eee;
	box-shadow: 0 0 30px 0 rgba(128,151,182,0.1) inset
}

<?php if (FALSE) : ?>
.dropzone-hint {
	cursor: pointer;
	max-width: 288px;
    width: 100%;
    height: 99%;
	padding: 0;
	background: inherit;
	border: 0 none;
	box-shadow: 0 0 1px 1px #ced4da;
}
.dropzone-hint:hover {
	box-shadow: none;
}
.dropzone-hint:before {
	background: rgba(255,255,255,1);
	padding: 25% 15px;
	text-align: center;
	font-size: 120%;
    line-height: 1.5;
	color: #bcbcbc;
	content: "<?php echo (sprintf('%s', Text::translate('COM_FTK_HINT_DROPZONE_USAGE_TEXT', $this->language))); ?>";
	display: block;
    width: 100%;
    height: 100%;
}
.dropzone-hint:hover:before {
	color: #bcbcbc;
}

/* Dropzone style overrides */
.dropzone {
	background: unset;
	cursor: pointer;
	max-width: 288px;
    width: 100%;
    height: 99%;
	padding: 0;
	border: 0 none;
}
.dropzone:hover {
	border: 2px dashed rgba(206, 212, 218, 1);
}
.dropzone .dz-default.dz-message .dz-button {
	display: none;
}
.dropzone .dz-details {
	width: 100%;
	height: 100%;
	padding: 10px 15px;
	padding: 6px 15px;
	padding: 15px;
	padding: 40px 20px;
	font-size: 90%;
	text-align: center;
}
.dropzone:hover .dz-details {
	padding: 8px 13px;
	padding: 4px 13px;
	padding: 13px 15px;
	padding: 38px 20px;
}
.dropzone.dz-started {
	background: #fff
}
.dropzone .dz-details.dz-complete.dz-success {
	color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}
.dropzone .dz-details.dz-complete.dz-error {
	color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}
.dropzone .dz-details.dz-error      .dz-success-mark,
.dropzone .dz-details.dz-error      .dz-success-message,
.dropzone .dz-details.dz-success    .dz-error-mark,
.dropzone .dz-details.dz-success    .dz-error-message,
.dropzone .dz-details.dz-processing:not(.dz-complete) .dz-error-mark,
.dropzone .dz-details.dz-processing:not(.dz-complete) .dz-error-message,
.dropzone .dz-details.dz-processing:not(.dz-complete) .dz-success-mark,
.dropzone .dz-details.dz-processing:not(.dz-complete) .dz-success-message {
	display: none;
}
.dropzone .dz-details.dz-complete.dz-error .dz-error-mark {
	display: none;
}
.dropzone .dz-details.dz-processing .dz-progress {
	margin-top: -55px;
	margin-bottom: 60px;
    height: 2px;
    background: #b3cae9;
}
.dropzone .dz-details.dz-processing .dz-progress > .dz-upload {
	display: block;
	background: #30588B;
	height: inherit;
}
.dropzone .dz-details.dz-complete.dz-error .dz-progress > .dz-upload {
	background: #721c24;
}
.dropzone .dz-details.dz-complete.dz-success .dz-progress > .dz-upload {
	background: #155724;
}
.dropzone .dz-details.dz-complete.dz-success .dz-success-mark {
	display: block;
}
.dropzone .dz-error-message,
.dropzone .dz-success-message {
	margin-top: 1.5rem;
}
.dropzone .dz-error-message:before {
	content: "<?php echo mb_strtoupper(sprintf('%s:', Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT', $this->language))); ?>";
	font-weight: bold;
}
.dropzone .dz-error-message > span {
	margin-left: .5rem;
}
.dropzone .dz-error-mark:before {
	content: "<?php echo mb_strtoupper(sprintf('%s:', Text::translate('COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT', $this->language))); ?>";
	font-weight: bold;
}
.dz-details.dz-processing .dz-filename:before {
	content: "<?php echo mb_strtoupper(sprintf('%s:', Text::translate('COM_FTK_LABEL_FILE_TEXT', $this->language))); ?>";
	font-weight: bold;
}
.dz-details.dz-processing .dz-filename {
	overflow: hidden;
}
.dz-details.dz-processing .dz-filename > span {
	margin-left: .5rem;
}
.dz-details.dz-processing .dz-size:before {
	content: "<?php echo mb_strtoupper(sprintf('%s:', Text::translate('COM_FTK_LABEL_SIZE_TEXT', $this->language))); ?>";
	font-weight: bold;
}
.dz-details.dz-processing .dz-size > span,
.dz-details.dz-processing .dz-size > strong {
	margin-left: .5rem;
}
.dz-details.dz-complete.dz-success .dz-progress {
	max-width: 95%;
	width: 92%;
	float: left;
}
.dz-details.dz-complete.dz-success .dz-success-mark {
    margin-top: -66px;
	display: inline-block;
    float: right;
}
.dz-details.dz-complete.dz-success .dz-error-message {
    clear: both
}
<?php endif; ?>

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

.collapse-sm .card {
	border: 0 none !important;
}
.collapse-sm .card-body {
	padding: 0.3rem;
}
/*.collapse-sm.show .card-body {
	background: unset!important;
}*/
.collapse-sm .card-body tbody tr td:first-of-type {
	padding-left: 0!important;
}
.collapse-sm .card-body tbody tr td:last-of-type {
	padding-right: 0!important;
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

.form-control-file {
	line-height: 1rem;
}

#artProcesses > .list-item:last-of-type > .form-row {
	margin-bottom: 0!important;
}
#artProcesses > .list-item:last-of-type > .collapse-sm {
	margin-bottom: 0!important;
}

table.process-measuring-definitions .mpValidity.text-danger {
	color: #dc3545!important;
	box-shadow: none!important;
}

table.process-measuring-definitions > thead:not(.banderolable) > tr > [id$='mpBanderole'] .btn-banderole {
	visibility: hidden
}
.table.process-measuring-definitions > tbody td > input[type],
.table.process-measuring-definitions > tbody td > select {
	font-size: 95%!important;
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
	  class="form form-horizontal <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> validate"
	  name="<?php echo sprintf('%s%s', $layout, ucfirst($formName)); ?>"
	  id="<?php echo sprintf('%s%s',   $layout, ucfirst($formName)); ?>"
	  enctype="multipart/form-data"
	  data-submit=""
	  data-monitor-changes="true"
>
	<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="<?php echo $layout; ?>" />
	<input type="hidden" name="aid"      value="<?php echo (int) $this->item->get('artID'); ?>" />
	<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
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

	<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>

	<hr>

	<div class="status-overlay wrapper position-relative"><?php // required for AJAX loading simulation ?>
		<?php if ($this->user->getFlags() >= User::ROLE_MANAGER) : ?>
			<?php if ($item->get('incomplete') && is_countable($untranslated = (array) $item->get('incomplete')->get('translation'))) : ?>
				<?php foreach ($untranslated as $property => $this->languageuages) : ?>
					<?php // Render untranslated properties hint. ?>
					<?php echo LayoutHelper::render('system.alert.notice', [
						'message' => sprintf(
							Text::translate('COM_FTK_HINT_PLEASE_TRANSLATE_X_INTO_Y_TEXT', $this->language),
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
				<a class="nav-link active" id="masterdata-tab" data-toggle="tab" href="#masterdata" role="tab" aria-controls="masterdata" aria-selected="true"><?php
				echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $this->language); ?></a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link"        id="drawing-tab"    data-toggle="tab" href="#drawing"    role="tab" aria-controls="drawing"    aria-selected="false"><?php
				echo Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->language); ?></a>
			</li>
			<li class="nav-item" role="presentation">
				<a class="nav-link"        id="processes-tab"  data-toggle="tab" href="#processes"  role="tab" aria-controls="processes"  aria-selected="false"><?php
				echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $this->language); ?></a>
			</li>
		</ul>

		<div class="tab-content" id="myTabContent">
			<fieldset class="tab-pane py-4 fade show active"
					  id="masterdata"
					  role="tabpanel"
					  aria-labelledby="masterdata-tab"
			>
				<legend class="sr-only"><?php echo Text::translate('COM_FTK_FIELDSET_LABEL_MASTER_DATA_TEXT', $this->language); ?></legend><?php
				require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_masterdata.php', $layout);
			?></fieldset>
			<fieldset class="tab-pane py-4 fade"
					  id="drawing"
					  role="tabpanel"
					  aria-labelledby="drawing-tab"
			>
				<legend class="sr-only"><?php echo Text::translate('COM_FTK_FIELDSET_LABEL_DRAWING_TEXT', $this->language); ?></legend><?php
				require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_drawing.php',    $layout);
			?></fieldset>
			<fieldset class="tab-pane py-<?php echo (is_countable($this->artProcesses) && $this->artProcesses) ? '4' : '2'; ?> fade"
					  id="processes"
					  role="tabpanel"
					  aria-labelledby="processes-tab"
			>
				<legend class="sr-only"><?php echo Text::translate('COM_FTK_FIELDSET_LABEL_PROCESS_TREE_TEXT', $this->language); ?></legend><?php
				require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_processes.php',  $layout);
			?></fieldset>
		</div>
	</div>
</form>

<?php // Free memory.
unset($input);
unset($item);
unset($model);
unset($user);
unset($view);
