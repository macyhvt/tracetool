<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Entity\Organisation;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
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
$proID  = $input->getInt('proid');
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
$canCreate = true;
?>
<?php /* Process form data */
if (!empty($_POST)) :
	$view->saveAddProjectMembers();
endif;
?>
<?php /* Load view data */
$item = $view->get('item');

$members = array_filter($item->get('organisations', [], 'ARRAY'));

$organisations = $model->getInstance('organisations', ['language' => $this->language])->getList([
	'filter' => ListModel::FILTER_ACTIVE
]);
$languages     = $model->getInstance('languages', ['language' => $this->language])->getList(true);

$this->item      = $item;
$this->languages = $languages;
$this->user      = $user;
?>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=team.members&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); ?>"
      method="post"
	  name="addProjectMembersForm"
	  class="form-horizontal"
	  id="addProjectMembersForm"
	  data-submit=""
>
	<input type="hidden" name="user"   value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="proid"  value="<?php echo (int) $item->get('proID'); ?>" />
	<input type="hidden" name="lng"    value="<?php echo $this->language; ?>" />
	<input type="hidden" name="lngID"  value="<?php echo (new Registry($model->getInstance('language', ['language' => $this->language])->getLanguageByTag($this->language)))->get('lngID'); ?>" />
	<input type="hidden" name="task"   value="addProjectMembers" />
	<input type="hidden" name="return" value="<?php echo base64_encode( UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=project&layout=team&proid=%d', $this->language, $item->get('proID') )))); ?>" />

	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=team&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); ?>"
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
				Text::translate('COM_FTK_LABEL_PROJECT_TEAM_TEXT', $this->language),
				html_entity_decode($item->get('name'))
			)
		);
	?></h1>
	<button type="submit"
			form="addProjectMembersForm"
			name="action"
			value="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
			title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $this->language); ?>"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="fas fa-save"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $this->language); ?></span>
	</button>

	<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php $i = 0; ?>
	<div class="row">
	<?php array_walk($organisations, function(&$organisation) use(&$i, &$item, &$members) { ?>
	<?php 	$organisation = (new Organisation(['lang' => $this->language]))->bind($organisation); ?>
	<?php 	$isMember     = in_array(($orgID = $organisation->get('orgID')), $members); ?>
	<?php 	$address      = new Registry($organisation->get('address')); ?>
		<div class="col-sm-6 col-lg-3 mb-lg-4">
			<div class="card<?php echo ($isMember ? ' alert-success' : ''); ?>">
				<div class="card-body" style="overflow:hidden">
					<h5 class="h6 card-title d-inline-block" style="max-width:100%"><abbr data-toggle="tooltip" title="<?php echo html_entity_decode($organisation->get('name')); ?>" class="d-inline-block text-truncate" style="max-width:100%"><?php echo html_entity_decode($organisation->get('name')); ?></abbr></h5>
					<address class="card-text"><?php
						echo html_entity_decode($address->get('addressline')) . nl2br(PHP_EOL);
						echo sprintf('%s %s', html_entity_decode($address->get('zip')), html_entity_decode($address->get('city'))) . nl2br(PHP_EOL);
						echo html_entity_decode($address->get('country')) . nl2br(PHP_EOL);
					?></address>
					<div class="btn-group-toggle" data-toggle="buttons" data-parent=".card">
						<label class="btn btn-block btn-info btn-checkbox<?php echo ($isMember ? ' active' : ''); ?>"
							   role="button"
							   data-target="> .label"
							   data-label-unchecked="<?php echo Text::translate('COM_FTK_LABEL_ADD_TEXT', $this->language); ?>"
							   data-label-checked="<?php echo Text::translate('COM_FTK_LABEL_SELECTED_TEXT', $this->language); ?>"
						>
							<i class="fas <?php echo ($isMember ? ' fa-check' : 'fa-plus'); ?> mr-2" data-toggle="icon" data-icon-unchecked="fa-plus" data-icon-checked="fa-check"></i>
							<input type="checkbox" name="oids[]" value="<?php echo (int) $orgID; ?>" autocomplete="off"<?php echo ($isMember ? ' checked' : ''); ?>>
							<span class="label"><?php echo Text::translate(($isMember ? 'COM_FTK_LABEL_SELECTED_TEXT' : 'COM_FTK_LABEL_ADD_TEXT'), $this->language); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>
	<?php $i += 1; }); ?>
	</div>
</form>

<?php // Free memory
unset($input);
unset($item);
unset($lang);
unset($members);
unset($model);
unset($organisations);
unset($user);
unset($view);
?>
