<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Load view data */
$allOrganisations  = $this->organisations;
$itemOrganisations = ArrayHelper::getValue($this->formData, 'organisations', [], 'ARRAY');
?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group mb-0">
			<label for="organisations" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_RESPONSIBLE_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<select name="organisations[]"
						class="form-control"
						id="inputOrganisations"
						size="<?php echo (is_countable($this->organisations)) ? count($this->organisations) : 1; ?>"
						multiple
						required
						tabindex="<?php echo ++$this->tabindex; ?>"
						data-rule-required="true"
						data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_A_RESPONSIBLE_ORGANISATION_TEXT', $this->language); ?>"
				>
				<?php array_walk($allOrganisations, function($organisation, $orgID) use(&$itemOrganisations) { ?>
					<?php $organisation = new Registry($organisation); ?>
					<option value="<?php echo (int) $orgID; ?>"<?php echo (in_array($organisation->get('orgID'), $itemOrganisations)) ? ' selected' : ''; ?>><?php
						echo $organisation->get('name');
					?></option>
				<?php }); ?>
				</select>
			</div>
		</div>
	</div>
</div>
