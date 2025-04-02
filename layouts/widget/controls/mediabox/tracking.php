<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang        = $this->get('language');
$partView    = View::getInstance('part');
$part        = $this->get('item');
$processView = View::getInstance('process');
$process     = $this->get('process');
$mediaFiles  = ArrayHelper::fromObject((new Registry($part->get('mediaFiles')))->get($process->get('procID')));
?>
<?php if (0 !== count($mediaFiles)) : ?>
<div class="" id="">
<?php foreach ($mediaFiles as $category => $collection) : ?>
	<?php switch ($category) : ?><?php
		case 'images' :
			$images = ArrayHelper::getValue($collection, 'fullsize', [], 'ARRAY');
			$thumbs = ArrayHelper::getValue($collection, 'thumbs',   [], 'ARRAY');
			$i      = 0;
			?>
			<?php if (0 !== ($cnt = count($images))) : ?>
			<div class="mediabox-collection" id="mediabox-collection-images" data-items="<?php echo $cnt; ?>">
				<?php foreach ($images as $hrtime => $fullsizePath) : ?><?php
					$thumbPath   = ArrayHelper::getValue($thumbs, $hrtime);
					$thumbPath   = preg_match(sprintf('/.*_%d/', $hrtime), $thumbPath) ? $thumbPath : null;

					$type        = (!is_null($thumbPath)) ? @pathinfo($thumbPath, PATHINFO_EXTENSION) : null;
					$data        = (!is_null($thumbPath)) ? file_get_contents($thumbPath) : null;
					$base64Thumb = (!is_null($data)) ? 'data:image/' . $type . ';base64,' . base64_encode($data) : null;
					$base64URI   = ( is_null($data)) ? 'javascript:void(0)' : sprintf('%s.asset.image&%s=%d&pid=%d&fid=%s',
						View::getInstance('part', ['language' => $this->get('language')])->getRoute(),
						$partView->getIdentificationKey(),
						$part->get($part->getPrimaryKeyName()),
						$process->get($process->getPrimaryKeyName()),
						base64_encode(@pathinfo($fullsizePath, PATHINFO_BASENAME))  // image file name with extension
					);
					?>
					<div class="card mb-3">
						<div class="row no-gutters">
							<div class="col-md-4">
								<a href="<?php echo $base64URI; ?>"
								   class="d-block link-to-fullsize-image"
								   target="_blank"
								   data-toggle="tooltip"
								   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_VIEW_PHOTOGRAPH_TEXT', $lang); ?>"
								   rel="nofollow noreferrer"
								>
									<?php // FIXME - render placeholder when either $thumbPath or $data is empty ?>
									<img src="<?php echo $base64Thumb; ?>" class="card-img right-radius-0 thumb enlargable" alt="" width="" height="">
								</a>
							</div>
							<div class="col-md-8">
								<div class="card-body">
									<h5 class="card-title"><?php echo $process->get('name'); ?></h5>
									<p class="card-text"><?php echo sprintf('%s #%d', Text::translate('COM_FTK_LABEL_PHOTO_EVIDENCE_TEXT', $lang), $i += 1); ?></p>
									<p class="card-text">
										<small class="text-muted"><?php echo sprintf(
											Text::translate('COM_FTK_LABEL_CREATION_DATE_TEXT', $lang),
											(!is_null($thumbPath)) ? date('d. M. Y H:i', filemtime($thumbPath)) : Text::translate('COM_FTK_NA_TEXT', $lang));
										?></small>
									</p>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		<?php break; ?>

		<?php default : ?>

	<?php endswitch; ?>
<?php endforeach; ?>
</div>
<?php else : ?>
<p class="alert alert-info"><i class="fas fa-info-circle mr-3"></i><?php echo Text::translate('COM_FTK_HINT_PROCESS_HAS_NO_MEDIAFILES_TEXT', $lang); ?></p>
<?php endif; ?>
<?php // Free memory.
unset($mediaFiles);
unset($part);
unset($process);
