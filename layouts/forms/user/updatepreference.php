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
		case 'editPreference' :
            //echo "<pre>";print_r($_POST);
            //echo "yes right";exit;
			$view->editPreference();
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
$usrTid = $model->getInstance('part', ['language' => $lang])->getuserTrack((int) $item->get('userID'));
// Init tabindex
$tabindex  = 0;
?>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=updatepreference', $lang ))); ?>"
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
	<input type="hidden" name="task"   value="editPreference" />
    <input type="hidden" name="backtopre" value="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=preference', $lang )));?>">
	<input type="hidden" name="return" value="<?php echo base64_encode( UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=profile&uid=%d', $lang, $item->get('userID') )))); ?>" />
    <input type="hidden" name="trackjump" value="0">

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
	
	<h1 class="h3 d-inline-block mr-3"><?php echo Text::translate('COM_FTK_LABEL_USERCHANGE_SETTING_TEXT', $lang); ?></h1>
	
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

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $lang]); ?>
	<?php endif; ?>

	<hr>

	<fieldset name="">

		<div class="row form-group">
			<label for="trackjump" class="col col-form-label col-md-8"><?php echo Text::translate('COM_FTK_LABEL_USERSETTING_CONSENT_TEXT', $lang); ?>:</label>
			<div class="col">
				<input type="checkbox"
					   name="trackjump"
					   class="form-control"
                       value="1"
                        <?php echo ($usrTid[0]['trackjump'] == 1) ? 'checked' : ''; ?>
                       tabindex="<?php echo ++$tabindex; ?>"
                       onclick="updateHiddenInput(this);"
				/>
			</div>
		</div>

	</fieldset>

	<?php if (FALSE) : ?>
	<fieldset name="profile">
		<legend class="h4 mb-4 pt-lg-2"></legend>
	</fieldset>
	<?php endif; ?>
</form>
<style>
    .form-control{
        width: 5%;
    }
    .form-control:focus{
        border: unset;
        border-color: unset;
        box-shadow: unset;
    }
</style>
<script>
    function updateHiddenInput(checkbox) {
        // Set the value of the hidden input to 1 when the checkbox is checked, and 0 otherwise
        document.getElementsByName("trackjump")[0].value = checkbox.checked ? 1 : 0;
    }
</script>