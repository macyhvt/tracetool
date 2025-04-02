<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="number" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_NUMBER_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col col-md-3">
				<input type="text"
					   name="number"
					   value="<?php echo html_entity_decode($this->item->get('number')); ?>"
					   class="form-control"
					   minlength="3"
					   maxlength="3"
					   pattern="<?php echo FTKREGEX_PROJECT_NUMBER; ?>"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROJECT_NUMBER_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_PROJECT_NUMBER_TEXT', $this->language); ?>"
					   required
					   autofocus
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_NUMBER_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="3"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_NUMBER_TOO_LONG_TEXT', $this->language); ?>"
					   data-rule-pattern="<?php echo FTKREGEX_PROJECT_NUMBER; ?>"
					   data-msg-pattern="<?php echo Text::translate('COM_FTK_HINT_INVALID_PROJECT_NUMBER_TEXT', $this->language); ?>"
				/>
			</div>
			<label for="status" class="col col-form-label col-md-2 text-lg-right"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<select name="status"
						class="form-control custom-select selectProjectStatus"
						required
						data-rule-required="true"
						data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						tabindex="<?php echo ++$this->tabindex; ?>"
				>
					<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); ?> &ndash;</option>
					<?php foreach ($this->__get('view')->get('statusTypes') as $abbr => $type) : ?>
					<?php	echo sprintf('<option value="%s"%s>%s</option>',
						$abbr,
						($abbr === $this->item->get('status') ? ' selected' : ''),
						html_entity_decode($type->name)
					); ?>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="name"
					   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
					   class="form-control"
					   minlength="3"
					   maxlength="50"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROJECT_NAME_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_PROJECT_NAME_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_NAME_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="50"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_NAME_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="customer" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="customer"
					   value="<?php echo html_entity_decode($this->item->get('customer')); ?>"
					   class="form-control"
					   minlength="1"
					   maxlength="100"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROJECT_CUSTOMER_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_PROJECT_CUSTOMER_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="1"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_CUSTOMER_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="100"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_CUSTOMER_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group mb-0">
			<label for="description" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ANNOTATION_TEXT', $this->language); ?>:</label>
			<div class="col">
				<textarea name="description"
				          class="form-control"
						  rows="3"
						  cols="10"
						  maxlength="1000"
						  placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language); ?>"
						  tabindex="<?php echo ++$this->tabindex; ?>"
				><?php $description = html_entity_decode($this->item->get('description')); echo OutputFilter::cleanText($description); ?></textarea>
			</div>
		</div>
	</div>
</div>
