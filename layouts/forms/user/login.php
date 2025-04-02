<?php
// Register required libraries.
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use \Helper\StringHelper;
use \Helper\UriHelper;
use \Messager;
use \Model;
use \Text;
use Symfony\Component\HttpFoundation\Session\Session;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Start session */
$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);

if (!$session->isStarted()) :
	$session->start();
endif;
?>
<?php /* Init vars */
$input    = new Input;
$debug    = $input->getBool('debug');
$view     = $input->getWord('view');

$langs    = Model::getInstance('languages')->getList(true);
$lang     = $input->get->getWord('hl');
//$lang     = (empty($lang)) ? $this->get('language') : $lang;
$lang     = $lang ?: $this->get('language');

// Rewrite of above code
$referer  = $input->server->getUrl('HTTP_REFERER');
$return   = $view ? $input->post->getBase64('return', $input->get->getBase64('return')) : null;
$redirect = (!is_null($return) && StringHelper::isBase64Encoded($return))
		? base64_decode($return)
		: sprintf('%s?hl=%s', $input->server->getUrl('PHP_SELF', 'index.php'), $lang)
;

if ($input->getCmd('se') == '1') :
	Messager::setMessage(
		[
			'type' => 'notice',
			'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_SESSION_EXPIRED_TEXT', $lang)
		],
		[
			'autohide' => false	// FIXME - fix how {@see Messager} handles message rendering, as currently config options are not processed
		]
	);
endif;
?>

<style>
/*.form-signin-container {
	padding-top: 50%;
	margin-top: -35%;
}*/

.form-signin {
	width: 100%;
	max-width: 330px;
	padding: 15px;
	margin: auto;
}
.form-signin .checkbox {
	font-weight: 400;
}
.form-signin .form-control {
	position: relative;
	box-sizing: border-box;
	height: auto;
	padding: 10px;
	font-size: 16px;
}
.form-signin .form-control:focus {
	z-index: 2;
}
.form-signin [name="email"] {
	margin-bottom: -1px;
	border-bottom-right-radius: 0;
	border-bottom-left-radius: 0;
}
.form-signin input[type="password"]:not(.validation-result) {
	margin-bottom: 10px;
	border-top-left-radius: 0;
	border-top-right-radius: 0;
}
/* Override bottom margin of inline error message */
.form-control.text-danger + .text-danger {
	margin-bottom: 1rem;
}
#cb-login-remember {}
#cb-login-remember > label {
	line-height: 1;
}
#cb-login-remember > label:hover {
	cursor: pointer;
}
#cb-login-remember > label > input {}
#cb-login-remember > label > input:hover {}
</style>

<div class="form-container my-md-3 my-lg-5 py-lg-5 text-center">
	<form action="index.php?hl=<?php echo $lang; // FIXME - involve URIHelper fixUri ossafe etc. like in the other forms ?>"
	      method="post"
		  name="userLoginForm"
		  class="form-horizontal form-signin userForm validate"
		  data-submit=""
	>
		<input type="hidden" name="view"   value="user" />
		<input type="hidden" name="task"   value="login" />
		<input type="hidden" name="return" value="<?php echo base64_encode($redirect); ?>" />
		<?php // TODO - create and render unique form token widget ?>

		<img src="<?php echo UriHelper::osSafe('/assets/img/global/logos/froetek-logo.png'); ?>"
			 class="mb-4 mb-3 mb-lg-4 img-ftk-shadow"
			 alt="Logo: "
			 width=""
			 height=""
		>

		<h1 class="h4 d-block mb-3 mb-lg-4 font-weight-normal"><?php echo Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_SIGN_TEXT', $lang); ?></h1>

		<label for="inputEmail" class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_TEXT', $lang); ?></label>
		<input type="email"
			   name="email"
			   class="form-control"
			   id="inputEmail"
			   minlength="5"
			   maxlength="50"
			   placeholder="<?php echo Text::translate('COM_FTK_LABEL_EMAIL_ADDRESS_TEXT', $lang); ?>"
			   required
			   autofocus
			   autocapitalize="off"
			   autocomplete="off"
			   spellcheck="false"
		       data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $lang); ?>"
			   data-rule-email="true"
			   data-msg-email="<?php echo Text::translate('COM_FTK_HINT_UNKNOWN_EMAIL_ADDRESS_TEXT', $lang); ?>"
		/>

		<label for="inputPassword" class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $lang); ?></label>
		<input type="password"
			   name="password"
			   class="form-control"
			   id="inputPassword"
			   minlength="10"
			   placeholder="<?php echo Text::translate('COM_FTK_LABEL_PASSWORD_TEXT', $lang); ?>"
			   required
			   autocapitalize="off"
			   autocomplete="off"
			   spellcheck="false"
			   data-rule-required="true"
			   data-msg-required="<?php echo Text::translate('COM_FTK_HINT_MANDATORY_FIELD_TEXT', $lang); ?>"
		/>

		<?php // TODO - make the selected locale the one to be set in index.php. It should have higher priority than the auto detectd language from the browser. ?>
		<label for="inputLocale" class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_LANGUAGE_TEXT', $lang); ?></label>
		<select name="locale"
				class="form-control"
				id="inputLocale"
				data-lang="<?php echo $lang; ?>"
		>
		<?php array_walk($langs, function($lng, $tag) use(&$lang) { ?>
			<?php $lng = new Registry($lng); ?>
			<option value="<?php echo $lng->get('tag'); ?>"<?php echo ($lng->get('tag') == $lang ? ' selected' : ''); ?>><?php
				echo Text::translate($lng->get('link.text'), $lang);
			?></option>
		<?php }); ?>
		</select>

		<?php if (FALSE) : ?>
		<div class="checkbox mt-3 mb-5" id="cb-login-remember">
			<label for="inputRememberMe" class="d-block m-0">
				<input type="checkbox"
					   name="remember"
					   class="d-inline-block align-bottom"
					   id="inputRememberMe"
					   value="1"
				/>
				<span class="d-inline-block ml-1"><?php echo Text::translate('COM_FTK_LABEL_REMEMBER_ME_TEXT', $lang); ?></span>
			</label>
		</div>
		<?php else : ?>
		<br>
		<?php endif; ?>

		<button type="submit" class="btn btn-block btn-lg btn-primary btn-submit btn-ftk-shadow btn-ftk-blue">
			<?php echo Text::translate('COM_FTK_BUTTON_TEXT_LOGIN_TEXT', $lang); ?>
			<i class="fas fa-sign-in-alt ml-1"></i>
		</button>
	</form>
</div>

<?php // Free memory
unset($input);
unset($langs);
unset($referer);
