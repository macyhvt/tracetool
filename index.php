<?php
$start_time = microtime(true);
// Make sure that this php.ini parameter is set to 0 as documented in Symfony session docs {@link https://symfony.com/doc/current/components/http_foundation/sessions.html}.
ini_set('session.auto_start', 0);

// Define path to sessions directory.
// See @link https://stackoverflow.com/a/27079746 for what's the '0;640;' prefix for.
if (is_dir(realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/cgi-fpm/sessions'))) :
	ini_set('session.save_path', realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/cgi-fpm/sessions/' . basename(__DIR__)));
//	ini_set('session.save_path', '0;640;' . realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '/cgi-fpm/sessions/' . basename(__DIR__)));
endif;
?>
<?php // Block Robots from site crawling
// see: https://developers.google.com/search/reference/robots_meta_tag?hl=de
// see: https://pagerangers.com/seo-handbuch/onpage/was-ist-das-robots-tag-und-wo-wird-es-gesetzt
// see: https://www.omt.de/suchmaschinenoptimierung/bye-bye-noindex-nofollow-und-crawl-delay-robots-txt-wird-revolutioniert
header('X-Robots-Tag: noindex, nofollow, noarchive, noimageindex, noodp, nosnippet, noydir');
if (FALSE) : header('X-Robots-Tag: noindex, nofollow, noarchive, noimageindex, noodp, nosnippet, noydir, notranslate', true); endif;
?>
<?php // Init constants
defined ('_FTK_APP_') OR define('_FTK_APP_', 1);
define  ('FTKPATH_BASE', $_SERVER['DOCUMENT_ROOT']);
define  ('FTKURI_BASE',  preg_replace( '#//#', '/', substr( $_SERVER['PHP_SELF'], 0, strrpos( $_SERVER['PHP_SELF'], '/' ) + 1 ) ) );
?>
<?php // Load dependencies via composer

use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Nematrack\Access\User;
use Nematrack\App;
use Nematrack\Factory;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Menu;
use Nematrack\Messager;
use Nematrack\Model;
use Nematrack\Service;
use Nematrack\Template;
use Nematrack\Text;
use Nematrack\View;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use voku\helper\HtmlMin;
use WyriHaximus\HtmlCompress\Factory as HtmlCompressor;

// I M P O R T A N T:   Whenever a new class file has been added run the following shell command from within root folder to register it:  cd lib && composer dump-autoload -o
// see: https://florianbrinkmann.com/autoloader-composer-4942/
// see: https://stackoverflow.com/a/28256734
// see: https://www.youtube.com/watch?v=v-7bTaT9GyY

//--> ENABLE IF composer.json resides under /
//if (!file_exists(FTKPATH_BASE . '/vendor/autoload.php')) :
//	throw new RuntimeException('Composer autoload script could not be found. Please make sure to run "composer install" on the commandline first.');
//endif;
//require_once FTKPATH_BASE . '/vendor/autoload.php';
//require_once FTKPATH_BASE . '/vendor/froetek/code-generator/src/coder.php';
//require_once FTKPATH_BASE . '/vendor/froetek/code-generator/src/barcoder.php';
//--> END

//--> ENABLE IF composer.json resides under /lib/
if (!file_exists(__DIR__ . '/lib/vendor/autoload.php')) :
	throw new RuntimeException('Composer autoload script could not be found. Please make sure to run "composer install" on the commandline first.');
endif;

require_once __DIR__ . '/lib/vendor/autoload.php';
//FIXME - Why is this lib not autoloaded and not available after "use Froetek\..."
require_once __DIR__ . '/lib/vendor/froetek/code-generator/src/coder.php';
require_once __DIR__ . '/lib/vendor/froetek/code-generator/src/barcoder.php';
//--> END
?>
<?php // Toggle error display
if (App::isDevEnv()) :
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);

	// Report all errors (as per default in php.ini).
	ini_set('error_reporting', E_ALL);
//	ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);

//	error_reporting(-1);
/*else :
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);

	// Show all errors, except for deprecated code and coding standards warnings (as per default in php.ini).
	ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);

	// Turn off error reporting.
//	ini_set('error_reporting', 0);*/
endif;
?>
<?php // Get application configuration
$config      = Factory::getConfig();
?>
<?php // Configure application language
$input       = App::getInput();
$browser     = (function_exists('get_browser')) ? new Registry(get_browser($input->server->getString('HTTP_USER_AGENT', ''))) : null;
$lang        = null;

// Fix potential browser version detection fail.
if ((int) $browser->get('version') == 0) :
	// Find match in user agent string.
	preg_match(sprintf('~%s\/(\d{1,4}\.\d{1,4}(\.\d{1,4})*)~i', $browser->get('browser')), $input->server->getString('HTTP_USER_AGENT', ''), $version);

	// Drop very first entry in matches list, as it is most likely the complete string including vendor name.
	array_shift($version);

	$browser->set('version', array_shift($version));
endif;

// First read client language from HTTP headers (typically something like "de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7").
$clientLang  = $input->server->getString('HTTP_ACCEPT_LANGUAGE', '');
// Second check for user selected language via form field 'locale' on login screen.
$postLang    = $input->post->getWord('locale');				// highest priority has the lang a user sent via login form
// Third check for language tag from URL query variable 'hl' in global $_GET.
$getLang     = $input->get->getWord('hl');
// Fall back to PHP global 'Locale'. (see: @link https://www.php.net/manual/de/class.locale.php)
// Code borrowed with some modification from {@link https://www.php.net/manual/de/function.gettext.php#96540}
$browserLang = mb_strtolower( substr( (class_exists('Locale') ? Locale::acceptFromHttp( $clientLang ) : $clientLang), 0, 2 ) );
$userLang    = null;

// Calculate display language.
switch (true) :
	// Fetched from POST data
	case (!empty($postLang)) :
		$lang = $postLang;
	break;

	// Fetched from GET data
	case (!empty($getLang)) :
		$lang = $getLang;
	break;

	/*// Fetched from useragent (browser)
	case (!empty($browserLang)) :
		$lang = $browserLang;
	break;*/

	// Fetched from USER profile
	case (!empty($userLang)) :
		$lang = $userLang;
	break;

	// Fallback to system default
	default :
		$lang = $config->get('app_language');
	break;
endswitch;
?>
<?php // Provide API service
// If a service has been requested, provide the service without delivering the document
if (!is_null($service = $input->getString('service'))) :
	// Get service object.
	try
	{
		$serviceObject = (class_exists('Nematrack\Service')) ? Service::getInstance(mb_strtolower($service), ['language' => $lang ]) : null;
	}
	catch (Exception $e)
	{
		//TODO - log error
		// die( sprintf('Error (#%s): %s', $e->getCode(), $e->getMessage() ) );

		$message = Text::translate('COM_FTK_ERROR_APPLICATION_SERVICE_NOT_AVAILABLE_TEXT');

		http_response_code('503');	// means: "Service unavailable"

		$response = ['error' => $message];

		/*// DiSABLED on 2023-07-06 - replaced with switch/case-block
		if ($input->getWord('format') === 'json') :
			header("Content-type: application/json; charset=utf-8");

			echo json_encode($response, JSON_THROW_ON_ERROR);
			exit;
		else  :
			echo $response['error'];
		endif;*/
		switch ($input->getWord('format'))
		{
			case 'jsonp' :
				header("Content-type: text/javascript; charset=utf-8");

				echo sprintf('%s(%s)', $input->get('callback', 'jsonpCallback'), json_encode($response, JSON_THROW_ON_ERROR));
			break;

			case 'json' :
				header("Content-type: application/json; charset=utf-8");

				echo json_encode($response, JSON_THROW_ON_ERROR);
			break;

			default :
				echo $response['error'];
		}

		// Exit application.
		exit;
	}

	// Request service if such service is provided.
	try
	{
		switch (get_class($serviceObject)) :
			case 'Nematrack\Service\Api' :
				$serviceObject->call(
					$input->post->getCmd('task', $input->get->getCmd('task', '')),
					$input->request->getArray()	// API requests must not only accept POST data, but also GET data
				);
			break;

			case 'Nematrack\Service\Provide' :
				if (method_exists($serviceObject, $service)) :
					$serviceObject->{$service}(
						$input->get->getCmd('what', $input->post->getCmd('what')),
						$input->get->getWord('format', $input->post->getWord('format'))
					);
//				else :  //TODO - extend \Exception class and implement ServiceException
				endif;
			break;

			/*// Service "Translate" is no longer available - live translation feature was buggy and development has been stoped.
			case 'Nematrack\Service\Translate' :
				if (method_exists($serviceObject, $service)) :
					$serviceObject->{$service}(
						$input->get->getCmd('what', $input->post->getCmd('what')),
						$input->get->getWord('format', $input->post->getWord('format')),
						$input->post->getArray()
					);
				else :
					//TODO - extend \Exception class and implement ServiceException
				endif;
			break;*/
		endswitch;
	}
	catch(Exception $e)
	{
		//TODO - log error
//		die( sprintf('%s: %s', Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT'), htmlentities($e->getMessage())) );

		//TODO - translate
// 		$message = Text::translate('An error occured while trying to process the most recent request.');
		$message = $e->getMessage();

		http_response_code($e->getCode());

		$response = ['error' => $message];

		/*// DiSABLED on 2023-07-06 - replaced with switch/case-block
		if ($input->getWord('format') === 'json') :
			header("Content-type: application/json; charset=utf-8");

			echo json_encode($response, JSON_THROW_ON_ERROR);
			exit;
		else  :
//			echo '<pre class="text-red">' . print_r($response['error'], true) . '</pre>';
		endif;*/
		switch ($input->getWord('format'))
		{
			case 'jsonp' :
				header("Content-type: text/javascript; charset=utf-8");

				echo sprintf('%s(%s)', $input->get('callback', 'jsonpCallback'), json_encode($response, JSON_THROW_ON_ERROR));
			break;

			case 'json' :
				header("Content-type: application/json; charset=utf-8");

				echo json_encode($response, JSON_THROW_ON_ERROR);
			break;

			default :
				echo $response['error'];
		}

		// Exit application.
		exit;
	}

	exit;
endif;
?>
<?php /* Start session */	//TODO - create a SESSION wrapper and let it handle that stuff to prevent this script from having redundant code ?>
<?php // Start new Symfony\Component\HttpFoundation\Session\Session or continue existing one
if (class_exists('\Symfony\Component\HttpFoundation\Session\Session')) :
	$lifetime = ini_get('session.gc_maxlifetime');  // Session lifetime in seconds as configured in php.ini
	$session  = new Session(new NativeSessionStorage([], new NativeFileSessionHandler()));

	if (!$session->isStarted()) :
		$session->set('navigator', $browser->toArray());

		$session->start();
	endif;

	// @debug
//	echo '<pre>session: ' . print_r($session, true) . '</pre>';
//	die('handle Symfony Session');

	$time = time();
	$last = $session->getMetadataBag()->getLastUsed();
	$idle = ($time - $last);

	// @debug
//	echo '<pre>current time: ' . print_r(date('h:i:s', $time), true) . '</pre>';
//	echo '<pre>last used: '    . print_r(date('h:i:s', $last), true) . '</pre>';
//	echo '<pre>idle time: '    . print_r(sprintf('%d seconds', $idle), true) . '</pre>';
//	echo '<pre>lifetime: '     . print_r(sprintf('%d seconds (%d hours)', $lifetime, $lifetime / 3600), true) . '</pre>';
//	echo '<pre>'               . print_r(str_repeat('-', 80), true) . '</pre>';

//	echo '<pre>metadataBag  c (created): '   . print_r(date('h:i:s', $session->getMetadataBag()->getCreated()), true) . '</pre>';
////	echo '<pre>metadataBag  u (updated): '   . print_r(sprintf('%s (+ %d minutes)', date('h:i:s', 1653051745), date('s', 1653051745 - 1653050691)), true) . '</pre>';
//	echo '<pre>metadataBag lu (last used): ' . print_r(date('h:i:s', $session->getMetadataBag()->getLastUsed()), true) . '</pre>';
//	echo '<pre>metadataBag  l (lifetime): '  . print_r(sprintf('%d seconds (%d hours)', $lt = $session->getMetadataBag()->getLifetime(), $lt / 3600), true) . '</pre>';

	if ($idle > $lifetime) :
//		die('Session is EXPIRED');
		$isTimeout = is_null($input->getWord('loggedout'));
		$isSignOut = ($input->getWord('loggedout') == 'true');

		if ($isTimeout) :
			// $session->migrate(true, $lifetime);
			$session->invalidate();		// use on log out

			// Redirect to login screen.
			$return   = new Uri($input->server->getUrl('REQUEST_URI'));
			$return   = (mb_strlen($return->getQuery())) ? $return : new Uri($input->server->getUrl('PHP_SELF'));
			$redirect = new Uri('index.php?hl=' . $lang . '&view=user&task=logout');

			if ($input->getInt('re')) :
				$redirect->setVar('se', $input->get->getInt('se'));
				$redirect->delVar('re');
				$redirect->setVar('return', $input->post->getBase64('return', $input->get->getBase64('return')));
			else:
				$redirect->setVar('se', 1);
				$redirect->setVar('return', base64_encode( basename( $return->toString() ) ));	// REQUEST_URI is the link a user clicked before it is redirected to the login screen
			endif;

			header('Location: ' . $redirect->toString());
			exit;
		// Current app implementation does in fact never reach this case, but safety first.
		elseif ($isSignOut) :
			// Redirect to homepage.
			$redirect = new Uri($input->server->getUrl('PHP_SELF'));
			$redirect->delVar('loggedout');
			$redirect->delVar('se');

			header('Location: ' . $redirect->toString());
			exit;
		endif;
//	else :
//		die('Session is ACTIVE');
	endif;
?>
<?php // Start new native PHP session or continue existing one ?>
<?php elseif (FALSE) :
	$session = session_start();

	$session['navigator'] = $browser->toArray();

	// @debug
//	echo '<pre>session: ' . print_r($session, true) . '</pre>';
//	die('handle native Session');

	$isNewSession = is_null(ArrayHelper::getValue($_SESSION, 'expired'));

	$session['timeout']   = ini_get('session.gc_maxlifetime');
	$session['timestamp'] = time();

	if ($isNewSession) :
		$session['expired']   = false;
	else :
		$session['expired']   = ((time() - ArrayHelper::getValue($_SESSION, 'timestamp', 0)) > ArrayHelper::getValue($_SESSION, 'timeout', ini_get('session.gc_maxlifetime')));

		$isExpiredSession = ArrayHelper::getValue($_SESSION, 'expired');

		if ($isExpiredSession) :
			// If the session has auto-expired, redirect user to login screen.
			// A session has auto-expired if the user did not press the 'LOGOUT' button
			// If a user pressed the the 'LOGOUT' button, then it is redirected to a URI including a query var named 'loggedout'

			$isTimeout = is_null(filter_input(INPUT_GET, 'loggedout', FILTER_SANITIZE_STRING));             // FIXME - FILTER_SANITIZE_STRING is deprecated ... replace with Joomla InputFilter->clean()
			$isSignOut =         filter_input(INPUT_GET, 'loggedout', FILTER_SANITIZE_STRING) == 'true';    // FIXME - FILTER_SANITIZE_STRING is deprecated ... replace with Joomla InputFilter->clean()

			// Render session timeout message.
			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_SESSION_EXPIRED_TEXT', $lang)
			]);

			if ($isTimeout) :
				header('Location: index.php?hl=' . $lang . '&view=user&task=logout&session=expired&return=' . base64_encode($input->server->getUrl('REQUEST_URI')));	// REQUEST_URI is the link a user clicked before it is redirected to the login screen
				exit;
			elseif ($isSignOut) :
				header('Location: ' . preg_replace('/([&?])loggedout=true/i', '', $input->server->getUrl('PHP_SELF')));
				exit;
			endif;
		endif;
	endif;
endif;
?>
<?php // Init vars
$appAuthor   = $config->get('app_author');
$appDomain   = $config->get('app_domain');
$appName     = $config->get('app_name');
$appTitle    = $config->get('app_title');
$appUri      = $config->get('app_uri');
$appVersion  = $config->get('app_version', (new Registry)->loadFile(FTKPATH_BASE . DIRECTORY_SEPARATOR . 'package.json')->get('version'));
$appDefaults = $config->get('defaults');

$viewName    = $input->post->getCmd('view',   $input->get->getCmd('view',   'system'));
$layoutName  = $input->post->getCmd('layout', $input->get->getCmd('layout', ($viewName == 'system' ? 'departure' : 'default')));
$taskName    = $input->post->getCmd('task',   $input->get->getCmd('task',   'display'));

$isPrint     = $taskName === 'print' || current(explode('.', $taskName)) === 'print';

$template    = new Template(['language' => $lang]);
?>
<?php // Load more dependencies
$menu   = (!is_null($viewName)) ? new Menu(['language' => $lang]) : null;
//$active = (!is_null($menu))     ? $menu->setActive($viewName) : null;
//$active = ( is_null($active))   ? $menu->getActive() : null;

$view   = (!is_null($viewName)) ? View::getInstance($viewName, ['layout' => $layoutName, 'language' => $lang]) : null;

//$crumb   = (!is_null($view))     ? new Breadcrumb(['language' => $lang ]) : null;

//$model   = $view->get('model');
//$user    = Entity::getInstance('user');
//$access  = Access::getInstance('user');
//$service = Service::getInstance('api');
?>
<?php // Store logged-in user as view property
$user   = App::getAppUser();
$userID = (is_a($user, 'Nematrack\Entity\User') ? $user->get('userID') : null);




/*
// @debug - find differences in registered app languages that may result in incomplete translations
if ($user->get('userID') == 1 && $user->isProgrammer()) :
	echo '<pre>de vs. en: ' . print_r(Text::getDiff('de','en'), true) . '</pre>';
	echo '<pre>de vs. fr: ' . print_r(Text::getDiff('de','fr'), true) . '</pre>';
	echo '<pre>de vs. hu: ' . print_r(Text::getDiff('de','hu'), true) . '</pre>';
	echo '<pre>de vs. uk: ' . print_r(Text::getDiff('de','uk'), true) . '</pre>';
	echo '<pre>'            . print_r(str_repeat('.', 150), true) . '</pre>';
	echo '<pre>en vs. fr: ' . print_r(Text::getDiff('en','fr'), true) . '</pre>';
	echo '<pre>en vs. hu: ' . print_r(Text::getDiff('en','hu'), true) . '</pre>';
	echo '<pre>en vs. uk: ' . print_r(Text::getDiff('en','uk'), true) . '</pre>';
	echo '<pre>'            . print_r(str_repeat('.', 150), true) . '</pre>';
	echo '<pre>fr vs. hu: ' . print_r(Text::getDiff('fr','hu'), true) . '</pre>';
	echo '<pre>fr vs. uk: ' . print_r(Text::getDiff('fr','uk'), true) . '</pre>';
	echo '<pre>'            . print_r(str_repeat('.', 150), true) . '</pre>';
	echo '<pre>hu vs. uk: ' . print_r(Text::getDiff('hu','uk'), true) . '</pre>';
	die;
endif;
*/

/*// @test - build SQL statement for C+P usage
if ($user->get('userID') == 1 && $user->isProgrammer()) :
	$tmp  = [];
	$sql  = 'INSERT IGNORE INTO `errors` (`errID`, `lngID`, `name`) VALUES ';

		for ($i = 1; $i <= 348; $i += 1)
		{
			$tmp[] = "($i, 3, 's/o')";
		}

	$sql .= implode(',', $tmp);
//	$sql .= ';';

	echo '<pre>' . print_r($sql, true) . '</pre>';
	die;
endif;*/




// Get user profile and check if its selected app language differs from the currently detected language. If so, switch app language to user preference.
if (is_a($user, 'Nematrack\Entity\User')) :
	$userProfile = new Registry(UserHelper::getProfile($user));
	$userLang    = $userProfile->get('user.language');
endif;

// Store reference to user
if (is_a($view, 'Nematrack\View')) :
	$view->__set('user', $user);
endif;
?>
<?php // Toggle webservice maintenance mode
$isMaint = 0;	// ENABLE / DISABLE   -   0 will disable, 1 will enable
// Grant access to admin or developer only
$isMaint = $isMaint && ($userID != '1');	// If visiting user is not site admin, remain in maintenance mode
?>
<?php // Prepare login view
if ( (!is_a($user, 'Nematrack\Entity\User') || is_null($userID) ) ) :
	$view = View::getInstance('user', ['layout' => 'default', 'language' => $lang]);
endif;

// Prepare content output.
if (is_a($view, 'Nematrack\View')) :
    $view->prepare();
endif;
?>
<?php // Fetch available processes (incl. tech. params) for the JS-App object.
$processes = [];

if ((int) $userID > 0) :
	$processes = Model::getInstance('processes', ['language' => $lang])->getList([
        'catalog' => false,
        'params'  => true
    ]);
endif;

$processes = $processes ?? [];

// $processes may be a boolean (false) which is returned from $model in case loading the data caused an error.
// Thus, we must ensure that further processing will work while an error is presented to the user.
//$processes = (is_array($processes) ? $processes : []);

array_walk($processes, function($process) use(&$lang, &$processes, &$template)
{
	// Load data into Registry for less error prone access.
	$process = new Registry($process);

	$processes[$process->get('procID')] = htmlentities( $process->get('name', Text::translate('COM_FTK_NA_TEXT', $lang)) );
});
?>
<?php $compress = 0; // Serving uncompressed HTML loads faster - tested in Google Network tab ?>
<?php if ($compress) : ob_start(); endif; ?>
<!Doctype html>
<?php /* * *   I M P O R T A N T :   The browser detection feature requires the property 'browscap' to be defined in php.ini   * * */ ?>
<html lang="<?php echo $lang ; ?>"
	  class="<?php echo ($input->get->getInt('at') == '1' ? 'isAutotrack' : ''); ?>"
	  itemscope="itemscope"
	  itemtype="https://schema.org/WebPage"
	  data-browser-platform="<?php echo mb_strtolower( $browser->get('platform') ); ?>"
	  data-browser-name="<?php echo mb_strtolower( $browser->get('browser') ); ?>"
	  data-browser-version="<?php echo mb_strtolower( $browser->get('version') ); ?>"
	  data-device="<?php echo mb_strtolower( $browser->get('device_type') ); ?>"
	  data-device-is-mobile="<?php echo ($browser->get('ismobiledevice') ? 'true' : 'false'); ?>"
	  data-device-is-tablet="<?php echo ($browser->get('istablet') ? 'true' : 'false'); ?>"
>

<head>
    <meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="application-name" content="<?php echo $appName; ?>">
	<meta name="author" content="<?php echo $appAuthor; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo $appAuthor; ?>">
    <meta name="mobile-web-app-capable" content="yes">
	<meta name="robots" content="noindex, nofollow, noarchive, noimageindex, noodp, nosnippet, noydir">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
	<meta itemprop="copyrightHolder" content="<?php echo $appAuthor; ?>">
	<meta itemprop="copyrightYear" content="<?php echo date_create()->format('Y'); ?>">
	<meta itemprop="image" content="<?php echo sprintf('https://%s/assets/img/global/logos/froetek-logo.png', $appDomain); ?>">
	<meta itemprop="isFamilyFriendly" content="true">
	<meta name="description" content="">
	<meta name="keywords" content="">
	<meta property="og:url" content="<?php echo sprintf('https://%s', $appDomain); // Use the canonical URL. It helps consolidate all connected data. ?>">
	<meta property="og:locale" content="<?php echo ($lang == 'en') ? '' : sprintf('%s_%s', $lang, mb_strtoupper($lang)) ; ?>">
	<meta property="og:type" content="website">
	<meta property="og:site_name" content="<?php echo $appName; ?>">
	<meta property="og:title" content="<?php echo sprintf('%s &ndash; %s', $appAuthor, $appTitle); ?>">
	<meta property="og:image" content="<?php echo sprintf('https://%s/assets/img/home/banner.jpg', $appDomain); /* The most essential tag:
    * renders a page preview,
    * Use custom images for “shareable” pages (e.g., homepage, articles, etc.) !
    * Use your logo or any other branded image for the rest of your pages !
    * Use images with a 1.91:1 ratio and minimum recommended dimensions of 1200x630 for optimal clarity across all devices !
    *
    * For further read see: https://ahrefs.com/blog/open-graph-meta-tags/
    */ ?>">
	<meta property="og:image:width" content="800<?php // preview width ?>" />
	<meta property="og:image:height" content="600<?php // preview height ?>" />
	<meta property="og:description" content="">
	<link rel="apple-touch-icon-precomposed" href="<?php echo sprintf('https://%s/assets/img/global/logos/froetek-logo.png', $appDomain); ?>">
    <link rel="canonical" href="<?php echo sprintf('https://%s', $appDomain); ?>">
    <link rel="shortcut icon" href="<?php echo sprintf('https://%s/favicon.ico', $appDomain); ?>">
    <title itemprop="name"><?php echo sprintf('%s &ndash; %s', $appAuthor, $appTitle); ?></title>

	<?php // Auto-refresher: automatic page reload can be toggled, which sets a URL var 'ar' ?>
	<?php if ($arTime = $input->getInt('ar') ? FTKRULE_INTERVAL_AUTOREFRESH_PROJECT_MATRIX : '') : ?>
	<meta http-equiv="refresh" content="<?php echo $arTime; ?>; URL=<?php echo $input->server->getUrl('REQUEST_URI'); ?>">
	<?php endif; ?>

	<?php // Create JS-application object
	$appDefaults    = json_encode($appDefaults);
	$jsonLanguage   = json_encode(Text::getLanguage($lang));
	$jsonProcesses  = json_encode($processes);
	$regexPassword  = FTKREGEX_PASSWORD;		// Combination of regular expression pattern + form field rules
	$uplMaxFileSize = FTKRULE_UPLOAD_MAX_SIZE;	// 5 MB in Bytes

$scripts = <<<JS
	window.FTKAPP = window.FTKAPP || {
		name       : "$appName",
		version    : "$appVersion",
		config     : {
            defaults : $appDefaults
		},
		constants  : {
			maxUploadFileSize : $uplMaxFileSize,
			regexPatterns : {
				FTKREGEX_DRAWING_NUMBER :      "^([\w\d]{3}\.)([\w\d]{3}\.)([\w\d]{2}\.)([\w\d]{5}\.)([\w\d]{3})\$",
				FTKREGEX_DRAWING_INDEX :       "^[0-9A-Z]{1}\$",
				FTKREGEX_DRAWING_FILE :        "^([\w\d]{3}\.)([\w\d]{3}\.)([\w\d]{2}\.)([\w\d]{5}\.)([\w\d]{3}\.)([\w\d]{1})(\.(pdf|PDF))\$",
				FTKREGEX_ERROR_NUMBER :        "^[0-9A-Z]{4,10}\$",
				FTKREGEX_ERROR_WINCARAT_CODE : "^[0-9A-Za-z]{4,10}\$",
				FTKREGEX_LOT_NUMBER :          "^[A-Za-z0-9]{50}\$",
				FTKREGEX_LOT_ITEM_NUMBER :     "^([A-Z0-9]{3}\-){2}[A-Z0-9]{3}@([\w\d]{3}\.)([\w\d]{3}\.)([\w\d]{2}\.)([\w\d]{5}\.)([\w\d]{3})\$",
				FTKREGEX_PASSWORD :            "$regexPassword",
				FTKREGEX_PROJECT_NUMBER :      "^[0-9A-Z]{3}\$",
				FTKREGEX_TRACKINGCODE :        "^([A-Z0-9]{3}\-){2}[A-Z0-9]{3}\$",
				FTKREGEX_TRACKINGCODE_INLINE : "\b([a-zA-Z0-9]{3}\-[a-zA-Z0-9]{3}\-[a-zA-Z0-9]{3})\b"
			}
		},
		functions  : {},
		translator : {
			language : "$lang",
			     map : $jsonLanguage,
			 sprintf : function(str, vars) {
				 return str.replace("%s", ("" + vars).trim() || "%s");
			 }
		},
		processes  : $jsonProcesses
	};
JS;
?>
<?php if (!$isPrint) : ?><script><?php echo class_exists('MatthiasMullie\Minify\JS') ? (new JS($scripts))->minify() : $scripts; ?></script><?php endif; ?>

	<?php // Style references  ?>

<?php if (!$isPrint) : ?>
	<?php // Bootstrap 4 from CDN  ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.3/css/bootstrap.min.css"
	      integrity="sha512-oc9+XSs1H243/FRN9Rw62Fn8EtxjEYWHXRvjS43YtueEewbS6ObfXcJNyohjHqVKFPoXXUxwc+q1K7Dee6vv9g=="
	      crossorigin="anonymous" />

	<?php // Bootstrap Colorpicker  ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/css/bootstrap-colorpicker.min.css"
	      integrity="sha512-wuFRnk4KiQftPmBWRd5TmmgnuMEMVSySF4EsQJ50FemRIHIF5JkwD57UdcWqtGwamThUWHgXf8tSiiJitWnD0w=="
	      crossorigin="anonymous" />

	<?php // Bootstrap Datepicker from CDN  ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker3.min.css"
	      integrity="sha256-FAOaXTpl90/K8cXmSdsskbQN3nKYulhCpPbcFzGTWKI="
	      crossorigin="anonymous"
	      referrerpolicy="no-referrer" />

	<?php // Bootstrap Timepicker from CDN  ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.min.css"
	      integrity="sha512-/Ae8qSd9X8ajHk6Zty0m8yfnKJPlelk42HTJjOHDWs1Tjr41RfsSkceZ/8yyJGLkxALGMIYd5L2oGemy/x1PLg=="
	      crossorigin="anonymous"
	      referrerpolicy="no-referrer" />

	<?php if (1) : // Bootstrap Table sortable plugin  ?>
	<link rel="stylesheet" href="<?php echo UriHelper::osSafe('/assets/css/vendor/drvic10k/bootstrap-sortable/2.0.0/css/bootstrap-sortable.css'); ?>" />
	<?php endif; ?>

	<?php // Chosen from CDN  ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css"
	      integrity="sha512-yVvxUQV0QESBt1SyZbNJMAwyKvFTLMyXSyBHDO4BG5t7k/Lw34tyqlSDlKIrIENIzCl+RVUNjmCPG+V/GMesRw=="
	      crossorigin="anonymous"
	      referrerpolicy="no-referrer" />

	<?php // Fontawesome 5 from CDN  ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
	      integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
	      crossorigin="anonymous"
	      referrerpolicy="no-referrer" />

	<?php // DropzoneJS from CDN  ?>
	<?php if (FALSE) : ?>
	<!--link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.2/min/dropzone.min.css" integrity="sha512-bbUR1MeyQAnEuvdmss7V2LclMzO+R9BzRntEE57WIKInFVQjvX7l7QZSxjNDt8bg41Ww05oHSh0ycKFijqD7dA==" crossorigin="anonymous" /-->
	<?php endif; ?>

    <!--[if lt IE 9]>
    <script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

	<?php // App Styles  ?>
	<link rel="stylesheet" href="<?php echo UriHelper::osSafe('/assets/css/style.css?v=' . $input->server->get('REQUEST_TIME')); ?>">

	<?php // App Colours
	$blue     = '#b3cae9';	// original
	$darkblue = '#a4bbda';
	?>

	<?php // CSS
$styles = <<<CSS
	/* SCREEN */
	/*select:not(.form-control.selectError) {
		text-align-last: center;
		text-align: center;
		-ms-text-align-last: center;
		-moz-text-align-last: center;
	}*/

	select option:hover,
	select option:focus {
		background: rgba(0,0,0,.075);
		background-color: rgba(0,0,0,.075)!important; /* for IE */
	}
	select option:active,
	select option:checked {
		background: linear-gradient(#fff4d3, #fff4d3);
		background-color: #fff4d3!important; /* for IE */
	}

	.btn-ftk-blue {
	background-color: $blue!important;
		border-color: $blue!important;
		color: #30588b!important;
	}
	.btn-ftk-blue:hover {
		background-color: $darkblue!important;
		border-color: $darkblue!important;
		color: #21497c!important;
	}

	.btn-ftk-shadow {
		box-shadow: 0 2px 5px 1px rgba(128,151,182,0.3)
	}

	.chocolat-image {
		outline: 0 !important;
	}
	/*a.chocolat-image:hover {
		cursor: zoom-in;
	}*/

	.form-control-input-file {
		outline: 0!important;
	}

	.img-ftk-shadow {
		box-shadow: 0 2px 5px 1px rgba(128,151,182,0.3)
	}

	/* BOOTSTRAP override */
	/* Begin: custom classes - no TWBS-classes */
	.alert-inline {
		padding: .3rem .5rem;
	}

	.icon-pdf {
		color: rgba(227,30,36,1) !important;
	}
	.btn-primary   .icon-pdf,
	.btn-success   .icon-pdf,
	.btn-warning   .icon-pdf,
	.btn-secondary .icon-pdf {
		color: unset !important;
	}

	.modal-content {
		border: unset;
		box-shadow: 0 2px 10px 5px rgb(90 90 90  / 50%)
	}

	.text-primary:not(.form-control):not(.modal-body),
	.text-secondary:not(.form-control):not(.modal-body),
	.text-success:not(.form-control):not(.modal-body),
	.text-danger:not(.form-control):not(.modal-body),
	.text-warning:not(.form-control):not(.modal-body),
	.text-info:not(.form-control):not(.modal-body),
	.text-light:not(.form-control):not(.modal-body),
	.text-dark:not(.form-control):not(.modal-body) {
		padding: 0;
	}

	.text-orange {
		color: #fd7e14;
	}
	.text-red {
		color: #dc3545;
	}
	.text-blue {
		color: #28a745;
	}
	.text-blue {
		color: #007bff;
	}

	.font-bold,
	.text-bold {
		font-weight: 700;
	}
	/* End: custom classes - no TWBS-classes */

	/* The original colors are way too light and of too less contrast and require an override */
	.alert-light {
		color: #414142;
		background-color: #FBFBFB;
		border-color: #F5F5F5;
	}

	.btn.disabled, .btn:disabled {
		opacity: .40;
	}
	.btn-group-sm > .btn-save {
		padding: .25rem .57rem;
	}
	.btn-group-sm > .btn-trashbin,
	.btn-trashbin {
		padding: .25rem .55rem;
	}

	/* Colorize HTML form validation error */
	.form-control.text-warning {
		/*color: #495057 !important;*/	/* reset red color to document default text color */
		border-color: rgba(255, 193, 7, 0.5);
		outline-color: #ffc107;
		box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.5);
	}
	.form-control.text-danger {
		/*color: #495057 !important;*/	/* reset red color to document default text color */
		border-color: #f9c2cd;
		outline-color: #dc143c;
		box-shadow: 0 0 0 0.2rem rgba(220,20,60,.25);
	}
	.form-control.text-danger + .text-danger {
		display: block;
		margin-top: .25rem;
		margin-bottom: 0;
		font-size: 80%;
		font-weight: 400;
	}
	.input-group .form-control.text-danger + .text-danger {
		position: absolute;
		margin-bottom: 0;
		margin-top: 0;
		top: 23%;
		left: 0.75rem;
	}
	.input-group select.form-control.text-danger + .text-danger {
		display: none !important;
	}

	abbr.count {
		text-decoration: none
	}

	select.form-control:not(.custom-select) {
		padding-left: .5rem;
	}

	/* jQuery UI override */
	.ui-menu-item .ui-state-active {
		background: gainsboro
	}
	.ui-helper-hidden-accessible {
		display: none!important;
	}

	/* Chosen override (styling adapted to fit Bootstrap's .form-control */
	.custom-select.filterable + .chosen-container-single {
		background-color: #fff;
		background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='4' height='5' viewBox='0 0 4 5'%3e%3cpath fill='%23343a40' d='M2 0L0 2h4zm0 5L0 3h4z'/%3e%3c/svg%3e");
		background-repeat: no-repeat;
		background-position: right .75rem center;
		background-size: 8px 10px;
	}
	.custom-select.filterable + .chosen-container-single > .chosen-single {
	    height: calc(1.5em + .75rem + 2px);
	    padding: .375rem 1.75rem .375rem .75rem;
	    background: inherit;
	    box-shadow: none;
		border: 1px solid #ced4da;
	    border-radius: .25rem;
	    line-height: inherit;
	    font-size: 1rem;
	    font-weight: 400;
	    color: #495057;
	    -webkit-appearance: none;
	    -moz-appearance: none;
	    appearance: none;
	}
	.custom-select.filterable + .chosen-container-single > .chosen-single > span + div {
		display: none;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-single {
		display: none;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop {
		border: 1px solid #ced4da;
		border-radius: .25rem;
		top: calc(-0.5 * (1.5em + .75rem + 10px));
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-search > input[type] {
		margin: 4px 0 0;
	    padding: 0 1rem 0 .75rem;
		width: 100%;
	    height: calc(1.5em + .75rem + 2px);
	    font-family: inherit;
	    font-size: 1rem;
	    font-weight: 400;
	    line-height: 1.5;
	    color: #495057;
	    border: 1px solid #ced4da;
		background: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3e%3cpath fill='%23B5BBC1' fill-rule='evenodd' d='M11.5 7a4.499 4.499 0 11-8.998 0A4.499 4.499 0 0111.5 7zm-.82 4.74a6 6 0 111.06-1.06l3.04 3.04a.75.75 0 11-1.06 1.06l-3.04-3.04z'/%3e%3c/svg%3e") no-repeat center right .75rem !important;
	    background-size: unset;
		background-position: center right .75rem;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results {
		padding: 4px 1px 4px 5px;
		font-size: 1rem;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > [data-option-array-index="0"] {
		display: none;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > .active-result.highlighted {
		background: linear-gradient(#fff4d3,#fff4d3);
	    background-color: #fff4d3!important;
	    color: unset;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > .group-result {
	    padding-left: .75rem;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > .group-option {
	    padding-left: 1.5rem;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > .no-results {
	    padding: .5rem .75rem;
		background: linear-gradient(#f8d7da,#f8d7da);
	    background-color: #f8d7da;
	    font-weight: bold;
	    color: #721c24!important;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > .no-results > span {
		font-weight: normal;
	}
	.custom-select.filterable + .chosen-container-single.chosen-with-drop.chosen-container-active > .chosen-drop > .chosen-results > .no-results > span:before {
		content: ":";
		margin-right: .75rem;
	}

	@media (min-width: 992px) {
		.form-group {
			margin-bottom: 1.5rem;
		}
	}
CSS;
?>	<style><?php echo class_exists('MatthiasMullie\Minify\CSS') ? (new CSS($styles))->minify() : $styles; ?></style>

	<?php // JAVASCRIPT  ?>
	<?php /* Make system messages thrown by JApplication appear as popup after page has loaded.
	       * This script MUST remain in the head section to apply system-message-container manipulation before it will be rendered.
		   * Using k2s-template.js is not ideal as the code executes way toooooo late, because it waits for jQuery !!!
		   */
$scripts = <<<JS
	window.onload   = function() {
		"use strict;"

		var sysMsgContainer = document.getElementById("system-message-container"),
				sysMessages = [];

		if (typeof sysMsgContainer === "object" && sysMsgContainer !== null) {
			sysMsgContainer.classList.add("text-left");
			sysMsgContainer.classList.add("static");
			sysMsgContainer.classList.remove("sr-only");
			sysMsgContainer.style.left = ((window.innerWidth - sysMsgContainer.offsetWidth) / 2) + "px";

			/* Get all children (alerts) to be shown. This which gives us a NodeList
			 * which are no real Arrays and therefore do not inherit the forEach method.
			 */
			sysMessages = sysMsgContainer.childNodes;

			if (sysMessages.length > 0) {
				// Convert the NodeList into a proper Array to enable usage of forEach method.
				sysMessages = Array.prototype.slice.call(sysMessages);

				// NodeList iteration solution kindly provided by:   https://stackoverflow.com/a/24775765
				[].forEach.call(sysMessages, function(element) {
					if (element.nodeType == "1" && element.nodeName.toLowerCase() === "div" && element.children instanceof HTMLCollection) {
						[].forEach.call(element.children, function(alert) {
							if (alert.nodeType == "1" && alert.nodeName.toLowerCase() === "div" && (typeof alert.classList !== "undefined" && alert.classList.contains("alert"))) {
								alert.classList.add("bg-white");
							}
						});
					}
				});
			}
		}
	};
	window.onresize = function() {
		"use strict;"

		var sysMsgContainer = document.getElementById("system-message-container");

		if (typeof sysMsgContainer === "object" && sysMsgContainer !== null) {
			sysMsgContainer.style.left = ((window.innerWidth - sysMsgContainer.offsetWidth) / 2) + "px";
		}
	};
JS;
?>	<script><?php echo class_exists('MatthiasMullie\Minify\JS') ? (new JS($scripts))->minify() : $scripts; ?></script>
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation"></script>

</head>

<body>
	<div id="wrapper">
		<?php // NAVIGATION ,   CONTENT  ?>
		<div class="container pt-md-1">
		<?php // USER LOGIN / LOGOUT  ?>
		<?php if ( (!is_a($user, 'Nematrack\Entity\User') || is_null($userID) ) ) : ?>
			<?php // Render system messages. ?>
			<?php Messager::render(Messager::getMessageQueue(), ['language' => $lang ]); ?>

			<?php // Render content. ?>
			<?php if (!is_null($view)) : ?>
				<?php $view->render(); ?>
			<?php endif; ?>
		<?php else : ?>
			<?php // LOGO ,   TOP-MENU ,   SEARCH  ?>
			<?php if (!$isPrint) : ?>
			<?php // Gain site access only if site is not in maintenance mode  ?>
			<?php if (!$isMaint) : ?>
			<header class="position-relative">
				<?php // Language toggles  ?>
				<small class="d-block py-sm-3 text-right">
					<?php echo LayoutHelper::render('navigation.languages', ['root' => 'index'], ['language' => $lang ]); ?>
				</small>
				<?php // Main navigation with. user menu (incl. logout link)  ?>
				<?php echo LayoutHelper::render('navigation.main', (object) ['menu' => $menu, 'view' => $view], ['language' => $lang ]); ?>
			</header>
			<?php endif; ?>
			<?php endif; ?>

			<?php // DEBUG user roles
//			if (FTK_DEBUG) :
			if (isset($_GET['auth']) && $_GET['auth'] === 'dev-op') : ?>
			<code class="d-block p-3 mt-4">
				<style>code {background-color:#ddd !important; border:1px solid #ccc !important } pre:last-of-type { margin-bottom:0 }</style>
				<?php if (is_object($user)) :
//					echo '<pre>User->groups: '             . print_r(json_encode($user->get('groups')), true) . '</pre>';
//					echo '<pre>User->roles: '              . print_r(json_encode(array_map(function (&$role) { return preg_replace('/ROLE_/', '', $role); }, $user->toArray())),  true) . '</pre>';
//					echo '<pre>User->flag: '               . print_r(json_encode($user->getFlags()), true) . '</pre>';
//					echo '<pre>GUEST->flag: '              . print_r(User::ROLE_GUEST, true) . '</pre>';
//					echo '<pre>User->organisation: '       . print_r(sprintf('%d (%s)', $user->get('orgID'), Model::getInstance('organisation', ['language' => $lang])->getItem($user->get('orgID'))->get('name')), true) . '</pre>';
//					echo '<pre>User->projects: '           . print_r(implode(',', array_column(Model::getInstance('organisation', ['language' => $lang])->getOrganisationProjectsNEW(['orgID' => $user->get('orgID')]), 'number')), true) . '</pre>';
//					echo '<pre>User->isRegistered: '       . print_r($user->isRegistered()       ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isActive: '           . print_r($user->isActive()           ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isGuest: '            . print_r($user->isGuest()            ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isWorker: '           . print_r($user->isWorker()           ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isCustomer: '         . print_r($user->isCustomer()         ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isSupplier: '         . print_r($user->isSupplier()         ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isQualityAssurance: ' . print_r($user->isQualityAssurance() ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isManager: '          . print_r($user->isManager()          ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isDrawer: '           . print_r($user->isDrawer()           ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isQualityManager: '   . print_r($user->isQualityManager()   ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isAdmin: '            . print_r($user->isAdministrator()    ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isProgrammer: '       . print_r($user->isProgrammer()       ? 'YES' : 'NO', true) . '</pre>';
//					echo '<pre>User->isSuperuser: '        . print_r($user->isSuperuser()        ? 'YES' : 'NO', true) . '</pre>';
				endif; ?>
			</code>
			<?php endif; ?>

			<?php // CONTENT  ?>
			<main class="pt-<?php echo (($viewName == 'system' && $layoutName == 'departure') || ($viewName == 'help' && $layoutName == 'default')) ? '4' : '5'; ?>">
			<?php // Gain site access only if site is not in maintenance mode  ?>
			<?php if (!$isMaint) : ?>
				<?php // Render system messages. ?>
				<?php Messager::render(Messager::getMessageQueue(), ['language' => $lang ]); ?>

				<?php // Render content. ?>
				<?php if (!is_null($view)) : ?>
					<?php $view->render(); ?>
				<?php endif; ?>
			<?php else : ?>
				<?php echo LayoutHelper::render('system.maintenance', new Registry(), ['language' => $lang ]); ?>
			<?php endif; ?>
			</main>
		<?php endif; ?>

			<?php // COPYRIGHT ,   APP-VERSION  ?>
			<?php if (!$isPrint && $userID) : ?>
			<?php if (!$isMaint) : ?>
			<footer class="text-center text-style-inset mb-md-2 mb-lg-3" role="contentinfo">
				<p class="text-muted small alert alert-sm alert-secondary mb-1"><?php echo
					sprintf(Text::translate('COM_FTK_COPYRIGHT_DISCLAIMER_TEXT', $lang ), sprintf('2020 &ndash; %d', date('Y')));
				?></p>
				<?php if ( is_a($user, 'Nematrack\Entity\User') && !is_null($userID) ) : ?>
				<span class="text-muted small">
					<small>
						<?php // APP-Name + APP-Version  ?>
						<?php echo LayoutHelper::render('system.version', [
							'appName'    => $appName,
							'appVersion' => $appVersion
						], ['language'   => $lang]); ?>
						<?php // LINKs to Bugtracker and Change Log  ?>
						<?php if ($user->getFlags() >= User::ROLE_PROGRAMMER && !in_array($user->getFlags(), [
							User::ROLE_GUEST,
							User::ROLE_CUSTOMER,
							User::ROLE_SUPPLIER
						])) : ?>&nbsp;&nbsp;|&nbsp;&nbsp;<?php if (0) : ?>
							<a href="index.php?hl=<?php echo $lang; ?>&view=quality"
							   title="<?php echo Text::translate('', $lang); ?>"
							   aria-label="<?php echo Text::translate('', $lang); ?>"
							   rel="noopener noreferrer"
							><?php echo Text::translate('QM-Tool', $lang);
							?></a><?php endif; ?>
							<?php if ($user->isProgrammer()) : // Show this link to programmers only ?>
							<a href="//bugtracker.nematrack.com"
							   title="<?php echo Text::translate('COM_FTK_MENU_ITEM_BUGTRACKER_DESC', $lang); ?>"
							   aria-label="<?php echo Text::translate('COM_FTK_MENU_ITEM_BUGTRACKER_DESC', $lang); ?>"
							   target="_blank"
							   rel="noopener noreferrer"
							><?php echo Text::translate('COM_FTK_MENU_ITEM_BUGTRACKER_LABEL', $lang);
							?></a><?php endif; ?><?php if (0) : ?>&nbsp;&nbsp;|&nbsp;&nbsp;
							<a href="index.php?hl=<?php echo $lang; ?>&view=development&layout=changelog"
							   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CHANGELOG_TEXT', $lang); ?>"
							   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_CHANGELOG_TEXT', $lang); ?>"
							   rel="noopener noreferrer"
							><?php echo Text::translate('COM_FTK_LABEL_CHANGELOG_TEXT', $lang);
							?></a><?php endif; ?>
						<?php endif; ?>
						<?php if (0) : ?>
						&nbsp;&nbsp;|&nbsp;&nbsp;
						<span class="text-muted d-inline-block" id="idleTimer"></span>
						<?php endif; ?>
					</small>
				</span>

				<?php endif; ?>
                <p style="font-size: 12px;" class="pageloadtrack">Load Time: <?php echo(number_format(microtime(true) - $start_time, 2)); ?> seconds.</p>
			</footer>
			<?php endif; ?>
			<?php endif; ?>

			<?php // SCROLL TO TOP  ?>
			<?php if (!$isPrint) : ?>
				<?php echo LayoutHelper::render('widget.controls.button.scrollTop.rounded', [], []); ?>
			<?php endif; ?>
		</div>

		<?php // MODAL dialog template  ?>
		<?php //TODO - convert to includable file ?>
		<?php if (!$isPrint) : ?>
		<div class="modal fade" id="mainModal" tabindex="-1" role="dialog" aria-labelledby="mainModalTitle" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
				<div class="modal-content" id="mainModalContent">
					<div class="modal-header" id="mainModalHeader">
						<h6 class="modal-title" id="mainModalTitle"><?php echo Text::translate('COM_FTK_STATUS_LOADING_TEXT', $lang ); ?></h6>
						<button type="button"
								class="close"
								data-dismiss="modal"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CLOSE_TEXT', $lang ); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CLOSE_TEXT', $lang ); ?>"
						><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body"   id="mainModalBody">&hellip;</div>
					<div class="modal-footer" id="mainModalFooter">
						<button type="button"
								class="btn btn-sm btn-outline-secondary btn-close"
								data-dismiss="modal"
								title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CLOSE_TEXT', $lang ); ?>"
								aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_CLOSE_TEXT', $lang ); ?>"
						><?php echo Text::translate('COM_FTK_BUTTON_TEXT_CLOSE_TEXT', $lang ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php // Script references  ?>

<?php if (!$isPrint) : ?>
	<?php // Required for workaround native Window.atob() function not properly decoding Base64-encoded HTML markup including Umlauts and special chars. ?>
	<script src="<?php echo UriHelper::osSafe('/assets/js/vendor/dankogai/js-base64/base64.js'); ?>"></script>

	<?php // jQuery from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"
	        integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-migrate/3.3.2/jquery-migrate.min.js"
	        integrity="sha512-3fMsI1vtU2e/tVxZORSEeuMhXnT9By80xlmXlsOku7hNwZSHJjwcOBpmy+uu+fyWwGCLkMvdVbHkeoXdAzBv+w=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>

	<?php // jQuery Validation plugin from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js"
			integrity="sha512-0QDLUJ0ILnknsQdYYjG7v2j8wERkKufvjBNmng/EdR/s/SE7X8cQ9y0+wMzuQT0lfXQ/NhG+zhmHNOWTUS3kMA=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/additional-methods.min.js"
			integrity="sha512-/KqL6qPlIMqbgKgrc650iXf2m5Z1vHDbWcuecnn1SGC7mmyp3v1nYNj0BSn8bVFQ+FOqJ1b9qrXTIPSj2+yWYw=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"></script>
	<?php if ($lang !== 'en') : ?>
	<?php switch ($lang) : ?><?php
		case 'de' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/localization/messages_de.min.js" integrity="sha512-QObKKlz/BRhvq9G7EqKiBxM7D082SNWY6MJ66+/SlDOFtLFBbwlDjiloYxNuG7B9CjIRR5hp+G2O74nYy2jhDw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'fr' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/localization/messages_fr.min.js" integrity="sha512-JOar5IcaT3It8b3qU86LziUcb+fHpojnkR/2CZporxs284sHFCLSl1odqXjpEOZ4aw5FRRZNG2ttogVbLEh5Qw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script></script><?php break; ?><?php
		case 'hu' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/localization/messages_hu.min.js" integrity="sha512-sG3798b2fMPDMBbMs8S/HN1dOCVU8EY3Y+M/LFQT+K0wpJjDiEawiA8t6brU0IJ01jipHA3V9U2bHxjIVfdlaQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'ru' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/localization/messages_ru.min.js" integrity="sha512-c8kY1WYTIVERnc3HqfpwiZ1BaOliPgdOnEnm+bo42nXBPDBBTSz/BiyJ03nG6Qx3Y7B3ivTYGWwcfOW9hZ/nJA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'uk' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/localization/messages_uk.min.js" integrity="sha512-LVWRTl2WBFv79CYZeWeiMFWfp3T5CyYDc24TX8J2Qill8yOaZ223e8LQ9TNzRRIA0zM+fGfVHk5m+6Xm6bzFsw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
	?><?php endswitch; ?>
	<?php endif; ?>

	<?php // Bootstrap from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.3/js/bootstrap.bundle.min.js"
	        integrity="sha512-iceXjjbmB2rwoX93Ka6HAHP+B76IY1z0o3h+N1PeDtRSsyeetU3/0QKJqGyPJcX63zysNehggFwMC/bi7dvMig=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>

	<?php // Bootstrap Colorpicker plugin from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/3.2.0/js/bootstrap-colorpicker.min.js"
	        integrity="sha512-INXxqXxcP6zawSei7i47Xmu+6ZIBRbsYN1LHAy5H1gzl1XIfTbI/OLjUcvBnDD8F3ZSVB6mf8asEPTMxz4VNjw=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>

	<?php // Bootstrap Datepicker plugin from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"
			integrity="sha512-T/tUfKSV1bihCnd+MxKD0Hm1uBBroVYBOYSk1knyvQ9VyZJpc/ALb4P0r6ubwVPSGB2GvjeoMAJJImBG12TiaQ=="
			crossorigin="anonymous"
			referrerpolicy="no-referrer"></script>
	<?php switch ($lang) : ?><?php
		case 'de' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.de.min.js"    integrity="sha512-3V4cUR2MLZNeqi+4bPuXnotN7VESQC2ynlNH/fUljXZiQk1BGowTqO5O2gElABNMIXzzpYg5d8DxNoXKlM210w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'en' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.en-GB.min.js" integrity="sha512-r4PTBIGgQtR/xq0SN3wGLfb96k78dj41nrK346r2pKckVWc/M+6ScCPZ9xz0IcTF65lyydFLUbwIAkNLT4T1MA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'fr' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.fr.min.js"    integrity="sha512-fx3aztaUjb4NyoD+Tso5g7R1l29bU3jnnTfNRKBiY9fdQOHzVhKJ10wEAgQ1zM/WXCzB9bnVryHD1M40775Tsw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'hu' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.hu.min.js"    integrity="sha512-hUj94GVUcMtQpARqIUR1qfiM9hFGW/sOKx6pZVEyuqUSYbjSw/LjQbjuXpFVfKqy8ZeYbDxylIm6D/KIfcJbTQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'ru' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.ru.min.js"    integrity="sha512-tPXUMumrKam4J6sFLWF/06wvl+Qyn27gMfmynldU730ZwqYkhT2dFUmttn2PuVoVRgzvzDicZ/KgOhWD+KAYQQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
		case 'uk' : ?><script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.uk.min.js"    integrity="sha512-zj4XeRYWp+L81MSZ3vFuy6onVEgypIi1Ntv1YAA6ThjX4fRhEtW7x+ppVnbugFttWDFe/9qBVdeWRdv9betzqQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><?php break; ?><?php
	?><?php endswitch; ?>

	<?php // Bootstrap Timepicker plugin from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.min.js"
	        integrity="sha512-2xXe2z/uA+2SyT/sTSt9Uq4jDKsT0lV4evd3eoE/oxKih8DSAsOF6LUb+ncafMJPAimWAXdu9W+yMXGrCVOzQA=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>

	<?php // Bootstrap Maxlength plugin from CDN  ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-maxlength/1.10.0/bootstrap-maxlength.min.js"
	        integrity="sha512-04L+TAgzlDAaUpaEGriEBg/qEryUjw4GNL/FkxA3h621EFPycccO2Y8vNhvid9UhgGC/9+MHLAFwGythpvOAAQ=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>

	<?php // jQuery Chosen plugin from CDN  ?>
	<?php if (1) : ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js"
	        integrity="sha512-rMGGF4wg1R73ehtnxXBt5mbUfN9JUJwbk21KMlnLZDJh7BkPmeovBuddZCENJddHYYMkCh9hPFnPmS9sspki8g=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>
	<?php else : ?>
		<?php if (0 && $user->isProgrammer()) : ?>
	<script src="<?php echo UriHelper::osSafe('/assets/js/vendor/harvest/chosen/1.8.7/chosen.jquery.js?v=' . $input->server->get('REQUEST_TIME')); ?>"></script>
		<?php else : ?>
	<script src="<?php echo UriHelper::osSafe('/assets/js/vendor/harvest/chosen/1.8.7/chosen.jquery.min.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<?php // Bootstrap Table sortable plugin  ?>
	<script src="<?php echo UriHelper::osSafe('/assets/js/vendor/drvic10k/bootstrap-sortable/2.0.0/js/bootstrap-sortable.js'); ?>"></script>
	<?php if (0) : // optional 3rd party plugin to sort dates  ?>
	<!--script src="<?php echo UriHelper::osSafe('/assets/js/vendor/drvic10k/bootstrap-sortable/2.0.0/js/moment.min.js'); ?>"></--script-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.3/moment-with-locales.min.js"
	        integrity="sha512-vFABRuf5oGUaztndx4KoAEUVQnOvAIFs59y4tO0DILGWhQiFnFHiR+ZJfxLDyJlXgeut9Z07Svuvm+1Jv89w5g=="
	        crossorigin="anonymous"
	        referrerpolicy="no-referrer"></script>
	<?php endif; ?>

	<?php // DropzoneJS from CDN  ?>
	<?php if (0) : ?>
	<!--script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.2/min/dropzone.min.js" integrity="sha512-9WciDs0XP20sojTJ9E7mChDXy6pcO0qHpwbEJID1YVavz2H6QBz5eLoDD8lseZOb2yGT8xDNIV7HIe1ZbuiDWg==" crossorigin="anonymous"></script-->
	<!--script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.7.2/dropzone.js" integrity="sha512-0QMJSMYaer2wnpi+qbJOy4rOAlE6CbYImSlrgQuf2MBBMqTvK/k6ZJV126/EbdKzMAXaB6PHzdYxOX6Qey7WWw==" crossorigin="anonymous"></script-->
	<?php endif; ?>

	<?php // jQuery UI from CDN  ?>
	<?php if (0) : ?>
	<!--script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js" integrity="sha512-uto9mlQzrs59VwILcLiRYeLKPPbS/bT71da/OEBYEwcdNUk8jYIy+D176RYoop1Da+f9mvkYrmj5MCLZWEtQuA==" crossorigin="anonymous"></script-->
	<?php else : ?>
	<script src="<?php echo UriHelper::osSafe('/assets/js/vendor/jquery-ui/1.13.0/jquery-ui.min.js'); ?>"></script>
	<?php endif; ?>

	<?php // jQuery UI Touch-Punch plugin from CDN  ?>
	<?php if (0) : ?>
	<!--script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js" integrity="sha256-AAhU14J4Gv8bFupUUcHaPQfvrdNauRHMt+S4UVcaJb0=" crossorigin="anonymous"></script-->
	<?php endif; ?>

	<?php // Socket.io plugin from CDN  ?>
	<?php if (0 && is_a($user, 'Nematrack\Entity\User') && !is_null($userID)) : ?>
	<!--script src="https://cdnjs.cloudflare.com/ajax/libs/socket.io/2.3.0/socket.io.js" integrity="sha256-bQmrZe4yPnQrLTY+1gYylfNMBuGfnT/HKsCGX+9Xuqo=" crossorigin="anonymous"></script-->

	<?php // Socket.io based app push notifications  ?>
	<!--script src="<?php //echo UriHelper::osSafe('/assets/js/vendor/froetek/push-notifications/static/client.js'); ?>"></script-->
	<?php endif; ?>

	<?php // App js  ?>
	<?php if (0) : ?><script src="<?php echo UriHelper::osSafe('/assets/js/jquery.eks-plugins.js?v=' . $input->server->get('REQUEST_TIME')); ?>"></script><?php endif; ?>
	<?php if (1) : ?><script src="<?php echo UriHelper::osSafe('/assets/js/script.js?v=' . $input->server->get('REQUEST_TIME')); ?>"></script><?php endif; ?>

	<?php // JAVASCRIPT  ?>
	<?php // Create webStorage object(s) that keeps track of user interaction data
	$scripts = [];

	// Create localStorage object if not exists.
	$scripts[] = '(function () {';
	$scripts[] = 	'"use strict";';
	$scripts[] = 	' ';
	if ( is_a($user, 'Nematrack\Entity\User') && !is_null($userID) ) :
	// Init and display auto-logoff timer.
	//FIXME - make the related function polling the server session idle time rather than local calculation,
	//        because several open windows cause a session expired even though a user may be continuously active in one tab.
	//        That's frustrating
//	$scripts[] = 	'window.FTKAPP.functions.initIdleTimer('. ($lifetime / 60) . ');';
	$scripts[] = 	'';
	$scripts[] = 	'try {';
	// Dump user object information.
	$scripts[] = 		'window.FTKAPP.client    = window.FTKAPP.client || {};';
	$scripts[] = 		'window.FTKAPP.client.ID = "O' . $user->get('orgID') . 'U' . $userID . '";';
	// Add user agent information. This will be required, e.g., to limit access to camera control features as important functionality is supported by Chrome only.
	// see:  https://developer.mozilla.org/en-US/docs/Web/API/MediaStream_Image_Capture_API#browser_compatibility
	$scripts[] = 		'window.FTKAPP.client.browser = {vendor: "' . $browser->get('browser') . '", version: "' . $browser->get('version') . '"};';
	if ($input->get->getCmd('at') == '0') :	// will catch when a user disables AutoTrack via click on active AutoTrack-button
	$scripts[] = 		' ';
	//FIXME - clearing whole sessionStorage may have undesired side effect(s). There may be other data that must not be deleted!
	$scripts[] = 		'window.FTKAPP.functions.clearWebStorage( "session" );';
	endif;
	// Register user-specific sessionStorage namespace.
	$scripts[] = 	'} catch (ex) {';
	$scripts[] = 		'console.warn("Error:", ex);';
	$scripts[] = 	'}';
	else :
	$scripts[] = 	'try {';
	$scripts[] = 		'window.FTKAPP.functions.clearWebStorage( "local" );';
	$scripts[] = 		'window.FTKAPP.functions.clearWebStorage( "session" );';
	$scripts[] = 	'} catch (ex) {';
	$scripts[] = 		'console.warn("Error:", ex);';
	$scripts[] = 	'}';
	endif;
	$scripts[] = '})();';

	$scripts = trim(preg_replace('/\s{2,}/i', ' ', implode('', $scripts)));
	?>
	<?php if (strlen($scripts)) : ?>
	<script><?php echo class_exists('MatthiasMullie\Minify\JS') ? (new JS($scripts))->minify() : $scripts; ?></script>
	<?php endif; ?>

	<?php if (!is_null($js = $input->getBase64('js'))) : ?>
	<script><?php echo base64_decode($js); ?></script>
	<?php endif; ?>

	<?php // Matomo (Piwik) Analytics Code  // 2022-10-05 Disabled because it turned out not to make much sense ?>
	<?php //echo LayoutHelper::render('widget.matomo', [], []); ?>
<?php endif; ?>
</body>
</html>
<?php // Free memory.
unset($config);
unset($jsonLanguage);
unset($jsonProcesses);
unset($processes);
unset($scripts);
unset($styles);
unset($user);
unset($view);
?>
<?php // Compress HTML
if ($compress) :

$html = ob_get_contents();

if (0 && class_exists('voku\helper\HtmlMin')) :
	$html = (new HtmlMin())->minify($html);
elseif (class_exists('WyriHaximus\HtmlCompress\Factory')) :
	$parser = HtmlCompressor::construct();
	$html = $parser->compress($html);
endif;

ob_end_clean();

echo $html;

endif; ?>
