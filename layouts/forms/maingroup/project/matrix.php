<?php
// Register required libraries.
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Access\User;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
//$proID    = $input->get->getInt('proid');
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
if (is_a($user, 'Nematrack\Entity\User')) :
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
if (!is_a($item, 'Nematrack\Entity\Project') || (is_a($item, 'Nematrack\Entity\Project') && is_null($item->get('proID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PROJECT_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $item->get('proID'))
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

	// Build collection of all registered process steps for this article required to build the table header.
	$allProcessSteps  = array_merge($allProcessSteps, (function($articleProcesses) {
		$tmp = [];

		array_walk($articleProcesses, function($articleProcess) use(&$tmp) {
			$tmp[] = $articleProcess->get('step');
		});

		return $tmp;
	})($articleProcesses));

	// Get name of this article's assemblies collection.
	$namePcs  = explode('.', $article->get('number'));
	$assembly = ArrayHelper::getValue($namePcs, 2, null, 'STRING');

	// If this x-collection doesn't exist yet, create it.
	if (!array_key_exists($assembly, $allArticlesGrouped)) :
		$allArticlesGrouped[$assembly] = [];
	endif;

	// Add this article to its corresponding x-collection.
	$allArticlesGrouped[$assembly][] = $article;
endforeach;

// Free memory.
unset($article);

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
<?php 	if (!$value) continue; ?>
<?php 	echo sprintf('.matrix-cell-%s { background: %s }', $abbreviation, $value); ?>
<?php endforeach; ?>

<?php foreach ($cssTxtColor as $abbreviation => $value) : ?>
<?php 	if (!$value) continue; ?>
<?php 	echo sprintf('.matrix-cell-%s { color: %s }', $abbreviation, $value); ?>
<?php endforeach; ?>

<?php foreach ($cssTxtStyle as $abbreviation => $value) : ?>
<?php 	if (!$value) continue; ?>
<?php 	echo sprintf('.matrix-cell-%s { font-weight: %s }', $abbreviation, ($value ?: 'normal')); ?>
<?php endforeach; ?>
</style>
<?php endif; ?>

<?php if (!$isPrint) : ?>
<div class="form-horizontal">
	<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL( sprintf( 'index.php?hl=%s&view=%s&layout=item&proid=%d',
			$this->language,
			$view->get('name'),
			$item->get('proID')

	   ))); ?>"
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
		$number = $item->get('number');
		$name   = $item->get('name');

		echo ucfirst(
			sprintf(
				'%s:<span class="text-monospace small ml-3">%s &ndash; %s</span>',
				Text::translate('COM_FTK_LABEL_PROJECT_MATRIX_TEXT', $this->language),
					OutputFilter::cleanText($number),
					OutputFilter::cleanText($name)
			)
		);
	?></h1>
<?php

$artNumberPcsw =[];
foreach ($allArticles as $assembly => $articles){
    $article      = current($articles);
    $article      = is_a($article, 'Joomla\Registry\Registry') ? $article : new Registry($article);
    $artNumbers    = $article->get('number');
    $artNumberPcsw[] = explode('.', $artNumbers);

}

echo "<select id='selectBox1' class='selectpvalr' onchange='showHideDivr()'>";
$uniqueValues = [];

foreach ($artNumberPcsw as $key => $vals) {
    $value = $vals[0];
    // Check if the value has not been encountered yet
    if (!isset($uniqueValues[$value])) {
        echo "<option value='$value'>$value</option>";
        // Mark the value as encountered
        $uniqueValues[$value] = true;
    }

}
echo "<option value='all' selected>All</option>";
echo "</select>";

echo "--";
    ?>
    <select class='selectpval' onchange='showHideDiv()' id="selectBox2"></select>

    <?php
echo "<script>
    document.addEventListener('DOMContentLoaded', function () {
        var arrays = " . json_encode($artNumberPcsw) . ";
        var selectBox1 = document.getElementById('selectBox1');
        var selectBox2 = document.getElementById('selectBox2');

        selectBox1.addEventListener('change', function () {
            var selectedValue = selectBox1.value;

            // Clear the options in the second select box
            selectBox2.innerHTML = '';

            // Filter arrays based on the selected value
            var filteredArrays = arrays.filter(function (array) {
                return array[0] === selectedValue;
            });

            // Populate the options in the second select box
            filteredArrays.forEach(function (array) {
                var option = document.createElement('option');
                option.value = array[2];
                option.textContent = array[2];
                selectBox2.appendChild(option);
            });
        });

        // Trigger change event on page load to populate the second select box initially
        selectBox1.dispatchEvent(new Event('change'));
    });
</script>";
?>

	<?php if (!$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
	<?php 	if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) : ?>
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=%s.matrix.config&proid=%s', // config-link
			$this->language,
			$view->get('name'),
			$view->get('name'),
			$item->get('proID')

	   ))); ?>"
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
	<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=%s.matrix&task=print&proid=%s', // print-link
			$this->language,
			$view->get('name'),
			$view->get('name'),
			$item->get('proID')

	   ))); ?>"
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
	<?php // Get first article from list and extract table head information from number stem (the first 3 character groups).
	$article      = current($articles);
	$article      = is_a($article, 'Joomla\Registry\Registry') ? $article : new Registry($article);
	$artNumber    = $article->get('number');
	$artNumberPcs = explode('.', $artNumber);

	$artNumberPcs[] = $article->get('drawingindex');
	// Mask process number and drawing index in article name (replace with 'x')
	array_walk($artNumberPcs, function($no, $i) use(&$artNumberPcs) {
		if ($i > 3)
		{
			$artNumberPcs[$i] = str_repeat('x', mb_strlen($no));
		}
	});

	$basename = implode('.', array_slice($artNumberPcs, 0, 3));
	$procname = implode('.', array_slice($artNumberPcs, 4));

	// Reset the array pointer.
	reset($allArticles);
	?>
	<div id="optChange" class="<?php $varss= explode('.',$basename); echo $varss[2];?> <?php $varss= explode('.',$basename); echo $varss[0];?> table-responsive mb-lg-3 Xmb-xl-4X">
		<table class="table table-bordered table-sm small mb-0" id="projProcessmatrix" style="font-family:Verdana, sans-serif">
			<caption class="table-caption text-left pb-2"><?php
				echo sprintf('%s: %s', Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language), OutputFilter::cleanText($basename));
			?></caption>
			<thead class="thead-dark">
				<tr>
					<th class="text-center" style="min-width:5%">
						<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php echo Text::translate('COM_FTK_LABEL_THE_FROETEK_ARTICLE_NUMBER_TEXT',  $this->language); ?>"><?php
							echo Text::translate('COM_FTK_LABEL_ARTICLE_NUMBER_SHORT_TEXT', $this->language);
						?></abbr>
					</th>
					<th class="text-center" style="min-width:5%">
						<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php echo Text::translate('COM_FTK_LABEL_THE_CUSTOMER_ARTICLE_NUMBER_TEXT', $this->language); ?>"><?php
							echo Text::translate('COM_FTK_LABEL_CUSTOMER_ARTICLE_NUMBER_SHORT_TEXT', $this->language);
						?></abbr>
					</th>
					<th class="text-center" style="min-width:45px">000</th>
					<?php foreach ($allProcessSteps as $step) : ?>
					<th class="text-center" style="min-width:45px"><?php $str = str_pad($step, 3, '0', STR_PAD_LEFT); echo OutputFilter::cleanText($str); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
			<?php // Get path to dummy template file and its hash value for comparison ?>
			<?php $dummyTemplate     = App::getDrawingDummy()->pdf; ?>
			<?php $dummyTemplateHash = hash_file('md5', $dummyTemplate); ?>

			<?php foreach ($articles as /*$artID => */$article) : ?><?php
				$article      = is_a($article, 'Joomla\Registry\Registry') ? $article : new Registry($article);
				$drawing      = $article->get('drawing');
				$drawing      = (isset($drawing) ? new Registry(json_decode((string) $drawing, null, 512, JSON_THROW_ON_ERROR)) : new Registry);	// extract FTK-drawing
				$artNumber    = $article->get('number');
				$artNumberPcs = explode('.', $artNumber);

				// TODO - move to Article-View::prepareDrawings() resp. Article-View::_evaluateDrawings()
				// Add new property path for easy output below.
				$drawing->def( 'isDefined',   ($drawing->get('file', '')) &&  $drawing->get('hash') );
				$drawing->def( 'pdfExists',   (is_file(FTKPATH_BASE     . $drawing->get('file', '')) &&
											   is_readable(FTKPATH_BASE . $drawing->get('file', ''))) );
				$drawing->def( 'thumbExists', (is_file(FTKPATH_BASE     . current($drawing->get('images', []))) &&
											   is_readable(FTKPATH_BASE . current($drawing->get('images', [])))) );

				// Flag as dummy if file is a dummy.
				$drawing->def( 'isDummy',     $drawing->get('hash') == $dummyTemplateHash);

				$custDrawing   = $article->get('customerDrawing');
				$custDrawing   = (isset($custDrawing) ? new Registry(json_decode((string) $custDrawing, null, 512, JSON_THROW_ON_ERROR)) : new Registry);	// extract CUSTOMER-drawing
				$custArtNumber = $article->get('custartno');

				// TODO - move to Article-View::prepareDrawings() resp. Article-View::_evaluateDrawings()
				// Add new property path for easy output below.
				$custDrawing->def( 'isDefined', ($custDrawing->get('file', '')) && $custDrawing->get('hash') );
				$custDrawing->def( 'pdfExists', (is_file(FTKPATH_BASE        . $custDrawing->get('file', '')) &&
											     is_readable(FTKPATH_BASE    . $custDrawing->get('file', ''))) );
				$custDrawing->def( 'thumbExists', (is_file(FTKPATH_BASE      . current($custDrawing->get('images', []))) &&
											       is_readable(FTKPATH_BASE  . current($custDrawing->get('images', [])))) );

				// Flag as dummy if file is a dummy.
				$custDrawing->def( 'isDummy',  $custDrawing->get('hash') == $dummyTemplateHash);

				$basename  = implode('.', array_slice($artNumberPcs, 0, 3));
				$procname  = $artNumberPcs[3];

				$artProcesses = (array) json_decode($article->get('processes'), null, 512, JSON_THROW_ON_ERROR);
				$artProcesses = array_reverse($artProcesses);

				$artProcessesDrawings = (array) json_decode($article->get('drawings'), null, 512, JSON_THROW_ON_ERROR);

				$tmp = [];

				array_walk($artProcessesDrawings, function ($artProcessDrawing, $processStep) use (&$dummyTemplateHash, &$tmp)
				{
					$drawing = (new Registry($artProcessDrawing))->extract('drawing');

					// Add new property path for easy output below.
					$drawing->def( 'pdfExists',   (is_file(FTKPATH_BASE     . $drawing->get('file', '')) &&
												   is_readable(FTKPATH_BASE . $drawing->get('file', ''))) );
					$drawing->def( 'thumbExists', (is_file(FTKPATH_BASE     . current($drawing->get('images', []))) &&
												   is_readable(FTKPATH_BASE . current($drawing->get('images', [])))) );

					// Flag as dummy if file is a dummy.
					$drawing->def( 'isDummy',     $drawing->get('hash') == $dummyTemplateHash);

					// Update input object.
					$artProcessDrawing->drawing = $drawing->toObject();

					// Dump object with process step as array index.
					$tmp[(int) $artProcessDrawing->step] = $artProcessDrawing;

					return true;
				});

				ksort($tmp);

				$artProcessesDrawings = $tmp;

				// Free memory.
				unset($tmp);
			?>
				<tr>
					<?php // Article number ?>
					<td class="text-left align-middle">
						<abbr class="text-decoration-none" data-toggle="tooltip" title="<?php echo OutputFilter::cleanText($artNumber); ?>"><?php echo OutputFilter::cleanText($procname); ?></abbr>
					</td>

					<?php // Customer-drawing
					$str        = $custArtNumber ?: sprintf('%s_%s', $procname, Text::translate('COM_FTK_LABEL_CUSTOMER_TEXT', $this->language));
					$isDefined  = ($custDrawing instanceof Registry && $custDrawing->get('isDefined'));	// there is a properly populated drawing-data object
					$isDummy    = ($custDrawing instanceof Registry && $custDrawing->get('isDummy'));
					$filePDF    = ($custDrawing instanceof Registry && $custDrawing->get('pdfExists')) ? $custDrawing->get('file', '') : null;
					$renderLink = $isDefined && !is_null($filePDF);
					?>
					<td class="text-center align-middle p-0 matrix-cell<?php                      echo (!is_null($filePDF) && !$isDummy) ? ' has-drawing' : ''; ?>"
						data-drawing-is-defined="<?php echo (int) $isDefined; ?>"
						data-is-dummy-drawing="<?php echo (int) $isDummy; ?>"
						data-render-link="<?php echo (int) $renderLink; ?>"
					>
						<?php // The customer drawing must be available only to the customer and authorised Nematrack stuff. ?>
						<?php // If drawing file (e.g. PDF) is available, render a hyperlink. ?>
						<?php if ($isDefined && $renderLink) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $custDrawing->get('file', ''), mt_rand(0, 9999999) ))); ?>"
						   class="d-block"
						   data-bind="windowOpen"
						   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $custDrawing->get('file', ''), mt_rand(0, 9999999) ))); ?>"
						   data-location-target="_blank"
						   target="_blank"
						   rel="nofollow noreferrer"
						>
							<span class="d-block"
								  data-toggle="tooltip"
								  title="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_CUSTOMER_DRAWING_TEXT', $this->language), OutputFilter::cleanText($custArtNumber)); ?>"
								  aria-label="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_CUSTOMER_DRAWING_TEXT', $this->language), OutputFilter::cleanText($custArtNumber)); ?>"
							>
								<span class="btn-text d-block"><?php echo OutputFilter::cleanText($str); ?></span>
							</span>
						</a>
						<?php // ..., otherwise display the customer article number. ?>
						<?php else : ?>
						<abbr class="d-block text-decoration-none"
						      data-toggle="tooltip"
						      title="<?php echo Text::translate( (!$isDefined
								? 'COM_FTK_LINK_TITLE_NO_CUSTOMER_DRAWING_TEXT'
								: ($isDefined && !$filePDF
									? 'COM_FTK_LINK_TITLE_CUSTOMER_DRAWING_NOT_FOUND_TEXT'
									: '' /*'COM_FTK_LINK_TITLE_PRODUCT_MAIN_SPECIFICATIONS_TEXT'*/)), $this->language ); ?>"
						><?php echo OutputFilter::cleanText($custArtNumber) ?: '&mdash;'; ?></abbr>
						<?php endif; ?>
					</td>

					<?php // FTK-drawing (pms)
					$abbr       = 'pms';
					$isDefined  = ($drawing instanceof Registry && $drawing->get('isDefined'));	// there is a properly populated drawing-data object
					$isDummy    = ($drawing instanceof Registry && $drawing->get('isDummy'));
					$filePDF    = ($drawing instanceof Registry && $drawing->get('pdfExists')) ? $drawing->get('file', '') : null;
					$renderLink = $isDefined && !is_null($filePDF);
					?>
					<td class="text-center align-middle p-0 matrix-cell-pms<?php                  echo (!is_null($filePDF) && !$isDummy) ? ' has-drawing' : ''; ?>"
						data-process-abbreviation="<?php echo OutputFilter::cleanText($abbr); ?>"
						data-drawing-is-defined="<?php echo (int) $isDefined; ?>"
						data-is-dummy-drawing="<?php echo (int) $isDummy; ?>"
						data-render-link="<?php echo (int) $renderLink; ?>"
					>
						<?php // The article drawing must be available only to authorised Nematrack stuff. ?>
						<?php // If drawing file (e.g. PDF) is available, render a hyperlink. ?>
						<?php if ($renderLink) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $drawing->get('file', ''), mt_rand(0, 9999999) ))); ?>"
						   class="d-block"
						   data-bind="windowOpen"
						   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $drawing->get('file', ''), mt_rand(0, 9999999) ))); ?>"
						   data-location-target="_blank"
						   target="_blank"
						   rel="nofollow noreferrer"
						>
							<span class="d-block"
								  title="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_X_DRAWING_TEXT', $this->language), mb_strtoupper(OutputFilter::cleanText($abbr))); ?>"
								  aria-label="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_X_DRAWING_TEXT', $this->language), mb_strtoupper(OutputFilter::cleanText($abbr))); ?>"
								  data-toggle="tooltip"
							>
								<span class="btn-text d-block"><?php echo mb_strtoupper(OutputFilter::cleanText($abbr)); ?></span>
							</span>
						</a>
						<?php else : ?>
						<abbr class="d-block text-decoration-none"
						      data-toggle="tooltip"
						      title="<?php echo Text::translate( (!$isDefined
								? 'COM_FTK_LINK_TITLE_NO_DRAWING_TEXT'
								: ($isDefined && !$filePDF
									? 'COM_FTK_LINK_TITLE_DRAWING_NOT_FOUND_TEXT'
									: '' /*'COM_FTK_LINK_TITLE_PRODUCT_MAIN_SPECIFICATIONS_TEXT'*/)), $this->language ); ?>"
						><?php echo mb_strtoupper(OutputFilter::cleanText($abbr)); ?></abbr>
						<?php endif; ?>
					</td>

					<?php // Process-drawing(s) ?>
					<?php foreach ($allProcessSteps as $step) : ?><?php
						$artProcessesDrawing = ArrayHelper::getValue($artProcessesDrawings, (int) $step);
						$artProcessesDrawing = new Registry($artProcessesDrawing);
						$drawing             = $artProcessesDrawing->extract('drawing');

						$isDefined   = $drawing instanceof Registry;	// there is a properly populated drawing-data object
						$hasDrawing  = $drawing instanceof Registry && !is_null($drawing->get('file', ''));	// ADDED on 2023-05-17
//						$isDummy     = ($drawing && $drawing->get('isDummy'));
						$isDummy     = $hasDrawing && $drawing instanceof Registry && $drawing->get('isDummy');	// ADDED on 2023-05-17

						$filePDF     = ($drawing && $drawing->get('pdfExists')) ? $drawing->get('file', '') : null;
						$renderLink  = $isDefined && !is_null($filePDF);

						$process     = ArrayHelper::getValue($allProcesses, $artProcessesDrawing->get('procID'));
						$process     = new Registry($process);
						$processName = $process->get('name', '');
						$processAbbr = $process->get('abbreviation', '');
					?>
					<td class="text-center align-middle p-0 matrix-cell-<?php echo $abbr; ?><?php echo (!is_null($filePDF) && !$isDummy) ? ' has-drawing' : ''; ?>"
					    data-process-abbreviation="<?php echo mb_strtolower($abbr); ?>"
						data-drawing-is-defined="<?php echo (int) $isDefined; ?>"
						data-is-dummy-drawing="<?php echo (int) $isDummy; ?>"
						data-render-link="<?php echo (int) $renderLink; ?>"
					>
						<?php // If drawing file (e.g. PDF) is available, render a hyperlink. ?>
						<?php if ($renderLink) : ?>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $drawing->get('file', ''), mt_rand(0, 9999999) ))); ?>"
						   class="d-block"
						   data-bind="windowOpen"
						   data-location="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( '%s?t=%d', $drawing->get('file', ''), mt_rand(0, 9999999) ))); ?>"
						   data-location-target="_blank"
						   target="_blank"
						   rel="nofollow noreferrer"
						>
							<span class="d-block"
								  title="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_X_DRAWING_TEXT', $this->language), OutputFilter::cleanText($processName)); ?>"
								  aria-label="<?php echo sprintf(Text::translate('COM_FTK_LINK_TITLE_SHOW_PROCESS_X_DRAWING_TEXT', $this->language), OutputFilter::cleanText($processName)); ?>"
								  data-toggle="tooltip"
							>
								<span class="btn-text d-block"><?php echo mb_strtoupper(OutputFilter::cleanText($processAbbr)); ?></span>
							</span>
						</a>
						<?php else: ?>
						<abbr class="d-block text-decoration-none"
						      data-toggle="tooltip"
							  title="<?php echo Text::translate('COM_FTK_HINT_DRAWING_NOT_FOUND_TEXT', $this->language); ?>"
							  aria-label="<?php echo Text::translate('COM_FTK_HINT_DRAWING_NOT_FOUND_TEXT', $this->language); ?>"
						><?php echo mb_strtoupper(OutputFilter::cleanText($processAbbr)); ?></abbr>
						<?php endif; ?>
					</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endforeach; ?>

    <script>
        function showHideDiv() {
            // Get the selected option value
            var selectedValue = document.querySelector('.selectpval').value;

            // Hide all divs with class 'table-responsive'
            var allDivs = document.querySelectorAll('.table-responsive');
            allDivs.forEach(function(div) {
                div.style.display = 'none';
            });

            // Show the divs based on the selected value
            if (selectedValue === 'all') {
                allDivs.forEach(function(div) {
                    div.style.display = 'block';
                });
            } else {
                var selectedDiv = document.querySelector('.' + selectedValue);
                console.log(selectedDiv);
                if (selectedDiv) {
                    selectedDiv.style.display = 'block';
                }
            }
        }

            function showHideDivr() {
            // Get the selected option value
            var selectedValue = document.querySelector('.selectpvalr').value;

            // Show all divs initially
            var allDivs = document.querySelectorAll('.table-responsive');
            allDivs.forEach(function(div) {
            div.style.display = 'none';
        });

            // Show the divs with the corresponding class or show all divs
            if (selectedValue === 'all') {
            allDivs.forEach(function(div) {
            div.style.display = 'block';
        });
        } else {
            var selectedDivs = document.querySelectorAll('.' + selectedValue);
            selectedDivs.forEach(function(div) {
            div.style.display = 'block';
        });
        }
        }

            // Initial call to ensure the correct div is shown when the page loads
            showHideDivr();

    </script>
	<?php if (is_countable($allArticles) && !count($allArticles)) : ?>
		<?php $messages = [Text::translate('COM_FTK_HINT_PROJECT_HAS_NO_ARTICLES_TEXT', $this->language)]; ?>

		<?php // For FRÖTEK- and NEMATECH-managers provide a direct link to articles management ?>
		<?php if ($this->user->get('orgID') == '1' && $this->user->getFlags() > User::ROLE_MANAGER) : ?><?php
				if (!$this->isArchived && !$this->isBlocked && !$this->isDeleted) :
					$messages[] = sprintf(
						Text::translate('COM_FTK_HINT_YOU_CAN_ADD_SOME_HERE_TEXT', $this->language),
						View::getInstance('articles', ['language' => $this->language])->getRoute()
					);
				endif;
		?>
		<?php else : ?>
			<?php $messages = [Text::translate('COM_FTK_HINT_PROCESS_NO_PROCESS_MATRIX_WITHOUT_ARTICLES_TEXT', $this->language)]; ?>
		<?php endif; ?>

		<?php echo LayoutHelper::render('system.alert.notice', ['message' => implode(' ', $messages)]); ?>
	<?php endif; ?>

	<?php // Overlay to block user interaction ?>
	<?php /*if ($this->isBlocked && $this->user->getFlags() < User::ROLE_ADMINISTRATOR) : ?>
	<?php   echo LayoutHelper::render('system.element.blocked', new stdclass, ['language' => $this->language]); ?>
	<?php endif;*/ ?>
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
