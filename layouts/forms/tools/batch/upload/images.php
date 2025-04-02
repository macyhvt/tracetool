<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;
use Nematrack\Utility\Math;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$view     = $this->__get('view');
$return   = $view->getReturnPage();	// Browser back-link required for back-button.

$layout   = $view->get('input')->getCmd('layout');
$task     = $view->get('input')->post->getCmd('task')  ?? ($view->get('input')->getCmd('task') ?? null);
$redirect = $view->get('input')->post->getString('return') ?? ($view->get('input')->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);
$redirect = (!is_null($view->get('input')->get->getWord('project')) && !is_null($view->get('input')->getWord('quality')))
	? $view->get('input')->server->getUrl('REQUEST_URI')
	: $redirect;
?>
<?php /* Assign refs. */
$this->view = $view;
$this->list = $this->view->get('list');
$this->user = $this->view->get('user');
?>
<?php /* Access check */
if (is_a($this->user, 'Nematrack\Entity\User')) :
	try
	{
		$formData = $this->user->__get('formData');
		$formData = (is_array($formData)) ? $formData : [];
	}
	catch (Exception $e)
	{
		$formData = null;
	}
endif;
?>
<?php /* Process form data */
if (!is_null($task)) :
	switch ($task) :
		case 'upload' :
			$this->view->handleBatchUpload();
		break;
	endswitch;
endif;
?>
<?php /* Load view data */
// Load data object into Registry object for easier access.
$this->list  = new Registry($this->list);
$this->files = $this->list->get('browsePathContents');

// Create form submit URL
$formAction  = $this->view->get('input')->server->getUrl('REQUEST_URI');
$this->view->__set('formName', 'toolsForm');

$path        = $this->view->get('input')->getBase64('path', '');
$pathRel     = str_ireplace(FTKPATH_BASE, '', str_ireplace(basename($this->list->get('browsePath', '')), '', $this->list->get('browsePath', '')));
$listCount   = count((array) $this->files);

// Init tabindex
$tabindex    = 0;
?>

<style>
.card-body {
	border: 0.75rem solid transparent;
	border-bottom-left-radius: 0.25rem;
	border-bottom-right-radius: 0.25rem
}
.card-body.alert-success {
	border-color: #d4edda;
}

.card-text.img-meta {
	top: 0;
	left: 0;
	z-index: 0;
	opacity: 0.8
}
.card-text.img-meta > dl {
	opacity: 0;
	margin-top: 1.5rem;
	padding-top: 1.5rem;
	transition: opacity 375ms, transform 375ms;
}
.card-text.img-meta,
._card-text.img-meta > dl {
	height: 0;
	transition: height 250ms, transform 250ms;
}

.card-body.alert-success:hover .card-text.img-meta {
	height: 100%;
}
.card-body.alert-success:hover .card-text.img-meta > dl {
	opacity: 1;
}

.col-form-label,
.nav-tabs .nav-link {
	background-color: #e8edf3;
	border-color: #dee3e9 #dee3e9 #dee2e6;
	color: #30588B;
}
</style>

<form action="<?php echo UriHelper::osSafe(UriHelper::fixURL($formAction)); ?>"
      method="<?php echo $this->view->get('input')->get('taskName') == 'browse' ? 'POST' : 'GET'; ?>"
      class="form form-horizontal <?php echo($formName = sprintf('%sForm', mb_strtolower($view->get('name')))); ?> validate"
      name="<?php echo $this->view->get('formName'); ?>"
      id="<?php echo $this->view->get('formName'); ?>"
      data-submit=""
      data-monitor-changes="false"
>
	<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
	<input type="hidden" name="view"   value="<?php echo $this->view->get('name'); ?>" />
	<input type="hidden" name="layout" value="<?php echo $this->view->get('layout'); ?>" />
	<input type='hidden' name="task"   value="<?php echo $this->view->get('input')->get('taskName', 'browse'); ?>" />
	<?php if (isset($path)) : ?>
	<input type="hidden" name="abbr"   value="<?php echo basename($pathRel); ?>" />
	<input type="hidden" name="path"   value="<?php echo $path; ?>" />
	<input type="hidden" name="return" value="<?php echo base64_encode($this->view->get('input')->server->getUrl('REQUEST_URI')); ?>" />
	<?php endif; ?>

	<h1 class="h3 viewTitle d-inline-block my-0 mr-3"><?php echo $this->view->get('viewTitle'); ?></h1>

	<hr>

	<div class="status-overlay wrapper position-relative" id="upload-wrapper"><?php // required for AJAX loading simulation ?>
		<?php /* Root path selection */ ?>
		<div class="row form-group ml-sm-0">
			<?php // display selected input path ?>
			<?php if ($this->list->get('browsePath')) : ?>
			<label for="process" class="col col-form-label col-form-label-sm col-auto"><?php echo Text::translate('COM_FTK_LABEL_PATH_TEXT', $this->language); ?>:&nbsp;&ast;</label>
			<div class="col">
				<span class="form-control form-control-sm" readonly><?php echo $pathRel; ?></span>
			</div>
			<?php endif; ?>

			<?php // Selected input path ?>
			<label for="path" class="col col-form-label col-form-label-sm col-auto"><?php echo Text::translate('COM_FTK_LABEL_LOOK_UP_DIRECTORY_TEXT', $this->language);
			?>:&nbsp;&ast;</label>

			<div class="col">
				<select name="path"
				        class="form-control form-control-sm"
				        id="lst-path"
				        size="1"
				        data-rule-required="true"
				        data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
				        onchange="document.forms[document.forms.length-1].submit()"
				        required
				>
					<option value=""><?php echo sprintf('&ndash; %s &ndash;', Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language)); ?></option>

					<?php foreach ($this->list->get('rootDirs') as $group => $rootDirs) : $rootDirs = (array) $rootDirs; ?>

					<?php // Skip empty path ?>
					<?php if (!is_countable($rootDirs) || !count($rootDirs)) : continue; endif; ?>

					<optgroup label="<?php echo $group; ?>">
					<?php foreach ($rootDirs as $dirName => $path) : ?>
						<?php $selected = $path == $this->list->get('browsePath') ? ' selected' : ''; ?>

						<option value="<?php echo base64_encode($path); ?>"
							<?php echo ($path == $this->list->get('browsePath') ? ' selected' : ''); ?>
						><?php echo $dirName; ?>
						</option>
					<?php endforeach; ?>
					</optgroup>

					<?php endforeach; ?>
				</select>
			</div>

			<?php if ($this->list->get('browsePath') && $listCount) : ?>
			<div class="col">
				<button type="submit"
				        class="btn btn-sm btn-warning px-lg-3"
				        form="<?php echo $this->view->get('formName'); ?>"
				        name="task"
				        value="upload"
				        title="<?php echo Text::translate(
							mb_strtoupper(
								sprintf('COM_FTK_BUTTON_TITLE_UPLOAD_SELECTED_%s_TEXT', ($listCount == '1' ? 'FILE' : 'FILES')
							)
						), $this->language); ?>"
				        onclick="(function(){ document.forms.<?php echo $this->view->get('formName'); ?>.className += ' submitted'; document.forms.<?php echo $this->view->get('formName'); ?>.submit() })();"
				>
					<i class="fas fa-upload"></i>
					<span class="d-none d-md-inline ml-md-2"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_START_UPLOAD_TEXT', $this->language); ?></span>
				</button>
			</div>
			<?php endif; ?>
		</div>

		<?php if ($this->list->get('browsePath') && !$listCount) : ?>
		<p class="alert alert-sm alert-info">
			<i class="fas fa-info-circle mr-1"></i>
			<?php echo Text::translate('COM_FTK_HINT_DIRECTORY_IS_EMPTY_TEXT', $this->language); ?>
		</p>
		<?php endif; ?>

		<?php if ($this->list->get('browsePath') && $listCount) : ?>
		<p class="alert alert-sm alert-warning mb-0">
			<i class="fas fa-info-circle mr-1"></i>
			<?php echo Text::translate('COM_FTK_HINT_INITIAL_IMAGE_PRESELECTION_TEXT', $this->language); ?>
		</p>

		<div class="row mt-4">
			<?php foreach ($this->files AS $FILE) : [$trackingcode, $articleNumber] = explode('@', '' . $FILE->getFileName()); ?>
			<div class="col-sm-6 col-lg-3 mb-4">
				<div class="card">
					<div class="card-header text-center py-1">
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=parts&layout=list&task=search&searchword=%s',
								$this->language,
								$trackingcode
						   ))); ?>"
						   class="btn-link card-link link-to-part"
						   data-toggle="tooltip"
						   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $this->language); ?>"
						   target="_blank"
						>
							<span class="align-middle"><?php echo htmlentities($trackingcode); ?></span>
						</a>
					</div>
					<div class="card-body m-0 p-0 alert-success">
						<div class="btn-group-toggle position-relative" data-toggle="buttons" data-parent=".card-body" style="overflow:hidden">
							<label class="btn label position-relative outline-0 m-0 p-0 active">
								<?php // File info overlay ?>
								<div class="card-text img-meta position-absolute w-100 bg-dark">
									<dl class="row text-white small">
										<dt class="col col-4 text-right text-capitalize pr-0"><?php echo Text::translate('COM_FTK_LABEL_DATE_TEXT', $this->language); ?>:</dt>
										<dd class="col col-8 text-left"><?php echo date('d M. Y H:m', $FILE->getCTime()); ?></dd>
										<dt class="col col-4 text-right text-capitalize pr-0"><?php echo Text::translate('COM_FTK_LABEL_READABLE_TEXT', $this->language); ?>:</dt>
										<dd class="col col-8 text-left"><?php echo Text::translate(
											mb_strtoupper(sprintf('COM_FTK_%s_TEXT', ($FILE->isReadable() ? 'YES' : 'NO'))
										), $this->language); ?></dd>
										<dt class="col col-4 text-right text-capitalize pr-0"><?php echo Text::translate('COM_FTK_LABEL_SIZE_TEXT', $this->language); ?>:</dt>
										<dd class="col col-8 text-left"><?php echo sprintf('%s MB', Math::bytesToMegabytes($FILE->getSize(), 2)); ?></dd>
									</dl>
								</div>

								<?php // Hidden checkbox to (un)select the file ?>
								<input type="checkbox"
								       class="d-none"
								       name="files[]"
								       value="<?php echo sprintf('%s@%s', $trackingcode, $articleNumber); ?>"
								       autocomplete="off"
								       checked
								/>

								<?php // File preview ?>
								<img src="<?php echo str_ireplace(FTKPATH_BASE, '', $FILE->getPathName()) . '?t=' . mt_rand(0, 9999999); ?>"
								     class="img-fluid btn-checkbox"
								     alt="<?php echo $FILE->getFileName(); ?>"
								     height="50"
								     width="auto"
								     loading="lazy"
								     referrerpolicy="no-referrer"
								/>
							</label>
						</div>
					</div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</form>

<?php // Free memory
unset($view);
?>

<?php // FIXME - activate message when there are no files ?>
<?php if (FALSE) : ?>
	<?php if (is_countable($files = $data['dirContent'])) : ?>
	<h6><?php echo sprintf('%d files found', count($files)); ?></h6>
	<?php   foreach ($files as $file) : ?>
		<span class="d-block small"><?php echo $file; ?></span>
	<?php   endforeach; ?>
	<?php else : ?>
		<?php echo LayoutHelper::render('system.alert.info', [
			'message' => Text::translate('COM_FTK_HINT_NO_RESULT_TEXT', $this->language),
			'attribs' => [
				'class' => 'alert-sm'
			]
		]); ?>
	<?php endif; ?>
<?php endif; ?>
