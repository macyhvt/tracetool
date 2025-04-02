<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Model\Lizt as ListModel;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$eid    = $input->getInt('eid', 0);
?>
<?php /* Access check */
$formData = null;

if (is_a($user, ' \Entity\User')) :
    try
    {
        $formData = $user->__get('formData');
        $formData = (is_array($formData)) ? $formData : [];
    }
    catch (Exception $e)
    {
        $formData = null;
    }
endif;

// TODO - Implement ACL and make calculate editor-right from ACL
$canDelete = true;
?>
<?php /* Process form data */
if (!empty($_POST)) :
	$view->saveErrorCatalog();
endif;
?>
<?php /* Load view data */
$item       = $view->get('item');
$errCatalog = $item->__get('errCatalog', []);
$list       = &$errCatalog;	// ADDED on 2023-06-05
//$filter     = $input->getString('filter', (string) \ \Model\Lizt::FILTER_ACTIVE);
$filter     = $input->getString('filter');
$lastID     = $model->getInstance('errors', ['language' => $this->language])->getLastInsertID();

echo (FTK_DEBUG ? '<pre style="color:crimson">' . print_r(sprintf('Last ErrorCatalog-ID: %d', $lastID), true) . '</pre>' : null);

$userModel  = $model->getInstance('user', ['language' => $this->language]);
$created    = $item->get('created');
$creator    = $userModel->getItem((int) $item->get('created_by'));
$modified   = $item->get('modified');
$modifyer   = $userModel->getItem((int) $item->get('modified_by'));

$formName       = 'editCatalogForm';	// ADDED on 2023-06-05
$filterFormName = sprintf('%sErrorsForm', mb_strtolower($view->get('name')));	// ADDED on 2023-06-05

$cnt = count($list);	// ADDED on 2023-06-05
?>

<style>
#errorCatalog > .card.focus {
	border-color: rgba(0, 123, 255, 0.3);
	box-shadow: 0 0 0.5rem 0.2rem rgba(0, 123, 255, 0.25)
}
#errorCatalog > .card input:focus,
#errorCatalog > .card input[autofocus],
#errorCatalog > .card.focus input[autofocus] {
	font-weight: bold;
}
/*#errorCatalog > .card.focus input.validation-result {
	color: red!important;
}*/
#errorCatalog input[type="text"] + .bootstrap-maxlength {
	/*position: unset !important;
	display: inline !important;
	margin-left: .5rem;*/
	line-height: 1 !important;
    top: 25% !important;	/* vertical align in the middle */
	left: unset !important;
    right: 25px !important;	/* col-padding of 15px + another 10px */
}
#errorCatalog .card-body.pb-4 {
	padding-bottom: 1.5rem !important;
}

<?php // ADDED on 2023-05-25 ?>
#errorCatalog > .card.deleted  .btn-toolbar {
	display: none;
}
#errorCatalog > .card.deleted  input,
#errorCatalog > .card.deleted  input:hover,
#errorCatalog > .card.deleted  input:focus,
#errorCatalog > .card.deleted  input:active,
#errorCatalog > .card.deleted  textarea,
#errorCatalog > .card.deleted  textarea:hover,
#errorCatalog > .card.deleted  textarea:focus,
#errorCatalog > .card.deleted  textarea:active,
#errorCatalog > .card.disabled input,
#errorCatalog > .card.disabled input:hover,
#errorCatalog > .card.disabled input:focus,
#errorCatalog > .card.disabled input:active,
#errorCatalog > .card.disabled textarea,
#errorCatalog > .card.disabled textarea:hover,
#errorCatalog > .card.disabled textarea:focus,
#errorCatalog > .card.disabled textarea:active {
	color: #999;
	outline: unset !important;
}
#errorCatalog > .card.deleted {
	border-color: rgba(114, 28, 36, 0.3); <?php // equals alert-danger colour #721c24 ?>
	outline: unset !important;
	opacity: 0.4;
}
#errorCatalog > .card.deleted:before {
	content: "<?php echo Text::translate('COM_FTK_HINT_DELETED_AFTER_SAVING_TEXT', $this->language); ?>";
    position: absolute;
    z-index: 2;
    transform: rotate(-10deg);
    font-size: 250%;
    font-weight: bold;
    font-variant: small-caps;
    height: 100%;
    top: 30%;
    left: 15%;
    right: 15%;
    color: rgba(114, 28, 36, 0.35); <?php // equals alert-danger colour #721c24 ?>
}
#errorCatalog > .card.deleted:after {
	content: "";
    position: absolute;
    z-index: 1;
    top: 1px;
    left: 1px;
    width: 99.9%;
    height: 99%;
    background-color: rgba(248, 215, 218, 0.1); <?php // equals alert-danger background-color #721c24 ?>
}
</style>

<div class="row">
	<div class="col">
		<h1 class="h3 viewTitle d-inline-block my-0 mr-3"><?php	// MODiFiED class on 2023-06-05 for the dropdown-button styles to apply
			echo ucfirst(
				sprintf(
					'%s:<span class="small ml-3">%s</span>',
					Text::translate('COM_FTK_LABEL_ERROR_CATALOG_TEXT', $this->language),
					html_entity_decode($item->get('name'))
				)
			);
		?></h1>

		<?php /* List filter */ ?><?php // ADDED on 2023-06-05 ?>
		<?php // TODO - make this a HTML widget (don't forget the above CSS) and replace this code ?>
		<?php if (0 && $cnt > 0) : ?>
		<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( $view->getRoute() ) ); ?>"
			  method="get"
			  class="form <?php echo $filterFormName; ?> d-inline-block"
			  name="<?php echo sprintf('%s%s', 'filter', ucfirst($filterFormName)); ?>"
			  id="<?php echo sprintf('%s%s', 'filter', ucfirst($filterFormName)); ?>"
			  data-submit=""
			  data-monitor-changes="false"
		>
			<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
			<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
			<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

			<div class="dropdown d-inline-block"
				 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
				 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
				 data-toggle="tooltip"
			>
				<button type="button"
						form="<?php echo sprintf('%s%s', 'filter', ucfirst($filterFormName)); ?>"
						class="btn btn-secondary dropdown-toggle"
						id="<?php echo sprintf('%s%sErrors', 'filter', ucfirst($view->get('name'))); ?>Button"
						data-toggle="dropdown"
						aria-haspopup="true"
						aria-expanded="false"
						style="vertical-align:super; padding:0.275rem 0.7rem; opacity:0.4"
						tabindex="<?php echo ++$this->tabindex; ?>"
				>
					<i class="fas fa-filter"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_FILTER_TEXT', $this->language); ?></span>
				</button>
				<div class="dropdown-menu dropdown-filter mt-0" data-multiple="false" aria-labelledby="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('name'))); ?>Button">
					<label for="filter"
						   class="d-block small mx-3"
						   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_TEXT', $this->language); ?>"
						   data-toggle="tooltip"
					>
						<input type="checkbox"
							   class="align-middle mr-1 auto-submit"
							   id="cb-filter-active"
							   name="filter"
							   value="<?php echo ListModel::FILTER_ACTIVE; ?>"
							   autocomplete="off"
							   <?php echo ($filter === '0') ? ' checked' : ''; ?>
						/>
						<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_LABEL', $this->language); ?></span>
					</label>
					<label for="filter"
						   class="d-block small mx-3"
						   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_TEXT', $this->language); ?>"
						   data-toggle="tooltip"
					>
						<input type="checkbox"
							   class="align-middle mr-1 auto-submit"
							   id="cb-filter-locked"
							   name="filter"
							   value="<?php echo ListModel::FILTER_LOCKED; ?>"
							   autocomplete="off"
							  <?php echo ($filter == ListModel::FILTER_LOCKED) ? ' checked' : ''; ?>
						/>
						<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_LABEL', $this->language); ?></span>
					</label>
					<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
					<label for="filter"
						   class="d-block small mx-3"
						   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_TEXT', $this->language); ?>"
						   data-toggle="tooltip"
					>
						<input type="checkbox"
							   class="align-middle mr-1 auto-submit"
							   id="cb-filter-deleted"
							   name="filter"
							   value="<?php echo ListModel::FILTER_DELETED; ?>"
							   autocomplete="off"
							   <?php echo ($filter == ListModel::FILTER_DELETED) ? ' checked' : ''; ?>
						/>
						<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_LABEL', $this->language); ?></span>
					</label>
					<?php endif; ?>
					<label for="filter"
						   class="d-block small mx-3 mb-1"
						   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_TEXT', $this->language); ?>"
						   data-toggle="tooltip"
					>
						<input type="checkbox"
							   class="align-middle mr-1 auto-submit"
							   id="cb-filter-all"
							   name="filter"
							   value="<?php echo ListModel::FILTER_ALL; ?>"
							   autocomplete="off"
							   <?php echo ($filter == ListModel::FILTER_ALL) ? ' checked' : ''; ?>
						/>
						<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language); ?></span>
					</label>
				</div>
			</div>
		</form>
		<?php endif; ?>

		<?php // View title and toolbar ?>
		<?php echo LayoutHelper::render(sprintf('toolbar.item.%s', 'edit'), [
				'viewName'   => $view->get('name'),
				'layoutName' => $layout,

				'formName'   => $formName,

				'backRoute'  => $return,	// View::getInstance('projects', ['language' => $this->language])->getRoute(),
				'hide'       => ['title','back','submitAndClose'/* ,'cancel' */],
				'user'       => $user
			],
			['language' => $this->language]
		); ?>
	</div>
</div>

<hr>

<?php if ($user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?>
	<?php if (0 &&	$item->get('incomplete') && is_countable($untranslated = (array) $item->get('incomplete')->get('translation'))) : ?>
		<?php foreach ($untranslated as $property => $this->languageuages) : ?>














			<?php // Render untranslated properties hint. ?>
			<?php echo LayoutHelper::render('system.alert.notice', [
					'message' => sprintf(
						Text::translate('COM_FTK_HINT_PLEASE_TRANSLATE_X_INTO_Y_TEXT', $this->language),
						Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $property)), $this->language),
						implode(', ', array_map('mb_strtoupper', array_keys((array) $this->languageuages)))
					),
					'attribs' => [
						'class' => 'alert-sm'
					]
			]); ?>
		<?php endforeach; ?>
	<?php else : ?>
		<?php echo LayoutHelper::render('system.alert.notice', [
			'message' => Text::translate('COM_FTK_HINT_PLEASE_TRANSLATE_EVERYTHING_TEXT', $this->language),
			'attribs' => [
				'class' => 'alert-sm'
			]
		]); ?>
	<?php endif; ?>
<?php endif; ?>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=edit.catalog&pid=%d',
		$this->language,
		$item->get('procID')
      ))); ?><?php echo ($eid > 0 ? sprintf('&eid=%d#', $eid, hash('CRC32', $eid)) : ''); ?>"
      method="post"
	  name="<?php echo $formName; ?>"
	  class="form form-horizontal catalogForm validate"
	  id="<?php echo $formName; ?>"
	  data-submit=""
	  data-monitor-changes="true"
>
	<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="editCatalog" />
	<input type="hidden" name="pid"      value="<?php echo (int) $item->get('procID'); ?>" />
	<input type="hidden" name="return"   value="<?php echo base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<div class="position-relative">
		<div class="mt-4" id="errorCatalog" data-next-id="<?php echo $lastID + 1; ?>"
	>
		<?php if ($cnt = count($errCatalog)) : $i = 1; ?>
		<?php 	foreach ($errCatalog as $errID => $catItem) : ?><?php
					$catItem   = new Registry($catItem);
					$isDeleted = ($catItem->get('trashed') && $catItem->get('deleted_by'));
					$setFocus  = ($errID == $view->get('input')->getInt('eid'));
		?>
			<a name="<?php echo hash('CRC32', (int) $errID); ?>" class="sr-only">&nbsp;</a>
			<div class="card mb-3<?php echo ($isDeleted ? ' d-none' : ($errID == $eid ? ' focus' : '')); ?>"
				 id="card-<?php echo $i; ?>"
				 data-crc32="<?php echo crc32((int) $errID); ?>"
			>
				<?php // Error number, title, toolbar ?>
				<div class="card-header border-bottom-0" id="heading-<?php echo $i; ?>">
					<div class="row">
						<?php // Field for error number ?>
						<div class="col col-2">
							<input type="text"
								   name="errors[<?php echo (int) $errID; ?>][number]"
								   class="form-control"
								   form="<?php echo $formName; ?>"
								   value="<?php echo html_entity_decode($catItem->get('number')); ?>"
								   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_ID_TEXT', $this->language); ?>"
								   minlength="4"
								   maxlength="10"
								   pattern="<?php echo FTKREGEX_ERROR_NUMBER; ?>"
								   required
								   readonly
								   data-rule-required="true"
								   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
								   data-rule-minlength="4"
								   data-msg-minlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_NUMBER_TOO_SHORT_TEXT', $this->language); ?>"
								   data-rule-maxlength="10"
								   data-msg-maxlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_NUMBER_TOO_LONG_TEXT', $this->language); ?>"
								   data-rule-pattern="<?php echo FTKREGEX_ERROR_NUMBER; ?>"
								   data-msg-pattern="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_INVALID_ERROR_NUMBER_TEXT', $this->language); ?>"
								   <?php echo ($isDeleted ? 'readonly' : ''); ?>
							>
						</div>
						<?php // Field for WinCarat number ?>
						<div class="col col-2">
							<input type="text"
								   name="errors[<?php echo (int) $errID; ?>][wincarat]"
								   class="form-control maxlength"
								   form="<?php echo $formName; ?>"
								   value="<?php echo html_entity_decode($catItem->get('wincarat')); ?>"
								   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_CODE_WINCARAT_TEXT', $this->language); ?>"
								   title="<?php echo Text::translate('COM_FTK_HINT_WINCARAT_CODE_PURPOSE_TEXT', $this->language); ?>"
								   minlength="4"
								   maxlength="10"
								   pattern="<?php echo FTKREGEX_ERROR_WINCARAT_CODE; ?>"
								   tabindex="<?php echo ++$this->tabindex; ?>"
								   <?php //required ?>
								   data-rule-required="false"
								   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
								   data-rule-minlength="4"
								   data-msg-minlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_WINCARAT_CODE_TOO_SHORT_TEXT', $this->language); ?>"
								   data-rule-maxlength="10"
								   data-msg-maxlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_WINCARAT_CODE_TOO_LONG_TEXT', $this->language); ?>"
								   data-rule-pattern="<?php echo FTKREGEX_ERROR_WINCARAT_CODE; ?>"
								   data-msg-pattern="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_INVALID_ERROR_WINCARAT_CODE_TEXT', $this->language); ?>"
								   data-toggle="tooltip"
								   data-trigger="focus"
								   <?php echo ($isDeleted ? 'readonly' : ''); ?>
							>
						</div>
						<?php // Field for error name ?>
						<div class="col col-6">
							<input type="text"
								   name="errors[<?php echo (int) $errID; ?>][name]"
								   class="form-control <?php echo (!$catItem->get('name') || $catItem->get('name') == Text::translate('COM_FTK_UNTRANSLATED_TEXT', $this->language) ? 'validation-result text-danger' : ''); ?> maxlength"
								   form="<?php echo $formName; ?>"
								   value="<?php echo html_entity_decode($catItem->get('name')); ?>"
								   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_TITLE_TEXT', $this->language); ?>"
								   minlength="3"
								   maxlength="100"
								   tabindex="<?php echo ++$this->tabindex; ?>"
								   required

								   tabindex="<?php echo ++$this->tabindex; ?>"
								   <?php // If a specific error item was selected for editing, add the "autofocus" property ?>
								   <?php echo ($errID == $eid ? 'autofocus' : ''); ?>
								   data-bind="fixDoubleQuotesToQuotes"
								   data-rule-required="true"
								   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
								   data-rule-minlength="3"
								   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_TITLE_TOO_SHORT_TEXT', $this->language); ?>"
								   data-rule-maxlength="100"
								   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_TITLE_TOO_LONG_TEXT', $this->language); ?>"
								   <?php echo ($isDeleted ? 'readonly' : ''); ?>
							>
						</div>

						<?php // Item toolbar ?>
						<div class="col col-2">
							<div class="btn-toolbar float-right" role="toolbar" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLBAR_WITH_BUTTON_GROUP_TEXT', $this->language); ?>">
								<div class="btn-group" role="group" aria-label="<?php echo sprintf('Button group %d', $errID); ?>"><?php // TODO - translate ?>
									<?php // ADD-Button ?>
									<button type="button"
											class="btn btn-outline-secondary btn-add"
											form="<?php echo $formName; ?>"
											data-toggle="addErrorCatalogItem"
											data-target="#errorCatalog"
											title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ADD_TEXT', $this->language); ?>"
											aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ADD_TEXT', $this->language); ?>"
											tabindex="<?php echo $this->tabindex = $this->tabindex + 2; ?>"
											onclick="window.FTKAPP.functions.setAttribute(this, 'data-tabindex', document.querySelector('#editCatalogForm input[name=&quot;tabindex&quot;]').value)"
									>
										<i class="fas fa-plus"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_ADD_TEXT', $this->language); ?></span>
									</button>

									<?php // SAVE-Button ?>
									<button type="button"
											class="btn btn-outline-secondary btn-edit btn-submit btn-save allow-window-unload"
											form="<?php echo $formName; ?>"
											title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
											aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
											tabindex="<?php echo ++$this->tabindex; ?>"
											onclick="document.querySelector('button[value=&quot;submit&quot;]').click()"
									>
										<i class="fas fa-save"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $this->language); ?></span>
									</button>

									<?php // DELETE-Button ?>
									<?php // FIXME - Must not be available to already tracked error(s) !!! ?>
									<button type="button"
											class="btn btn-outline-secondary btn-edit btn-trashbin"
											form="<?php echo $formName; ?>"
											title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ENTRY_DELETE_THIS_TEXT', $this->language); ?>"
											aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ENTRY_DELETE_THIS_TEXT', $this->language); ?>"
											tabindex="<?php echo ++$this->tabindex; ?>"
											data-toggle="deleteErrorCatalogItem"
											data-action="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&service=api&task=error.tracked&enum=%s', $this->language, html_entity_decode($catItem->get('number'))))); ?>"
											<?php // data-bind="deleteListItem" // DiSABLED ON 2023-05-25 because deletion has been refactored in Error-Model ?>
											data-target="#card-<?php echo $i; ?>"
											data-parent="#errorCatalog"
											data-confirm-delete="true"
											data-confirm-delete-empty="false"
											data-confirm-delete-message="<?php echo sprintf("%s\r\n%s",
												Text::translate('COM_FTK_DIALOG_PROCESS_ERROR_CONFIRM_DELETION_TEXT', $this->language),
												Text::translate('COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT', $this->language)
											); ?>"
									>
										<i class="far fa-trash-alt"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_DELETE_TEXT', $this->language); ?></span>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>

				<?php // Error description ?>
				<div id="collapse-<?php echo $i; ?>" class="collapse show" aria-labelledby="heading-<?php echo $i; ?>">
					<div class="card-body">
						<textarea name="errors[<?php echo (int) $errID; ?>][description]"
								  class="form-control maxlength"
								  form="<?php echo $formName; ?>"
								  placeholder="<?php echo sprintf('%s (%s)',
									Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_DESCRIPTION_TEXT', $this->language),
									Text::translate('COM_FTK_HINT_OPTIONAL_TEXT', $this->language)
								  ); ?>"
								  id=""
								  rows="2"
								  cols="10"
								  minlength="0"
								  maxlength="500"
								  tabindex="<?php echo ($this->tabindex - 3); ?>"

								  data-rule-maxlength="500"
								  data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_TEXT_TOO_LONG_TEXT', $this->language); ?>"
								  data-bind="fixDoubleQuotesToQuotes"
								  <?php echo ($isDeleted ? 'readonly' : ''); ?>
						><?php $description = html_entity_decode($catItem->get('description')); echo OutputFilter::cleanText($description); ?></textarea>
					</div>
				</div>
			</div>
			<?php $i += 1;
			endforeach; ?>

		<?php else : $errID = ++$lastID; ?>

			<div class="card mb-3"
				 id="card-<?php //echo $i; ?>"

			>
				<?php // Error number, title, toolbar ?>
				<div class="card-header border-bottom-0" id="heading-<?php //echo $i; ?>">
					<div class="row">
						<?php // Field for error number ?>
						<div class="col col-2">


							<input type="text"
								   name="errors[<?php echo (int) $errID; ?>][number]"
								   class="form-control"
								   form="<?php echo $formName; ?>"
								   value="<?php echo $model->getInstance('error', ['language' => $this->language])->createErrorNumber($errID); ?>"
								   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_ID_TEXT', $this->language); ?>"
								   title="<?php echo Text::translate('COM_FTK_HINT_ERROR_NUMBER_IS_AUTOMATICALLY_GENERATED_TEXT', $this->language); ?>"
								   minlength="4"
								   maxlength="10"
								   pattern="<?php echo FTKREGEX_ERROR_NUMBER; ?>"
								   required
								   readonly
								   data-rule-required="true"
								   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
								   data-rule-minlength="4"
								   data-msg-minlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_NUMBER_TOO_SHORT_TEXT', $this->language); ?>"
								   data-rule-maxlength="10"
								   data-msg-maxlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_NUMBER_TOO_LONG_TEXT', $this->language); ?>"
								   data-rule-pattern="<?php echo FTKREGEX_ERROR_NUMBER; ?>"
								   data-msg-pattern="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_INVALID_ERROR_NUMBER_TEXT', $this->language); ?>"
								   data-toggle="tooltip"
							>
						</div>
						<?php // Field for WinCarat number ?>
						<div class="col col-2">


							<input type="text"
								   name="errors[<?php echo (int) $errID; ?>][wincarat]"
								   class="form-control maxlength"
								   form="<?php echo $formName; ?>"
								   value=""
								   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_CODE_WINCARAT_TEXT', $this->language); ?>"
								   title="<?php echo Text::translate('COM_FTK_HINT_WINCARAT_CODE_PURPOSE_TEXT', $this->language); ?>"
								   minlength="4"
								   maxlength="10"
								   pattern="<?php echo FTKREGEX_ERROR_WINCARAT_CODE; ?>"
								   tabindex="<?php echo ++$this->tabindex; ?>"
								   <?php //required ?>
								   data-rule-required="false"
								   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
								   data-rule-minlength="4"
								   data-msg-minlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_WINCARAT_CODE_TOO_SHORT_TEXT', $this->language); ?>"
								   data-rule-maxlength="10"
								   data-msg-maxlength="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_ERROR_WINCARAT_CODE_TOO_LONG_TEXT', $this->language); ?>"
								   data-rule-pattern="<?php echo FTKREGEX_ERROR_WINCARAT_CODE; ?>"
								   data-msg-pattern="<?php echo Text::translate('COM_FTK_INPUT_VALIDATION_MESSAGE_INVALID_ERROR_WINCARAT_CODE_TEXT', $this->language); ?>"
								   data-toggle="tooltip"
								   data-trigger="hover focus"
							>
						</div>
						<?php // Field for error name ?>
						<div class="col col-6">


							<input type="text"
								   name="errors[<?php echo (int) $errID; ?>][name]"
								   class="form-control maxlength"
								   form="<?php echo $formName; ?>"
								   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_TITLE_TEXT', $this->language); ?>"
								   value=""
								   minlength="3"
								   maxlength="100"
								   tabindex="<?php echo ++$this->tabindex; ?>"
								   required
								   autofocus
								   data-rule-required="true"
								   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
								   data-rule-minlength="3"
								   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_TITLE_TOO_SHORT_TEXT', $this->language); ?>"
								   data-rule-maxlength="100"
								   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_TITLE_TOO_LONG_TEXT', $this->language); ?>"
								   data-bind="fixDoubleQuotesToQuotes"
							>
						</div>

						<?php // Item toolbar ?>
						<div class="col col-2">
							<div class="btn-toolbar float-right" role="toolbar" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLBAR_WITH_BUTTON_GROUP_TEXT', $this->language); ?>">
								<div class="btn-group" role="group" aria-label="<?php echo sprintf('Button group %d', $errID); ?>"><?php // TODO - translate ?>
									<?php // ADD-Button ?>
									<button type="button"
											class="btn btn-outline-secondary btn-add"
											form="<?php echo $formName; ?>"
											data-toggle="addErrorCatalogItem"
											data-target="#errorCatalog"
											title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ADD_TEXT', $this->language); ?>"
											aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ADD_TEXT', $this->language); ?>"
											tabindex="<?php echo $this->tabindex = $this->tabindex + 2; ?>"
											onclick="window.FTKAPP.functions.setAttribute(this, 'data-tabindex', document.querySelector('#editCatalogForm input[name=&quot;tabindex&quot;]').value)"
									>
										<i class="fas fa-plus"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_ADD_TEXT', $this->language); ?></span>
									</button>

									<?php // SAVE-Button ?>
									<button type="button"
											class="btn btn-outline-secondary btn-edit btn-submit btn-save allow-window-unload"
											form="<?php echo $formName; ?>"
											title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
											aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
											tabindex="<?php echo ++$this->tabindex; ?>"
											onclick="document.querySelector('button[value=&quot;submit&quot;]').click()"
									>
										<i class="fas fa-save"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $this->language); ?></span>
									</button>

									<?php // DELETE-Button ?>
									<?php // Must not be available for an initial empty list item and is therefore no longer rendered ?>
								</div>
							</div>
						</div>
					</div>
				</div>

				<?php // Error description ?>
				<div id="collapse-<?php //echo $i; ?>" class="collapse show" aria-labelledby="heading-<?php //echo $i; ?>">
					<div class="card-body">


						<textarea name="errors[<?php echo (int) $errID; ?>][description]"
								  class="form-control maxlength"
								  form="<?php echo $formName; ?>"
								  placeholder="<?php echo sprintf('%s (%s)',
									Text::translate('COM_FTK_INPUT_PLACEHOLDER_ERROR_DESCRIPTION_TEXT', $this->language),
									Text::translate('COM_FTK_HINT_OPTIONAL_TEXT', $this->language)
								  ); ?>"
								  id=""
								  rows="3"
								  cols="10"
								  minlength="0"
								  maxlength="500"
								  tabindex="<?php echo ($this->tabindex - 2); ?>"

								  data-rule-maxlength="500"
								  data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_TEXT_TOO_LONG_TEXT', $this->language); ?>"
								  data-bind="fixDoubleQuotesToQuotes"
						></textarea>
					</div>
				</div>
			</div>

		<?php endif; ?>
		</div>

		<?php // Helper field. Required for the javascript that adds new list items to read current tabindex value. ?>
		<input type="hidden" name="tabindex" value="<?php echo $this->tabindex; ?>" />
	</div>
</form>

<?php // Free memory.
unset($errCatalog);
unset($item);
