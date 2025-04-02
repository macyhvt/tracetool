<?php
// Register required libraries.
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
//$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
// $return = $view->getReturnPage();	// Browser back-link required for back-button.
$return = basename( (new Uri($input->server->getUrl('REQUEST_URI')))->toString() );
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$proID  = $input->getInt('proid');
?>
<?php /* Access check */
$formData = null;

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

// TODO - Implement ACL and make calculate editor-right from ACL
$canDelete = true;
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$item = $view->get('item');

// Block the attempt to open a non-existing project.
if (!is_a($item, 'Nematrack\Entity\Project') || (is_a($item, 'Nematrack\Entity\Project') && is_null($item->get('proID')))) :
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

$this->item = $item;
$this->user = $user;

// Fetch certificates list.
$data  = $model->getCertificates($proID, $input->getString('dateFrom'), $input->getString('dateTo'));
$dates = ArrayHelper::getColumn($data, 'Datum'); sort($dates);

$dateFrom = current($dates);
$dateFrom = $dateFrom ? date_create($dateFrom)->format('d.m.Y') : $dateFrom;
$dateTo   = end($dates);
$dateTo   = $dateTo   ? date_create($dateTo)->format('d.m.Y') : $dateTo;

// Free memory.
unset($dates);

$dateToday     =  new DateTime('NOW');
$dateYesterday = (new DateTime('NOW'))->sub(new DateInterval('P1D'));

$dateToday     =  new DateTime('NOW');
$dateYesterday = (new DateTime('NOW'))->sub(new DateInterval('P1D'));

// Init tabindex
$tabindex = 0;
?>

<style>
</style>

<div class="form-horizontal position-relative">
	<?php // View title and toolbar ?>
	<?php // TODO - implement toolbar ... it is very tricky because in this view showing buttons depends on item status and user access rights ?>
	<div class="row" style="overflow:hidden">
		<div class="col col-lg-7">
			<a href="<?php echo UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=%s&layout=item&proid=%d',
					$this->language,
					$view->get('name'),
					$this->item->get('proID'))));
			?>"
			   role="button"
			   class="btn btn-link py-0"
			   title="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
			   aria-label="<?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?>"
			   style="vertical-align:text-bottom; color:inherit!important"
			>
				<i class="fas fa-sign-out-alt fa-flip-horizontal"></i>
				<span class="sr-only"><?php echo Text::translate('COM_FTK_LINK_TITLE_BACK_TO_OVERVIEW_TEXT', $this->language); ?></span>
			</a>
			<h1 class="h3 d-inline-block my-0 mr-3" style="line-height:1!important; margin-top:-2px!important"><?php
				echo ucfirst(
						sprintf('%s:<span class="small ml-3">%s &mdash; %s (%d)</span>',
							Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $this->language),
							html_entity_decode($this->item->get('number')),
							Text::translate('COM_FTK_HEADING_RAW_MATERIAL_CERTIFICATES_TEXT', $this->language),
							count($data)
						)
				);
				?></h1>
		</div>
		<div class="col col-lg-5 pl-0">
			<form action="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf('index.php?hl=%s&view=%s&layout=%s&proid=%d',
					$this->language,
					$view->get('name'),
					$view->get('layout'),
					$this->item->get('proID'),
					$input->getString('dateFrom'),
					$input->getString('dateTo') )));
			?>"
			      method="get"
			      name="projectForm"
			      class="form-horizontal statsForm"
			      id="projectForm"
			      data-submit=""
			      style="padding-top:0.05rem"
			>
				<input type="hidden" name="hl"     value="<?php echo $this->language; ?>" />
				<input type="hidden" name="view"   value="<?php echo $view->get('name'); ?>" />
				<input type="hidden" name="layout" value="<?php echo $view->get('layout'); ?>" />
				<input type="hidden" name="proid"  value="<?php echo $this->item->get('proID'); ?>" />

				<div class="form-row">
					<div class="form-group mb-lg-0 col-5">
						<label for="date" class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_FROM_TEXT', $this->language); ?>:</label>
						<div class="input-group date position-relative"
						     data-provide="datepicker"
						     data-date-language="<?php echo $this->language; ?>"
						     data-date-week-start="1"
						     data-date-days-of-week-disabled="[]"
						     <?php // data-date-days-of-week-highlighted="[0,6]" ?>
                             data-date-format="dd.mm.yyyy"
                             data-date-autoclose="true"
                             data-date-calendar-weeks="true"
                             data-date-clear-btn="true"
                             data-date-today-highlight="true"
                             data-date-today-btn="linked"
                             data-date-end-date="<?php echo $dateYesterday->format('d.m.Y'); ?>"
						>
							<input type="text"
							       name="dateFrom"
							       value="<?php echo htmlentities($input->getString('dateFrom', $dateFrom)); ?>"
							       class="form-control form-control-sm datepicker auto-submit"
							       id="ipt-dateFrom"
							       form="projectForm"
							       placeholder="from date"
							       data-target="#certificatesList"
							       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
							       aria-described-by="btn-pick-date"
							       tabindex="<?php echo ++$tabindex; ?>"
							       readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append">
								<span class="input-group-text" id="btn-pick-date" role="button"><i class="fas fa-calendar-alt"></i></span>
							</div>
						</div>
					</div>
					<div class="form-group mb-lg-0 col-5">
						<label for="dateTo" class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_TO_TEXT', $this->language); ?>:</label>
						<div class="input-group date position-relative"
						     data-provide="datepicker"
						     data-date-language="<?php echo $this->language; ?>"
						     data-date-week-start="1"
						     data-date-days-of-week-disabled="[]"
						     <?php // data-date-days-of-week-highlighted="[0,6]" ?>
                             data-date-format="dd.mm.yyyy"
                             data-date-autoclose="true"
                             data-date-calendar-weeks="true"
                             data-date-clear-btn="true"
                             data-date-today-highlight="true"
                             data-date-today-btn="linked"
                             data-date-end-date="<?php echo $dateToday->format('d.m.Y'); ?>"
						>
							<input type="text"
							       name="dateTo"
							       value="<?php echo htmlentities($input->getString('dateTo', $dateTo)); ?>"
							       class="form-control form-control-sm datepicker auto-submit"
							       id="ipt-dateTo"
							       form="projectForm"
							       placeholder="to date"
							       data-target="#certificatesList"
							       aria-label="<?php echo Text::translate('COM_FTK_INPUT_TITLE_SELECT_DATE_TEXT', $this->language); ?>"
							       aria-described-by="btn-pick-date"
							       tabindex="<?php echo ++$tabindex; ?>"
							       readonly
							/><?php // TODO - fix shared parameters ?>
							<div class="input-group-append">
								<span class="input-group-text" id="btn-pick-date" role="button"><i class="fas fa-calendar-alt"></i></span>
							</div>
						</div>
					</div>
					<div class="btn-group text-right" role="group" aria-label="Basic example">
						<button type="submit"
						        class="btn btn-sm btn-secondary"
						        form="projectForm"
						>
							<span title="<?php echo Text::translate('Refresh list', $this->language); ?>"
							      aria-label="<?php echo Text::translate('Refresh list', $this->language); ?>"
							      data-toggle="tooltip"
							>
								<i class="fas fa-sync"></i>
								<span class="btn-text sr-only"><?php echo Text::translate('Refresh', $this->language); ?></span>
							</span>
						</button>
						<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf('index.php?hl=%s&view=%s&layout=%s&proid=%d&dateFrom=%s&dateTo=%s&task=export&format=csv',
								$this->language,
								$view->get('name'),
								$view->get('layout'),
								$this->item->get('proID'),
								$input->getString('dateFrom'),
								$input->getString('dateTo') )));
						?>"
						   role="button"
						   class="btn btn-sm btn-info"
						   rel="nofollow noreferrer"
						   onclick="alert('This function has yet to be implemented. Until then, please copy and paste the data below into an Excel sheet.'); return false;"
						>
							<span title="<?php echo Text::translate('COM_FTK_BUTTON_TITLE_EXPORT_DATA_TO_CSV_TEXT', $this->language); ?>"
							      aria-label="<?php echo Text::translate('COM_FTK_BUTTON_TEXT_EXPORT_TEXT', $this->language); ?>"
							      data-toggle="tooltip"
							>
								<i class="fas fa-download"></i>
								<span class="btn-text sr-only"><?php echo Text::translate('COM_FTK_BUTTON_TEXT_EXPORT_TEXT', $this->language); ?></span>
							</span>
						</a>
					</div>
				</div>
			</form>
		</div>
	</div>

	<?php if (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) : ?>
		<?php echo LayoutHelper::render('system.element.metadata', ['item' => $this->item, 'hide' => []], ['language' => $this->language]); ?>
	<?php endif; ?>

	<hr>

	<?php // Inner toolbar (manage team members, project matrix) ?>
	<?php if (FALSE) : ?>
	<div class="btn-toolbar float-right mt-md-1 mt-lg-2 pt-1" role="toolbar" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLBAR_TEXT', $this->language); ?>">
		<div class="btn-group" role="group" aria-label="<?php echo Text::translate('COM_FTK_LABEL_TOOLS_GROUP_TEXT', $this->language); ?>">
			<?php if ($this->user->getFlags() >= \Nematrack\Access\User::ROLE_MANAGER) : ?>
				<a href="<?php echo UriHelper::osSafe( UriHelper::fixURL(sprintf( 'index.php?hl=%s&view=%s&layout=project.matrix&proid=%d', $this->language, $view->get('name'), /* $this->item->get('proID') */0 ))); // link to matrix ?>"
				   role="button"
				   class="btn btn-link"
				   title="<?php echo Text::translate('COM_FTK_LABEL_PROCESS_MATRIX_TEXT', $this->language); ?>"
				   data-toggle="tooltip"
				   style="vertical-align:baseline; color:inherit!important; text-decoration:none!important"
				>
					<i class="fas fa-th"></i>
					<span class="sr-only"><?php echo Text::translate('COM_FTK_LABEL_PROCESS_MATRIX_TEXT', $this->language); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="position-relative mt-lg-3" id="certificatesList">
		<div>
			<?php if (!$data) : ?>
				<?php if ($input->getString('dateFrom') || $input->getString('dateTo')) : ?>
					<?php echo LayoutHelper::render('system.alert.info', [
							'message' => Text::translate('No result for the selected date period', $this->language),
							'attribs' => [
									'class' => 'alert-sm Xmt-2 Xmt-lg-3 Xmb-3 Xmb-lg-4',
									'id' => 'msg-artProcesses'
							]
					]); ?>
				<?php else : ?>
					<?php echo LayoutHelper::render('system.alert.info', [
							'message' => Text::translate('COM_FTK_HINT_PROJECT_HAS_NO_ARTICLES_TEXT', $this->language),
							'attribs' => [
									'class' => 'alert-sm Xmt-2 Xmt-lg-3 Xmb-3 Xmb-lg-4',
									'id' => 'msg-artProcesses'
							]
					]); ?>
				<?php endif; ?>
			<?php else : ?>
				<table class="table table-sm position-relative clearable sortable" id="article-certificates-list">
					<thead class="thead-dark">
					<tr>
						<th scope="col" class="pl-2 filterable"><?php echo Text::translate('COM_FTK_LABEL_PART_TEXT', $this->language); ?></th>
						<th scope="col" class="pl-2 filterable"><?php echo Text::translate('COM_FTK_LABEL_ARTICLE_TEXT', $this->language); ?></th>
						<th scope="col" class="pl-2 filterable"><?php echo Text::translate('COM_FTK_LABEL_CERTIFICATE_TEXT', $this->language); ?></th>
						<th scope="col"><?php echo Text::translate('COM_FTK_LABEL_DATE_TEXT', $this->language); ?></th>
					</tr>
					</thead>
					<tfoot></tfoot>
					<tbody>
					<?php array_walk($data, function($row) { ?>
						<tr>
							<td scope="row"><?php echo $row->Teil; ?></td>
							<td scope="row"><?php echo $row->Artikel; ?></td>
							<td scope="row"><?php echo $row->Zertifikat; ?></td>
							<td scope="row"><?php echo $row->Datum; ?></td>
						</tr>
					<?php }); ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php // Free memory.
unset($data);
?>
