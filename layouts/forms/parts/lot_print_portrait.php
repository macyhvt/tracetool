<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Nematrack\Helper\ImageHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php // Prepare article image(s) to be rendered
$imgPathThumbnail = '';

if (is_object($drawing = $article->get('drawing'))) :
	// Article has drawing object AND both the thumbnail + fullsize image
	if ($drawing->def('images', null) && count($images = $drawing->get('images', []))) :
		$imgPathThumbnail = current($images);
		$imgPathThumbnail = (trim(FTKPATH_BASE . $imgPathThumbnail) !== '' ? trim(FTKPATH_BASE . $imgPathThumbnail) : '');
		$imgPathThumbnail = (\is_file($imgPathThumbnail) && \is_readable($imgPathThumbnail) ? $imgPathThumbnail : '');
		$imgPathThumbnail = str_ireplace(FTKPATH_BASE, '', $imgPathThumbnail);
	endif;

	if (!\is_file(FTKPATH_BASE . $imgPathThumbnail) && \is_file(FTKPATH_BASE . $drawing->get('file'))) :
		$imgPathThumbnail = !empty($imgPathThumbnail) ? $imgPathThumbnail : ImageHelper::makeImageFromPDF(FTKPATH_BASE . $drawing->get('file'), [
			// 'width'      => FTKRULE_DRAWING_THUMB_WIDTH,	// height will be calculated to preserve correct aspect ratio - only if width is set to 0 !!!
			'height'     => FTKRULE_DRAWING_THUMB_HEIGHT,	// width will be calculated to preserve correct aspect ratio - only if width is set to 0 !!!
			'resolution' => '72',
			'suffix'     => '__thumb',
			'extension'  => FTKRULE_DRAWING_THUMB_EXTENSION
		]);
		$imgPathThumbnail = (is_array($imgPathThumbnail) ? current($imgPathThumbnail) : $imgPathThumbnail);
		$imgPathThumbnail = str_ireplace(FTKPATH_BASE, '', $imgPathThumbnail) . '?t=' . mt_rand(0, 9999999);
	endif;
endif;
?>

<?php // Get article type.
$type = $this->get('lot')->get('type'); ?>

<?php if (is_array($list)) : ?>
<?php /* Load view data */
		$userOrganisation = Model::getInstance('organisation', ['language' => $lang])->getItem((int) $user->get('orgID'));

		$codeGenerator = new \Froetek\Coder\Barcoder;
?>
<table class="table" id="lotParts" border="0" cellpadding="0" cellspacing="0" style="width:18.2cm">
	<thead>
		<tr>
			<td colspan="2" style="width:30%; border-bottom:1px solid">
				<table class="table" id="lotMeta" border="0" cellpadding="0" cellspacing="0" style="width:100%; font-family:Arial,sans-serif; font-size:13px">
					<tbody>
						<tr>
							<td style="width:30%; padding-top:0; padding-bottom:.4rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_DATE_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="text-align:left"><?php echo (new DateTime('NOW'))->format('d.m.Y H:i:s');  ?></td>
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
							<td style="width:30%; padding-top:.4rem; padding-bottom:1.2rem; text-align:left"><strong><?php echo Text::translate('COM_FTK_LABEL_QUANTITY_TEXT', $this->get('language')); ?>:</strong></td>
							<td style="padding-top:.4rem; padding-bottom:1.2rem; text-align:left"><?php echo (int) count($list); ?></td>
						</tr>
					</tbody>
				</table>
			</td>
			<td style="border-bottom:1px solid;<?php echo /* empty($imgPathThumbnail) ? ' padding-bottom:15px;' : '' */ 'padding-bottom:15px'; ?>">
				<table class="table" id="lotImage" border="0" cellpadding="0" cellspacing="0" style="width:100%">
					<tr>
						<?php // Configure barcode/qrcode generator.
						$codeGenerator->__set('width',  2);
						$codeGenerator->__set('height', 2);
						?>
						<td style="width:<?php echo /* empty($imgPathThumbnail) ? '50' : '70' */ '50'; ?>%; text-align:left">
							<i style="display:block; Xmargin-top:65px; margin-top:38px; margin-bottom:35px"><?php echo $codeGenerator->getQRCode($lotNumber); ?></i>
						</td>
						<td style="<?php echo /* empty($imgPathThumbnail) ? 'vertical-align:top; text-align:right' : '' */ 'vertical-align:top; text-align:right'; ?>">
							<?php if (false/*  !empty($imgPathThumbnail) */) : ?>
							<img src="<?php echo UriHelper::osSafe($imgPathThumbnail); ?>"
								 alt="<?php echo Text::translate('COM_FTK_LABEL_ARTICLE_DRAWING_TEXT', $this->get('language')); ?>"
								 width=""
								 height=""
								 style="width:185px; height:130px; margin-bottom:8px"
							/>
							<?php else : ?>
							<strong style="font-family:Arial,sans-serif; display:inline-block; margin-bottom:10px;"><?php 
								//echo Text::translate('COM_FTK_HINT_NO_DRAWING_TEXT', $this->get('language'));
							?></strong>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<th style="width:23%; height:67px; text-align:left;/* padding-top:2rem; *//* padding-bottom:0.8rem; */"><?php echo Text::translate('COM_FTK_LABEL_TRACKING_CODE_TEXT', $this->get('language')); ?></th>
			<th style="width:30%; height:67px; text-align:left;/* padding-top:2rem; *//* padding-bottom:0.8rem; */"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->get('language')); ?></th>
			<th style="width:31%; height:67px; text-align:left;/* padding:2rem 0 0.8rem; */"><?php echo Text::translate('COM_FTK_LABEL_LASER_PARAMETERS_TEXT', $this->get('language')); ?></th>
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
			<td style="padding-top:42px; padding-bottom:42px"><?php echo html_entity_decode($code); ?></td>
			<td style="text-align:left"><?php echo html_entity_decode($type); ?></td>
			<td style="text-align:<?php echo $i % 2 == 0 ? 'right' : 'left'; ?>"><?php echo $codeGenerator->getQRCode($code, $type); ?></td>
		</tr>
	<?php $i += 1; }); ?>
	</tbody>
</table>
<?php endif; ?>

<?php // Free memory.
unset($list);
unset($imagick);
unset($codeGenerator);
unset($userOrganisation);
?>
