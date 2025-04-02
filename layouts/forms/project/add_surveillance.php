<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group mb-0">
			<label for="parts" class="col col-form-label col-md-2"><?php echo sprintf('%s %s',
				Text::translate('COM_FTK_LABEL_PARTS_TEXT',         $this->language),
				Text::translate('COM_FTK_LABEL_PARTS_ORDERED_TEXT', $this->language));
			?>:</label>
			<div class="col">
				<input type="number"
					   name="order"
					   value="<?php echo ArrayHelper::getValue($this->formData, 'order', '0', 'INT'); ?>"
					   class="form-control"
					   id="ipt-parts-order"
					   min="0"
					   max="9999"
					   step="1"
					   placeholder="0"
					   tabindex="<?php echo ++$this->tabindex; ?>"
					   data-rule-min="0"
					   data-msg-min="<?php echo sprintf(Text::translate('COM_FTK_HINT_NUMBER_MUST_BE_GREATER_THAN_X_TEXT', $this->language),  '0'); ?>"
					   data-rule-max="9999"
					   data-msg-max="<?php echo sprintf(Text::translate('COM_FTK_HINT_NUMBER_MUST_BE_LOWER_THAN_X_TEXT', $this->language), '9999'); ?>"
				/>
			</div>
		</div>
	</div>
</div>
