<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
// $return = $view->getReturnPage();	// Browser back-link required for back-button.
$return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$oid    = $input->getInt('oid');
$uid    = $input->getInt('uid');
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

if ($user->getFlags() < User::ROLE_ADMINISTRATOR) : // Administration is permitted to admins only
	Messager::setMessage([
		'type' => 'notice',
		'text' => sprintf('%s! %s',
				Text::translate('COM_FTK_SYSTEM_MESSAGE_ACCESS_DENIED_TEXT', $this->language),
				Text::translate('COM_FTK_SYSTEM_MESSAGE_MISSING_PRIVILEGE_TEXT', $this->language)
		)
	]);

//	if (!headers_sent($filename, $linenum)) :
		header('Location: index.php?hl=' . $this->language);
		exit;
//	endif;

	return false;
endif;

// TODO - Implement ACL and make calculate editor-right from ACL
$canCreate = true;
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$item       = $view->get('item');

$xUser      = $model->getInstance('user', ['language' => $this->language])->getItem((int) $uid);
$xUserLangs = (array) $xUser->get('languages', []);
$xUserLangs = array_keys($xUserLangs);

// Block the attempt to access a non-existing user.
if (!is_a($xUser, 'Nematrack\Entity\User')) :
	Messager::setMessage([
		'type' => 'notice',
		'text' => sprintf(Text::translate('COM_FTK_HINT_USER_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $oid)
	]);

	if (!headers_sent($filename, $linenum)) :
		header('Location: index.php?hl=' . $this->language . '&view=organisations&layout=users');
		exit;
	endif;

	return false;
endif;

// Get previously generated new password from the user object.
$password = $user->get('newPassword');

$this->item      = $item;
$this->languages = $model->getInstance('languages', ['language' => $this->language])->getList(true);
$groups     = $model->getInstance('groups', ['language' => $this->language])->getList();
$userGroups = (array) $xUser->get('groups', []);
$userGroups = array_keys($userGroups);

// Init tabindex
$tabindex   = 0;
?>

<style>
.btn-codegen {}

.btn-codegen.loading + * {
	width: 100%;
	height: 100%;
	z-index: 2;
	padding-top: 7px;
	padding-left: 8px
}

.btn-codegen.loading + * > .fas {
	font-size: 150%;
}
</style>

<div class="form form-horizontal position-relative">
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=users&oid=%d', $this->language, $this->item->get('orgID') ))); ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?></span>
	</a>

	<h1 class="h3 d-inline-block mr-3"><?php
		echo ucfirst(
			sprintf(
				'%s:<span class="small ml-3">%s</span>',
				Text::translate('COM_FTK_LABEL_USER_TEXT', $this->language),
				html_entity_decode($this->item->get('name'))
			)
		);
	?></h1>

	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=user_edit&oid=%d&uid=%d', $this->language, $this->item->get('orgID'), $xUser->get('userID') ))); ?>"
	   role="button"
	   class="btn btn-info"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_EDIT_THIS_TEXT', $this->language); ?>"
	   style="vertical-align:super"
	>
		<i class="fas fa-pencil-alt"></i>
		<span class="d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language); ?></span>
	</a>

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php // Input for organisation name ?>
	<div class="row form-group">
		<label for="company" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $this->language); ?>:</label>
		<div class="col">
			<input type="text"
				   name="company"
				   class="form-control"
				   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
				   tabindex="<?php echo ++$tabindex; ?>"
				   readonly
				   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
			/>
		</div>
	</div>

	<?php // Input for user full name ?>
	<div class="row form-group">
		<label for="fullname" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_FULL_NAME_TEXT', $this->language); ?>:</label>
		<div class="col">
			<input type="text"
				   name="fullname"
				   class="form-control"
				   value="<?php echo html_entity_decode($xUser->get('fullname')); ?>"
				   tabindex="<?php echo ++$tabindex; ?>"
				   readonly
				   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
			/>
		</div>
	</div>

	<?php // Input for user email ?>
	<div class="row form-group">
		<label for="email" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_TEXT', $this->language); ?>:</label>
		<div class="col">
			<input type="email"
				   name="email"
				   class="form-control"
				   value="<?php echo html_entity_decode($xUser->get('email')); ?>"
				   tabindex="<?php echo ++$tabindex; ?>"
				   readonly
				   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
			/>
		</div>
	</div>

	<?php // Input for user password ?>
	<div class="row form-group">
		<label for="password" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $this->language); ?>:</label>
		<div class="col">
			<input type="text"
				   name="password"
				   class="form-control"
				   value="<?php echo str_repeat('*', 50); ?>"
				   tabindex="<?php echo ++$tabindex; ?>"
				   required
				   readonly
			/>
		</div>
	</div>

	<?php // Input for account status ?>
	<div class="row form-group">
		<label for="status" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?>:</label>
		<div class="col">
			<input type="text"
				   name="status"
				   class="form-control"
				   value="<?php echo Text::translate(($xUser->get('blocked') == '0' ? 'COM_FTK_STATUS_ACTIVE_TEXT' : 'COM_FTK_STATUS_LOCKED_TEXT'), $this->language); ?>"
				   tabindex="<?php echo ++$tabindex; ?>"
				   readonly
				   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
			/>
		</div>
	</div>

	<?php // Input for user languages ?>
	<div class="row form-group">
		<label for="languages" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LANGUAGE_TEXT', $this->language); ?>:</label>
		<div class="col">
			<select name="languages[]"
					class="form-control"
					id="inputLanguages"
					size="<?php echo is_countable($this->languages) ? count($this->languages) : 4; ?>"
					multiple
					readonly
					disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
					data-lang="<?php echo $this->language; ?>"
					tabindex="<?php echo ++$tabindex; ?>"
					data-rule-required="true"
					data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
			>
			<?php array_filter($this->languages, function($arr) use(&$xUserLangs) { ?>
				<?php $lang = new Registry($arr); ?>
				<option value="<?php echo $lang->get('lngID'); ?>"<?php echo in_array($lang->get('lngID'), $xUserLangs) ? ' selected' : ''; ?>><?php
					echo Text::translate($lang->get('link.text'), $this->language);
				?></option>
			<?php return true; }); ?>
			</select>
		</div>
	</div>

	<?php // Input for user groups ?>
	<div class="row form-group">
		<label for="groups" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ROLES_TEXT', $this->language); ?>:</label>
		<div class="col">
			<select name="groups[]"
					class="form-control"
					id="inputGroups"
					size="<?php echo is_countable($groups) ? count($groups) : 3; ?>"
					multiple
					readonly
					disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
					data-lang="<?php echo $this->language; ?>"
					tabindex="<?php echo ++$tabindex; ?>"
					data-rule-required="true"
					data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
			>
			<?php array_filter($groups, function($group) use(&$userGroups) { ?>
				<?php $group     = new Registry($group); ?>
				<?php $groupName = ucfirst(mb_strtolower($group->get('name'))); ?>
				<option value="<?php echo $group->get('groupID'); ?>"<?php echo in_array($group->get('groupID'), $userGroups) ? ' selected' : ''; ?>><?php echo html_entity_decode($groupName); ?></option>
			<?php return true; }, ARRAY_FILTER_USE_BOTH); ?>
			</select>
		</div>
	</div>
</div>

<?php // Unset previously generated new password from the user object, because it served filling out the form only.
$user->__set('newPassword', null);
?>

<?php // Free memory.
unset($input);
unset($item);
unset($model);
unset($projects);
unset($user);
unset($view);
unset($xUser);
