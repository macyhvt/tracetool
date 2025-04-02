<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\App;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');
?>

<?php // Input for production instructions ?>
<?php if (FALSE) : ?>
<div class="row form-group ml-sm-0<?php echo ($this->isBlocked || $this->isDeleted) ? ' mb-0' : ''; ?>">
	<label for="instructions" class="col-sm-6 col-md-5 col-lg-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_PRODUCTION_INSTRUCTIONS_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-7 col-lg-8">
		<textarea name="instructions"
		          class="form-control pb-3"
				  rows="3"
				  cols="10"
				  readonly
		><?php $instructions = html_entity_decode($this->item->get('instructions')); echo OutputFilter::cleanText($instructions); ?></textarea>
	</div>
</div>
<?php endif; ?>

<?php if ($cnt = count($this->artProcesses)) : $pids = array_keys($this->artProcesses); $i = 1; ?>

	<?php foreach ($this->artProcesses as $pid => $artProcess) : ?>
	<?php 	// Prepare measuring data widget

			/* Dynamically render article process measurement data table */
			$mesaurementTrackingWidget = LayoutHelper::render('forms.article.process_measurement', [
				'isTracking' => false,	// flags this request as "tracking" mode (part process is tracked), which makes only limited fields editable rather than in "compositing" mode (article process definitions))
				'isReadonly' => true,	// flags this request as "read" mode (part is viewed)
				'processes'  => $this->item->get('processes'),
				'item'       => $this->item,
				'pid'        => $pid,
				'hide'       => ['mpInput','mpValidity','mpToolbar']
				], ['language' => $this->language]
			);

			if (empty($mesaurementTrackingWidget) || $mesaurementTrackingWidget == '') :
				$mesaurementTrackingWidget = LayoutHelper::render('system.alert.info', [
					'message' => Text::translate('COM_FTK_HINT_MEASURING_WIDGET_NOT_AVAILABLE_TEXT', $this->language),
					'attribs' => [
						'class' => 'alert-sm'
					]
				]);
			endif;

			$measurementDefinitionsRenderOptions = json_encode([
				'element'    => 'div',
				'html'       => base64_encode($mesaurementTrackingWidget),
				'attributes' => [
					'class'       => 'collapse collapse-sm mt-2' .
										($cnt > 1 && $pid != end($pids) ? ' mb-3 mb-md-4 mb-lg-5' : '') . 
										($this->user->isProgrammer()
											? ' show'	// for a developer, expand all tables immediately to prevent him from a hundred clicks
											: ''),
					'id'          => 'card-' . (int) $pid,
					'data-parent' => '#p-'   . (int) $pid
				]
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	?>
<div class="list-item dynamic-content position-relative" id="p-<?php echo $pid; ?>">
	<div class="row form-group ml-sm-0 <?php echo ($i == $cnt) ? ' mb-0' : ' mb-sm-2'; ?>">
		<?php	// Drawing existence check.
				$drawing    = $artProcess->extract('drawing');
				$fileExists = (true === $drawing->get('fileExists'));

				$process = ArrayHelper::getValue($this->processes, $artProcess->get('procID')); /* iterate over the array of process ids ($p is a Nematrack\Entity\Process) */
				$process = new Registry($process);
		?>
		<div class="col-sm-6 col-md-5 col-lg-4 px-sm-0">
			<div style="background:#e8edf3">
				<?php // Measuring definition list toggle ?>
				<div class="input-group-prepend d-inline-block">
					<?php // Toggle measuring definition list for this process ?>
					<button type="button"
							class="btn btn-secondary left-radius-0 right-radius-0 px-2"
							id="card-p-<?php echo $pid; ?>-toggle"
							tabindex="<?php echo ++$this->tabindex; ?>"
							data-toggle="collapse"
							data-target="#card-<?php echo $pid; ?>"
					>
						<span class="px-1"
							  title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_DEFINE_MEASURMENT_DATA_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_DEFINE_MEASURMENT_DATA_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
							  data-html="true"
						>
							<small class="btn-text sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_SHOW_MEASURING_POINTS_TEXT', $this->language); ?></small>
							<i class="fas fa-cogs"></i>
						</span>
					</button>
				</div>

				<?php // Process name ?>
				<label for="<?php echo html_entity_decode($process->get('abbreviation')); ?>"
					   class="col-form-label col-auto"
					   style="margin-left:-6px; vertical-align:middle; background-color:unset"
				><?php
					echo html_entity_decode($process->get('name'));
				?>:</label>
			</div>
		</div>

		<?php // Drawing details ?>
		<div class="col-sm-6 col-md-7 col-lg-8">
			<div class="input-group">
				<?php // Drawing file name (stem must equal article name) ?>
				<label for="drawings" class="col col-form-label sr-only"><?php echo Text::translate('COM_FTK_LABEL_DRAWING_NUMBER_TEXT', $this->language); ?>:</label>
				<input type="text"
					   name="drawings[<?php echo (int) $process->get('procID'); ?>]"
					   class="form-control form-control-drawing-number<?php echo (!$fileExists) ? ' text-red' : ''; ?>"
					   id="drw-p-<?php echo $pid; ?>-filenumber"
					   value="<?php echo ($fileExists)
							? sprintf('%s.%s', html_entity_decode($drawing->get('number')), html_entity_decode($drawing->get('index')))
							: Text::translate('COM_FTK_HINT_FILE_NOT_FOUND_TEXT', $this->language); ?>"
					   readonly
					   required
					   aria-live="polite"
					   data-rule-required="true"
					   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_FIELD_IS_AUTOFILLED_TEXT', $this->language); ?>"
				/>

				<?php // Drawing file size information ?>
				<span class="form-control d-none d-lg-inline col-3 col-xl-2 text-right<?php echo (!$fileExists) ? ' text-red' : ''; ?>" id="drw-p-<?php echo (int) $process->get('procID'); ?>-filesize" readonly><?php
					echo ($fileExists) ? sprintf('%.2f %s', $drawing->get('metadata.sizes.KB'), Text::translate('COM_FTK_UNIT_KILOBYTES', $this->language)) : '';
				?></span>

				<?php // Drawing file preview toggle ?>
				<div class="input-group-append">
					<div class="btn-group btn-group-sm" role="group" aria-label="<?php echo Text::translate('COM_FTK_LABEL_BUTTON_GROUP_TEXT', $this->language); ?>">
						<?php // File preview button for this process drawing ?>
						<a href="<?php echo ($fileExists) ? App::getRouter()->fixRoute($drawing->get('file')) : 'javascript:void(0)'; ?>"
						   role="button"
						   class="btn btn-secondary left-radius-0 right-radius-0 px-2"
						   target="_blank"
						   rel="nofollow noreferrer"
						   <?php // style="background-color:#e9ecef; border-color:#ced4da" ?>
						   <?php if (!$fileExists) : ?>
						   disabled
						   style="cursor:not-allowed"
						   onclick="return false;"
						   <?php endif; ?>
						>
							<span class="px-2"
								  <?php if ($fileExists) : ?>
								  title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CURRENT_DRAWING_TEXT', $this->language); ?>"
								  aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CURRENT_DRAWING_TEXT', $this->language); ?>"
								  <?php else : ?>
								  title="<?php echo Text::translate('COM_FTK_SYSTEM_MESSAGE_DRAWING_NOT_FOUND_TEXT', $this->language); ?>"
								  aria-label="<?php echo Text::translate('COM_FTK_SYSTEM_MESSAGE_DRAWING_NOT_FOUND_TEXT', $this->language); ?>"
								  <?php endif; ?>
								  data-toggle="tooltip"
								  style="vertical-align:sub"
							>
								<i class="far fa-file-pdf icon-pdf" style="vertical-align:text-top; font-size:1.2rem"></i>
								<span class="btn-text d-none d-md-inline ml-2"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_VIEW_PDF_TEXT', $this->language); ?></span>
							</span>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php // Measurement points definition list per process ?>
	<span id="dynamic-table-<?php echo $pid; ?>"
		  data-toggle="replaceElement"
		  data-target="#dynamic-table-<?php echo $pid; ?>"
		  data-replace="true"
		  data-replacement-options='<?php echo preg_replace('/%ID%/', $pid, $measurementDefinitionsRenderOptions); ?>'  <?php // IMPORTANT: use single quotes here !!! ?>
	></span>
</div>
	<?php $i += 1; endforeach; ?>

<?php else : ?>

	<?php if (!$this->isBlocked && !$this->isDeleted) : ?>
	<?php 	if ($this->user->getFlags() >= User::ROLE_MANAGER) : ?>
		<?php echo LayoutHelper::render('system.alert.info', [
			'message' => Text::translate('COM_FTK_HINT_ARTICLE_HAS_NO_PROCESSES_TEXT', $this->language) .
				'<a href="javascript:void(0)" class="ml-md-3" data-bind="delegateClick" data-target="#btn-edit">' .
					'<span class="btn-text mr-2">' . Text::translate('COM_FTK_LABEL_PROCESSES_EDIT_TEXT', $this->language) . '</span>' .
					'<i class="fas fa-external-link-alt fa-xs"></i>' .
				'</a>'
			,
			'attribs' => [
				'class' => 'alert-sm my-0'
			]
		]
	); ?>
	<?php 	endif; ?>
	<?php endif; ?>

<?php endif; ?>
