<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Nematrack\Helper\UriHelper;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View;

/**
 * Class description
 */
class Lizt extends View
{
	/**
	 * @var    array|null  A list of entities.
	 * @since  1.1
	 */
	protected array $list;

	/**
	 * {@inheritdoc}
	 * @see View::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

		$this->list = [];
	}

	/**
	 * {@inheritdoc}
	 * @see View::getRoute
	 */
	public function getRoute() : string
	{
		$route = mb_strtolower( sprintf( 'index.php?hl=%s&view=%s&layout=list', $this->get('language'), $this->get('name') ) );

		return UriHelper::fixURL($route);
	}

	/**
	 * {@inheritdoc}
	 * @see View::prepareDocument
	 */
	protected function prepareDocument() : bool
	{
		parent::prepareDocument();

		// Calculate page heading
		if (property_exists($this, 'viewTitle'))
		{
			$this->viewTitle = Text::translate(mb_strtoupper(sprintf('COM_FTK_HEADING_%s_TEXT', $this->get('name'))), $this->language);

			if (strlen($this->input->getString('filter')))
			{
				switch ($this->input->getString('filter'))
				{
					case (ListModel::FILTER_ALL) :
						$this->viewTitle = sprintf('%s %s',
							Text::translate('COM_FTK_LIST_OPTION_ALL_ITEMS_LABEL', $this->language),
							$this->viewTitle
						);
					break;

					case (ListModel::FILTER_ACTIVE) :
						$this->viewTitle = sprintf('%s%s %s',
							Text::translate('COM_FTK_STATUS_ACTIVE_TEXT', $this->language),
							($this->language == 'de' ? 'e' : ''),
							$this->viewTitle
						);
					break;

					case (ListModel::FILTER_ARCHIVED) :
						$this->viewTitle = sprintf('%s%s %s',
							Text::translate('COM_FTK_STATUS_ARCHIVED_TEXT', $this->language),
							($this->language == 'de' ? 'e' : ''),
							$this->viewTitle
						);
					break;

					case (ListModel::FILTER_DELETED) :
						$this->viewTitle = sprintf('%s%s %s',
							Text::translate('COM_FTK_STATUS_DELETED_TEXT', $this->language),
							($this->language == 'de' ? 'e' : ''),
							$this->viewTitle
						);
					break;

					case (ListModel::FILTER_LOCKED) :
						$this->viewTitle = sprintf('%s%s %s',
							Text::translate('COM_FTK_STATUS_LOCKED_TEXT', $this->language),
							($this->language == 'de' ? 'e' : ''),
							$this->viewTitle
						);
					break;
				}
			}
		}

		return true;
	}
}
