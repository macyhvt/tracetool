<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use JsonException;
use Nematrack\Entity;
use Nematrack\Helper\JsonHelper;
use function is_null;
use function is_string;

/**
 * Under one lot, in logistics also batch, in production also series, in process engineering also batch,
 * in the field of medical products and in pharmaceutical law also lot or lot, is understood the totality
 * of the units, which are produced in a batch process or a lot-production.
 *
 * This class represents a lot item.
 */
class Lot extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $lotID = null;

	/**
	 * @var    string  An alphanumeric string (max. 250 chars) that identifies a lot.
	 * @since  1.1
	 */
	protected $number = null;

	/**
	 * @var    integer  The article id.
	 * @since  1.1
	 */
	protected $artID = null;

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
	 * @var    string  The article name.
	 * @since  1.1
	 */
	protected $type = null;

	/**
	 * @var    array  The part id's container.
	 * @since  1.1
	 */
	protected $parts = null;


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

		return 'lots';
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

		/* Convert entity parts from JSON to Array */
		$parts = $this->get('parts');

//		if (!is_null($parts) && !is_array($parts) && !is_object($parts) && (is_string($parts) || empty($parts)))
		if (is_string($parts) && JsonHelper::isValidJSON($parts))
		{
			try
			{
				$parts = (array) json_decode($parts, null, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$parts = [];
			}

			$this->parts = $parts;
		}
		else
		{
			if (is_null($parts))
			{
				$this->parts = [];
			}
		}

		return $this;
	}

	/**
	 * Getter for property $parts.
	 *
	 * @return array
	 */
	public function getParts() : array
	{
		return $this->parts ?? [];
	}
}
