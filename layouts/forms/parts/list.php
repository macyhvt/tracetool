<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;
ini_set('memory_limit','2048M');
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
$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task') ?? ($input->getCmd('task') ?? null);

$redirect = $input->post->getString('return') ?? $input->getString('return');
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);

$ptid = $input->post->getInt('ptid') ?? null;	// partID
$pid  = $input->post->getInt('pid')  ?? null;	// procID
$q    = $input->post->getString('searchword') ?? ($input->getString('searchword') ?? null);	// search term

$isAutotrack      = $input->getInt('at') == '1';
$isAutotrackClass = ($isAutotrack ? 'isAutotrack' : '');
?>
<?php /* Access check */
// TODO
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		// Search term received. Expected formats: '000-9FF-9FF@SSS.1EJ.CB.00100.000' (used for laser parameters detection), '000-9FF-9FF' (trackingcode), 'SSS.1EJ.CB.00100.000' (article type), '1EJ' (project number)
		case 'search' :
			if (true) :
				$list = (array) $view->doSearch($q);
			else :
				switch (true) :
					// 8cHGc6mVYkudhR2vYBGnaJqHXu4suzxBGPVvvTYBKXYYEWsgJt (lot number)
					case preg_match('/' . FTKREGEX_LOT_NUMBER . '/', $q) :
						header(
							sprintf('Location: index.php?hl=%s&view=%s&layout=lot&lid=%s', $this->language, $view->get('name'), $q)
						);
						exit;

					// 000-9FF-9FF@SSS.1EJ.CB.00100.000 (code scanned from a printed lot sheet item)
					case preg_match('/' . FTKREGEX_LOT_ITEM_NUMBER . '/', $q) :
						$qPieces = (array) explode('@', $q);

						$q = current($qPieces);

						$list = (array) $view->doSearch__OLD($q);
					break;

					// 0009FF9FF  or  000-9FF-9FF (single part code)
					default :
						$list = (array) $view->doSearch__OLD($q);
				endswitch;
			endif;
		break;

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

	// Get updated process list
	// $list = $model->getInstance($view->get('name'), ['language' => $this->language])->getList();
else :
	// Delete form data from user session as it is not required any more.
	$user->__unset('formData');
endif;

$cnt = count($list);

// Init tabindex
$tabindex = 0;
?>


<style>
<?php // FIXME - bring the next 3 blocks otta here into global Stylesheet file as it applies to all btn.secondary having style attribute "opacity:0.4" ?>
#btnAutotrack,
#monAutotrack {
	opacity: 0.4;
}
#btnAutotrack:hover {
	opacity: 0.5;
}
#btnAutotrack:focus,
#btnAutotrack:active {
	opacity: 0.6;
}

#btnAutotrack.isAutotrack,
#monAutotrack.isAutotrack {
	opacity: 1;
}

#btnAutotrack.isAutotrack {
	background-color: rgba(255, 68, 0, .75);	/* orangered */
	border-color:     rgba(255, 68, 0, .75);	/* orangered */
}
#btnAutotrack.isAutotrack:hover {
	background-color: rgba(255, 68, 0, 1) !important;	/* orangered */
	border-color:     rgba(255, 68, 0, 1) !important;	/* orangered */
}
#btnAutotrack.isAutotrack:focus,
#btnAutotrack.isAutotrack:active {
	background-color: rgba(229, 61, 0, 1) !important;	/* orangered 10% darkened */
	border-color:     rgba(229, 61, 0, 1) !important;	/* orangered 10% darkened */
}
</style>

<div class="row">
	<div class="col">
		<h1 class="h3 viewTitle d-inline-block my-0 mr-3">
			<?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $this->language); ?>
			<?php echo ($cnt ? sprintf('<small class="text-muted ml-2">(%d)</small>', $cnt) : ''); ?>
		</h1>

		<?php if ($user->isManager() || $user->isAdministrator() || $user->isProgrammer() || $user->isSuperuser() || $user->isWorker()) : // Management is permitted to managers only @UPDATE 02 sep 2024 added role for worker to add lot and part ?>
		<?php // Button create part ?>
		<div class="d-inline-block align-top mr-md-2 mr-lg-3">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-plus text-success"></i>
					</span>
				</div>
				<a href<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=add', $this->language ))); ?>"
				   role="button"
				   class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
				   data-bind="windowOpen"
				   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=add&return=%s', $this->language, base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
				   style="padding:0.375rem 0.8rem"
				   tabindex="<?php echo ++$tabindex; ?>"
				>
					<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PART_CREATE_TEXT', $this->language); ?>"
						  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PART_CREATE_TEXT', $this->language); ?>"
						  data-toggle="tooltip"
					>
						<span class="btn-text d-none d-md-inline ml-md-1 ml-lg-2 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
							echo Text::translate('COM_FTK_BUTTON_TITLE_PART_CREATE_TEXT', $this->language);
						?></span>
					</span>
				</a>
			</div>
		</div>
		<?php // Button create a lot ?>
		<div class="d-inline-block align-top">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-clone text-success"></i>
					</span>
				</div>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=add_lot', $this->language ))); ?>"
				   role="button"
				   class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
				   data-bind="windowOpen"
				   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=add_lot&return=%s', $this->language, base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
				   style="padding:0.375rem 0.8rem"
				   tabindex="<?php echo ++$tabindex; ?>"
				>
					<span title="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_CREATE_TEXT', $this->language); ?>"
						  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_CREATE_TEXT', $this->language); ?>"
						  data-toggle="tooltip"
					>
						<span class="btn-text d-none d-md-inline ml-md-1 ml-lg-2 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
							echo Text::translate('COM_FTK_LINK_TITLE_LOT_CREATE_TEXT', $this->language);
						?></span>
					</span>
				</a>
			</div>
		</div>
		<?php endif; ?>

		<?php /* Dynamically render button that toggles "Autotrack" feature */ ?>
		<?php $hintAutotrackRenderOptions = json_encode([
			'element'    => 'span',
			'icon'       => 'fas fa-info-circle mr-1',
			'text'       => sprintf('<strong class="text-uppercase">%s</strong>', Text::translate('COM_FTK_STATUS_' . ($isAutotrack ? 'ACTIVE' : 'INACTIVE') . '_TEXT', $this->language)),
			'attributes' => [
				'id'          => 'monAutotrack',
				'class'       => trim('alert alert-inline alert-' . ($isAutotrack ? 'danger' : 'light') . ' autotrack-status autotrack-status-hint my-0 px-3 ' . $isAutotrackClass),
				'role'        => 'alert',
				'style'       => 'padding-top:0.375rem',
				'data-autotrack-active-text'   => Text::translate('COM_FTK_STATUS_ACTIVE_TEXT',   $this->language),
				'data-autotrack-inactive-text' => Text::translate('COM_FTK_STATUS_INACTIVE_TEXT', $this->language)
			]
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		?>
		<?php $btnAutotrackRenderOptions  = json_encode([
			'element'    => 'button',
			'icon'       => 'fas fa-history',
			'text'       => Text::translate('COM_FTK_LABEL_AUTOTRACK_TEXT', $this->language),
			'attributes' => [
				'name'         => 'at',
				'value'        => ($isAutotrack ? '0' : '1'),
				'id'           => 'btnAutotrack',
				'class'        => trim('btn btn-sm btn-secondary btn-autotrack ' . $isAutotrackClass),
				'title'        => Text::translate('COM_FTK_LINK_TITLE_TOGGLE_AUTOTRACK_MODE_TEXT', $this->language),
				'aria-label'   => Text::translate('COM_FTK_LINK_TITLE_TOGGLE_AUTOTRACK_MODE_TEXT', $this->language),
				'data-toggle'  => 'tooltip',
				'data-bind'    => 'sessionStorage',
				'data-monitor' => '#monAutotrack',
				'tabindex'     => ++$tabindex,
				'style'        => 'vertical-align:super; padding:0.375rem 0.8rem'
			]
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		?>
        <?php // echo $view->getRoute(); ?>
		<form action="<?php echo $view->getRoute(); ?>"
			  method="get"
			  class="form <?php echo ($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> float-right"
			  name="<?php echo sprintf('%s%s', 'autotrack', ucfirst($formName)); ?>"
			  id="<?php echo sprintf('%s%s', 'autotrack', ucfirst($formName)); ?>"
			  data-require="sessionStorage"
			  style="padding-top:1px"
		>
			<input type="hidden" name="hl"         value="<?php echo $this->language; ?>" />
			<input type="hidden" name="view"       value="<?php echo $view->get('name'); ?>" />
			<input type="hidden" name="layout"     value="<?php echo $layout; ?>" />
			<input type="hidden" name="task"       value="<?php echo $input->get->getCmd('task'); ?>" />
			<input type="hidden" name="searchword" value="<?php echo html_entity_decode($q); ?>">

			<?php if ($user->isWorker() || $user->getFlags() >= User::ROLE_MANAGER) : ?>
			<aside class="btn-group dynamic-content position-relative" role="group" aria-label="Dynamic button group" style="min-width:40px; min-height:2rem">
				<?php if ($isAutotrack) : ?>
				<div id="dynamic-hint-autotrack"
					 data-toggle="replaceElement"
					 data-animation="false"
					 data-target="#dynamic-hint-autotrack"
					 data-append="false"
					 data-prepend="false"
					 data-replace="true"
					 data-replacement-options='<?php echo $hintAutotrackRenderOptions; ?>'
				></div>
				<?php endif; ?>
				<div id="dynamic-button-autotrack"
					 data-toggle="replaceElement"
					 data-animation="true"
					 data-target="#dynamic-button-autotrack"
					 data-append="false"
					 data-prepend="false"
					 data-replace="true"
					 data-replacement-options='<?php echo $btnAutotrackRenderOptions; ?>'
					 data-submit=""
				></div>
			</aside>
			<?php endif; ?>
		</form>
	</div>
</div>

<hr>

<div class="row">
	<div class="col">
		<h4 class="sr-only"></h4>
		<div class="mb-2">
			<form action="<?php echo $view->getRoute(); ?>"
				  method="get"
				  name="searchForm"
				  class="form-inline d-block<?php echo ($isAutotrack ? ' highlighting' : ''); ?>"
				  data-submit=""
			>
				<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
				<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
				<input type="hidden" name="layout" value="<?php echo $layout; ?>" />
				<input type="hidden" name="task"   value="search" />
				<?php if (!empty($input->get->getCmd('at'))) : ?>
				<input type="hidden" name="at"     value="<?php echo $input->get->getCmd('at'); ?>" />
				<?php endif; ?>

				<?php // Search field ?>
				<div class="input-group input-group-md">
					<div class="input-group-prepend">
						<span class="input-group-text"><?php echo Text::translate('COM_FTK_INPUT_TITLE_SEARCH_TEXT', $this->language) . ':'; ?></span>
					</div>
					<input type="search"
						   name="searchword"
						   value="<?php echo html_entity_decode($q); ?>"
						   placeholder="<?php echo sprintf('%s / %s / %s',
								Text::translate('COM_FTK_INPUT_PLACEHOLDER_TRACKINGCODE_PARTIALLY_TEXT', $this->language),
								Text::translate('COM_FTK_INPUT_PLACEHOLDER_ARTICLE_TYPE_PARTIALLY_TEXT', $this->language),
								Text::translate('COM_FTK_INPUT_PLACEHOLDER_LOT_NUMBER_FULLY_TEXT', $this->language)
						   ); ?>"
						   class="form-control position-relative"
						   <?php // data-toggle="fixTrackingcodeFormat" // buggy ?>
						   id="searchParts"
						   <?php // TODO - translate ?>
						   <?php if ($isAutotrack) : ?>
						   title="AutoTrack mode is ACTIVE. Your input will be copied and may be used with next part."	<?php // TODO - translate ?>
						   data-toggle="tooltip"
						   data-trigger="hover focus"
						   data-placement="top"
						   <?php endif; ?>
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
								data-target="#searchParts"
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
				<div class="position-relative">
					<ul class="list-unstyled striped" id="<?php echo $view->get('name'); ?>-list">
					<?php array_walk($list, function($part) use(&$view, &$input, &$isAutotrack, &$q) { ?>
					<?php 	// Load data into Registry for less error prone access. ?>
					<?php	$item = new Registry($part); ?>
					<?php 	// Show all list items ?>
					<?php 	if (empty($q)) : ?>
						<li class="list-item<?php echo ($item->get('trashed') ? ' list-item-hidden d-none' : ''); ?>"
							data-rel="<?php echo mb_strtolower(html_entity_decode($item->get('trackingcode'))); ?>"
							style="margin-top:.75rem; margin-bottom:.75rem"
						>
							<div class="row">
								<div class="col d-none d-md-block px-0 pl-1 text-center" style="max-width:3rem">
									<span class="btn btn-sm btn-lock"
										  data-toggle="tooltip"
										  title="<?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('trashed') ? 'TRASHED' : ($item->get('blocked') ? 'LOCKED' : 'ACTIVE')) . '_TEXT', $this->language); ?>"
									>
										<i class="<?php echo ($item->get('trashed') ? 'far fa-trash-alt text-muted' : ($item->get('blocked') ? 'fas fa-lock' : 'fas fa-lock-open')); ?>"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('trashed') ? 'TRASHED' : ($item->get('blocked') ? 'LOCKED' : 'ACTIVE')) . '_TEXT', $this->language); ?></span>
									</span>
								</div>
								<div class="col-9 col-xl-auto px-md-0" style="font-size:1rem!important">
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d%s', $this->language, $item->get('partID'), ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '') ))); ?>"
									   class="btn btn-sm btn-link d-inline-block<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
									   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $this->language); ?>"
									   target="_blank"
									>
										<span class=""><?php echo html_entity_decode($item->get('trackingcode')); ?></span>
									</a>
									<small class="text-muted<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>">(<?php
										echo html_entity_decode($item->get('type'));
									?>)</small>
								</div>

								<?php if (0 && !$item->get('trashed')) : // DiSABLED on 20230430 because this way it was possible to edit whereas the item view did not provide the edit button ?>
								<div class="col-auto row-actions px-0 px-sm-auto">
									<?php if (is_a($user = $view->get('user'), 'Nematrack\Entity\User') && ($user->getFlags() >= User::ROLE_MANAGER)) : ?>
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d%s', $this->language, $item->get('partID'), ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '') ))); ?>"
									   role="button"
									   class="btn btn-sm btn-link btn-edit"
									   target="_blank"
									>
										<i class="fas fa-pencil-alt"></i>
										<span class="sr-only"
											  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_EDIT_THIS_TEXT', $this->language); ?>"
											  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_EDIT_THIS_TEXT', $this->language); ?>"
											  data-toggle="tooltip"
										><?php
											echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language);
										?></span>
									</a>
									<?php endif; ?>
								</div>
								<?php endif; ?>
								<div class="col-auto px-0 text-muted">
									<?php if ($item->get('lotNumber')) : ?>
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=lot&lid=%s', $this->language, $view->get('name'), $item->get('lotNumber') ))); ?>"
									   role="button"
									   class="btn btn-sm btn-link text-muted"
									   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_DETAILS_TEXT', $this->language); ?>"
									   target="_blank"
									>
										<i class="fas fa-layer-group"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language); ?></span>
									</a>
									<?php endif; ?>
								</div>
							</div>
						</li>
					<?php // Show only search result(s) ?>
					<?php else : ?>
						<li class="list-item<?php echo ($item->get('trashed') ? ' list-item-hidden d-none' : ''); ?>" style="margin-top:.75rem; margin-bottom:.75rem">
							<div class="row">
								<div class="col d-none d-md-block px-0 pl-1 text-center" style="max-width:3rem">
									<span class="btn btn-sm btn-lock"
										  data-toggle="tooltip"
										  title="<?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('trashed') ? 'TRASHED' : ($item->get('blocked') ? 'LOCKED' : 'ACTIVE')) . '_TEXT', $this->language); ?>"
									>
										<i class="<?php echo ($item->get('trashed') ? 'far fa-trash-alt text-muted' : ($item->get('blocked') ? 'fas fa-lock' : 'fas fa-lock-open')); ?>"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($item->get('trashed') ? 'TRASHED' : ($item->get('blocked') ? 'LOCKED' : 'ACTIVE')) . '_TEXT', $this->language); ?></span>
									</span>
								</div>
								<div class="col-9 col-xl-auto px-md-0" style="font-size:1rem!important">
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d%s&artid=%d', $this->language, $item->get('partID'), ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : ''),$item->get('artID') ))); ?>"
									   class="btn btn-sm btn-link d-inline-block<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
									   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $this->language); ?>"
									   target="_blank"
									>
										<span class=""><?php echo html_entity_decode($item->get('trackingcode')); ?></span>
									</a>
									<small class="text-muted<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>">(<?php
										echo html_entity_decode($item->get('type'));
									?>)</small>
								</div>
								<?php if (0 && !$item->get('trashed')) : ?>
								<div class="col-auto row-actions px-0 px-sm-auto">
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d%s', $this->language, $item->get('partID'), ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : '') ))); ?>"
									   role="button"
									   class="btn btn-sm btn-link btn-edit"
									   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_EDIT_THIS_TEXT', $this->language); ?>"
									   target="_blank"
									>
										<i class="fas fa-pencil-alt"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language); ?></span>
									</a>
								</div>
								<?php endif; ?>
								<div class="col-auto px-0 text-muted">
									<?php if ($item->get('lotNumber')) : ?>
									<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=lot&lid=%s', $this->language, $view->get('name'), $item->get('lotNumber') ))); ?>"
									   role="button"
									   class="btn btn-sm btn-link text-muted"
									   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_DETAILS_TEXT', $this->language); ?>"
									   target="_blank"
									>
										<i class="fas fa-layer-group"></i>
										<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $this->language); ?></span>
									</a>
									<?php endif; ?>
								</div>
							</div>
						</li>
					<?php endif; ?>
					<?php }); ?>
					</ul>
				</div>
				<?php endif; ?>
			</form>
		</div>
	</div>
</div>

<?php // Free memory
unset($list);
