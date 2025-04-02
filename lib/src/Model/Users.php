<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use  \Access;
use  \App;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use function is_a;
use function is_null;

/**
 * Class description
 */
class Users extends ListModel
{
	protected $tableName = 'user';

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

	public function getList($userID = null) : array
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
		// MYSQLI_QUERY($db, "SET SESSION group_concat_max_len = 100000;");
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from(        $db->qn('organisations', 'o'))
		->join('LEFT', $db->qn('organisation_user') . ' AS ' . $db->qn('ou')  . ' ON ' . $db->qn('o.orgID')     . ' = ' . $db->qn('ou.orgID'))
		->join('LEFT', $db->qn('users')             . ' AS ' . $db->qn('u')   . ' ON ' . $db->qn('ou.userID')   . ' = ' . $db->qn('u.userID'))
		->join('LEFT', $db->qn('user_usergroup')    . ' AS ' . $db->qn('uug') . ' ON ' . $db->qn('ou.userID')   . ' = ' . $db->qn('uug.userID'))
		->join('LEFT', $db->qn('usergroups')        . ' AS ' . $db->qn('ug')  . ' ON ' . $db->qn('uug.groupID') . ' = ' . $db->qn('ug.groupID'))
		->select([
			$db->qn('ou.userID'),
			$db->qn('o.orgID'),
			$db->qn('o.name'),
			$db->qn('u.fullname'),
			$db->qn('u.email'),
			$db->qn('u.password'),
			$db->qn('u.blocked'),
			"CONCAT('[', GROUP_CONCAT(DISTINCT " . $db->qn('uug.groupID') . "), ']') AS " . $db->qn('groups'),
			$db->qn('ug.blocked') . ' AS ' . $db->qn('gblocked'),
			$db->qn('u.languages'),
			$db->qn('u.blockDate'),
			$db->qn('u.registerDate'),
			$db->qn('u.lastVisitDate'),
			$db->qn('u.lastLogoutDate'),
			$db->qn('u.lastResetTime'),
			$db->qn('u.resetCount'),
			$db->qn('u.requireReset'),
			$db->qn('u.created'),
			$db->qn('u.created_by'),
			$db->qn('u.modified'),
			$db->qn('u.modified_by'),
			$db->qn('u.deleted'),
			$db->qn('u.deleted_by')
		]);

		/*// Only users with higher privileges must be allowed to see blocked items.
		if (!\is_a($user, ' \Entity\User') || (\is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where('u.blocked = ' . $db->q('0'));
		}*/

		$query
		->where($db->qn('ug.blocked') . ' = ' . $db->q('0'))
		->where($db->qn('ou.userID')  . ' IS NOT NULL');

		// If specific user has been requested, apply its ID as search criteria.
		if (!is_null($userID))
		{
			$query
			->where($db->qn('u.userID') . ' = ' . (int) $userID);
		}

		// Hide Testuser (required for automated testing) from users list
		// if users list is requested by non-developers and not Tino!
		if (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER))
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@ .com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}

		// Add grouping and ordering.
		if (is_null($userID))
		{
			$query
			->group($db->qn('ou.userID'))
//			->order($db->qn('o.name') . ',' . $db->qn('u.created'));
			->order($db->qn('o.name') . ',' . $db->qn('u.fullname'));
		}

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
	// TODO - implement like in projects model
	public function getListNEW(/* $userID = null */) : array	// refactoring of 'getList' that is available to developers only. Shall replace 'getList' once refactoring is finished.
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// If there's an article id pick it for the query specification.
		// $filter   = array_map('intval', array_unique(ArrayHelper::getValue($args, 'filter', [], 'ARRAY')));
		// $filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$filter = ArrayHelper::getValue($args, 'filter');
		$filter = (is_null($filter)) ? null : (int) $filter;
		$id     = ArrayHelper::getValue($args, 'userID');
		$id     = (is_null($id)) ? null : (int) $id;

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

		$rows = [];
		
//		$orgID = (is_null($orgID) ? $orgID : (int) $orgID);
		
		// Build query.
		$query = $db->getQuery(true)
		->from(        $db->qn('organisations', 'o'))
//		->join('LEFT', $db->qn('organisation_meta') . ' AS ' . $db->qn('om')  . ' ON ' . $db->qn('om.orgID')    . ' = ' . $db->qn('o.orgID'))
		->join('LEFT', $db->qn('organisation_user') . ' AS ' . $db->qn('ou')  . ' ON ' . $db->qn('o.orgID')     . ' = ' . $db->qn('ou.orgID'))
		->join('LEFT', $db->qn('users')             . ' AS ' . $db->qn('u')   . ' ON ' . $db->qn('ou.userID')   . ' = ' . $db->qn('u.userID'))
		->join('LEFT', $db->qn('user_usergroup')    . ' AS ' . $db->qn('uug') . ' ON ' . $db->qn('ou.userID')   . ' = ' . $db->qn('uug.userID'))
		->join('LEFT', $db->qn('usergroups')        . ' AS ' . $db->qn('ug')  . ' ON ' . $db->qn('uug.groupID') . ' = ' . $db->qn('ug.groupID'))
		->select([
			$db->qn('ou.userID'),
			$db->qn('o.orgID'),
			$db->qn('o.name'),
			$db->qn('u.fullname'),
			$db->qn('u.email'),
			$db->qn('u.password'),
			$db->qn('u.blocked'),
			"CONCAT('[', GROUP_CONCAT(DISTINCT " . $db->qn('uug.groupID') . "), ']') AS " . $db->qn('groups'),
			$db->qn('ug.blocked') . ' AS ' . $db->qn('gblocked'),
			$db->qn('u.languages'),
			$db->qn('u.blockDate'),
			$db->qn('u.registerDate'),
			$db->qn('u.lastVisitDate'),
			$db->qn('u.lastLogoutDate'),
			$db->qn('u.lastResetTime'),
			$db->qn('u.resetCount'),
			$db->qn('u.requireReset'),
			$db->qn('u.created'),
			$db->qn('u.created_by'),
			$db->qn('u.modified'),
			$db->qn('u.modified_by'),
			$db->qn('u.deleted'),
			$db->qn('u.deleted_by')
		]);

		// Limit results to selected application language.
		// $query
		// ->where($db->qn('om.language') . ' = ' . $db->q($this->language));

		/*// Only users with higher privileges must be allowed to see blocked items.
		if (!\is_a($user, ' \Entity\User') || (\is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where('u.blocked = ' . $db->q('0'));
		}*/

		// Apply status filter to control access.
		switch (true)
		{
			case ($filter === Lizt::FILTER_ALL) :
				$query
				// ->where($db->qn('u.archived')  . ' IN(' . implode(',', ['0','1']) . ')')
				->where($db->qn('u.blocked')   . ' IN(' . implode(',', ['0','1']) . ')')
				->orWhere($db->qn('u.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
			break;

			/*// case in_array(\ \Model\Lizt::FILTER_DELETED, $filter) :
			case ($filter === \ \Model\Lizt::FILTER_DELETED) :
				$query->where($db->qn('u.trashed')  . ' = ' . $db->q('1'));
			break; */

			// case in_array(\ \Model\Lizt::FILTER_ARCHIVED, $filter) :
			case ($filter === Lizt::FILTER_ARCHIVED) :
				// $query->where($db->qn('u.archived') . ' = ' . $db->q('1'));
				$query->where($db->qn('u.blocked')  . ' = ' . $db->q('1'));
			break;

			// case in_array(\ \Model\Lizt::FILTER_LOCKED, $filter) :
			case ($filter === Lizt::FILTER_LOCKED) :
				$query->where($db->qn('u.blocked')  . ' = ' . $db->q('1'));
			break;

			default :
				$query
				// ->where(   $db->qn('u.archived') . ' = ' . $db->q('0'))
				->where($db->qn('u.blocked') . ' = ' . $db->q('0'))
				->where($db->qn('u.trashed') . ' = ' . $db->q('0'));
		}

		$query
		->where($db->qn('ug.blocked') . ' = ' . $db->q('0'))
		->where($db->qn('ou.userID')  . ' IS NOT NULL');

		/*// If specific user has been requested, apply its ID as search criteria.
		if (!is_null($userID))
		{
			$query
			->where($db->qn('u.userID') . ' = ' . (int) $userID);
		}*/

		// Hide Testuser (required for automated testing) from users list
		// if users list is requested by non-developers and not Tino!
		if (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER))
		{
			$query
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('administration@ .com')) // super user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%example.com'))     // test account(s)
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%brackebusch%'))    // programmer
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%test%'))           // automated testing user
			->where($db->qn('u.email') . ' NOT LIKE ' . $db->q('%mustermann%'));    // Max Mustermann - user for online debugging
		}

		// Add grouping and ordering.
		if (is_null($id))
		{
			$query
			->group($db->qn('ou.userID'))
//			->order($db->qn('o.name') . ',' . $db->qn('u.created'));
			->order($db->qn('o.name') . ',' . $db->qn('u.fullname'));
		}

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
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}
}
