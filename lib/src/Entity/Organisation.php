<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use Nematrack\Entity;
use Nematrack\Helper\JsonHelper;
use Nematrack\Text;
use function is_null;
use function is_string;

/**
 * Class description
 */
class Organisation extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $orgID = null;

	/**
	 * @var    string  The organisation name.
	 * @since  1.1
	 */
	protected $name = null;

	/**
	 * @var    string  The organisation address country.
	 * @since  1.1
	 */
	protected $country = null;

	/**
	 * @var    string  The organisation address city.
	 * @since  1.1
	 */
	protected $city = null;

	/**
	 * @var    string  The organisation address postal code.
	 * @since  1.1
	 */
	protected $zip = null;

	/**
	 * @var    object  The organisation address.
	 * @since  1.1
	 */
	protected $address = null;

	/**
	 * @var    string  The fully qualified homepage URL.
	 * @since  1.1
	 */
	protected $homepage = null;

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
	 * @var    Role  The organisation role.
	 * @since  1.1
	 */
	protected $role = null;

	/**
	 * @var    array  Registry instances container.
	 * @since  1.1
	 */
	protected $processes = null;

    protected $org_color = null;

    protected $org_abbr = null;

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

		return 'organisations';
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

		/* Convert entity address from JSON to Object */
		$address = $this->get('address');

//		if (!is_null($address) && !is_array($address) && !is_object($address) && (is_string($address) || empty($address)))
		if (is_string($address) && JsonHelper::isValidJSON($address))
		{
			try
			{
				$address = json_decode($address, null, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$address = [
					'addressline' => Text::translate('COM_FTK_ERROR_APPLICATION_JSON_DECODE_TEXT', $this->language)
				];
			}

			$this->address = $address;
		}
		else
		{
			if (is_null($address))
			{
				$this->address = $address;
			}
		}

		/* Convert entity processes from JSON to Object */
		$processes = $this->get('processes');

		if (is_string($processes) && JsonHelper::isValidJSON($processes))
		{
			try
			{
				$processes = json_decode($processes, null, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$processes = [];
			}

			$this->processes = $processes;
		}
		else
		{
			if (empty($processes))
			{
				$this->processes = [];
			}
		}

		/* Convert entity role from JSON to Object */
		$role = $this->get('role');

//		if (!is_null($role) && !is_array($role) && !is_object($role) && (is_string($role) || empty($role)))
		if (is_string($role) && JsonHelper::isValidJSON($role))
		{
			try
			{
				$role = (array) json_decode($role, null, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$role = [
					'abbreviation' => '',
					'name'         => Text::translate('COM_FTK_ERROR_APPLICATION_JSON_DECODE_TEXT', $this->language)
				];
			}

			$this->role = Entity::getInstance('role', [
				'id'       => ArrayHelper::getValue($role, 'roleID'),
				'language' => $this->language
			])
			->bind($role);
		}

		return $this;
	}
}
