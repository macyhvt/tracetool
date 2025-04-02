<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \Helper\StringHelper;
use  \Helper\UserHelper;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang     = $this->get('language');
$view     = $this->__get('view');
$input    = $view->get('input');
$model    = $view->get('model');
$user     = $view->get('user');

$layout   = $input->getCmd('layout');
$proID    = $input->getInt('proid', 0);
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
if (!is_null($task)) :
	switch ($task) :
	endswitch;
endif;
?>
<?php /* Load view data */
$item = $view->get('item');

$this->item = $item;
$this->user = $user;

$allArticles  = $model->getInstance('articles', ['language' => $this->language])->getArticlesByProjectID((int) $proID);
$allArticlesGrouped = [];

$allProcesses = $model->getInstance('processes', ['language' => $this->language])->getList();
$allProcessSteps = [];

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
	$namePcs  = explode('.', $article->get('name'));
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

<style>
h1 {
	font-family: Calibri;
	font-size: 14px;
}
.text-center {
	text-align: center !important;
}
.text-left {
	text-align: left !important;
}
.text-right {
	text-align: right !important;
}
table, h1 {
	width: 620px;
	margin-left: auto;
	margin-right: auto;
}
table {
	border-collapse: collapse;
	margin-bottom: 1.5rem;
	font-family: Calibri, Verdana, sans-serif;
	font-size: 10px;
}
table, th, td {
	border: 1px solid #000;
}
th {
	background: #D9D9D9;
}
td, th {
	padding-top: .1rem;
	padding-bottom: .1rem;
}
.table-caption {
	caption-side: top;
	font-size: 0.75rem;
	font-weight: bold;
	margin-bottom: 0.15rem;
}
.pms {
	background: #D8E4BC;
}
.p-step {
	padding-left: .25rem;
	padding-right: .25rem;
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

<h1 class="h3 project-header text-right"><?php echo sprintf('%s, %s: %s, %s: %s',
	Text::translate('COM_FTK_LABEL_PROCESS_MATRIX_TEXT', $this->language),
	Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language),
	html_entity_decode($item->get('number')),
	Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language),
	(new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Ymd')
); ?></h1>

<?php foreach ($allArticles as $assembly => $articles) : ?>
<?php // Get the first article from the list and prepare table head information for rendering.
$article   = new Registry(current($articles));
$name      = $article->get('name');
$namePcs   = explode('.', $name);
$namePcs[] = $article->get('drawingindex');
// Mask process number and drawing index in article name (replace with 'x')
array_walk($namePcs, function($no, $i) use(&$namePcs) {
	if ($i > 3)
	{
		$namePcs[$i] = str_repeat('x', mb_strlen($no));
	}
});
$basename = implode('.', array_slice($namePcs, 0, 3));
$procname = implode('.', array_slice($namePcs, 4));

// Sort process steps.
// sort($articleProcessSteps);

// Reset the array pointer.
reset($allArticles);
?>
<table class="table table-bordered" id="processMatrix" border="0" cellpadding="0" cellspacing="0">
	<caption class="table-caption text-left pb-2"><?php 
		echo sprintf('%s: %s', Text::translate('COM_FTK_LABEL_PROJECT_TEXT', $this->language), html_entity_decode($basename));
	?></caption>
	<thead class="thead-dark">
		<tr>
			<th class="text-center artno"     width="10%" style="width:100px; font-family:Calibri; font-size:10px"><?php echo html_entity_decode($basename); ?>.</th>
			<th class="text-center custartno" width="10%" style="width:100px; font-family:Calibri; font-size:10px">Article no.</th>
			<th class="text-center p-step"    width="5%"  style="font-family:Calibri; font-size:14px; width:35px">000</th>
			<?php foreach ($allProcessSteps as $step) : ?>
			<th class="text-center p-step"    width="5%"  style="font-family:Arial;   font-size:14px; width:35px"><?php echo
				html_entity_decode(str_pad($step, 3, '0', STR_PAD_LEFT));
			?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($articles as $artID => $article) : ?><?php
		$article   = new Registry($article);
		$name      = $article->get('name');
		$namePcs   = explode('.', $name);
		$namePcs[] = $article->get('drawingindex');
		// Mask process number and drawing index in article name (replace with 'x')
		array_walk($namePcs, function($no, $i) use(&$namePcs) {
			if ($i > 3)
			{
				$namePcs[$i] = str_repeat('x', mb_strlen($no));
			}
		});
		$basename = implode('.', array_slice($namePcs, 0, 3));
		$procname = implode('.', array_slice($namePcs, 3));

		$artProcesses = (array) json_decode($article->get('processes'), null, 512, JSON_THROW_ON_ERROR);
		$artProcesses = array_reverse($artProcesses);

		$artProcessesDrawings = (array) json_decode($article->get('drawings'), null, 512, JSON_THROW_ON_ERROR);

		$tmp = [];

		array_walk($artProcessesDrawings, function($artProcessDrawing) use(&$tmp) {
			$tmp[(int) $artProcessDrawing->step] = $artProcessDrawing;
		});

		ksort($tmp);

		$artProcessesDrawings = $tmp;

		// Free memory.
		unset($tmp);
		?>
		<tr>
			<td scope="row" class="text-center align-middle"     style="font-family:Calibri; font-size:8px">.<?php echo html_entity_decode($procname); ?></td>
			<td             class="text-center artcustno"        style="font-family:Calibri; font-size:8px"> <?php
				echo html_entity_decode($article->get('custartno', '&ndash;'));
			?></td>
			<td             class="text-center align-middle pms" style="font-family:Arial;   font-size:9px">pms</td>
			<?php foreach ($allProcessSteps as $step) : ?><?php
				$artProcessesDrawing = ArrayHelper::getValue($artProcessesDrawings, (int) $step);
				$artProcessesDrawing = new Registry($artProcessesDrawing);

				$process = ArrayHelper::getValue($allProcesses, $artProcessesDrawing->get('procID'));
				$process = new Registry($process);
			?>
			<td class="text-center align-middle matrix-cell-<?php echo $process->get('abbreviation'); ?>" style="font-family:Arial; font-size:9px"><?php
				echo $process->get('abbreviation');
			?></td>
			<?php endforeach; ?>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endforeach; ?>

<?php // Free memory
unset($allArticles);
unset($allProcesses);
unset($allProcessSteps);
unset($articleProcessSteps);
unset($artProcessesDrawings);
unset($input);
unset($item);
unset($lang);
unset($model);
unset($user);
unset($view);
