<?php
// Register required libraries.
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* NOTE:  CSS and Javascript code borrowed with slight changes from
{@link https://www.geeksforgeeks.org/wp-content/themes/iconic-one/css/gfg.css?ver=7.7} and
{@link https://www.geeksforgeeks.org/wp-content/themes/iconic-one/js/gfg.js?ver=10.33}

The following Javascript fragment is required.

// Snippet to show/hide "scrollTop" button
$(window).scroll(function() {
	if ($(this).scrollTop() > 200) {
		$('#scrollTopBtn').stop().fadeIn("fast")
	} else {
		$('#scrollTopBtn').stop().fadeOut("fast")
	}
});

$('#scrollTopBtn').click(function() {
	$('html, body').animate({scrollTop: 0}, 250);
	return !1;
});

The Javascript code was already copied into our script.js
*/ ?>
<style>.scrollTopButton { display:none; position:fixed; z-index:10; width:40px; height:40px; right:40px; bottom:40px; background-color:#999999; /*rgb(153, 153, 153)*/ }</style>
<button type="button"
		class="btn btn-link btn-secondary border-0 rounded text-white scrollTopButton"
		id="scrollTopBtn"
		title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SCROLL_TO_TOP_TEXT', $this->get('language')); ?>"
		data-toggle="tooltip"
		aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_SCROLL_TO_TOP_TEXT', $this->get('language')); ?>"
		aria-hidden="true"
		aria-live="polite"
>
	<span class="sr-only" aria-hidden="true">&Delta;</span>
	<i class="fas fa-angle-up fa-lg"<?php /*style="font-size:180%"*/ ?>></i>
</button>
