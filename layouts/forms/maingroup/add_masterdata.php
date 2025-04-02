<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Utilities\ArrayHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="number" class="col col-form-label col-md-2"><?php echo "Main group";//echo Text::translate('COM_FTK_LABEL_PROJECT_NUMBER_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col col-md-3">
				<input type="text"
					   name="group_name"
					   value="<?php //echo ArrayHelper::getValue($this->formData, 'number', '', 'CMD'); ?>"
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

            <label for="parts" class="col col-form-label col-md-2"><?php echo "Sub-group";/*echo sprintf('%s %s',
				Text::translate('COM_FTK_LABEL_PARTS_TEXT',         $this->language),
				Text::translate('COM_FTK_LABEL_PARTS_ORDERED_TEXT', $this->language));*/
                ?>:</label>
            <div class="col">
                <input type="text"
                       name="number"
                       value="<?php //echo ArrayHelper::getValue($this->formData, 'number', '', 'CMD'); ?>"
                       class="form-control"
                       minlength="3"
                       maxlength="3"
                       pattern="<?php echo FTKREGEX_PROJECT_NUMBER; ?>"
                    <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
                       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROJECT_NUMBER_TEXT', $this->language); ?>"
                       placeholder="<?php echo '3 characters';//Text::translate('COM_FTK_INPUT_PLACEHOLDER_PROJECT_NUMBER_TEXT', $this->language); ?>"
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
		</div>

		<div class="row form-group mb-0">
			<label for="description" class="col col-form-label col-md-2"><?php echo "Explaination";//echo Text::translate('COM_FTK_LABEL_ANNOTATION_TEXT', $this->language); ?>:</label>
			<div class="col">
				<textarea name="explaination"
				          class="form-control"
						  rows="3"
						  cols="10"
						  maxlength="1000"
						  placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language); ?>"
						  tabindex="<?php echo ++$this->tabindex; ?>"
				><?php $description = ArrayHelper::getValue($this->formData, 'description', '', 'STRING'); echo OutputFilter::cleanText($description); ?></textarea>
			</div>
		</div>
	</div>
</div>
