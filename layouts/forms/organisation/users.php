<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');
$userID = $user->get('userID');
$orgID  = $user->get('orgID');

$list   = [];
$filter = $input->getString('filter', (string) ListModel::FILTER_ALL);
$layout = $input->getCmd('layout');
$task   = $input->post->getCmd('task')  ?? ($input->getCmd('task') ?? null);
$oid    = $input->getInt('oid');
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
//$list       = $model->getInstance('organisation', ['language' => $this->language])->getOrganisationUsers__OBSOLETE($oid);
$list       = $model->getInstance('organisation', ['language' => $this->language])->getOrganisationUsers([
	'orgID'  => $oid,   // FIXME
	'filter' => $filter,
]);

$this->item       = $item;
$this->list       = $list;
$this->user       = $user;
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';

// Init tabindex
$tabindex  = 0;
?>

<style>
#users-list th,
#users-list td {
	vertical-align: inherit;	/* required to align text with buttons vertically centered */
}
</style>

<div class="form-horizontal" id="organisationUsersForm">
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=item&oid=%d', $this->language, $this->item->get('orgID') ))); ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?></span>
	</a>

	<h1 class="h3 viewTitle d-inline-block mr-3"><?php
		echo ucfirst(
			sprintf(
				'%s:<span class="small ml-3">%s</span>',
				Text::translate('COM_FTK_LABEL_USERS_TEXT', $this->language),
				html_entity_decode($this->item->get('name'))
			)
		);
	?></h1>

	<?php /* List filter */ ?>
	<?php // TODO - make this a HTML widget (don't forget the above CSS) and replace this code ?>
	<?php if ($user->getFlags() >= User::ROLE_MANAGER) : ?>
	<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( /* $input->server->getUrl( 'REQUEST_URI' ) */ $view->getRoute() ) ); ?>"
		  method="get"
		  class="form <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> d-inline-block"
		  name="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
		  id="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
		  data-submit=""
		  data-monitor-changes="false"
	>
		<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
		<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
		<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />
		<input type="hidden" name="oid"    value="<?php echo $oid; ?>" />

		<div class="dropdown d-inline-block"
			 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
			 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
			 data-toggle="tooltip"
		>
			<button type="button"
					form="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
					class="btn btn-secondary dropdown-toggle"
					id="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('name'))); ?>Button"
					data-toggle="dropdown"
					aria-haspopup="true"
					aria-expanded="false"
					style="vertical-align:super<?php /*; padding:0.275rem 0.7rem*/ ?>; opacity:0.4"
					tabindex="<?php echo ++$tabindex; ?>"
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
						   name="filter"
						   value="<?php echo ListModel::FILTER_LOCKED; ?>"
						   autocomplete="off"
						   <?php echo ($filter == ListModel::FILTER_LOCKED) ? ' checked' : ''; ?>
					/>
					<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_LABEL', $this->language); ?></span>
				</label>
				<?php if (FALSE && !$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
				<label for="filter"
					   class="d-block small mx-3"
					   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
				>
					<input type="checkbox"
						   class="align-middle mr-1 auto-submit"
						   name="filter" value="<?php echo ListModel::FILTER_DELETED; ?>"
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
						   name="filter" value="<?php echo ListModel::FILTER_ALL; ?>"
						   autocomplete="off"
						   <?php echo ($filter == ListModel::FILTER_ALL) ? ' checked' : ''; ?>
					/>
					<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language); ?></span>
				</label>
			</div>
		</div>
	</form>
	<?php endif; ?>

	<?php /* Create User */ ?>
	<?php if (!$this->isDeleted && !$this->isBlocked && $user->getFlags() >= User::ROLE_ADMINISTRATOR) : // Creation is permitted to (super)admins only ?>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=user_add&oid=%d', $this->language, $this->item->get('orgID') ))); ?>"
	   role="button"
	   class="btn btn-info"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_CREATE_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_CREATE_TEXT', $this->language); ?>"
	   tabindex="<?php echo ++$tabindex; ?>"
	   style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="fas fa-plus"></i>
		<span class="d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LINK_TITLE_USER_CREATE_TEXT', $this->language); ?></span>
	</a>
	<?php endif; ?>

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php if (is_countable($list) && count($list)) : ?>
	<table class="table table-sm" id="users-list">
		<thead class="thead-dark">
			<tr>
				<?php if ($user->isGuest() || $user->isCustomer() || $user->isSupplier()) : ?>
				<th scope="col"><span class="pl-2">#</span></th>
				<?php endif; ?>
				<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
				<th scope="col" class="text-center"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?></th>
				<?php endif; ?>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->language); ?></th>
				<th scope="col" class="d-none d-md-table-cell"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_TEXT', $this->language); ?></th>
				<th scope="col" class="d-none d-md-table-cell"><?php echo Text::translate('COM_FTK_LABEL_CREATED_TEXT', $this->language); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_LAST_VISIT_TEXT', $this->language); ?></th>
				<th scope="col"></th>
			</tr>
		</thead>
		<tbody>
			<?php $i = 1; ?>
			<?php foreach ($list as $u) : ?><?php
					$item = new Registry($u);

					// Don't display blocked accounts to guests or customers/suppliers.
					if ($item->get('blocked') == '1' && ($user->isGuest() || $user->isCustomer() || $user->isSupplier())) :
						continue;
					endif;

					$created       = $item->get('created', FTKRULE_NULLDATE);
					$created       = ($created == FTKRULE_NULLDATE) ? null : $created;
					$created       = (is_a($created, 'DateTime') ?
										$created : (is_string($created) ?
											(new DateTime($created, new DateTimeZone(FTKRULE_TIMEZONE))) : null));

					$lastVisitDate = $item->get('lastVisitDate', FTKRULE_NULLDATE);
					$lastVisitDate = ($lastVisitDate == FTKRULE_NULLDATE) ? null : $lastVisitDate;
					$lastVisitDate = (is_a($lastVisitDate, 'DateTime') ?
										$lastVisitDate : (is_string($lastVisitDate) ?
											(new DateTime($lastVisitDate, new DateTimeZone(FTKRULE_TIMEZONE))) : null));
			?>
			<tr class="list-item<?php echo ($item->get('trashed') ? ' list-item-hidden d-none' : ''); ?>">
				<?php if ($user->isGuest() || $user->isCustomer() || $user->isSupplier()) : ?>
				<td scope="row">
					<span class="pl-1"><?php echo $i < 10 ? "0$i" : $i; ?></span>
				</td>
				<?php endif; ?>

				<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
				<td class="text-center">
					<?php if (!$this->isDeleted && !$this->isBlocked && $user->getFlags() >= User::ROLE_ADMINISTRATOR) : // Management is permitted to (super)admins only ?>
					<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=default', $this->language ))); ?>"
						  method="post"
						  name="lockUserForm-<?php echo $i; ?>"
						  class="d-inline"
						  id="lockUserForm-<?php echo $i; ?>"
						  data-submit=""
					>
						<input type="hidden" name="user"   value="<?php echo (int) $user->get('userID'); ?>" />
						<input type="hidden" name="oid"    value="<?php echo (int) $item->get('orgID'); ?>" />
						<input type="hidden" name="lng"    value="<?php echo $this->language; ?>" />
						<input type="hidden" name="lngID"  value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
						<input type="hidden" name="task"   value="<?php echo ($item->get('blocked') ? 'unlock' : 'lock'); ?>" />
						<input type="hidden" name="xid"    value="<?php echo (int) $item->get('userID'); ?>" />
						<?php if (FALSE) : ?>
						<input type="hidden" name="return" value="<?php echo base64_encode('index.php?hl=' . $this->language . '&view=organisation&layout=users&oid=' . (int) $item->get('orgID')); ?>" />
						<?php endif; ?>

						<button type="submit"
								class="btn btn-sm btn-link btn-lock"
								form="lockUserForm-<?php echo $i; ?>"
								<?php if (FALSE) : // FIXME - Javascript functionality not yet implemented ?>
								data-toggle="lockUser"
								data-target="#users-list"
								<?php endif; ?>
								data-toggle="tooltip"
								title="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_USER_%s_THIS_TEXT', ($item->get('blocked') ? 'UNLOCK' : 'LOCK')), $this->language); ?>"
								aria-label="<?php echo Text::translate(sprintf('COM_FTK_BUTTON_TITLE_USER_%s_THIS_TEXT', ($item->get('blocked') ? 'UNLOCK' : 'LOCK')), $this->language); ?>"
						>
							<i class="fas fa-lock<?php echo $item->get('blocked') ? '' : '-open'; ?>"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('blocked') ? 'BLOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>"></span>
						</button>
					</form>
					<?php else : ?>
					<span class="btn btn-sm btn-link btn-lock"
						  data-toggle="tooltip"
						  title="<?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('blocked') ? 'LOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>"
					>
						<i class="fas fa-lock<?php echo $item->get('blocked') ? '' : '-open'; ?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('blocked') ? 'BLOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>"></span>
					</span>
					<?php endif; ?>
				</td>
				<?php else : ?>
				<?php if (FALSE) : ?>
				<td class="text-center"
					data-toggle="tooltip"
					title="<?php echo Text::translate('COM_FTK_STATUS_' . (($item->get('trashed')) ? 'TRASHED' : ($item->get('blocked') ? 'LOCKED' : 'ACTIVE')) . '_TEXT', $this->language); ?>"
				>
					<span class="btn-lock">
						<i class="<?php echo ($item->get('trashed')
							? 'far fa-trash-alt text-muted'
							: ($item->get('archived')
								? 'fas fa-archive text-muted'
								: ($item->get('blocked')
									? 'fas fa-lock'
									: 'fas fa-lock-open')));
						?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . (($item->get('trashed'))
							? 'TRASHED'
							: ($item->get('blocked')
								? 'LOCKED'
								: 'ACTIVE')) . '_TEXT', $this->language);
						?></span>
					</span>
				</td>
				<?php endif; ?>
				<?php endif; ?>

				<td><?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=user_item&oid=%d&uid=%d', $this->language, $item->get('orgID'), $item->get('userID') ))); ?>"
					   <?php if ($user->getFlags() >= User::ROLE_ADMINISTRATOR) :	// Display user roles in pop-opver ?><?php
								$roles = explode(',', $item->get('groups'));

								array_shift($roles);	// Role "REGISTERED" shall not be displayed.

								$roles = array_map(function($role) { return Text::translate($role, $this->language); }, $roles);
					   ?>
					   data-toggle="tooltip"
					   data-html="true"
					   data-sanitize="false"
					   data-title="<?php echo Text::translate('COM_FTK_HEADING_ROLES_TEXT', $this->language); ?>"
					   title="&lt;h6 class&equals;mb-1&gt;<?php echo Text::translate('COM_FTK_HEADING_ROLES_TEXT', $this->language); ?>&lt;/h6&gt;<?php echo ltrim(implode(', ', $roles), '<br>'); ?>"
					   <?php else : ?>
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_VIEW_THIS_TEXT', $this->language); ?>"
					   <?php endif; ?>
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_VIEW_THIS_TEXT', $this->language); ?>"
					><?php echo html_entity_decode($item->get('fullname')); ?></a>
				<?php else : ?>
					 <?php echo html_entity_decode($item->get('fullname')); ?>
				<?php endif; ?></td>

				<td class="d-none d-md-table-cell"><?php echo html_entity_decode($item->get('email')); ?></td>
				<td class="d-none d-md-table-cell"><?php echo (is_null($created) ? '&ndash;' : $created->format('d.m.Y')); ?></td>
				<td><?php echo (is_null($lastVisitDate) ? '&ndash;' : $lastVisitDate->format('d.m.Y H:i')); ?></td>

				<?php continue; // FIXME - It should be ensured that the admin belongs to the organisation it is about to manage here. ?>

				<?php if (!$this->isDeleted && !$this->isBlocked && $user->getFlags() >= User::ROLE_ADMINISTRATOR) : // Creation is permitted to (super)admins only ?>
				<td class="text-right">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=user_edit&oid=%d&uid=%d', $this->language, $item->get('orgID'), $item->get('userID') ))); ?>"
					   role="button"
					   class="btn btn-sm btn-link btn-edit"
					   data-toggle="tooltip"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?>"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?></span>
					</a>
					<?php //if ($user->getFlags() >= \Nematrack\Access\User::ROLE_ADMINISTRATOR) : // Deletion is permitted to (super)admins only ?>
					<form action="<?php echo View::getInstance('users', ['language' => $this->language])->getRoute(); ?>"
						  method="post"
						  name="deleteUserForm-<?php echo $i; ?>"
						  class="d-inline"
						  id="deleteUserForm-<?php echo $i; ?>"
						  data-submit=""
					>
						<input type="hidden" name="user"   value="<?php echo (int) $user->get('userID'); ?>" />
						<input type="hidden" name="oid"    value="<?php echo (int) $item->get('orgID'); ?>" />
						<input type="hidden" name="lng"    value="<?php echo $this->language; ?>" />
						<input type="hidden" name="lngID"  value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
						<input type="hidden" name="task"   value="delete" />
						<input type="hidden" name="xid"    value="<?php echo (int) $item->get('userID'); ?>" />
						<?php if (FALSE) : ?>
						<input type="hidden" name="return" value="<?php echo base64_encode('index.php?hl=' . $this->language . '&view=organisation&layout=users&oid=' . (int) $item->get('orgID')); ?>" />
						<?php endif; ?>

						<button type="submit"
								class="btn btn-sm btn-link btn-trashbin"
								form="deleteUserForm-<?php echo $i; ?>"
								<?php if (FALSE) : // FIXME - Javascript functionality not yet implemented ?>
								data-toggle="deleteUser"
								data-target="#users-list"
								<?php endif; ?>
								title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_USER_DELETE_THIS_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_USER_DELETE_THIS_TEXT', $this->language); ?>"
								onclick="return confirm('<?php echo Text::translate('COM_FTK_DIALOG_USER_CONFIRM_DELETION_TEXT', $this->language); ?>')"
						>
							<i class="far fa-trash-alt"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_USER_DELETE_TEXT', $this->language); ?></span>
						</button>
					</form>
					<?php //endif; ?>
				</td>
				<?php endif; ?>
			</tr>
			<?php $i += 1;?>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<?php //die('STOP > ELSE'); ?>
		<?php if ($user->getFlags() >= User::ROLE_MANAGER) : ?>
			<?php echo LayoutHelper::render('system.alert.info', [
				'message' => Text::translate('COM_FTK_HINT_ORGANISATION_HAS_NO_USERS_TEXT', $this->language),
				'attribs' => [
					'class' => 'alert-sm'
				]
			]); ?>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php // Free memory
unset($input);
unset($item);
unset($list);
unset($model);
unset($projects);
unset($user);
unset($view);
