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

<?php $fieldname = 'product_process_specification_tolerance'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
		echo sprintf('%s / %s , %s / %s',
			Text::translate('COM_FTK_LABEL_PRODUCT_TEXT', $this->language),
			Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language),
			Text::translate('COM_FTK_LABEL_SPECIFICATION_TEXT', $this->language),
			Text::translate('COM_FTK_LABEL_TOLERANCE_TEXT', $this->language)
		);
	?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
		       name="<?php echo $fieldname; ?>"
		       value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PRODUCT_PROCESS_SPECIFICATION_TOLERANCE_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PRODUCT_PROCESS_SPECIFICATION_TOLERANCE_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'evaluation_measurement_technique'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
		echo sprintf('%s / %s',
			Text::translate('COM_FTK_LABEL_EVALUATION_TEXT', $this->language),
			Text::translate('COM_FTK_LABEL_MEASUREMENT_TECHNIQUE_TEXT', $this->language)
		);
	?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
		       name="<?php echo $fieldname; ?>"
		       value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_EVALUATION_MEASUREMENT_TECHNIQUE_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_EVALUATION_MEASUREMENT_TECHNIQUE_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'size'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
		echo Text::translate('COM_FTK_LABEL_SIZE_TEXT', $this->language);
	?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
			   name="<?php echo $fieldname; ?>"
			   value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="1"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SIZE_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SIZE_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="1"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'frequency'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
		echo Text::translate('COM_FTK_LABEL_FREQUENCY_TEXT', $this->language);
	?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
			   name="<?php echo $fieldname; ?>"
			   value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_FREQUENCY_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_FREQUENCY_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'control_method'; ?>
<div class="row form-group ml-sm-0">
	<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
		echo Text::translate('COM_FTK_LABEL_CONTROL_METHOD_TEXT', $this->language);
	?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
		       name="<?php echo $fieldname; ?>"
		       value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CONTROL_METHOD_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CONTROL_METHOD_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php $fieldname = 'reaction_plan'; ?>
<div class="row form-group ml-sm-0 mb-0">
	<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
		echo Text::translate('COM_FTK_LABEL_REACTION_PLAN_TEXT', $this->language);
	?>:&nbsp;&ast;</label>
	<div class="col">
		<input type="text"
			   name="<?php echo $fieldname; ?>"
			   value="<?php echo ArrayHelper::getValue($this->formData, $fieldname, '', 'STRING'); ?>"
			   class="form-control"
			   id="<?php echo sprintf('ipt-%s', $fieldname); ?>"
			   minlength="3"
			   maxlength="50"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_REACTION_PLAN_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_REACTION_PLAN_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="3"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="50"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>
