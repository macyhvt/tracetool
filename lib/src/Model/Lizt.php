<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Registry\Registry;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Messager;
use Nematrack\Model;
use Nematrack\Text;
use function array_map;
use function is_a;

/**
 * Class description
 */
abstract class Lizt extends Model
{
	/**
	 * A list filter identifier.
	 *
	 * @var    int
	 * @since  2.8
	 */
	public const FILTER_ALL      =  3;

	public const FILTER_NEMA      =  111;

    public const FILTER_FRO      =  112;

    public const FILTER_NEMEC      =  113;
	
	/**
	 * A list filter identifier.
	 *
	 * @var    int
	 * @since  2.8
	 */
	public const FILTER_ARCHIVED =  2;

	/**
	 * A list filter identifier.
	 *
	 * @var    int
	 * @since  2.8
	 */
	public const FILTER_LOCKED   =  1;

	/**
	 * A list filter identifier.
	 *
	 * @var    int
	 * @since  2.8
	 */
	public const FILTER_ACTIVE   =  0;

	/**
	 * A list filter identifier.
	 *
	 * @var    int
	 * @since  2.8
	 */
	public const FILTER_DELETED  = -1;

	/**
	 * A list filter identifier.
	 *
	 * @var    int
	 * @since  2.10
	 */
	public const FILTER_EMPTY    = 4;

	/**
	 * @var    string
	 */
	protected $tableName = '';

	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * Fetch a list of items.
	 *
	 * Must be implemented by the child class.
	 *
	 * @return  array
	 */
	abstract public function getList();

	/**
	 * Fetches last primary key value from given table.
	 *
	 * @return  int  This ordering value.
	 *
	 * @since   1.1
	 */
	public function getLastInsertID() : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__, true) . '</pre>' : null;

		$id = 0;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$entity = Entity::getInstance($this->get('tableName'), ['language' => $this->language]);

		if (!is_a($entity, 'Nematrack\Entity'))
		{
			return $id;
		}

		$tableName = $entity->getTableName();
		$pk        = $entity->getPrimaryKeyName();

		// Build query.
		$query = $db->getQuery(true)
		->select('MAX( DISTINCT(' . $db->qn($pk). ') ) AS ' . $db->qn($pk))
		->from($db->qn($tableName));

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObject();
			$rs = new Registry($rs);

			$id = $rs->get($pk);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$id = -1;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return (int) $id;
	}

	/**
	 * Blocks/Unblocks items identified by passed in IDs.
	 *
	 * @param   array  $proIDs  A list of the items to manipulate.
	 * @param   int    $state   The state to set. (0 = unblocked, 1 = blocked)
	 *
	 * @return  array  A list of the manipulated item ids.
	 *
	 * @since   1.9
	 */
	public function setState(array $proIDs = [], $state = 0) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '(' . json_encode($proIDs) . ', ' . $state . ')', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$entity = Entity::getInstance($this->get('tableName'), ['language' => $this->language]);

		if (!is_a($entity, 'Nematrack\Entity'))
		{
			return $proIDs;
		}

		$tableName = $entity->getTableName();
		$pk        = $entity->getPrimaryKeyName();

		// Sanitize input data.
		$proIDs = array_map('intval', $proIDs);
		$state  = (int) $state;

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn($tableName))
		->where($db->qn($pk) . ' IN( ' . implode(',', $proIDs) . ' )')
		->set($db->qn('blocked') . ' = ' . $db->q((int) $state));

		// Execute query.
		try
		{
			if (count($proIDs))
			{
				$db
				->setQuery($query)
				->execute();
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return $proIDs;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $proIDs;
	}

	/**
	 * Fetches last ordering value from given table.
	 *
	 * @return  int  This primary key value.
	 *
	 * @since   1.1
	 */
	private function ___getLastItemOrder___OBSOLETE___() : int	// DiSABLED on 2023-09-12 - because its not used anywhere + no db table has a column 'ordering'
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__, true) . '</pre>' : null;

		$ordering = null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select('MAX(' . $db->qn('ordering') . ')')
		->from($db->qn($this->get('tableName')));

		// Execute query.
		try
		{
			$ordering = (int) $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$ordering = -1;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $ordering;
	}

	/**
	 * Add description...
	 *
	 * @return bool
	 */
	protected function userCanDelete() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__, true) . '</pre>' : null;

		return (parent::userCanDelete() && App::getAppUser()->getFlags() > \Nematrack\Access\User::ROLE_MANAGER);
	}
}
