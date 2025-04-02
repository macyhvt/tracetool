<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\App;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars  */
$lang      = $this->get('language');
$item      = $this->get('item', new Registry);
$user      = App::getAppUser();

$userModel = Model::getInstance('user', ['language' => $lang]);
$now       = \date_create('NOW');	// FIXME - get user timezone and/or date/time format config from user profile

$hide      = $this->get('hide', [], 'ARRAY');

$created   = $item->get('created',   $now);
$creator   = $userModel->getItem((int) $item->get('created_by'));
$edited    = $item->get('modified',  $now);
$editor    = $userModel->getItem((int) $item->get('modified_by'));
$isDeleted = $item->get('trashed', 0) == '1';
$deleted   = $item->get('deleted');
$deleter   = $userModel->getItem((int) $item->get('deleted_by'));

$isBlocked = $item->get('blocked', 0) == '1';
$blocked   = $item->get('blockDate', $now);
$blocker   = $userModel->getItem((int) $item->get('blocked_by'));
?>

<p class="text-muted mt-2 mt-lg-3" data-class="mb-lg-0 p-0 pb-1 pb-lg-0">
	<small class="text-muted">
	<?php if (isset($created)) : ?>
		<?php if (!in_array('created', $hide) && is_a($created, 'DateTime')) : ?>
			<?php // External users must only see dates. Names will be hidden to satisfy GDPR. ?>
			<?php if (!is_a($creator, 'Nematrack\Entity\User') || !$creator->get('userID') || ($user->isGuest() || $user->isCustomer() || $user->isSupplier())) : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_CREATION_DATE_TEXT', $lang), $created->format('d.m.Y'));  ?>
			<?php else : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_AUTHOR_CREATION_DATE_TEXT', $lang), $created->format('d.m.Y'), html_entity_decode($creator->get('fullname')));  ?>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (isset($edited)) : ?>
		<?php if (!in_array('edited', $hide) && is_a($edited, 'DateTime')) : ?><span class="d-block d-md-inline"><span class="d-none d-md-inline">&nbsp;|&nbsp;</span></span>
			<?php // External users must only see dates. Names will be hidden to satisfy GDPR. ?>
			<?php if (!is_a($editor, 'Nematrack\Entity\User') || !$editor->get('userID') || ($user->isGuest() || $user->isCustomer() || $user->isSupplier())) : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_EDIT_DATE_TEXT', $lang), $edited->format('d.m.Y'));  ?>
			<?php else : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_AUTHOR_EDITING_DATE_TEXT', $lang), $edited->format('d.m.Y'), html_entity_decode($editor->get('fullname')));  ?>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (isset($blocked) && true == $isBlocked) : ?>
		<?php if (!in_array('blocked', $hide) && is_a($blocked, 'DateTime')) : ?><span class="d-block d-md-inline"><span class="d-none d-md-inline">&nbsp;|&nbsp;</span></span>
			<?php // External users must only see dates. Names will be hidden to satisfy GDPR. ?>
			<?php if (!is_a($blocker, 'Nematrack\Entity\User') || !$blocker->get('userID') || ($user->isGuest() || $user->isCustomer() || $user->isSupplier())) : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_BLOCK_DATE_TEXT', $lang), $blocked->format('d.m.Y'));  ?>
			<?php else : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_AUTHOR_BLOCK_DATE_TEXT', $lang), $blocked->format('d.m.Y'), html_entity_decode($blocker->get('fullname')));  ?>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php // Seeing deleted content is permitted to non-external users only ?>
	<?php if (isset($deleted) && true == $isDeleted) : ?>
		<?php if (!in_array('deleted', $hide) && is_a($deleted, 'DateTime')) : ?><span class="d-block d-md-inline"><span class="d-none d-md-inline">&nbsp;|&nbsp;</span></span>
			<?php // External users must only see dates. Names will be hidden to satisfy GDPR. ?>
			<?php if (!is_a($deleter, 'Nematrack\Entity\User') || !$deleter->get('userID') || ($user->isGuest() || $user->isCustomer() || $user->isSupplier())) : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_DELETION_DATE_TEXT', $lang), $deleted->format('d.m.Y'));  ?>
			<?php else : ?>
				<?php echo sprintf(Text::translate('COM_FTK_LABEL_AUTHOR_DELETION_DATE_TEXT', $lang), $deleted->format('d.m.Y'), html_entity_decode($deleter->get('fullname')));  ?>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
	</small>
</p>

<?php // Free memory.
unset($blocked);
unset($blocker);
unset($created);
unset($creator);
unset($deleted);
unset($deleter);
unset($edited);
unset($editor);
unset($hide);
unset($item);
unset($now);
unset($user);
unset($userModel);
