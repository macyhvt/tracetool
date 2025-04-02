<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
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

<?php // Input for article number and drawing index ?>
<div class="row form-group ml-sm-0">
	<label for="number" class="col-sm-6 col-md-4 col-form-label mb-sm-3 mb-md-0"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_NUMBER_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-4 col-xl-5 mb-3 mb-md-0">
		<input type="text"
			   name="number"
			   class="form-control"
			   value="<?php echo html_entity_decode($this->item->get('number')); ?>"
			   readonly
		/>
	</div>
	<label for="drawingindex" class="col-sm-6 col-md-2 col-form-label mb-md-0 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_DRAWING_INDEX_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-2 col-xl-1">
		<input type="text"
			   name="drawingindex"
			   class="form-control text-md-right"
			   value="<?php echo html_entity_decode($this->item->get('drawingindex')); ?>"
			   <?php // style="margin-left:-5px; margin-left:3px" ?>
			   readonly
		/>
	</div>
</div>

<?php // Input for article name used in the ERP system (optional) ?>
<div class="row form-group ml-sm-0">
	<label for="name" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_NAME_ERP_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="name"
			   class="form-control"
			   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
			   readonly
		/>
	</div>
</div>

<?php // Input for customer article number (optional) ?>
<div class="row form-group ml-sm-0">
	<label for="custartno" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_ARTICLE_NUMBER_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="custartno"
			   class="form-control"
			   value="<?php echo html_entity_decode($this->item->get('custartno')); ?>"
			   readonly
		/>
	</div>
</div>

<?php // Input for customer article name (optional) ?>
<div class="row form-group ml-sm-0">
	<label for="custartname" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_ARTICLE_NAME_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<input type="text"
			   name="custartname"
			   class="form-control"
			   value="<?php echo html_entity_decode($this->item->get('custartname')); ?>"
			   readonly
		/>
	</div>
</div>

<?php // Input for annotation (optional) ?>
<div class="row form-group ml-sm-0 mb-0">
	<label for="description" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_ANNOTATION_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<textarea name="description"
		          class="form-control pb-3"
				  rows="2"
				  cols="10"
				  readonly
		><?php $description = html_entity_decode($this->item->get('description')); echo OutputFilter::cleanText($description); ?></textarea>
	</div>
</div>
