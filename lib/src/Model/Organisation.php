<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Messager;
use Nematrack\Model\Item as ItemModel;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_walk;
use function is_a;
use function is_array;
use function is_int;
use function is_null;
use function is_object;
use function is_string;
use function property_exists;

/**
 * Class description
 */
class Organisation extends ItemModel
{
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
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $itemID
	 *
	 * @return  \Nematrack\Entity\Organisation
	 */
	public function getItem(int $itemID) : Entity\Organisation
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('organisations', ['language' => $this->language])->getList(
					[
						'orgID' => $itemID
					]
				),
				$itemID
			);
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf('Nematrack\Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf('Nematrack\Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,   'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('orgID')) {}

		return $row;
	}

	public function getItemMeta(int $orgID, string $lang, bool $isNotNull = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->hasOrganisationMeta($orgID, $lang, $isNotNull, true);
	}

	// Proxy-method
	public function getAdmins() : array
	{
		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		return $this->getAdministrators($args);
	}

	public function getAdministrators() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$orgID  = ArrayHelper::getValue($args, 'orgID');
		$orgID  = (is_null($orgID))  ? null : (int) $orgID;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery(/** @lang MySQL */'SET NAMES utf8')->execute();
		$db->setQuery(/** @lang MySQL */'SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		$table = 'usergroups';
		$sub   = $db->getQuery(true)
		->from($db->qn($table))
		->select($db->qn('groupID'))
		->where($db->qn('name') . ' LIKE ' . $db->q('%GROUP_ADMINISTRATOR%'));

		// Build query.
		$table = 'user_usergroup';
		$query = $db->getQuery(true)
		->from($db->qn($table, 'uug'))
		->join('LEFT', $db->qn('organisation_user') . '  AS ' . $db->qn('ou') . ' ON ' . $db->qn('uug.userID')  . ' = ' . $db->qn('ou.userID'))
		->join('LEFT', $db->qn('organisations')     . '  AS ' . $db->qn('o')  . ' ON ' . $db->qn('ou.orgID')    . ' = ' . $db->qn('o.orgID'))
		->join('LEFT', $db->qn('usergroups')        . '  AS ' . $db->qn('ug') . ' ON ' . $db->qn('uug.groupID') . ' = ' . $db->qn('ug.groupID'))
		->join('LEFT', $db->qn('users')             . '  AS ' . $db->qn('u')  . ' ON ' . $db->qn('uug.userID')  . ' = ' . $db->qn('u.userID'))
		->select($orgID . ' AS ' . $db->qn('orgID'))
		->select($db->qn('o.name') . ' AS ' . $db->qn('organisation'))
		->select($db->qn('uug.userID'))
		->select($db->qn('u.fullname'))
		->select($db->qn('u.email'))
		->select($db->qn('u.blocked'))
		->select($db->qn('uug.groupID'))
		->select($db->qn('ug.name') . ' AS ' . $db->qn('group'));

		// Limit results to passed item ID.
		if (!is_null($orgID))
		{
			$query
			->where($db->qn('ou.orgID') . ' = ' . $orgID);
		}

		// Apply filter.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('0'))
				->where($db->qn('u.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			/*case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;*/

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('u.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('u.trashed')  . ' = ' . $db->q('1'));
			break;

			/*case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
//				->where($db->qn('u.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break;*/

			/*default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
//				->where($db->qn('u.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
		}

		$query
		->where($db->qn('uug.groupID') . ' = (' . $sub . ')');

		// Hide these users (required for automated testing, debugging, etc.) from users list
		// if users list is requested by non-developers and not Tino!
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@nematrack.com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}

		// Add ordering.
		$query
		->order(
			$db->qn([
				'o.name',
				'u.fullname'
			])
		);

		// Execute query.
		try
		{
			$pkName = Entity::getInstance('user')->getPrimaryKeyName();
			$rows   = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Translate language name.
				if (array_key_exists('group', $row))
				{
					$row['group'] = (str_starts_with($row['group'], 'COM_FTK_GROUP_')
						? Text::translate(ArrayHelper::getValue($row, 'group', null, 'STRING'), $this->language)
						: $row['group']);
				}

//				$rows[$row[$pkName]] = $row;
				$rows[$row['email']] = $row;
			}
		}
		catch (Exception $e)
		{
			$rows = [];

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	public function getOrganisationByName(string $organisationName) : Entity\Organisation
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select('orgID')
		->from($db->qn('organisations'))
		->where('LOWER(' . $db->qn('name') . ') = LOWER( TRIM(' . $db->q(trim($organisationName)) . ') )');

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadResult();

			$organisation = $this->getItem((int) $rs);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = null;
		}
		
		// Close connection.
		$this->closeDatabaseConnection();

		return $organisation;
	}

	public function getOrganisationProcesses(int $orgID, $procID = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$orgID  = (is_null($orgID)  ? $orgID  : $orgID);
		$procID = (is_null($procID) ? $procID : (int) $procID);

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('process_organisation'))
		->select(
			$db->qn([
				'orgID',
				'procID'
			])
		)
		->order(
			$db->qn([
				'orgID',
				'procID'
			])
		);

		if (!is_null($orgID))
		{
			$query->where($db->qn('orgID') . ' = ' . $orgID);
		}

		if (!is_null($procID))
		{
			$query->where($db->qn('procID') . ' = ' . (int) $procID);
		}

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadObjectList();

			foreach ($rs as $row)
			{
				if (property_exists($row, 'drawing'))
				{
					$row->drawing = (is_string($row->drawing)
						? json_decode($row->drawing, null, 512, JSON_THROW_ON_ERROR)
						: $row->drawing);
				}

				// FIXME - Don't preload data into Registry or Entity objects, just send them as array or object.
				// Load process data into a {@see \Joomla\Registry\Registry} object for consistent data access.
				$rows[$row->procID] = new Registry($row);
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	private function getOrganisationProjects__OBSOLETE(int $orgID, $proID = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('project_organisation', 'po'))
		->join('INNER', $db->qn('projects')     . ' AS ' . $db->qn('p')  . ' ON ' . $db->qn('po.proID')  . ' = ' . $db->qn('p.proID'))
		->join('INNER', $db->qn('project_meta') . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('po.proID')  . ' = ' . $db->qn('pm.proID'))
		->join('INNER', $db->qn('roles')        . ' AS ' . $db->qn('r')  . ' ON ' . $db->qn('po.roleID') . ' = ' . $db->qn('r.roleID'))
		->select([
			$db->qn('po.proID'),
			$db->qn('p.number'),
			$db->qn('p.name'),
			$db->qn('pm.description'),
			$db->qn('po.roleID'),
			$db->qn('r.name') . ' AS ' . $db->qn('role'),
			$db->qn('r.abbreviation'),
			$db->qn('p.blocked'),
			$db->qn('p.blockDate'),
			$db->qn('p.blocked_by'),
			$db->qn('p.archived'),
			$db->qn('p.archiveDate'),
			$db->qn('p.archived_by'),
			$db->qn('p.trashed'),
			$db->qn('p.trashDate'),
			$db->qn('p.trashed_by'),
			$db->qn('p.created'),
			$db->qn('p.created_by'),
			$db->qn('p.modified'),
			$db->qn('p.modified_by'),
			$db->qn('p.deleted'),
			$db->qn('p.deleted_by')
		])
		/*->select("
			CONCAT(
				'{',
				GROUP_CONCAT(
					CONCAT( '\"', `po`.`orgID`, '\"', ':', '\"', `r`.`abbreviation`, '\"' )
				),
				'}'
			) AS `organisations`"
		)*/
		->where($db->qn('po.orgID') . ' = ' . $orgID);

		// Limit results to selected application language.
		$query
		->where($db->qn('pm.language') . ' = ' . $db->q($this->language));

		// Only users with higher privileges must be allowed to see blocked items.
		/* if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}*/

		if (!is_null($proID))
		{
			$query
			->where($db->qn('po.proID') . ' = ' . (int) $proID);
		}

		// Add grouping and ordering.
		$query
		->order($db->qn('p.number'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				if (array_key_exists('role', $row))
				{
					$row['role'] = Text::translate(ArrayHelper::getValue($row, 'role', null, 'STRING'), $this->language);
				}

				if (array_key_exists('organisations', $row))
				{
					$row['organisations'] = (array) json_decode($row['organisations'], null, 512, JSON_THROW_ON_ERROR);

					array_walk($row['organisations'], function(&$org)
					{
						$org = Text::translate($org, $this->language);
					});
				}
				else
				{
					$row['organisations'] = [];
				}

				$rows[$row['proID']] = $row;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}
	/**
	 * Returns a list of items filtered by user access rights.
	 *
	 * @return  array
	 */
	public function getOrganisationProjectsNEW() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$proID  = ArrayHelper::getValue($args, 'proID');
		$proID  = (is_null($proID))  ? null : (int) $proID;
		$orgID  = ArrayHelper::getValue($args, 'orgID');
		$orgID  = (is_null($orgID))  ? null : (int) $orgID;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('project_organisation', 'po'))
		->join('INNER', $db->qn('projects')     . ' AS ' . $db->qn('p')  . ' ON ' . $db->qn('po.proID')  . ' = ' . $db->qn('p.proID'))
		->join('INNER', $db->qn('project_meta') . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('po.proID')  . ' = ' . $db->qn('pm.proID'))
		->join('INNER', $db->qn('roles')        . ' AS ' . $db->qn('r')  . ' ON ' . $db->qn('po.roleID') . ' = ' . $db->qn('r.roleID'))
		->select([
			$db->qn('po.proID'),
			$db->qn('p.number'),
			$db->qn('p.name'),
			$db->qn('pm.description'),
			$db->qn('po.roleID'),
			$db->qn('r.name') . ' AS ' . $db->qn('role'),
			$db->qn('r.abbreviation'),
			$db->qn('p.blocked'),
			$db->qn('p.blockDate'),
			$db->qn('p.blocked_by'),
			$db->qn('p.archived'),
			$db->qn('p.archiveDate'),
			$db->qn('p.archived_by'),
			$db->qn('p.trashed'),
			$db->qn('p.trashDate'),
			$db->qn('p.trashed_by'),
			$db->qn('p.created'),
			$db->qn('p.created_by'),
			$db->qn('p.modified'),
			$db->qn('p.modified_by'),
			$db->qn('p.deleted'),
			$db->qn('p.deleted_by')
		])
		/*->select("
			CONCAT(
				'{',
				GROUP_CONCAT(
					CONCAT( '\"', `po`.`orgID`, '\"', ':', '\"', `r`.`abbreviation`, '\"' )
				),
				'}'
			) AS `organisations`"
		)*/
		->where($db->qn('po.orgID') . ' = ' . (int) $orgID);

		// Limit results to selected application language.
		$query
		->where($db->qn('pm.language') . ' = ' . $db->q($this->language));

		// Only users with higher privileges must be allowed to see blocked items.
		/* if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}*/

		if (!is_null($proID))
		{
			$query
			->where($db->qn('po.proID') . ' = ' . $proID);
		}

		// Apply filter.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
				->where($db->qn('p.archived') . ' = ' . $db->q('0'))
				->where($db->qn('p.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
			break;

			/* case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
				->where($db->qn('p.archived') . ' = ' . $db->q('1'))
				->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
			break; */

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('p.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('p.trashed')  . ' = ' . $db->q('1'));
			break;

			/* case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
				->where($db->qn('p.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('p.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('p.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break; */

			/* default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
				->where($db->qn('p.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('p.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('p.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')'); */
		}

		// Add grouping and ordering.
		$query
		->order($db->qn('p.number'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				if (array_key_exists('role', $row))
				{
					$row['role'] = Text::translate(ArrayHelper::getValue($row, 'role', null, 'STRING'), $this->language);
				}

				if (array_key_exists('organisations', $row))
				{
					$row['organisations'] = (array) json_decode($row['organisations'], null, 512, JSON_THROW_ON_ERROR);

					array_walk($row['organisations'], function(&$org)
					{
						$org = Text::translate($org, $this->language);
					});
				}
				else
				{
					$row['organisations'] = [];
				}

				$rows[$row['proID']] = $row;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}
    public function getOrganisationProjectsNEW2() : array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Get additional function args.
        $args = func_get_args();
        $args = (array) array_shift($args);

        // There may be arguments for this function.
        $filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
        $proID  = ArrayHelper::getValue($args, 'proID');
        $proID  = (is_null($proID))  ? null : (int) $proID;
        $orgID  = ArrayHelper::getValue($args, 'orgID');
        $orgID  = (is_null($orgID))  ? null : (int) $orgID;

        // Init shorthand to database object.
        $db = $this->db;

        /* Force UTF-8 encoding for proper display of german Umlaute
         * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
         */
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        /* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
         * It's limited only by the unsigned word length of the platform, which is:
         *    2^32-1 (2.147.483.648) on a 32-bit platform and
         *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
         */
        $db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

        // Build query.
        $query = $db->getQuery(true)
            ->from($db->qn('project_organisation', 'po'))
            ->join('INNER', $db->qn('projects')     . ' AS ' . $db->qn('p')  . ' ON ' . $db->qn('po.proID')  . ' = ' . $db->qn('p.proID'))
            ->join('INNER', $db->qn('project_meta') . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('po.proID')  . ' = ' . $db->qn('pm.proID'))
            ->join('INNER', $db->qn('roles')        . ' AS ' . $db->qn('r')  . ' ON ' . $db->qn('po.roleID') . ' = ' . $db->qn('r.roleID'))
            ->select([
                $db->qn('po.proID'),
                $db->qn('p.number'),
                //$db->qn('p.name'),
                //$db->qn('pm.description'),
                $db->qn('po.roleID'),
               // $db->qn('r.name') . ' AS ' . $db->qn('role'),
                //$db->qn('r.abbreviation'),

            ])
            /*->select("
                CONCAT(
                    '{',
                    GROUP_CONCAT(
                        CONCAT( '\"', `po`.`orgID`, '\"', ':', '\"', `r`.`abbreviation`, '\"' )
                    ),
                    '}'
                ) AS `organisations`"
            )*/
            ->where($db->qn('po.orgID') . ' = ' . (int) $orgID);

        // Limit results to selected application language.
        $query
            ->where($db->qn('pm.language') . ' = ' . $db->q($this->language));

        // Only users with higher privileges must be allowed to see blocked items.
        /* if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
        {
            $query
            ->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
        }*/

        if (!is_null($proID))
        {
            $query
                ->where($db->qn('po.proID') . ' = ' . $proID);
        }

        // Apply filter.
        switch (true)
        {
            case ($filter == Lizt::FILTER_ACTIVE) :
                $query
                    ->where($db->qn('p.archived') . ' = ' . $db->q('0'))
                    ->where($db->qn('p.blocked')  . ' = ' . $db->q('0'))
                    ->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
                break;

            /* case ($filter == Lizt::FILTER_ARCHIVED) :
                $query
                ->where($db->qn('p.archived') . ' = ' . $db->q('1'))
                ->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
            break; */

            case ($filter == Lizt::FILTER_LOCKED) :
                $query
                    ->where($db->qn('p.blocked')  . ' = ' . $db->q('1'))
                    ->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
                break;

            case ($filter == Lizt::FILTER_DELETED) :
                $query
                    ->where($db->qn('p.trashed')  . ' = ' . $db->q('1'));
                break;

            /* case ($filter == Lizt::FILTER_ALL) :
            default :
                $states = ['0','1'];

                $query
                ->where($db->qn('p.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
                ->andWhere($db->qn('p.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
                ->andWhere($db->qn('p.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
                ;
            break; */

            /* default :
                $states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

                $query
                ->where($db->qn('p.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
                ->where($db->qn('p.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
                ->where($db->qn('p.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')'); */
        }

        // Add grouping and ordering.
        $query
            ->order($db->qn('p.number'));

        // Execute query.
        try
        {
            $rows = [];

            $rs = $db->setQuery($query)->loadAssocList();

            foreach ($rs as $row)
            {
                if (array_key_exists('role', $row))
                {
                    $row['role'] = Text::translate(ArrayHelper::getValue($row, 'role', null, 'STRING'), $this->language);
                }

                if (array_key_exists('organisations', $row))
                {
                    $row['organisations'] = (array) json_decode($row['organisations'], null, 512, JSON_THROW_ON_ERROR);

                    array_walk($row['organisations'], function(&$org)
                    {
                        $org = Text::translate($org, $this->language);
                    });
                }
                else
                {
                    $row['organisations'] = [];
                }

                $rows[$row['proID']] = $row;
            }
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $rows = [];
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return $rows;
    }

	// Proxy-method
	public function getOrganisationProject(int $orgID, int $proID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return ArrayHelper::getValue(
			$this->getOrganisationProjectsNEW(['orgID' => $orgID, 'proID' => $proID]),
			$proID,
			null
		);
	}

	public function getQualityManagers() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$orgID  = ArrayHelper::getValue($args, 'orgID');
		$orgID  = (is_null($orgID))  ? null : (int) $orgID;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery(/** @lang MySQL */'SET NAMES utf8')->execute();
		$db->setQuery(/** @lang MySQL */'SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		$table = 'usergroups';
		$sub   = $db->getQuery(true)
		->from($db->qn($table))
		->select($db->qn('groupID'))
		->where($db->qn('name') . ' LIKE ' . $db->q('%_GROUP_QUALITY_MANAGER'));

		// Build query.
		$table = 'user_usergroup';
		$query = $db->getQuery(true)
		->from($db->qn($table, 'uug'))
		->select($orgID . ' AS ' . $db->qn('orgID'))
		->select($db->qn('o.name') . ' AS ' . $db->qn('organisation'))
		->select($db->qn('uug.userID'))
		->select($db->qn('u.fullname'))
		->select($db->qn('u.email'))
		->select($db->qn('u.blocked'))
		->select($db->qn('uug.groupID'))
		->select($db->qn('ug.name') . ' AS ' . $db->qn('group'))
		->join('LEFT', $db->qn('organisation_user') . '  AS ' . $db->qn('ou') . ' ON ' . $db->qn('uug.userID')  . ' = ' . $db->qn('ou.userID'))
		->join('LEFT', $db->qn('organisations')     . '  AS ' . $db->qn('o')  . ' ON ' . $db->qn('ou.orgID')    . ' = ' . $db->qn('o.orgID'))
		->join('LEFT', $db->qn('usergroups')        . '  AS ' . $db->qn('ug') . ' ON ' . $db->qn('uug.groupID') . ' = ' . $db->qn('ug.groupID'))
		->join('LEFT', $db->qn('users')             . '  AS ' . $db->qn('u')  . ' ON ' . $db->qn('uug.userID')  . ' = ' . $db->qn('u.userID'));

		// Limit results to passed item ID.
		if (!is_null($orgID))
		{
			$query
			->where($db->qn('ou.orgID') . ' = ' . $orgID);
		}

		// Apply filter.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('0'))
				->where($db->qn('u.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			/*case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;*/

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('u.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('u.trashed')  . ' = ' . $db->q('1'));
			break;

			/*case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
//				->where($db->qn('u.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break;*/

			/*default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
//				->where($db->qn('u.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
		}

		$query
		->where($db->qn('uug.groupID') . ' = (' . $sub . ')');

		// Hide these users (required for automated testing, debugging, etc.) from users list
		// if users list is requested by non-developers and not Tino!
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@nematrack.com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}

		// Add ordering.
		$query
		->order(
			$db->qn([
				'o.name',
				'u.fullname'
			])
		);

		// Execute query.
		try
		{
			$pkName = Entity::getInstance('user')->getPrimaryKeyName();
			$rows   = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Translate language name.
				if (array_key_exists('group', $row))
				{
					$row['group'] = (str_starts_with($row['group'], 'COM_FTK_GROUP_')
						? Text::translate(ArrayHelper::getValue($row, 'group', null, 'STRING'), $this->language)
						: $row['group']);
				}

//				$rows[$row[$pkName]] = $row;
				$rows[$row['email']] = $row;
			}
		}
		catch (Exception $e)
		{
			$rows = [];

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	public function getQualityResponsibles() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$orgID  = ArrayHelper::getValue($args, 'orgID');
		$orgID  = (is_null($orgID))  ? null : (int) $orgID;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery(/** @lang MySQL */'SET NAMES utf8')->execute();
		$db->setQuery(/** @lang MySQL */'SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		$table = 'usergroups';
		$sub   = $db->getQuery(true)
		->from($db->qn($table))
		->select($db->qn('groupID'))
		->where($db->qn('name') . ' LIKE ' . $db->q('%_GROUP_QUALITY_ASSURANCE'));

		// Build query.
		$table = 'user_usergroup';
		$query = $db->getQuery(true)
		->from($db->qn($table, 'uug'))
		->select($orgID . ' AS ' . $db->qn('orgID'))
		->select($db->qn('o.name') . ' AS ' . $db->qn('organisation'))
		->select($db->qn('uug.userID'))
		->select($db->qn('u.fullname'))
		->select($db->qn('u.email'))
		->select($db->qn('u.blocked'))
		->select($db->qn('uug.groupID'))
		->select($db->qn('ug.name') . ' AS ' . $db->qn('group'))
		->join('LEFT', $db->qn('organisation_user') . '  AS ' . $db->qn('ou') . ' ON ' . $db->qn('uug.userID')  . ' = ' . $db->qn('ou.userID'))
		->join('LEFT', $db->qn('organisations')     . '  AS ' . $db->qn('o')  . ' ON ' . $db->qn('ou.orgID')    . ' = ' . $db->qn('o.orgID'))
		->join('LEFT', $db->qn('usergroups')        . '  AS ' . $db->qn('ug') . ' ON ' . $db->qn('uug.groupID') . ' = ' . $db->qn('ug.groupID'))
		->join('LEFT', $db->qn('users')             . '  AS ' . $db->qn('u')  . ' ON ' . $db->qn('uug.userID')  . ' = ' . $db->qn('u.userID'));

		// Limit results to passed item ID.
		if (!is_null($orgID))
		{
			$query
			->where($db->qn('ou.orgID') . ' = ' . $orgID);
		}

		// Apply filter.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('0'))
				->where($db->qn('u.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			/*case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;*/

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('u.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('u.trashed')  . ' = ' . $db->q('1'));
			break;

			/*case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
//				->where($db->qn('u.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break;*/

			/*default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
//				->where($db->qn('u.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
		}

		$query
		->where($db->qn('uug.groupID') . ' = (' . $sub . ')');

		/*// Hide these users (required for automated testing, debugging, etc.) from users list
		// if users list is requested by non-developers and not Tino!
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@nematrack.com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}*/

		// Add ordering.
		$query
		->order(
			$db->qn([
				'o.name',
				'u.fullname'
			])
		);

		// Execute query.
		try
		{
			$pkName = Entity::getInstance('user')->getPrimaryKeyName();
			$rows   = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Translate language name.
				if (array_key_exists('group', $row))
				{
					$row['group'] = (str_starts_with($row['group'], 'COM_FTK_GROUP_')
						? Text::translate(ArrayHelper::getValue($row, 'group', null, 'STRING'), $this->language)
						: $row['group']);
				}

//				$rows[$row[$pkName]] = $row;
				$rows[$row['email']] = $row;
			}
		}
		catch (Exception $e)
		{
			$rows = [];

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	// TODO - moved here from model Users to convert into function that fetches users by organisation ID - so far untested !!!
	private function getOrganisationUsers__OBSOLETE(int $orgID) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('users', 'u'))
		->join('INNER', $db->qn('organisation_user') . ' AS ' . $db->qn('ou') . ' ON ' . $db->qn('u.userID') . ' = ' . $db->qn('ou.userID'))
		->join('LEFT',  $db->qn('organisations')     . ' AS ' . $db->qn('o')  . ' ON ' . $db->qn('ou.orgID') . ' = ' . $db->qn('o.orgID'))
		->select(
			$db->qn([
				'ou.orgID',
				'u.userID',
				'u.fullname',
				'u.email',
				'u.password',
				'u.blocked',
				'u.blockDate',
				'u.registerDate',
				'u.lastVisitDate',
				'u.lastLogoutDate',
				'u.lastResetTime',
				'u.resetCount',
				'u.requireReset',
				'u.languages',
				'u.created',
				'u.created_by',
				'u.modified',
				'u.modified_by',
			])
		)
		->select("
			CONCAT(
				'{',
				'\"id\":\"'     , `o`.`orgID`,   '\",',
				'\"name\":\"'   , `o`.`name`,    '\",',
				'\"country\":\"', `o`.`country`, '\" '
				'}'
			) AS `organisation`
		");

		// Limit results to passed item ID.
		if (!is_null($orgID))
		{
			$query
			->where($db->qn('ou.orgID') . ' = ' . $orgID);
		}

		/*// Hide Testuser (required for automated testing) from users list
		// if users list is requested by non-developers and not Tino!
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@nematrack.com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}*/

		$query
//		->order($db->qn('u.created'));
		->order($db->qn('u.fullname'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['userID']] = $row;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}
	public function getOrganisationUsers() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$orgID  = ArrayHelper::getValue($args, 'orgID');
		$orgID  = (is_null($orgID))  ? null : (int) $orgID;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$table = 'users';
		$query = $db->getQuery(true)
		->from($db->qn($table, 'u'))
		->select(
			$db->qn([
				'ou.orgID',
				'u.userID',
				'u.fullname',
				'u.email',
				'u.password',
				'u.blocked',
				'u.blockDate',
				'u.registerDate',
				'u.lastVisitDate',
				'u.lastLogoutDate',
				'u.lastResetTime',
				'u.resetCount',
				'u.requireReset',
				'u.languages',
				'u.created',
				'u.created_by',
				'u.modified',
				'u.modified_by',
			])
		)
		->select("
			CONCAT(
				'{',
				'\"id\":\"'     , `o`.`orgID`,   '\",',
				'\"name\":\"'   , `o`.`name`,    '\",',
				'\"country\":\"', `o`.`country`, '\" '
				'}'
			) AS `organisation`
		")
		->select('GROUP_CONCAT(' . $db->qn('ug.groupID') . ') AS ' . $db->qn('gids'))
		->select('GROUP_CONCAT(' . $db->qn('ug.name')    . ') AS ' . $db->qn('groups'))
		->join('INNER', $db->qn('organisation_user')     . '  AS ' . $db->qn('ou')  . ' ON ' . $db->qn('u.userID')    . ' = ' . $db->qn('ou.userID'))
		->join('LEFT',  $db->qn('organisations')         . '  AS ' . $db->qn('o')   . ' ON ' . $db->qn('ou.orgID')    . ' = ' . $db->qn('o.orgID'))
		->join('LEFT',  $db->qn('user_usergroup')        . '  AS ' . $db->qn('uug') . ' ON ' . $db->qn('u.userID')    . ' = ' . $db->qn('uug.userID'))
		->join('LEFT',  $db->qn('usergroups')            . '  AS ' . $db->qn('ug')  . ' ON ' . $db->qn('uug.groupID') . ' = ' . $db->qn('ug.groupID'));

		// Limit results to passed item ID.
		if (!is_null($orgID))
		{
			$query
			->where($db->qn('ou.orgID') . ' = ' . $orgID);
		}

		// Apply filter.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('0'))
				->where($db->qn('u.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			/* case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
//				->where($db->qn('u.archived') . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break; */

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('u.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('u.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('u.trashed')  . ' = ' . $db->q('1'));
			break;

			/* case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
//				->where($db->qn('u.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('u.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break; */

			/* default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
//				->where($db->qn('u.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('u.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')'); */
		}

		// Hide these users (required for automated testing, debugging, etc.) from users list
		// if users list is requested by non-developers and not Tino!
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@nematrack.com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}

		$query
		->group($db->qn('ou.userID'))
		->order($db->qn('u.fullname'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['userID']] = $row;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}


	public function addOrganisation($organisation)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = ArrayHelper::getValue($organisation, 'form', [], 'ARRAY');

		// Dupecheck organisation name.
		if ($this->existsOrganisation(ArrayHelper::getValue($formData, 'oid', null), ArrayHelper::getValue($formData, 'name')))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ORGANISATION_TEXT', $this->language),
					ArrayHelper::getValue($formData, 'name', null, 'STRING')
				)
			]);

			return false;
		}

		// Prepare formData to be stored into the database.
		if (array_key_exists('processes', $formData) && is_array($formData['processes']))
		{
			$formData['processes'] = array_map('intval', $formData['processes']);
			$formData['processes'] = json_encode($formData['processes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		}
		else
		{
			$formData['processes'] = '[]';
		}

		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		$userID = $user->get('userID');

		// Validate session userID equals current form editor's userID
		if ((int) $userID !== (int) $formData->user)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}

		/*// Prepare organisation processes for storing.
		$processes = (is_string($formData->processes ?? '[]') ? json_decode($formData->processes, null, 512, JSON_THROW_ON_ERROR) : $formData->processes);
		$processes = (is_array($processes) ? array_map('intval', $processes) : $processes);
		$processes = (is_array($processes) ? json_encode($processes, JSON_THROW_ON_ERROR) : '[]');*/    // DISABLED ON 2022-06-03 because of IDE claiming "Unused local variable 'processes'. The value of the variable is not used anywhere."

		// Build query.
		$rowData = new Registry($formData);

		$query = $db->getQuery(true)
		->insert($db->qn('organisations'))
		->columns(
			$db->qn([
				'name',
				'country',
				'city',
				'zip',
				'addressline',
				'homepage',
                'org_color',
                'org_abbr',
				'created',
				'created_by'
			])
		)
		->values(implode(',', [
			$db->q(filter_var($rowData->get('name'),        FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('country'),     FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('city'),        FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('zip'),         FILTER_SANITIZE_STRING)),
			$db->q(filter_var($rowData->get('addressline'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('homepage'),    FILTER_SANITIZE_STRING)),
            $db->q(filter_var($rowData->get('org_color'),    FILTER_SANITIZE_STRING)),
            $db->q(filter_var($rowData->get('org_abbr'),    FILTER_SANITIZE_STRING)),
			$db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			(int) $userID
		]));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$insertID = null;
		}

		// Inject insert_id as foreign key value for the metadata store process.
		$formData->oid = $insertID;

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Store organisation meta information.
		$metaStored = $this->storeOrganisationMeta($formData->oid, $formData);

		// Store organisation role(s).
		$roleStored = $this->storeOrganisationRole($formData->oid, $formData);

		// Get all registered app languages and drop currently active language
		$langs = array_filter($this->getInstance('languages', ['language' => $this->language])->getList(['filter' => Lizt::FILTER_ACTIVE, 'onlyTags' => true]), function($language)
		{
			// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
			$language = new Registry($language);

			return $language->get('tag') !== $this->language;
		});

		// Store placholders for all other languages that are not current language.
		$isError = false;

		array_walk($langs, function($language, $tag) use(&$formData, &$isError)	// in addOrganisation()
		{
			if ($isError)
			{
				return;
			}

			// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
			$language = new Registry($language);

			// Skip prev. stored metadata object.
			if ($language->get('lngID') == $formData->lngID)
			{
				return;
			}

			$formData->lngID = $language->get('lngID');
			$formData->lng   = $language->get('tag');
			$formData->name  = Text::translate('COM_FTK_NA_TEXT', $language->get('tag'));
			$formData->description = null;

			// Store organisation meta information placeholder.
			$isError = !$this->storeOrganisationMeta($formData->oid, $formData);
		});

		return (($insertID > 0 && $metaStored && $roleStored && !$isError) ? $formData->oid : false);
	}

	public function updateOrganisation($organisation)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = $organisation['form'] ?? [];
		$fileData = $art['files'] ?? [];

		// Existence check.
		if (!$this->existsOrganisation(ArrayHelper::getValue($formData, 'oid')))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such organisation: %s', $this->language), $formData['name'])
			]);

			return false;
		}

		// Dupe check.
		if ($tmpOrganisation = $this->getOrganisationByName(ArrayHelper::getValue($formData, 'name', '', 'STRING')))
		{
			// Compare both IDs. If they're different, then another item already uses the name this item shall use, which is not allowed.
			if (is_a($tmpOrganisation, 'Nematrack\Entity\Organisation')
			&& is_int($tmpOrganisation->get('orgID'))
			&& ($tmpOrganisation->get('orgID') != ArrayHelper::getValue($formData, 'oid'))
			) {
				Messager::setMessage([
					'type' => 'info',
					'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ORGANISATION_TEXT', $this->language),
						ArrayHelper::getValue($formData, 'name', null, 'STRING')
					)
				]);

				return false;
			}
			else
			{
				// Free memory.
				unset($tmpOrganisation);
			}
		}

		// Prepare formData to be stored into the database.
		if (array_key_exists('processes', $formData) && is_array($formData['processes']))
		{
			$formData['processes'] = array_map('intval', $formData['processes']);
			$formData['processes'] = json_encode($formData['processes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		}
		else
		{
			$formData['processes'] = '[]';
		}

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		$userID = $user->get('userID');

		// Validate session userID equals current form editor's userID
		if ((int) $userID !== (int) $formData->user)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}

		/*// Prepare organisation processes value for storing.
		$processes = (is_string($formData->processes ?? '[]') ? json_decode($formData->processes, null, 512, JSON_THROW_ON_ERROR) : $formData->processes);
		$processes = (is_array($processes) ? array_map('intval', $processes) : $processes);
		$processes = (is_array($processes) ? json_encode($processes, JSON_THROW_ON_ERROR) : '[]');*/    // DISABLED ON 2022-06-03 because of IDE claiming "Unused local variable 'processes'. The value of the variable is not used anywhere."

		// Build query.
		$rowData = new Registry($formData);

		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('name')        . ' = ' . $db->q(filter_var($rowData->get('name'),        FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->qn('country')     . ' = ' . $db->q(filter_var($rowData->get('country'),     FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->qn('city')        . ' = ' . $db->q(filter_var($rowData->get('city'),        FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->qn('zip')         . ' = ' . $db->q(filter_var($rowData->get('zip'),         FILTER_SANITIZE_STRING)),
			$db->qn('addressline') . ' = ' . $db->q(filter_var($rowData->get('addressline'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->qn('homepage')    . ' = ' . $db->q(filter_var($rowData->get('homepage'),    FILTER_SANITIZE_STRING)),
            $db->qn('org_color')    . ' = ' . $db->q(filter_var($rowData->get('org_color'),    FILTER_SANITIZE_STRING)),
            $db->qn('org_abbr')    . ' = ' . $db->q(filter_var($rowData->get('org_abbr'),    FILTER_SANITIZE_STRING)),
			$db->qn('modified')    . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('modified_by') . ' = ' . (int) $userID
		])
		->where($db->qn('orgID')   . ' = ' . (int) filter_var($rowData->get('oid'), FILTER_VALIDATE_INT));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$affectedRows = null;
		}

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Store organisation meta information.
		$metaStored = $this->storeOrganisationMeta($formData->oid, $formData);

		// Store organisation role(s).
		$roleStored = $this->storeOrganisationRole($formData->oid, $formData);

		return (($affectedRows > 0 && $metaStored && $roleStored) ? $formData->oid : false);
	}


	public function lockOrganisation(int $orgID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*// Can this user delete content?
		if (!$this->userCanLock())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load organisation from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($orgID);

		if (!is_a($item, 'Nematrack\Entity\Organisation') || !$item->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive organisations at all?
		if (false === $this->canLockOrganisation($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this organisation.
		if (false === $this->organisationIsLockable($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('1'),
			$db->qn('blockDate')   . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('blocked_by')  . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('orgID')   . ' = ' . $db->q($orgID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $orgID;
	}

	public function unlockOrganisation(int $orgID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRestore())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load organisation from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($orgID);

		if (!is_a($item, 'Nematrack\Entity\Organisation') || !$item->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover organisations at all?
		if (false === $this->canRestoreOrganisation($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this organisation.
		if (false === $this->organisationIsRestorable($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('0'),
			$db->qn('blockDate')   . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('blocked_by')  . ' = NULL'
		])
		->where($db->qn('orgID')   . ' = ' . $db->q($orgID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $orgID;
	}

	public function archiveOrganisation(int $orgID)		// This is currently the opposite of restoreOrganisation - it blockes and archives an accessible item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*// Can this user delete content?
		if (!$this->userCanArchivate())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load organisation from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($orgID);

		if (!is_a($item, 'Nematrack\Entity\Organisation') || !$item->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive organisations at all?
		if (false === $this->canArchiveOrganisation($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this organisation.
		if (false === $this->organisationIsArchivable($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('1'),
			$db->qn('archiveDate') . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('archived_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('orgID')   . ' = ' . $db->q($orgID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $orgID;
	}

	public function restoreOrganisation(int $orgID)		// This is currently the opposite of archiveOrganisation - it restored an archived item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRestore())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load organisation from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($orgID);

		if (!is_a($item, 'Nematrack\Entity\Organisation') || !$item->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover organisations at all?
		if (false === $this->canRestoreOrganisation($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this organisation.
		if (false === $this->organisationIsRestorable($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('0'),
			$db->qn('archiveDate') . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('archived_by') . ' = NULL'
		])
		->where($db->qn('orgID')   . ' = ' . $db->q($orgID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $orgID;
	}

	public function deleteOrganisation(int $orgID)		// This is currently the opposite of recoverOrganisation - it deletes an accessible item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Can this user delete content?
		if (!$this->userCanDelete())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load organisation from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($orgID);

		if (!is_a($item, 'Nematrack\Entity\Organisation') || !$item->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Is this user allowed to delete organisations at all?
		if (false === $this->canDeleteOrganisation($orgID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this organisation.
		if (false === $this->organisationIsDeletable($orgID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Build query.
		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('1'),
			$db->qn('trashDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('trashed_by') . ' = ' . $db->q((int) $user->get('userID')),
			$db->qn('deleted')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('deleted_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('orgID')  . ' = ' . $db->q($orgID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Reset AUTO_INCREMENT count.
		try
		{
			$db
			->setQuery('ALTER TABLE `organisations` AUTO_INCREMENT = 1')
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $orgID;
	}

	public function recoverOrganisation(int $orgID)		// This is currently the opposite of deleteOrganisation - it recovers a deleted item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRecover())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_RECOVER_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load organisation from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($orgID);

		if (!is_a($item, 'Nematrack\Entity\Organisation') || !$item->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover organisations at all?
		if (false === $this->canRestoreOrganisation($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this organisation.
		if (false === $this->organisationIsRestorable($orgID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('organisations'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('0'),
			$db->qn('trashDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('trashed_by') . ' = NULL',
			$db->qn('deleted')    . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('deleted_by') . ' = NULL'
		])
		->where($db->qn('orgID')  . ' = ' . $db->q($orgID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $orgID;
	}


	/**
	 * Add description...
	 *
	 * @throws  \InvalidArgumentException  When function is called with empty parameters.
	 */
	protected function existsOrganisation($orgID = null, $organisationName = null, $lang = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		// Function parameter check.
		if (is_null($orgID) && is_null($organisationName))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function requires at least 1 argument.');
		}

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('orgID'))
		->from($db->qn('organisations'));

		// This function is not called PRIOR CREATION (no orgID available) and PRIOR DELETION (orgID is available).
		// Hence, 'orgID' must not be the only column to check for!
		switch (true)
		{
			// Should find existing organisation identified by orgID + orgName.
			case (!empty($orgID) && !empty($organisationName)) :
				$query
				->where($db->qn('orgID') . ' = ' . (int) $orgID)
				->where('LOWER(' . $db->qn('name') . ') = LOWER( TRIM(' . $db->q(trim($organisationName)) . ') )');
			break;

			// Should find existing organisation identified by orgID.
			case (!empty($orgID) && (int) $orgID > 0) :
				$query
				->where($db->qn('orgID') . ' = ' . (int) $orgID);
			break;

			// Should find existing organisation identified by orgName.
			case (!empty($organisationName) && trim($organisationName) !== '') :
				$query
				->where('LOWER(' . $db->qn('name') . ') LIKE LOWER( TRIM(' . $db->q(trim($organisationName)) . ') )');
			break;
		}

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		// return (!\is_null($rs) && property_exists($rs, 'orgID') ? true : false);
		return $rs > 0;
	}

	protected function canDeleteOrganisation(int $orgID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_null(App::getAppUser()))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_FAILURE_TEXT', $this->language)
			]);

			return false;
		}

		// TODO - Implement ACL and make calculate editor-right from ACL
		$canDelete = true;
		// $canDelete = (\is_object($user) ? (bool) $user->rights->canDelete : false);
		// $canDelete = (\is_object($user) && $user->groups ? (bool) $user->rights->canDelete : false);
		// $canDelete = (($user instanceof User && $user->hasRole(FTKUser::RoleEditor) ? (bool) $user->rights->canDelete : false);

		if (!$canDelete)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

		return true;
	}

	protected function organisationIsDeletable(int $orgID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if ($this->organisationHasDependencies($orgID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_DELETION_DEPENDENCIES_TEXT', $this->language)
			]);

			return false;
		}

		return true;
	}

	//@todo - implement proper check for dependencies
	protected function organisationHasDependencies(int $orgID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Organisation has dependencies when it has process responability, users, etc.

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		$from1 = $db->getQuery(true)
		->setQuery('
			SELECT `x`.`count` AS `users`
			FROM (
				SELECT COUNT(`userID`) AS `count`
				FROM `organisation_user`
				WHERE `orgID` = ' . $orgID . '
			) AS `x`'
		);

		$from2 = $db->getQuery(true)
		->setQuery('
			SELECT `y`.`count` AS `projects`
			FROM (
				SELECT COUNT(`orgID`) AS `count`
				FROM `project_organisation`
				WHERE `orgID` = ' . $orgID . '
			) AS `y`'
		);

		// Build query.
		$query = $db->getQuery(true)
		->select(
			$db->qn([
				'm.users',
				'n.projects'
			])
		)
		->from('
			(' . $from1 . ') AS `m` ,
			(' . $from2 . ') AS `n`
		');

		// Execute query.
		try
		{
			$row = $db->setQuery($query)->loadObject();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return true;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $row->users > 0 || $row->projects > 0;
	}


	protected function hasOrganisationMeta(int $orgID, string $lang, bool $isNotNull = false, $returnData = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$table = 'organisation_meta';
		$query = $db->getQuery(true)
		->from($db->qn($table))
		->where($db->qn('orgID')    . ' = ' . $orgID)
		->where($db->qn('language') . ' = ' . $db->q(trim($lang)));

		if ($isNotNull)
		{
			$query
			->where($db->qn('description'). ' IS NOT NULL');
		}

		if ($returnData)
		{
			$columns = DatabaseHelper::getTableColumns($table);

			if (!is_array($columns))
			{
				return null;
			}

			$query
			->select(
				implode(',', $db->qn($columns))
			);
		}
		else
		{
			$query
			->select('COUNT(' . $db->qn('orgID') . ') AS ' . $db->qn('count'));
		}

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssoc();

			if (!$returnData)
			{
				$rs = array_map('intval', $rs);
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return ($returnData) ? $rs : $rs['count'] > 0;
	}

	//@todo - save organisation name for all available languages since this is not gonna translated into another language
	protected function storeOrganisationMeta(int $orgID, $organisationMeta) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!count(array_filter((array) $organisationMeta)))
		{
			return true;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		if (!is_object($organisationMeta))
		{
			$organisationMeta = (object) $organisationMeta;
		}

		$hasMeta = $this->hasOrganisationMeta((int) $organisationMeta->oid, $organisationMeta->lng);

		// Build query.
		$rowData = new Registry($organisationMeta);

		$orgDescription = filter_var($rowData->get('description'),  FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		$orgDescription = (is_null($orgDescription) || trim($orgDescription)  == '') ? "NULL" : $db->q(trim($orgDescription));

		if (!$hasMeta)
		{
			$query = $db->getQuery(true)
			->insert($db->qn('organisation_meta'))
			->columns(
				$db->qn([
					'orgID',
					'lngID',
					'description',
					'language'
				])
			)
			->values(implode(',', [
				(int)  filter_var($rowData->get('oid'),   FILTER_VALIDATE_INT),
				(int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT),
				$orgDescription,
				$db->q(filter_var($rowData->get('lng'),   FILTER_SANITIZE_STRING))
			]));
		}
		else
		{
			$query = $db->getQuery(true)
			->update($db->qn('organisation_meta'))
			->set([
				$db->qn('description') . ' = ' . $orgDescription,
				$db->qn('language')    . ' = ' . $db->q(filter_var($rowData->get('lng'),   FILTER_SANITIZE_STRING))
			])
			->where($db->qn('orgID')   . ' = ' . (int)  filter_var($rowData->get('oid'),   FILTER_VALIDATE_INT))
			->where($db->qn('lngID')   . ' = ' . (int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT));
		}

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	protected function deleteOrganisationMeta(int $orgID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('organisation_meta'))
		->where($db->qn('orgID') . ' = ' . $orgID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// return $orgID;
		return true;
	}


	protected function hasOrganisationRole(int $orgID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('roleID'))
		->from($db->qn('organisation_role'))
		->where($db->qn('orgID') . ' = ' . $orgID);

		// Execute query.
		try
		{
			$row = $db->setQuery($query)->loadObject();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$row = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return (!is_null($row) && property_exists($row, 'roleID') ? true : false);
	}

	protected function storeOrganisationRole(int $orgID, $organisationRole) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		if (!is_object($organisationRole))
		{
			$organisationRole = (object) $organisationRole;
		}

		// TODO - lookup roleID from organisation_role
		$hasRole = $this->hasOrganisationRole((int) $organisationRole->oid);

		// Build query.
		$rowData = new Registry($organisationRole);

		if (!$hasRole)
		{
			$query = $db->getQuery(true)
			->insert($db->qn('organisation_role'))
			->columns(
				$db->qn([
					'orgID',
					'roleID'
				])
			)
			->values(implode(',', [
				(int) filter_var($rowData->get('oid'), FILTER_VALIDATE_INT),
				(int) filter_var($rowData->get('rid'), FILTER_VALIDATE_INT)
			]));
		}
		else
		{
			$query = $db->getQuery(true)
			->update($db->qn('organisation_role'))
			->set($db->qn('roleID')  . ' = ' . (int) filter_var($rowData->get('rid'), FILTER_VALIDATE_INT))
			->where($db->qn('orgID') . ' = ' . (int) filter_var($rowData->get('oid'), FILTER_VALIDATE_INT));
		}

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}
}
