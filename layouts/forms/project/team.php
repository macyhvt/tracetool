<?php
// Register required libraries.
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$proID    = $input->getInt('proid');
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
if (!is_null($task)) :
	switch ($task) :
		case 'deleteMember' :
			$view->saveDeleteMember();
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing project.
if (!is_a($item, 'Nematrack\Entity\Project') || (is_a($item, 'Nematrack\Entity\Project') && is_null($item->get('proID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PROJECT_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $proID)
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('projects', ['language' => $this->language])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->item       = $item;
$this->user       = $user;
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';

$members = $model->getProjectMembers($item->get('proID'));
?>

<style>
#projOrganisations th,
#projOrganisations td {
	vertical-align: inherit;	/* required to align text with buttons vertically centered */
}
</style>

<div class="form-horizontal">
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=item&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?></span>
	</a>
	<h1 class="h3 d-inline-block mr-3"><?php
		echo ucfirst(
			sprintf(
				'%s:<span class="small ml-3">%s</span>',
				Text::translate('COM_FTK_LABEL_TEAM_TEXT', $this->language),
				html_entity_decode($item->get('name'))
			)
		);
	?></h1>
	<?php /* CREATE/EDIT Member */ ?>
	<?php if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=team.members&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); ?>"
	   role="button"
	   class="btn btn-info"
	   title="<?php echo Text::translate('COM_FTK_LABEL_TEAM_' . (count($members) ? 'EDIT' : 'CREATE') . '_TEXT', $this->language); ?>"
	   style="vertical-align:super"
	>
		<i class="fas fa-pencil-alt"></i>
		<span class="d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_TEAM_' . (count($members) ? 'EDIT' : 'CREATE') . '_TEXT', $this->language); ?></span>
	</a>
	<?php endif; //-> END: !isArchived && !isBlocked && !isDeleted ?>

	<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php if (is_countable($members) && count($members)) : ?>
	<table class="table table-sm" id="projOrganisations">
		<thead class="thead-dark">
			<tr>
				<th scope="col"><span class="pl-2">#</span></th>
				<th scope="col" class="text-center"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->language); ?></th>
				<?php if (!$this->user->isCustomer()) : ?>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_ROLE_TEXT', $this->language); ?></th>
				<?php endif; ?>
				<th scope="col"></th>
			</tr>
		</thead>
		<tbody>
			<?php $i = 1; ?>
			<?php foreach ($members as $organisation) : ?>
			<tr>
				<td scope="row">
					<span class="pl-1"><?php echo ($i < 10 ? "0$i" : $i); ?></span>
				</td>
				<td class="text-center" data-toggle="tooltip" title="<?php echo Text::translate('COM_FTK_STATUS_' . ($organisation->get('blocked') ? 'BLOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>">
					<span class="btn-lock">
						<i class="fas fa-lock<?php echo ($organisation->blocked ? '' : '-open'); ?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($organisation->get('blocked') ? 'BLOCKED' : 'ACTIVE') . '_TEXT', $this->language); ?>"></span>
					</span>
				</td>
				<td>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=item&oid=%d', $this->language, $organisation->get('orgID') ))); ?>"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROJECT_VIEW_THIS_TEXT', $this->language); ?>"
					><?php echo html_entity_decode($organisation->get('name')); ?></a>
				</td>
				<?php if (!$this->user->isCustomer()) : ?>
				<td><?php echo html_entity_decode($organisation->get('role')->get('name')); ?></td>
				<?php endif; ?>
				<td class="text-right">
					<?php // FIXME - Task not implemented ?>
					<?php if (!$this->isDeleted && !$this->isBlocked && $this->user->getFlags() >= User::ROLE_MANAGER) : // Deletion is permitted to privileged users only ?>
					<form method="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=team&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); ?>"
						  method="post"
						  name="deleteMemberForm-<?php echo $i; ?>"
						  class="d-inline"
						  id="deleteMemberForm-<?php echo $i; ?>"
						  data-submit=""
					>
						<input type="hidden" name="user"   value="<?php echo (int) $this->user->get('userID'); ?>" />
						<input type="hidden" name="proid"  value="<?php echo (int) $item->get('proID'); ?>" />
						<input type="hidden" name="task"   value="deleteMember">
						<input type="hidden" name="oid"    value="<?php echo (int) $organisation->get('orgID'); ?>">
						<?php if (TRUE) : ?>
						<input type="hidden" name="return" value="<?php echo base64_encode('index.php?hl=' . $this->language . '&view=project&layout=team&proid=' . (int) $item->get('proID')); ?>" />
						<?php endif; ?>

						<button type="submit"
								class="btn btn-sm btn-link btn-trashbin"
								form="deleteMemberForm-<?php echo $i; ?>"
								title="<?php echo Text::translate('COM_FTK_LINK_TITLE_MEMBER_DELETE_THIS_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_MEMBER_DELETE_THIS_TEXT', $this->language); ?>"
								onclick="return confirm('<?php echo Text::translate('COM_FTK_DIALOG_TEAM_MEMBER_CONFIRM_DELETION_TEXT', $this->language); ?>')"
						>
							<i class="far fa-trash-alt"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_MEMBER_DELETE_TEXT', $this->language); ?></span>
						</button>
					</form>
					<?php endif; ?>
				</td>
			</tr>
			<?php $i += 1; endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
		<?php echo LayoutHelper::render('system.alert.notice', ['message' => Text::translate('COM_FTK_HINT_PROJECT_HAS_NO_TEAM_TEXT', $this->language)]); ?>
	<?php endif; ?>
</div>

<?php // Free memory
unset($input);
unset($item);
unset($lang);
unset($members);
unset($model);
unset($user);
unset($view);
