<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Model\Lizt as ListModel;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$oid    = $input->getInt('oid');
$filter = $input->getString('filter', (string) ListModel::FILTER_ACTIVE);
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
$canDelete = true;
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$item = $view->get('item');

$this->item       = $item;
$this->user       = $user;
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';

$list = $model->getInstance('organisation', ['language' => $this->language])->getOrganisationProjectsNEW([
	'orgID'  => $this->item->get('orgID'),
	'filter' => $filter
]);

$cnt = (int) count($list);

// Init tabindex
$tabindex  = 0;
?>
<?php /* Calculate page heading */
$viewTitle = Text::translate(sprintf('%s:<span class="small ml-3">%s</span>',
	Text::translate('COM_FTK_LABEL_PROJECTS_TEXT', $this->language),
	html_entity_decode($this->item->get('name'))
), $this->language);

if (strlen($input->getString('filter'))) :
	switch ($input->getString('filter')) :
		case (ListModel::FILTER_ALL) :
			$viewTitle = sprintf('%s %s',   Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language), $viewTitle);
		break;

		case (ListModel::FILTER_ACTIVE) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_ACTIVE_TEXT',   $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;

		case (ListModel::FILTER_ARCHIVED) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_ARCHIVED_TEXT', $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;

		case (ListModel::FILTER_DELETED) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_DELETED_TEXT',  $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;

		case (ListModel::FILTER_LOCKED) :
			$viewTitle = sprintf('%s%s %s', Text::translate('COM_FTK_STATUS_LOCKED_TEXT',   $this->language), ($this->language == 'de' ? 'e' : ''), $viewTitle);
		break;
	endswitch;
endif;
?>

<style>
#orgProjects th,
#orgProjects td {
	vertical-align: inherit;	/* required to align text with buttons vertically centered */
}
</style>

<div class="form-horizontal" id="organisationProjectsForm">
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=organisation&layout=item&oid=%d', $this->language, $this->item->get('orgID') ))); ?>"





	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?></span>
	</a>

	<h1 class="h3 viewTitle d-inline-block my-0 mr-3">
		<?php echo $viewTitle; ?>
		<?php echo ($cnt ? sprintf('<small class="text-muted ml-2">(%d)</small>', $cnt) : ''); ?>
	</h1>

	<?php /* List filter */ ?>
	<?php // TODO - make this a HTML widget (don't forget the above CSS) and replace this code ?>
	<?php //if ($this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?>
	<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( $view->getRoute() ) ); ?>"
		  method="get"
		  class="form <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> d-inline-block"
		  name="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
		  id="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
		  data-submit=""
		  data-monitor-changes="false"
	>
		<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
		<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
		<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />
		<input type="hidden" name="oid"    value="<?php echo $this->item->get('orgID'); ?>" />

		<div class="dropdown d-inline-block"
			 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
			 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
			 data-toggle="tooltip"
		>
			<button type="button"
					form="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
					class="btn btn-secondary dropdown-toggle"
					id="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('name'))); ?>Button"
					data-toggle="dropdown"
					aria-haspopup="true"
					aria-expanded="false"
					style="vertical-align:super; padding:0.275rem 0.7rem; opacity:0.4"
					tabindex="<?php echo ++$tabindex; ?>"
			>
				<i class="fas fa-filter"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_FILTER_TEXT', $this->language); ?></span>
			</button>
			<div class="dropdown-menu dropdown-filter mt-0" data-multiple="false" aria-labelledby="<?php echo sprintf('%s%s', 'filter', ucfirst($view->get('name'))); ?>Button">
				<label for="filter"
					   class="d-block small mx-3"
					   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
				>
					<input type="checkbox"
						   class="align-middle mr-1 auto-submit"
						   id="cb-filter-active"
						   name="filter"
						   value="<?php echo ListModel::FILTER_ACTIVE; ?>"
						   autocomplete="off"
						   <?php echo ($filter === '0') ? ' checked' : ''; ?>
					/>
					<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ACTIVE_ITEMS_LABEL', $this->language); ?></span>
				</label>
				<label for="filter"
					   class="d-block small mx-3"
					   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
				>
					<input type="checkbox"
						   class="align-middle mr-1 auto-submit"
						   id="cb-filter-locked"
						   name="filter"
						   value="<?php echo ListModel::FILTER_LOCKED; ?>"
						   autocomplete="off"
						  <?php echo ($filter == ListModel::FILTER_LOCKED) ? ' checked' : ''; ?>
					/>
					<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_LOCKED_ITEMS_LABEL', $this->language); ?></span>
				</label>
				<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
				<label for="filter"
					   class="d-block small mx-3"
					   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
				>
					<input type="checkbox"
						   class="align-middle mr-1 auto-submit"
						   id="cb-filter-deleted"
						   name="filter"
						   value="<?php echo ListModel::FILTER_DELETED; ?>"
						   autocomplete="off"
						   <?php echo ($filter == ListModel::FILTER_DELETED) ? ' checked' : ''; ?>
					/>
					<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_DELETED_ITEMS_LABEL', $this->language); ?></span>
				</label>
				<?php endif; ?>
				<label for="filter"
					   class="d-block small mx-3 mb-1"
					   title="<?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_TEXT', $this->language); ?>"
					   data-toggle="tooltip"
				>
					<input type="checkbox"
						   class="align-middle mr-1 auto-submit"
						   id="cb-filter-all"
						   name="filter"
						   value="<?php echo ListModel::FILTER_ALL; ?>"
						   autocomplete="off"
						   <?php echo ($filter == ListModel::FILTER_ALL) ? ' checked' : ''; ?>
					/>
					<span class="d-inline-block align-text-top"><?php echo Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language); ?></span>
				</label>
			</div>
		</div>
	</form>
	<?php //endif; ?>

	<?php //if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<?php //echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>
	<?php //endif; ?>

	<hr>

	<?php if (is_countable($list) && count($list)) : ?>
	<table class="table table-sm" id="orgProjects">
		<thead class="thead-dark">
			<tr>
				<th scope="col"><span class="pl-2">#</span></th>
				<th scope="col" class="text-center"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language)); ?></th>
				<th scope="col"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language)); ?></th>
				<th scope="col" class="d-none d-lg-table-cell"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->language)); ?></th>
				<?php if ($this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?><th scope="col"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_ROLE_TEXT', $this->language)); ?></th><?php endif; ?>
				<th scope="col"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_CREATED_TEXT', $this->language)); ?></th>
				<th scope="col"><?php echo ucfirst(Text::translate('COM_FTK_LABEL_CLOSED_TEXT', $this->language)); ?></th>
				<?php if (FALSE && $this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?><th scope="col"></th><?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php $i = 1; ?>
		<?php foreach ($list as $project) : ?><?php
				$project = new Registry($project);
				$created = $project->get('created', FTKRULE_NULLDATE);
				$created = ($created == FTKRULE_NULLDATE) ? null : $created;
		?>
			<tr>
				<td scope="row">
					<span class="pl-1"><?php echo $i < 10 ? "0{$i}" : $i; ?></span>
				</td>
				<td class="text-center"
					data-toggle="tooltip"
					data-state-blocked="<?php  echo $project->get('blocked');  ?>"
					data-state-archived="<?php echo $project->get('archived'); ?>"
					data-state-trashed="<?php  echo $project->get('trashed');  ?>"
					data-state-deleted="<?php  echo $project->get('deleted');  ?>"
					title="<?php echo Text::translate('COM_FTK_STATUS_' .
						  (($project->get('trashed'))
							? 'TRASHED'
							: /* ($this->item->get('blocked') ? 'LOCKED' : 'ACTIVE') */
							(($project->get('blocked'))
								? 'LOCKED'
								: (($project->get('archived'))
									? 'ARCHIVED'
									: 'ACTIVE'))
							) . '_TEXT', $this->language); ?>"
				>
					<span class="btn-lock">
						<i class="<?php echo ($project->get('trashed')
							? 'far fa-trash-alt text-muted'
							: ($project->get('archived')
								? 'fas fa-archive text-muted'
								: ($project->get('blocked')
									? 'fas fa-lock'
									: 'fas fa-lock-open')));
						?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' .
						   (($project->get('trashed'))
							? 'TRASHED'
							: (($project->get('blocked'))
								? 'LOCKED'
								: (($project->get('archived'))
									? 'ARCHIVED'
									: 'ACTIVE'))
							) . '_TEXT', $this->language);
						?></span>
					</span>
				</td>
				<td>
					<?php //if ($this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?>
					<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=project&layout=item&proid=%d', $this->language, $project->get('proID') ))); ?>"




					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROJECT_VIEW_THIS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROJECT_VIEW_THIS_TEXT', $this->language); ?>"
					><?php echo html_entity_decode($project->get('number')); ?></a>
					<?php else : ?>
					<span><?php echo html_entity_decode($project->get('number')); ?></span>
					<?php endif; ?>
				</td>
				<td class="d-none d-lg-table-cell">
					<?php //if ($this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?>
					<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=project&layout=item&proid=%d', $this->language, $project->get('proID') ))); ?>"



					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROJECT_VIEW_THIS_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROJECT_VIEW_THIS_TEXT', $this->language); ?>"
					><?php echo html_entity_decode($project->get('name')); ?></a>
					<?php else : ?>
					<span><?php echo html_entity_decode($project->get('name')); ?></span>
					<?php endif; ?>
				</td>
				<?php if ($this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?><td><?php echo html_entity_decode($project->get('role')); ?></td><?php endif; ?>
				<td><?php echo is_null($created) ? '&ndash;' : (new \DateTime($created, new \DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d'); ?></td>
				<td><?php echo !$project->get('archived') ? '&ndash;' : (new \DateTime($project->get('archiveDate'), new \DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d'); ?></td>
				<?php if (FALSE && $this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?><td class="text-right"></td><?php endif; ?>
			</tr>
		<?php $i += 1; endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
		<?php if ($this->user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?>
			<?php echo LayoutHelper::render('system.alert.info', [
				'message' => Text::translate('COM_FTK_HINT_ORGANISATION_HAS_NO_PROJECTS_TEXT', $this->language),
				'attribs' => [
					'class' => 'alert-sm'
				]
			]); ?>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php // Free memory
unset($input);
unset($item);
unset($list);
unset($model);
unset($user);
unset($view);
