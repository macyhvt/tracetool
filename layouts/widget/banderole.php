<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\App;
use Nematrack\Helper\UriHelper;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang          = $this->get('language');
$input         = App::getInput();
$data          = new Registry($this->get('data'));
$banderoleData = $data->extract('banderoleData', [], 'ARRAY');
?>
<style>
.bg-goldenrod       { background-color: #d8a420!important }
.bg-light-goldenrod { background-color: #efbB37!important }   /* lightened 9% */
.bg-dark-goldenrod  { background-color: #c18d09!important }   /* darkened 9% */

.color-goldenrod       { color: #d8a420!important }
.color-light-goldenrod { color: #efbB37!important }   /* lightened 9% */
.color-dark-goldenrod  { color: #c18d09!important }   /* darkened 9% */

.banderole-stripe.semi { opacity: 0.4!important }
.banderole-text.semi   { opacity: 0.5!important }
</style>
<div class="position-sticky text-center t-0 top-0 ml-2" id="process-banderole">
	<?php // Banderole top icon ?>
	<i class="fas fa-medal fa-3x mb-2 mb-md-3 mb-lg-4 color-light-goldenrod"></i>

	<?php // Banderole stripes (1 per completely tracked process) ?>
	<?php $i = 0; foreach ($banderoleData as $procID => $obj) : ?><?php
		$procAbbreviation            = $obj->abbreviation;
		$transparency                = $obj->transparency;
		$styleStripeOffsetTop        = 5;    // first element offset to banderole icon. Follow-up elements' offset is calculated via defined offset multiplier.
		$styleStripeOffsetMultiplier = 1.5;
		$styleStripeNextOffset       = $styleStripeOffsetTop;
		?>
	<?php // Stripe wrapper - necessary to generate sharp edges of the actual stripe via "overflow-x:hidden" ?>
	<span class="banderole-stripe-wrapper position-absolute d-inline-block"
	      style="top:<?php echo ($i == '0') ? $styleStripeOffsetTop : ($styleStripeNextOffset + ($i * $styleStripeOffsetMultiplier)); ?>rem">
		<?php // The actual stripe slightly rotated as demanded by sma ?>
		<span class="banderole-stripe <?php echo $transparency; ?> d-inline-block bg-light-goldenrod"></span>
	</span>
	<?php   $i += 1; ?>
	<?php endforeach; ?>

	<?php // Process abbreviation ?>
	<span class="banderole-abbreviation position-absolute d-block color-goldenrod"
	      style="top:<?php echo $styleStripeNextOffset + ($i * $styleStripeOffsetMultiplier); ?>rem;"
	><?php foreach ($banderoleData as $procID => $obj) : ?><?php
		$procAbbreviation            = $obj->abbreviation;
		$transparency                = $obj->transparency;
	?>
		<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf('%s#p-%s', $input->server->getUrl('REQUEST_URI'), hash('MD5', $procID)) ) ); ?>"
		   class="btn-link banderole-text d-inline-block align-middle text-bold text-center text-decoration-none text-reset text-uppercase w-100 py-4 <?php echo $transparency; ?>"
		   role="button"
		   data-toggle="tooltip"
		   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_JUMP_TO_PROCESS_TEXT', $lang); ?>"
		><?php
			echo trim(implode('<br>', mb_str_split(rtrim($procAbbreviation, '1'))));
		?></a>
		<?php   $i += 1; ?>
		<?php endforeach; ?>
	</span>
</div>
