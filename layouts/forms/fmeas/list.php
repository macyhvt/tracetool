<?php
// Register required libraries.
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Entity;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view     = $this->__get('view');
$layout   = $view->get('input')->getCmd('layout');
$task     = $view->get('input')->post->getCmd('task') ?? ($view->get('input')->getCmd('task') ?? null);

$filter   = $view->get('input')->getString('filter', (string) ListModel::FILTER_ACTIVE);


$redirect = $view->get('input')->post->getString('return') ?? ($view->get('input')->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
	);

$q = $view->get('input')->post->getString('searchword') ?? ($view->get('input')->getString('searchword') ?? null);	// search term
?>
<?php /* Assign refs. */
$this->view = $view;
$this->list = $this->view->get('list');
$this->user = $this->view->get('user');
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
	$view->get('user')->__unset('formData');
endif;
?>
<?php /* Prepare view data */
// Assign refs.
$this->view = $view;
$this->list = $this->view->get('list');
$this->user = $this->view->get('user');

$listCount  = count($this->list);

// Create form submit URL
$formAction = $this->view->getRoute();

// Init tabindex
$tabindex = 0;
?>

<div class="row">
	<div class="col">
		<h1 class="h3 viewTitle d-inline-block my-0 mr-3"><?php
			echo $this->view->get('viewTitle'); ?>
			<?php echo ($listCount ? sprintf('<small class="text-muted ml-1">(%d)</small>', $listCount) : '');
		?></h1>

		<?php /* List filter */ ?>
		<?php // TODO - make this a HTML widget (don't forget the above CSS) and replace this code ?>
		<?php if ($task == 'search' && $listCount > 0) : ?>
		<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL( $this->view->getRoute() ) ); ?>"
			  method="get"
			  class="form <?php echo $this->view->get('formName'); ?> d-inline-block"
			  name="<?php echo $this->view->get('formName'); ?>"
			  id="<?php echo $this->view->get('formName'); ?>"
			  data-submit=""
			  data-monitor-changes="false"
		>
			<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
			<input type="hidden" name="view"   value="<?php echo mb_strtolower($this->view->get('name')); ?>" />
			<input type="hidden" name="layout" value="<?php echo $this->view->get('layout'); ?>" />





			<div class="dropdown d-inline-block"
				 title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
				 aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FILTER_LIST_TEXT', $this->language); ?>"
				 data-toggle="tooltip"
			>
				<button type="button"
						form="<?php echo sprintf('%s%s', 'filter', ucfirst($this->view->get('formName'))); ?>"
						class="btn btn-secondary dropdown-toggle"
						id="<?php echo sprintf('%s%s', 'filter', ucfirst($this->view->get('name'))); ?>Button"
						data-toggle="dropdown"
						aria-haspopup="true"
						aria-expanded="false"
						style="vertical-align:super; padding:0.275rem 0.7rem; opacity:0.4"
						tabindex="<?php echo ++$tabindex; ?>"
				>
					<i class="fas fa-filter"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_FILTER_TEXT', $this->language); ?></span>
				</button>
				<div class="dropdown-menu dropdown-filter mt-0" data-multiple="false" aria-labelledby="<?php echo sprintf('%s%s', 'filter', ucfirst($this->view->get('name'))); ?>Button">
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
		<?php endif; ?>

		<?php if ($this->user->isQualityManager() || $this->user->isProgrammer() || $this->user->isSuperuser()) : // Management is permitted to managers only ?>
		<div class="d-inline-block align-top">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-plus text-success"></i>
					</span>
				</div>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=fmea&layout=add', $this->language ))); ?>"
				   role="button"
				   class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
				   data-bind="windowOpen"
				   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=fmea&layout=add&return=%s',
						$this->language,
						base64_encode( basename( (new Uri($this->view->get('input')->server->getUrl('REQUEST_URI')))->toString() ) )
				   ))); ?>"
				   style="padding:0.375rem 0.8rem"
				   tabindex="<?php echo ++$tabindex; ?>"
				>
					<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FMEA_CREATE_TEXT', $this->language); ?>"
						  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_FMEA_CREATE_TEXT', $this->language); ?>"
						  data-toggle="tooltip"
					>
						<span class="btn-text d-none d-md-inline ml-md-1 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
							echo Text::translate('COM_FTK_BUTTON_TEXT_FMEA_ADD_TEXT', $this->language);
						?></span>
					</span>
				</a>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<hr>

<?php // Search filter ?>
<div class="row">
	<div class="col">
		<h4 class="sr-only"></h4>
		<div class="mt-2 mb-<?php echo ($listCount > 0 ? '3' : '1'); ?>">
			<form action="<?php echo $this->view->getRoute(); ?>"
				  method="get"
				  name="searchform"
				  class="form-inline d-block"
				  data-submit=""
			>
				<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
				<input type="hidden" name="view"   value="<?php echo mb_strtolower($this->view->get('name')); ?>" />
				<input type="hidden" name="layout" value="<?php echo $this->view->get('layout'); ?>" />
				<input type="hidden" name="task"   value="search" />

				<div class="input-group input-group-md">
					<div class="input-group-prepend">
						<span class="input-group-text"><?php echo Text::translate('COM_FTK_INPUT_TITLE_SEARCH_TEXT', $this->language) . ':'; ?></span>
					</div>
					<input type="search"
						   name="searchword"
						   value="<?php echo html_entity_decode($q); ?>"
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_FMEA_NUMBER_TEXT', $this->language); ?>"
						   class="form-control position-relative"
						   id="<?php echo sprintf('%s%s', 'search', ucfirst(mb_strtolower($this->view->get('name')))); ?>"
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
								form="searchform"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_EMPTY_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_EMPTY_TEXT', $this->language); ?>"
								data-toggle="reset"
								data-target="#searchArticles"
								onclick="(function(){
									if (!document.getElementById('searchArticles').value.trim().length) return false;
									window.location.replace(window.location.href.replace(/&searchword=[^&]*/i, ''));
								})()"
						>
							<i class="fas fa-times" aria-hidden="true"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_EMPTY_TEXT', $this->language); ?></span>
						</button>
						<button type="submit"
								class="btn btn-sm btn-search btn-outline-secondary px-3"
								form="searchform"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $this->language); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $this->language); ?>"
						>
							<i class="fas fa-search" aria-hidden="true"></i>
							<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SEARCH_TEXT', $this->language); ?></span>
						</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<?php if (!empty($q) && !$listCount && $task) : ?>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_NO_RESULT_TEXT', $this->language),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>
<?php endif; ?>

<?php // Results list ?>
<?php if (is_array($this->list)/* && $listCount*/) : ?>
<div class="position-relative" data-items="<?php echo $listCount; ?>">
	<ul class="list-unstyled striped" id="<?php echo $this->view->get('name'); ?>-list">
	<?php array_walk($this->list, function($fmea) { ?>
		<?php // Load data into an entity object. ?>
		<?php $item = Entity::getInstance('fmea', ['language' => $this->language])->bind($fmea); ?>
		<li class="list-item<?php //echo (/* $item->get('trashed') ? ' list-item-hidden d-none' : '' */); ?>"
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
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=fmea&layout=item&id=%d&return=%s',
							$this->language,
							(int) $item->get($item->getPrimaryKeyName()),
							base64_encode($this->view->getRoute())
					   ))); ?>"
					   class="btn btn-sm btn-link d-inline-block<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
					   data-bind="windowOpen"
					   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=fmea&layout=item&id=%d&return=%s',
						   $this->language,
						   (int) $item->get($item->getPrimaryKeyName()),
						   base64_encode( basename( (new Uri($this->view->get('input')->server->getUrl('REQUEST_URI')))->toString() ) )
					   ))); ?>"
					   data-location-target="_blank"
					   target="_self"
					>
						<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_VIEW_THIS_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						><?php
							echo '// TODO - output unique item property' //html_entity_decode($item->get('name'));
						?></span>
					</a>
					<small class="d-none d-lg-inline-block align-middle text-muted<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>">(
						<?php // echo sprintf('%d %s', $cnt, Text::translate($cnt == 1 ? 'COM_FTK_LABEL_PARAMETER_TEXT' : 'COM_FTK_LABEL_PARAMETERS_TEXT', $this->language)); ?>
					)</small>
				</div>

				<?php if (0 && !$item->get('trashed')) : // DiSABLED on 20230430 because this way it was possible to edit whereas the item view did not provide the edit button ?>
				<div class="col-auto row-actions px-0 px-sm-auto">
					<?php if (is_a($this->user, 'Nematrack\Entity\User') && $this->user->getFlags() >= User::ROLE_MANAGER) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=fmea&layout=edit&id=%d&return=%s',
							$this->language,
							(int) $item->get($item->getPrimaryKeyName()),
							base64_encode($this->view->getRoute())
					   ))); ?>"
					   role="button"
					   class="btn btn-sm btn-link btn-edit"
					   data-bind="windowOpen"
					   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=fmea&layout=edit&id=%d&return=%s',
						   $this->language,
						   (int) $item->get($item->getPrimaryKeyName()),
						   base64_encode( basename( (new Uri($this->view->get('input')->server->getUrl('REQUEST_URI')))->toString() ) )
					   ))); ?>"
					   data-location-target="_blank"
					   target="_self"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="sr-only"
							  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_EDIT_THIS_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PROCESS_EDIT_THIS_TEXT', $this->language); ?>"
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

<?php // Free memory
