<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Messager;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php // FIXME - current implementation of {@see \Nematrack\Messager does not process configuration options. Hence, calculation of 'autohide' below has no effect. }?>
<?php /* Init vars */
$data        = new Registry($this->data);
$options     = new Registry($this->options);

$msgList     = array_filter((array) $data->get('messages', [], 'ARRAY'));
$msgCount    = count($msgList);
$hasMsg      = $msgCount > 0;

$msgTypes    = Messager::getMessageTypes();
$msgHeadings = Messager::getHeadingTypes();
?>
<?php if ($msgCount > 0) : ?>
<aside class="container sr-only" id="system-message-container" data-count="<?php echo $msgCount; ?>">
<?php 	if (is_array($msgList) && $hasMsg) : $i = 1; ?>

	<?php foreach ($msgList as $type => $list) : ?>

	<div class="system-message system-message-<?php echo $i; ?> alert alert-dismissible <?php echo ($options->get('autohide') && !$options->get('autohide')) ? '' : 'autohide' ;?> rounded-0 fade show"
		 id="system-message-<?php echo $i; ?>"
		 role="alert"
	>
		<div class="alert-message alert-message-<?php echo ArrayHelper::getValue($msgTypes, $type, '', 'STRING'); ?> border-0 bg-white">
			<?php // This requires JS so we should add it trough JS. Progressive enhancement and stuff. ?>
			<button type="button" class="close system-message-toggle<?php //echo ' pull-' . $pullTo; ?>"
					title="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $this->language); ?>"
					data-dismiss="alert"	<?php // the attribute value requires a parent element with attribute 'role="alert"' because this attributes value is the reference name for an attribute 'role="alert"' ?>
					aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $this->language); ?>"
			>
				<span aria-hidden="true">&times;</span>
			</button>

			<?php if (!empty($list)) : ?>
			<h4 class="alert-heading h5 mb-1"><?php echo ucfirst(Text::translate(ArrayHelper::getValue($msgHeadings, $type, 'message'), $this->language)); ?></h4>
			<div class="messages">
				<?php foreach ($list as $msg) : ?>
				<p class="mb-0"><?php echo trim($msg); ?></p>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php $i += 1; ?>
	<?php endforeach; ?>

<?php 	endif; ?>
</aside>
<?php endif; ?>
