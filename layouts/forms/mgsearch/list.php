<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');
$userID   = $user->get('userID');
$orgID    = $user->get('orgID');

$list     = (array) $view->get('list');
// $filter   = $input->getString('filter', (string) ListModel::FILTER_ACTIVE);
$filter   = $input->getString('filter');
$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task') ?? ($input->getCmd('task') ?? null);

$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);
?>
<?php /* Access check */
// TODO
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		case 'archive' :
		case 'restore' :
			$view->saveArchivation();
		break;

		case 'delete' :
		case 'recover' :
			$view->saveDeletion();
		break;

		case 'lock' :
		case 'unlock' :
			$view->saveState();
		break;

		case 'window.close' :
			echo '<script>window.close();</script>';
		break;
	endswitch;
else :
	// Delete form data from user session as it is not required any more.
	$user->__unset('formData');
endif;

$cnt = count($list);

// Init tabindex
$tabindex = 0;
?>
<?php /* Calculate page heading */
$viewTitle = Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $this->language);

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
<?php // Prepare view data
$mainGroups         = $view->get('mainGroups', []);			// all registered main groups
$mainGroups1         = $view->get('mainGroups1', []);			// all registered main groups
$mainGroups2         = $view->get('mainGroups2', []);			// all registered main groups
$mainGroupsFilter   = $view->get('mainGroupsFilter');		// selected main group(s)
$mainGroupsFiltered = $view->get('mainGroupsFiltered', []);	// selected main group(s) data extracted from main groups
$mainGroupsList     = array_keys($mainGroups);
//print_r($mainGroups['group_name']);
//echo "<pre>";print_r($mainGroups2);
/*print_r($mainGroupsList1);
$mainGroupsN = $model->getInstance('articles', ['language' => $this->language])->getMainGroupsNew();
print_r($mainGroupsN);
$listCount  = count($view->get('list'));
$mainGroupsList = array_diff($mainGroupsList1, $mainGroupsN);*/
//$mainGroupsList2 = array_merge($mainGroupsList1, $mainGroupsN);

//$mainGroupsList = array_unique($mainGroupsList2);
//print_r($mainGroups1);
//print_r($mainGroupsFilter);
// Create form submit URL
$formAction = $view->getRoute();

// Init tabindex
$tabindex   = 0;
?>

<style>
.no-results::before {
	content: "<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_PROJECT_MAIN_GROUP_TEXT', $this->language); ?>";
    font-weight: bold;
    background-color: rgba(106 106 106 / 40%);
    color: transparent;
    font-size: 200%;
    text-shadow: 2px 2px 3px rgb(255 255 255 / 50%);
    -webkit-background-clip: text;
    -moz-background-clip: text;
    background-clip: text;
}
</style>

<div class="row">
	<div class="col">
		<h1 class="h3 viewTitle d-inline-block my-0 mr-3">
			<?php echo $viewTitle; ?>
			<?php echo ($cnt ? sprintf('<small class="text-muted ml-2">(%d)</small>', $cnt) : ''); ?>
		</h1>

		<?php /* List filter */ ?>
		<?php // TODO - make this a HTML widget (don't forget the above CSS) and replace this code ?>
		<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( $formAction ) ); ?>"
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
					<?php if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
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
			
			<?php // Filter: project main group ?>
			<select name="mgrp[]"
			        class="form-control form-control-sm custom-select d-inline-block align-top w-auto selectMaingroup auto-submit"
			        id="ipt-projects-mgrp"
			        title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_MAIN_GROUP_TEXT', $this->language); ?>"
			        aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_MAIN_GROUP_TEXT', $this->language); ?>"
			        data-toggle="tooltip"
			        tabindex="<?php echo ++$this->tabindex; ?>"
			>
				<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); ?> &ndash;</option>
				<?php foreach ($mainGroups1 as $group) : ?>
				<?php	echo sprintf('<option id="'.$group['mgid'].'" value="%s"%s>%s</option>', $group['group_name'], (in_array($group['group_name'], $mainGroupsFilter) ? ' selected' : ''), $group['group_name']); ?>
				<?php endforeach; ?>
			</select>
		</form>


	</div>
</div>

<hr>

<?php if (!$cnt) : ?>
	<?php if ($mainGroupsFilter) : ?>
		<?php echo LayoutHelper::render('system.alert.info', [
				'message' => Text::translate('COM_FTK_HINT_NO_RESULT_TEXT', $this->language),
				'attribs' => [
						'class' => 'alert-sm'
				]
		]); ?>
	<?php else : ?>

	<?php endif; ?>
<?php endif; ?>

<?php //echo "<pre>";print_r($list);// Projects list ?>
<?php if (is_array($list)) : ?>
<div class="position-relative" data-items="<?php echo count($list); ?>">
	<ul class="list-unstyled striped" id="<?php echo $view->get('name'); ?>-list">
	<?php array_walk($list, function($project) use(&$view, &$model, &$input, &$user) { ?>
		<?php // Load data into Registry for less error prone access. ?>
		<?php $item    = new Registry($project); ?>
		<?php $members = $item->get('organisations'); ?>
		<?php $states  = array_intersect_key(
				$project, ['blocked' => null, 'trashed' => null, 'archived' => null]
		); ?>
		<?php // When project has no members, limit access to high privileged users, when list has members, limit access for low privileged users to their projects ?>
		<li id="<?php echo $item->get('mgid')?>" class="list-item<?php //echo (/* $user->getFlags() <= \Nematrack\Access\User::ROLE_MANAGER &&  */ ($item->get('archived') || $item->get('blocked') || $item->get('trashed')) ? ' list-item-hidden d-none' : ''); ?>"
			style="margin-top:.75rem; margin-bottom:.75rem"
			data-rel="<?php echo mb_strtoupper(html_entity_decode($item->get('number'))); ?>"
		>
			<div class="row">
				<div class="col d-none d-md-block px-0 pl-1 text-center" style="max-width:3rem">
					<span class="btn btn-sm btn-lock"
						  data-toggle="tooltip"
						  title="<?php echo Text::translate('COM_FTK_STATUS_' .
						  (($item->get('trashed'))
							? 'TRASHED'
							: /* ($item->get('blocked') ? 'LOCKED' : 'ACTIVE') */
							(($item->get('blocked'))
								? 'LOCKED'
								: (($item->get('archived'))
									? 'ARCHIVED'
									: 'ACTIVE'))
							) . '_TEXT', $this->language); ?>"
					>
						<i class="<?php echo
							($item->get('trashed')
								? 'far fa-trash-alt text-muted'
								: /* ($item->get('archived') ? 'fas fa-archive text-muted' : 'fas fa-lock-open') */
								($item->get('blocked')
									? 'fas fa-lock'
									: 'fas fa-lock-open')
							); ?>"
						></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' .
						   (($item->get('trashed'))
							? 'TRASHED'
							: /* ($item->get('blocked') ? 'LOCKED' : 'ACTIVE') */
							(($item->get('blocked'))
								? 'LOCKED'
								: (($item->get('archived'))
									? 'ARCHIVED'
									: 'ACTIVE'))
							) . '_TEXT', $this->language);
						?></span>
					</span>
				</div>
				<div class="col-10 col-xl-auto px-md-0" style="font-size:1rem!important">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=project&layout=item&proid=%d&refr=%s', $this->language, $item->get('proID'),'subgroup' ))); ?>"
					   class="btn btn-sm btn-link d-inline-block text-left<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
					   data-bind="windowOpen"
					   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=project&layout=item&proid=%d&refr=%s&return=%s', $this->language, $item->get('proID'),'subgroup', base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
					   data-location-target="_blank"
					   target="_blank"
					   style="min-width:3rem"
					>
						<span title="View this subgroup"
							  aria-label="View this subgroup"
							  data-toggle="tooltip"
						><?php echo sprintf('%s : <span class="text-%s">%s</span>',
							(empty($item->get('number')) ? Text::translate('COM_FTK_NA_TEXT', $this->language) : html_entity_decode($item->get('number'))),
							($item->get('trashed') ? 'muted' : 'dark'),
							(empty($item->get('name'))   ? Text::translate('COM_FTK_NA_TEXT', $this->language) : html_entity_decode($item->get('name'))),
						); ?></span>
					</a>
					<?php if (is_a($user, 'Nematrack\Entity\User') && !$user->isCustomer() && !$user->isSupplier()) : ?>
					<small class="d-none d-lg-inline-block align-middle text-muted<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>">(
						<?php $cnt = count((array) $item->get('organisations')); ?>
						<?php echo sprintf('%d %s', $cnt, Text::translate($cnt == 1 ? 'COM_FTK_LABEL_MEMBER_TEXT' : 'COM_FTK_LABEL_MEMBERS_TEXT', $this->language)); ?>
					)</small>
					<?php endif; ?>
				</div>

				<?php if (0 && !$item->get('trashed')) : // DiSABLED on 20230430 because this way it was possible to edit whereas the item view did not provide the edit button ?>
				<div class="col-auto row-actions px-0 px-sm-auto">
					<?php if (is_a($user = $view->get('user'), 'Nematrack\Entity\User') && ($user->getFlags() >= User::ROLE_MANAGER)) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=project&layout=edit&proid=%d', $this->language, $item->get('proID') ))); ?>"
					   role="button"
					   class="btn btn-sm btn-link btn-edit"
					   data-bind="windowOpen"
					   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=project&layout=edit&proid=%d&return=%s', $this->language, $item->get('proID'), base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
					   data-location-target="_blank"
					   target="_self"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="sr-only"
							  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROJECT_EDIT_THIS_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_ORGANISATION_EDIT_THIS_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						><?php
							echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language);
						?></span>
					</a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</li>
	<?php }); ?>
	</ul>
</div>
<?php endif; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    /*$(document).ready(function() {
        // Initially hide all list items
        $('#mgsearch-list .list-item').hide();

        // Update list items based on dropdown selection
        $('#ipt-projects-mgrp').change(function() {
            var selectedId = $('#ipt-projects-mgrp option:selected').attr('id');

            // Hide all list items
            $('#mgsearch-list .list-item').hide();

            // Show the list item with the matching id
            if (selectedId) {
                $('#mgsearch-list .list-item#' + selectedId).show();
            }
        });

        // Trigger change event on page load to show the correct item if an option is pre-selected
        $('#ipt-projects-mgrp').trigger('change');
    });*/
</script>
<?php // Free memory
unset($list);
?>
