<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Factory;
use  \Helper\UriHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang  = $this->get('language');
$view  = $this->__get('view');
$input = $view->get('input');
$model = $view->get('model');
$user  = $view->get('user');

$date  = $input->getCmd('date') ?? (new \DateTime('NOW', new \DateTimeZone(FTKRULE_TIMEZONE)))->format('d.m.Y');

$list  = (array) $view->get('list');

//$date     = '06.04.2021';
$stats    = $model->getProcessStats(1, $date);
//$articles = $model->getProcessArticles(1, $date);
//$parts    = $model->getProcessParts(1, 416, $date);

// Init tabindex
$tabindex = 0;
?>

<form class="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=%s&date=%s', $lang, $view->get('name'), $view->get('layout'), $date ))); ?>"
	  class="form-inline"
	  method="get"
	  name="form-horizontal statsform toolbarForm"
	  data-submit=""
>
	<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
	<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
	<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />

	<h1 class="h3 d-inline-block mr-3"><?php echo 'Fehlerstatistik über alle Prozesse'; //echo Text::translate('COM_FTK_LABEL_STATISTIC_PROCESS_ERRORS_TEXT', $lang); ?></h1>

	<?php if (FALSE) : ?>
	<div class="input-group date"
		 data-provide="datepicker"
		 data-date-language="<?php echo $lang; ?>"
		 data-date-week-start="1"
		 data-date-days-of-week-disabled="[]"
		 <?php // data-date-days-of-week-highlighted="[0,6]" ?>
		 data-date-format="dd.mm.yyyy"
		 data-date-autoclose="true"
		 data-date-calendar-weeks="true"
		 data-date-clear-btn="false"
		 data-date-today-highlight="true"
		 data-date-today-btn="linked"
		 data-date-end-date="<?php echo date_create('NOW', new \DateTimeZone(FTKRULE_TIMEZONE))->format('d.m.Y'); ?>"
		 style="margin-top:-0.25rem"
	>
		<input type="text"
			   name="date"
			   value="<?php echo htmlentities($date); ?>"
			   class="form-control datepicker auto-submit text-right"
			   placeholder="dd.mm.yyyy"
			   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $lang); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $lang); ?>"
			   aria-described-by="btn-pick-date"
		/><?php // TODO - fix shared parameters ?>
		<div class="input-group-append">
			<span class="input-group-text" id="btn-pick-date" role="button"><i class="fas fa-calendar-alt"></i></span>
		</div>
	</div>
	<?php endif; ?>
</form>

<hr>

<h4><code>// TODO - decide on how to display</code></h4>

<?php if (FALSE) : ?>
<div class="status-overlay"><?php // required for AJAX loading simulation ?>
	<table class="table table-sm mt-4 position-relative" id="trkProcesses">
		<thead class="thead-dark">
			<tr>
				<th scope="col"><span class="pl-2">#</span></th>
				<th scope="col" class="text-center"><?php echo Text::translate('COM_FTK_LABEL_STATUS_TEXT', $lang); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TEXT', $lang); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_FIRST_ENTRY_TEXT', $lang); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_LAST_ENTRY_TEXT', $lang); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_IDLING_TEXT', $lang); ?></th>
				<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_PARTS_TEXT', $lang); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php $i = 1; ?>
			<?php foreach ($list as $process) : ?><?php
					$process = new Registry($process);
					$stats   = $statsModel->getProcessStats($process->get('procID'), $date);
					$stats   = new Registry($stats);
			?>
			<tr>
				<td scope="row">
					<span class="pl-1"><?php echo $i < 10 ? "0{$i}" : $i; ?></span>
				</td>
				<td class="text-center">
					<i class="fas fa-lock<?php echo $process->get('blocked') ? '' : '-open'; ?>"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($process->get('blocked') ? 'BLOCKED' : 'ACTIVE') . '_TEXT', $lang); ?>"></span>
				</td>
				<td class="text-left">
				<?php if (FALSE) : // Link name to process details ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=item&pid=%d', $lang, $process->get('procID') ))); ?>"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $lang); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $lang); ?>"
					><?php echo html_entity_decode($process->get('name')); ?></a>
				<?php endif; ?>

					<?php // Link name to stats details ?>
				<?php if ((int) $stats->get('total', 0) > 0) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=statistics&layout=tracking.process&pid=%d&date=%s', $lang, $process->get('procID'), $date ))); ?>"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $lang); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $lang); ?>"
					   target="_blank"
					><?php echo html_entity_decode($process->get('name')); ?></a>
				<?php else : ?>
					<?php 	echo html_entity_decode($process->get('name')); ?>
				<?php endif; ?>
				</td>
				<td><?php echo is_null($stats->get('first', null))
						? '&ndash;'
						: (new \DateTime($stats->get('first'), new \DateTimeZone(FTKRULE_TIMEZONE)))->format('H:i:s'); ?></td>
				<td><?php echo is_null($stats->get('last', null))
						? '&ndash;'
						: (new \DateTime($stats->get('last'), new \DateTimeZone(FTKRULE_TIMEZONE)))->format('H:i:s'); ?></td>
				<td><?php echo is_null($stats->get('breaks', null))
						? '&ndash;'
						: sprintf('%d %s', $stats->get('breaks'), Text::translate('COM_FTK_LABEL_MINUTES_TEXT', $lang)); ?></td>
				<td>
				<?php if (is_null($stats->get('total', null))) : ?>
						&ndash;
				<?php else : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=statistics&layout=tracking.process&pid=%d&date=%s', $lang, $process->get('procID'), $date ))); ?>"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $lang); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $lang); ?>"
					   target="_blank"
					><?php echo (int) $stats->get('total', 0); ?></a>
				<?php endif; ?>
				</td>
			</tr>
			<?php $i += 1; endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>
