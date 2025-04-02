<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Text;

//$mainGroups = $this->getInstance('articles', ['language' => $this->language])->getMainGroupsNew();
/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<div class="row ml-md-0">
	<div class="col">
		<div class="row form-group">

               <!-- <label for="mgid" class="col col-form-label col-md-2"><?php /*echo "Select Main Group";//echo Text::translate('COM_FTK_LABEL_ROLE_TEXT', $this->language); */?>:&nbsp;</label>
                <div class="col">
                    <select name="mgid"
                            class="form-control custom-select"
                        <?php /*// Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. */?>
                            title="<?php /*echo Text::translate('COM_FTK_INPUT_TITLE_ROLE_TEXT', $this->language); */?>"
                            aria-label="<?php /*echo Text::translate('COM_FTK_INPUT_TITLE_ROLE_TEXT', $this->language); */?>"
                            required
                            tabindex="<?php /*echo ++$this->tabindex; */?>"
                            data-rule-required="true"
                            data-msg-required="<?php /*echo Text::translate('COM_FTK_HINT_PLEASE_SELECT_TEXT', $this->language); */?>"
                    >
                        <option value="">&ndash; <?php /*echo Text::translate('COM_FTK_LIST_OPTION_SELECT_TEXT', $this->language); */?> &ndash;</option>
                        <?php /*foreach ($this->mainGroups as $option):  */?>
                            <?php
/*                            echo "<option value='".$option['mgid']."'>".$option['group_name']."</option>";
                             */?>
                        <?php /*endforeach; */?>
                    </select>
                </div>-->


            <label for="parts" class="col col-form-label col-md-2"><?php echo "Sub-group";/*echo sprintf('%s %s',
				Text::translate('COM_FTK_LABEL_PARTS_TEXT',         $this->language),
				Text::translate('COM_FTK_LABEL_PARTS_ORDERED_TEXT', $this->language));*/
                ?>:</label>
            <div class="col">
                <input type="text"
                       name="number"
                       value="<?php //echo ArrayHelper::getValue($this->formData, 'number', '', 'CMD'); ?>"
                       class="form-control"
                       minlength="3"
                       maxlength="3"
                       pattern="<?php echo FTKREGEX_PROJECT_NUMBER; ?>"
                    <?php // Note: The title attribute is used as the error message if the user input does not match the pattern. Hence, it is mandatory. ?>
                       title="<?php echo Text::translate('COM_FTK_INPUT_TITLE_PROJECT_NUMBER_TEXT', $this->language); ?>"
                       placeholder="<?php echo '3 characters';//Text::translate('COM_FTK_INPUT_PLACEHOLDER_PROJECT_NUMBER_TEXT', $this->language); ?>"
                       required
                       autofocus
                       tabindex="<?php echo ++$this->tabindex; ?>"
                       data-rule-required="true"
                       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
                       data-rule-minlength="3"
                       data-msg-minlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_NUMBER_TOO_SHORT_TEXT', $this->language); ?>"
                       data-rule-maxlength="3"
                       data-msg-maxlength="<?php echo Text::translate('COM_FTK_HINT_PROJECT_NUMBER_TOO_LONG_TEXT', $this->language); ?>"
                       data-rule-pattern="<?php echo FTKREGEX_PROJECT_NUMBER; ?>"
                       data-msg-pattern="<?php echo Text::translate('COM_FTK_HINT_INVALID_PROJECT_NUMBER_TEXT', $this->language); ?>"
                />
            </div>
		</div>

		</div>
	</div>
</div>
