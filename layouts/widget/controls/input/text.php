<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Text;

// No direct script access
defined('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php // Init vars
$data = new Registry($this->data);

$attribs        = $data->extract('attribs');
$attribs        = $attribs ?? new Registry;
$context        = $data->get('context');

$autocapitalize = $attribs->get('autocapitalize', 'off');	// can be one of: "off/none", "on/sentence", "characters", "words" (see https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes/autocapitalize)
$autocomplete   = $attribs->get('autocomplete', 'off');	// can be one of: "off", "on", or more (see https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete)
$autocorrect    = $attribs->get('autocorrect', 'off');	// use spellcheck="true" instead (see: https://developer.mozilla.org/en-US/docs/Web/HTML/Element/Input#autocorrect)
$class          = trim('form-control ' . $attribs->get('class'));
$form           = $attribs->get('form');
$id             = $attribs->get('id');
$inputMode      = $attribs->get('inputmode');	// can be: "numeric"; provides a numeric keyboard; use in conjunction with autocomplete="off" OR autocomplete="one-time-code"
$datalist       = $attribs->get('list', $attribs->get('datalist'));	// the id of a <datalist> element located in the same document
$maxLength      = $attribs->get('maxlength');
$minLength      = $attribs->get('minlength');
$name           = $attribs->get('name', 'password');
$pattern        = $attribs->get('pattern');	// must be a valid JavaScript regular expression, as used by the RegExp type and documented at https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions
$placeholder    = $attribs->get('placeholder', Text::translate('COM_FTK_INPUT_PLACEHOLDER_REQUIRED_TEXT', $this->language));
$readonly       = $attribs->get('readonly', false);		// can be one of: "false", "true"
$required       = $attribs->get('required', false);
$spellcheck     = $attribs->get('spellcheck', 'false');	// can be one of: "false", "true", ""; where "" triggers the element's default behavior for spell checking (see: https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes/spellcheck)
$tabindex       = $attribs->get('tabindex');
$value          = $attribs->get('value');
?>
<input type="text"
       <?php if ($name)           : ?>name="<?php           echo       $name;           ?>"<?php endif; ?>
       <?php if ($class)          : ?>class="<?php          echo       $class;          ?>"<?php endif; ?>
       <?php if ($id)             : ?>id="<?php             echo       $id;             ?>"<?php endif; ?>
       <?php if ($form)           : ?>form="<?php           echo       $form;           ?>"<?php endif; ?>
	   <?php if ($tabindex)       : ?>tabindex="<?php       echo (int) $tabindex;       ?>"<?php endif; ?>
	   <?php if ($inputMode)      : ?>inputMode="<?php      echo       $inputMode;      ?>"<?php endif; ?>
	   <?php if ($minLength)      : ?>minlength="<?php      echo (int) $minLength;      ?>"<?php endif; ?>
	   <?php if ($maxLength)      : ?>maxlength="<?php      echo (int) $maxLength;      ?>"<?php endif; ?>
	   <?php if ($pattern)        : ?>pattern="<?php        echo       $pattern;        ?>"<?php endif; ?>
	   <?php if ($placeholder)    : ?>placeholder="<?php    echo       $placeholder;    ?>"<?php endif; ?>
       <?php if ($autocapitalize) : ?>autocapitalize="<?php echo       $autocapitalize; ?>"<?php endif; ?>
       <?php if ($autocomplete)   : ?>autocomplete="<?php   echo       $autocomplete;   ?>"<?php endif; ?>
       <?php if (0) : ?>autocorrect="off"<?php endif; ?>
       <?php if ($spellcheck)     : ?>spellcheck="<?php     echo       $spellcheck;     ?>"<?php endif; ?>
	   <?php if ($readonly)       : ?>readonly<?php endif; ?>
	   <?php if ($required) : ?>
       required
       data-rule-required="true"
       data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $this->language); ?>"
	   <?php endif; ?>
       <?php if (0) : ?>data-rule-minlength="true"<?php endif; // TODO - add translation ?>
       <?php if (0) : ?>data-msg-minlength="<?php echo Text::translate('', $this->language); ?>"<?php endif; // TODO - add translation ?>
       <?php if (0) : ?>data-rule-maxlength="true"<?php endif; // TODO - add translation ?>
       <?php if (0) : ?>data-msg-maxlength="<?php echo Text::translate('', $this->language); ?>"<?php endif; // TODO - add translation ?>
>
