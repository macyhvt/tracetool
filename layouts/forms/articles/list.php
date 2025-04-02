<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Model;
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



$list     = (array) $view->get('list');
//$filter   = $input->getString('filter', (string) \Nematrack\Model\Lizt::FILTER_ACTIVE);
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


$q = $input->post->getString('searchword') ?? ($input->getString('searchword') ?? null);	// search term
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

		case 'search' :
			$list = (array) $view->doSearch($q);
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

<style>
/*the container must be positioned relative:*/

.autocomplete {
	position: relative;
	display: inline-block;
}

.autocomplete-items {
	position: absolute;
	border: 1px solid #d4d4d4;
	border-bottom: none;
	z-index: 99;
	/*position the autocomplete items to be the same width as the container:*/
	top: 100%;
	left: 0;
	right: 0;
	margin-top: -1px;
	margin-left: 5px;
	margin-right: 5px;
}

.autocomplete-items div {
	padding: 0.375rem 0.75rem;
	cursor: pointer;
	background-color: #fff;
	border-bottom: 1px solid #d4d4d4;
}


/*when hovering an item:*/

.autocomplete-items div:hover {
	background-color: #e9e9e9;
}


/*when navigating through the items using the arrow keys:*/

.autocomplete-active {
	background-color: DodgerBlue !important;
	color: #ffffff;
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
		<?php if ($task == 'search' && $cnt > 0) : ?>
		<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( $view->getRoute() ) ); ?>"
			  method="get"
			  class="form <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> d-inline-block"
			  name="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
			  id="<?php echo sprintf('%s%s', 'filter', ucfirst($formName)); ?>"
			  data-submit=""
			  data-monitor-changes="false"
		>
			<input type="hidden" name="hl"         value="<?php echo $this->language; ?>" />
			<input type="hidden" name="view"       value="<?php echo $view->get('name'); ?>" />
			<input type="hidden" name="layout"     value="<?php echo $view->get('layout'); ?>" />
			<input type="hidden" name="task"       value="search" />
			<?php if (isset($q)) : ?>
			<input type="hidden" name="searchword" value="<?php echo html_entity_decode($q); ?>" />
			<?php endif; ?>

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
		</form>
		<?php endif; ?>

		<?php if ($user->isManager() || $user->isAdministrator() || $user->isProgrammer() || $user->isSuperuser()) : // Management is permitted to managers only ?>
		<div class="d-inline-block align-top">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-plus text-success"></i>
					</span>
				</div>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=add', $this->language ))); ?>"
				   role="button"
				   class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
				   data-bind="windowOpen"
				   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=add&return=%s', $this->language, base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
				   style="padding:0.375rem 0.8rem"
				   tabindex="<?php echo ++$tabindex; ?>"
				>
					<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ARTICLE_CREATE_TEXT', $this->language); ?>"
						  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_ARTICLE_CREATE_TEXT', $this->language); ?>"
						  data-toggle="tooltip"
					>
						<span class="btn-text d-none d-md-inline ml-md-1 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
							echo Text::translate('COM_FTK_LABEL_ARTICLE_ADD_TEXT', $this->language);
						?></span>
					</span>
				</a>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<hr>

<div class="row">
	<div class="col">
		<h4 class="sr-only"></h4>
		<div class="mb-2">
			<form action="<?php echo $view->getRoute(); ?>"
				  method="get"
				  name="searchform"
				  class="form-inline d-block"
				  data-submit=""
			>
				<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
				<input type="hidden" name="view"   value="<?php echo mb_strtolower($view->get('name')); ?>" />
				<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />
				<input type="hidden" name="task"   value="search" />




				<?php // Search field ?>
				<div class="input-group input-group-md">
					<div class="input-group-prepend">
						<span class="input-group-text"><?php echo Text::translate('COM_FTK_INPUT_TITLE_SEARCH_TEXT', $this->language) . ':'; ?></span>
					</div>
					<input type="search"
						   name="searchword"
						   value="<?php echo html_entity_decode($q); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_DRAWING_NUMBER_PARTIALLY_TEXT', $this->language); ?>"
						   class="form-control position-relative"
						   id="searchArticles"
						   title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SEARCH_TEXT', $this->language); ?>"
						   aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SEARCH_TEXT', $this->language); ?>"
						   aria-describedby="search-addon"
						   required
						   autofocus
						   style="padding-bottom:0.5rem"	<?php // Bootstrap override ?>
					/>
					<div class="input-group-append">
						<button type="button"
								class="btn btn-sm btn-reset btn-outline-secondary px-3"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_EMPTY_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_EMPTY_TEXT', $this->language); ?>"
								data-toggle="reset"
								data-target="#searchArticles"
						>
							<i class="fas fa-times" aria-hidden="true"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_EMPTY_TEXT', $this->language); ?></span>
						</button>
						<button type="submit"
								class="btn btn-sm btn-search btn-outline-secondary px-3"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $this->language); ?>"
						>
							<i class="fas fa-search" aria-hidden="true"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $this->language); ?></span>
						</button>
					</div>
				</div>

				<?php if (!empty($q) && !$cnt) : ?>
					<?php echo LayoutHelper::render('system.alert.info', [
						'message' => Text::translate('COM_FTK_HINT_NO_SEARCH_RESULT_TEXT', $this->language),
						'attribs' => [
							'class' => 'alert-sm'
						]
					]); ?>
				<?php endif; ?>

				<?php // Results list ?>
				<?php if (is_array($list)) : ?>
				<div class="position-relative" data-items="<?php echo $cnt; ?>">
					<ul class="list-unstyled striped" id="<?php echo $view->get('name'); ?>-list">
					<?php array_walk($list, function($article) use(&$view, &$model, &$input) { ?>
					<?php	// Load data into Registry for less error prone access. ?>
					<?php	$item          = new Registry($article); ?>
					<?php	$itemProcesses = $item->get('processes'); ?>
					<?php	$itemProcesses = $itemProcesses == '[]' ? [] : explode(',', $item->get('processes')); ?>
						<li class="list-item<?php //echo (/* $item->get('trashed') ? ' list-item-hidden d-none' : '' */); ?>"
							data-rel="<?php echo mb_strtolower(html_entity_decode($item->get('number'))); ?>"
							style="margin-top:.75rem; margin-bottom:.75rem"
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
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=item&aid=%d', $this->language, $item->get('artID') ))); ?>"
									   class="btn btn-sm btn-link d-inline-block text-left<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
									   data-bind="windowOpen"
									   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=item&aid=%d&return=%s', $this->language, $item->get('artID'), base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
									   data-location-target="_blank"
									   target="_self"
									>
										<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_ARTICLE_VIEW_THIS_TEXT', $this->language); ?>"
											  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_ARTICLE_VIEW_THIS_TEXT', $this->language); ?>"
											  data-toggle="tooltip"
										><?php
											echo empty($item->get('number')) ? Text::translate('COM_FTK_NA_TEXT', $this->language) : html_entity_decode($item->get('number'));
										?></span>
									</a>
									<small class="d-none d-lg-inline-block align-middle text-muted<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>">(
										<?php echo sprintf('%d %s', ($cnt = count($itemProcesses)), Text::translate($cnt == 1 ? 'COM_FTK_LABEL_PROCESS_TEXT' : 'COM_FTK_LABEL_PROCESSES_TEXT', $this->language)); ?>
									)</small>
								</div>

								<?php if (0 && !$item->get('trashed')) : // DiSABLED on 20230430 because this way it was possible to edit whereas the item view did not provide the edit button ?>
								<div class="col-auto row-actions px-0 px-sm-auto">
									<?php if (is_a($user = $view->get('user'), 'Nematrack\Entity\User') && ($user->getFlags() >= User::ROLE_MANAGER)) : ?>
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=edit&aid=%d&return=%s', $this->language, $item->get('artID'), base64_encode($view->getRoute()) ))); ?>"
									   role="button"
									   class="btn btn-sm btn-link btn-edit"
									   data-bind="windowOpen"
									   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=edit&aid=%d&return=%s', $this->language, $item->get('artID'), base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
									   data-location-target="_blank"
									   target="_self"
									>
										<i class="fas fa-pencil-alt"></i>
										<span class="sr-only"
											  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_ARTICLE_EDIT_THIS_TEXT', $this->language); ?>"
											  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_ARTICLE_EDIT_THIS_TEXT', $this->language); ?>"
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
					<?php }) ?>
					</ul>
				</div>
				<?php endif; ?>
			</form>
		</div>
	</div>
</div>

<?php // Free memory
unset($list);
