<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<style>
@media (max-width: 575.98px) {
	.row > .col-form-label {
		margin-left: 15px!important;
		margin-right: 15px!important;
	}
}
</style>

<?php $fieldname = 'process_number'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col-sm-6 col-md-4 col-form-label"><?php echo sprintf('%s&ndash;&nbsp;/ %s',
		Text::translate('COM_FTK_LABEL_PART_TEXT', $this->language),
		Text::translate('COM_FTK_LABEL_PROCESS_NUMBER_TEXT', $this->language)
	); ?>:&nbsp;&ast;</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="<?php echo $fieldname; ?>"
			   value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="1"
			   maxlength="20"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NUMBER_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NUMBER_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="false"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="1"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="20"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'process_name'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col-sm-6 col-md-4 col-form-label"><?php echo sprintf('%s&nbsp;/ %s',
		Text::translate('COM_FTK_LABEL_PROCESS_NAME_TEXT', $this->language),
		Text::translate('COM_FTK_LABEL_OPERATION_DESCRIPTION_TEXT', $this->language)
	); ?>:&nbsp;&ast;</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="<?php echo $fieldname; ?>"
			   value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NAME_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NAME_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="false"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
		   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'process_utility'; ?>
<div class="row form-group ml-sm-0 mb-0">
	<label for="<?php echo $fieldname; ?>"
	       class="col col-form-label col-md-2 col-lg-3 col-xl-4"
	><?php echo implode(', ', [
		Text::translate('COM_FTK_LABEL_MACHINE_TEXT', $this->language),
		Text::translate('COM_FTK_LABEL_DEVICE_TEXT',  $this->language),
		Text::translate('COM_FTK_LABEL_JIG_TEXT',     $this->language),
		Text::translate('COM_FTK_LABEL_TOOL_TEXT',    $this->language)
	]); ?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
			   name="<?php echo $fieldname; ?>"
			   value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_UTILITY_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_UTILITY_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="false"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>
