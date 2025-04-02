<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Text;
use  \View;

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
$pid    = $input->getInt('pid');

?>
<?php /* Access check */
$formData = null;

if (is_a($user, ' \Entity\User')) :
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
$canAdd      = true;
$canEdit     = true;
$canEditPart = true;
$canEditProc = true;
$isEditProc  = $canEditProc;
?>
<?php /* Process form data */
$task   = $input->post->getCmd('task',    $input->getCmd('task'));
$format = $input->post->getWord('format', $input->getWord('format'));

if (!is_null($task)) :
	switch ($task) :
		case 'add' :
			$view->saveAdd();
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
$code         = $input->getCmd('code') ?? null;	// Tracking code
$artID        = $input->getInt('aid')  ?? null;	// Article id

$isAutotrack  = $input->getInt('at') == '1';
$isAutofill   = $input->getInt('af') == '1';
$isAutosubmit = $input->getInt('as') == '1';

// Required for the article type select.
$articles = $model->getInstance('articles', ['language' => $lang])->getList();
$projects = (array) $model->getInstance('projects', ['language' => $lang])->getProjectNumbers();

// Prepare articles dropdown list to articles grouped by project.
// Group articles by projects dropping number of projects having no articles.
$groups   = [];

\array_walk($projects, function($projectNumber) use(&$articles, &$groups)
{
	\array_walk($articles, function($article) use(&$exists, &$groups, &$projectNumber)
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
	padding: 15px 0 5px 30px;
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
    left: -37.85px;
    left: -37px;
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

fieldset.part-process-tracking {
    outline: 0 !important;
	/* padding-left: 10px;
    padding-right: 10px;
	padding-bottom: 10px; */
	/* Disabled on 08.Dec.2020 */
	/* padding: 10px; */
}
fieldset.part-process-tracking:focus {
    background: rgba(0, 123, 255, 0.1);
    box-shadow: 0 0 10px 10px rgba(0, 123, 255, 0.1);
}
</style>

<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=part&layout=add', $lang ))); ?>"
      method="post"
	  name="addPartForm"
	  class="form form-horizontal partForm validate"
	  id="addPartForm"
	  enctype="multipart/form-data"
	  data-submit=""
	  data-monitor-changes="false"
>
	<input type="hidden" name="user"     value="<?php echo $user->get('userID'); ?>" />
	<input type="hidden" name="lng"      value="<?php echo $lang; ?>" />
	<input type="hidden" name="lngID"    value="<?php echo (new Registry($view->get('model')->getInstance('language', ['language' => $lang])->getLanguageByTag($lang)))->get('lngID'); ?>" />
	<input type="hidden" name="task"     value="add" />
	<input type="hidden" name="ptid"     value="" />
	<input type="hidden" name="at"       value="<?php echo $input->get->getInt('at'); ?>" />
	<input type="hidden" name="return"   value="<?php echo base64_encode($return); ?>" />
	<input type="hidden" name="fragment" value="" /><?php // Is populated via JS whenever a BS Tabs pane toggle is clicked ?>

	<a href="<?php echo View::getInstance('parts', ['language' => $lang])->getRoute(); ?><?php echo ($isAutotrack ? "&at={$input->get->getInt('at')}" : ''); ?>"
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
	<h1 class="h2 d-inline-block mr-3"><?php
		echo ucfirst(Text::translate('COM_FTK_LABEL_PART_ADD_TEXT', $lang));
	?></h1>
	<button type="submit"
			form="addPartForm"
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
			<select name="type"
					class="form-control custom-select filterable"

					required<?php echo ($canEditProc ? '' : ' disabled'); ?>	<?php // the 'disabled' attribute prevents this data from being submitted ?>

					autofocus

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
					data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_ARTICLE_TEXT', $lang); ?>"
			>
				<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $lang); ?> &ndash;</option>
				<?php array_walk($projects, function($articles, $projectNumber) use(&$artID) { ?>
					<optgroup label="<?php echo html_entity_decode($projectNumber); ?>">
					<?php array_walk($articles, function($article) use(&$artID) { ?><?php
							// Skip blocked articles
							if ($article->get('blocked') == '1') return;
					?>
						<?php	echo sprintf('<option value="%d"%s>%s</option>',
							$article->get('artID'),
							($article->get('artID') == $artID ? ' selected' : ''),
							html_entity_decode($article->get('number'))
						); ?>
					<?php }); ?>
					</optgroup>
				<?php }); ?>
			</select>
		</div>
	</div>
	<div class="row form-group">
		<label for="name" class="col col-form-label col-md-2"><?php echo Text::translate('COM_FTK_LABEL_TRACKING_CODE_TEXT', $lang); ?>:&nbsp;&ast;</label>
		<div class="col-sm-10">
			<div class="input-group">
				<input type="text"
					   name="code"
					   value="<?php echo $code; ?>"
				       class="form-control"
					   id="ipt-part-code"
					   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_PART_ID_GENERATION_TEXT', $lang); ?>"
					   aria-live="polite"	<?php // content is dynamically set via Javascript ?>
					   aria-described-by="btn-generate-code"
					   style="letter-spacing:1px"
					   readonly
					   required
				/>
				<div class="input-group-append position-relative">
					<button type="submit"
							name="task"
							form="addPartForm"
							value="gencode"
							class="btn btn-info btn-codegen"
							id="btn-generate-code"
							title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_GENERATE_ID_TEXT', $lang); ?>"
							aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_GENERATE_ID_TEXT', $lang); ?>"
							data-toggle="generatePartID"
							data-target="#ipt-part-code"
							tabindex="<?php echo ++$tabindex; ?>"
					>
						<i class="fas fa-calculator"></i>
						<span class="sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TITLE_GENERATE_ID_TEXT', $lang); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>

	<h3 class="h5 d-inline-block mt-lg-3 mb-lg-3 mr-3"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $lang); ?></h3>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_PROCESS_ARTICLE_TYPE_DEPENDENCY_TEXT', $lang),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>
</form>

<?php // Free memory.
unset($articles);
unset($groups);
unset($projects);
?>
