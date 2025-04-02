<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Model;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$task   = $input->getWord('task');
$aid    = $input->getInt('artid');




$aid    = $input->post->getString('artid') ?? ($input->getString('artid') ?? null);	// search term
$proid    = $input->post->getString('pid') ?? ($input->getString('pid') ?? null);	// search term

$formAction = new Uri('index.php');
$formAction->setVar('hl',        $lang);
$formAction->setVar('view',      $view->get('name'));
$formAction->setVar('layout',    $layout);
$formAction->setVar('artid', (int) $aid);
$formAction->setVar('pid', (int) $proid);
$this->orgabbr    = $model->getInstance('article', ['language' => $lang])->getOrganisationAbbr();
//echo "<pre>";print_r($this->orgabbr);
?>
<?php /* Access check */
// TODO
?>
<?php /* Process form data */
//echo "";print_r($_POST);exit;
//echo $view->saveOrg();
if(isset($_POST['saveord'])){
    $view->saveOrg();
}
if (!empty($_POST)) :
    switch ($input->getWord('button')) :
        case 'submit' :
            echo "hehk";exit;
            $view->saveOrg();
            break;

        default :
            $view->closeEdit();
            break;
    endswitch;
endif;
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
		</h1>
	</div>
</div>

<hr>

<div class="row">
	<div class="col">
		<h4 class="sr-only"></h4>
		<div class="mb-2">
			<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL($formAction->toString())); ?> <?php //echo $view->getRoute(); ?>"
				  method="post"
				  name="saveOrg"
				  class="form-inline d-block"
				  data-submit=""
			>
                <input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
                <input type="hidden" name="lng"      value="<?php echo $lang; ?>" />
                <input type="hidden" name="lngID"    value="<?php echo (new Registry($model->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
                <input type="hidden" name="task"     value="<?php echo $layout; ?>" />
                <input type="hidden" name="aid"      value="<?php echo (int) $aid; ?>" />
                <input type="hidden" name="return"   value="<?php echo base64_encode('dse'); ?>" />
                <input type="hidden" name="pid"      value="<?php echo (int) $proid; ?>" />

				<div class="position-relative">
                    <select name="org_abbr"
                            class="form-control custom-select selectorgAbbr"
                            required
                            data-bind="orgabbrSelected"
                            data-rule-required="true"
                    >

                     <?php /*echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $lang); */?>
                    <?php foreach ($this->orgabbr as $option) : $option = new Registry($option); ?>
                            <?php	echo sprintf('<option value="%d"%s>%s</option>',
                                $option->get('orgID'),
                                ($option->get('orgID') == 25 ? ' selected' : ''),
                                html_entity_decode($option->get('org_abbr'))
                            ); ?>
                        <?php endforeach; ?>


                    <?php //echo "<pre>";print_r($this->orglists); ?>
                    </select>
                    <input type="submit" name="saveord" value="Save" class="btn btn-sm btn-success btn-submit btn-save left-radius-0 pr-md-3 allow-window-unload" />
<!--                    <button type="submit" form="saveOrg" name="button" value="submit" class="btn btn-sm btn-success btn-submit btn-save left-radius-0 pr-md-3 allow-window-unload">save</button>-->
				</div>

			</form>
		</div>
	</div>
</div>

<?php // Free memory

