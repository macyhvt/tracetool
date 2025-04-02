<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Load view data */
$itemTechParams = ArrayHelper::getValue($this->formData, 'params', [], 'ARRAY');
?>

<?php if ($this->user->getFlags() >= User::ROLE_MANAGER) : ?>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_TECHNICAL_PARAMETERS_TEXT', $this->language),
		'attribs' => [
			'class' => 'alert-sm mb-3 mb-lg-4',
			   'id' => 'msg-procTechParams'
		]
	]); ?>
<?php endif; ?>

<ul class="list-unstyled<?php echo (is_countable($this->techParams) && !count($this->techParams)) ? ' my-0' : ''; ?>"
	id="procParams"
	data-next-id="<?php echo $this->lastID + 1; ?>"
>
	<li class="list-item dynamic-content position-relative">
		<div class="form-row my-md-2 my-lg-3">
			<div class="col col-md-2 col-lg-3">
				<div class="input-group" style="width:104.5%">
					<div class="input-group-prepend">
						<span class="input-group-text px-3">
							<i class="fas fa-plus px-1"></i>
						</span>
					</div>
					<button type="button"
							class="btn btn-info"
							data-toggle="hideElement"
							data-toggle-element="#msg-procTechParams"
							data-toggle-effect="slide"
							data-bind="addTechnicalParam"
							data-target="#procParams"
							data-parent="processForm"
							tabindex="<?php echo ++$this->tabindex; ?>"
							style="vertical-align:baseline; border-top-left-radius:0; border-bottom-left-radius:0"
					>
						<span title="<?php echo Text::translate('COM_FTK_LABEL_PARAMETER_ADD_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LABEL_PARAMETER_ADD_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text"><?php echo Text::translate('COM_FTK_LABEL_PARAMETER_ADD_TEXT', $this->language); ?></span>
						</span>
					</button>
				</div>
			</div>
		</div>
	</li>

	<?php foreach ($itemTechParams as $paramID => $paramValue) : ?>
	<?php 	$isStaticField = (!empty($paramValue) && ArrayHelper::arraySearch($paramValue, $this->staticTechParams)); ?>
	<li class="list-item dynamic-content position-relative" id="tp-<?php echo $paramID; ?>">
		<div class="form-row procParam my-md-1 my-lg-2">
			<div class="col">
				<div class="input-group">
					<input type="text"
						   name="params[<?php echo (int) $paramID; ?>]"
						   id="param-<?php echo (int) $paramID; ?>"
						   value="<?php echo OutputFilter::cleanText($paramValue); ?>"
					<?php if ($isStaticField) : ?>
						   class="form-control"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_NON_EDITABLE_STANDARD_FIELD_TEXT', $this->language); ?>"
						   data-toggle="tooltip"
						   data-placement="top"
					<?php else : ?>
						   class="form-control dynamic-datalist"
						   placeholder="<?php echo Text::translate('COM_FTK_LABEL_PARAMETER_NAME_TEXT', $this->language); ?>"
						   data-bind="parseDatalist"
						   data-list="techParams"
						   required
					<?php endif; ?>
						   tabindex="<?php echo ++$this->tabindex; ?>"
						<?php if ($isStaticField) : ?>
						<?php echo 'readonly disabled'; ?>	<?php // the 'disabled' attribute prevents this data from being submitted ?>
					<?php else : ?>
						<?php echo 'writable enabled'; ?>
					<?php endif; ?>
					/>
					<div class="input-group-append">
						<?php if (!$isStaticField) : ?>
						<button type="button"
								class="btn btn-danger"
								data-bind="deleteListItem"
								data-target="#tp-<?php echo $paramID; ?>"
								data-confirm-delete="true"
								data-confirm-delete-empty="false"
								data-confirm-delete-message="<?php echo sprintf("%s\r\n%s",
									Text::translate('COM_FTK_DIALOG_TECHNICAL_PARAMETER_CONFIRM_DELETION_TEXT', $this->language),
									Text::translate('COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT', $this->language)
								); ?>"
								tabindex="<?php echo ++$this->tabindex; ?>"
						>
							<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PARAMETER_DELETE_THIS_TEXT', $this->language); ?>"
								  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PARAMETER_DELETE_THIS_TEXT', $this->language); ?>"
								  data-toggle="tooltip"
							>
								<i class="far fa-trash-alt"></i>
								<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_LABEL_PARAMETER_DELETE_TEXT', $this->language); ?></span>
							</span>
						</button>
					<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</li>
<?php endforeach; ?>
</ul>
