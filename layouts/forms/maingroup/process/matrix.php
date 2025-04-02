<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \Access\User;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Helper\UserHelper;
use  \Messager;
use  \Text;
use  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$proID    = $input->get->getInt('proid');
$task     = $input->post->getCmd('task')  ?? ($input->getCmd('task') ?? null);

$redirect = $input->post->getString('return') ?? ($input->getString('return') ?? null);
$redirect = (!is_null($redirect) && StringHelper::isBase64Encoded($redirect))
	? base64_decode($redirect)
	: ( (!empty($referrer = basename(ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', '', 'STRING'))) && ( !preg_match('/\blayout=logout&task=leave\b/i', $referrer) && !preg_match('/\blogout=true\b/i', $referrer) ) )
		? $referrer
		: basename($_SERVER['PHP_SELF'])
);

$isPrint  = $task === 'print';
?>
<?php /* Access check */
$formData = null;

if (is_a($user, ' \Entity\User')) :
	try
	{
		$formData = $user->__get('formData');
		$formData = (is_array($formData)) ? $formData : [];
	}
	catch (Exception $e)
	{
		$formData = null;
	}
endif;
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing project.
if (!is_a($item, ' \Entity\Project') || (is_a($item, ' \Entity\Project') && is_null($item->get('proID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PROJECT_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $proID)
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('projects', ['language' => $this->language])->getRoute());
		exit;
    endif;

    return false;
endif;

$this->item       = $item;
$this->user       = $user;
$this->isArchived = $this->item->get('archived') == '1';
$this->isBlocked  = $this->item->get('blocked')  == '1';
$this->isDeleted  = $this->item->get('trashed')  == '1';

$allArticles  = $model->getInstance('articles',  ['language' => $this->language])->getArticlesByProjectID((int) $item->get('proID', 0));
$allArticlesGrouped = [];

$allProcesses = $model->getInstance('processes', ['language' => $this->language])->getList();
$allProcessSteps = [];

// TODO - decide whether this should be a function provided by Articles-model and migrate.
foreach ($allArticles as $artID => $article) :
	$article = new Registry($article);

	$articleProcesses = $model->getInstance('article', ['language' => $this->language])->getArticleProcesses($artID);

	// Build a collection of all registered process steps for this article required to build the table header.
	$allProcessSteps  = array_merge($allProcessSteps, (function($articleProcesses) {
		$tmp = [];

		array_walk($articleProcesses, function($articleProcess) use(&$tmp) {
			$tmp[] = $articleProcess->get('step');
		});

		return $tmp;
	})($articleProcesses));

	// Get the name of this article's assembly collection.
	$namePcs  = explode('.', $article->get('number'));
	$assembly = ArrayHelper::getValue($namePcs, 2, null, 'STRING');

	// If this x-collection doesn't exist yet, create it.
	if (!array_key_exists($assembly, $allArticlesGrouped)) :
		$allArticlesGrouped[$assembly] = [];
	endif;

	// Add this article to its corresponding x-collection.
	$allArticlesGrouped[$assembly][] = $article;
endforeach;

$allArticles = $allArticlesGrouped;

// Free memory.
unset($allArticlesGrouped);

$allProcessSteps = array_unique($allProcessSteps);

sort($allProcessSteps);

// Get matrix configuration from user profile.
$userProfile  = new Registry(UserHelper::getProfile($this->user));
$matrixConfig = $userProfile->extract('process.matrix');
$matrixConfig = (is_a($matrixConfig, 'Joomla\Registry\Registry') ? $matrixConfig : new Registry);

$cssCellBG    = (array) $matrixConfig->get('colors.cell');
$cssTxtColor  = (array) $matrixConfig->get('colors.font');
$cssTxtStyle  = (array) $matrixConfig->get('font.weight');
?>

<?php if (!$isPrint) : ?>
<style>
.table-caption {
	caption-side: top;
	font-weight: bold;
}
<?php foreach ($cssCellBG as $abbreviation => $value) : ?>
<?php 	echo sprintf('.matrix-cell-%s { background: %s }', $abbreviation, $value); ?>
<?php endforeach; ?>

<?php foreach ($cssTxtColor as $abbreviation => $value) : ?>
<?php 	echo sprintf('.matrix-cell-%s { color: %s }', $abbreviation, $value); ?>
<?php endforeach; ?>

<?php foreach ($cssTxtStyle as $abbreviation => $value) : ?>
<?php 	echo sprintf('.matrix-cell-%s { font-weight: %s }', $abbreviation, ($value ?: 'normal')); ?>
<?php endforeach; ?>
</style>
<?php endif; ?>

<?php if (!$isPrint) : ?>
<div class="form-horizontal">
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=item&proid=%d', $this->language, $view->get('name'), $item->get('proID') ))); ?>"
	   role="button"
	   class="btn btn-link"
	   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?>"
	   style="vertical-align:super; color:inherit!important"
	>
		<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
		<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_PREVIOUS_PAGE_TEXT', $this->language); ?></span>
	</a>

	<h1 class="h3 d-inline-block mr-3"><?php
		$projectNumber = html_entity_decode($item->get('number'));
		$projectName   = html_entity_decode($item->get('name'));

		echo ucfirst(
			sprintf(
				'%s:<span class="small ml-3">%s &ndash; %s</span>',
				Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language),
				OutputFilter::cleanText($projectNumber),
				OutputFilter::cleanText($projectName)
			)
		);
	?></h1>

	<?php if (!$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
	<?php 	if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=process.matrix.config&proid=%s', $this->language, $view->get('name'), $item->get('proID') ))); // config-link ?>"
	   role="button"
	   class="btn btn-secondary"
	   data-toggle="tooltip"
	   title="<?php echo Text::translate('COM_FTK_LABEL_EXPORT_SETTINGS_TEXT', $this->language); ?>"
	   style="vertical-align:super; opacity:0.4"
	>
		<i class="fas fa-sliders-h"></i>
		<span class="d-none"><?php echo Text::translate('COM_FTK_LABEL_EXPORT_SETTINGS_TEXT', $this->language); ?></span>
	</a>

	<?php 		if (is_countable($allArticles) && count($allArticles)) : ?>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=process.matrix&task=print&proid=%s', $this->language, $view->get('name'), $item->get('proID') ))); // print-link ?>"
	   role="button"
	   class="btn btn-info float-right"
	   title="<?php echo Text::translate('COM_FTK_LABEL_EXPORT_TO_PRINT_TEXT', $this->language); ?>"
	   aria-label="<?php echo Text::translate('COM_FTK_LABEL_EXPORT_TO_PRINT_TEXT', $this->language); ?>"
	   target="_blank"
	   style="vertical-align:super"
	>
		<i class="fas fa-file-export"></i>
		<span class="d-none d-md-inline ml-lg-1"><?php echo Text::translate('COM_FTK_LABEL_EXPORT_TO_PRINT_TEXT', $this->language); ?></span>
	</a>
	<?php 		endif; ?>
	<?php 	endif; //-> END: !isArchived && !isBlocked && !isDeleted ?>
	<?php endif; //->END: !isCustomer && !isSupplier ?>

	<hr>

	<?php foreach ($allArticles as $assembly => $articles) : ?>
	<?php // Get the first article from the list and prepare table head information for rendering.
	$article     = new Registry(current($articles));
	$number      = $article->get('number');
	$numberPcs   = explode('.', $number);
	$numberPcs[] = $article->get('drawingindex');

	// Mask process number and drawing index in article name (replace with 'x')
	array_walk($numberPcs, function($no, $i) use(&$numberPcs) {
		if ($i > 3)
		{
			$numberPcs[$i] = str_repeat('x', mb_strlen($no));
		}
	});

	$basename = implode('.', array_slice($numberPcs, 0, 3));
	$procname = implode('.', array_slice($numberPcs, 4));

	// Reset the array pointer.
	reset($allArticles);
	?>
	<table class="table table-bordered table-sm small" id="projProcessmatrix" style="font-family:Verdana, sans-serif">
		<caption class="table-caption text-left pb-2"><?php
			echo sprintf('%s: %s', Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language), OutputFilter::cleanText($basename));
		?></caption>
		<thead class="thead-dark">
			<tr>
				<th class="text-center" style="width:10%"><?php echo OutputFilter::cleanText($basename); ?></th>
				<th class="text-center" style="width:10%"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_NUMBER_SHORT_TEXT', $this->language); ?></th>
				<th class="text-center" style="width:5%">000</th>
				<?php foreach ($allProcessSteps as $step) : $str = str_pad($step, 3, '0', STR_PAD_LEFT); ?>
				<th class="text-center" style="width:5%"><?php echo OutputFilter::cleanText($str); ?></th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($articles as $artID => $article) : ?><?php
			$article   = new Registry($article);
			$drawing   = $article->get('drawing');
			$drawing   = (isset($drawing) ? new Registry(json_decode((string) $article->get('drawing', ''), null, 512, JSON_THROW_ON_ERROR)) : null);
			$number    = $article->get('number');
			$numberPcs = explode('.', $number);
			/*array_push($numberPcs, $article->get('drawingindex'));	// Disabled on 2020-Dec-18 as it is obvious that only the middle part of the article number must be displayed
			// Mask process number and drawing index in article name (replace with 'x')
			array_walk($numberPcs, function($no, $i) use(&$numberPcs) {
				if ($i > 3)
				{
					$numberPcs[$i] = str_repeat('x', mb_strlen($no));
				}
			});*/
			$basename  = implode('.', array_slice($numberPcs, 0, 3));
			// $procname  = implode('.', \array_slice($numberPcs, 3));	// Disabled on 2020-Dec-18 as it is obvious that only the middle part of the article number must be displayed
			$procname  = $numberPcs[3];

			$artProcesses = (array) json_decode($article->get('processes'), null, 512, JSON_THROW_ON_ERROR);
			$artProcesses = array_reverse($artProcesses);

			$artProcessesDrawings = (array) json_decode($article->get('drawings'), null, 512, JSON_THROW_ON_ERROR);

			$tmp = [];

			array_walk($artProcessesDrawings, function($artProcessDrawing, $processStep) use(&$tmp)
			{
				$drawing = (new Registry($artProcessDrawing))->extract('drawing');

				// Add new property 'path' for comfy outputting below.
				$drawing->def( 'pdfExists',   (is_file(FTKPATH_BASE . $drawing->get('file')) &&
											   is_readable(FTKPATH_BASE . $drawing->get('file'))) );
				$drawing->def( 'thumbExists', (is_file(FTKPATH_BASE . current($drawing->get('images'))) &&
											   is_readable(FTKPATH_BASE . current($drawing->get('images')))) );

				// Update input object.
				$artProcessDrawing->drawing = $drawing->toObject();

				// Dump the object with the process step as array index.
				$tmp[(int) $artProcessDrawing->step] = $artProcessDrawing;
			});

			ksort($tmp);

			$artProcessesDrawings = $tmp;

			// Free memory.
			unset($tmp);
		?>
			<tr>
				<td scope="row" class="text-center align-middle">
				<?php // If drawing file (e.g. PDF) is available, render the link to drawing file... ?>
				<?php if ((!$this->user->isCustomer() && !$this->user->isSupplier()) && is_a($drawing, 'Joomla\Registry\Registry') && $drawing->get('file')) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( '%s?t=%d', $drawing->get('file'), mt_rand(0, 9999999) ))); ?>"
					   class="d-block"
					   data-toggle="tooltip"
					   title="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_ARTICLE_X_DRAWING_TEXT', $this->language), OutputFilter::cleanText($number)); ?>"
					   aria-label="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_ARTICLE_X_DRAWING_TEXT', $this->language), OutputFilter::cleanText($number)); ?>"
					   target="_blank"
					><?php echo OutputFilter::cleanText($procname); ?></a>
				<?php // ...otherwise display article name as tooltip ?>
				<?php else : ?>
					<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php echo OutputFilter::cleanText($number); ?>"><?php echo OutputFilter::cleanText($procname); ?></abbr>
				<?php endif; ?>
				</td>
				<td class="text-center"><?php $str = $article->get('custartno'); echo OutputFilter::cleanText($str); ?></td>
				<td class="text-center align-middle matrix-cell-pms" data-process-abbreviation="pms">
					<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php echo Text::translate('COM_FTK_LABEL_CUSTOMER_SPECIFICATIONS_TEXT', $this->language); ?>">pms</abbr>
				</td>
				<?php foreach ($allProcessSteps as $step) : ?><?php
					$artProcessesDrawing = ArrayHelper::getValue($artProcessesDrawings, (int) $step);
					$artProcessesDrawing = new Registry($artProcessesDrawing);
					$drawing             = $artProcessesDrawing->extract('drawing');

					$filePDF = ($drawing && $drawing->get('pdfExists')) ? $drawing->get('file') : null;

					$process = ArrayHelper::getValue($allProcesses, $artProcessesDrawing->get('procID'));
					$process = new Registry($process);
				?>
				<td class="text-center align-middle matrix-cell-<?php echo $process->get('abbreviation'); ?><?php echo (!is_null($filePDF)) ? ' has-drawing' : ''; ?>" data-process-abbreviation="<?php echo $process->get('abbreviation'); ?>">
					<?php if ((!$this->user->isCustomer() && !$this->user->isSupplier()) && !is_null($filePDF)) : ?>
					<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL( sprintf( '%s?t=%d', $filePDF, mt_rand(0, 9999999) ))); ?>"
					   class=""
					   target="_blank"
					   rel="nofollow noreferrer"
					>
						<span title="<?php $str = $process->get('name'); echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_X_DRAWING_TEXT', $this->language), OutputFilter::cleanText($str)); ?>"
							  aria-label="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_X_DRAWING_TEXT', $this->language), OutputFilter::cleanText($str)); ?>"
							  data-toggle="tooltip"
						>
							<span class="btn-text"><?php $str = $process->get('abbreviation'); echo OutputFilter::cleanText($str); ?></span>
						</span>
					</a>
					<?php else: ?>
					<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php $str = $process->get('name'); echo OutputFilter::cleanText($str); ?>"><?php
						$str = $process->get('abbreviation'); echo OutputFilter::cleanText($str);
					?></abbr>
					<?php endif; ?>
				</td>
				<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endforeach; ?>

	<?php if (is_countable($allArticles) && !count($allArticles)) : ?>
		<?php $messages = [Text::translate('COM_FTK_HINT_PROJECT_HAS_NO_ARTICLES_TEXT', $this->language)];

			if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) :
				$messages[] = sprintf(
					Text::translate('COM_FTK_HINT_YOU_CAN_DO_THIS_HERE_TEXT', $this->language),
					View::getInstance('articles', ['language' => $this->language])->getRoute()
				);
			endif;
		?>
		<?php echo LayoutHelper::render('system.alert.notice', ['message' => implode(' ', $messages)
		]); ?>
	<?php endif; ?>

	<?php // Overlay to block user interaction ?>
	<?php if ($this->isBlocked && $this->user->getFlags() < User::ROLE_ADMINISTRATOR) : ?>
	<?php 	// echo LayoutHelper::render('system.element.blocked', new \stdclass, ['language' => $this->language]); ?>
	<?php endif; ?>
</div>
<?php else : ?>
	<?php if (!$this->isBlocked) : ?>
		<?php include_once __DIR__ . '/matrix/print/portrait.php'; ?>
	<?php endif; ?>
<?php endif; ?>

<?php // Free memory
unset($allArticles);
unset($allProcesses);
unset($allProcessSteps);
unset($articleProcessSteps);
unset($artProcessesDrawings);
unset($drawing);
unset($input);
unset($item);
unset($lang);
unset($model);
unset($user);
unset($view);
