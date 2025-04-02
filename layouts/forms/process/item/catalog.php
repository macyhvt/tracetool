<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
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
$item       = $view->get('item');
$errCatalog = $item->__get('errCatalog', []);

$userModel  = $model->getInstance('user', ['language' => $this->language]);
$created    = $item->get('created');
$creator    = $userModel->getItem((int) $item->get('created_by'));
$modified   = $item->get('modified');
$modifyer   = $userModel->getItem((int) $item->get('modified_by'));
?>

<style>
.card-header > *:hover {
	cursor: pointer
}
.card-header > h5 {
	vertical-align: middle;
	margin-top: .1rem;
}
.fa-caret-down + span {
	margin-left: 0.75rem!important;
}

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

<div class="row">
	<div class="col">

		<h1 class="h3 viewTitle d-inline-block my-0 mr-3<?php echo ($this->isArchived || $this->isBlocked || $this->isDeleted) ? ' text-light' : ''; ?>"><?php
			echo ucfirst(
				sprintf(
					'%s:<span class="text-monospace small ml-3">%s</span>',
					Text::translate('COM_FTK_LABEL_ERROR_CATALOG_TEXT', $this->language),
					html_entity_decode($item->get('name'))
				)
			);
		?></h1>

		<?php /*// FIXME - debug this code. It was c+p from dev.nematrack.com and doesn't work
			$uri = new Uri($return);
			$uri->setVar('layout', 'item.catalog');
			$uri->setVar('return', base64_encode($return));

			$href = (new Uri( UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=edit.catalog&%s=%d&return=%s',
				$this->language,
				mb_strtolower($view->get('name')),
				$idKey,
				(int) $item->get($pkName),
				base64_encode($uri->toString())
			)))));*/
		?>

		<?php if ($user->isProgrammer()) : // DiSABLED on 2023-05-23 - Access is temp. limited to programmer only until error catalogue content issues are fixed to prevent database altering ?>

		<?php // E D I T - button ?>
		<?php if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
		<?php 	if ($user->get('orgID') == '1' && $user->getFlags() >= User::ROLE_MANAGER) : // Editing is granted to privileged FRÖTEK-users only ?>
		<div class="d-inline-block align-top">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-edit text-success"></i>
					</span>
				</div>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=edit.catalog&pid=%d&return=%s',
						$this->language,
						$item->get('procID'),
						base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) )
				   ))); ?>"
				   role="button"
				   class="btn btn-sm btn-custom btn-cancel left-radius-0 pr-md-3 allow-window-unload"
				   id="btn-edit"
				   data-bind="windowOpen"
				   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=edit.catalog&pid=%d&return=%s',
						$this->language,
						$item->get('procID'),
						base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) )
				   ))); ?>"
				   data-location-target="_self"
				>
					<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CATALOG_' . (count($errCatalog) ? 'EDIT' : 'CREATE') . '_TEXT', $this->language); ?>"
						  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CATALOG_' . (count($errCatalog) ? 'EDIT' : 'CREATE') . '_TEXT', $this->language); ?>"
						  data-toggle="tooltip"
					>
						<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
							echo Text::translate('COM_FTK_LABEL_' . (count($errCatalog) ? 'EDIT' : 'CREATE') . '_TEXT', $this->language);
						?></span>
					</span>
				</a>
			</div>
		</div>
		<?php 	endif; // END: ACL-Check ?>
		<?php endif; // END: !$this->isArchived && !$this->isDeleted && !$this->isBlocked ?>

		<?php endif; ?>

		<?php // C A N C E L - button ?>
		<div class="d-inline-block align-top ml-md-2 ml-lg-3">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-times text-red"></i>
					</span>
				</div>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=item&pid=%d', $this->language, $item->get('procID') ))); ?>"





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
	</div>
</div>

<hr>

<?php if ($cnt = count($errCatalog)) : ?>
<div class="mt-4" id="errorCatalog">
	<?php $i = 1; ?>
	<?php foreach ($errCatalog as $errID => $catItem) : ?>
	<?php 	$catItem = new Registry($catItem); ?>
	<div class="card<?php echo ($i < $cnt ? ' border-bottom-0' : ' mb-3'); ?>"
		 style="<?php echo ($i == 1 ? 'border-bottom-left-radius:0; border-bottom-right-radius:0' : ($i == $cnt ? 'border-top-left-radius:0; border-top-right-radius:0' : '')); ?>"
	>
		<div class="card-header border-bottom-0" id="heading-<?php echo $i; ?>">
			<h5 class="h6 d-inline-block mb-0 w-75" data-toggle="collapse" data-target="#collapse-<?php echo $i; ?>" aria-expanded="true" aria-controls="collapse-<?php echo $i; ?>">
				<i class="fas fa-caret-right"></i>
				<span class="ml-3"><?php echo html_entity_decode($catItem->get('name')); ?></span>
			</h5>

			<?php // Item toolbar ?>
			<?php if ($user->getFlags() >= User::ROLE_MANAGER) : // Management is permitted to privileged users only ?>
			<div class="btn-toolbar d-inline-block float-right" role="toolbar" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLBAR_WITH_BUTTON_GROUP_TEXT', $this->language); ?>">
				<div class="btn-group btn-group-sm" role="group" aria-label="First group">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=edit.catalog&pid=%d&eid=%d&return=%s',
							$this->language,
							$item->get('procID'),
							$errID,
							base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) )
					   ))); ?>#<?php echo hash('CRC32', (int) $errID); ?>"
					   role="button"
					   class="btn btn-link btn-edit"
					   title="<?php echo Text::translate('COM_FTK_LABEL_CATALOG_ITEM_EDIT_THIS_TEXT', $this->language); ?>"
					   style="vertical-align:super"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_CATALOG_ITEM_EDIT_THIS_TEXT', $this->language); ?></span>
					</a>
				</div>
			</div>
			<?php endif; ?>
		</div>

		<?php $description = trim($catItem->get('description')); ?>
		<div id="collapse-<?php echo $i; ?>" class="collapse<?php //echo ($i == 1 ? ' show' : ''); ?>" aria-labelledby="heading-<?php echo $i; ?>" data-parent="#errorCatalog">
			<div class="card-body">
			<?php if (mb_strlen($description)) : ?>
				<?php echo html_entity_decode($description); ?>
			<?php else : ?>
				<small class="text-muted"><?php echo Text::translate('COM_FTK_HINT_NO_DESCRIPTION_ADDED_TEXT', $this->language); ?></small>
			<?php endif; ?>
			</div>
		</div>
	</div>
	<?php $i += 1;
	endforeach; ?>
</div>
<?php else : ?>
	<?php if ($user->getFlags() >= User::ROLE_MANAGER) : ?>
		<?php echo LayoutHelper::render('system.alert.notice', [
			'message' => Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_ERROR_CATALOG_TEXT', $this->language),
			'attribs' => [
				'class' => 'alert-sm'
			]
		]); ?>
	<?php endif; ?>
<?php endif; ?>

<?php // Free memory.
unset($errCatalog);
unset($item);
