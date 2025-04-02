<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
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
$canAdd    = true;
$canAddLot = true;
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
$artNum   = $input->getCmd('article');	// Article number
$copies   = $input->getInt('copies');	// Number of copies
$artID    = null;

// Required for the article type select.
$articles = $model->getInstance('articles', ['language' => $lang])->getList();
$projects = (array) $model->getInstance('projects', ['language' => $lang])->getProjectNumbers();

// Prepare articles dropdown list to articles grouped by project.
// Group articles by projects dropping number of projects having no articles.
$groups   = [];

array_walk($projects, function($projectNumber) use(&$articles, &$groups)
{
	array_walk($articles, function($article) use(&$exists, &$groups, &$projectNumber)
	{
		$article = new Registry($article);

		if ($projectNumber === $article->get('project', null))
		{
			// Ensure the project collection exists.
			$groups[$projectNumber] = ArrayHelper::getValue($groups, $projectNumber, []);

			// Push article to project.
			$groups[$projectNumber][$article->get('artID')] = $article;
		}
	});
});

// Re-assign grouped articles.
$projects = $groups;

// Free memory.
unset($articles);
unset($groups);

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

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=add_lot', $lang ))); ?>"
      method="post"
	  name="addLotForm"
	  class="form form-horizontal partForm validate"
	  id="addLotForm"
	  enctype="multipart/form-data"
	  data-submit=""
	  <?php if (!is_null($artNum)) : // Don't bother users with "losing changes" message until there's no tracking data form ?>
	  data-monitor-changes="true"
	  <?php endif; ?>
>
	<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $lang; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($view->get('model')->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="addLot" />
	<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<a href="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?>"
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
	<h1 class="h3 d-inline-block mr-3"><?php
		echo ucfirst(Text::translate('COM_FTK_LINK_TITLE_LOT_CREATE_TEXT', $lang));
	?></h1>
	<button type="submit"
			form="addLotForm"
			name="action"
			value="submit"
	        class="btn btn-warning btn-submit btn-save allow-window-unload"
			title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SAVE_CHANGE_TEXT', $lang); ?>"
			style="vertical-align:super; padding:0.375rem 0.8rem"
	>
		<i class="fas fa-save"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SAVE_TEXT', $lang); ?></span>
	</button>

	<hr>

	<h2 class="h4 mb-4 mt-lg-4"><?php echo Text::translate('COM_FTK_LABEL_MASTER_DATA_TEXT', $lang); ?></h2>

	<div class="row form-group">
		<label for="type" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $lang); ?>:&nbsp;&ast;</label>
		<div class="col">
			<select name="article"
					class="form-control custom-select filterable"
					data-bind="changeFormLocation"
					required<?php echo $canAddLot ? '' : ' disabled'; ?>	<?php // the 'disabled' attribute prevents this data from being submitted ?>
					<?php if (is_null($artNum)) : ?>
					autofocus
					<?php endif; ?>
					tabindex="<?php echo ++$tabindex; ?>"
					data-chosen-case-sensitive-search="false"
					data-chosen-disable-search="false"
					data-chosen-disable-search-threshold="10"
					data-chosen-display-disabled-options="false"
					data-chosen-include-group-label-in-selected="true"
					data-chosen-max-selected-options="1"
					data-chosen-no-results-text="<?php echo Text::translate('COM_FTK_LIST_OPTION_NOT_FOUND_TEXT', $lang); ?>"
					data-chosen-placeholder-text-multiple="<?php echo Text::translate('COM_FTK_HINT_MAKE_AT_LEAST_SELECTION_N_1_TEXT', $lang); ?>"
					data-chosen-placeholder-text-single="<?php echo Text::translate('COM_FTK_LIST_OPTION_PLEASE_SELECT_TEXT', $lang); ?>"
					data-chosen-rtl="false"
					data-rule-required="true"
					data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $lang); ?>"
			>
				<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $lang); ?> &ndash;</option>
				<?php array_walk($projects, function($articles, $projectNumber) use(&$artID, &$artNum) { ?>
					<optgroup label="<?php echo html_entity_decode($projectNumber); ?>">
					<?php array_walk($articles, function($article) use(&$artID, &$artNum) { ?><?php
							// Skip blocked articles
							if ($article->get('blocked') == '1') return;

							// Detect whether this article matches the one selected from the list
							// and dump its ID for output into the hidden field below.
							if (trim($article->get('number')) == trim($artNum)) :
								$artID = $article->get('artID');
							endif;
					?>
						<?php	echo sprintf('<option value="%s"%s>%s</option>',
							html_entity_decode($article->get('number')),
							(trim($article->get('number')) == trim($artNum) ? ' selected' : ''),
							html_entity_decode($article->get('number'))
						); ?>
					<?php }); ?>
					</optgroup>
				<?php }); ?>
			</select>
		</div>
	</div>
	<div class="row form-group">
		<label for="totalDuplicates" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LINK_TITLE_NUMBER_OF_PARTS_SHORT_TEXT', $lang); ?>:&nbsp;&ast;</label>
		<div class="col">
			<input type="number"
				   name="copies"
				   class="form-control"
				   id="totalDuplicates"
				   min="2"
				   max="1000"
				   step="1"
				   value="<?php echo $copies; ?>"
				   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_NUMBER_OF_DUPLICATES_TEXT', $lang); ?>"
				   required
				   <?php if (!is_null($artNum)) : ?>
				   autofocus
				   <?php endif; ?>
				   tabindex="<?php echo ++$tabindex; ?>"
				   data-rule-required="true"
				   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $lang); ?>"
				   data-rule-min="2"
				   data-msg-min="<?php echo sprintf(Text::translate('COM_FTK_HINT_NUMBER_MUST_BE_GREATER_THAN_X_TEXT', $lang), '1'); ?>"
				   data-rule-max="1000"
				   data-msg-max="<?php echo sprintf(Text::translate('COM_FTK_HINT_NUMBER_MUST_BE_LOWER_THAN_X_TEXT', $lang), '1000'); ?>"
			/>
		</div>
	</div>

	<?php if (!is_null($artNum)) : ?>
	<?php // W A R N I N G   about data to be stored ?>
	<?php echo LayoutHelper::render('system.alert.notice', [
		'heading' => Text::translate('COM_FTK_HEADING_ATTENTION_TEXT', $lang),
		'message' => Text::translate('COM_FTK_HINT_LOT_GENERATION_INFORMATION_DUPLICATION_TEXT', $lang),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>

	<div class="mt-lg-3" id="partProcesses">
		<?php echo LayoutHelper::render('forms.article.process_tree', ['task' => 'add'], ['language' => $lang]); ?>
	</div>
	<?php endif; ?>

	<input type="hidden" name="type" value="<?php echo (int) $artID; ?>" />
</form>

<?php // Free memory.
unset($articles);
unset($groups);
unset($projects);
?>
