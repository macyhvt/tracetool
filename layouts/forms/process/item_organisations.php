<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Load view data */
$allOrganisations  = $this->organisations;
$itemOrganisations = $this->itemOrganisations;
?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group mb-0">
			<label for="organisations" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $this->language); ?>:</label>
			<div class="col">
				<select name="organisations[]"
						class="form-control"
						id="inputOrganisations"
						size="<?php echo is_countable($this->organisations) ? count($this->organisations) : 1; ?>"
						multiple
						readonly
						disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
				>
				<?php array_walk($allOrganisations, function($organisation, $orgID) use($itemOrganisations) { ?>
					<?php $organisation = new Registry($organisation); ?>
					<option value="<?php echo (int) $orgID; ?>"<?php echo (in_array($organisation->get('orgID'), $itemOrganisations) ? ' selected' : ''); ?>><?php
						echo $organisation->get('name');
					?></option>
				<?php }); ?>
				</select>
			</div>
		</div>
	</div>
</div>
