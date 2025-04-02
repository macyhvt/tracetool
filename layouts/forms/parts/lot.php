<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
// $return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');

$list     = (array) $view->get('list');
$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task') ?? ($input->getCmd('task') ?? null);
$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);

$lotModel  = $model->getInstance('lot', ['language' => $lang]);
$lotNumber = $input->getAlnum('lid');
$item      = $lotModel->getLotByNumber($lotNumber);

// Block the attempt to open a non-existing article.
if (!is_a($item, 'Nematrack\Entity\Lot') || (is_a($item, 'Nematrack\Entity\Lot') && is_null($item->get('lotID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_LOT_HAVING_NUMBER_X_NOT_FOUND_TEXT', $this->language), $item->get('lotID'))
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('parts', ['language' => $lang])->getRoute());
		exit;
    endif;

    return false;
endif;

// Assign to view for access through sub-templates.
$this->__set('lot', $item);

$isPrint   = $task === 'print';

if (!is_null($task)) :
	switch ($task) :
	endswitch;

	// Get updated lot list
	// $item  = $lotModel->getLotByNumber($lotNumber);
else :
	// Delete form data from user session as it is not required any more.
	$user->__unset('formData');
endif;

// Get reference to the parts list of this lot.
$list = (is_a($item, 'Nematrack\Entity\Lot') ? $item->getParts() : []);

// Get related article.
$article = $lotModel->getInstance('article')->getItem((int) $item->get('artID'));

$cnt = count($list);

// Init tabindex
$tabindex = 0;
?>

<?php if (!$isPrint) : ?>
<a href="<?php echo $return; ?>"
   role="button"
   class="btn btn-link"
   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
   data-bind="windowClose"
   data-force-reload="true"
   style="vertical-align:super; color:inherit!important"
>
	<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
	<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?></span>
</a>
<h1 class="h3 d-inline-block mr-3">
	<?php echo Text::translate('COM_FTK_LABEL_LOT_TEXT', $lang); ?>

	<?php if (!$isPrint) : ?>
	<?php echo (is_a($item, 'Nematrack\Entity\Lot')
			? sprintf('<abbr class="small text-muted text-decoration-none ml-2 count" data-toggle="tooltip" title="%s">(%d)</abbr>',
				Text::translate('COM_FTK_HINT_LOT_ABBREVIATION_TOTAL_ITEMS_INCLUDED_TEXT', $lang),
				count($list)
			)
			: ''
	); ?>
	<?php endif; ?>
</h1>

<?php if ($user->getFlags() >= \Nematrack\Access\User::ROLE_PROGRAMMER) : // Limit access to developer(s) and superuser until implementation is completed. ?>
<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit_lot&lid=%s', $lang, $lotNumber ))); ?>"
   role="button"
   class="btn btn-info"
   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_EDIT_THIS_TEXT', $lang); ?>"
   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_LOT_EDIT_THIS_TEXT', $lang); ?>"
   style="vertical-align:super"
>
	<i class="fas fa-pencil-alt"></i>
	<span class="d-none d-lg-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_LOT_EDIT_TEXT', $lang); ?></span>
</a>
<?php endif; ?>

<?php if ($user->getFlags() >= User::ROLE_MANAGER) : // Deletion is permitted to privileged users only ?>
<?php if (FALSE) : ?>
<form action="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?>"
	  method="post"
	  name="deleteLotForm"
	  class="form-horizontal d-inline-block"
	  data-submit=""
	  onclick="alert('// TODO - implement'); return false;"
>
	<input type="hidden" name="user"  value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"   value="<?php echo $lang; ?>" />
	<input type="hidden" name="lngID" value="<?php echo (new Registry($view->get('model')->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
	<input type="hidden" name="task"  value="delete" />
	<input type="hidden" name="lid"   value="<?php echo (int) $item->get('lotID'); ?>" />

	<button type="submit"
			class="btn btn-danger btn-trashbin"
			title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_LOT_DELETE_THIS_TEXT', $lang); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_LOT_DELETE_THIS_TEXT', $lang); ?>"
			onclick="return confirm('<?php echo Text::translate('COM_FTK_DIALOG_LOT_CONFIRM_DELETION_TEXT', $lang); ?>')"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="far fa-trash-alt"></i>
		<span class="d-none d-lg-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_LOT_DELETE_TEXT', $lang); ?></span>
	</button>
</form>
<?php else : ?>
<div class="form-horizontal d-inline-block">
	<button type="submit"
			class="btn btn-danger btn-trashbin"
			onclick='window.FTKAPP.functions.renderMessage(<?php 
				echo json_encode(['type' => 'info', 'text' => sprintf('%s<br>%s', 
					Text::translate('COM_FTK_SYSTEM_MESSAGE_DELETION_IS_NOT_PERMITTED_TEXT', $lang),
					Text::translate('COM_FTK_HINT_CONTACT_THE_PROGRAMMER_FOR_ASSISTANCE_TEXT', $lang)
				)]); ?>, <?php 
				echo json_encode(['autohide' => "false"]);
			?>);'
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="far fa-trash-alt"></i>
		<span class="d-none d-lg-inline ml-lg-2"><?php echo Text::translate('COM_FTK_LABEL_LOT_DELETE_TEXT', $lang); ?></span>
	</button>
</div>
<?php endif; ?>
<?php endif; ?>

<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=parts&layout=lot&task=print&lid=%s', $lang, $item->get('number') ))); ?>"
   role="button"
   class="btn btn-info float-right"
   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_EXPORT_TO_PRINT_TEXT', $lang); ?>"
   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_EXPORT_TO_PRINT_TEXT', $lang); ?>"
   tabindex="<?php echo ++$tabindex; ?>"
   target="_blank"
   style="vertical-align:super"
>
	<i class="fas fa-file-export"></i>
	<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_LABEL_EXPORT_TO_PRINT_TEXT', $lang); ?></span>
</a>
<?php endif; //-> !isPrint ?>

<?php if (!$isPrint) : ?>
<?php 	if (!$user->isGuest() && !$user->isCustomer() && !$user->isSupplier()) : ?>
	<?php echo LayoutHelper::render('system.element.metadata', ['item' => $item, 'hide' => []], ['language' => $lang]); ?>
<?php 	endif; ?>

<hr>
<?php endif; ?>

<?php // Parts list ?>
<?php if (!$isPrint) : ?>
	<?php if (is_array($list)) : ?>
<div class="position-relative">
	<ul class="list-unstyled striped" id="parts-list">
	<?php \array_walk($list, function($part) use(&$lang, &$item) { ?>
	<?php 	// Load data into Registry for less error prone access. ?>
	<?php	$part = new Registry($part); ?>
		<li class="list-item" data-rel="<?php echo mb_strtolower(html_entity_decode($part->get('trackingcode'))); ?>" style="margin-top:.75rem; margin-bottom:.75rem">
			<div class="row">
				<?php if (FALSE) : // Disable buttons ?>
				<div class="col d-none d-md-block px-0 pl-1 text-left text-muted" style="max-width:4rem">
					<span class="btn btn-sm btn-entity-id">
						<span class="pl-1">#</span><?php echo (int) $part->get('partID'); ?>
					</span>
				</div>
				<?php endif; ?>
				<div class="col d-none d-md-block px-0 pl-1 text-center" style="max-width:3rem">
					<span class="btn btn-sm btn-lock"
						  data-toggle="tooltip"
						  title="<?php echo Text::translate('COM_FTK_STATUS_' . ($part->get('blocked') ? 'LOCKED' : 'ACTIVE') . '_TEXT', $lang); ?>"
					>
						<i class="fas fa-lock<?php echo $part->get('blocked') ? '' : '-open'; ?>"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_STATUS_' . ($part->get('blocked') ? 'LOCKED' : 'ACTIVE') . '_TEXT', $lang); ?></span>
					</span>
				</div>
				<div class="col-10 col-xl-auto px-md-0" style="font-size:1rem!important">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=item&ptid=%d', $lang, $part->get('partID') ))); ?>"
					   class="btn btn-sm btn-link d-inline-block"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $lang); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $lang); ?>"
					>
						<span class=""><?php echo html_entity_decode($part->get('trackingcode')); ?></span>
					</a>
					<small class="text-muted">(
						<?php echo html_entity_decode($item->get('type')); ?>
					)</small>
				</div>

				<?php if (0 && !$item->get('trashed')) : // DiSABLED on 20230430 because this way it was possible to edit whereas the item view did not provide the edit button ?>
				<div class="col-auto row-actions px-0 px-sm-auto">
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit&ptid=%d', $lang, $part->get('partID') ))); ?>"
					   role="button"
					   class="btn btn-sm btn-link btn-edit"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_EDIT_THIS_TEXT', $lang); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_EDIT_THIS_TEXT', $lang); ?>"
					>
						<i class="fas fa-pencil-alt"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EDIT_TEXT', $lang); ?></span>
					</a>
				</div>
				<?php endif; ?>
			</div>
		</li>
	<?php }); ?>
	</ul>
</div>
	<?php endif; ?>
<?php else : ?>
	<style>body {margin:0 !important}</style>
	<?php include_once __DIR__ . sprintf('/%s/print/portrait.php', strtolower($layout)); ?>
<?php endif; //-> !isPrint ?>

<?php // Free memory
unset($list);
unset($item);
?>

<?php if ($isPrint) : ?>
<script>window.print();</script>
<?php endif; ?>
