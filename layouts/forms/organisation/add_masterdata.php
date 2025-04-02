<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LABEL_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="name"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'name', '', 'STRING'); ?>"
					   class="form-control"
					   minlength="3"
					   maxlength="100"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ORGANISATION_NAME_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ORGANISATION_NAME_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   autofocus
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="100"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="country" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_COUNTRY_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="country"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'country', '', 'STRING'); ?>"
					   class="form-control"
					   minlength="3"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_COUNTRY_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_COUNTRY_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="zip" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ZIP_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="zip"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'zip', '', 'STRING'); ?>"
					   class="form-control"
					   minlength="3"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_ZIP_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_ZIP_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				/>
			</div>
			<label for="city" class="col col-form-label col-md-2 text-lg-right"><?php echo Text::translate('COM_FTK_LABEL_CITY_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="city"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'city', '', 'STRING'); ?>"
					   class="form-control"
					   minlength="3"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_CITY_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_CITY_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="addressline" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ADDRESS_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="addressline"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'addressline', '', 'STRING'); ?>"
					   class="form-control"
					   minlength="3"
					   maxlength="100"
					   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
					   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_LINE_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ADDRESS_LINE_TEXT', $this->language); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
					   required
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
					   data-rule-maxlength="100"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="rid" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ROLE_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<select name="rid"
						class="form-control custom-select"
						<?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ROLE_TEXT', $this->language); ?>"
						aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_ROLE_TEXT', $this->language); ?>"
						required
						tabindex="<?php echo ++$this->tabindex; ?>"
						data-rule-required="true"
						data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
				>
					<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); ?> &ndash;</option>
					<?php foreach ($this->roles as $id => $option) : $option = new Registry($option); ?>
					<?php	echo sprintf('<option value="%d"%s>%s</option>',
						$option->get('roleID'),
						(ArrayHelper::getValue($this->formData, 'rid', '', 'int') ? ' selected' : ''),
						html_entity_decode($option->get('name'))
					); ?>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="row form-group">
			<label for="homepage" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_HOMEPAGE_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   name="homepage"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'homepage', '', 'STRING'); ?>"
					   class="form-control"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_URL_TEXT', $this->language); ?>"
					   tabindex="<?php echo ++$this->tabindex; ?>"
				/>
			</div>
		</div>

        <div class="row form-group">
            <label for="color" class="col col-form-label col-md-2"><?php echo Text::translate('Color', $this->language); ?>:&nbsp;&ast;</label>
            <div class="col">
                <input type="color"
                       name="org_color"
                       class="form-control"
                       value="<?php echo ArrayHelper::getValue($this->formData, 'org_color', '', 'STRING'); ?>"
                       required
                       tabindex="<?php echo ++$this->tabindex; ?>"
                />
            </div>
            <label for="orgAbbr" class="col col-form-label col-md-2 text-lg-right"><?php echo Text::translate('Organisation Abbr.', $this->language); ?>:&nbsp;&ast;</label>
            <div class="col">
                <input type="text"
                       name="org_abbr"
                       class="form-control"
                       value="<?php echo ArrayHelper::getValue($this->formData, 'org_abbr', '', 'STRING'); ?>"
                       minlength="3"
                       maxlength="3"
                       placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
                       required
                       tabindex="<?php echo ++$this->tabindex; ?>"
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
				><?php $description = ArrayHelper::getValue($this->formData, 'description', '', 'STRING'); echo OutputFilter::cleanText($description); ?></textarea>
			</div>
		</div>
	</div>
</div>
