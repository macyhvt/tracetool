<?php
// Register required libraries.
use Joomla\Uri\Uri;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
//$return = $view->getReturnPage();	// Browser back-link required for back-button.
$return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$pid    = $input->getInt('pid');
?>
<?php /* Access check */
$formData = null;

if (is_a($user, 'Nematrack\Entity\User')) :
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
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing process.
if (!is_a($item, 'Nematrack\Entity\Process') || (is_a($item, 'Nematrack\Entity\Process') && is_null($item->get('procID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PROCESS_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $pid)
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' .View::getInstance('processes', ['language' => $this->language])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->item              = $item;
$this->user              = $user;
$this->isArchived        = $this->item->get('archived') == '1';
$this->isBlocked         = $this->item->get('blocked') == '1';
$this->isDeleted         = $this->item->get('trashed') == '1';
$this->techParams        = $this->item->__get('techParams', []);	// This gets the stripped-down techParams (without static techParams)
$this->organisations     = $model->getInstance('organisations', ['language' => $this->language])->getList([
	'filter' => ListModel::FILTER_ALL
]);
$this->itemOrganisations = (array) $this->item->get('organisations');
?>


<style>
.nav-tabs {
	overflow: hidden;
}
.col-form-label,
.nav-tabs .nav-link {
	background-color: #e8edf3;
	border-color: #dee3e9 #dee3e9 #dee2e6;
	color: #30588B;
}
.nav-tabs .nav-link.active {
	background-color: #d9dee4;
	background-color: #d1e8ff;
	border-color: #cfd4da #cfd4da #dee2e6;
	color: #264E81;
}
.nav-tabs .nav-item > .nav-link.active {
	box-shadow: 0 0 6px 0 rgba(128, 128, 128, 0.5);
	box-shadow: 0 3px 9px 2px rgba(169, 192, 223, 0.8);
}
.nav-tabs .nav-item:first-of-type > .nav-link.active {
	box-shadow: 3px 0 6px -3px rgba(128, 128, 128, 0.5);
	box-shadow: 3px 3px 9px -1px rgba(169, 192, 223, 0.8);
}
.nav-tabs .nav-item:last-of-type > .nav-link.active {
	box-shadow: -3px 0 6px -3px rgba(128, 128, 128, 0.5);
	box-shadow: -3px 3px 9px -1px rgba(169, 192, 223, 0.8);
}

.form-horizontal.archived-item,
.form-horizontal.blocked-item,
.form-horizontal.deleted-item {
	overflow-x: hidden;
}
.form-horizontal.archived-item .status-badge,
.form-horizontal.blocked-item .status-badge,
.form-horizontal.deleted-item .status-badge {
	left: 0;
	line-height: 2;
}

.form-control:disabled,
.form-control[readonly],
.btn:disabled {
	cursor: not-allowed;
	opacity: .60;
}
.btn:disabled {
	opacity: .40;
}
.form-control[readonly]:not(:disabled) {
	cursor: default;
	opacity: 1;
}

.input-group-append .btn,
.input-group-append .btn:focus,
.input-group-append .btn:active,
.input-group-prepend .btn,
.input-group-prepend .btn:focus,
.input-group-prepend .btn:active {
	outline: 0 !important;
}

.status-badge {
	letter-spacing: 1px;
}

.viewTitle {
	line-height: 1 !important;
}
</style>

<div class="<?php echo trim(preg_replace('/[\s|\t|\n]+/', ' ', sprintf('form-horizontal position-relative %s %s %s',
		($this->isDeleted  ? 'deleted-item'  : ''),
		($this->isBlocked  ? 'blocked-item'  : ''),
		($this->isArchived ? 'archived-item' : '')))); ?>"
     id="<?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?>"
>
	<?php // View title and toolbar ?>
	<?php // TODO - implement toolbar ... it is very tricky because in this view showing buttons depends on item status and user access rights ?>
	<div class="row" style="overflow:hidden">
		<div class="col-12<?php echo ($this->isDeleted ? ' badge-danger' : ($this->isBlocked  ? ' badge-warning' : ($this->isArchived  ? ' badge-info' : ''))); ?>">
			<?php // B A C K - button ?>
			<?php if ($user->isCustomer() || $user->isSupplier()) : ?>
			<a href="javascript:void(0)"
			   role="button"
			   class="btn btn-link outline-0 pl-1 pr-3 allow-window-unload<?php echo ($this->isArchived || $this->isBlocked || $this->isDeleted) ? ' text-light' : ' text-dark'; ?>"
			   data-bind="windowClose"
			   data-force-reload="true"
			   style="vertical-align:text-bottom"
			>
				<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
					  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
					  data-toggle="tooltip"
				>
					<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
					<span class="btn-text sr-only"><?php
						echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $this->language) );
					?></span>
				</span>
			</a>
			<?php endif; ?>

			<h1 class="h3 viewTitle d-inline-block my-0 mr-3<?php echo ($this->isArchived || $this->isBlocked || $this->isDeleted) ? ' text-light' : ''; ?>"><?php
				echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $this->language);
			?></h1>

			<?php if ($this->isArchived || $this->isBlocked || $this->isDeleted) : ?>
			<span class="<?php echo trim(implode(' ', [
					'position-absolute',
					'btn',
					'd-inline-block',
					'h-100',
					'status-badge',
					// ($this->isArchived ? 'badge-info' : ($this->isBlocked ? 'badge-danger' : '')),
					'text-center',
					'text-white',
					'text-uppercase',
					'font-weight-bold'
				  ])); ?>"
				  style="background-color:unset; z-index:0; left:55px; width:90%; line-height:0.9"
			>
				<span class="d-inline-block align-middle"><?php
					switch (true) :
						case ( $this->isDeleted) :
							echo Text::translate('COM_FTK_STATUS_DELETED_TEXT', $this->language);
						break;

						case ( $this->isBlocked) :
							echo Text::translate('COM_FTK_STATUS_LOCKED_TEXT', $this->language);
						break;

						case ( $this->isArchived) :
							echo Text::translate('COM_FTK_STATUS_ARCHIVED_TEXT', $this->language);
						break;
					endswitch;
				?></span>
			</span>
			<?php endif; ?>


			<?php if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
			<?php 	if (UserHelper::isFroetekOrNematechMember($user) && $user->getFlags() >= \Nematrack\Access\User::ROLE_MANAGER) : // Editing is granted to privileged FRÖTEK- and NEMATECH-users only ?>
			<?php // E D I T - button ?>
			<div class="d-inline-block align-top">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text">
							<i class="fas fa-edit text-success"></i>
						</span>
					</div>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=edit&pid=%d', $this->language, $view->get('name'), $this->item->get('procID') ))); ?>"
					   role="button"
					   class="btn btn-sm btn-custom btn-edit left-radius-0 pr-md-3"
					   id="btn-edit"
					   data-bind="windowOpen"
					   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=edit&pid=%d&return=%s', $this->language, $view->get('name'), $this->item->get('procID'), base64_encode($return) ))); ?>"
					   data-location-target="_self"
					>
						<span title="<?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_%s_EDIT_THIS_TEXT', $view->get('name'))), $this->language); ?>"
							  aria-label="<?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LINK_TITLE_%s_EDIT_THIS_TEXT', $view->get('name'))), $this->language); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
								echo mb_strtolower( Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language) );
							?></span>
						</span>
					</a>
				</div>
			</div>
			<?php 	endif; // END: ACL-Check ?>
			<?php endif; // END: !$this->isArchived && !$this->isDeleted && !$this->isBlocked ?>

			<?php // C A N C E L - button ?>
			<div class="d-inline-block align-top ml-md-2 ml-lg-3">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text">
							<i class="fas fa-times text-red"></i>
						</span>
					</div>
					<a href="javascript:void(0)"
					   role="button"
					   class="btn btn-sm btn-custom btn-cancel left-radius-0 pr-md-3 allow-window-unload"
					   data-bind="windowClose"
					   data-force-reload="true"
					>
						<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
								echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $this->language) );
							?></span>
						</span>
					</a>
				</div>
			</div>

			<?php /*   T O O L B A R   */ ?>
			<?php if (UserHelper::isFroetekOrNematechMember($user) && $user->getFlags() >= \Nematrack\Access\User::ROLE_MANAGER) : // Management is granted to privileged FRÖTEK- and NEMATECH-users only ?>
			<div class="position-absolute" style="z-index:1; top:0; right:0; padding-right:15px">
				<div class="align-middle text-right">
					<?php // (U N) B L O C K - button ?>
					<?php if (($user->isQualityAssurance() || $user->isQualityManager())) : // (Un)Publishing is granted to higher privileged users only ?>
					<?php	if (!$this->isDeleted/*  && !$this->isArchived */) : ?>
					<form action="<?php echo View::getInstance('processes', ['language' => $this->language])->getRoute(); ?>"
						  method="post"
						  name="<?php echo sprintf('%s%sForm', ($this->isBlocked ? 'unblock' : 'block'), ucfirst($view->get('name'))); ?>"
						  class="form-horizontal d-inline-block"
						  data-submit=""
					>
						<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
						<input type="hidden" name="task"     value="<?php echo ($this->isBlocked ? 'unlock' : 'lock'); ?>" />
						<input type="hidden" name="state"    value="<?php echo ($this->isBlocked ? '0' : '1'); ?>" />
						<input type="hidden" name="pid"      value="<?php echo (int) $this->item->get('procID'); ?>" />
						<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
						<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

						<button type="submit"
								name="button"
								value="<?php echo ($this->isBlocked ? 'unlock' : 'lock'); ?>"
								class="btn btn-sm btn-<?php echo ($this->isArchived ? 'secondary' : 'dark'); ?> btn-lock align-super px-lg-3"
						>
							<span title="<?php      echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isBlocked ? 'UNLOCK' : 'LOCK')), $this->language); ?>"
								  aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isBlocked ? 'UNLOCK' : 'LOCK')), $this->language); ?>"
								  data-toggle="tooltip"
							>
								<i class="fas fa-lock<?php echo ($this->isBlocked ? '-open' : ''); ?>"></i>
								<span class="btn-text d-none d-md-inline ml-lg-2"><?php
									echo mb_strtolower(
										Text::translate(
											sprintf('COM_FTK_BUTTON_TEXT_%s_TEXT', ($this->isBlocked ? 'UNLOCK' : 'LOCK')
										), $this->language)
									);
								?></span>
							</span>
						</button>
					</form>
					<?php	endif; // END: !isDeleted && !isArchived ?>
					<?php endif; // END: ACL-Check ?>

					<?php // (U N) A R C H I V A T E - button ?>
					<?php if (FALSE && $user->getFlags() >= \Nematrack\Access\User::ROLE_ADMINISTRATOR) : // (Un)Publishing is granted to higher privileged users only ?>
					<?php	if (!$this->isDeleted) : ?>
					<form action="<?php echo View::getInstance('processes', ['language' => $this->language])->getRoute(); ?>"
						  method="post"
						  name="<?php echo sprintf('%s%sForm', ($this->isArchived ? 'restore' : 'archive'), ucfirst($view->get('name'))); ?>"
						  class="form-horizontal d-inline-block"
						  data-submit=""
					>
						<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
						<input type="hidden" name="task"     value="<?php echo ($this->isArchived ? 'restore' : 'archive'); ?>" />
						<input type="hidden" name="state"    value="<?php echo ($this->isArchived ? '0' : '1'); ?>" />
						<input type="hidden" name="pid"      value="<?php echo (int) $this->item->get('procID'); ?>" />
						<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
						<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

						<button type="submit"
								name="button"
								value="<?php echo ($this->isArchived ? 'restore' : 'archive'); ?>"
								class="btn btn-sm btn-<?php echo ($this->isArchived ? 'secondary' : 'dark'); ?> btn-archive align-super px-lg-3"
								onclick="return confirm('<?php echo Text::translate(
									sprintf('COM_FTK_DIALOG_%s_CONFIRM_%s_TEXT',
										mb_strtoupper($view->get('name')),
										mb_strtoupper($this->isArchived ? 'restoration' : 'archivation')
									), $this->language);
								?>')"
						>
							<span title="<?php      echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isArchived ? 'RESTORE' : 'ARCHIVATE')), $this->language); ?>"
								  aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isArchived ? 'RESTORE' : 'ARCHIVATE')), $this->language); ?>"
								  data-toggle="tooltip"
							>
								<i class="fas fa-archive"></i>
								<span class="btn-text d-none d-md-inline ml-lg-2"><?php
									echo mb_strtolower(
										Text::translate(
											sprintf('COM_FTK_BUTTON_TEXT_%s_TEXT', ($this->isArchived ? 'RESTORE' : 'ARCHIVATE')
										), $this->language)
									);
								?></span>
							</span>
						</button>
					</form>
					<?php	endif; // END: !isDeleted ?>
					<?php endif; // END: ACL-Check ?>

					<?php // (U N) D E L E T E - button ?>
					<?php if ($user->getFlags() >= \Nematrack\Access\User::ROLE_ADMINISTRATOR) : // (Un)Deleting is granted to higher privileged users only ?>
					<form action="<?php echo View::getInstance('processes', ['language' => $this->language])->getRoute(); ?>"
						  method="post"
						  name="<?php echo sprintf('delete%sForm', ucfirst($view->get('name'))); ?>"
						  name="<?php echo sprintf('%s%sForm', ($this->isDeleted ? 'recover' : 'delete'), ucfirst($view->get('name'))); ?>"
						  class="form-horizontal d-inline-block"
						  data-submit=""
					>
						<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
						<input type="hidden" name="task"     value="<?php echo ($this->isDeleted ? 'recover' : 'delete'); ?>" />
						<input type="hidden" name="state"    value="<?php echo ($this->isDeleted ? '0' : '1'); ?>" />
						<input type="hidden" name="pid"      value="<?php echo (int) $this->item->get('procID'); ?>" />
						<input type="hidden" name="return"   value="<?php echo (($this->isDeleted) ? base64_encode( $return ) : base64_encode( View::getInstance('processes', ['language' => $this->language])->getRoute() )); ?>" />
						<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

						<button type="submit"
								name="button"
								value="<?php echo ($this->isDeleted ? 'recover' : 'delete'); ?>"
								class="btn btn-sm btn-<?php echo ($this->isDeleted ? 'dark' : 'danger'); ?> btn-trashbin align-super px-lg-3"
								onclick="return confirm('<?php echo Text::translate(
									sprintf('COM_FTK_DIALOG_%s_CONFIRM_%s_TEXT',
										mb_strtoupper($view->get('name')),
										mb_strtoupper($this->isDeleted ? 'RECOVERY' : 'DELETION')
									), $this->language);
								?>')"
						>
							<span title="<?php      echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isDeleted ? 'RECOVER' : 'DELETE')), $this->language); ?>"
								  aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_%s_%s_THIS_TEXT', mb_strtoupper($view->get('name')), ($this->isDeleted ? 'RECOVER' : 'DELETE')), $this->language); ?>"
								  data-toggle="tooltip"
							>
								<i class="far fa-trash-alt"></i>
								<span class="btn-text d-none d-md-inline ml-lg-2"><?php
									echo mb_strtolower(
										Text::translate(
											sprintf('COM_FTK_BUTTON_TEXT_%s_TEXT', ($this->isDeleted ? 'RECOVER' : 'DELETE')
										), $this->language)
									);
								?></span>
							</span>
						</button>
					</form>
					<?php endif; // END: ACL-Check ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>

    <hr>

	<?php if ($user->getFlags() >= \Nematrack\Access\User::ROLE_WORKER) : ?>
		<?php if (!$this->item->get('error_catalog') || (is_countable($catalog = (array) $this->item->get('error_catalog'))) && empty($catalog)) : ?>
			<?php echo LayoutHelper::render('system.alert.danger', [
				'message' => Text::translate('COM_FTK_ERROR_APPLICATION_PROCESS_HAS_NO_ERROR_CATALOGUE_TEXT', $this->language),
				'attribs' => [
						'class' => 'alert-sm'
					]
			]); ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ($user->getFlags() >= \Nematrack\Access\User::ROLE_MANAGER) : ?>
		<?php if ($this->get('incomplete') && is_countable($untranslated = (array) $this->item->get('incomplete')->get('translation'))) : ?>
			<?php foreach ($untranslated as $property => $this->languageuages) : ?>
				<?php echo LayoutHelper::render('system.alert.notice', [
					'message' => sprintf(Text::translate('COM_FTK_HINT_PLEASE_TRANSLATE_X_INTO_Y_TEXT', $this->language),
						Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $property)), $this->language),
						implode(', ', array_map('mb_strtoupper', array_keys((array) $this->languageuages)))
					),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]); ?>
			<?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php // Inner toolbar (manage error catalog) ?>
	<div class="btn-toolbar float-right mt-md-1 mt-lg-2 pt-1" role="toolbar" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLBAR_TEXT', $this->language); ?>">
		<div class="btn-group" role="group" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLS_GROUP_TEXT', $this->language); ?>">
			<?php if (UserHelper::isFroetekOrNematechMember($user) /*&& ( // Access is granted to users of FRÖTEK + NEMATECH or
				  $this->user->getFlags() == User::ROLE_QUALITY_ASSURANCE ||    // Quality managers or
				  $this->user->getFlags() >= User::ROLE_PROGRAMMER			    // high privileged users only
			)*/) : ?>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=item.catalog&pid=%d', $this->language, $this->item->get('procID') ))); ?>"
			   role="button"
			   class="btn btn-link"
			   title="<?php echo Text::translate('COM_FTK_LABEL_ERROR_CATALOG_TEXT', $this->language); ?>"
			   data-toggle="tooltip"
			   style="vertical-align:baseline; color:inherit!important; text-decoration:none!important"
			>
				<i class="fas fa-book"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_ERROR_CATALOG_TEXT', $this->language); ?></span>
			</a>
			<?php endif; ?>
		</div>
	</div>

	<ul class="nav nav-tabs mt-md-3 mt-lg-4" id="myTab" role="tablist">
		<li class="nav-item" role="presentation">
			<a class="nav-link active" id="masterdata-tab"    data-toggle="tab" href="#masterdata"    role="tab" aria-controls="masterdata"    aria-selected="true"><?php
			echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $this->language); ?></a>
		</li>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="organisations-tab" data-toggle="tab" href="#organisations" role="tab" aria-controls="organisations" aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_PROCESS_RESPONSIBILITY_TEXT', $this->language); ?></a>
		</li>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="tech-params-tab"   data-toggle="tab" href="#tech-params"   role="tab" aria-controls="tech-params"   aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_TECHNICAL_PARAMETERS_TEXT', $this->language); ?></a>
		</li>
		<li class="nav-item" role="presentation">
			<a class="nav-link"        id="config-tab"        data-toggle="tab" href="#config"        role="tab" aria-controls="config"        aria-selected="false"><?php
			echo Text::translate('COM_FTK_LABEL_CONFIGURATION_TEXT', $this->language); ?></a>
		</li>
	</ul>

	<div class="tab-content" id="myTabContent">
		<div class="tab-pane py-4 fade show active" id="masterdata"    role="tabpanel" aria-labelledby="masterdata-tab"><?php    require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_masterdata.php',    $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="organisations" role="tabpanel" aria-labelledby="organisations-tab"><?php require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_organisations.php', $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="tech-params"   role="tabpanel" aria-labelledby="tech-params-tab"><?php   require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_tech-params.php',   $layout); ?></div>
		<div class="tab-pane py-4 fade"             id="config"        role="tabpanel" aria-labelledby="config-tab"><?php        require_once __DIR__ . DIRECTORY_SEPARATOR . sprintf('%s_config.php',        $layout); ?></div>
	</div>
</div>

<?php // Free memory.
unset($input);
unset($item);
unset($model);

unset($techParams);
unset($user);
unset($view);
