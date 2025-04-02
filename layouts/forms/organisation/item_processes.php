<?php
// Register required libraries.
use Joomla\Uri\Uri;
use  \Access\User;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Text;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php if (count($processes = $this->item->get('processes'))): ?>
<ul id="orgProcesses" class="list-inline mb-md-0">
	<?php foreach ($processes as $procID => $procName) : ?>
	<li class="list-item list-inline-item py-1">
		<?php if (!$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=item&pid=%d', $this->language, $procID ))); ?>"
		   class="btn btn-sm btn-info"
		   data-toggle="tooltip"
		   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $this->language); ?>"
		   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $this->language); ?>"
		   rel="nofollow noreferrer"
		><?php echo html_entity_decode($procName); ?></a>
		<?php else : ?>
		<span class="btn btn-sm btn-info"><?php echo html_entity_decode($procName); ?></span>
		<?php endif; ?>
	</li>
	<?php endforeach; ?>
</ul>
<?php else : ?>
	<?php $messages = [
		Text::translate('COM_FTK_HINT_ORGANISATION_HAS_NO_PROCESSES_TEXT', $this->language),
		sprintf(
			Text::translate('COM_FTK_HINT_YOU_CAN_DO_THIS_ON_THE_RESPECTIVE_PROCESS_TEXT', $this->language),
			View::getInstance('processes', ['language' => $this->language])->getRoute() . '&return=' . base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) )
		)];
	?>

	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => implode(' ', $messages),
		'attribs' => [
			'class' => 'alert-sm mb-0'
		]
	]); ?>
<?php endif; ?>

<?php // Overlay to block user interaction ?>
<?php if ($this->isBlocked && $this->user->getFlags() < User::ROLE_ADMINISTRATOR) : ?>
<?php 	// echo LayoutHelper::render('system.element.blocked', new \stdclass, ['language' => $this->language]); ?>
<?php endif; ?>
