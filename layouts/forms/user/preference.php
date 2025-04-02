<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
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
?>
<?php /* Load view data */
$item         = $view->get('item');
$isArchived   = $item->get('archived') == '1';
$isBlocked    = $item->get('blocked')  == '1';
$isDeleted    = $item->get('trashed')  == '1';

$organisation = $model->getInstance('organisation', ['language' => $lang])->getItem((int) $item->get('orgID'));
$langs        = $model->getInstance('languages', ['language' => $lang])->getList(true);
$itemLangs    = (array) $item->get('languages', []);
$itemLangs    = array_keys($itemLangs);

// Init tabindex
$tabindex  = 0;
$usrTid = $model->getInstance('part', ['language' => $lang])->getuserTrack((int) $item->get('userID'));
//echo "<pre>";print_r($usrss);
//echo $_GET['message'];
$msgurl = $input->get('message');
?>
<?php if(isset($msgurl) && $msgurl =="prefupdate"):?>
<div class="alert alert-success" role="alert">
    <?php echo Text::translate('COM_FTK_LABEL_USER_SETTINGUPDATED_TEXT', $lang); ?>
</div>
<script>
    if (document.querySelector('.alert')) {
        document.querySelectorAll('.alert').forEach(function($el) {
            setTimeout(function(){
                document.querySelector('.alert').style.display = "none"

            }, 3000);
        });
    }
</script>
<?php endif; ?>
<div class="form-horizontal form-signin userForm validate" id="editProfileForm">
	<h1 class="h3 d-inline-block mr-3"><?php echo Text::translate('COM_FTK_LABEL_USERSETTING_TEXT', $lang); ?></h1>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=updatepreference&uid=%d', $lang, $item->get('userID') ))); ?>"
	   role="button"
	   class="btn btn-info"
	   title="<?php echo Text::translate('COM_FTK_LABEL_USERCHANGE_SETTING_TEXT', $lang); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LABEL_USERCHANGE_SETTING_TEXT', $lang); ?>"
	   style="vertical-align:super"
	>
		<i class="fas fa-pencil-alt"></i>
		<span class="d-none d-md-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $lang); ?></span>
	</a>

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $lang]); ?>
	<?php endif; ?>

	<hr>

	<fieldset name="">
		<legend class="h4 mb-4 pt-lg-2"></legend>

		<div class="row form-group">
			<label for="trackjump" class="col col-form-label col-md-8"><?php echo Text::translate('COM_FTK_LABEL_USERSETTING_CONSENT_TEXT', $lang); ?>:</label>
			<div class="col">
				<input type="checkbox"
					   name="trackjump"
					   class="form-control"
                    <?php echo ($usrTid[0]['trackjump'] == 1) ? 'checked' : ''; ?>
					   value="<?php echo html_entity_decode($item->get('trackjump')); ?>"
					   readonly
                       disabled
					   tabindex="<?php echo ++$tabindex; ?>"
				/>
			</div>
		</div>


	</fieldset>

	<?php if (FALSE) : ?>
	<fieldset name="profile">
		<legend class="h4 mb-4 pt-lg-2"></legend>
	</fieldset>
	<?php endif; ?>
</div>
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