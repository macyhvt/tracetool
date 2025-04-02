<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LABEL_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col col-6">
				<input type="text"
					   name="name"
					   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
					   class="form-control"
					   minlength="3"
					   maxlength="50"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   autofocus
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_NAME_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="50"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_NAME_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
			<label for="abbreviation" class="col col-form-label col-md-2 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_ABBREVIATION_UNIQUE_SHORT_TEXT', $this->language); ?>:</label>
			<div class="col col-6 col-md-2">
				<input type="text"
					   name="abbreviation"
					   value="<?php echo html_entity_decode($this->item->get('abbreviation')); ?>"
					   class="form-control"
					   minlength="3"
					   maxlength="5"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-toggle="tooltip"
					   data-html="true"
					   data-trigger="focus"
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_UNIQUE_ABBREVIATION_WITH_NO_BLANKS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_UNIQUE_ABBREVIATION_WITH_NO_BLANKS_TEXT', $this->language); ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_PROCESS_ABBREVIATION_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="5"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_PROCESS_ABBREVIATION_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group mb-0">
			<label for="description" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ANNOTATION_TEXT', $this->language); ?>:</label>
			<div class="col">
				<textarea name="description"
						  class="form-control"
						  rows="2"
						  cols="10"
						  maxlength="1000"
						  placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language); ?>"
						  tabindex="<?php echo ++$this->tabindex; ?>"
				><?php $description = html_entity_decode($this->item->get('description')); echo OutputFilter::cleanText($description); ?></textarea>
			</div>
		</div>
	</div>
</div>
