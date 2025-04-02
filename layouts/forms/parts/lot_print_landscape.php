<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php // Prepare article image(s) to be rendered
$imgPathThumbnail = '';

if (is_object($drawing = $article->get('drawing'))) :
	$articleHasDrawing = true;

	if (is_null($relPathPDF = $drawing->get('file'))) :
		$articleHasDrawing = false;

		// If thumbnail is still not available, fetch a placeholder image from free online service
//		$imgSrc = 'https://via.placeholder.com/500x353/FFFFFF/F00.png?text=' . Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $this->get('language'));
		$imgSrc = sprintf('https://%s/500x353/FFFFFF/F00.png?text=%s', FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER, Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $this->get('language')));
	endif;

	if ($articleHasDrawing) :
		$absPathPDF = trim(FTKPATH_BASE . $relPathPDF) !== '' ? trim(FTKPATH_BASE . $relPathPDF) : '';
		$absPathPDF = \is_file($absPathPDF) && \is_readable($absPathPDF) ? $absPathPDF : '';
		$absPathPDF = realpath($absPathPDF);

		if (\is_file($absPathPDF)) :
			$articleHasDrawing = true;

			// GET instance of Imagick and render article picture.
			if (class_exists('\Imagick')) :
				if (true === (($imagick = new \Imagick) instanceof \Imagick)) :
					/* IMPROVE CLARITY by setting a higher resolution
					 * Required for better text readability.
					 * Must be called  BEFORE  reading the image, otherwise has no effect.
					 * 	density {$x_resolution}x{$y_resolution}
					 */
					$imagick->setResolution(300, 300);

					/* LOAD resource
					 * $imagick->readImage($filePath);				// this will automatically render the last page in the PDF
					 * the number in the bracket indicates the number of the page to be rendered starting with 0
					 *
					 *   VERY TIME CONSUMING !!!
					 *
					 */
					$imagick->readImage($absPathPDF . '[0]');

					/* SET TYPE of target file
					 */
					$imagick->setImageFormat('png');

					/* SCALE image
					 * If 0 is provided as a width or height parameter,
					 * the other value is maintained by imagemagick to preserve the aspect ratio.
					 */
					$imagick->scaleImage(550, 389);
					// $imagick->scaleImage(500, 353);
					// $imagick->scaleImage(450, 318);
					// $imagick->scaleImage(400, 283);
				endif;
			endif;
		endif;
	endif;
endif;
?>
<?php if ($articleHasDrawing && is_a($imagick, 'Imagick')) : ?><?php
	// Code friendly borrowed with slight changes from {@link https://stackoverflow.com/a/13624958}
	// Set header for PHP to read content properly.
	header('Content-Type: image/jpeg');

	ob_start();
	$imgSrc = $imagick->getImageBlob();
	ob_end_clean();

	$imgSrc = 'data:image/jpg;base64,' . base64_encode($imgSrc);

	// Resent content header to HTML or further rendering will result in a black screen.
	header('Content-Type: text/html');
	?>
<?php endif; ?>

<?php // Get article type.
$type = $this->get('lot')->get('type'); ?>

<?php if (is_array($list)) : ?>
<?php /* Load view data */
$userOrganisation = Model::getInstance('organisation', ['language' => $lang])->getItem((int) $user->get('orgID'));

$codeGenerator = new \Froetek\Coder\Barcoder;
?>
<table class="table" id="lotParts" border="0" cellpadding="0" cellspacing="0" style="width:97%; border-collapse:collapse;">
	<thead></thead>
	<tbody>
		<tr>
			<td style="width:500px; vertical-align:top; border-right:1px solid; padding-right:1.5rem">
				<img src="<?php echo $imgSrc; ?>" alt="<?php echo Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->get('language')); ?>" width="" height="" style="padding-bottom:1.5rem; margin-bottom:1.5rem; border-bottom:1px solid;" />
				<table class="table" id="lotMeta" border="0" cellpadding="0" cellspacing="0" style="width:100%; font-family:Arial,sans-serif; font-size:13px">
					<tbody>
						<tr>
							<td style="width:30%; padding-top:0; padding-bottom:.4rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_DATE_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="text-align:left"><?php echo (new DateTime('NOW'))->format('d.m.Y H:i:s');  ?></td>
							<?php // Configure barcode/qrcode generator.
							$codeGenerator->__set('width',  4);
							$codeGenerator->__set('height', 4);
							?>
							<td rowspan="5" style="vertical-align:top; text-align:right; padding-right:5px;">
								<i style="display:block; margin-top:.3rem"><?php echo $codeGenerator->getQRCode($lotNumber); ?></i>
							</td>
						</tr>
						<tr>
							<td style="width:30%; padding-top:.4rem; padding-bottom:.4rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_NAME_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="text-align:left"><?php echo html_entity_decode($user->get('fullname')); ?></td>
						</tr>
						<tr>
							<td style="width:30%; padding-top:.4rem; padding-bottom:.4rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="text-align:left"><?php echo html_entity_decode($userOrganisation->get('name')); ?></td>
						</tr>
						<tr>
							<td style="width:30%; padding-top:.4rem; padding-bottom:.4rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_ARTICLES_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="text-align:left"><?php echo html_entity_decode($article->get('name')); ?></td>
						</tr>
						<tr>
							<td style="width:30%; padding-top:.4rem; padding-bottom:.5rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_QUANTITY_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="padding-top:.4rem; padding-bottom:.5rem; text-align:left"><?php echo (int) count($list); ?></td>
						</tr>
					</tbody>
				</table>
			</td>
			<td style="width:auto;  vertical-align:top; border-left:1px solid;  padding-left:1.5rem">
				<table class="table" id="lotParts" border="0" cellpadding="0" cellspacing="0" style="width:100%">
					<thead>
						<tr>
							<th style="width:40%;  border-bottom:1px solid; margin-bottom:1.5rem; padding-bottom:1rem; text-align:left">
								<?php  echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->get('language')); ?>
							</th>
							<th style="width:auto; border-bottom:1px solid; margin-bottom:1.5rem; padding-bottom:1rem; text-align:left">
								<?php echo Text::translate('COM_FTK_LABEL_LASER_PARAMETERS_TEXT', $this->get('language')); ?>
							</th>
						</tr>
						<tr>
							<td colspan="2" style="padding-top:1.5rem"></td>
						</tr>
					</thead>
					<tbody>
					<?php $i = 1; ?>
					<?php \array_walk($list, function($part) use(&$i, &$lang, &$lot, &$type, &$codeGenerator) { ?>
						<?php // Configure barcode/qrcode generator.
						$codeGenerator->__set('delimiter', '@');
						$codeGenerator->__set('width',  2);
						$codeGenerator->__set('height', 2);
						?>
						<tr data-row="<?php echo $i; ?>">
							<?php // Load data into Registry for less error prone access. ?>
							<?php $part = new Registry($part); ?>
							<?php $code = $part->get('trackingcode'); ?>
							<td style=""><?php echo html_entity_decode($code); ?></td>
							<td style="text-align:<?php echo $i % 2 == 0 ? 'right' : 'left'; ?>"><?php echo $codeGenerator->getQRCode($code, $type); ?></td>
						</tr>
					<?php $i += 1; }); ?>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
	<tfood></tfood>
</table>
<?php endif; ?>

<?php // Free memory.
unset($list);
unset($imagick);
unset($codeGenerator);
unset($userOrganisation);
?>
