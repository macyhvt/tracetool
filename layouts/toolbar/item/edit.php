<?php
// Register required libraries.
use Joomla\Uri\Uri;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang      = $this->get('language');
$user      = $this->get('user');
$hide      = (array) $this->get('hide', []);
$buttons   = array_diff(['submit','submitAndClose','cancel'], $hide);
$formName  = $this->get('formName');
$backRoute = $this->get('backRoute');
?>

<?php /***   B A C K   -   B U T T O N   ***/ ?>
<?php if (in_array('back', $buttons)) : ?>
<a href="<?php echo $backRoute; ?>"
   role="button"
   class="btn btn-sm btn-cancel btn-link pl-0 pr-3"
   <?php if ((new Uri($backRoute))->getVar('layout') == 'list') : // The edit view may have been directly requested from the list view ?>
   data-bind="windowClose"
   data-force-reload="true"
   <?php endif; ?>
>
	<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
		  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
		  data-toggle="tooltip"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="btn-text sr-only"><?php
			echo Text::translate('COM_FTK_LINK_TEXT_BACK_TO_OVERVIEW_TEXT', $lang);
		?></span>
	</span>
</a>
<?php endif; ?>

<?php /***   H E A D L I N E   ***/ ?>
<?php if (!in_array('title', $hide)) : ?>
<h1 class="h3 viewTitle d-sm-inline-block mt-0 mb-sm-0 mr-sm-4 mr-md-5"><?php
	echo ucfirst(Text::translate(mb_strtoupper(sprintf('COM_FTK_HEADING_%s_%s_TEXT', $this->get('viewName'), $this->get('layoutName'))), $lang));
?></h1>
<?php endif; ?>

<?php /***   S A V E   -   B U T T O N   ***/ ?>
<?php if (in_array('submit', $buttons)) : ?>
<div class="d-inline-block align-top">
	<div class="input-group">
		<div class="input-group-prepend">
			<span class="input-group-text">
				<i class="fas fa-save"></i>
			</span>
		</div>
		<button type="submit"
				form="<?php echo $formName; ?>"
				name="button"
				value="submit"
				class="btn btn-sm btn-success btn-submit btn-save left-radius-0 pr-md-3 allow-window-unload"
		>
			<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
				  data-toggle="tooltip"
			>
				<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
					echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $lang) );
				?></span>
			</span>
		</button>
	</div>
</div>
<?php endif; ?>






<?php /***   S A V E  &  C L O S E   -   B U T T O N   ***/ ?>
<?php if (in_array('submitAndClose', $buttons)) : ?>
<div class="d-none d-lg-inline-block align-top ml-3">
	<div class="input-group">
		<div class="input-group-prepend">
			<span class="input-group-text">
				<i class="fas fa-check text-success"></i>
			</span>
		</div>
		<button type="submit"
				form="<?php echo $formName; ?>"
				name="button"
				value="submitAndClose"
				class="btn btn-sm btn-custom btn-submit btn-saveAndClose pr-md-3 allow-window-unload"
		>
			<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_AND_CLOSE_TEXT', $lang); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_AND_CLOSE_TEXT', $lang); ?>"
				  data-toggle="tooltip"
			>
				<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
					echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_SAVE_AND_CLOSE_TEXT', $lang) );
				?></span>
			</span>
		</button>
	</div>
</div>
<?php endif; ?>

<?php /***   C A N C E L   -   B U T T O N   ***/ ?>
<?php if (in_array('cancel', $buttons)) : ?>
<div class="d-inline-block align-top ml-3">
	<div class="input-group">
		<div class="input-group-prepend">
			<span class="input-group-text">
				<i class="fas fa-times text-red"></i>
			</span>
		</div>
		<?php if (1) : // TODO - implement form submit on cancel to check in a previously checked out database row ?>
		<a href="<?php echo empty($backRoute) ? 'javascript:void(0)' : $backRoute; ?>"
		   data-href="javascript:void(0)"
		   role="button"
		   class="btn btn-sm btn-custom btn-cancel left-radius-0 pr-md-3 allow-window-unload"
		   <?php if (empty($backRoute)) : ?>
		   data-bind="windowClose"
		   data-force-reload="true"
		   <?php endif; ?>
		>
			<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $lang); ?>"
				  data-toggle="tooltip"
			>
				<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
					echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $lang) );
				?></span>
			</span>
		</a>
		<?php else : ?>
		<button type="submit"
				form="<?php echo $formName; ?>"
				name="button"
				value="cancel"
				class="btn btn-sm btn-custom btn-cancel left-radius-0 pr-md-3 allow-window-unload"
				data-bind="disableEmptyFields"
		>
			<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ABORT_AND_CLOSE_TEXT', $lang); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ABORT_AND_CLOSE_TEXT', $lang); ?>"
				  data-toggle="tooltip"
			>
				<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
					echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $lang) );
				?></span>
			</span>
		</button>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>
