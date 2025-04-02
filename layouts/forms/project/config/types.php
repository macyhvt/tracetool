<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
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
$task   = $input->post->getCmd('task',    $input->getCmd('task'));
$format = $input->post->getWord('format', $input->getWord('format'));

if (!empty($_POST)) :
	if (!is_null($task)) :
		switch ($task) :
			case 'edit' :
				$view->saveEdit();
			break;
		endswitch;
	endif;
endif;
?>
<?php /* Load view data */
$item = $view->get('item');

$this->item = $item;
$this->user = $user;

// Init tabindex
$tabindex = 10;
?>

<style>
.nav-tabs {
	overflow: hidden;
}
.col-form-label,
.nav-tabs .nav-link {
	background-color: #e8edf3;
	border-color: #dee3e9 #dee3e9 #dee2e6;
	color: #30588B;
}
.nav-tabs .nav-link.active {
	background-color: #d9dee4;
	background-color: #d1e8ff;
	border-color: #cfd4da #cfd4da #dee2e6;
	color: #264E81;
}
.nav-tabs .nav-item > .nav-link.active {
	box-shadow: 0 0 6px 0 rgba(128, 128, 128, 0.5);
	box-shadow: 0 3px 9px 2px rgba(169, 192, 223, 0.8);
}
.nav-tabs .nav-item:first-of-type > .nav-link.active {
	box-shadow: 3px 0 6px -3px rgba(128, 128, 128, 0.5);
	box-shadow: 3px 3px 9px -1px rgba(169, 192, 223, 0.8);
}
.nav-tabs .nav-item:last-of-type > .nav-link.active {
	box-shadow: -3px 0 6px -3px rgba(128, 128, 128, 0.5);
	box-shadow: -3px 3px 9px -1px rgba(169, 192, 223, 0.8);
}

.form-control-file {
	line-height: 1rem;
}

.form-control-animated.animate-height {
	transition: height 1s ease-out;
}
.form-control-animated.animate-height:focus {
	height: 5rem;
	transition: height 1s ease-in;
}

@supports (-webkit-appearance:none) {
	.form-control-file {
		max-width: 132px;
	}
}
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=config.types&proid=%d', $lang, $view->get('name'), $this->item->get('proID') ))); ?>"
      method="post"
	  name="editProcessConfigForm"
	  class="form-horizontal"
	  id="editProcessConfigForm"
	  data-submit=""
>
	<input type="hidden" name="user"   value="<?php echo $this->user->get('userID'); ?>" />
	<input type="hidden" name="proid"  value="<?php echo (int) $this->item->get('proID'); ?>" />
	<input type="hidden" name="number" value="<?php $number = $this->item->get('number'); echo OutputFilter::cleanText($number); ?>" />
	<input type="hidden" name="lng"    value="<?php echo $lang; ?>" />
	<input type="hidden" name="lngID"  value="<?php echo (new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
	<input type="hidden" name="task"   value="edit" />
	<input type="hidden" name="return" value="<?php echo base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString())); ?>" />

	<a href="<?php echo $return; ?>"
	   role="button"
	   class="btn btn-link"
	   <?php if ((new Uri($return))->getVar('layout') == 'list') : ?>
	   data-bind="windowClose"
	   data-force-reload="true"
	   <?php endif; ?>
	   style="vertical-align:super; color:inherit!important"
	>
		<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
			  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
			  data-toggle="tooltip"
		>
			<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
			<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?></span>
		</span>
	</a>
	<h1 class="h3 d-inline-block mr-3"><?php
		echo ucfirst(
			sprintf(
				'%s:<span class="small ml-3">%s</span>',
				Text::translate('COM_FTK_LABEL_CONFIGURATION_TEXT', $lang),
				html_entity_decode($this->item->get('name'))
			)
		);
	?></h1>
	<button type="submit"
			form="editProcessConfigForm"
			name="action"
			value="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			  data-toggle="tooltip"
		>
			<i class="fas fa-save"></i>
			<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
				echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $lang);
			?></span>
		</span>
	</button>

	<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $lang]); ?>
	<?php endif; ?>

	<hr>

	<?php foreach ($view->get('statusTypes') as $abbr => $type) : ?>
	<div class="row ml-md-0">
		<div class="col">
			<div class="row form-group">
				<label for="config[type][<?php echo $abbr; ?>][abbr]" class="col col-form-label col-md-2"><?php echo $type->name; ?>:</label>
				<div class="col col-md-2 col-lg-1 mr-md-0">
					<input type="text"
						   value="<?php echo $abbr; ?>"
						   class="form-control"
						   readonly
						   disabled
					>
				</div>
				<div class="col">
					<textarea class="form-control form-control-animated animate-height"
						   rows="1"
						   cols="10"
						   readonly
					><?php echo $type->description; ?></textarea>
				</div>
				<label for="config[type][<?php echo $abbr; ?>][spreading]"
					   class="col col-form-label col-md-2 text-md-right"
					   title="<?php echo Text::translate('COM_FTK_LABEL_SPREADING_FACTOR_DESC', $lang); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LABEL_SPREADING_FACTOR_DESC', $lang); ?>"
					   data-toggle="tooltip"
				><?php
					echo Text::translate('COM_FTK_LABEL_SPREADING_FACTOR_TEXT', $lang);
				?>:</label>
				<div class="col col-md-2">
					<input type="number"
						   name="config[factors][<?php echo $abbr; ?>]"
						   class="form-control"
						   placeholder="0 ... 9"
						   min="1"
						   max="10"
						   step="0.05"
						   value="<?php echo $type->factor; ?>"
						   title="<?php echo Text::translate('COM_FTK_LABEL_SPREADING_FACTOR_DESC', $lang); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_LABEL_SPREADING_FACTOR_DESC', $lang); ?>"
						   data-bind="fixDecimal"
						   data-toggle="tooltip"
						   data-trigger="hover focus"
						   data-placement="right"
						   tabindex="<?php echo ++$tabindex; ?>"
					>
				</div>
			</div>
		</div>
	</div>
	<?php endforeach; ?>
</form>

<?php // Free memory
unset($input);
unset($item);
unset($langs);
unset($members);
unset($model);
unset($organisations);
unset($user);
unset($view);
