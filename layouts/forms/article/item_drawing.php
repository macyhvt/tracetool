<?php
// Register required libraries.
use Nematrack\App;
use Nematrack\Text;

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

	<?php // Thumbnail of customer drawing or dummy ?>
	<div class="col position-relative mx-3 text-center"
		 style="overflow-y:hidden; padding-top:2px; padding-bottom:1px; background:#f0f0f0"
	>
		<figure class="figure bg-white m-md-auto">
			<?php if (true === $this->item->get('customerDrawing')->get('fileExists')) : ?>
			<?php 	if (!$this->isBlocked && !$this->isDeleted) : ?>
			<a href="<?php echo App::getRouter()->fixRoute($this->item->get('customerDrawing')->get('file', '')); ?>"
			   class="d-block"
			   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_CUSTOMER_DRAWING_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_CUSTOMER_DRAWING_TEXT', $this->language); ?>"
			   data-toggle="tooltip"
			   target="_blank"
			>
			<?php 	endif; ?>
				<img src="<?php echo App::getRouter()->fixRoute($this->item->get('customerDrawing')->get('thumb', '')); ?>"
					 <?php // FIXME - add attribute value ?>
					 class="bg-white"
					 alt="" 
					 width="" 
					 height=""
				/>
				<!--figcaption class="figure-caption pt-5 h5"></figcaption-->
			<?php 	if (!$this->isBlocked && !$this->isDeleted) : ?>
			</a>
			<?php 	endif; ?>
			<?php else : ?>
				<div class="position-absolute center-block d-block upload-hint">
					<div class="position-absolute border-0 outline-0 w-100 h-100 d-block text-red"><?php echo Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $this->language); ?></div>
				</div>
				<img src="<?php echo App::getRouter()->fixRoute($this->item->get('customerDrawing')->get('placeholder', '')); ?>" alt="" width="" height=""/>
			<?php endif; ?>
		</figure>
	</div>

	<?php // Meta data of customer drawing ?>
	<div class="col col-form-label col-md-5 col-lg-4 mr-3">
		<?php $drawing = $this->item->get('customerDrawing', $this->item->get('placeholder')); ?>
		<dl class="inline-flex small">
			<dt class="mb-md-3"><?php echo Text::translate('COM_FTK_LABEL_FILE_NAME_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php echo $drawing->get('fileName'); ?></dd>
			<dt class="mb-md-3"><?php echo Text::translate('COM_FTK_LABEL_FILE_SIZE_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php echo sprintf('%s %s', $drawing->get('metadata.sizes.KB', 0), Text::translate('COM_FTK_UNIT_KILOBYTES', $this->language)); ?></dd>

			<?php if (FALSE) : ?>
			<dt class="mb-md-3"><?php echo Text::translate('COM_FTK_LABEL_LAST_ACCESS_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php
				echo (true === $this->item->get('customerDrawing')->get('fileExists'))
						? $drawing->get('metadata.access')
						: '';
			?></dd>
			<?php endif; ?>

			<dt class=""><?php echo Text::translate('COM_FTK_LABEL_LAST_MODIFIED_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php
				echo (true === $this->item->get('customerDrawing')->get('fileExists'))
						? $drawing->get('metadata.modified')
						: '';
			?></dd>
		</dl>
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
	<div class="col position-relative mx-3 text-center"
		 style="overflow-y:hidden; padding-top:2px; padding-bottom:1px; background:#f0f0f0"
	>
		<figure class="figure bg-white m-md-auto">
			<?php if (true === $this->item->get('drawing')->get('fileExists')) : ?>
			<?php 	if (!$this->isBlocked && !$this->isDeleted) : ?>
			<a href="<?php echo App::getRouter()->fixRoute($this->item->get('drawing')->get('file', '')); ?>"
			   class="d-block"
			   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_ARTICLE_DRAWING_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_ARTICLE_DRAWING_TEXT', $this->language); ?>"
			   data-toggle="tooltip"
			   target="_blank"
			>
			<?php 	endif; ?>
				<img src="<?php echo App::getRouter()->fixRoute($this->item->get('drawing')->get('thumb', '')); ?>"
					 <?php // FIXME - add attribute value ?>
					 class="bg-white"
					 alt=""
					 width=""
					 height=""
				/>
				<!--figcaption class="figure-caption pt-5 h5"></figcaption-->
			<?php 	if (!$this->isBlocked && !$this->isDeleted) : ?>
			</a>
			<?php 	endif; ?>
			<?php else : ?>
				<div class="position-absolute center-block d-block upload-hint">
					<div class="position-absolute border-0 outline-0 w-100 h-100 d-block text-red"><?php echo Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $this->language); ?></div>
				</div>
				<img src="<?php echo App::getRouter()->fixRoute($this->item->get('drawing')->get('placeholder', '')); ?>"
					 <?php // FIXME - add attribute value ?>
				     alt=""
				     width=""
				     height=""
				     style="margin-top:-1px; background-color:#fff; box-shadow:0 0 1px 1px #ced4da"
				/>
			<?php endif; ?>
		</figure>
	</div>

	<?php // Meta data of drawing ?>
	<div class="col col-form-label col-md-5 col-lg-4 mr-3">
		<?php $drawing = $this->item->get('drawing', $this->item->get('placeholder')); ?>
		<dl class="inline-flex small">
			<dt class="mb-md-3"><?php echo Text::translate('COM_FTK_LABEL_FILE_NAME_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php echo $drawing->get('fileName'); ?></dd>
			<dt class="mb-md-3"><?php echo Text::translate('COM_FTK_LABEL_FILE_SIZE_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php echo sprintf('%s %s', $drawing->get('metadata.sizes.KB', 0), Text::translate('COM_FTK_UNIT_KILOBYTES', $this->language)); ?></dd>

			<?php if (FALSE) : ?>
			<dt class="mb-md-3"><?php echo Text::translate('COM_FTK_LABEL_LAST_ACCESS_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php
				echo (true === $this->item->get('drawing')->get('fileExists'))
						? $drawing->get('metadata.access')
						: '';
			?></dd>
			<?php endif; ?>

			<dt class=""><?php echo Text::translate('COM_FTK_LABEL_LAST_MODIFIED_TEXT', $this->language); ?>:</dt>
			<dd class="text-right"><?php
				echo (true === $this->item->get('drawing')->get('fileExists'))
						? $drawing->get('metadata.modified')
						: '';
			?></dd>
		</dl>
	</div>
</div>
