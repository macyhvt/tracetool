<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Model;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang  = $this->get('language');
$view  = $this->__get('view');
$input = $view->get('input');
$model = $view->get('model');
$user  = $view->get('user');
?>
<?php /* Access check */
$formData = null;

if (is_a($user, ' \Entity\User')) :
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
// $canDelete = true;
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$item = $view->get('item');
?>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=%s&pid=%d&date=%s',
		$lang,
		$view->get('name'),
		$view->get('layout'),
		$item->get('process')->get('procID'),
		$item->get('date')
	  ))); ?>"
	  method="get"
	  name="statsForm"
	  class="form-inline statsForm"
	  id="statsForm"
	  data-submit=""
>
	<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
	<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
	<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

    <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=statistics&layout=tracking.process&pid=%d&date=%s',
		    $lang,
		    $item->get('process')->get('procID'),
		    $item->get('date')
       ))); ?>"
       role="button"
       class="btn btn-link"
       title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
       aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
       style="vertical-align:super; color:inherit!important"
    >
        <i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
        <span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?></span>
    </a>

	<h1 class="h3 d-inline-block mr-3">
		<?php echo sprintf('%s:<span class="small ml-3">%s</span>',
				Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $lang),
				html_entity_decode($item->get('process')->get('name'))
		); ?>
	</h1>

	<div style="margin-top:-0.25rem">
		<input type="text"
			   name="date"
			   value="<?php echo htmlentities($item->get('date')); ?>"
			   class="form-control text-right"
			   placeholder="dd.mm.yyyy"
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECTED_DATE_TEXT', $lang); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECTED_DATE_TEXT', $lang); ?>"
			   readonly
			   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
		/><?php // TODO - fix shared parameters ?>
	</div>
</form>

<hr>

<table class="table table-sm mt-4 position-relative" id="trkProcesses">
	<thead class="thead-dark">
		<tr>
			<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $lang); ?></th>
			<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $lang); ?></th>
			<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_PART_TEXT', $lang); ?></th>
			<th scope="col"></th>
		</tr>
	</thead>
	<tbody>
		<?php $process = $item->get('process'); ?>
		<?php $parts   = $item->get('parts'); ?>
		<?php $procID  = $process->get('procID'); ?>

		<?php foreach ($parts as $part) : ?>
		<?php 	$part = new Registry($part); ?>
		<tr>
			<td><?php echo html_entity_decode($part->get('type')); ?></td>
			<td><?php echo html_entity_decode($process->get('name')); ?></td>
			<td>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d&pid=%d',
						$lang,
						$part->get('partID'),
						$procID
				   ))); ?>#p-<?php echo hash('MD5', $procID); ?>"
				   data-toggle="tooltip"
				   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $lang); ?>"
				   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $lang); ?>"
				><?php echo html_entity_decode($part->get('trackingcode')); ?></a>
			</td>
			<!--td><?php //echo html_entity_decode($part->get('editor')); ?></td-->
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if (empty($parts)) : ?>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_PROCESS_NOT_HANDLED_ON_THIS_DATE_TEXT', $lang),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>
<?php endif; ?>

<?php // Free memory
unset($parts);
unset($part);
?>
