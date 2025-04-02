<?php
// Register required libraries.
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang      = $this->get('language');
$user      = $this->get('user');
$buttons   = array_diff(['submit','submitAndNew','submitAndClose','cancel'], (array) $this->get('hide', []));
$formName  = $this->get('formName');
$backRoute = $this->get('backRoute');
?>

<?php /***   C A N C E L   -   B U T T O N   ***/ ?>
<?php if (in_array('cancel', $buttons)) : ?>
<div class="d-inline-block align-top ml-3">
	<div class="input-group">
		<div class="input-group-prepend">
			<span class="input-group-text">
				<i class="fas fa-times text-red"></i>
			</span>
		</div>
		<button type="submit"
				form="<?php echo $formName; ?>"
				name="button"
				value="cancel"
				class="btn btn-sm btn-custom btn-cancel left-radius-0 pr-md-3 allow-window-unload"
		>
			<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ABORT_AND_CLOSE_TEXT', $$lang); ?>"
				  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ABORT_AND_CLOSE_TEXT', $$lang); ?>"
				  data-toggle="tooltip"
			>
				<span class="btn-text ml-md-1 ml-lg-2 text-<?php echo ($$lang == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
					echo mb_strtolower( Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $$lang) );
				?></span>
			</span>
		</button>
	</div>
</div>
<?php endif; ?>
