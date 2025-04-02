<?php
// Register required libraries.
use Nematrack\App;
use Nematrack\Helper\UriHelper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$img     = UriHelper::osSafe(FTKPATH_ASSETS . '/img/global/' . (!App::isDevEnv() ? 'maintenance' : 'out-of-service-small') . '.png');
$img     = str_ireplace('//', '/', $img);
$imgData = '';
$srcAttr = '';
	
if (is_file($img) && is_readable($img)) :
	$imgData = base64_encode(file_get_contents($img));
	$srcAttr = 'data: ' . mime_content_type($img) . ';base64,' . $imgData;
endif;
?>
<style>
.img-maintenance {
	-webkit-box-shadow: 0 0 20px 5px rgba(128, 151, 182, 0.5);
	   -moz-box-shadow: 0 0 20px 5px rgba(128, 151, 182, 0.5);
			box-shadow: 0 0 20px 5px rgba(128, 151, 182, 0.5);
}
</style>

<div class="text-center">
	<img <?php echo (!App::isDevEnv() ? ' class="img-maintenance"' : ''); ?> src="<?php echo $srcAttr; ?>" alt="" width="" height="">
</div>
