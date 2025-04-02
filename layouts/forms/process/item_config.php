<?php
// Register required libraries.
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$config   = $this->item->get('config');
$sections = array_keys($config->toArray());
?>

<?php $i = 1; foreach ($sections as $section) : ?>
<fieldset class="<?php echo $i == count($sections) ? 'mb-0' : ''; ?>" data-section-count="<?php echo $i; ?>">
	<legend class="h5 text-muted mb-3 ml-2 pl-2"
			data-section-name="<?php echo $section; ?>"
			style="font-variant:petite-caps"
	><?php $sectionTitle = $config->get(sprintf('%s.label', $section));
		echo Text::translate($sectionTitle, $this->language); ?>
		<?php // Inline quick-info icon ?>
		<?php if ($sectionHint = $config->get(sprintf('%s.description', $section))) : ?>
		<small title="click for explanation" data-toggle="tooltip">
			<i class="fas fa-info-circle text-muted ml-1 autohide"
			   role="button"
			   title="<?php echo Text::translate($sectionTitle, $this->language); ?>"
			   data-title="<?php echo Text::translate($sectionTitle, $this->language); ?>"
			   data-toggle="popover"
			   data-timeout="15000"
			   data-content="<?php echo Text::translate($sectionHint, $this->language); ?>"
			></i>
		</small>
		<?php endif; ?>
	</legend>

	<?php // Extract all parameters in this configuration section ?>
	<?php $params = $config->extract(sprintf('%s.params', $section)); ?>

	<?php if ($params && ($keys = array_keys($params->toArray())) && count($keys)) : ?>
	<div class="row form-group ml-sm-0<?php echo $i == count($sections) ? ' mb-0' : ''; ?>"
		 data-section-count="<?php echo $i; ?>"
	>
		<?php foreach ($keys as $param) : ?><?php
				$fieldName  = sprintf('config[%s][params][%s][value]', $section, $param);
				$fieldLabel = $params->get(sprintf('%s.label', $param));
				$fieldValue = $params->get(sprintf('%s.value', $param));
		?>
		<label for="<?php echo $fieldName; ?>" class="col col-form-label col-md-4 col-lg-3"><?php
			echo Text::translate($fieldLabel, $this->language);
		?>:</label>
		<div class="col">
			<input type="text"
				   name="<?php echo $fieldName; ?>"
				   class="form-control"
				   value="<?php echo sprintf('%d %s', $fieldValue, Text::translate(mb_strtoupper(sprintf('COM_FTK_LIST_OPTION_HOURS_%s_TEXT', ($fieldValue == 1 ? 'N_1' : 'N'))), $this->language)); ?>"
				   readonly
			/>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</fieldset>
<?php $i += 1; endforeach; ?>
