<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$pid    = $input->getInt('pid');
$ptid   = $input->getInt('ptid');
?>
<?php /* Access check */
$formData = null;

if (is_a($user, 'Nematrack\Entity\User')) :
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
$canEdit     = true;
// $canEditLot = $canEdit;
$canEditLot = true;	//FIME
$isEditLot  = $canEditLot;
?>
<?php /* Process form data */
$task   = $input->post->getCmd('task',    $input->getCmd('task'));
$format = $input->post->getWord('format', $input->getWord('format'));

if (!is_null($task)) :
	switch ($task) :
		case 'add' :
			$view->saveAdd();
		break;

		case 'addLot' :
			$view->saveAddLot();
		break;

		case 'gencode' :
			if (mb_strtolower($format) === 'json') :
				echo $view->genCodeJSON();
				exit;
			else :
				$view->genCode();
			endif;
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
/*
// $code     = $input->get->getCmd('code')    ?? null;	// Tracking code
$artID    = $input->getInt('aid')     ?? null;	// Article id
$artNum   = $input->getCmd('article') ?? null;	// Article number
$copies   = $input->getInt('copies')  ?? null;	// Number of copies

// Required for the article type select.
$articles = $model->getInstance('articles', ['language' => $lang])->getList();
$projects = (array) $model->getInstance('projects', ['language' => $lang])->getProjectNumbers();

// Prepare articles dropdown list to articles grouped by project.
// Group articles by projects dropping number of projects having no articles.
$groups   = [];

\array_walk($projects, function($project) use(&$articles, &$groups) {
	\array_walk($articles, function($article) use(&$exists, &$groups, &$project)
	{
		if ($article->project == $project)
		{
			// Ensure the project collection exists.
			$groups[$project] = \array_key_exists($project, $groups) ? $groups[$project] : [];

			// Push article to project.
			$groups[$project][$article->artID] = $article;
		}
	});
});

// Re-assign grouped articles.
$projects = $groups;

// Free memory.
unset($articles);
unset($groups);
*/

$lotModel  = $model->getInstance('lot', ['language' => $lang]);
$lotNumber = $input->getAlnum('lid') ?? null;
$lot       = $lotModel->getLotByNumber($lotNumber);

// Assign to view for access through sub-templates.
$this->__set('lot', $lot);

// Init tabindex
$tabindex = 0;
?>

<style>
.btn-codegen {}
.btn-codegen.loading + * {
	width: 100%;
	height: 100%;
	z-index: 2;
	padding-top: 7px;
	padding-left: 8px
}
.btn-codegen.loading + * > .fas {
	font-size: 150%;
}

.timeline {
    border-left: 4px solid #ced4da;
    border-bottom-right-radius: 4px;
    border-top-right-radius: 4px;
	margin-left: 30px;
    letter-spacing: 0.5px;
    position: relative;
    line-height: 1.4em;
    padding: 15px 0 5px 50px;
    list-style: none;
    text-align: left;
}
.timeline .event {
    border-bottom: 1px dashed #ced4da;
    padding-bottom: 15px;
    margin-top: 25px;
    position: relative;
}
.timeline .event:last-of-type {
	border-bottom: none;
	margin-bottom: 0;
}
.timeline .event:before {
	width: 200px;
    left: -300px;
	content: "";
    text-align: right;
    font-weight: 100;
    font-size: 0.9em;
    min-width: 120px;
    font-family: 'Saira', sans-serif;
}
.timeline .event:after {
    box-shadow: 0 0 0 4px #ced4da;
    left: -57.85px;
    background: #b3cae9;
    border-radius: 50%;
    height: 11px;
    width: 11px;
    content: "";
    top: 7px;
}
.timeline .event:before, .timeline .event:after {
    position: absolute;
    display: block;
}

#partProcesses.loading {
	min-height: 200px;
	background: url('<?php echo UriHelper::osSafe( UriHelper::fixURL( '/assets/img/global/spinner.gif' ) ); ?>') center center no-repeat;
}
#partProcesses.loading > .alert,
#partProcesses.loaded > .alert {
	display: none;
}
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=edit_lot', $lang ))); ?>"
      method="post"
	  name="editLotForm"
	  class="form form-horizontal partForm validate"
	  id="editLotForm"
	  data-submit=""
	  data-monitor-changes="true"
>
	<input type="hidden" name="user"  value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"   value="<?php echo $lang; ?>" />
	<input type="hidden" name="lngID" value="<?php echo (new Registry($view->get('model')->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
	<input type="hidden" name="task"  value="addLot" />
	<input type="hidden" name="ptid"  value="" />

	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=parts&layout=lot&lid=%s', $lang, $lotNumber ))); ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?>"
	   style="vertical-align:super; color:inherit!important"
	   onclick="window.close();"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $lang); ?></span>
	</a>
	<h1 class="h3 d-inline-block mr-3"><?php
		echo ucfirst(Text::translate('COM_FTK_LABEL_LOT_EDIT_TEXT', $lang));
	?></h1>
	<button type="submit"
			form="addLotForm"
			name="action"
			value="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
			title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			style="vertical-align:super; padding:0.375rem 0.8rem"
			onclick="alert('<?php echo Text::translate('COM_FTK_SYSTEM_MESSAGE_FEATURE_NOT_YET_IMPLEMENTED_TEXT', $lang); ?>');"
	>
		<i class="fas fa-save"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $lang); ?></span>
	</button>

	<hr>

	<h2 class="h4 mb-4 mt-lg-4"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?></h2>

	<div class="row form-group">
		<label for="type" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $lang); ?>:</label>
		<div class="col">
			<input type="text"
				   name="article"
				   class="form-control"
				   value="<?php echo $lot->get('type'); ?>"
				   readonly
				   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
			/>
		</div>
	</div>
	<div class="row form-group">
		<label for="totalDuplicates" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_PARTS_TEXT', $lang); ?>:</label>
		<div class="col">
			<input type="text"
				   name="copies"
				   class="form-control"
				   value="<?php echo (int) count($lot->get('parts', [])); ?>"
				   readonly
				   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
			/>
		</div>
	</div>

	<?php if (FALSE) : ?>
	<h3 class="h5 d-inline-block mt-lg-5 mb-lg-3 mr-3"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $lang); ?></h3>
	<?php endif; ?>

	<?php // W A R N I N G   about changes to be applied ?>
	<?php echo LayoutHelper::render('system.alert.notice', [
		'heading' => Text::translate('COM_FTK_HEADING_ATTENTION_TEXT', $lang), 
		'message' => Text::translate('COM_FTK_HINT_EDIT_LOT_TEXT', $lang),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>

	<?php if (is_a($lot, 'Nematrack\Entity\Lot')) : ?>
	<div class="mt-lg-3" id="partProcesses">
		<?php echo LayoutHelper::render('forms.article.process_tree', ['task' => 'edit', 'article' => $lot->get('type')] , ['language' => $lang]); ?>
	</div>
	<?php endif; ?>

	<input type="hidden" name="type" value="<?php echo (int) $lot->get('artID'); ?>" />
</form>
