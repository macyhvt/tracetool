<?php
// Register required libraries.
// use Joomla\Registry\Registry;
// use Joomla\Uri\Uri;
// use Joomla\Utilities\ArrayHelper;
// use  \Helper\LayoutHelper;
// use  \Helper\UriHelper;
use  \Text;
// use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>

<?php if (is_a($item, ' \Entity\Part')) : ?>
<?php /* Load view data */
$codeGenerator = new \Froetek\Coder\Barcoder;
?>
<table class="table" id="itemMasterData" border="0" cellpadding="0" cellspacing="0" style="width:18.2cm">
	<thead>
		<tr>
			<th style="width:23%; height:67px; text-align:left"><?php echo Text::translate('COM_FTK_LABEL_TRACKING_CODE_TEXT', $this->get('language')); ?></th>
			<th style="width:30%; height:67px; text-align:left"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->get('language')); ?></th>
			<th style="width:31%; height:67px; text-align:left"></th>
		</tr>
	</thead>
	<tbody>
		<?php // Configure barcode/qrcode generator.
		$codeGenerator->__set('delimiter', '@');
		$codeGenerator->__set('width',  2);
		$codeGenerator->__set('height', 2);
		?>
		<tr>
			<td class="align-middle"><?php echo html_entity_decode($code = $item->get('trackingcode')); ?></td>
			<td class="align-middle"><?php echo html_entity_decode($type = $item->get('type')); ?></td>
			<td style="text-align:left"><?php echo $codeGenerator->getQRCode($code, $type); ?></td>
		</tr>
</tbody>
</table>
<?php endif; ?>

<?php // Free memory.
unset($item);
unset($imagick);
unset($codeGenerator);
?>
