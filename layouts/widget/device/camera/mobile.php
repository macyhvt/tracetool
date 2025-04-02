<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$data   = $this->data;
$view   = ArrayHelper::getValue($data, 'view'); // The Joomla\Registry object does not preserve other class instances. Hence, we extract it prior loading $data into a Registry.
$input  = App::getInput();
$return = $input->server->getUrl('REQUEST_URI');

$layout = $input->getCmd('layout');
$task   = $input->post->getCmd('task',    $input->getWord('task'));
$format = $input->post->getWord('format', $input->getWord('format'));
?>
<?php /* Assign refs. */
$this->view  = $view;
$this->input = $input;
$this->user  = $this->view->get('user');
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		// Move previously uploaded picture from temp directory to the part's media files directory.
		case 'handle' :
			$this->view->handleUploadedPicture($input->server->getUrl('REQUEST_URI'));
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
$data = new Registry($this->data);

//$formName   = sprintf('%sForm', mb_strtolower($view->get('name')));
$formName   = 'pictureUploadForm';
$formAction = $input->server->getUrl('REQUEST_URI');
$formAction = UriHelper::osSafe(UriHelper::fixURL($formAction));
$return     = base64_encode($return);

$ajaxUrl    = base64_encode((new Joomla\Uri\Uri('index.php?hl=de&service=provide&what=image.stream&format=json'))->toString());

$deviceClass     = $this->view->get('browser')->get('ismobiledevice') ? 'mobile' : 'desktop';
$labelVisibility = $this->view->get('browser')->get('ismobiledevice') ? 'none' : 'flex';
$inputTitle      = Text::translate(($this->view->get('browser')->get('ismobiledevice') ? 'Hier klicken, um ein Foto aufzunehmen' : 'Hier klicken, um ein Foto auszuwählen'), $this->language);
$buttonTakeText  = Text::translate(($this->view->get('browser')->get('ismobiledevice') ? 'COM_FTK_BUTTON_TEXT_TAKE_TEXT' : 'COM_FTK_BUTTON_TEXT_SELECT_TEXT'), $this->language);
$buttonSendText  = Text::translate('COM_FTK_BUTTON_TEXT_UPLOAD_TEXT', $this->language);

// Init tabindex
$tabindex = 0;
?>

<?php /* CSS */
$CSS = <<<STYLES
.col-form-label,
.nav-tabs .nav-link {
	background-color: #e8edf3;
	border-color: #dee3e9 #dee3e9 #dee2e6;
	color: #30588B;
}

form.submitted > div:not(.isPreview) {
	padding-bottom: 15rem;
}

#upload-wrapper.desktop .custom-file-preposition {
	display: none;
}
#upload-wrapper.desktop .custom-file-input {
	display: none!important;
}
#upload-wrapper.desktop .custom-file-label::before,
#upload-wrapper.desktop .custom-file-label::after {
	content: unset;
}
#upload-wrapper.desktop .custom-file-label {
	/* Reset TWBS-style */
	position: unset;
	top: unset;
	left: unset;
	right: unset;
	bottom: unset;
	z-index: unset;
	margin: 0;
	padding: unset;
	font-weight: unset;
	line-height: unset;
	color: unset;
	background-color: unset;
	width: 100%;
	height: 100%;
	border-top-left-radius: 0.25rem!important;
	border-top-right-radius: 0.25rem!important;
	border-bottom-left-radius: 0.25rem!important;
	border-bottom-right-radius: 0.25rem!important;
    /* btn-outline-info */
    color: #17a2b8;
    border-color: #17a2b8;
}

form.submitted #input-group-select-file,
#upload-wrapper.isPreview #input-group-select-file {
	display: none;
}

#upload-wrapper.isPreview .img-thumbnail {
	border-radius: 0;
	padding: 0;
}
STYLES;

if (class_exists('WyriHaximus\CssCompress\Factory')) :
	$compressor = \WyriHaximus\CssCompress\Factory::constructSmallest();
	$CSS = $compressor->compress($CSS);
endif;
?>
<style><?php echo $CSS; ?></style>

<?php /* CAMERA/VIDEO device list */
$HTML = <<<HTML
<form action="$formAction"
      method="post"
      class="form form-horizontal $formName validate"
      name="$formName"
      id="$formName"
	  enctype="multipart/form-data"
	  data-monitor-changes="false"
	  data-submit=""
>
	<input type="hidden" name="hl"     value="$this->language" />
	<input type="hidden" name="view"   value="{$view->get('name')}" />
	<input type="hidden" name="layout" value="{$view->get('layout')}" />
	<input type="hidden" name="task"   value="{$input->get('taskName','upload')}" />
	<input type="hidden" name="id"     value="{$data->get('partID')}" />
	<input type="hidden" name="pid"    value="{$data->get('procID')}" />
	<input type="hidden" name="format" value="" />
	<input type="hidden" name="return" value="$return" />

	<div class="status-overlay wrapper position-relative $deviceClass" id="upload-wrapper"><?php // required for AJAX loading simulation ?>
		<fieldset class="input-group" id="input-group-select-file">
			<div class="w-100">
				<input type="file"
				       name="picture"
				       class="custom-file-input file-input-image previewable autosubmit"
				       id="ipt-file"
				       aria-describedby="lbl-file"
				       accept="image/*"
				       capture="camera"
				       title="$inputTitle"
				       data-toggle="tooltip"
				       tabindex="$tabindex"
				       required
				       data-preview-endpoint="$ajaxUrl"
				/>
				<label for="ipt-file" class="border-0 btn btn-outline-info border-0 m-0 mx-auto p-0 w-100">
					<i class="fas fa-camera fa-10x"></i>
				</label>
			</div>
		</fieldset>
	</div>
</form>
HTML;
$HTML = preg_replace(
	[
		'/%STR1%/'
	],
	[
		Text::translate('COM_FTK_LABEL_PATH_TEXT', $lang)
	],
	$HTML
);

if (class_exists('WyriHaximus\HtmlCompress\Factory')) :
	$compressor = \WyriHaximus\HtmlCompress\Factory::constructSmallest();
	$HTML = $compressor->compress($HTML);
endif;

echo $HTML;
?>

<?php /* Javascript (code is spread over several event handlers in script.js */
$JS = <<<JS

JS;


// Do not compress if the source file is the *.min.js file.
if (class_exists('WyriHaximus\JsCompress\Factory')) :
	$compressor = \WyriHaximus\JsCompress\Factory::construct();
	$JS = $compressor->compress($JS);
endif;
?>
<script><?php echo $JS; ?></script>
