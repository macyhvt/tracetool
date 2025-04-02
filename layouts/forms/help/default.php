<?php
// Register required libraries.
use Nematrack\Crypto;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$model  = $view->get('model');
$user   = $view->get('user');
$layout = $input->getCmd('layout');
?>
<div class="card-deck my-0">
	<div class="card vcard position-relative">
		<!--img src="<?php // echo UriHelper::osSafe( UriHelper::fixURL( '/assets/img/persona/sm.png' ) ); ?>"
			 class="card-img-top"
			 alt="<?php echo sprintf('%s: %s',
				Text::translate('COM_FTK_LABEL_VCARD_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_PROJECT_MANAGEMENT_TEXT', $this->language)); ?>"
		/-->
		<div class="card-body">
            <h5 class="card-title small mb-4"><?php echo sprintf('%s', Text::translate('COM_FTK_LABEL_LEADERSHIP_TEXT', $this->language)); ?></h5>
			<p class="card-text mb-0 mb-lg-2"><?php echo sprintf('%s', FTKPARAM_PERSONA_PROJECT_MANAGEMENT); ?></p>
			<p class="card-text mb-lg-5"><?php echo Text::translate('COM_FTK_LABEL_GM_NEMA_TEXT', $this->language); ?><?php /*echo sprintf('%s %s / %s',
				Text::translate('COM_FTK_LABEL_DIRECTOR_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_ELECTRICAL_ENGINEERING_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_NEW_MARKETS_TEXT', $this->language));*/ ?></p>
			<dl class="row pt-3 pt-lg-0">
				<!--<dt class="col-1 dt dt-1">
					<i class="fas fa-phone"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_FON_TEXT', $this->language); */?>:</span>
				</dt>-->
				<!--<dd class="col-10 col-sm-11 col-md-11 col-lg-11 dd dd-1">
					<a href="tel:04955229010501"
					   title="<?php /*echo Text::translate('COM_FTK_LINK_TITLE_START_PHONE_CALL_TEXT', $this->language); */?>"
					   aria-label="<?php /*echo Text::translate('COM_FTK_LINK_TITLE_START_PHONE_CALL_TEXT', $this->language); */?>"
					>
						<span class="country-code mr-1">+49</span>&ndash;<span class="call-number d-inline-block ml-1">5522&nbsp;9010&nbsp;501</span>
					</a>
				</dd>
				<dt class="col-1 dt dt-2">
					<i class="fas fa-fax"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_FAX_TEXT', $this->language); */?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-11 col-lg-11 dd dd-2">
					<span class="country-code mr-1">+49</span>&ndash;<span class="call-number d-inline-block ml-1">5522&nbsp;9010&nbsp;920</span>
				</dd>-->
				<dt class="col-1 dt dt-3">
					<i class="fas fa-mobile-alt"></i><span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_MOBILE_FON_TEXT', $this->language); ?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-11 col-lg-11 dd dd-3">
					<a href="tel:0491789010267"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_START_MOBILE_CALL_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_START_MOBILE_CALL_TEXT', $this->language); ?>"
					>
						<span class="country-code mr-1">+49</span>&ndash;<span class="d-lg-none pl-1"></span><span class="call-number d-inline-block ml-1">178&nbsp;9010&nbsp;267</span>
					</a>
				</dd>
				<dt class="col-1 dt dt-4">
					<i class="fas fa-at"></i><span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_TEXT', $this->language); ?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-11 col-lg-11 mb-0 dd dd-4">
					<?php $email = FTKPARAM_EMAIL_PROJECT_MANAGEMENT; $pcs = explode('@', $email); ?>
					<a href="javascript:FTKAPP.functions.sendEncryptedMail('<?php echo Crypto::encryptEmailAddress($email); ?>');"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SEND_EMAIL_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SEND_EMAIL_TEXT', $this->language); ?>"
					>
						<span class="country-code"><?php echo current($pcs); ?></span>@<span class="call-number d-inline-block"><?php echo end($pcs); ?></span>
					</a>
				</dd>
			</dl>
		</div>
	</div>
	<div class="card vcard position-relative">
		<!--img src="<?php // echo UriHelper::osSafe( UriHelper::fixURL( '/assets/img/persona/nb.png' ) ); ?>"
			 class="card-img-top"
			 alt="<?php echo sprintf('%s: %s',
				Text::translate('COM_FTK_LABEL_VCARD_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_PROGRAMMING_AND_DEVELOPMENT_TEXT', $this->language)); ?>"
		/-->
		<div class="card-body">
			<h5 class="card-title small mb-4"><?php echo sprintf('%s', Text::translate('COM_FTK_LABEL_PRODUCTION_MANAGEMENT_TEXT', $this->language)); ?></h5>
			<p class="card-text mb-0 mb-lg-2"><?php echo sprintf('%s', FTKPARAM_PERSONA_MOULD_PROJECT_MANAGEMENT); ?></p>
			<p class="card-text mb-lg-5"><?php echo Text::translate('COM_FTK_LABEL_PRODUCTION_MANAGER_TEXT', $this->language); ?></p>
			<dl class="row pt-3 pt-lg-0">
				<dt class="col-1 dt dt-1">
					<i class="fas fa-phone"></i><span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_FON_TEXT', $this->language); ?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 dd dd-1">
					<a href="tel:+36205463168"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_START_PHONE_CALL_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_START_PHONE_CALL_TEXT', $this->language); ?>"
					>
						<span class="country-code mr-1">+36</span>&ndash;<span class="call-number d-inline-block ml-1">20&nbsp;546&nbsp;3168</span>
					</a>
				</dd>
				<!--<dt class="col-1 dt dt-2">
					<i class="fas fa-fax"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_FAX_TEXT', $this->language); */?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 dd dd-2">
					<span class="country-code mr-1"></span>&ndash;<span class="call-number d-inline-block ml-1 pl-2"></span>
				</dd>
				<dt class="col-1 dt dt-3">
					<i class="fas fa-mobile-alt"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_MOBILE_FON_TEXT', $this->language); */?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 dd dd-3">

					   title="<?php /*echo Text::translate('COM_FTK_LINK_TITLE_START_MOBILE_CALL_TEXT', $this->language); */?>"
					   aria-label="<?php /*echo Text::translate('COM_FTK_LINK_TITLE_START_MOBILE_CALL_TEXT', $this->language); */?>"

						<span class="country-code mr-1"></span>&ndash;<span class="call-number d-inline-block"></span>

				</dd>-->
				<dt class="col-1 dt dt-4">
					<i class="fas fa-at"></i><span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_TEXT', $this->language); ?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 mb-0 dd dd-4">
					<?php $email = FTKPARAM_EMAIL_MOULD_PROJECT_MANAGEMENT; $pcs = explode('@', $email); ?>
					<a href="javascript:FTKAPP.functions.sendEncryptedMail('<?php echo Crypto::encryptEmailAddress($email); ?>');"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SEND_EMAIL_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SEND_EMAIL_TEXT', $this->language); ?>"
					>
						<span class="country-code"><?php echo current($pcs); ?></span>@<span class="call-number d-inline-block"><?php echo end($pcs); ?></span>
					</a>
				</dd>
			</dl>
		</div>
	</div>
	<div class="card vcard position-relative">
		<!--img src="<?php // echo UriHelper::osSafe( UriHelper::fixURL( '/assets/img/persona/tb.png' ) ); ?>"
			 class="card-img-top"
			 alt="<?php echo sprintf('%s: %s',
				Text::translate('COM_FTK_LABEL_VCARD_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_PROGRAMMING_AND_DEVELOPMENT_TEXT', $this->language)); ?>"
		/-->
		<div class="card-body">
			<h5 class="card-title small mb-4"><?php echo sprintf('%s', Text::translate('COM_FTK_LABEL_PROGRAMMING_TEXT', $this->language)); ?></h5>
			<p class="card-text mb-0 mb-lg-2"><?php echo sprintf('%s', FTKPARAM_PERSONA_PROGRAMMING_AND_TECH_SUPPORT); ?></p>
			<p class="card-text mb-lg-5"><?php echo sprintf('%s / %s',
				Text::translate('COM_FTK_LABEL_DEVELOPMENT_TEXT', $this->language),
				Text::translate('COM_FTK_LABEL_TECHNICAL_SUPPORT_TEXT', $this->language)); ?></p>
			<dl class="row pt-3 pt-lg-0">
				<!--<dt class="col-1 dt dt-1">
					<i class="fas fa-phone"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_FON_TEXT', $this->language); */?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 dd dd-1">
					<a href="tel:04955229010501"
					   title="<?php /*echo Text::translate('COM_FTK_LINK_TITLE_START_PHONE_CALL_TEXT', $this->language); */?>"
					   aria-label="<?php /*echo Text::translate('COM_FTK_LINK_TITLE_START_PHONE_CALL_TEXT', $this->language); */?>"
					>
						<span class="country-code mr-1">+49</span>&ndash;<span class="call-number d-inline-block ml-1">5522&nbsp;9010&nbsp;501</span>
					</a>
				</dd>
				<dt class="col-1 dt dt-2">
					<i class="fas fa-fax"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_FAX_TEXT', $this->language); */?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 dd dd-2">
					<span class="country-code mr-1">+49</span>&ndash;<span class="call-number d-inline-block ml-1">5522&nbsp;9010&nbsp;920</span>
				</dd>-->
				<dt class="col-1 dt dt-3">
					<i class="fas fa-mobile-alt"></i><span class="sr-only"><?php /*echo Text::translate('COM_FTK_LABEL_MOBILE_FON_TEXT', $this->language); */?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 dd dd-3">
					<a href="tel:+36703031312"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_START_MOBILE_CALL_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_START_MOBILE_CALL_TEXT', $this->language); ?>"
					>
						<span class="country-code mr-1">+36</span>&ndash;<span class="d-lg-none pl-1"></span><span class="call-number d-inline-block ml-1">70&nbsp;3031&nbsp;312</span>
					</a>
				</dd>
				<dt class="col-1 dt dt-4">
					<i class="fas fa-at"></i><span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_TEXT', $this->language); ?>:</span>
				</dt>
				<dd class="col-10 col-sm-11 col-md-10 col-lg-11 mb-0 dd dd-4">
					<?php $email = FTKPARAM_EMAIL_PROGRAMMING_AND_TECH_SUPPORT; $pcs = explode('@', $email); ?>
					<a href="javascript:FTKAPP.functions.sendEncryptedMail('<?php echo Crypto::encryptEmailAddress($email); ?>');"
					   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SEND_EMAIL_TEXT', $this->language); ?>"
					   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SEND_EMAIL_TEXT', $this->language); ?>"
					>
						<span class="country-code"><?php echo current($pcs); ?></span>@<span class="call-number d-inline-block"><?php echo end($pcs); ?></span>
					</a>
				</dd>
			</dl>
		</div>
	</div>
</div>
