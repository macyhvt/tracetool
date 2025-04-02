<?php /* Block Robots from site crawling */
// see: https://developers.google.com/search/reference/robots_meta_tag?hl=de
// see: https://pagerangers.com/seo-handbuch/onpage/was-ist-das-robots-tag-und-wo-wird-es-gesetzt
// see: https://www.omt.de/suchmaschinenoptimierung/bye-bye-noindex-nofollow-und-crawl-delay-robots-txt-wird-revolutioniert
header('X-Robots-Tag: noindex, nofollow, noarchive, noimageindex, noodp, nosnippet, noydir');
// header('X-Robots-Tag: noindex, nofollow, noarchive, noimageindex, noodp, nosnippet, noydir, notranslate', true);
?>
<?php /* Init constants */
defined ('_FTK_APP_') OR define('_FTK_APP_', 1);
define  ('FTKPATH_BASE', __DIR__);
define  ('FTKURI_BASE',  preg_replace( '#//#', '/', substr( $_SERVER['PHP_SELF'], 0, strrpos( $_SERVER['PHP_SELF'], '/' ) + 1 ) ) );
?>
<?php /* Load dependencies via composer */

use Joomla\Registry\Registry;
use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Nematrack\App;
use Nematrack\Factory;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Template;
use Nematrack\Text;
use Nematrack\View;

//--> ENABLE IF composer.json resides under /lib/
if (!file_exists(__DIR__ . '/lib/vendor/autoload.php')) :
	throw new RuntimeException('Composer autoload script could not be found. Please make sure to run "composer install" on the commandline first.');
endif;

require_once __DIR__ . '/lib/vendor/autoload.php';
//--> END
?>
<?php /* Toggle error display */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Report all errors (as per default in php.ini).
ini_set('error_reporting', E_ALL);
//ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE);
?>
<?php /* Get application configuration */
$config      = Factory::getConfig();
?>
<?php /* Configure application language */
$input       = App::getInput();
$browser     = (function_exists('get_browser')) ? new Registry(get_browser($input->server->getString('HTTP_USER_AGENT', ''))) : null;
$lang        = null;

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

	/* // Fetched from useragent (browser)
	case (!empty($browserLang)) :
		$lang = $browserLang;
	break; */

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
<?php /* Provide API service */
?>
<?php /* Init vars */
$appAuthor  = $config->get('app_author');
$appName    = $config->get('app_name');
$appVersion = $config->get('app_version', (new Registry)->loadFile( FTKPATH_BASE . DIRECTORY_SEPARATOR . 'package.json')->get('version'));

$viewName   = $input->post->getCmd('view',   $input->get->getCmd('view',   'help'));
$layoutName = $input->post->getCmd('layout', $input->get->getCmd('layout', 'default'));
$taskName   = $input->post->getCmd('task',   $input->get->getCmd('task',   'display'));

$isPrint    = $taskName === 'print' || current(explode('.', $taskName)) === 'print';

$template   = new Template(['language' => $lang]);
?>
<?php /* Load more dependencies */
$view   = (!is_null($viewName)) ? View::getInstance($viewName, ['layout' => $layoutName, 'language' => $lang ]) : null;
?>
<?php /* Store logged in user as view property */
?>
<?php /* Toggle webservice maintenance mode */
$isMaint = 0;	// ENABLE / DISABLE   -   0 will disable, 1 will enable
?>
<?php /* Prepare login view */
//if ( (!is_a($user, 'Nematrack\Entity\User') || is_null($userID) ) ) :
//	$view = View::getInstance('user', ['layout' => 'default', 'language' => $lang]);
//endif;

// Prepare content output.
//if (is_a($view, 'Nematrack\View')) :
//	$view->prepare();
//endif;
?>
<?php /* Fetch available processes (incl. tech. params) for the JS-App object. */
?>
<?php $compress = 0; // Serving uncompressed HTML loads faster - tested in Google Network tab ?>
<?php if ($compress) : ob_start(); endif; ?>
<!Doctype html>
<?php /* * *   I M P O R T A N T :   The browser detection feature requires the property 'browscap' to be defined in php.ini   * * */ ?>
<html lang="<?php echo $lang ; ?>"
      class=""
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
	<meta charset='utf-8'>
	<meta name='application-name' content="<?php echo $appName; ?>">
	<meta name='author' content="<?php echo $appAuthor; ?>">
	<meta name='apple-mobile-web-app-capable' content='yes'>
	<meta name='apple-mobile-web-app-status-bar-style' content='default'>
	<meta name='apple-mobile-web-app-title' content="<?php echo $appAuthor; ?>">
	<meta name='mobile-web-app-capable' content='yes'>
	<meta name='robots' content='noindex, nofollow, noarchive, noimageindex, noodp, nosnippet, noydir'>
	<?php // <meta name="revisit-after" content="99999999999 days"> ?>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
	<meta itemprop="copyrightHolder" content="<?php echo $appAuthor; ?>">
	<meta itemprop="copyrightYear" content="<?php echo date_create()->format('Y'); ?>">
	<meta itemprop="image" content="https://nematrack.com/assets/img/global/logos/froetek-logo.png">
	<meta itemprop="isFamilyFriendly" content="true">
	<meta name="description" content="">
	<meta name="keywords" content="">
	<meta property="og:description" content="">
	<meta property="og:image" content="https://nematrack.com/assets/img/global/logos/froetek-logo.png">
	<meta property="og:site_name" content="<?php echo $appName; ?>">
	<meta property="og:title" content="<?php echo sprintf('%s &ndash; %s', $appAuthor, 'Tracking Tool'); ?>">
	<meta property="og:type" content="website">
	<meta property="og:url" content="https://nematrack.com/">
	<link rel="apple-touch-icon-precomposed" href="https://nematrack.com/assets/img/global/logos/froetek-logo.png">
	<link rel="canonical" href="https://nematrack.com/">
	<link rel="shortcut icon" href="https://nematrack.com/favicon.ico">
	<title itemprop="name"><?php echo sprintf('%s &ndash; %s', $appAuthor, 'Tracking Tool'); ?></title>

<?php // Auto-refresher: automatic page reload can be toggled, which sets a URL var 'ar' ?>
<?php if ($arTime = $input->getInt('ar') ? 60 : '') : ?>
	<meta http-equiv="refresh" content="<?php echo $arTime; ?>; URL=<?php echo $input->server->getUrl('REQUEST_URI'); ?>">
<?php endif; ?>

<?php /* Create JS-application object */
$jsonLanguage   = json_encode(Text::getLanguage($lang));
$uplMaxFileSize = FTKRULE_UPLOAD_MAX_SIZE;    // 5 MB in Bytes

$scripts = <<<JS
	window.FTKAPP = window.FTKAPP || {
		name       : "$appName",
		version    : "$appVersion",
		constants  : {
			maxUploadFileSize : $uplMaxFileSize
		},
		functions  : {},
		translator : {
			language : "$lang",
			     map : $jsonLanguage,
			 sprintf : function(str, vars) {
				 return str.replace("%s", vars.trim() || "%s");
			 }
		}
	};
JS;
?>
<?php if (!$isPrint) : ?><script><?php echo class_exists('MatthiasMullie\Minify\JS') ? (new JS($scripts))->minify() : $scripts; ?></script><?php endif; ?>

	<?php /* Style references */ ?>

<?php if (!$isPrint) : ?>
	<?php /* Bootstrap 4 from CDN */ ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.3/css/bootstrap.min.css" integrity="sha512-oc9+XSs1H243/FRN9Rw62Fn8EtxjEYWHXRvjS43YtueEewbS6ObfXcJNyohjHqVKFPoXXUxwc+q1K7Dee6vv9g==" crossorigin="anonymous">

	<?php /* Fontawesome 5 from CDN */ ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css" integrity="sha256-+N4/V/SbAFiW1MPBCXnfnP9QSN3+Keu+NlB+0ev/YKQ=" crossorigin="anonymous">

	<!--[if lt IE 9]>
	<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<?php /* App Styles */ ?>
	<link rel="stylesheet" href="<?php echo UriHelper::osSafe('/assets/css/style.css?v=' . $input->server->get('REQUEST_TIME')); ?>">

	<?php /* App Colours */
	$blue     = '#b3cae9';    // original
	$darkblue = '#a4bbda';
	?>

	<?php /* CSS */
$styles = <<<CSS
CSS;
?>	<style><?php echo class_exists('MatthiasMullie\Minify\CSS') ? (new CSS($styles))->minify() : $styles; ?></style>

	<?php /* JAVASCRIPT */ ?>
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
</head>

<body>
	<div id="wrapper">
		<?php /* NAVIGATION ,   CONTENT */ ?>
		<div class="container pt-md-1">
			<?php /* LOGO ,   TOP-MENU ,   SEARCH */ ?>
			<?php if (!$isPrint) : ?>
			<header class="position-relative">
			<?php /* Gain site access only if site is not in maintenance mode */ ?>
			<?php if (!$isMaint) : ?>
				<?php /* Language toggles */ ?>
				<small class="d-block py-sm-3 text-right">
					<?php echo LayoutHelper::render('navigation.languages', ['root' => 'service'], ['language' => $lang ]); ?>
				</small>
				<?php /* Main navigation with. user menu (incl. logout link) */ ?>
				<?php echo LayoutHelper::render('navigation.main', (object) ['view' => $view], ['language' => $lang ]); ?>
			<?php endif; ?>
			</header>
			<?php endif; ?>

			<?php /* CONTENT */ ?>
			<main class="pt-5">
			<?php /* Gain site access only if site is not in maintenance mode */ ?>
			<?php if (!$isMaint) : ?>
				<?php // Render system messages. ?>
				<?php Messager::render(Messager::getMessageQueue(), ['language' => $lang ]); ?>

				<?php // Render content. ?>
				<?php if (!is_null($view)) : ?>
					<?php // @debug
//					echo '<pre>view: ' . print_r($view, true) . '</pre>'; ?>
					<?php $view->render(); ?>
				<?php else : ?>
				<?php 	echo sprintf('View %s not found.', $viewName); ?>
				<?php endif; ?>
			<?php else : ?>
				<?php echo LayoutHelper::render('system.maintenance', new Registry(), ['language' => $lang ]); ?>
			<?php endif; ?>
			</main>

			<?php /* COPYRIGHT ,   APP-VERSION */ ?>
			<?php if (!$isPrint) : ?>
			<footer class="text-center text-style-inset mb-md-2 mb-lg-3" role="contentinfo">
				<p class="text-muted small alert alert-sm alert-secondary mb-1"><?php echo
					sprintf(Text::translate('COM_FTK_COPYRIGHT_DISCLAIMER_TEXT', $lang ), sprintf('2020 &ndash; %d', date('Y')));
				?></p>
			</footer>
			<?php endif; ?>

			<?php /* SCROLL TO TOP */ ?>
			<?php if (!$isPrint) : ?>
				<?php echo LayoutHelper::render('widget.scrollTop.rounded', [], []); ?>
			<?php endif; ?>
		</div>
	</div>

	<?php /* Script references */ ?>

<?php if (!$isPrint) : ?>
	<?php // Required for workaround native Window.atob() function not properly decoding Base64-encoded HTML markup including Umlauts and special chars. ?>
	<script src="<?php echo UriHelper::osSafe('/assets/js/vendor/dankogai/js-base64/base64.js'); ?>"></script>

	<?php /* jQuery from CDN */ ?>
	<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js'
	        integrity='sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==' crossorigin='anonymous'
	        referrerpolicy='no-referrer'></script>
	<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery-migrate/3.3.2/jquery-migrate.min.js'
	        integrity='sha512-3fMsI1vtU2e/tVxZORSEeuMhXnT9By80xlmXlsOku7hNwZSHJjwcOBpmy+uu+fyWwGCLkMvdVbHkeoXdAzBv+w==' crossorigin='anonymous'
	        referrerpolicy='no-referrer'></script>

	<?php /* jQuery Validation plugin from CDN */ ?>
	<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js'
	        integrity='sha512-37T7leoNS06R80c8Ulq7cdCDU5MNQBwlYoy1TX/WUsLFC2eYNqtKlV0QjH7r8JpG/S0GUMZwebnVFLPd6SU5yg==' crossorigin='anonymous'
	        referrerpolicy='no-referrer'></script>
	<script src='https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/additional-methods.min.js'
	        integrity='sha512-XZEy8UQ9rngkxQVugAdOuBRDmJ5N4vCuNXCh8KlniZgDKTvf7zl75QBtaVG1lEhMFe2a2DuA22nZYY+qsI2/xA==' crossorigin='anonymous'
	        referrerpolicy='no-referrer'></script>
	<?php if ($lang !== 'en') : ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/localization/messages_<?php echo $lang ; ?>.min.js"></script>
	<?php endif; ?>

	<?php /* Bootstrap from CDN */ ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.3/js/bootstrap.bundle.min.js" integrity="sha512-iceXjjbmB2rwoX93Ka6HAHP+B76IY1z0o3h+N1PeDtRSsyeetU3/0QKJqGyPJcX63zysNehggFwMC/bi7dvMig==" crossorigin="anonymous"></script>

	<?php /* App js */ ?>
	<?php if (0) : ?><script src="<?php echo UriHelper::osSafe('/assets/js/script.js?v=' . $_SERVER['REQUEST_TIME']); ?>"></script><?php endif; ?>

	<?php /* JAVASCRIPT */ ?>
	<?php /* Create webStorage object(s) that keeps track of user interaction data */
	$scripts = [];

	// Create localStorage object if not exists.
	$scripts[] = '(function () {';
	$scripts[] = 	'"use strict";';
	$scripts[] = 	' ';
	$scripts[] = 	' ';
	$scripts[] = '})();';

	$scripts = trim(preg_replace('/\s{2,}/i', ' ', implode('', $scripts)));
	?>
	<?php if (strlen($scripts)) : ?>
	<script><?php echo class_exists('MatthiasMullie\Minify\JS') ? (new JS($scripts))->minify() : $scripts; ?></script>
	<?php endif; ?>

	<?php /* Matomo (Piwik) Analytics Code */ ?>
	<?php //echo LayoutHelper::render('widget.matomo', [], []); ?>
<?php endif; ?>
</body>

</html>
