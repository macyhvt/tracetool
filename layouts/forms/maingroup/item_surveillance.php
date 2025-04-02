<?php
// Register required libraries.
use Nematrack\Access\User;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<?php if (FALSE) : ?>
		<?php // Dropdown for project state ?>
		<div class="row form-group filter" id="filter-category">
			<label for="status" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PROJECT_STATUS_TEXT', $this->language); ?>:</label>
			<div class="col input-group">
				<input type="text"
					   class="form-control"
					   id="status"
					   value="<?php echo html_entity_decode($this->item->get('status')); ?>"
					   readonly
				/>
				<?php if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
				<div class="input-group-append">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=config.types&proid=%d', $this->language, $view->get('name'), $this->item->get('proID') ))); ?>"
					   role="button"
					   class="btn btn-outline-info"
					   id="link-to-website"
					   data-bind="windowOpen"
					   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=config.types&proid=%d&return=%s', $this->language, $view->get('name'), $this->item->get('proID'), base64_encode($return) ))); ?>"
					   data-location-target="_self"
					>
						<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_CONFIGURE_PROJECT_STATUS_TYPES_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_CONFIGURE_PROJECT_STATUS_TYPES_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						>
							<i class="fas fa-sliders-h"></i>
							<span class="btn-text d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_CONFIGURE_TEXT', $this->language); ?></span>
						</span>
					</a>
				</div>
				<?php endif; //-> END: !isArchived && !isBlocked && !isDeleted ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="row form-group">
			<label for="articles" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ARTICLES_TOTAL_TEXT', $this->language); ?>:</label>
			<div class="col input-group">
				<input type="text"
				       class="form-control"
				       id="articles"
				       value="<?php echo $this->articles; ?>"
				       aria-describedby="link-to-certificates"
				       readonly
				/>
				<?php if ($this->user->getFlags() >= User::ROLE_MANAGER && !$this->isArchived && !$this->isBlocked && !$this->isDeleted) : // Certificate list is provided to privileged users only ?>
				<div class="input-group-append">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=certificates&proid=%d',
						$this->language,
						$this->__get('view')->get('name'),
						$this->item->get('proID') )));
					   ?>"
					   role="button"
					   class="btn btn-outline-info"
					   id="link-to-website"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_CREATE_CERTIFICATE_LIST_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_CREATE_CERTIFICATE_LIST_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
					   rel="noopener noreferrer"
					>
						<i class="fas fa-file-csv"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_VISIT_HOMEPAGE_TEXT', $this->language); ?></span>
					</a>
				</div>
				<?php endif; //-> END: !isArchived && !isBlocked && !isDeleted ?>
			</div>
		</div>
		<div class="row form-group mb-0">
			<label for="parts" class="col col-form-label col-md-2"><?php echo sprintf('%s %s',
				Text::translate('COM_FTK_LABEL_PARTS_TEXT',         $this->language),
				Text::translate('COM_FTK_LABEL_PARTS_ORDERED_TEXT', $this->language));
			?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="ipt-parts-order"
					   value="<?php echo (int) $this->item->get('order'); ?>"
					   readonly
				/>
			</div>
			<label for="faultyparts" class="col col-form-label col-md-2 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_PARTS_PRODUCED_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="ipt-parts-produced"
					   value="<?php echo (int) $this->parts; ?>"
					   readonly
				/>
			</div>
			<label for="faultyparts" class="col col-form-label col-md-2 text-md-right"><?php echo Text::translate('COM_FTK_LABEL_PARTS_TRASHED_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="ipt-parts-bad"
					   value="<?php echo (int) $this->item->get('badParts'); ?>"
					   readonly
				/>
			</div>
		</div>
	</div>
</div>
