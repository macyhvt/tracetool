<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Entity;
use Nematrack\Model\Techparams;
use function array_walk;

/**
 * Class description
 */
class Part extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $partID = null;

	/**
	 * @var    integer  The parent article id.
	 * @since  1.1
	 */
	protected $artID = null;

	/**
	 * @var    integer  Flag indicating whether this item is a sample.
	 * @since  2.8
	 */
	protected $sample = null;

	/**
	 * @var    string  The unique tracking code.
	 * @since  1.1
	 */
	protected $trackingcode = null;

	/**
	 * @var    integer  The id of the lot this part potentially belongs to.
	 * @since  1.1
	 */
	protected $lotID = null;

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
	 * @var    string  The name of the user that blocked this row.
	 * @since  1.1
	 */
	protected $blocked_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is archived.
	 * @since  1.1
	 */
	protected $archived = null;

	/**
	 * @var    DateTime  Date and time when this row was archived.
	 * @since  1.1
	 */
	protected $archiveDate = null;

	/**
	 * @var    string  The name of the user that archived this row.
	 * @since  1.1
	 */
	protected $archived_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is marked as deleted.
	 * @since  1.4
	 */
	protected $trashed = null;

	/**
	 * @var    string  The name of the user that trashed this row.
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
	 * @var    string  The article number.
	 * @since  1.1
	 */
	protected $type = null;

	/**
	 * @var    integer  The lot number.
	 * @since  1.1
	 */
	protected $lot = null;

	/**
	 * @var    array  Registry instances container.
	 * @since  1.1
	 */
	protected $processes = null;

	/**
	 * @var    array  Object container.
	 * @since  1.1
	 */
	protected $trackingData = null;

	/**
	 * @var    array  Object container.
	 * @since  2.5
	 */
	protected $measuredData = null;

	/**
	 * @var   array  Media files that belong to this specific item
	 * @since 2.8
	 */
	protected array $mediaFiles = [];

	/**
	 * @var    array  List of objects where this part is a component of
	 * @since  2.8
	 */
	protected array $isComponentOf = [];

	/**
	 * @var    bool  Flag indicating whether this part is a bad part
	 * @since  2.2
	 */
	protected bool $isBad = false;


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

		return 'parts';
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

		// TODO
		// Check tracking data of this part for error status > 0,
		// which indicates this part is a 'bad part' and set is property 'isBad' accordingly.
		/*$measuredData = $this->get('measuredData', []);

		array_walk($measuredData, function($entry)
		{
			// TODO
		});

		// Free memory.
		unset($measuredData);*/

		// Check tracking data of this part for error status > 0,
		// which indicates this part is a 'bad part' and set is property 'isBad' accordingly.
		$trackingData = $this->get('trackingData', []);

		// TODO - sort trackingData equally sorted to item processes.

		array_walk($trackingData, function($entry)
		{
			if (true === $this->isBad)
			{
				return;
			}

			$this->isBad = ArrayHelper::getValue((array) $entry, Techparams::STATIC_TECHPARAM_ERROR, 'INT') > 0;
		});

		// Free memory.
		unset($trackingData);

		// TODO - decide whether untracked process IDs (pids) should be separated from tracked ones and previous
		//		 as well as next process ID should be preloaded (code is available in item.php and edit.php)
		//       for pagination-like navigation (prev item ... next item).

		return $this;
	}

	/**
	 * Detects whether this item is a bad part.
	 *
	 * @return  bool
	 */
	public function isBad() : bool
	{
		return $this->isBad;
	}

	/**
	 * Detects whether this item is a subcomponent of another item.
	 *
	 * @return  bool
	 */
	public function isComponent() : bool
	{
		return count($this->isComponentOf) > 0;
	}
}
