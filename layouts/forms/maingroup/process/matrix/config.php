<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Helper\UserHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
//$return   = $view->getReturnPage();	// Browser back-link required for back-button.
$return   = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$proID    = $input->getInt('proid');
$task     = $input->post->getCmd('task')  ?? ($input->getCmd('task') ?? null);
$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);
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
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		case 'matrixConfigExport' :
			$view->saveMatrixConfig();
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
$this->user   = $user;

$processes    = $model->getInstance('processes', ['language' => $this->language])->getList();
$userProfile  = new Registry(UserHelper::getProfile($this->user));
$matrixConfig = $userProfile->extract('process.matrix');
$matrixConfig = (is_a($matrixConfig, 'Joomla\Registry\Registry') ? $matrixConfig : new Registry);

// Inject the dummy process to provide configuration for process step 000
array_unshift($processes, [
	'name'         => Text::translate('COM_FTK_LABEL_CUSTOMER_SPECIFICATIONS_TEXT', $this->language),
	'abbreviation' => 'pms',
	'blocked'      => '0',
	'trashed'      => '0'
]);
?>

<style>
.cell-style-preview {
	background: #fff;
	color: inherit;
}
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( $return ) ); ?>"
	  method="post"
	  name="editProcessMatrixForm"
	  class="form form-horizontal projectForm validate"
	  id="editProcessMatrixForm"
	  data-monitor-changes="false"
	  data-submit=""
>
	<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="task"     value="matrixConfigExport" />
	<input type="hidden" name="proid"    value="<?php echo (int) $proID; ?>" />
	<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=process.matrix&proid=%d', $this->language, $view->get('name'), $proID ))); // back-link ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?></span>
	</a>
	<h1 class="h3 d-inline-block mr-3"><?php
		echo ucfirst(Text::translate('COM_FTK_LABEL_PROCESS_MATRIX_EXPORT_SETTINGS_EDIT_TEXT', $this->language));
	?></h1>
	<button type="submit"
			form="editProcessMatrixForm"
			name="action"
			value="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
			title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="fas fa-save"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $this->language); ?></span>
	</button>

	<hr>

	<fieldset name="colors">
		<legend class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_COLOURS_TEXT', $this->language); ?></legend>

		<div class="row form-group">
			<div class="col col-11"><?php
				echo LayoutHelper::render('system.alert.info', [
					'message' => Text::translate('COM_FTK_HINT_PROCESS_MATRIX_COLORS_CONFIGURATION_TEXT', $this->language),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			</div>
			<div class="col col-1 text-right" data-toggle="tooltip" title="<?php echo Text::translate('COM_FTK_LINK_TITLE_CLEAR_SETTINGS_TEXT', $this->language); ?>">
				<button type="button"
						class="btn btn-sm btn-secondary btn-trashbin my-2"
						data-toggle="resetCellStyleAll"
						data-target=".btn-resetCellStyle"
				>
					<i class="far fa-trash-alt"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_CLEAR_SETTINGS_TEXT', $this->language); ?></span>
				</button>
			</div>
		</div>

		<?php foreach ($processes as $procID => $process) : ?>
		<?php	$process = new Registry($process); ?>
		<?php	// Skip blocked or trashed processes. ?>
		<?php	if ($process->get('blocked') || $process->get('trashed')) : ?>
		<?php		continue; ?>
		<?php	endif; ?>
		<div class="row form-group">
			<label for="name" class="col col-form-label col-form-label-sm col-md-2"><?php echo html_entity_decode($process->get('name')); ?></label>
			<div class="col">
				<input type="text"
					   name="config[process][matrix][colors][cell][<?php echo html_entity_decode($process->get('abbreviation')); ?>]"
					   value="<?php echo html_entity_decode($matrixConfig->get('colors.cell.' . $process->get('abbreviation'))); ?>"
					   class="form-control form-control-sm bs-colorpicker bs-colorpicker-background cell-style-<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   data-toggle="colorpicker"
					   data-target="#<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   placeholder="<?php echo html_entity_decode($process->get('abbreviation')); ?> &ndash; <?php echo Text::translate('COM_FTK_LABEL_TEXT_COLOUR_TEXT', $this->language); ?>"
				/>
			</div>
			<div class="col">
				<input type="text"
					   name="config[process][matrix][colors][font][<?php echo html_entity_decode($process->get('abbreviation')); ?>]"
					   value="<?php echo html_entity_decode($matrixConfig->get('colors.font.' . $process->get('abbreviation'))); ?>"
					   class="form-control form-control-sm bs-colorpicker bs-colorpicker-font cell-style-<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   data-toggle="colorpicker"
					   data-target="#<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   placeholder="<?php echo html_entity_decode($process->get('abbreviation')); ?> &ndash; <?php echo Text::translate('COM_FTK_LABEL_BACKGROUND_COLOUR_TEXT', $this->language); ?>"
				/>
			</div>
			<div class="col">
					<label class="btn btn-sm btn-block btn-info btn-checkbox my-0 cell-style-<?php echo html_entity_decode($process->get('abbreviation')); ?><?php echo ($matrixConfig->get('font.weight.' . $process->get('abbreviation')) == 'bold' ? ' active' : ''); ?>"
						   role="button"
						   data-toggle="textBolder"
						   data-target="#<?php echo html_entity_decode($process->get('abbreviation')); ?>"
						   data-label-unchecked="<?php echo Text::translate('COM_FTK_CSS_FONT_WEIGHT_BOLD_TEXT', $this->language); ?>"
						   data-label-checked="<?php echo Text::translate('COM_FTK_CSS_FONT_WEIGHT_NORMAL_TEXT', $this->language); ?>"
					>
						<input type="checkbox"
							   name="config[process][matrix][font][weight][<?php echo html_entity_decode($process->get('abbreviation')); ?>]"
							   class="sr-only"
							   value="bold"
							   autocomplete="off"
							   <?php echo ($matrixConfig->get('font.weight.' . $process->get('abbreviation')) == 'bold' ? ' checked' : ''); ?>
						/>
						<span class="label"><?php
							echo Text::translate(strtoupper(sprintf('COM_FTK_CSS_FONT_WEIGHT_%s_TEXT', $matrixConfig->get('font.weight.' . $process->get('abbreviation'), 'normal'))), $this->language);
						?></span>
					</label>
			</div>
			<div class="col">
				<input type="text"
					   class="form-control form-control-sm cell-style-preview cell-style-<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   id="<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   value="<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   readonly
					   style="background:<?php
						echo $matrixConfig->get('colors.cell.' . $process->get('abbreviation'), '#FFFFFF'); ?>;color:<?php
						echo $matrixConfig->get('colors.font.' . $process->get('abbreviation'), '#000000'); ?>;font-weight:<?php
						echo $matrixConfig->get('font.weight.' . $process->get('abbreviation'), 'normal'); ?>"
				/>
			</div>
			<div class="col col-1 text-right" data-toggle="tooltip" title="<?php echo Text::translate('COM_FTK_LINK_TITLE_CLEAR_SETTING_THIS_TEXT', $this->language); ?>">
				<button type="button"
						class="btn btn-sm btn-link btn-trashbin btn-resetCellStyle"
						data-toggle="resetCellStyle"
						data-target=".cell-style-<?php echo html_entity_decode($process->get('abbreviation')); ?>"
				>
					<i class="far fa-trash-alt"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_CLEAR_SETTING_TEXT', $this->language); ?></span>
				</button>
			</div>
		</div>
		<?php endforeach; ?>
	</fieldset>
</form>

<?php // Free memory
unset($matrixConfig);
unset($processes);
unset($userProfile);
