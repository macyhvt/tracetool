<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Entity\Process;
use  \Helper\UriHelper;
use  \Text;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang    = $this->get('language');
$view    = $this->__get('view');
$input   = (is_a($view, ' \View') ? $view->get('input') : App::getInput());
$model   = (is_a($view, ' \View') ? $view->get('model') : null);
$user    = (is_a($view, ' \View') ? $view->get('user')  : App::getAppUser());

// N O T E :
// The next two lines appeared to be necessary when requesting this template via AJAX.
// It is supposed that the view initialization which happens in index.php is bypassed when requesting content this way.
$view    = (is_null($view) ? ($input->post->getWord('view') ?? ($input->getWord('view') ?? null)) : $view);
$view    = (is_a($view, ' \View') ? $view : View::getInstance($view, ['language' => $this->language]));
$input   = (is_a($view, ' \View') ? $view->get('input') : $input);
$model   = (is_a($view, ' \View') ? $view->get('model') : $model);

$task    = $this->get('task');
$article = $this->get('article', ($input->getCmd('article') ?? null));	// Attempt to fetch article name from layout data first and fall back to $_GET
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
$canCreate = true;
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$userOrganisation = $model->getInstance('organisation', ['language' => $this->language])->getItem((int) $user->get('orgID'));

// Get hands on processes this user's organization is reponsible for. Its required for access control.
$orgName       = $userOrganisation->get('name');
$orgProcesses  = (array) $userOrganisation->get('processes', []);
//$orgProcessIDs = array_keys($orgProcesses);	// FALSE: array_keys() collects the indices rather than the IDs
$orgProcessIDs = array_values($orgProcesses);

$article   = $model->getInstance('article',   ['language' => $this->language])->getArticleByNumber($article);
$processes = $model->getInstance('processes', ['language' => $this->language])->getList(['params' => true]);

// Link article processes list from the article this part inherits from to this port for accessibility.
$artProcesses = $article->get('processes', []);

// Get those technical parameters that are fixed and required by every technical parameter.
// These will be filled from system- or user data.
$staticTechParams = (array) $model->getInstance('techparams', ['language' => $this->language])->getStaticTechnicalParameters(true);

// Get error catalogue for this part's processes.
//$errors    = $model->getInstance('errors', ['language' => $this->language])->getErrorsByLanguage($view->get('lang')->get('lngID'), $partProcessIDs);

$now       = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
$dateNow   = $now->format('Y-m-d');
$timeNow   = $now->format('H:i:s');

$userModel = $model->getInstance('user', ['language' => $this->language]);
$created   = $article->get('created');
$creator   = $userModel->getItem((int) $article->get('created_by'));
$modified  = $article->get('modified');
$modifyer  = $userModel->getItem((int) $article->get('modified_by'));

// Init tabindex
$tabindex = 0;
?>

<div id="articleProcessTree">
	<h3 class="h5 d-inline-block mb-lg-3 mr-3"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_TREE_TEXT', $this->language); ?></h3>

	<ul class="list-unstyled timeline" id="partProcesses">
		<?php // Iterate over the list of processes assigned to an article and load the related process from the processes list using the article process' id ?>
		<?php foreach ($artProcesses as $artProcID => $artProc) : ?><?php
			$process    = ArrayHelper::getValue($processes, $artProcID, new stdclass);
			$process    = (new Process(['language' => $this->language]))->bind($process);
			$drawing    = new Registry($artProc->get('drawing'));
			$filePDF    = (is_file(FTKPATH_BASE . $drawing->get('file')) && is_readable(FTKPATH_BASE . $drawing->get('file')))
							? $drawing->get('file')
							: null;
			$images     = (array) $drawing->get('images');
			$image      = count($images) ? current($images) : null;
			$image      = ((!is_null($image) && is_file(FTKPATH_BASE . $image) && is_readable(FTKPATH_BASE . $image))
							? UriHelper::osSafe( UriHelper::fixURL($image . '?t=' . mt_rand(0, 9999999)) )
							: sprintf('%s/288x204/E9ECED/FF0000.png?text=%s',
									FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER,
									urlencode(Text::translate('COM_FTK_HINT_NO_THUMBNAIL_TEXT', $this->language))
							)
			);

			$techParams = (array) $process->get('tech_params');

			$readOnly   = !in_array($process->get('procID'), $orgProcessIDs);
		?>
		<li class="list-item event partProcess position-relative"
			data-proc-id="<?php echo html_entity_decode($process->get('procID')); ?>"
			data-pid="<?php echo $artProcID; ?>"
			<?php echo $readOnly ? 'disabled' : ''; ?>	<?php // the 'disabled' attribute prevents this data from being submitted ?>
		>
			<h5 class="h6 text-uppercase d-inline-block mr-lg-3 mb-4"><?php echo html_entity_decode($process->get('name')); ?></h5>
			<small class="d-none d-md-inline mr-3 text-muted" style="vertical-align:baseline">(
				<?php echo html_entity_decode($drawing->get('number')); ?>
			)</small>

			<?php // den Anker nur an dem Prozess ausgeben, der bearbeitet wird, weil sonst die anderen Buttons zu ihrem jeweiligen Anker scrollen anstatt das Link-Ziel zu öffnen ?>
			<span class="d-inline" style="margin-left:2px">&nbsp;</span>

			<?php if (!is_null($filePDF)) : ?>
			<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $filePDF, mt_rand(0, 9999999) ))); ?>"
			   class="float-right"
			   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_OPEN_PROCESS_DRAWING_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_OPEN_PROCESS_DRAWING_TEXT', $this->language); ?>"
			   target="_blank"
			   rel="nofollow noreferrer"
			>
				<i class="far fa-file-pdf icon-pdf" style="vertical-align:middle; font-size:1.5rem"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_OPEN_PROCESS_DRAWING_TEXT', $this->language); ?></span>
			</a>
			<?php else : ?>
			<span class="px-2 py-1 text-muted float-right"
				  title="<?php echo Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $this->language); ?>"
			>
				<i class="far fa-file" style="opacity:.5; vertical-align:bottom; font-size:1.5rem"></i>
			</span>
			<?php endif; ?>

			<div class="position-absolute" style="right:0; margin-right:1px; background:red">
				<figure class="figure bg-white m-md-auto">
					<?php // TODO - integrate rendering of article image or a Placeholder image like is done in edit.php ... pass required data to the layout for output preparation ?>
					<?php if (TRUE) : ?>
					<a href="<?php echo (is_null($filePDF)) ? 'javascript:void(0)' : UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $filePDF, mt_rand(0, 9999999) ))); ?>"
					   class="chocolat-image d-block"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_DRAWING_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_DRAWING_TEXT', $this->language); ?>"
					   target="_blank"
					>
						<img src="<?php echo $image; // URI is fixed above ?>"
							 width=""
							 height=""
							 alt=""
							 style="box-shadow:0 0 1px 1px #ced4da"
						/>
						<!--figcaption class="figure-caption pt-5 h5"></figcaption-->
					</a>
					<?php else : ?>
						<?php // render drawing placeholder image ?>
						<?php // echo LayoutHelper::render('image.placeholder.article', new stdclass, ['language' => $this->language]); ?>
						<img src="<?php echo $image; // URI is fixed above ?>"
						     width=""
						     height=""
						     alt="<?php echo Text::translate('COM_FTK_HINT_NO_THUMBNAIL_TEXT', $this->language); ?>"
						     style="box-shadow:0 0 1px 1px #ced4da"
						/>
					<?php endif; ?>
				</figure>
			</div>

			<?php // Iterate over all technical parameters assigned to a process and take its ID to read a potential input from the parts technical parameters data provided via user input ?>
			<?php $cntStaticParams = count($staticTechParams); ?>

			<?php array_filter($techParams, function($tparam, $id) use(&$drawing, &$artProcID, &$process, &$staticTechParams, &$cntStaticParams, &$userOrganisation, &$user, &$task, &$dateNow, &$timeNow, &$tabindex, &$readOnly) { ?>
			<?php	$tparam = new Registry($tparam); ?>
			<div class="row form-group" data-proc-id="<?php echo html_entity_decode($process->get('procID')); ?>" data-param-id="<?php echo html_entity_decode($id); ?>">
				<label for="procParams" class="col col-form-label col-4"><?php echo html_entity_decode($tparam->get('name')); ?>:</label>
				<div class="col<?php echo (int) $id < ($cntStaticParams - 1) ? ' col-lg-4' : ''; ?>">
					<input type="text"
						   name="procParams[<?php echo html_entity_decode($process->get('procID')); ?>][<?php echo html_entity_decode($id); ?>]"
						   class="form-control"
						   tabindex="<?php echo ++$tabindex; ?>"
						   <?php if (!property_exists($tparam, 'fieldname') && !array_key_exists($id, $staticTechParams)) : ?>
							<?php // do nothing ?>
						   <?php else : // field is one of those staticTechParam fields ?>
							<?php if ($tparam->get('fieldname') !== 'annotation') : ?>
						   readonly
						   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
						   placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_AUTO_FILLED_WHEN_SAVING_TEXT', $this->language); ?>"
							<?php endif; ?>
						   value="<?php
							switch ($tparam->get('fieldname')) : ?><?php
								case 'operator' :
									// echo html_entity_decode($user->get('fullname'));
								break;

								case 'organisation' :
									// echo html_entity_decode($userOrganisation->get('name'));
								break;

								case 'date' :
									// echo $dateNow;
								break;

								case 'time' :
									// echo $timeNow;
								break;

								// Autofill currently active process drawing number only if none has been stored previously
								case 'drawing' :
									echo html_entity_decode(sprintf('%s.%s', $drawing->get('number'), $drawing->get('index')));
								break;

								// case 'annotation' :
									// echo is_object($partProc) ? html_entity_decode($partProc->get($id)) : '';
								// break;
							endswitch; ?>"
						   <?php endif; ?>
						   
						   <?php if (true == $readOnly) : ?>
						   readonly
						   disabled	<?php // the 'disabled' attribute prevents this data from being submitted ?>
						   <?php endif; ?>
					/>
				</div>
			</div>
			<?php }, ARRAY_FILTER_USE_BOTH); ?>
		</li>

		<?php endforeach; ?>
	</ul>
</div>

<?php // Free memory.
unset($article);
unset($artProcesses);
unset($input);
unset($model);
unset($orgProcesses);
unset($orgProcessIDs);
unset($processes);
unset($staticTechParams);
unset($user);
unset($userOrganisation);
unset($view);
?>
