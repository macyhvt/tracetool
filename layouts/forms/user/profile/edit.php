<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$task   = $input->post->getCmd('task');
$format = $input->getCmd('format') ?? ($input->post->getCmd('format') ?? null);

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

// TODO - Implement ACL and make calculate editor-right from ACL
// $canAdd = true;
?>
<?php /* Process form data */
if (!empty($_POST)) :
	switch ($task) :
		case 'genpw' :
			if (mb_strtolower($format) === 'json') :
				echo $view->genPasswordJSON();
				exit;
			else :
				$view->genPassword();
			endif;
		break;

		case 'editProfile' :
			$view->saveEditProfile();
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
$item         = $view->get('item');
$isArchived   = $item->get('archived') == '1';
$isBlocked    = $item->get('blocked')  == '1';
$isDeleted    = $item->get('trashed')  == '1';

$organisation = $model->getInstance('organisation', ['language' => $lang])->getItem((int) $item->get('orgID'));
$langs        = $model->getInstance('languages',    ['language' => $lang])->getList(true);
$itemLangs    = (array) $item->get('languages', []);
$itemLangs    = array_keys($itemLangs);

// Init tabindex
$tabindex  = 0;
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

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=profile.edit', $lang ))); ?>"
      method="post"
	  name="editProfileForm"
	  class="form-horizontal form-signin userForm validate"
	  id="editProfileForm"
	  enctype="multipart/form-data"
	  data-submit=""
	  data-monitor-changes="true"
>
	<input type="hidden" name="user"   value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="uid"    value="<?php echo $item->get('userID'); ?>" />
	<input type="hidden" name="xid"    value="<?php echo $item->get('userID'); ?>" />
	<input type="hidden" name="lng"    value="<?php echo $lang; ?>" />
	<input type="hidden" name="lngID"  value="<?php echo (new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
	<input type="hidden" name="task"   value="editProfile" />
	<input type="hidden" name="return" value="<?php echo base64_encode( UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=profile.edit&uid=%d', $lang, $item->get('userID') )))); ?>" />

	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=profile', $lang, $item->get('userID') ))); ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?></span>
	</a>
	
	<h1 class="h3 d-inline-block mr-3"><?php echo Text::translate('COM_FTK_LINK_TITLE_USER_PROFILE_EDIT_LABEL', $lang); ?></h1>
	
	<button type="submit"
			form="editProfileForm"
			name="action"
			value="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
			title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="fas fa-save"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $lang); ?></span>
	</button>
    <a class="btn btn-info btn-save" style="position: relative;bottom: 8px;left: 5px;" id="printData" href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=printbarcode', $this->language ))); ?>">Generate Login Code</a>

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $lang]); ?>
	<?php endif; ?>

	<hr>

	<fieldset name="">
		<legend class="h4 mb-4 pt-lg-2"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?></legend>
		<div class="row form-group">
			<label for="company" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $lang); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="company"
					   class="form-control"
					   value="<?php echo html_entity_decode($organisation->get('name')); ?>"
					   required
					   readonly
					   tabindex="<?php echo ++$tabindex; ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="fullname" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_FULL_NAME_TEXT', $lang); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="text"
					   name="fullname"
					   class="form-control"
					   minlength="3"
					   maxlength="50"
					   value="<?php echo html_entity_decode($item->get('fullname')); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $lang); ?>"
					   required
					   autofocus
					   tabindex="<?php echo ++$tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $lang); ?>"
					   data-rule-minlength="3"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $lang); ?>"
					   data-rule-maxlength="50"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $lang); ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="email" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_TEXT', $lang); ?>:&nbsp;&ast;</label>
			<div class="col">
				<?php if ($item->isCustomer()) : ?>
				<input type="email"
					   name="email"
					   class="form-control"
					   minlength="5"
					   maxlength="50"
					   value="<?php echo html_entity_decode($item->get('email')); ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $lang); ?>"
					   required
					   tabindex="<?php echo ++$tabindex; ?>"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $lang); ?>"
					   data-rule-minlength="5"
					   data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_SHORT_TEXT', $lang); ?>"
					   data-rule-maxlength="50"
					   data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_INPUT_TOO_LONG_TEXT', $lang); ?>"
				/>
				<?php else : ?>
				<input type="email"
					   name="email"
					   class="form-control"
					   value="<?php echo html_entity_decode($item->get('email')); ?>"
					   required
					   readonly
					   tabindex="<?php echo ++$tabindex; ?>"
				/>
				<?php endif; ?>
			</div>
		</div>
		<div class="row form-group">
			<label for="password" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $lang); ?>:</label>
			<div class="col col-lg-7">
				<input type="text"
					   name="password"
					   class="form-control bg-white"
					   id="password"
					   minlength="<?php echo FTKPARAM_PASSWORD_MIN_LENGTH; ?>"
					   maxlength="<?php echo FTKPARAM_PASSWORD_MAX_LENGTH; ?>"
					   pattern="<?php echo FTKREGEX_PASSWORD; ?>"
					   placeholder="<?php echo Text::translate('COM_FTK_HINT_ONLY_FILL_IN_TO_CHANGE_TEXT', $lang); ?>"

					   aria-live="polite"
					   tabindex="<?php echo ++$tabindex; ?>"
					   data-rule-minlength="<?php echo FTKPARAM_PASSWORD_MIN_LENGTH; ?>"
					   data-msg-minlength="<?php echo sprintf(Text::translate('COM_FTK_HINT_PASSWORD_TOO_SHORT_TEXT', $lang), FTKPARAM_PASSWORD_MIN_LENGTH); ?>"
					   data-rule-maxlength="<?php echo FTKPARAM_PASSWORD_MAX_LENGTH; ?>"
					   data-msg-maxlength="<?php echo sprintf(Text::translate('COM_FTK_HINT_PASSWORD_TOO_LONG_TEXT', $lang), FTKPARAM_PASSWORD_MAX_LENGTH); ?>"
				/><?php // content will be updated with Javascript ?>
			</div>
			<div class="col col-lg-3 position-relative">
				 <button type="submit"
						 name="task"
						 form="editProfileForm"
						 value="genpw"
						 class="btn btn-info btn-block btn-pwgen"
						 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CREATE_NEW_PASSWORD_TEXT', $lang); ?>"
						 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CREATE_NEW_PASSWORD_TEXT', $lang); ?>"
						 tabindex="<?php echo ++$tabindex; ?>"
						 data-toggle="generatePassword"
						 data-target="#password"
				>
					<i class="fas fa-sync-alt mr-2"></i>
					<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CHANGE_PASSWORD_TEXT', $lang); ?>
				 </button>
			</div>
		</div>
		<div class="row form-group">
			<label for="languages" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LANGUAGE_TEXT', $lang); ?>:&nbsp;&ast;</label>
			<div class="col">
				<select name="languages[]"
						class="form-control custom-select"
						id="inputLanguages"
						size="<?php echo is_countable($langs) ? count($langs) : 4; ?>"
						multiple
						required
						data-lang="<?php echo $lang; ?>"
						tabindex="<?php echo ++$tabindex; ?>"
						data-rule-required="true"
						data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $lang); ?>"
				>
				<?php array_walk($langs, function($lng) use($itemLangs, &$lang) { ?>
					<?php $lng = new Registry($lng); ?>
					<option value="<?php echo $lng->get('lngID'); ?>"<?php echo in_array($lng->get('lngID'), $itemLangs) ? ' selected' : '';?>><?php
						echo Text::translate($lng->get('link.text'), $lang);
					?></option>
				<?php }); ?>
				</select>
			</div>
		</div>
	</fieldset>

	<?php if (FALSE) : ?>
	<fieldset name="profile">
		<legend class="h4 mb-4 pt-lg-2"></legend>
	</fieldset>
	<?php endif; ?>
</form>
<script>

    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("printData").style.backgroundColor = "grey";
        document.getElementById("printData").addEventListener("click", function(event) {
            event.preventDefault();

            var formData = new FormData(document.getElementById("editProfileForm"));
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

                var url = "<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=user&layout=printbarcode', $this->language))); ?>" + "&parsecode=" + encodeURIComponent("<?php echo $printbarstr;?>") + "&name=" + encodeURIComponent(name) + "&email=" + encodeURIComponent(email) + "&org=" + encodeURIComponent(org) + "&pass=" + encodeURIComponent(pass);
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