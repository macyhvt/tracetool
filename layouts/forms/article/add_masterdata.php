<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Text;

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

<?php // Input for article number and drawing index ?>
<div class="row form-group ml-sm-0">
	<label for="number" class="col-sm-6 col-md-4 col-form-label mb-sm-3 mb-md-0"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_NUMBER_TEXT', $this->language); ?>:&nbsp;&ast;</label>
	<div class="col-sm-6 col-md-4 col-xl-5 mb-3 mb-md-0">
		<input type="text"
			   name="number"
			   value="<?php echo ArrayHelper::getValue($this->formData, 'number', '', 'STRING'); ?>"
			   class="form-control"
			   id="ipt-number"
			   minlength="20"
			   maxlength="20"
			   pattern="<?php echo FTKREGEX_DRAWING_NUMBER; ?>"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ARTICLE_NUMBER_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ARTICLE_NUMBER_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ARTICLE_NUMBER_TEXT', $this->language); ?>"
			   required
			   autofocus
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-minlength="20"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_ARTICLE_NUMBER_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="20"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_ARTICLE_NUMBER_TOO_LONG_TEXT', $this->language); ?>"
			   data-rule-pattern="<?php echo FTKREGEX_DRAWING_NUMBER; ?>"
			   data-msg-pattern="<?php echo Text::translate('COM_FTK_HINT_INVALID_NUMBER_FORMAT_TEXT', $this->language); ?>"
		/>
	</div>
	<label for="drawingindex" class="col-sm-6 col-md-2 col-lg-1 col-form-label mb-md-0 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_DRAWING_INDEX_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-2">
		<input type="text"
			   name="drawingindex"
			   value="<?php echo ArrayHelper::getValue($this->formData, 'drawingindex', '', 'CMD'); ?>"
			   class="form-control form-control-drawing-number text-md-right"
			   id="ipt-drawingindex"
			   minlength="1"
			   maxlength="1"
			   pattern="<?php echo FTKREGEX_DRAWING_INDEX; ?>"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_DRAWING_INDEX_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_DRAWING_INDEX_TEXT', $this->language); ?>"
			   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_DRAWING_INDEX_TEXT', $this->language); ?>"
			   <?php // style="margin-left:3px; margin-left:-2px" ?>
			   required
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
			   data-rule-pattern="<?php echo FTKREGEX_DRAWING_INDEX; ?>"
			   data-msg-pattern="<?php echo Text::translate('COM_FTK_HINT_INVALID_CHARACTER_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php // Input for article name used in the ERP system (optional) ?>
<div class="row form-group ml-sm-0">
	<label for="name" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_NAME_ERP_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<?php // TODO - sync change to element properties with dev-version ?>
		<input type="text"
			   name="name"
			   value="<?php echo ArrayHelper::getValue($this->formData, 'name', '', 'STRING'); ?>"
			   class="form-control"
			   id="ipt-name"
			   maxlength="150"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ARTICLE_NAME_ERP_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ARTICLE_NAME_ERP_TEXT', $this->language); ?>"
			   placeholder="<?php echo sprintf('%s (%s)',
					Text::translate('COM_FTK_INPUT_PLACEHOLDER_ARTICLE_NAME_ERP_TEXT', $this->language),
					Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language)); ?>"
			   data-rule-maxlength="150"
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ARTICLE_NAME_ERP_TOO_LONG_TEXT', $this->language); ?>"
			   tabindex="<?php echo ++$this->tabindex; ?>"
		/>
	</div>
</div>

<?php // Input for customer article number (optional) ?>
<div class="row form-group ml-sm-0">
	<label for="custartno" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_ARTICLE_NUMBER_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="custartno"
			   value="<?php echo ArrayHelper::getValue($this->formData, 'custartno', '', 'STRING'); ?>"
			   class="form-control"
			   id="ipt-custartno"
			   minlength="5"
			   maxlength="150"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CUSTOMER_ARTICLE_NUMBER_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CUSTOMER_ARTICLE_NUMBER_TEXT', $this->language); ?>"
			   placeholder="<?php echo sprintf('%s (%s)',
					Text::translate('COM_FTK_INPUT_TITLE_CUSTOMER_ARTICLE_NUMBER_TEXT', $this->language),
					Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language)); ?>"
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   data-rule-minlength="5"
			   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_ARTICLE_NUMBER_TOO_SHORT_TEXT', $this->language); ?>"
			   data-rule-maxlength="150"<?php // TODO - sync change of maxlength with dev-version ?>
			   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_ARTICLE_NUMBER_TOO_LONG_TEXT', $this->language); ?>"
		/>
	</div>
</div>

<?php // Input for customer article name (optional) ?>
<div class="row form-group ml-sm-0">
	<label for="custartname" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_ARTICLE_NAME_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="custartname"
			   value="<?php echo ArrayHelper::getValue($this->formData, 'custartname', '', 'STRING'); ?>"
			   class="form-control"
			   id="ipt-custartname"
			   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CUSTOMER_ARTICLE_NAME_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CUSTOMER_ARTICLE_NAME_TEXT', $this->language); ?>"
			   placeholder="<?php echo sprintf('%s (%s)',
					Text::translate('COM_FTK_INPUT_TITLE_CUSTOMER_ARTICLE_NAME_TEXT', $this->language),
					Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language)); ?>"
			   tabindex="<?php echo ++$this->tabindex; ?>"
		/>
	</div>
</div>

<?php // Input for annotation (optional) ?>
<div class="row form-group ml-sm-0 mb-0">
	<label for="description" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_ANNOTATION_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<textarea name="description"
		          class="form-control pb-3"
		          id="ipt-description"
				  rows="2"
				  cols="10"
				  maxlength="1000"
				  title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ARTICLE_ANNOATION_TEXT', $this->language); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ARTICLE_ANNOATION_TEXT', $this->language); ?>"
				  placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language); ?>"
				  tabindex="<?php echo ++$this->tabindex; ?>"
		><?php $description = ArrayHelper::getValue($this->formData, 'description', '', 'STRING'); echo OutputFilter::cleanText($description); ?></textarea>
	</div>
</div>
