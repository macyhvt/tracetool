<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Exception;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use Nematrack\Entity;
use Nematrack\Factory;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Helper\JsonHelper;
use function is_null;
use function is_string;

/**
 * Class description
 */
class Project extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $proID = null;

	/**
	 * @var    string  The project number.
	 * @since  1.1
	 */
	protected $number = null;

	/**
	 * @var    string  The project name.
	 * @since  1.1
	 */
	protected $name = null;

	/**
	 * @var    string  The project customer(s).
	 * @since  1.1
	 */
	protected $customer = null;

	/**
	 * @var    string  The total parts ordered.
	 * @since  2.8
	 */
	protected $order = null;

	/**
	 * @var    string  The project status.
	 * @since  2.6
	 */
	protected $status = null;

	/**
	 * @var    string  The project configuration in JSON format.
	 * @since  2.6
	 */
	protected $config = null;

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
	 * @var    array  The project members.
	 * @since  1.1
	 */
	protected $organisations = null;

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
	 * @var    DateTime  Date and time when this row was marked as deleted.
	 * @since  1.1
	 */
	protected $deleted = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $deleted_by = null;

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

		return 'projects';
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

		/* Convert entity config from JSON to Object */
		$config = $this->get('config');

//		if (!is_null($config) && !is_array($config) && !is_object($config) && (is_string($config) || empty($config)))
		if (is_string($config) && JsonHelper::isValidJSON($config))
		{
			try
			{
				$config = json_decode($config, null, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$config = [];
			}

			$this->config = $config;
		}
		else
		{
			if (is_null($config))
			{
				$this->config = $config;
			}
		}

		return $this;
	}

	public function getMembers() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Get required entities.
		$orgEntity  = Entity::getInstance('organisation', ['language' => $this->language]);
		$roleEntity = Entity::getInstance('role', ['language' => $this->language]);

		// Build sub-query first.
		$sub = $db->getQuery(true)
		->select($db->qn('po.' . $orgEntity->getPrimaryKeyName()))
		->from($db->qn('project_organisation') . ' AS ' . $db->qn('po'))
		->where($db->qn('po.'  . $this->getPrimaryKeyName()) . ' = ' . $this->get($this->getPrimaryKeyName()));

		// Build query.
		$query = $db->getQuery(true)
		->select([
			$db->qn('o.'  . $orgEntity->getPrimaryKeyName()),
			$db->qn('o.blocked'),
			$db->qn('o.name'),
			$db->qn('or.' . $roleEntity->getPrimaryKeyName()),
			"CONCAT('{',
				'\"{$roleEntity->getPrimaryKeyName()}}\":\"', `r`.`{$roleEntity->getPrimaryKeyName()}`, 
				'\",\"abbreviation\":\"', `r`.`abbreviation`, 
				'\",\"name\":\"', `r`.`name`, 
			'\"}') AS " . $db->qn('role'),
			$db->qn('o.created'),
			$db->qn('o.created_by'),
			$db->qn('o.modified'),
			$db->qn('o.modified_by')
		])
		->from($db->qn('organisations') . ' AS ' . $db->qn('o'))
		->join('LEFT', $db->qn('organisation_meta') . ' AS ' . $db->qn('om') . ' ON ' . $db->qn('o.orgID')  . ' = ' . $db->qn('om.' . $orgEntity->getPrimaryKeyName()))
		->join('LEFT', $db->qn('organisation_role') . ' AS ' . $db->qn('or') . ' ON ' . $db->qn('o.orgID')  . ' = ' . $db->qn('or.' . $orgEntity->getPrimaryKeyName()))
		->join('LEFT', $db->qn('roles')             . ' AS ' . $db->qn('r')  . ' ON ' . $db->qn('r.roleID') . ' = ' . $db->qn('or.' . $roleEntity->getPrimaryKeyName()))
		->where($db->qn('o.orgID')  . ' IN (' . $sub . ')');

		$query
		->group($db->qn('o.' . $orgEntity->getPrimaryKeyName()))
		->order($db->qn('o.name'));

		// Execute query.
		try
		{
			$rows = [];

//			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($db->setQuery($query)->loadAssocList() as $row)
			{
				// FIXME - get rid of loading row data into entity. Just collect object and load on demand where it is required.
				// Load row data into a new entity and add it to the collection.
				$rows[$row[$orgEntity->getPrimaryKeyName()]] = Entity::getInstance('organisation', ['id' => $row['orgID'], 'language' => $this->language])->bind( $row );
			}
		}
		catch (Exception $e)
		{
			$rows = null;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $rows;
	}

	public function getMember(int $id) : Entity\Organisation
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return ArrayHelper::getValue(
			$this->getMembers(),
			$id,
			Entity::getInstance('organisation', ['language' => $this->language])
		);
	}
}
