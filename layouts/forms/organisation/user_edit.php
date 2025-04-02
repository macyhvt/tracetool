<?php
// Register required libraries.
use Joomla\Registry\Registry;
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
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$oid    = $input->getInt('oid');
$uid    = $input->getInt('uid');
$printbarstr = "COM_FTK_SYSTEM_MESSAGE_ACCESS_DENIED_TEXT_COM_FTK_SYSTEM_MESSAGE_MISSING_PRIVILEGE_TEXT";
$printbarstr = base64_encode($printbarstr);
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

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=default', $this->language ))); ?>"
      method="post"
	  name="editUserForm"
	  class="form form-horizontal userForm validate"
	  id="editUserForm"
	  data-submit=""
	  data-monitor-changes="true"
>
	<input type="hidden" name="oid"    value="<?php echo $this->item->get('orgID'); ?>" />
	<input type="hidden" name="user"   value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"    value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"  value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"   value="edit" />
	<input type="hidden" name="uid"    value="<?php echo (int) $xUser->get('userID'); ?>" />
	<input type="hidden" name="xid"    value="<?php echo (int) $xUser->get('userID'); ?>" />
	<input type="hidden" name="return" value="<?php echo base64_encode('index.php?hl=' . $this->language . '&view=organisation&layout=user_edit&oid=' . (int) $this->item->get('orgID') . '&uid=' . (int) $xUser->get('userID')); ?>" />

	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=user_item&oid=%d&uid=%d', $this->language, $this->item->get('orgID'), $xUser->get('userID') ))); ?>"
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
				Text::translate('COM_FTK_LABEL_USER_EDIT_TEXT', $this->language),
				html_entity_decode($this->item->get('name'))
			)
		);
	?></h1>
	<button type="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
	        title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="fas fa-save"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $this->language); ?></span>
	</button>
    <a class="btn btn-info btn-save" style="position: relative;bottom: 8px;left: 5px;" id="printData" href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=printbarcode', $this->language ))); ?>">Generate Login Code</a>
	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php // Input for organisation name ?>
	<div class="row form-group">
		<label for="company" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $this->language); ?>:&nbsp;&ast;</label>
		<div class="col">
			<input type="text"
				   name="company"
				   class="form-control"
				   value="<?php echo html_entity_decode($this->item->get('name')); ?>"
				   minlength="3"
				   maxlength="100"
				   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
				   required
				   readonly
				   tabindex="<?php echo ++$tabindex; ?>"
				   data-rule-required="true"
				   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
				   data-rule-minlength="3"
				   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				   data-rule-maxlength="100"
				   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
			/>
		</div>
	</div>

	<?php // Input for user full name ?>
	<div class="row form-group">
		<label for="fullname" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_FULL_NAME_TEXT', $this->language); ?>:&nbsp;&ast;</label>
		<div class="col">
			<input type="text"
				   name="fullname"
				   class="form-control"
				   minlength="3"
				   maxlength="50"
				   value="<?php echo html_entity_decode($xUser->get('fullname')); ?>"
				   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
				   required
				   autofocus
				   tabindex="<?php echo ++$tabindex; ?>"
				   data-rule-required="true"
				   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
				   data-rule-minlength="3"
				   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				   data-rule-maxlength="50"
				   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
			/>
		</div>
	</div>

	<?php // Input for user email ?>
	<div class="row form-group">
		<label for="email" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_TEXT', $this->language); ?>:&nbsp;&ast;</label>
		<div class="col">
			<input type="email"
				   name="email"
				   class="form-control"
				   minlength="5"
				   maxlength="50"
				   value="<?php echo html_entity_decode($xUser->get('email')); ?>"
				   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language); ?>"
				   required
				   tabindex="<?php echo ++$tabindex; ?>"
				   data-rule-required="true"
				   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
				   data-rule-minlength="5"
				   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $this->language); ?>"
				   data-rule-maxlength="50"
				   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $this->language); ?>"
			/>
		</div>
	</div>

	<?php // Input for user password ?>
	<div class="row form-group">
		<label for="password" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $this->language); ?>:</label>
		<div class="col col-lg-7">
			<input type="text"
				   name="password"
				   class="form-control<?php echo ($user->get('userID') != '1') ? ' nocp' : ''; ?>"
				   id="password"
				   minlength="<?php echo FTKPARAM_PASSWORD_MIN_LENGTH; ?>"
				   maxlength="<?php echo FTKPARAM_PASSWORD_MAX_LENGTH; ?>"
				   pattern="<?php echo FTKREGEX_PASSWORD; ?>"
				   placeholder="<?php echo Text::translate('COM_FTK_HINT_ONLY_FILL_IN_TO_CHANGE_TEXT', $this->language); ?>"

				   aria-live="polite"

				   tabindex="<?php echo ++$tabindex; ?>"
				   data-rule-minlength="<?php echo FTKPARAM_PASSWORD_MIN_LENGTH; ?>"
				   data-msg-minlength="<?php echo sprintf(Text::translate('COM_FTK_HINT_PASSWORD_TOO_SHORT_TEXT', $this->language), FTKPARAM_PASSWORD_MIN_LENGTH); ?>"
				   data-rule-maxlength="<?php echo FTKPARAM_PASSWORD_MAX_LENGTH; ?>"
				   data-msg-maxlength="<?php echo sprintf(Text::translate('COM_FTK_HINT_PASSWORD_TOO_LONG_TEXT', $this->language), FTKPARAM_PASSWORD_MAX_LENGTH); ?>"
			/><?php // content will be updated with Javascript ?>
		</div>
		<div class="col col-lg-3 position-relative">
			 <button type="submit"
					 name="task"
					 form="editUserForm"
					 value="genpw"
					 class="btn btn-info btn-block btn-pwgen"
					 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CREATE_NEW_PASSWORD_TEXT', $this->language); ?>"
					 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CREATE_NEW_PASSWORD_TEXT', $this->language); ?>"
					 tabindex="<?php echo ++$tabindex; ?>"
					 data-toggle="generatePassword"
					 data-target="#password"
			>
				<i class="fas fa-sync-alt mr-2"></i>
				<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CHANGE_PASSWORD_TEXT', $this->language); ?>
			 </button>
		</div>
	</div>

	<?php // Input for account status ?>
	<div class="row form-group">
		<label for="status" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language); ?>:&nbsp;&ast;</label>
		<div class="col">
			<select name="status"
					class="form-control"
					id="inputStatus"
					required
					data-lang="<?php echo $this->language; ?>"
					tabindex="<?php echo ++$tabindex; ?>"
					data-rule-required="true"
					data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
			>
				<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); ?> &ndash;</option>
				<option value="0"<?php echo ($xUser->get('blocked') == '0' ? ' selected' : ''); ?>><?php echo Text::translate('COM_FTK_STATUS_ACTIVE_TEXT', $this->language); ?></option>
				<option value="1"<?php echo ($xUser->get('blocked') == '1' ? ' selected' : ''); ?>><?php echo Text::translate('COM_FTK_STATUS_LOCKED_TEXT', $this->language); ?></option>
			</select>
		</div>
	</div>

	<?php // Input for user languages ?>
	<div class="row form-group">
		<label for="languages" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LANGUAGE_TEXT', $this->language); ?>:&nbsp;&ast;</label>
		<div class="col">
			<select name="languages[]"
					class="form-control"
					id="inputLanguages"
					size="<?php echo is_countable($this->languages) ? count($this->languages) : 4; ?>"
					multiple
					required
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
		<label for="groups" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ROLES_TEXT', $this->language); ?>:&nbsp;&ast;</label>
		<div class="col">
			<select name="groups[]"
					class="form-control"
					id="inputGroups"
					size="<?php echo is_countable($groups) ? count($groups) : 3; ?>"
					multiple
					required
					data-lang="<?php echo $this->language; ?>"
					tabindex="<?php echo ++$tabindex; ?>"
					data-rule-required="true"
					data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
			>
			<?php array_filter($groups, function($group) use(&$userGroups) { ?>
				<?php $group     = new Registry($group); ?>
				<?php $groupName = ucfirst(mb_strtolower($group->get('name'))); ?>
				<?php $selected  = ($group->get('flag') == '1') ? ' selected' : ((in_array($group->get('groupID'), $userGroups)) ? ' selected' : ''); ?>
				<option value="<?php echo $group->get('groupID'); ?>"<?php echo $selected; ?>><?php echo html_entity_decode($groupName); ?></option>
			<?php return true; }, ARRAY_FILTER_USE_BOTH); ?>
			</select>
		</div>
	</div>
</form>

    <script>

        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("printData").style.backgroundColor = "grey";
            document.getElementById("printData").addEventListener("click", function(event) {
                event.preventDefault();

                var formData = new FormData(document.getElementById("editUserForm"));
                var name = formData.get("fullname");
                var email = formData.get("email");
                var pass = formData.get("password");
                var org = formData.get("company");

                if(pass == ""){
                    document.getElementById("printData").disabled = true;
                    alert("Please Generate Password");
                }else {
                    document.getElementById("printData").style.backgroundColor = "#17a2b8";
                    var queryString = 'name=' + encodeURIComponent(name) + '&email=' + encodeURIComponent(email);

                    var url = "<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=organisation&layout=printbarcode', $this->language))); ?>" + "&parsecode=" + encodeURIComponent("<?php echo $printbarstr;?>") + "&name=" + encodeURIComponent(name) + "&email=" + encodeURIComponent(email) + "&org=" + encodeURIComponent(org) + "&pass=" + encodeURIComponent(pass) + "&uid=" + encodeURIComponent("<?php echo $uid;?>");
                    var newTab = window.open(url, '_blank');
                    if (newTab) {
                        newTab.focus();
                    } else {
                        alert('Please allow pop-ups for this site to open the link in a new tab.');
                    }
                    /*var anchorTag = document.getElementById("printData");
                    anchorTag.textContent = "Printing in process...";
                    window.location.href = url;*/
                }
            });
        });
        document.querySelector(".btn-pwgen").addEventListener("click", function(event) {
            document.getElementById("printData").style.backgroundColor = "#17a2b8";
        })
    </script>

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
