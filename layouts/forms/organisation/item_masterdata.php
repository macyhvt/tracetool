<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use  \Text;
use  \Helper\UriHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">
			<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LABEL_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   name="name"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="country" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_COUNTRY_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   name="country"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->address->get('country')); ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="zip" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ZIP_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   class="form-control"
					   name="zip"
					   value="<?php echo html_entity_decode($this->address->get('zip')); ?>"
					   readonly
				/>
			</div>
			<label for="city" class="col col-form-label col-md-2 text-lg-right"><?php echo Text::translate('COM_FTK_LABEL_CITY_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   name="city"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->address->get('city')); ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="addressline" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ADDRESS_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   name="addressline"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->address->get('addressline')); ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="role" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ROLE_TEXT', $this->language); ?>:</label>
			<div class="col">
				<input type="text"
					   name="role"
					   class="form-control"
					   value="<?php echo html_entity_decode($this->item->get('role')->get('name')); ?>"
					   readonly
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="homepage" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_HOMEPAGE_TEXT', $this->language); ?>:</label>
			<div class="col input-group">
				<input type="text"
					   name="homepage"
					   class="form-control"
					   value="<?php echo UriHelper::stripProtocol($this->item->get('homepage')); ?>"
					   aria-describedby="link-to-website"
					   readonly
				/>
				<div class="input-group-append">
					<a href="<?php echo html_entity_decode($this->item->get('homepage')); ?>"
					   role="button"
					   class="btn btn-outline-info"
					   id="link-to-website"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VISIT_HOMEPAGE_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_VISIT_HOMEPAGE_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
					   target="_blank"
					   rel="external"
					>
						<i class="fas fa-globe"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_VISIT_HOMEPAGE_TEXT', $this->language); ?></span>
					</a>
				</div>
			</div>
		</div>
        <div class="row form-group">
            <label for="zip" class="col col-form-label col-md-2"><?php echo Text::translate('Organisation Color', $this->language); ?>:</label>
            <div class="col">
                <input type="color"
                       class="form-control"
                       name="orgColor"
                       value="<?php echo html_entity_decode($this->item->get('org_color')); ?>"
                       readonly
                />
            </div>
            <label for="city" class="col col-form-label col-md-2 text-lg-right"><?php echo Text::translate('Organisation Abbr.', $this->language); ?>:</label>
            <div class="col">
                <input type="text"
                       name="orgAbbr"
                       class="form-control"
                       value="<?php echo html_entity_decode($this->item->get('org_abbr')); ?>"
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
