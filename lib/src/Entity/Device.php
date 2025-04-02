<?php
/* define application namespace */
namespace Nematrack\Entity;

// No direct script access
defined('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Entity;
use Nematrack\Model;

/**
 * Class description
 */
class Device extends Entity
{
	/**
	 * @var    int|null  A unique entity id.
	 * @since  1.1
	 */
	protected ?int $devID = null;

	/**
	 * @var    int|null  The manufacturer id.
	 * @since  1.1
	 */
	protected ?int $manID = null;

	/**
	 * @var    int|null  The organisation id.
	 * @since  1.1
	 */
	protected ?int $orgID = null;

	/**
	 * @var    string|null  The device category index (d/m/t).
	 * @since  1.1
	 */
	protected ?string $category = null;

	/**
	 * @var    string|null  The device name / model number.
	 * @since  1.1
	 */
	protected ?string $model = null;

	/**
	 * @var    string|null  A European Article Number.
	 * @see    https://www.weber-marking.com/blog/ean-code-is-not-ean/
	 * @since  1.1
	 */
	protected ?string $EAN = null;

	/**
	 * @var    string|null  The serial number.
	 * @since  1.1
	 */
	protected ?string $serialNumber = null;

	/**
	 * @var    int|null  Flag that indicates whether this row is blocked.
	 * @since  2.10
	 */
	protected ?int $blocked = null;

	/**
	 * @var    DateTime|null  Date and time when this row was blocked.
	 * @since  2.10
	 */
	protected ?DateTime $blockDate = null;

	/**
	 * @var    int|null  The id of the blocker of this row.
	 * @since  2.10
	 */
	protected ?int $blocked_by = null;

	/**
	 * @var    int|null  Flag that indicates whether this row is archived.
	 * @since  2.10
	 */
	protected ?int $archived = null;

	/**
	 * @var    DateTime|null  Date and time when this row was archived.
	 * @since  2.10
	 */
	protected ?DateTime $archiveDate = null;

	/**
	 * @var    int|null  The id of the archiver of this row.
	 * @since  2.10
	 */
	protected ?int $archived_by = null;

	/**
	 * @var    int|null  Flag that indicates whether this row is marked as deleted.
	 * @since  2.10
	 */
	protected ?int $trashed = null;

	/**
	 * @var    DateTime|null  Date and time when this row was trashed.
	 * @since  2.10
	 */
	protected ?DateTime $trashDate = null;

	/**
	 * @var    int|null  The id of the trasher of this row.
	 * @since  2.10
	 */
	protected ?int $trashed_by = null;

	/**
	 * @var    DateTime|null  Date and time when this row was created.
	 * @since  2.10
	 */
	protected ?DateTime $created = null;

	/**
	 * @var    int|null  The id of the creator of this row.
	 * @since  2.10
	 */
	protected ?int $created_by = null;

	/**
	 * @var    DateTime|null  Date and time when this row was last edited.
	 * @since  2.10
	 */
	protected ?DateTime $modified = null;

	/**
	 * @var    int|null  The id of the last editor of this row.
	 * @since  2.10
	 */
	protected ?int $modified_by = null;

	/**
	 * @var    DateTime|null  Date and time when this row was marked as deleted.
	 * @since  2.10
	 */
	protected ?DateTime $deleted = null;

	/**
	 * @var    int|null  The id of the last editor of this row.
	 * @since  2.10
	 */
	protected ?int $deleted_by = null;


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
	 * @var    string|null  The entity kind name.
	 * @since  1.1
	 */
	protected $type = null;

	/**
	 * @var    string|null  The entity's manufacturer name and type as name.
	 * @since  1.1
	 */
	protected $name = null;

	/**
	 * @var    string|null  The item annotation.
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
	 * @see Entity::__construct
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		$this->categoryMap = [
			'a' => ['tag' => 'a', 'name' => 'COM_FTK_LIST_OPTION_ACCESSORY_TEXT'],  // (a)ccessory
			// 'd' => ['tag' => 'd', 'name' => 'COM_FTK_LIST_OPTION_DEVICE_TEXT'],     // (d)evice
			'm' => ['tag' => 'm', 'name' => 'COM_FTK_LIST_OPTION_MACHINE_TEXT'],    // (m)achine
			't' => ['tag' => 't', 'name' => 'COM_FTK_LIST_OPTION_TOOL_TEXT']        // (t)ool
		];
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::bind
	 */
	public function bind(array $data = []): Entity
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		// Convert location ID into organisation object.
		$this->location = Model::getInstance('organisation', ['language' => $this->language])->getItem(ArrayHelper::getValue($data, 'orgID', 0, 'INT'));

		return $this;
	}

	/**
	 * Returns the name of a category identified by tag.
	 *
	 * @param string $tag  The identifier of the desired category.
	 *
	 * @return  string|null
	 */
	public function getCategoryByTag(string $tag): ?string
	{
		$category = ArrayHelper::getValue($this->categoryMap, $tag);

		return is_array($category) ? ArrayHelper::getValue($category, 'name') : $category;
	}

	/**
	 * Returns the category map.
	 *
	 * @return  array|string[][]
	 */
	public function getCategoryMap(): array
	{
		return $this->categoryMap;
	}
}
