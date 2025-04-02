<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php // Input for production instructions ?>
<?php if (FALSE) : ?>
<div class="row form-group ml-sm-0<?php echo ($this->isBlocked || $this->isDeleted) ? ' mb-0' : ''; ?>">
	<label for="instructions" class="col-sm-6 col-md-4 col-form-label"><?php echo Text::translate('COM_FTK_LABEL_PRODUCTION_INSTRUCTIONS_TEXT', $this->language); ?>:</label>
	<div class="col-sm-6 col-md-8">
		<textarea name="instructions"
		          class="form-control pb-3"
		          id="ipt-instructions"
				  rows="3"
				  cols="10"
				  maxlength="1000"
				  placeholder="<?php echo Text::translate('COM_FTK_INPUT_PLACEHOLDER_OPTIONAL_TEXT', $this->language); ?>"
				  tabindex="<?php echo ++$this->tabindex; ?>"
		><?php $description = ArrayHelper::getValue($this->formData, 'description', '', 'STRING'); echo OutputFilter::cleanText($description); ?></textarea>
	</div>
</div>
<?php endif; ?>

<?php $this->tabindex = 4; ?>

<?php if ($this->user->getFlags() >= User::ROLE_MANAGER) : ?>

		<?php echo LayoutHelper::render('system.alert.info', [
			'message' => Text::translate('COM_FTK_HINT_ARTICLE_HAS_NO_PROCESSES_TEXT', $this->language),
			'attribs' => [
				'class' => 'alert-sm mt-3',
				   'id' => 'msg-artProcesses'
			]
		]); ?>

<?php endif; ?>

<ul class="list-unstyled mb-0 observable"
	id="artProcesses"
	data-observe="childlist subtree attributeOldValue"
	data-next-id="<?php echo $this->lastID + 1; ?>"
>
	<?php // Add process - BUTTON ?>
	<li class="list-item dynamic-content position-relative">
		<div class="form-row process my-md-1 my-lg-2">
			<div class="col col-md-2 col-lg-3">
				<div class="input-group" style="width:104.5%">
					<div class="input-group-prepend">
						<span class="input-group-text px-2">
							<i class="fas fa-plus px-1"></i>
						</span>
					</div>
					<button type="button"
							class="btn btn-sm btn-info"
							data-toggle="hideElement"
							data-toggle-element="#msg-artProcesses"
							data-toggle-effect="slide"
							data-bind="addArticleProcess"
							data-target="#artProcesses"
							tabindex="<?php echo ++$this->tabindex; ?>"
							style="vertical-align:baseline; border-top-left-radius:0; border-bottom-left-radius:0"
					>
						<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PROCESS_CREATE_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_PROCESS_CREATE_TEXT', $this->language); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_ADD_TEXT', $this->language); ?></span>
						</span>
					</button>
				</div>
			</div>
		</div>
	</li>
</ul>
