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
$mainGroupsN         = $view->get('mainGroupsn', []);
$mainGroups         = $view->get('mainGroups', []);			// all registered main groups
$mainGroupsFilter   = $view->get('mainGroupsFilter');		// selected main group(s)
$mainGroupsFiltered = $view->get('mainGroupsFiltered', []);	// selected main group(s) data extracted from main groups
$mainGroupsList     = array_keys($mainGroups);

$listCount  = count($view->get('list'));

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
.maingroupblock .input-group {
    width: auto;
    padding: 0 4px;
}
</style>

<div class="row">
	<div class="col" style="display: flex;">
		<h1 class="h3 viewTitle d-inline-block my-0 mr-3">
			<?php echo "Main Group";//echo $viewTitle; ?>
			<?php //echo ($cnt ? sprintf('<small class="text-muted ml-2">(%d)</small>', $cnt) : ''); ?>
		</h1>

		<?php /* ADD-button */ ?>
		<?php if ($user->isManager() || $user->isAdministrator() || $user->isProgrammer() || $user->isSuperuser()) : // Management is permitted to managers only ?>
		<div class="maingroupblock d-inline-block align-top" style="width: 840px;display: flex !important;">
			<div class="input-group">
				<div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-plus text-success"></i>
					</span>
				</div>
				<a href<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=maingroup&layout=add', $this->language ))); ?>"
				   role="button"
				   class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
				   data-bind="windowOpen"
				   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=maingroup&layout=add&return=%s', $this->language, base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
				   style="padding:0.375rem 0.8rem"
				   tabindex="<?php echo ++$tabindex; ?>"
				>
					<span title="Add new main group"
						  aria-label="Add new main group"
						  data-toggle="tooltip"
					>
						<span class="btn-text d-none d-md-inline ml-md-1 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
							echo "Add Main Group";//echo Text::translate('COM_FTK_LABEL_PROJECT_ADD_TEXT', $this->language);
						?></span>
					</span>
				</a>
			</div>
            <div class="input-group">
                <div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-plus text-success"></i>
					</span>
                </div>

                <a href<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=maingroups&layout=add', $this->language ))); ?>"
                role="button"
                class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
                data-bind="windowOpen"
                data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=maingroups&layout=add&return=%s', $this->language, base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
                style="padding:0.375rem 0.8rem"
                tabindex="<?php echo ++$tabindex; ?>"
                >
                <span title="Add subgroup to existing main group"
                      aria-label="Add subgroup to existing main group"
                      data-toggle="tooltip"
                >
						<span class="btn-text d-none d-md-inline ml-md-1 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
                            echo "Add subgroup";
                            ?></span>
					</span>
                </a>
            </div>
            <div class="input-group">
                <div class="input-group-prepend">
					<span class="input-group-text">
						<i class="fas fa-plus text-success"></i>
					</span>
                </div>

                <a href<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=mgsearch&layout=add', $this->language ))); ?>"
                role="button"
                class="btn btn-sm btn-custom align-super left-radius-0 pr-md-3 allow-window-unload"
                data-bind="windowOpen"
                data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=mgsearch&layout=add&return=%s', $this->language, base64_encode( basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() ) ) ))); ?>"
                style="padding:0.375rem 0.8rem"
                tabindex="<?php echo ++$tabindex; ?>"
                >
                <span title="Add subgroup to existing main group"
                      aria-label="Add subgroup to existing main group"
                      data-toggle="tooltip"
                >
						<span class="btn-text d-none d-md-inline ml-md-1 text-<?php echo ($this->language == 'de') ? 'capitalize' : 'lowercase'; ?>"><?php
                            echo "Assign subgroup to Main Group";
                            ?></span>
					</span>
                </a>
            </div>
		</div>
		<?php endif; ?>
	</div>
</div>

<hr>

<?php // Projects list ?>
<div class="position-relative" data-items="<?php //echo count($list); ?>">
    <ul class="list-unstyled striped">
        <?php foreach ($view->get('mainGroupsn') as $group) : ?>
            <li class="list-item">
                <div class="row">
                    <div class="col-10 col-xl-auto px-md-0" style="font-size:1rem!important;margin-left: 12px;">
                        <a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=mgsearch&layout=list&mgrp=%s', $this->language, $group))); ?>"
                           class="btn btn-sm btn-link d-inline-block text-left<?php //echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
                           data-bind="windowOpen"
                           data-location="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=mgsearch&layout=list&mgrp=%s', $this->language, $group))); ?>"
                           data-location-target="_blank"
                           target="_blank"
                           style="min-width:3rem"
                        ><i style="color: #212529;" class="fas fa-layer-group"></i>
                        <span style="color: #212529;" title="View project"
                              aria-label="View project"
                              data-toggle="tooltip"
                        ><?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>


<?php // Free memory
unset($list);
?>
