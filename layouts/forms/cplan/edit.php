<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view   = $this->__get('view');
$layout = $view->get('input')->getCmd('layout');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$idKey  = $view->getIdentificationKey();
$idVal  = $view->get('input')->getInt($idKey);

$pkName = $view->get('item') ? $view->get('item')->getPrimaryKeyName() : null;	// item is null when data is edited because the view redirects here when there's POST data to run into POST handler switch below
$pkVal  = $view->get('item') ? $view->get('item')->get($pkName)        : null;

// TODO - Implement ACL and make calculate editor-right from ACL
$canEdit    = true;
$canEditArt = true;
$isEditArt  = true;
?>
<?php /* Process form data */
if (!empty($_POST)) :
	$view->saveAdd();
endif;
?>
<?php /* Prepare view data */
$this->item = $view->get('item', new Registry([
	'process_number'          => '10000',
	'process_name'            => 'Incoming goods',
	'process_utility'         => 'Labels',
	'number'                  => '',
	'product'                 => 'Completeness',
	'process'                 => '',
	'special_character_class' => '',
	'product_process_specification_tolerance' => 'Amount, Delivery documents',
	'evaluation_measurement_technique'        => 'visual',
	'size'                    => '1',
	'frequency'               => 'per charge',
	'control_method'          => 'ERP System',
	'reaction_plan'           => 'NE-05'
]));

$this->view      = $view;
$this->item      = $this->view->get('item');
$this->user      = $this->view->get('user');
$this->isBlocked = $this->item->get('blocked') == '1';
$this->isDeleted = $this->item->get('trashed') == '1';

// Create form submit URL
$formAction = new Uri('index.php');
$formAction->setVar('hl',     $this->language);
$formAction->setVar('view',   $this->view->get('name'));
$formAction->setVar('layout', $layout);

// Init tabindex
$this->tabindex = 0;
?>





<form action="<?php echo UriHelper::osSafe(UriHelper::fixURL($formAction->toString())); ?>"
      method="post"
      class="form form-horizontal <?php echo $this->view->get('formName'); ?> validate"
	  name="<?php echo $this->view->get('formName'); ?>"
	  id="<?php echo $this->view->get('formName'); ?>"

      data-submit=""
      data-monitor-changes="false"
>
	<input type="hidden" name="user"     value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($this->view->get('model')->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="<?php echo $this->view->get('taskName', $this->view->get('layoutName')); ?>" />
	<input type="hidden" name="<?php echo $idKey; ?>" value="<?php echo (int) $pkVal; ?>" />
	<input type="hidden" name="return"   value="<?php echo $this->view->get('input')->getBase64('return', $return); ?>" /><?php // previously it was base64_encode($return) ?>
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<?php // View title and toolbar ?>
	<?php echo LayoutHelper::render(sprintf('toolbar.item.%s', $this->view->get('layoutName')), [
			'viewName'   => mb_strtolower($this->view->get('name')),
			'layoutName' => $this->view->get('layoutName'),
			'taskName'   => $this->view->get('taskName'),
			'formName'   => $this->view->get('formName'),
			'heading'    => mb_strtoupper(sprintf('COM_FTK_HEADING_%s_%s_TEXT', $this->view->get('name'), $this->view->get('layoutName'))),
			'backRoute'  => $return,
			'hide'       => ['back'],
			'user'       => $this->user
		],
		['language' => $this->language]
	); ?>

	<?php // View metadata ?>
	<?php if (FALSE && !$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => ['edited','deleted']], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<fieldset id="fmea-columns">
		<legend class="sr-only fieldset-fmea-columns"><?php
			echo Text::translate('COM_FTK_FIELDSET_LABEL_COLUMN_HEADINGS_TEXT', $this->language);
		?></legend>

		<?php $fieldname = 'process_number'; ?>
		<div class="row form-group">
			<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php echo sprintf('%s&ndash;&nbsp;/ %s',
				Text::translate('COM_FTK_LABEL_PART_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_PROCESS_NUMBER_TEXT', $this->language)
			); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
				       name="<?php echo $fieldname; ?>"
				       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
				       class="form-control"
				       minlength="1"
				       maxlength="20"
				       <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
				       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NUMBER_TEXT', $this->language); ?>"
				       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NUMBER_TEXT', $this->language); ?>"
				       placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
				       Xrequired
				       tabindex="<?php echo ++$this->tabindex; ?>"
				       data-rule-required="false"
				       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
				       data-rule-minlength="1"
				       data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				       data-rule-maxlength="20"
				       data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<?php $fieldname = 'process_name'; ?>
		<div class="row form-group">
			<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php echo sprintf('%s&nbsp;/ %s',
				Text::translate('COM_FTK_LABEL_PROCESS_NAME_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_OPERATION_DESCRIPTION_TEXT', $this->language)
			); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
				       name="<?php echo $fieldname; ?>"
				       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
				       class="form-control"
				       minlength="3"
				       maxlength="50"
				       <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
				       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NAME_TEXT', $this->language); ?>"
				       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_NAME_TEXT', $this->language); ?>"
				       placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
				       Xrequired
				       tabindex="<?php echo ++$this->tabindex; ?>"
				       data-rule-required="false"
				       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
				       data-rule-minlength="3"
				       data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				       data-rule-maxlength="50"
				       data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
		<?php $fieldname = 'process_utility'; ?>
		<div class="row form-group mb-5">
			<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php echo implode(', ', [
				Text::translate('COM_FTK_LABEL_MACHINE_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_DEVICE_TEXT',  $this->language),
				Text::translate('COM_FTK_LABEL_JIG_TEXT',     $this->language),
				Text::translate('COM_FTK_LABEL_TOOL_TEXT',    $this->language)
			]); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
				       name="<?php echo $fieldname; ?>"
				       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
				       class="form-control"
				       minlength="3"
				       maxlength="50"
				       <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
				       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_UTILITY_TEXT', $this->language); ?>"
				       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_UTILITY_TEXT', $this->language); ?>"
				       placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
				       Xrequired
				       tabindex="<?php echo ++$this->tabindex; ?>"
				       data-rule-required="false"
				       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
				       data-rule-minlength="3"
				       data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				       data-rule-maxlength="50"
				       data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>

		<fieldset id="fmea-column-group-characteristics my-5">
			<legend class="h5 mb-2 mb-lg-3 mb-xl-4 fieldset-fmea-characteristics"><?php
				echo Text::translate('COM_FTK_FIELDSET_LABEL_CHARACTERISTICS_TEXT', $this->language);
			?></legend>

			<?php $fieldname = 'number'; ?>
			<div class="row form-group">
				<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
					echo Text::translate('COM_FTK_LABEL_NUMBER_TEXT', $this->language);
				?>:&nbsp;&ast;</label>
				<div class="col">
					<input type="text"
					       name="<?php echo $fieldname; ?>"
					       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
						   class="form-control"
						   minlength="3"
						   maxlength="50"
						   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_NUMBER_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_NUMBER_TEXT', $this->language); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
						   Xrequired
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   data-rule-required="false"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   data-rule-minlength="3"
						   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
						   data-rule-maxlength="50"
						   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
					/>
				</div>
			</div>
			<?php $fieldname = 'product'; ?>
			<div class="row form-group">
				<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
					echo Text::translate('COM_FTK_LABEL_PRODUCT_TEXT', $this->language);
				?>:&nbsp;&ast;</label>
				<div class="col">
					<input type="text"
					       name="<?php echo $fieldname; ?>"
					       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
						   class="form-control"
						   minlength="3"
						   maxlength="50"
						   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PRODUCT_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PRODUCT_TEXT', $this->language); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
						   Xrequired
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   data-rule-required="false"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   data-rule-minlength="3"
						   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
						   data-rule-maxlength="50"
						   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
					/>
				</div>
			</div>
			<?php $fieldname = 'process'; ?>
			<div class="row form-group">
				<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
					echo Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language);
				?>:&nbsp;&ast;</label>
				<div class="col">
					<input type="text"
					       name="<?php echo $fieldname; ?>"
					       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
						   class="form-control"
						   minlength="3"
						   maxlength="50"
						   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROCESS_TEXT', $this->language); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
						   Xrequired
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   data-rule-required="false"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   data-rule-minlength="3"
						   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
						   data-rule-maxlength="50"
						   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
					/>
				</div>
			</div>
		</fieldset>

		<?php $fieldname = 'special_character_class'; ?>
		<div class="row form-group my-5">
			<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
				echo Text::translate('COM_FTK_LABEL_SPECIAL_CHARACTERS_CLASS_TEXT', $this->language);
			?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
				       name="<?php echo $fieldname; ?>"
				       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
				       class="form-control"
				       minlength="3"
				       maxlength="50"
				       <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
                       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SPECIAL_CHARACTERS_CLASS_TEXT', $this->language); ?>"
                       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SPECIAL_CHARACTERS_CLASS_TEXT', $this->language); ?>"
                       placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
                       Xrequired
                       tabindex="<?php echo ++$this->tabindex; ?>"
                       data-rule-required="false"
                       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
                       data-rule-minlength="3"
                       data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
                       data-rule-maxlength="50"
                       data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>

		<fieldset id="fmea-column-group-methods my-5">
			<legend class="h5 mb-2 mb-lg-3 mb-xl-4 fieldset-fmea-methods"><?php
				echo Text::translate('COM_FTK_FIELDSET_LABEL_METHODS_TEXT', $this->language);
			?></legend>

			<?php $fieldname = 'product_process_specification_tolerance'; ?>
			<div class="row form-group">
				<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
					echo sprintf('%s / %s , %s / %s',
						Text::translate('COM_FTK_LABEL_PRODUCT_TEXT', $this->language),
						Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $this->language),
						Text::translate('COM_FTK_LABEL_SPECIFICATION_TEXT', $this->language),
						Text::translate('COM_FTK_LABEL_TOLERANCE_TEXT', $this->language)
					);
				?>:&nbsp;&ast;</label>
				<div class="col">
					<input type="text"
					       name="<?php echo $fieldname; ?>"
					       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
						   class="form-control"
						   minlength="3"
						   maxlength="50"
						   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PRODUCT_PROCESS_SPECIFICATION_TOLERANCE_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PRODUCT_PROCESS_SPECIFICATION_TOLERANCE_TEXT', $this->language); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
						   required
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   data-rule-required="true"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   data-rule-minlength="3"
						   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
						   data-rule-maxlength="50"
						   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
					/>
				</div>
			</div>
			<?php $fieldname = 'evaluation_measurement_technique'; ?>
			<div class="row form-group mb-5">
				<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
					echo sprintf('%s / %s',
						Text::translate('COM_FTK_LABEL_EVALUATION_TEXT', $this->language),
						Text::translate('COM_FTK_LABEL_MEASUREMENT_TECHNIQUE_TEXT', $this->language)
					);
				?>:&nbsp;&ast;</label>
				<div class="col">
					<input type="text"
					       name="<?php echo $fieldname; ?>"
					       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
						   class="form-control"
						   minlength="3"
						   maxlength="50"
						   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_EVALUATION_MEASUREMENT_TECHNIQUE_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_EVALUATION_MEASUREMENT_TECHNIQUE_TEXT', $this->language); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
						   required
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   data-rule-required="true"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   data-rule-minlength="3"
						   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
						   data-rule-maxlength="50"
						   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
					/>
				</div>
			</div>

			<fieldset id="fmea-column-group-sample my-5">
				<legend class="h5 mb-2 mb-lg-3 mb-xl-4 fieldset-fmea-sample"><?php
					echo Text::translate('COM_FTK_FIELDSET_LABEL_SAMPLE_TEXT', $this->language);
				?></legend>

				<?php $fieldname = 'size'; ?>
				<div class="row form-group">
					<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
						echo Text::translate('COM_FTK_LABEL_SIZE_TEXT', $this->language);
					?>:&nbsp;&ast;</label>
					<div class="col">
						<input type="text"
							   name="<?php echo $fieldname; ?>"
							   value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
							   class="form-control"
							   minlength="1"
							   maxlength="50"
							   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
							   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SIZE_TEXT', $this->language); ?>"
							   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SIZE_TEXT', $this->language); ?>"
							   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
							   required
							   tabindex="<?php echo ++$this->tabindex; ?>"
							   data-rule-required="true"
							   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
							   data-rule-minlength="1"
							   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
							   data-rule-maxlength="50"
							   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
						/>
					</div>
				</div>
				<?php $fieldname = 'frequency'; ?>
				<div class="row form-group">
					<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
						echo Text::translate('COM_FTK_LABEL_FREQUENCY_TEXT', $this->language);
					?>:&nbsp;&ast;</label>
					<div class="col">
						<input type="text"
							   name="<?php echo $fieldname; ?>"
							   value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
							   class="form-control"
							   minlength="3"
							   maxlength="50"
							   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
							   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_FREQUENCY_TEXT', $this->language); ?>"
							   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_FREQUENCY_TEXT', $this->language); ?>"
							   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
							   required
							   tabindex="<?php echo ++$this->tabindex; ?>"
							   data-rule-required="true"
							   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
							   data-rule-minlength="3"
							   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
							   data-rule-maxlength="50"
							   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
						/>
					</div>
				</div>
			</fieldset>

			<?php $fieldname = 'control_method'; ?>
			<div class="row form-group">
				<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
					echo Text::translate('COM_FTK_LABEL_CONTROL_METHOD_TEXT', $this->language);
				?>:&nbsp;&ast;</label>
				<div class="col">
					<input type="text"
					       name="<?php echo $fieldname; ?>"
					       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
						   class="form-control"
						   minlength="3"
						   maxlength="50"
						   <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CONTROL_METHOD_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_CONTROL_METHOD_TEXT', $this->language); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
						   required
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   data-rule-required="true"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   data-rule-minlength="3"
						   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
						   data-rule-maxlength="50"
						   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
					/>
				</div>
			</div>
		</fieldset>

		<?php $fieldname = 'reaction_plan'; ?>
		<div class="row form-group mt-5">
			<label for="<?php echo $fieldname; ?>" class="col col-form-label col-md-2 col-lg-3 col-xl-4"><?php
				echo Text::translate('COM_FTK_LABEL_REACTION_PLAN_TEXT', $this->language);
			?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
				       name="<?php echo $fieldname; ?>"
				       value="<?php echo html_entity_decode($this->item->get($fieldname)); ?>"
				       class="form-control"
				       minlength="3"
				       maxlength="50"
				       <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
                       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_REACTION_PLAN_TEXT', $this->language); ?>"
                       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_REACTION_PLAN_TEXT', $this->language); ?>"
                       placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
                       required
                       tabindex="<?php echo ++$this->tabindex; ?>"
                       data-rule-required="true"
                       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
                       data-rule-minlength="3"
                       data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
                       data-rule-maxlength="50"
                       data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
				/>
			</div>
		</div>
	</fieldset>
</form>

<?php // Free memory.
?>
