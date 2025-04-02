<?php
// Register required libraries.
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="articles" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ARTICLES_TOTAL_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="articles"
					   value="<?php echo $this->articles; ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group mb-0">
			<label for="parts" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PARTS_TOTAL_TEXT', $this->language); ?>:</label>
			<div class="col col-md-5">
				<input type="text"
					   class="form-control"
					   id="parts"
					   value="<?php echo $this->parts; ?>"
					   readonly
				/>
			</div>
			<label for="faultyparts" class="col col-form-label col-md-1 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_FAULTY_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="faultyparts"
					   value="<?php echo $this->item->get('badParts'); ?>"
					   readonly
				/>
			</div>
		</div>
	</div>
</div>
