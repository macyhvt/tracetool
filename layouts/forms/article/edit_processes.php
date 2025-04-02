<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;
use Nematrack\Model;
/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php $this->tabindex = 4; ?>

<style>
.upload-hint {
	content: "Zeichnung fehlt";
	text-align: center;
	font-size: 120%;
    line-height: 1.5;
	color: #bcbcbc;
	display: none;
    width: 100%;
    height: 100%;
}
</style>

<div id="artProcesses">
	<?php // BUTTON: Add process ?>
	<div class="list-item dynamic-content position-relative">
		<div class="row form-group ml-sm-0 mb-sm-2">
			<div class="col-sm-6 col-md-5 col-lg-4 px-sm-0">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text left-radius-0 right-radius-0 px-2">
							<span class="px-1">
								<i class="fas fa-plus" style="padding-left:3px; padding-right:3px"></i>
							</span>
						</span>
					</div>
					<button type="button"
							class="btn btn-info left-radius-0 px-2"
							data-toggle="hideElement"
							data-toggle-element="#msg-artProcesses"
							data-toggle-effect="slide"
							data-bind="addArticleProcess"
							data-target="#artProcesses"
							tabindex="<?php echo ++$this->tabindex; ?>"
					>
						<span class="px-1"
							  title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PROCESS_CREATE_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PROCESS_CREATE_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_ADD_TEXT', $this->language); ?></span>
						</span>
					</button>
				</div>
			</div>
		</div>
	</div>
	<?php // END: Button ?>
<?php if ($cnt = count($this->artProcesses)) : $pids = array_keys($this->artProcesses); $i = 1; ?>

	<?php foreach ($this->artProcesses as $pid => $artProcess) : ?>
	<?php 	// Prepare measuring data widget
			// $drawing    = $artProcess->extract('drawing');
			// $fileExists = (true === $drawing->get('fileExists'));

			/* Dynamically render article process measurement data table */
			$mesaurementTrackingWidget = LayoutHelper::render('forms.article.process_measurement', [
				'isTracking' => false,	// flags this request as "tracking" mode (part process is tracked), which makes only limited fields editable rather than in "compositing" mode (article process definitions))
				'isReadonly' => false,	// flags this request as "read" mode (part is viewed)
				'processes'  => $this->item->get('processes'),
				'item'       => $this->item,
				'pid'        => $pid,
				'hide'       => ['mpInput','mpValidity']
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
		<div class="row form-group ml-sm-0<?php echo ($i == $cnt) ? ' mb-0' : ' mb-sm-2'; ?>">
		<?php	// Drawing existence check.
				$drawing = $artProcess->extract('drawing');
				$fileExists  = (true === $drawing->get('fileExists'));

				$process = ArrayHelper::getValue($this->processes, $artProcess->get('procID')); /* iterate over array of process ids ($p is a Nematrack\Entity\Process) */
				$process = new Registry($process);
		?>
			<div class="col-sm-3 col-md-3 col-lg-3 px-sm-0" <?php /* style="background:#e8edf3; border-color:#dee3e9 #dee3e9 #dee2e6" */ ?>>
				<div class="input-group" style="background:#e8edf3">
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

					<?php // Process list lebel (hidden, because overlaid by following list element) ?>
					<label for="<?php echo html_entity_decode($process->get('abbreviation')); ?>"
						   class="col-form-label col-auto sr-only"
						   style="margin-left:-3px; vertical-align:middle; background-color:unset"
					><?php
						echo html_entity_decode($process->get('name'));
					?>:</label>

					<?php // Process list ?>
					<select name="processes[]"
							class="form-control custom-select selectProcess"
							id="ipt-processes-<?php echo $pid; ?>"
							required
							data-bind="procSelected"
							data-parent="#p-<?php echo $pid; ?>"
							data-target="#card-p-<?php echo $pid; ?>-toggle,
										 #drw-p-<?php  echo $pid; ?>-filenumber,
										 #drw-p-<?php  echo $pid; ?>-filesize,
										 #drw-p-<?php  echo $pid; ?>-toggle,
										 #drw-p-<?php  echo $pid; ?>-file"
							data-rule-required="true"
							data-msg-required="<?php echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); ?>"
							tabindex="<?php echo ++$this->tabindex; ?>"
					>
						<option value="">&ndash; <?php echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); ?> &ndash;</option>
						<?php foreach ($this->processes as $option) : $option = new Registry($option); ?>
						<?php	echo sprintf('<option value="%d"%s>%s</option>',
							 $option->get('procID'),
							($option->get('procID') == $pid ? ' selected' : ''),
							html_entity_decode($option->get('name'))
						); ?>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php // Select drawing file ?>
			<div class="col-sm-9 col-md-9 col-lg-9">
				<div class="input-group">
                    <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=upworkinstruction&artid=%d&pid=%d',
                        $this->language,
                        $this->item->get('artID'),
                        $pid
                    ))); ?>"
                       target="_blank"
                       class="btn btn-outline-success"
                       title="<?php echo "Work Instructions"; ?>"
                    ><i class="fas fa-upload dpUpload"></i>WI</a>
                    <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=upfailurecatalog&artid=%d&pid=%d',
                        $this->language,
                        $this->item->get('artID'),
                        $pid
                    ))); ?>"
                            target="_blank"
                            class="btn btn-outline-success fcUpload"
                            id="wi-p-<?php echo $pid; ?>"
                            data-toggle="tooltip"
                            title="<?php echo "Failure catalogue"; ?>"
                    ><i class="fas fa-upload dpUpload"></i>FC</a>
                    <a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=updatasheet&artid=%d&pid=%d',
                        $this->language,
                        $this->item->get('artID'),
                        $pid
                    ))); ?>"
                            target="_blank"
                            class="btn btn-outline-success dsUpload"
                            id="wi-p-<?php echo $pid; ?>"
                            data-toggle="tooltip"
                            title="<?php echo "Data sheet"; ?>"
                    ><i class="fas fa-upload dpUpload"></i>DS</a>
					<?php // Input drawing file name (stem must equal article name) ?>
					<label for="drawings" class="col col-form-label sr-only"><?php echo Text::translate('COM_FTK_LABEL_DRAWING_NUMBER_TEXT', $this->language); ?>:</label>
					<input type="text"
						   name="drawings[<?php echo (int) $pid; ?>]"
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
					<?php // Info about file size ?>
					<span class="form-control col-2 text-right<?php echo (!$fileExists) ? ' text-red' : ''; ?>"
						  id="drw-p-<?php echo $pid; ?>-filesize"
						  readonly
					><?php echo ($fileExists)
							? sprintf('%.2f %s', $drawing->get('metadata.sizes.KB'), Text::translate('COM_FTK_UNIT_KILOBYTES', $this->language))
							: Text::translate('COM_FTK_NA_TEXT', $this->language);
					?></span>
					<div class="input-group-append">
						<div class="btn-group btn-group-sm" role="group" aria-label="<?php echo Text::translate('COM_FTK_LABEL_BUTTON_GROUP_TEXT', $this->language); ?>">
							<?php // File upload togle for this process drawing ?>
							<button type="button"
									class="btn btn-sm btn-info"
									id="drw-p-<?php echo $pid; ?>-toggle"
									data-bind="delegateClick"
									data-target="#drw-p-<?php echo $pid; ?>-file"
									data-toggle="tooltip"
									title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_UPLOAD_PROCESS_DRAWING_TEXT', $this->language); ?>"
									aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_UPLOAD_PROCESS_DRAWING_TEXT', $this->language); ?>"
									style="border-top-left-radius:0; border-bottom-left-radius:0"
									tabindex="<?php echo ++$this->tabindex; ?>"
								>
								<span class="px-2">
									<i class="fas fa-recycle" style="vertical-align:text-top; font-size:1.2rem"></i>
									<span class="btn-text ml-md-2"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_CHANGE_DRAWING_TEXT', $this->language); ?></span>
								</span>
							</button>
							<?php // File preview toggle for this process drawing ?>
							<?php if ($fileExists) : ?>
							<a href="<?php echo ($fileExists) ? sprintf('%s', UriHelper::osSafe( UriHelper::fixURL($drawing->get('file')) ) ) : 'javascript:void(0)'; ?>"
							   role="button"
							   class="btn btn-secondary"
							   data-toggle="tooltip"
							   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CURRENT_DRAWING_TEXT', $this->language); ?>"
							   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CURRENT_DRAWING_TEXT', $this->language); ?>"
							   target="_blank"
							   rel="nofollow noreferrer"
							>
								<span class="px-2" style="vertical-align:sub">
									<i class="far fa-file-pdf icon-pdf" style="vertical-align:text-top; font-size:1.2rem"></i>
									<span class="btn-text ml-md-2"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_VIEW_PDF_TEXT', $this->language); ?></span>
								</span>
							</a>
							<?php else : ?>
							<span class="btn btn-secondary disabled"
								  data-toggle="tooltip"
								  disabled
								  aria-disabled="true"
							>
								<span class="px-2" style="vertical-align:sub">
									<i class="far fa-file icon-file" style="vertical-align:text-top; font-size:1.2rem"></i>
									<span class="btn-text ml-md-2"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_PDF_MISSING_TEXT', $this->language); ?></span>
								</span>
							</span>
							<?php endif; ?>
							<?php // File pool for this process ?>
							<?php if (FALSE) : ?>
							<button type="button"
									class="btn btn-secondary"
									id=""
									onclick="alert('Process files pool is yet to come');"
									tabindex="<?php echo ++$this->tabindex; ?>"
							>
								<span class="px-2"
									  title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_MANAGE_PROCESS_FILE_POOL_TEXT', $this->language); ?>"
									  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_MANAGE_PROCESS_FILE_POOL_TEXT', $this->language); ?>"
									  data-toggle="tooltip"
								>
									<i class="far fa-folder"></i>
									<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_LABEL_FILE_POOL_TEXT', $this->language); ?></span>
								</span>
							</button>
							<?php endif; ?>
							<?php // Delete process button ?>
							<button type="button"
									class="btn btn-danger left-radius-0 right-radius-0 px-2"
									id="drw-p-<?php echo $pid; ?>-trasher"
									title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PROCESS_DELETE_THIS_TEXT', $this->language); ?>"
									aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PROCESS_DELETE_THIS_TEXT', $this->language); ?>"
									data-toggle="tooltip"
									data-bind="deleteListItem"
									data-target="#p-<?php echo $pid; ?>"
									data-confirm-delete="true"
									data-confirm-delete-empty="false"
									data-confirm-delete-message="<?php echo sprintf("%s\r\n%s",
										Text::translate('COM_FTK_DIALOG_PROCESS_CONFIRM_DELETION_TEXT', $this->language),
										Text::translate('COM_FTK_HINT_NO_DATA_LOSS_PRIOR_STORAGE_TEXT', $this->language)
									); ?>"
									tabindex="<?php echo ++$this->tabindex; ?>"
							>
								<span class="px-2">
									<i class="far fa-trash-alt"></i>
									<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_LABEL_DELETE_TEXT', $this->language); ?></span>
								</span>
							</button>
                            <?php if($artProcess->get('org_abbr') == "DEF"){?>
                                <a style="color:#000; background: <?php echo $artProcess->get('org_color');  ?>" target="_blank" class="btn btn-secondary sw" href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=editorgabbr&artid=%d&pid=%d',
                                    $this->language,
                                    $this->item->get('artID'),
                                    $pid
                                ))); ?>"><?php echo $artProcess->get('org_abbr');?></a>
                            <?php }else{?>
                                <a style="background: <?php echo $artProcess->get('org_color'); ?>" target="_blank" class="btn btn-secondary" href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=editorgabbr&artid=%d&pid=%d',
                                    $this->language,
                                    $this->item->get('artID'),
                                    $pid
                                ))); ?>"><?php echo $artProcess->get('org_abbr');?></a>
                            <?php }?>
                            <!-- Activate or Deactivate process-->

                            <?php
                            $checkPoint = Model::getInstance('article', ['language' => $this->language])->getArticleUpCount($this->item->get('artID'), $pid);
                            ?>

                            <style>
                                .proUpdatebtns{
                                    position: absolute !important;
                                    right: -134px;
                                    top: 5px;
                                }
                                .proUpdatebtnse{
                                    position: absolute !important;
                                    right: -149px;
                                    top: 5px;
                                }
                                .proUpdatebtnset{
                                    position: absolute !important;
                                    right: -140px;
                                    top: 5px;
                                    background: grey;
                                    color: #fff;
                                    cursor: unset;
                                    pointer-events: none;
                                }
                            </style>
                            <?php
                            $groupRole = $this->user->get('groups');

                            if ((isset($groupRole) && isset($groupRole[11]) && $groupRole[11]['groupID'] == 11) || (isset($groupRole) && isset($groupRole[8]) && $groupRole[8]['groupID'] == 8)){
                                ?>

                                <?php if($checkPoint === null){
                                    if($artProcess->get('processState') == 1){ ?>
                                        <a target="_blank" class="btn btn-outline-warning proUpdatebtns btnsTool" href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=updateprocessstate&artid=%d&pid=%d&statcode=%d',
                                            $this->language,
                                            $this->item->get('artID'),
                                            $pid,
                                            0
                                        ))); ?>">Activated Tracking<span class="tooltiptext">It means that this process was Activated (TRACKABLE). If you click on this button it will disable the process and there is no longer a need to track the process, it will be automatically tracked once the next available process gets tracked.</span></a>
                                    <?php }elseif ($artProcess->get('processState') == 0){ ?>
                                        <a target="_blank" class="btn btn-outline-primary proUpdatebtnse btnsTool" href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=updateprocessstate&artid=%d&pid=%d&statcode=%d',
                                            $this->language,
                                            $this->item->get('artID'),
                                            $pid,
                                            1
                                        ))); ?>">Deactivated Tracking<span class="tooltiptext">It means that this process was Deactivated and disabled (NOT TRACKED). If you click on this button it will make it available again to track the process in the Tracking Tool.</span></a>
                                    <?php }
                                }else{ ?>
                                    <a data-title="Mandatory to track this process, as it has measuring points." target="_blank" class="btn btn-outline-primary proUpdatebtnset" href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=article&layout=updateprocessstate&artid=%d&pid=%d&statcode=%d',
                                        $this->language,
                                        $this->item->get('artID'),
                                        $pid,
                                        1
                                    ))); ?>">Mandatory to Track</a>
                                <?php }
                            }else{}?>
                            <style>
                                .btnsTool {
                                    position: relative;
                                    display: inline-block;
                                    border-bottom: 1px dotted black;
                                }
                                .btnsTool .tooltiptext {
                                    visibility: hidden;
                                    width: 120px;
                                    background-color: black;
                                    color: #fff;
                                    text-align: center;
                                    padding: 5px 10px;
                                    border-radius: 6px;
                                    left: 105%;
                                    top: -60px;

                                    position: absolute;
                                    z-index: 1;
                                }
                                .proUpdatebtns .tooltiptext {
                                    visibility: hidden;
                                    width: 120px;
                                    background-color: black;
                                    color: #fff;
                                    text-align: center;
                                    padding: 5px 10px;
                                    border-radius: 6px;
                                    left: 116%;
                                    top: -60px;

                                    position: absolute;
                                    z-index: 1;
                                }

                                .btnsTool:hover .tooltiptext {
                                    visibility: visible;
                                }
                            </style>
                            <!-- Activate or Deactivate process-->
						</div>
					</div>
					<?php // MAX_FILE_SIZE  MUST precede the file input field  (see: https://webstoked.com/purpose-max-file-size-php-form-validation ) ?>
					<input type="hidden" name="MAX_FILE_SIZE[<?php echo (int) $pid; ?>]" value="<?php echo FTKRULE_UPLOAD_MAX_SIZE; ?>" /><?php // allow max. 1MB per file - BE SURE TO APPLY SERVER SIDE VALIDATION TOO !!! ?>
					<label for="drawings" class="col col-form-label sr-only"><?php echo Text::translate('COM_FTK_LABEL_DRAWING_SELECTOR_TEXT', $this->language); ?>:</label>
					<input type="file"
						   name="drawings[<?php echo (int) $pid; ?>]"
						   multiple="false"
						   class="form-control form-control-input-file d-none"
						   id="drw-p-<?php echo $pid; ?>-file"
						   accept="application/pdf"
						   data-bind="processDrawingSelected"
						   data-monitor="#drw-p-<?php echo $pid; ?>-filenumber, #drw-p-<?php echo $pid; ?>-filesize"
						   required
						   data-rule-required="true"
						   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
						   tabindex="<?php echo ++$this->tabindex; ?>"
						   style="font-size:90%; padding-top:.3rem; padding-left:0; border:unset; background:inherit"
					/>
				</div>
			</div>
		</div>

		<?php //print_r($measurementDefinitionsRenderOptions); // Measurement points definition list per process ?>
		<span id="dynamic-table-<?php echo $pid; ?>"
			  data-toggle="replaceElement"
			  data-target="#dynamic-table-<?php echo $pid; ?>"
			  data-replace="true"
			  data-replacement-options='<?php echo preg_replace('/%ID%/', $pid, $measurementDefinitionsRenderOptions); ?>'
		></span>
	</div>
	<?php $i += 1; endforeach; ?>

<?php else : ?>

	<?php if (!$this->isBlocked && !$this->isDeleted) : ?>
	<?php	if ($this->user->getFlags() >= User::ROLE_MANAGER) : ?>
		<?php echo LayoutHelper::render('system.alert.info', [
			'message' => Text::translate('COM_FTK_HINT_ARTICLE_HAS_NO_PROCESSES_TEXT', $this->language),
			'attribs' => [
				'class' => 'alert-sm',
				   'id' => 'msg-artProcesses'
			]
		]); ?>
	<?php	endif; ?>
	<?php endif; ?>

<?php endif; ?>
<style>
    .custom-select{
    padding: .375rem 1.5rem .375rem .5rem;
    }
    .dpUpload{
        font-size: 10px;
        margin-right: 4px;
    }
</style>
