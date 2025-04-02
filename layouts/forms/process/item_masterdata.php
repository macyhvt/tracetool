<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LABEL_TEXT', $this->language); ?>:</label>
			<div class="col col-6 col-md-6">
				<input type="text"
					   name="name"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
					   readonly
				/>
			</div>
			<label for="abbreviation" class="col col-form-label col-md-2 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_ABBREVIATION_UNIQUE_SHORT_TEXT', $this->language); ?>:</label>
			<div class="col col-6 col-md-2">
				<input type="text"
					   name="abbreviation"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->item->get('abbreviation')); ?>"
					   readonly
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
						  readonly
				><?php $description = html_entity_decode($this->item->get('description')); echo OutputFilter::cleanText($description); ?></textarea>
			</div>
		</div>
	</div>
</div>
