<?php
// Register required libraries.
use  \App;
use  \Helper\UriHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php // Render CUSTOMER-article drawing preview ?>
<div class="row form-group ml-sm-0">
	<label for="drawing-cust"
	       class="col col-form-label col-md-3"
		   style="margin-right:1px"
	><?php echo sprintf('%s %s',
		Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->language),
		Text::translate('COM_FTK_LABEL_CUSTOMER_TEXT', $this->language));
	?>:</label>

	<?php // Thumbnail of drawing or dummy ?>
	<?php // CUSTOMER-article drawing existence check.
	$drawingCUST = $this->item->get('customerDrawing');
	$fileExists  = isset($drawingCUST) ? (true === $drawingCUST->get('thumbExists')) : false;;
	?>
	<div class="col col-md-5 col-lg-6 position-relative"
		 style="overflow-y:hidden; padding-bottom:1px"
	>
		<?php // Hidden file select element.
		// M U S T   come first to get access to the validation message via CSS
		?>		
		<input type="file"
		       multiple="false"
			   name="drawing-cust"
			   class="form-control form-control-input-file form-control-input-file-drawing form-control-input-file-article-drawing h-auto d-none"
			   id="fileArticleDrawing-cust"
			   accept="application/pdf"
			   title="<?php echo Text::translate('COM_FTK_HINT_ARTICLE_NUMBER_IS_REQUIRED_TEXT', $this->language); ?>"
			   <?php // TODO - implement the below mentioned JS-function ?>
			   data-bind="articleDrawingSelected"
			   data-monitor="#drawing-filenumber-cust"
		       data-current-customer-drawing-number="#ipt-custartno"
			   tabindex="<?php echo ++$this->tabindex; ?>"
			   style="padding-top:.2rem; padding-bottom:.15rem; padding-left:0; border:unset; background:inherit"
		/>

		<div class="position-absolute center-block d-block upload-hint" id="drawing-filenumber-cust">
			<button type="button"
			        class="position-absolute border-0 fileSelectToggle outline-0 w-100 h-100 d-block"
					id="fileArticleDrawing-toggle-cust"
			        data-bind="delegateClick"
					data-target="#fileArticleDrawing-cust"
			        style="background:unset; top:0; left:0"
			        tabindex="<?php echo ++$this->tabindex; ?>"
			>
				<span class="d-block w-100 h-100"
					  title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_UPLOAD_DRAWING_TEXT', $this->language); ?>"
					  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_UPLOAD_DRAWING_TEXT', $this->language); ?>"
					  data-toggle="tooltip"
				>
					<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_LABEL_SELECT_TEXT', $this->language); ?></span>
				</span>
			</button>
		</div>

		<?php // Preview ?>
		<img src="<?php echo ($fileExists) ? App::getRouter()->fixRoute($drawingCUST->get('thumb')) : $drawingCUST->get('placeholder'); ?>"
		     class="img-fluid center-block"
			 alt="<?php echo sprintf('%s: %s',
				 Text::translate('COM_FTK_LABEL_IMAGE_TEXT', $this->language),
				 Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->language)
			 ); ?>"
		     width="288"
		     height="204"
		     style="background-color:#fff"
		/>
	</div>
</div>

<?php // Render FRÖTEK-article drawing preview ?>
<div class="row form-group ml-sm-0 mb-0">
	<label for="drawing-ftk"
	       class="col col-form-label col-md-3"
		   style="margin-right:1px"
	><?php echo sprintf('%s %s',
		Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->language),
		'FRÖTEK');
	?>:</label>

	<?php // Thumbnail of drawing or dummy ?>
	<?php // FRÖTEK-article drawing existence check.
	$drawingFTK = $this->item->get('drawing');
	$fileExists = isset($drawingFTK) ? (true === $drawingFTK->get('thumbExists')) : false;
	?>
	<div class="col col-md-5 col-lg-6 position-relative"
		 style="overflow-y:hidden; padding-bottom:1px"
	>
		<?php // Hidden file select element.
		// M U S T   come first to get access to the validation message via CSS
		?>		
		<input type="file"
		       multiple="false"
			   name="drawing-ftk"
			   class="form-control form-control-input-file form-control-input-file-drawing form-control-input-file-article-drawing h-auto d-none"
			   id="fileArticleDrawing-ftk"
			   accept="application/pdf"
			   title="<?php echo Text::translate('COM_FTK_HINT_ARTICLE_NUMBER_IS_REQUIRED_TEXT', $this->language); ?>"
			   <?php // TODO - implement the below mentioned JS-function ?>
			   data-bind="articleDrawingSelected"
			   data-monitor="#drawing-filenumber-ftk"
		       data-current-drawing-number="#ipt-number"
		       data-current-drawing-index="#ipt-drawingindex"

			   tabindex="<?php echo ++$this->tabindex; ?>"
			   style="padding-top:.2rem; padding-bottom:.15rem; padding-left:0; border:unset; background:inherit"
		/>

		<div class="position-absolute center-block d-block upload-hint" id="drawing-filenumber-ftk">
			<button type="button"
			        class="position-absolute border-0 fileSelectToggle outline-0 w-100 h-100 d-block"
					id="fileArticleDrawing-toggle-ftk"
			        data-bind="delegateClick"
					data-target="#fileArticleDrawing-ftk"
			        style="background:unset; top:0; left:0"
			        tabindex="<?php echo ++$this->tabindex; ?>"
			>
				<span class="d-block w-100 h-100"
					  title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_UPLOAD_DRAWING_TEXT', $this->language); ?>"
					  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_UPLOAD_DRAWING_TEXT', $this->language); ?>"
					  data-toggle="tooltip"
				>
					<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_LABEL_SELECT_TEXT', $this->language); ?></span>
				</span>
			</button>
		</div>

		<?php // Preview ?>
		<img src="<?php echo ($fileExists) ? App::getRouter()->fixRoute($drawingFTK->get('thumb')) : $drawingFTK->get('placeholder'); ?>"
		     class="img-fluid center-block"
			 alt="<?php echo sprintf('%s: %s',
				 Text::translate('COM_FTK_LABEL_IMAGE_TEXT', $this->language),
				 Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->language)
			 ); ?>"
		     width="288"
		     height="204"
		     style="background-color:#fff"
		/>
	</div>
</div>
