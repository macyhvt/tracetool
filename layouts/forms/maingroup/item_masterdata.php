<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use  \Access\User;
use  \Helper\UriHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="number" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_NUMBER_TEXT', $this->language); ?>:</label>
			<div class="col col-md-3">
				<input type="text"
					   class="form-control"
					   id="number"
					   value="<?php echo html_entity_decode($this->item->get('number')); ?>"
					   readonly
				/>
			</div>
			<?php // Dropdown for project state ?>
			<label for="status" class="col col-form-label col-md-2 text-lg-right"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?>:</label>
			<div class="col input-group">
				<input type="text"
					   class="form-control"
					   id="status"
					   value="<?php echo $this->__get('view')->get('statusTypes')->{$this->item->get('status')}->name; ?>"
					   readonly
				/>
				<?php if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted && $this->user->getFlags() >= User::ROLE_MANAGER) : // Editing is permitted to privileged user(s) only and only to active items ?>
				<div class="input-group-append">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=config.types&proid=%d', $this->language, $this->__get('view')->get('name'), $this->item->get('proID') ))); ?>"
					   role="button"
					   class="btn btn-outline-info"
					   id="link-to-website"
					   data-bind="windowOpen"
                       data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=config.types&proid=%d&return=%s', $this->language, $this->__get('view')->get('name'), $this->item->get('proID'), base64_encode($return) ))); ?>"
					   data-location-target="_self"
					>
						<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_CONFIGURE_PROJECT_STATUS_TYPES_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_CONFIGURE_PROJECT_STATUS_TYPES_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						>
							<i class="fas fa-sliders-h"></i>
							<span class="btn-text d-none Xd-md-inlineX ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_CONFIGURE_TEXT', $this->language); ?></span>
						</span>
					</a>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="name"
					   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   id="customer"
					   value="<?php echo html_entity_decode($this->item->get('customer')); ?>"
					   readonly
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
						  readonly
				><?php $description = html_entity_decode($this->item->get('description')); echo OutputFilter::cleanText($description); ?></textarea>
			</div>
		</div>
	</div>
</div>
