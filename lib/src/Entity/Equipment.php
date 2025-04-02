<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Entity;
use Nematrack\Model;

/**
 * Class description
 */
class Equipment extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $eqpID = null;

	/**
	 * @var    integer  The manufacturer id.
	 * @since  1.1
	 */
	protected $manID = null;

	/**
	 * @var    integer  The organisation id.
	 * @since  1.1
	 */
	protected $orgID = null;

	/**
	 * @var    string  The device category index (d/m/t).
	 * @since  1.1
	 */
	protected $category = null;

	/**
	 * @var    string  The device name / model number.
	 * @since  1.1
	 */
	protected $model = null;

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
	 * @var    Entity  The item's current usage location.
	 * @since  1.1
	 */
	protected $location = null;

	/**
	 * @var    Entity  The item manufacturer.
	 * @since  1.1
	 */
	protected $manufacturer = null;

	/**
	 * @var    string  The entity kind name.
	 * @since  1.1
	 */
	protected $type = null;

	/**
	 * @var    string  The item annotation.
	 * @since  1.1
	 */
	protected $annotation = null;

	/**
	 * @var    array  A list of entity categories 1:1 mapped to an individual letter
	 * @since  1.1
	 */
	private $categoryMap;


	/**
	 * {@inheritdoc}
	 * @see \Nematrack\Entity::__construct()
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		$this->categoryMap = [
			'a' => ['tag' => 'a', 'name' => 'COM_FTK_LIST_OPTION_ACCESSORY_TEXT'],  // (a)ccessory
			'd' => ['tag' => 'd', 'name' => 'COM_FTK_LIST_OPTION_DEVICE_TEXT'],     // (d)evice
			'm' => ['tag' => 'm', 'name' => 'COM_FTK_LIST_OPTION_MACHINE_TEXT'],    // (m)achine
			't' => ['tag' => 't', 'name' => 'COM_FTK_LIST_OPTION_TOOL_TEXT']        // (t)ool
		];
	}

	/**
	 * {@inheritdoc}
	 * @see \Nematrack\Entity::getTableName()
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return 'devices';
	}

	/**
	 * {@inheritdoc}
	 * @see \Nematrack\Entity::bind()
	 */
	public function bind(array $data = []) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		// Convert location ID into organisation object.
		$this->location = Model::getInstance('organisation', ['language' => $this->language])->getItem(ArrayHelper::getValue($data, 'orgID', 0, 'INT'));

		return $this;
	}

	/**
	 * Returns the category map.
	 *
	 * @return  array|\string[][]
	 */
	public function getCategoryMap() : array
	{
		return $this->categoryMap;
	}

	/**
	 * Returns the name of a category identified by tag.
	 *
	 * @param   string $tag  The identifier of the desired category.
	 *
	 * @return  string|null
	 */
	public function getCategoryByTag(string $tag) : ?string
	{
		$category = ArrayHelper::getValue($this->categoryMap, $tag);

		return is_array($category) ? ArrayHelper::getValue($category, 'name') : $category;
	}
}
