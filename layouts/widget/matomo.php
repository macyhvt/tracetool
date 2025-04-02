<?php /* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Start Matomo (Piwik) */ ?>
<script id="MatomoX" async>
let _paq = window._paq = window._paq || [];
<?php if (FALSE) : ?>
// GDPR - require user consent before processing data
// _paq.push(['requireConsent']);
// _paq.push(['rememberConsentGiven']);
/* tracker methods like "setCustomDimension" should be called before "trackPageView" */
<?php endif; ?>
_paq.push(["setDocumentTitle", document.domain + "/" + document.title]);
_paq.push(["setCookieDomain", "*.nematrack.com"]);
// _paq.push(["setDomains", ["*.nematrack.com"]]);
_paq.push(["disableCookies"]);<?php /* Disable all first party cookies. Existing Matomo cookies for this websites will be deleted on the next page view. Cookies will be even disabled if the user has given cookie consent using the method. */ ?>
_paq.push(["enableHeartBeatTimer", 5]);
_paq.push(["setDoNotTrack", true]);<?php /* Set to true to not track users who opt out of tracking using Mozilla's (proposed) Do Not Track setting. */ ?>
_paq.push(["trackPageView"]);
_paq.push(["enableLinkTracking"]);
(function() {
let u="//froetekstats.de/";
_paq.push(["setTrackerUrl", u+"matomo.php"]);
_paq.push(["setSiteId", "6"]);
let d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0];
g.type="text/javascript"; g.async=true; g.defer=true; g.src=u+"matomo.js"; s.parentNode.insertBefore(g,s);
})();
</script>
<?php /* None-JS tracking method via Image to track users with JS disabled */ ?>
<noscript class="sr-only" style="display:none!important;bottom:-9999px">
	<img src="https://froetekstats.de/matomo.php?idsite=6&rec=1&rand=<?php echo time(); ?>"
	     alt=""
	     referrerpolicy="no-referrer-when-downgrade"
	     style="border:0"
	/>
</noscript>
<?php /* End Matomo (Piwik) */ ?>
