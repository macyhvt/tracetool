<?php
// Register required libraries.
use Joomla\Uri\Uri;
use Nematrack\App;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang  = $this->get('language');
$view  = $this->__get('view');
$input = (is_a($view, 'Nematrack\View') ? $view->get('input') : App::getInput());
$model = (is_a($view, 'Nematrack\View') ? $view->get('model') : null);
$user  = (is_a($view, 'Nematrack\View') ? $view->get('user')  : App::getAppUser());
$uri   = new Uri($input->server->getUrl('REQUEST_URI')); $uri->delVar('hl');
$query = $uri->getQuery();
$root  = $this->get('root', 'index');   // refers to the root php-file (e.g. index.php, service.php, etc.)
?>

<?php // TODO - fetch all defined languages from database and render list iteratively ?>
<aside class="language-toggler small w-100">
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
			$root . sprintf('.php?hl=%s', mb_strtolower(Text::translate('COM_FTK_LINK_LANG_DE_TEXT', 'de'))) . (!empty($query) ? '&' . $uri->getQuery() : '')
	   )); ?>"
	   role="button"
	   class="language-toggle px-3 <?php echo $lang === 'de' ? 'active' : ''; ?>"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_DE_TEXT', $lang); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_DE_TEXT', $lang); ?>"
	   target="_self"
	   rel="noopener noreferrer"
	   data-bind="forceWindowNavigation"
	><?php echo Text::translate('COM_FTK_LINK_LANG_DE_TEXT', $lang); ?></a
	><a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
			$root . sprintf('.php?hl=%s', mb_strtolower(Text::translate('COM_FTK_LINK_LANG_EN_TEXT', 'de'))) . (!empty($query) ? '&' . $uri->getQuery() : '')
	    )); ?>"
	    role="button"
	    class="language-toggle px-3 border-left <?php echo $lang === 'en' ? 'active' : ''; ?>"
        title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_EN_TEXT', $lang); ?>"
        aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_EN_TEXT', $lang); ?>"
        target="_self"
        rel="noopener noreferrer"
        data-bind="forceWindowNavigation"
	><?php echo Text::translate('COM_FTK_LINK_LANG_EN_TEXT', $lang); ?></a
	><?php if (FALSE) : // HIDE FRENCH ?><a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
			$root . sprintf('.php?hl=%s', mb_strtolower(Text::translate('COM_FTK_LINK_LANG_FR_TEXT', 'de'))) . (!empty($query) ? '&' . $uri->getQuery() : '')
	    )); ?>"
	    role="button"
	    class="language-toggle px-3 border-left <?php echo $lang === 'fr' ? 'active' : ''; ?>"
        title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_FR_TEXT', $lang); ?>"
        aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_FR_TEXT', $lang); ?>"
        target="_self"
        rel="noopener noreferrer"
        data-bind="forceWindowNavigation"
	><?php echo Text::translate('COM_FTK_LINK_LANG_FR_TEXT', $lang); ?></a
	><?php endif; ?><a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
			$root . sprintf('.php?hl=%s', mb_strtolower(Text::translate('COM_FTK_LINK_LANG_HU_TEXT', 'de'))) . (!empty($query) ? '&' . $uri->getQuery() : '')
	    )); ?>"
	    role="button"
	    class="language-toggle pl-3 pr-1 border-left <?php echo $lang === 'hu' ? 'active' : ''; ?>"
        title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_HU_TEXT', $lang); ?>"
        aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_HU_TEXT', $lang); ?>"
        target="_self"
        rel="noopener noreferrer"
        data-bind="forceWindowNavigation"
	><?php echo Text::translate('COM_FTK_LINK_LANG_HU_TEXT', $lang); ?></a
	><a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(
			$root . sprintf('.php?hl=%s', mb_strtolower(Text::translate('COM_FTK_LINK_LANG_UK_TEXT', 'de'))) . (!empty($query) ? '&' . $uri->getQuery() : '')
	    )); ?>"
	    role="button"
	    class="language-toggle pl-3 pr-1 border-left <?php echo $lang === 'uk' ? 'active' : ''; ?>"
        title="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_UK_TEXT', $lang); ?>"
        aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_SWITCH_LANG_TO_UK_TEXT', $lang); ?>"
        target="_self"
        rel="noopener noreferrer"
        data-bind="forceWindowNavigation"
	><?php echo Text::translate('COM_FTK_LINK_LANG_UK_TEXT', $lang); ?></a>
</aside>
