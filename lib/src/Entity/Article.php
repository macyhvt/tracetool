<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Joomla\Registry\Registry;
use Nematrack\Entity;
use Nematrack\Helper\JsonHelper;
use Nematrack\Model;
use function is_string;

/**
 * Class description
 */
class Article extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $artID = null;

	/**
	 * @var    string  The article number.
	 * @since  2.6
	 */
	protected $number = null;

	/**
	 * @var    string  The customer article numer.
	 * @since  1.6
	 */
	protected $custartno = null;

	/**
	 * @var    string  The article name.
	 * @since  1.1
	 */
	protected $name = null;

	/**
	 * @var    string  The customer article name.
	 * @since  2.6
	 */
	protected $custartname = null;

	/**
	 * @var    array  Registry instances container.
	 * @since  1.1
	 */
	protected $processes = null;

	/**
	 * @var    string  The article drawing object in JSON-format.
	 *                 The object must contain the following details:
	 *                      [file]   => /relative/path/to/file.pdf
	 *                      [number] => AAA.BBB.CC.CCCCC.000
	 *                      [index]  => 0
	 *                      [hash]   => 9317814fdebb2f34d3557af04aa58d77
	 *                      [images] => Array(
	 *                          [0] => /relative/path/to/file__thumb.png
	 *                      )
	 * @since  1.1
	 */
	protected $drawing = null;

	/**
	 * @var    string  The drawing index.
	 * @since  1.1
	 */
	protected $drawingindex = null;

	/**
	 * @var    string  The customer article drawing object in JSON-format.
	 *                 The object must contain the following details:
	 *                      [file]   => /relative/path/to/file.pdf
	 *                      [custartno] => 1234-5678
	 *                      [index]  => 
	 *                      [hash]   => 9317814fdebb2f34d3557af04aa58d77
	 *                      [images] => Array(
	 *                          [0] => /relative/path/to/file__thumb.png
	 *                      )
	 * @since  2.10.1
	 */
	protected $customerDrawing = null;

	/**
	 * @var    DateTime  The row creation date and time.
	 * @since  1.1
	 */
	protected $created = null;

	/**
	 * @var    string  The name of the creator of this row.
	 * @since  1.1
	 */
	protected $created_by = null;

	/**
	 * @var    DateTime  Date and time when this row was last edited.
	 * @since  1.1
	 */
	protected $modified = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $modified_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is blocked.
	 * @since  1.1
	 */
	protected $blocked = null;

	/**
	 * @var    DateTime  Date and time when this row was blocked.
	 * @since  1.1
	 */
	protected $blockDate = null;

	/**
	 * @var    string  The name of the blocker of this row.
	 * @since  1.1
	 */
	protected $blocked_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as archived.
	 * @since  1.1
	 */
	protected $archived = null;

	/**
	 * @var    DateTime  Date and time when this row was archived.
	 * @since  1.1
	 */
	protected $archiveDate = null;

	/**
	 * @var    string  The name of the archivator of this row.
	 * @since  1.1
	 */
	protected $archived_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is marked as deleted.
	 * @since  1.4
	 */
	protected $trashed = null;

	/**
	 * @var    DateTime  Date and time when this row was trashed.
	 * @since  1.1
	 */
	protected $trashDate = null;

	/**
	 * @var    string  The name of the trasher of this row.
	 * @since  1.1
	 */
	protected $trashed_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as deleted.
	 * @since  1.1
	 */
	protected $deleted = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $deleted_by = null;


	/** Properties that are no database table columns */


	/**
	 * @var    integer  The project id.
	 * @since  1.1
	 */
	protected $proID = null;

	/**
	 * @var    string  The project name.
	 * @since  1.1
	 */
	protected $project = null;

	/**
	 * @var    string  The item description.
	 * @since  1.1
	 * @deprecated Deprecated in version 2.11
	 */
	protected $description = null;

	/**
	 * @var    string  The item annotation.
	 * @since  1.1
	 */
	protected $annotation = null;


	/**
	 * {@inheritdoc}
	 * @see Entity::__construct
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::getTableName
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return 'articles';
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::bind
	 */
	public function bind(array $data = []) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		/* Convert entity drawing from JSON to Object */
		// Load drawing data into a {@link \Joomla\Registry\Registry} object for consistent data access.
		$drawing = $this->get('drawing');

		if (is_string($drawing) && JsonHelper::isValidJSON($drawing))
		{
			$drawing = json_decode($drawing, null, 512, JSON_THROW_ON_ERROR);
			$drawing = new Registry($drawing);
		}

		$this->drawing = $drawing;

		/* Convert entity customerDrawing from JSON to Object */
		// Load customerDrawing data into a {@link \Joomla\Registry\Registry} object for consistent data access.
		$customerDrawing = $this->get('customerDrawing');

		if (is_string($customerDrawing) && JsonHelper::isValidJSON($customerDrawing))
		{
			$customerDrawing = json_decode($customerDrawing, null, 512, JSON_THROW_ON_ERROR);
			$customerDrawing = new Registry($customerDrawing);
		}

		$this->customerDrawing = $customerDrawing;

		/* Fetch entity processes from JSON to Object */
		// FIXME - move loading data outta here. This object should only receive data and prepare it.
		$this->processes = (array) Model::getInstance('article', ['language' => $this->language])->getArticleProcesses(
			$this->get(
				$this->getPrimaryKeyName()
			)
		);

		return $this;
	}
}
