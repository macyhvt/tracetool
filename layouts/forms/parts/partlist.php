<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang     = $this->get('language');
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
$articleID = $input->get->getCmd('artid');
$processID = $input->get->getCmd('procid');
$pdid = "process-".$processID;
$ptid = $input->post->getInt('ptid') ?? null;	// partID
$pid  = $input->post->getInt('pid')  ?? null;	// procID
$q    = $input->post->getString('searchword') ?? ($input->getString('searchword') ?? null);	// search term

$aid    = $input->post->getString('artid') ?? ($input->getString('artid') ?? null);	// search term
$proid    = $input->post->getString('procid') ?? ($input->getString('procid') ?? null);	// search term
$mpid    = $input->post->getString('mp') ?? ($input->getString('mp') ?? null);	// search term

$isAutotrack      = $input->getInt('at') == '1';
$isAutotrackClass = ($isAutotrack ? 'isAutotrack' : '');
?>
<?php /* Access check */
// TODO
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :

		case 'search' :
			if (true) :
				$list = (array) $view->mikesearch($aid,$proid,$mpid);
            //print_r($list);
			else :

			endif;
		break;
		case 'window.close' :
			echo '<script>window.close(),</script>';
		break;
	endswitch;
else :
	$user->__unset('formData');
endif;
$cnt = count($list);
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
			<?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $lang); ?>
			<?php echo ($cnt ? sprintf('<small class="text-muted ml-2">(%d)</small>', $cnt) : ''); ?>
		</h1>
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
				<input type="hidden" name="hl"     value="<?php echo $lang; ?>" />
				<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
				<input type="hidden" name="layout" value="<?php echo $layout; ?>" />
				<input type="hidden" name="task"   value="search" />
				<?php if (!empty($input->get->getCmd('at'))) : ?>
				<input type="hidden" name="at"     value="<?php echo $input->get->getCmd('at'); ?>" />
				<?php endif; ?>

				<?php // Search field ?>


				<?php if (!empty($q) && !$cnt) : ?>
					<?php echo LayoutHelper::render('system.alert.info', [
						'message' => Text::translate('COM_FTK_HINT_NO_SEARCH_RESULT_TEXT', $lang),
						'attribs' => [
							'class' => 'alert-sm'
						]
					]); ?>
				<?php endif; ?>

				<?php // Results list ?>
				<?php if (is_array($list)) : ?>
				<div class="position-relative">
					<ul class="list-unstyled striped" id="<?php echo $view->get('name'); ?>-list">
					<?php array_walk($list, function($part) use(&$view, &$input, &$isAutotrack, &$lang, &$q, $articleID, $processID, $pdid) { ?>
					<?php 	// Load data into Registry for less error prone access. ?>
					<?php	$item = new Registry($part); //echo "<pre>"; print_r($item); ?>
					<?php //print_r($item);	// Show all list items ?>

						<li class="list-item<?php echo ($item->get('trashed') ? ' list-item-hidden d-none' : ''); ?>" style="margin-top:.75rem; margin-bottom:.75rem">
							<div class="row">

								<div class="col-9 col-xl-auto px-md-0" style="font-size:1rem!important">
									<a href="<?php
                                    echo UriHelper::osSafe( UriHelper::fixURL(sprintf(
                                            'index.php?hl=%s&view=part&layout=item&ptid=%d%s&artid=%d&lotid=%d#%s',
                                            $lang,
                                            $item->get('partID'),
                                            ($isAutotrack ? sprintf('&at=%d', $input->get->getInt('at')) : ''),
                                        $articleID,
                                        $processID,
                                        $pdid
                                    ))); ?>"
									   class="btn btn-sm btn-link d-inline-block<?php echo ($item->get('trashed') ? ' text-line-through' : ''); ?>"
									   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $lang); ?>"
									   target="_blank"
									>
										<span class=""><?php echo html_entity_decode($item->get('trackingcode')); ?></span>
									</a>

								</div>

							</div>
						</li>

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
