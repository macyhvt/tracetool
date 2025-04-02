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
?>

<div class="form-horizontal form-signin userForm validate" id="editProfileForm">
	<h1 class="h3 d-inline-block mr-3"><?php echo Text::translate('COM_FTK_LABEL_USER_PROFILE_LABEL', $lang); ?></h1>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=user&layout=profile.edit&uid=%d', $lang, $item->get('userID') ))); ?>"
	   role="button"
	   class="btn btn-info"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_PROFILE_EDIT_LABEL', $lang); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_USER_PROFILE_EDIT_LABEL', $lang); ?>"
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
					   value="<?php echo html_entity_decode($item->get('fullname')); ?>"
					   required
					   readonly
					   tabindex="<?php echo ++$tabindex; ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="email" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_SHORT_TEXT', $lang); ?>:&nbsp;&ast;</label>
			<div class="col">
				<input type="email"
					   name="email"
					   class="form-control"
					   value="<?php echo html_entity_decode($item->get('email')); ?>"
					   required
					   readonly
					   tabindex="<?php echo ++$tabindex; ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="password" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $lang); ?>:</label>
			<div class="col">
				<input type="text"
					   name="password"
					   class="form-control"
					   value="<?php echo str_repeat('*', 50); ?>"
					   required
					   readonly
					   tabindex="<?php echo ++$tabindex; ?>"
				/>
			</div>
		</div>
		<div class="row form-group">
			<label for="languages" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_LANGUAGE_TEXT', $lang); ?>:&nbsp;&ast;</label>
			<div class="col">
				<select name="languages[]"
						class="form-control"
						id="inputLanguages"
						size="<?php echo is_countable($langs) ? count($langs) : 4; ?>"
						multiple
						required
						readonly
						tabindex="<?php echo ++$tabindex; ?>"
				>
				<?php array_walk($langs, function($lng) use($itemLangs, &$lang) { ?> 
					<?php $lng = new Registry($lng); ?>
					<option value="<?php echo $lng->get('lngID'); ?>"<?php echo in_array($lng->get('lngID'), $itemLangs) ? ' selected' : ''; ?>><?php
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
</div>
