<?php
// Register required libraries.
use Joomla\Registry\Registry;
use  \Factory;
use  \Helper\LayoutHelper;
use  \Helper\UriHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php if (is_countable($this->techParams) && !count($this->techParams)) : ?>
	<?php echo LayoutHelper::render('system.alert.info', [
		'message' => Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_TECHNICAL_PARAMETERS_TEXT', $this->language),
		'attribs' => [
			'class' => 'alert-sm'
		]
	]); ?>
<?php endif; ?>

<?php // E D I T - button for active item ?>
<?php if (!$this->isDeleted) : ?>
<?php 	if (!$this->isBlocked) : ?>
<?php 		if ($user->getFlags() >= \ \Access\User::ROLE_MANAGER) : ?>
<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=edit&pid=%d', $this->language, $this->item->get('procID') ))); ?>#tech-params"
   role="button"
   class="btn btn-sm btn-info<?php echo (is_countable($this->techParams) && !count($this->techParams)) ? ' mt-3' : ' mb-3'; ?>"
   title="<?php echo Text::translate('COM_FTK_LABEL_PARAMETER_EDIT_TEXT', $this->language); ?>"
   aria-label="<?php echo Text::translate('COM_FTK_LABEL_PARAMETER_EDIT_TEXT', $this->language); ?>"
   data-bind="forceWindowNavigation"
   style="vertical-align:super"
>
	<i class="fas fa-pencil-alt"></i>
	<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_LABEL_PARAMETER_EDIT_TEXT', $this->language); ?></span>
</a>
<?php 		endif; // END: ACL-Check ?>
<?php 	endif; // END: !isBlocked ?>
<?php endif; // END: !isDeleted ?>

<?php if (is_countable($this->techParams) && count($this->techParams)) : ?>
<ul class=""
	id="procParams"
	style="padding-inline-start:2.2rem">
	<?php foreach ($this->techParams as $paramID => $param) : ?>
	<?php 	$param = new Registry($param); ?>
	<li class="list-item py-1">
	<?php if ($param->get('name') !== Text::translate('COM_FTK_NA_TEXT', Factory::getConfig()->get('app_language'))) :		   // NOTE: 'en' was replaced by FTKPARAM_APP_LANGUAGE on 2019-12-18 ... change back if it breaks functionality ?>
		<?php echo html_entity_decode($param->get('name')); ?>
	<?php else : ?>

<?php /* BEGIN:  In-place-translation widget --- DiSABLED UNTiL FiXED */ ?>
<?php if (FALSE) : ?><?php
		$cmplParam   = current( \array_filter( (array) $model->getInstance('techparam', ['language' => $this->language])->existsTechnicalParameter($paramID), function($param) {
			return $param->get('name') !== Text::translate('COM_FTK_NA_TEXT', Factory::getConfig()->get('app_language'));	// NOTE: 'en' was replaced by FTKPARAM_APP_LANGUAGE on 2019-12-18 ... change back if it breaks functionality
		}));
		$headline	 = sprintf(Text::translate('COM_FTK_HINT_TRANSLATE_X_INTO_LANGNAME_TEXT', $this->language), html_entity_decode($cmplParam->get('name')), $cmplParam->get('language'), $this->language);
		$hint		 = Text::translate('COM_FTK_HINT_TRANSLATION_NOT_REVERTABLE_TEXT', $this->language);
		$placeholder = Text::translate('COM_FTK_INPUT_PLACEHOLDER_TRANSLATION_TEXT', $this->language);
		$formAction  = UriHelper::osSafe( UriHelper::fixURL('index.php?hl=%s&view=processes&layout=%s') );
		$dataAction  = UriHelper::osSafe( UriHelper::fixURL('index.php?hl=%s&service=translate&task=&what=tparam') );
		$modalForm   = <<<HTML
<form action="{$formAction}"
	  method="post"
	  id="translateForm"
	  data-action="{$formAction}"
	  data-target="#param-{$dataAction}"
	  data-trigger="submit"
	  data-submit="ajax"
	  data-format="json"
	  data-modal-on-error=""
	  data-modal-on-error-target=""
	  data-modal-on-success="linkToText"
	  data-modal-on-success-target="#param-{$paramID}"
>
	<input type="hidden" name="user" value="{$user->get('userID')}" />
	<input type="hidden" name="param[language]"  value="{$this->language}" />
	<input type="hidden" name="format" value="" />
	<input type="hidden" name="param[ID]" value="{$paramID}" />

	<p class="text-info">{$headline}</p>
	<p class="alert alert-info alert-inline" role="alert"><i class="fas fa-info-circle mr-3"></i>{$hint}</p>

	<div class="form-group row">
		<div class="col">
			<input type="text" name="param[name]" class="form-control autofocus" id="ipt-paramName" placeholder="{$placeholder}" required />
		</div>
	</div>
</form>
HTML;
?><?php endif; ?>
<?php /* END:  In-place-translation widget --- DiSABLED UNTiL FiXED */ ?>

		<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=process&layout=edit&pid=%d', $this->language, $this->item->get('procID') ))); ?>"
		   role="button"
		   class="position-relative d-block"
		   id="param-<?php echo (int) $paramID; ?>"

		   <?php /* BEGIN:  In-place-translation widget attribs --- DiSABLED UNTiL FiXED */ ?>
		   <?php if (FALSE) : ?>
		   href="javascript:void(0)"
		   data-toggle="modal"
		   data-size="lg"
		   data-backdrop="static"
		   data-target="#mainModal"
		   data-modal-title="<?php echo Text::translate('COM_FTK_HEADING_TRANSLATE_TECHNICAL_PARAMETER_TEXT', $this->language); ?>"
		   data-modal-content="<?php echo base64_encode($modalForm); ?>"
		   data-modal-submittable="true"
		   aria-haspopup="true"
		   <?php endif; ?>
		   <?php /* END:  In-place-translation widget attribs --- DiSABLED UNTiL FiXED */ ?>

		   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_TRANSLATE_TECHNICAL_PARAMETER_TEXT', $this->language); ?>"
		><?php echo html_entity_decode($param->get('name')); ?></a>
		<?php endif; ?>
	</li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>
