<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');
$userID   = $user->get('userID');
$orgID    = $user->get('orgID');

$list     = (array) $view->get('list');
$filter   = $input->getString('filter', (string) ListModel::FILTER_ACTIVE);
$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task')  ?? ($input->getCmd('task') ?? null);

$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);
?>
<?php /* Access check */
// TODO - Restrict access to Admins only!!! (SuperAdmin, OrgAdmin, GroupAdmin, etc.)
if (!$user->get('orgID') == '1' || ($user->get('orgID') == '1' && (!$user->isAdministrator() && !$user->isProgrammer() && !$user->isSuperuser()))) :
	Messager::setMessage([
		'type' => 'notice',
		'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_VIEW_NOT_FOUND_TEXT', $this->language)
	]);

	if (!headers_sent($filename, $linenum)) :
		header('Location: index.php?hl=' . $this->language);
		exit;
	endif;

	return false;
endif;
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		case 'delete' :
		// case 'recover' :		// NOT ALLOWED !!!
			$view->saveDeletion();
		break;

		case 'window.close' :
			echo '<script>window.close();</script>';
		break;
	endswitch;

	// Get updated process list
	// $list = $model->getInstance($view->get('name'), ['language' => $this->language])->getList();
else :
	// FIXME limit access to all vs. organisation specific users like for organisations.
	/*if ($user->isProgrammer() || $user->isSuperuser()) :
		// $list = $model->getList();
	else*/
	if ($user->isAdministrator()) :
//		$list = $model->getInstance('organisation', ['language' => $this->language])->getOrganisationUsers__OBSOLETE($user->get('orgID'));
		$list = $model->getInstance('organisation', ['language' => $this->language])->getOrganisationUsers([
			'orgID'  => $user->get('orgID'),    // FIXME - getPrimaryKeyName
			'filter' => $filter,
		]);
	else :
		$list = [];
	endif;

	// Delete form data from user session as it is not required any more.
	$user->__unset('formData');
endif;

// Preload complete organisations list for the user creation dropdown widget below.
$organisations = $model->getInstance('organisations', ['language' => $this->language])->getList([
	'filter' => ListModel::FILTER_ALL
]);

$cnt = count($list);

// Init tabindex
$tabindex = 0;
?>
<?php /* Calculate page heading */
$viewTitle = Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $this->language);

if (strlen($input->getString('filter'))) :
	switch ($input->getString('filter')) :
		case (ListModel::FILTER_ALL) :
			$viewTitle = sprintf('%s %s',   Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language), $viewTitle);
		break;

		case (ListModel::FILTER_ACTIVE) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_ACTIVE_TEXT',   $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;

		case (ListModel::FILTER_ARCHIVED) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_ARCHIVED_TEXT', $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;

		case (ListModel::FILTER_DELETED) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_DELETED_TEXT',  $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;

		case (ListModel::FILTER_LOCKED) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_LOCKED_TEXT',   $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;
	endswitch;
endif;
?>

<style>
#users-list th,
#users-list td {
	vertical-align: inherit;	/* required to align text with buttons vertically centered */
}
</style>

<h1 class="h3 viewTitle d-inline-block my-0 mr-3">
	<?php echo $viewTitle; ?>
	<?php echo ($cnt ? sprintf('<small class="text-muted ml-2">(%d)</small>', $cnt) : ''); ?>
</h1>

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
				style="vertical-align:super; padding:0.275rem 0.7rem; opacity:0.4"
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
<?php $modalForm = [];
$modalForm[] = LayoutHelper::render('system.alert.info', [
	'message' => Text::translate('COM_FTK_HINT_SELECT_CORRESPONDING_ORGANISATION_TEXT', $this->language),
	'attribs' => [
		'class' => 'alert-sm'
	]
],
['language'   => $this->language]
);
$modalForm[] = '<select name="oid" class="form-control selectUserOrganisation" data-toggle="openOrganisationUsers" data-rule-required="true" data-msg-required="' . 
					Text::translate('COM_FTK_HINT_MAKE_A_SELECTION_TEXT', $this->language) . '" required' .
				'>';
$modalForm[] = 	'<option value="">&ndash; ' . Text::translate('COM_FTK_LIST_OPTION_PLEASE_SELECT_TEXT', $this->language) . ' &ndash;</option>';

foreach ($organisations as $orgID => $organisation) :
	$modalForm[] = '<option value="' . UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=organisation&layout=user_add&oid=%d', $this->language, $orgID ) ) ) . '">';
	$modalForm[] = 	ArrayHelper::getValue($organisation, 'name', Text::translate('COM_FTK_NA_TEXT', $this->language), 'STRING');
	$modalForm[] = '</option>';
endforeach;

$modalForm[] = '</select>';
?>
<div class="d-inline-block align-top">
	<div class="input-group">
		<div class="input-group-prepend">
			<span class="input-group-text">
				<i class="fas fa-plus text-success"></i>
			</span>
		</div>
		<?php if ($user->isProgrammer() || $user->isSuperuser()) : // Creation is permitted to admins only ?>
		<button type="button"
				class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
				title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_CREATE_TEXT', $this->language); ?>"
				data-toggle="modal"
				data-size="md"
				data-backdrop="static"
				data-target="#mainModal"
				data-modal-title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_CREATE_TEXT', $this->language); ?>"
				data-modal-content="<?php echo base64_encode(implode($modalForm)); ?>"
				data-modal-submittable="false"
				aria-haspopup="true"
				style="padding:0.375rem 0.8rem"
		>
			<span class="btn-text d-none d-md-inline ml-md-1 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
				echo Text::translate('COM_FTK_BUTTON_TEXT_USER_CREATE_TEXT', $this->language);
			?></span>
		</button>
		<?php else : ?>
		<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=organisation&layout=users&oid=%d', $this->language, $user->get('orgID') ))); ?>"
		   class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
		   role="button"
		   title="<?php echo Text::translate('COM_FTK_LABEL_USER_ADD_TEXT', $this->language); ?>"
		   style="padding:0.375rem 0.8rem"
		>
			<span class="d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_USER_ADD_TEXT', $this->language); ?></span>
		</a>
		<?php endif; ?>
	</div>
</div>

<hr>

<?php // Users list ?>
<?php if (is_array($list)) : ?>
<div class="form-horizontal">
	<table class="table table-sm" id="<?php echo $view->get('name'); ?>-list">
		<thead class="thead-dark">
			<tr>
				<?php if (FALSE) : // Disabled because not useful and misleading ?><th scope="col"><span class="pl-2">#</span></th><?php endif; ?>
				<th scope="col" class="text-center"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->language); ?></th>
				<th scope="col" style="max-width:40%; min-width:25%; width:28%"><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $this->language); ?></th>
				<th scope="col" class="d-none d-md-table-cell"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_TEXT', $this->language); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_LAST_VISIT_TEXT', $this->language); ?></th>
				<th scope="col"></th>
			</tr>
		</thead>
		<tbody>
			<?php $i = 1; foreach ($list as $userID => $u) : ?><?php
					$item = new Registry($u);

					$itemOrganisation = ArrayHelper::getValue($organisations, $item->get('orgID'), new stdclass);
					$itemOrganisation = new Registry($itemOrganisation);

					$lastVisitDate = $item->get('lastVisitDate', FTKRULE_NULLDATE);
					$lastVisitDate = ($lastVisitDate == FTKRULE_NULLDATE) ? null : $lastVisitDate;
					$lastVisitDate = (is_a($lastVisitDate, 'DateTime') ?
										$lastVisitDate : (is_string($lastVisitDate) ?
											(new DateTime($lastVisitDate, new DateTimeZone(FTKRULE_TIMEZONE))) : null));
			?>
			<tr class="list-item<?php echo ($item->get('trashed') ? ' list-item-hidden d-none' : ''); ?>">
				<?php if (FALSE) : // Disabled because not useful and misleading ?><td scope="row"><?php echo $i < 10 ? "0$i" : $i; ?></td><?php endif; ?>
				<td class="text-center">
					<?php if (FALSE) : ?>
					<a href="<?php echo $view->getRoute(); ?>&task=<?php echo $item->get('blocked') ? 'unlock' : 'lock'; ?>&oid=<?php echo (int) $itemOrganisation->get('orgID'); ?>&uid=<?php echo (int) $item->get('userID'); ?>"
					   role="button"
					   class="btn btn-sm btn-lock"
					   title="<?php echo Text::translate('COM_FTK_LABEL_USER_' . ($item->get('blocked') ? 'UNLOCK' : 'LOCK') . '_THIS_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
					   onclick="return confirm('<?php echo Text::translate('COM_FTK_LABEL_USER_CONFIRM_' . ($item->get('blocked') ? 'UNLOCK' : 'LOCK') . '_TEXT', $this->language); ?>')"
					>
						<i class="fas fa-lock<?php echo $item->get('blocked') ? '' : '-open'; ?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('blocked') ? 'BLOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>"></span>
					</a>
					<?php else : ?>
					<span class="btn btn-sm btn-lock"
					      data-toggle="tooltip"
						  title="<?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('blocked') ? 'LOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>"
					>
						<i class="fas fa-lock<?php echo $item->get('blocked') ? '' : '-open'; ?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('blocked') ? 'LOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?></span>
					</span>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=organisation&layout=user_item&oid=%d&uid=%d', $this->language, $itemOrganisation->get('orgID'), $item->get('userID') ))); ?>"
					   class="text-link"
					   title="<?php echo Text::translate('COM_FTK_LABEL_USER_DETAILS_TEXT', $this->language); ?>"
					><?php echo html_entity_decode($item->get('fullname')); ?></a>
				</td>
				<td><?php echo html_entity_decode($itemOrganisation->get('name')); ?></td>
				<td class="d-none d-md-table-cell"><?php echo html_entity_decode($item->get('email')); ?></td>
				<td><?php echo (is_null($lastVisitDate) ? '&ndash;' : $lastVisitDate->format('d.m.Y H:i')); ?></td>
				<?php if ($user->isAdministrator() || $user->isProgrammer() || $user->isSuperuser()) : // Manage user accounts is permitted to admins+ only ?>
				<td class="text-right">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=organisation&layout=user_edit&oid=%d&uid=%d', $this->language, $itemOrganisation->get('orgID'), $item->get('userID') ))); ?>"
					   role="button"
					   class="btn btn-sm btn-edit btn-link float-right"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?>"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?></span>
					</a>
				</td>
				<?php endif; ?>
			</tr>
			<?php $i += 1; endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<?php // Free memory
unset($list);
unset($itemOrganisation);
unset($organisations);
?>
