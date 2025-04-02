<?php
// Register required libraries.
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;
use \Factory;
use \Helper\LayoutHelper;
use \Helper\StringHelper;
use \Messager;
use \Text;
use Symfony\Component\HttpFoundation\Session\Session;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* init vars */
$lang  = $this->get('language');

// Get user.
$view  = $this->__get('view');
$model = $view->get('model');
$user  = $view->get('user');

$input = new Input;

// The view name from $_REQUEST may be different to this objects name like is when a user's session expires
// while submitting a form (e.g.: view name from $_POST = 'article' vs. view name from this $object = 'user')
if (!is_null($input->getWord('view')) && $input->getWord('view') !== $view->get('name')) :
	/*// @debug
	if (App::getAppUser()->isProgrammer()) :
		echo '<pre>' . print_r('Reset $_POST and $_FILES', true) . '</pre>';
	endif;*/

	$_POST  = [];
	$_FILES = [];

	$input  = new Input;
endif;

/*// @debug
if (App::getAppUser()->isProgrammer()) :
	echo '<pre>$_POST 1: ' . print_r($_POST, true) . '</pre>';
endif;*/

$layout   = $input->getCmd('layout');
$task     = $input->post->getCmd('task',      $input->getCmd('task'));
$format   = $input->post->getWord('format',   $input->getWord('format'));
$return   = $input->post->getBase64('return', $input->getBase64('return'));
$redirect = (!is_null($return) && StringHelper::isBase64Encoded($return))
				? base64_decode($return)
				: $input->server->getUrl('HTTP_REFERER', $input->server->getUrl('PHP_SELF') . '?hl=' . $lang);
$redirect = new Joomla\Uri\Uri(basename($redirect));
$redirect->delVar('loggedout');
$redirect->delVar('se');

// Set redirect route into $_POST will make the route available to the login- and logout-scripts.
// We use $_POST as $_GET has limited capacity and may throw an error if max length has exceeded.
//$_GET['return']  = base64_encode( basename( $redirect->toString() ) );
$_POST['return'] = base64_encode( basename( $redirect->toString() ) );
?>
<?php switch (true) : ?>
<?php // Render users list. ACTUALLY NEVER CATCHED!
	case ($view->get('name') === 'users' && $view->get('layout') === 'list') :
		if (!is_a($user, '\Entity\User')) :
			sprintf('403 %s', Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language));
			exit;
		endif;

		// TODO - implement ACL here
//		if (!$user->isAdmin) :
//			die('Access denied.');
//		endif;

		echo LayoutHelper::render('forms.users.' . $layout, (object) ['user' => $user]);
	break;
?>
<?php // Add user form data received. Process it and redirect the user to where it previously wanted to go to.
	case ($view->get('name') === 'user' && $task === 'add') :
		/*// @debug
		if (App::getAppUser()->isProgrammer()) :
			echo '<pre>$_POST: '   . print_r($_POST, true) . '</pre>';
			echo '<pre>is empty? ' . print_r(empty($_POST) ? 'YES' : 'NO', true) . '</pre>';
			die;
		endif;*/

		if (!empty($_POST)) :
			$view->saveAdd($redirect->toString());
		endif;
	break;
?>
<?php // Edit user form data received. Process it and redirect the user to where it previously wanted to go to.
	case ($view->get('name') === 'user' && $task === 'edit') :
		/*// @debug
		if (App::getAppUser()->isProgrammer()) :
			echo '<pre>$_POST: '   . print_r($_POST, true) . '</pre>';
			echo '<pre>is empty? ' . print_r(empty($_POST) ? 'YES' : 'NO', true) . '</pre>';
			die;
		endif;*/

		if (!empty($_POST)) :
			$view->saveEdit($redirect->toString());
		endif;
	break;
?>
<?php // Generate a password to be filled stored for a created/edited user account.
	case ($view->get('name') === 'user' && $task === 'genpw') :
		/*// @debug
		if (App::getAppUser()->isProgrammer()) :
			echo '<pre>$_POST: '   . print_r($_POST, true) . '</pre>';
			echo '<pre>is empty? ' . print_r(empty($_POST) ? 'YES' : 'NO', true) . '</pre>';
			die;
		endif;*/

		if (mb_strtolower($format) === 'json') :
			echo $view->genPasswordJSON();
			exit;
		else :
			$view->genPassword();
		endif;
	break;
?>
<?php // Edit user status form data received. Process it and redirect the user to where it previously wanted to go to.
	case ($view->get('name') === 'user' && $task === 'lock') :
		/*// @debug
		if (App::getAppUser()->isProgrammer()) :
			echo '<pre>$_POST: '   . print_r($_POST, true) . '</pre>';
			echo '<pre>is empty? ' . print_r(empty($_POST) ? 'YES' : 'NO', true) . '</pre>';
//			echo '<pre>view: ' . print_r($view, true) . '</pre>';
//			die;
		endif;*/

		if (!empty($_POST)) :		
			$view->saveState($redirect->toString());
		endif;
	break;
?>
<?php // Login form data received. Process it and redirect the user to where it previously wanted to go to.
	case ($view->get('name') === 'user' && $task === 'login') :
		// Get current session.
		$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);

		// Only start if it hasn't already.
		if (!$session->isStarted()) :
			$session->start();
		endif;

		$layout   = 'login';

		// Get sanitized $_POST data.
		$email    = $input->post->getEmail('email');
		$password = $input->post->getPassword('password');
		$locale   = $input->post->getWord('locale');
		$remember = $input->post->getInt('remember');

		// Get empty user object and attempt to log the user in.
		// TODO - refactor to $view->getModel()->getInstance()
		$user = $model->getInstance('user', ['language' => $lang])->getUserByCredentials($email, $password);	// result should be an instance of '\Entity\User' populated with data of the related user account

		// User could not be found. Errors are set in class {@see User}
		if (!is_a($user, '\Entity\User')) :
			// Invalidate current session.
			$session->clear();
			$session->invalidate();

			// User access forbidden.
			http_response_code('403');

			// Redirect to homepage.
			header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
			exit;
		elseif (is_a($user, '\Entity\User') && !$user->isRegistered()) :
			// Invalidate current session.
			$session->clear();
			$session->invalidate();

			// User isn't found.
			http_response_code('404');

			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf('%s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_FOUND_TEXT', $this->language),
					Text::translate('COM_FTK_HINT_PLEASE_CHECK_INPUT_FOR_TYPOS_TEXT', $this->language)
				)
			]);

				// Redirect to homepage.
			header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
			exit;
		else :
			/* STEP 1:  Check if the user's organisation is blocked? */

			if ($input->get('auth') === 'dev-op') :
				die('ELSE');
			endif;

			$userOrganisation = $model->getInstance('organisation', ['language' => $lang])->getItem((int) $user->get('orgID'));

			if (true == $userOrganisation->get('blocked')) :
				Messager::setMessage([
					'type' => 'info',
					'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_ACCESS_BLOCKED_TEXT', $this->language)
				]);

				// Free memory.
				unset($userOrganisation);
				unset($user);

				// User access forbidden.
				http_response_code('403');

				// Redirect to homepage.
				header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
				exit;
			endif;

			/* STEP 2:  Check if the user is blocked? */

			if (true == $user->get('blocked')) :
				Messager::setMessage([
					'type' => 'info',
					'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_LOGIN_TEXT', $this->language)
				]);

				// Free memory.
				unset($userOrganisation);
				unset($user);

				// User access forbidden.
				http_response_code('403');

				// Redirect to homepage.
				header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
				exit;
			endif;

			/* STEP 3:  Log the user in and apply previous checks */

			$loginResult = $user->login($email, $password);

			/* STEP 4:  Check if the user was found. */

			if (!is_a($loginResult, '\Entity\User')) :
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_FOUND_TEXT', $this->language)
				]);

				// Free memory.
				unset($loginResultOrganisation);
				unset($userOrganisation);
				unset($user);

				// User unauthorized.
				http_response_code('401');

				// Redirect to homepage.
				header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
				exit;
			endif;

			/* STEP 5:  Check if the user's organisation is blocked? */

			$loginResultOrganisation = $model->getInstance('organisation', ['language' => $lang])->getItem((int) $loginResult->get('orgID'));

			if (true == $loginResultOrganisation->get('blocked')) :
				Messager::setMessage([
					'type' => 'info',
					'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_ACCESS_BLOCKED_TEXT', $this->language)
				]);

				// Free memory.
				unset($loginResultOrganisation);
				unset($userOrganisation);
				unset($user);

				// User access forbidden.
				http_response_code('403');

				// Redirect to homepage.
				header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
				exit;
			endif;

			// Free memory.
			unset($loginResultOrganisation);

			/* STEP 6:  Check if the user is blocked? */

			if (true == $loginResult->get('blocked')) :
				Messager::setMessage([
					'type' => 'info',
					'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_LOGIN_TEXT', $this->language)
				]);

				// Free memory.
				unset($loginResultOrganisation);
				unset($userOrganisation);
				unset($user);

				// User access forbidden.
				http_response_code('403');

				// Redirect to homepage.
				header(sprintf('Location: index.php?hl=%s', $input->getWord('hl', Factory::getConfig()->get('app_language'))));
				exit;
			endif;

			// Update SESSION - store user object
			$session->set('user', $user);

			/*// Application feedback. (NOTE: disabled on 2021-02-11 on demand)
			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_LOGIN_SUCCESS_TEXT', $this->language)
			]);*/

			// User successfully logged in.
//			http_response_code('200');

			// Redirect to its previous screen or home page.
			header('Location: ' . $redirect->toString());
			exit;
		endif;
	break;
?>
<?php // Logout link clicked. Check and redirect user to logout screen.
	case ($view->get('name') === 'user' && $task === 'logout') :
		if (is_a($user, '\Entity\User')) :
			$logout = $user->logout();	// Update user's last log out date column in database
		endif;

		// Get current session.
		$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);

		// Invalidate session.
		$session->clear();
		$session->invalidate();

		$referer = $return = $redirect = null;

		// Calculate HTTP_REFERER for the next login. (see: https://forums.phpfreaks.com/topic/135931-solved-clearing-http_referer/)
		// se=0 means 'session expired = false', whereas se=1 means 'session expired = true'
		if ($input->getCmd('se') == '0') :
			// User actively logged out: Redirect without caching to homepage.
			header('Refresh: 0; url=index.php?hl=' . $lang);
			exit;
		else :
			// User session expired due to inactivity: Redirect to previous page after login.
			// Note: The URL parameter 'se=' is required by the login screen to calculate what message to render.
			header('Location: index.php?hl=' . $lang . '&se=' . $input->getCmd('se'));
			exit;
		endif;
	break;
?>
<?php // Edit user status form data received. Process it and redirect the user to where it previously wanted to go to.
	case ($view->get('name') === 'user' && $task === 'unlock') :
		/*// @debug
		if (App::getAppUser()->isProgrammer()) :
			echo '<pre>$_POST: '   . print_r($_POST, true) . '</pre>';
			echo '<pre>is empty? ' . print_r(empty($_POST) ? 'YES' : 'NO', true) . '</pre>';
//			echo '<pre>view: ' . print_r($view, true) . '</pre>';
//			die;
		endif;*/

		if (!empty($_POST)) :
			$view->saveState($redirect->toString());
		endif;
	break;
?>
<?php // No action requested. Just render the login form.
	default :
		$layout = 'login';

		echo LayoutHelper::render('forms.user.' . $layout, new stdclass);
	break;
?>
<?php endswitch; ?>
