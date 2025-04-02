<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Exception;
use Joomla\Filter\InputFilter;
use Joomla\Utilities\ArrayHelper;
use  \Access;
use  \App;
use  \Entity;
use  \Helper\DatabaseHelper;
use  \Helper\UserHelper;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function array_key_exists;
use function array_walk;
use function is_a;
use function is_null;
use function mb_strlen;

/**
 * Class description
 */
class Articles extends ListModel
{
	protected $tableName = 'article';

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
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
	 * Returns a list of items filtered by user access rights.
	 *
	 * @return  array
	 */
	public function getList(): array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', __CLASS__)));
//		$className  = $this->getName();
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be data filter arguments passed to this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$artID  = ArrayHelper::getValue($args, 'artID');
		$artID  = (is_null($artID)) ? null : (int) $artID;



		/* Get ID of selected language.
		 * IMPORTANT:   It turned out on 2022-06-03 that using lngID over language prevents the necessity of grouping the query resultset.
		 *              While testing the query there were duplicate rows when using the term <em>am.language = 'tag'</em>, whereas there
		 *              were no duplicates when using the term <em>am.lngID = ID</em> - both used without GROUP BY.
		 */
		$lngTag = ArrayHelper::getValue($args, 'language', $this->language, 'STRING');
		$lngID  = ArrayHelper::getValue($args, 'lngID');
		$lngID  = is_int($lngID)
			? $lngID
			: ArrayHelper::getValue($this->getInstance('language')->getLanguageByTag($lngTag), 'lngID', 'INT');

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$table = Entity::getInstance($entityName)->getTableName();
		$query = $db->getQuery(true)
		->from($db->qn($table, 'a'))
		->join('LEFT', $db->qn('projects')     . ' AS ' . $db->qn('p')  . ' ON '  . $db->qn('p.number') . ' = SUBSTR(' . $db->qn('a.number') . ', 5, 3)')
		->join('LEFT', $db->qn('article_meta') . ' AS ' . $db->qn('am') . ' ON (' .
			$db->qn('a.' . $pkName)     . ' = ' . $db->qn('am.' . $pkName) . ' AND ' .
			$db->qn('am.language') . ' = ' . $db->q($this->language) .
		')')
		->select([
			$db->qn('a.' . $pkName),
			$db->qn('a.name'),
			$db->qn('a.number'),
			$db->qn('a.custartname'),
			$db->qn('a.custartno'),
			$db->qn('a.processes'),
			$db->qn('a.drawingindex'),
			$db->qn('a.drawing'),
			$db->qn('a.customerDrawing'),
			$db->qn('a.blocked'),
			$db->qn('a.blockDate'),
			$db->qn('a.blocked_by'),
			$db->qn('a.archived'),
			$db->qn('a.archiveDate'),
			$db->qn('a.archived_by'),
			$db->qn('a.created'),
			$db->qn('a.created_by'),
			$db->qn('a.modified'),
			$db->qn('a.modified_by'),
			$db->qn('a.trashDate'),
			$db->qn('a.trashed'),
			$db->qn('a.trashed_by'),
			$db->qn('a.deleted'),
			$db->qn('a.deleted_by'),
			$db->qn('p.proID'),
			$db->qn('p.number')  . ' AS ' . $db->qn('project'),
			$db->qn('p.blocked') . ' AS ' . $db->qn('locked'),
			$db->qn('am.description')/*,
			$db->qn('am.instructions')*/
		]);

		// Ignore the 'DUMMY'.
		$query
		->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

		/*// Only high privileged users must be allowed to see ALL items.
		if (!empty($user) && $user->getFlags() >= Access\User::ROLE_ADMINISTRATOR)
		{
			$query
			->where($db->qn('a.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
		}
		else
		{
			$query
			->where($db->qn('a.trashed') . ' = ' . $db->q('0'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_MANAGER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'))
			->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
		}*/

		// Limit results to passed item ID.
		if (!is_null($artID))
		{
			$query
			->where($db->qn('a.' . $pkName) . ' = ' . $artID);
		}

		// Apply status filter to control access.
		if (!is_null($artID))
		{
			switch (true)
			{
				case ($filter == Lizt::FILTER_ACTIVE) :
					$query
					->where($db->qn('a.archived') . ' = ' . $db->q('0'))
					->where($db->qn('a.blocked')  . ' = ' . $db->q('0'))
					->where($db->qn('a.trashed')  . ' = ' . $db->q('0'));
				break;

				/*case ($filter == Lizt::FILTER_ARCHIVED) :
					$query
					->where($db->qn('a.archived') . ' = ' . $db->q('1'))
					->where($db->qn('a.trashed')  . ' = ' . $db->q('0'));
				break;*/

				case ($filter == Lizt::FILTER_LOCKED) :
					$query
					->where($db->qn('a.blocked')  . ' = ' . $db->q('1'))
					->where($db->qn('a.trashed')  . ' = ' . $db->q('0'));
				break;

				case ($filter == Lizt::FILTER_DELETED) :
					$query
					->where($db->qn('a.trashed')  . ' = ' . $db->q('1'));
				break;

				/*case ($filter == Lizt::FILTER_ALL) :
				default :
					$states = ['0','1'];

					$query
					->where($db->qn('a.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('a.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('a.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					;
				break;*/

				/*default :
					$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

					$query
					->where($db->qn('a.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('a.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('a.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
			}
		}

		// Ignore Tino's test item when user is no programmer and not Max Mustermann (the dummy user for testing).
		if (!$user->isProgrammer())
		{
			if (mb_strtolower($user->get('fullname')) != 'max mustermann')
			{
				// FIXME - find dummy projects and take their numbers rather than hardcoding article numbers.
				$query
				->where($db->qn('a.number') . ' NOT LIKE "' . $db->q('AAA.BBB.CC.D') . '%"');
			}
		}

		// Add grouping and ordering.
		$query
		->group($db->qn('a.' . $pkName))
		->order($db->qn('a.number'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['artID']] = $row;
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
	 * Returns a list of articles filtered by user access rights and optionally passed search term.
	 *
	 * @return  array|null
	 *
	 * @uses   {@link \Symfony\Component\String\Inflector\EnglishInflector}
	 */
	public function findArticles() : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', __CLASS__)));
//		$className  = $this->getName();
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$search = ArrayHelper::getValue($args, 'search', '', 'STRING');
		$search = (is_null($search)) ? null : (new InputFilter)->clean($search);
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);



		/* Get ID of selected language.
		 * IMPORTANT:   It turned out on 2022-06-03 that using lngID over language prevents the necessity of grouping the query resultset.
		 *              While testing the query there were duplicate rows when using the term <em>am.language = 'tag'</em>, whereas there
		 *              were no duplicates when using the term <em>am.lngID = ID</em> - both used without GROUP BY.
		 */
		$lngTag = ArrayHelper::getValue($args, 'language', $this->language, 'STRING');
		$lngID  = ArrayHelper::getValue($args, 'lngID');
		$lngID  = is_int($lngID)
			? $lngID
			: ArrayHelper::getValue($this->getInstance('language')->getLanguageByTag($lngTag), 'lngID', 'INT');

		if (empty($search))
		{
			/*Messager::setMessage([
				'type' => 'info',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_ENTER_A_SEARCH_TERM_TEXT', $this->language)
			]);*/

			return null;
		}

		if (in_array(trim($search), ['%', '000']))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_TYPE_A_MORE_SPECIFIC_SEARCH_TERM_TEXT', $this->language)
			]);

			return null;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Get user organisation information.
		$orgID    = $user->get('orgID');
		$projects = $this->getInstance('organisation', ['language' => $this->language])->getOrganisationProjectsNEW(['orgID' => $orgID]);

		$tmp = [];

		/*// Build filter for articles a user (when is a customer/supplier) is allowed to find.
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			array_walk($projects, function($project) use(&$tmp, &$orgID)
			{
				$project = new Registry($project);

				// If the project is not blocked...
				if (false == $project->get('blocked'))
				{
					array_push($tmp, $project->get('number'));
				}
			});
		}
		else
		{*/
			$tmp = array_column($projects, 'number');
		/*}*/

		// Create comma separated list from allowed projects ready to use as a filter in the database query.
		$userProjects = implode(',', $tmp);

		// Free memory.
		unset($projects);
		unset($tmp);

		// Build query.
		$table = Entity::getInstance($entityName)->getTableName();
		$query = $db->getQuery(true)
		->from($db->qn($table, 'a'))
		->join('LEFT', $db->qn('projects') . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.number') . ' = SUBSTR(' . $db->qn('a.number') . ', 5, 3)')
		->select(
			$db->qn([
				'a.' . $pkName,
				'a.number',
				'a.custartno',
				'a.blocked',
				'a.blockDate',
				'a.blocked_by',
				'a.archived',
				'a.archiveDate',
				'a.archived_by',
				'a.created',
				'a.created_by',
				'a.modified',
				'a.modified_by',
				'a.trashDate',
				'a.trashed',
				'a.trashed_by',
				'a.deleted',
				'a.deleted_by',
				'a.processes'
			])
		);

		// Ignore the 'DUMMY'.
		$query
		->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

		/*// Only high privileged users must be allowed to see ALL items.
		if ($user->isSuperuser())
		{
			$query
			->where($db->qn('a.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
		}
		else
		{
			$query
			->where($db->qn('a.trashed') . ' = ' . $db->q('0'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_MANAGER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'))
			->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
		}*/

		// Apply filter.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
				->where($db->qn('a.archived') . ' = ' . $db->q('0'))
				->where($db->qn('a.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('a.trashed')  . ' = ' . $db->q('0'));
			break;

			/* case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
				->where($db->qn('a.archived') . ' = ' . $db->q('1'))
				->where($db->qn('a.trashed')  . ' = ' . $db->q('0'));
			break; */

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('a.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('a.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('a.trashed')  . ' = ' . $db->q('1'));
			break;

			/* case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
				->where($db->qn('a.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('a.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('a.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break; */

			/* default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
				->where($db->qn('a.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('a.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('a.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')'); */
		}

		// If the search term is not the universal placeholder '%', apply projects filter.
		// Match project number in article number.
		if (mb_strlen($search) > 0)
		{
			if (is_a($user, ' \Entity\User') && $user->getFlags() < Access\User::ROLE_PROGRAMMER)
			{
				// TODO - migrate to Joomla findInSet()
				$query
				->where('FIND_IN_SET( SUBSTR(' . $db->qn('a.number') . ', 5, 3), ' . $db->q($userProjects) . ' )');
			}
		}

		// Search the ETC article number AND customer article number.
		$query
		->andWhere([
			$db->qn('a.number')    . ' LIKE "%' . $db->escape(trim($search)) . '%"',
			$db->qn('a.custartno') . ' LIKE "%' . $db->escape(trim($search)) . '%"'
		]);

		// Ignore Tino's test item when user is no programmer and not Max Mustermann (the dummy user for testing).
		if (!$user->isProgrammer())
		{
			if (mb_strtolower($user->get('fullname')) != 'max mustermann')
			{
				// FIXME - find dummy projects and take their numbers rather than hardcoding article numbers.
				$query
				->where($db->qn('a.number') . ' NOT LIKE "' . $db->q('AAA.BBB.CC.D') . '%"');
			}
		}

		// Add grouping and ordering.
		$query
		->order($db->qn('a.number'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['artID']] = $row;
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
	 * Returns a list of articles filtered by passed article number.
	 *
	 * @param   null $number  The article number fully qualified or a portion of it
	 *
	 * @return  array
	 */
	public function getArticlesByNumber($number = null) : array
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
		->from($db->qn('articles', 'a'))
		->select(
			$db->qn([
				'a.artID',
				'a.number',
				'a.custartno'
			])
		)
		->where($db->qn('a.number') . ' LIKE "%' . $db->escape(trim($number)) . '%"')
		// Ignore the 'DUMMY'.
		->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
		}

		$query
		->order($db->qn('a.number'));

		// Execute query.
		try
		{
			$rows = [];

			// $rs = $db->setQuery($query)->loadObjectList();
			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['number']] = trim($row['artID']);
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
	 * Returns a list of articles filtered by passed project ID or just the count of it.
	 *
	 * @param   int   $proID      The project ID
	 * @param   false $totalOnly  Flag to return just the results count
	 *
	 * @return  array|int  The results list or just the results count
	 */
	public function getArticlesByProjectID(int $proID, bool $totalOnly = false)
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

		// Build sub-query first.
		$sub = $db->getQuery(true)
		->select($db->qn('number'))
		->from($db->qn('projects'))
		->where($db->qn('proID') . ' = ' . $proID);

		// Build query.
		$query = $db->getQuery(true);

		if ($totalOnly)
		{
			$query
			->from($db->qn('articles', 'a'))
			->select('COUNT(' . $db->qn('a.artID') . ') AS ' . $db->qn('articles'))
			->where($db->qn('a.number') . " LIKE CONCAT('%.', (" . $sub . "), '.%')")
			// Ignore the 'DUMMY'.
			->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

			// Only users with higher privileges must be allowed to see blocked items.
			if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
			{
				$query
				->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
			}
		}
		else
		{
			$query
			->from($db->qn('articles', 'a'))
			->join('LEFT', $db->qn('article_process') . ' AS ' . $db->qn('ap') . ' ON ' . $db->qn('a.artID') . ' = ' . $db->qn('ap.artID'))
                ->join('LEFT', $db->qn('organisations') . ' AS ' . $db->qn('orgs') . ' ON ' . $db->qn('ap.org_abbr') . ' = ' . $db->qn('orgs.orgID'))
			->join('LEFT', $db->qn('projects') . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.number') . ' = SUBSTR(' . $db->qn('a.number') . ', 5, 3)')
			->select([
				$db->qn('a.artID'),
				$db->qn('a.name'),
				$db->qn('a.number'),
				$db->qn('a.custartname'),
				$db->qn('a.custartno'),
				$db->qn('a.processes'),
				$db->qn('a.blocked'),
				$db->qn('a.trashed'),
				$db->qn('a.created'),
				$db->qn('a.created_by'),
				$db->qn('a.modified'),
				$db->qn('a.modified_by'),
				$db->qn('a.deleted'),
				$db->qn('a.deleted_by'),
				$db->qn('a.drawing'),
				$db->qn('a.drawingindex'),
				$db->qn('a.customerDrawing'),
				$db->qn('p.proID'),
				$db->qn('p.number')  . ' AS ' . $db->qn('project'),
				$db->qn('p.blocked') . ' AS ' . $db->qn('locked')
			])
			->select("CONCAT(
			'[',
				GROUP_CONCAT(
					CONCAT('{',
						'\"procID\"', ':', '\"', `ap`.`procID`, '\"', ',',
						'\"hasBanderole\"', ':', '\"', `ap`.`hasBanderole`, '\"', ',',
						'\"drawingnumber\"', ':', '\"', `ap`.`drawingnumber`, '\"', ',',
						'\"step\"', ':', '\"', `ap`.`step`, '\"', ',',
						'\"org_abbr\"', ':', '\"', `orgs`.`org_abbr`, '\"', ',',
						'\"org_color\"', ':', '\"', `orgs`.`org_color`, '\"', ',',
						'\"orgName\"', ':', '\"', `orgs`.`name`, '\"', ',',
						'\"orgCode\"', ':', '\"', `ap`.`org_abbr`, '\"', ',',
						'\"drawing\"', ':', `ap`.`drawing`,
					'}')
				),
			']') AS `drawings`"
			)
			->where($db->qn('a.number') . " LIKE CONCAT('%.', (" . $sub . "), '.%')")
			// Ignore the 'DUMMY'.
			->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

			// Only users with higher privileges must be allowed to see blocked items.
			if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
			{
				$query
				->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
			}

			$query
			->group($db->qn('a.artID'))
			->order($db->qn('a.number'));
		}

		// Execute query.
		try
		{
			if ($totalOnly)
			{
				$rs = $db->setQuery($query)->loadObject();

				$rows = (int) $rs->articles;
			}
			else
			{
				$rs = $db->setQuery($query)->loadAssocList();

				$rows = [];

				foreach ($rs as $row)
				{
					if (!array_key_exists('drawings', $row) || empty($row['drawings']))
					{
						$row['drawings'] = '[]';
					}

					$rows[$row['artID']] = $row;
				}
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = ($totalOnly ? 0 : []);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	/**
	 * Returns a list of articles filtered by passed project number or just the count of it.
	 *
	 * @param   string $number     The project number
	 * @param   false  $totalOnly  Flag to return just the results count
	 *
	 * @return  array|int  The results list or just the results count
	 */
	public function getArticlesByProjectNumber(string $number, bool $totalOnly = false)
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
		$query = $db->getQuery(true);

		if ($totalOnly)
		{
			$query
			->from($db->qn('articles', 'a'))
			->select('COUNT(' . $db->qn('a.artID') . ') AS ' . $db->qn('articles'))
			->where($db->qn('a.number') . " LIKE CONCAT('%.', " . $db->q($number) . ", '.%')")
			// Ignore the 'DUMMY'.
			->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

			// Only users with higher privileges must be allowed to see blocked items.
			if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
			{
				$query
				->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
			}
		}
		else
		{
			$query
			->from($db->qn('articles', 'a'))
			->join('LEFT', $db->qn('article_process') . ' AS ' . $db->qn('ap') . ' ON ' . $db->qn('a.artID') . ' = ' . $db->qn('ap.artID'))
                ->join('LEFT', $db->qn('organisations') . ' AS ' . $db->qn('orgs') . ' ON ' . $db->qn('ap.org_abbr') . ' = ' . $db->qn('orgs.orgID'))
			->join('LEFT', $db->qn('projects') . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.number') . ' = SUBSTR(' . $db->qn('a.number') . ', 5, 3)')
			->select([
				$db->qn('a.artID'),
				$db->qn('a.name'),
				$db->qn('a.number'),
				$db->qn('a.custartname'),
				$db->qn('a.custartno'),
				$db->qn('a.processes'),
				$db->qn('a.drawing'),
				$db->qn('a.drawingindex'),
				$db->qn('a.customerDrawing'),
				$db->qn('a.blocked'),
				$db->qn('a.trashed'),
				$db->qn('a.created'),
				$db->qn('a.created_by'),
				$db->qn('a.modified'),
				$db->qn('a.modified_by'),
				$db->qn('a.deleted'),
				$db->qn('a.deleted_by'),
				$db->qn('p.proID'),
				$db->qn('p.number')  . ' AS ' . $db->qn('project'),
				$db->qn('p.blocked') . ' AS ' . $db->qn('locked')
			])
			->select("CONCAT(
			'[',
				GROUP_CONCAT(
					CONCAT('{',
						'\"procID\"', ':', '\"', `ap`.`procID`, '\"', ',',
						'\"hasBanderole\"', ':', '\"', `ap`.`hasBanderole`, '\"', ',',
						'\"drawingnumber\"', ':', '\"', `ap`.`drawingnumber`, '\"', ',',
						'\"step\"', ':', '\"', `ap`.`step`, '\"', ',',
						'\"org_abbr\"', ':', '\"', `orgs`.`org_abbr`, '\"', ',',
						'\"org_color\"', ':', '\"', `orgs`.`org_color`, '\"', ',',
						'\"drawing\"', ':', `ap`.`drawing`,
					'}')
				),
			']') AS `drawings`"
			)
			->where($db->qn('a.number') . " LIKE CONCAT('%.', " . $db->q($number) . ", '.%')")
			// Ignore the 'DUMMY'.
			->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)');

			// Only users with higher privileges must be allowed to see blocked items.
			if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
			{
				$query
				->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
			}

			$query
			->group($db->qn('a.artID'))
			->order($db->qn('a.number'));
		}

		// Execute query.
		try
		{
			if ($totalOnly)
			{
				$rs = $db->setQuery($query)->loadObject();

				$rows = (int) $rs->articles;
			}
			else
			{
				$rs = $db->setQuery($query)->loadAssocList();

				$rows = [];

				foreach ($rs as $row)
				{
					if (!array_key_exists('drawings', $row) || empty($row['drawings']))
					{
						$row['drawings'] = '[]';
					}

					$rows[$row['artID']] = $row;
				}
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = ($totalOnly ? 0 : []);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	/**
	 * Returns a list of all main groups of all registered articles.
	 * A main group is the first character tuple of an article number,
	 * e.g. 'AAA' in the fictitious main group in the fictitious article number 'AAA.BBB.CC.CCCCC.000'.
	 *
	 * @param   int  $nestingLevel  Defines the nesting level of the articles in relation to their main group, project number and sub group
	 *
	 * @return  array  The list of all discovered main groups
	 */
	public function getMainGroups(int $nestingLevel = 3) : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', __CLASS__)));
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();
		$rs         = [];

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

		try
		{
			// Build query.
			$table = Entity::getInstance('article')->getTableName();
			$query = $db->getQuery(true);

			$query
			->from($db->qn($table, 'a'));

			if ($nestingLevel)
			{
				$query
				->select($db->qn('a.number'))
				->select('LEFT(' . $db->qn('a.number') . '   , 3) AS ' . $db->qn('mainGroup'));

				if ($nestingLevel >= 2)
				{
					$query
					->select('MID('  . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('project'));
				}

				if ($nestingLevel >= 3)
				{
					$query
					->select('MID('  . $db->qn('a.number') . ', 9, 2) AS ' . $db->qn('subGroup'));
				}
			}
			else
			{
				$query
				->select('DISTINCT LEFT(' . $db->qn('a.number') . ', 3)');
			}

			$query
			->where($db->qn('a.number') . ' NOT LIKE ' . $db->q('DUMMY-DO-NOT-TOUCH'));

			/*// DiSABLED on 20230430 because worker of Test GmbH & Co. KG could not find its own project(s) listed
			// Ignore Tino's test item(s).
			if (!$user->isProgrammer())
			{
				$query
				->where($db->qn('a.number') . ' NOT LIKE ' . $db->q('AAA.BBB.%'));
			}*/

			// Add grouping and ordering.
			$query
			->order($db->qn('a.number'));

			// Execute query.
			if ($nestingLevel)
			{
				$tmp = [];

				$rs = $db->setQuery($query)->loadObjectList();

				array_walk($rs, function($row) use (&$nestingLevel, &$tmp)
				{
					$tmp[$row->mainGroup] = $tmp[$row->mainGroup] ?? [];

					switch (true)
					{
						case ($nestingLevel >= 3) :
							$tmp[$row->mainGroup][$row->project][$row->subGroup]   = $tmp[$row->mainGroup][$row->project][$row->subGroup] ?? [];
							$tmp[$row->mainGroup][$row->project][$row->subGroup][] = $row->number;
						break;

						case ($nestingLevel == 2) :
							$tmp[$row->mainGroup][$row->project]   = $tmp[$row->mainGroup][$row->project] ?? [];
							$tmp[$row->mainGroup][$row->project][] = $row->number;
						break;

						default :
							$tmp[$row->mainGroup][] = $row->number;
					}
				});

				$rs = $tmp;

				unset($tmp);
			}
			else
			{
				$rs = $db->setQuery($query)->loadColumn();
			}
		}
		catch (Exception $e)
		{
			// Log error.
			$this->logger->log('error', __METHOD__, ['error' => $e->getMessage()]);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_DATABASE_GET_MAIN_GROUPS_TEXT', $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rs;
	}

    public function getMainGroupsNew(int $nestingLevel = 3) //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        $db = $this->db;
        // Get current user object.
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        $query = $db->getQuery(true);
        // Build query.
        $query->select($db->qn('a.group_name'))
            ->from($db->qn('maingroups', 'a'))
            ->order($db->qn('a.group_name'));

        // Execute query.
        try
        {
            // Set the query
            $db->setQuery($query);

// Execute the query and fetch all results
            $artNum = $db->loadColumn();
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $artNum = null;
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return $artNum;
    }
    public function getMainGroupsMike(int $nestingLevel = 3) //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        $db = $this->db;
        // Get current user object.
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        $query = $db->getQuery(true);
        // Build query.
        $query->select('*')
            ->from($db->qn('maingroups', 'a'))
            ->order($db->qn('a.group_name'));

        // Execute query.
        try
        {
            // Set the query
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $artNum = null;
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return $artNum;
    }
    public function getMainGroupsMike2() //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        $db = $this->db;
        // Get current user object.
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        $query = $db->getQuery(true);
        // Build query.
        $query->select('*')
            ->from($db->qn('maingroup_assigns', 'a'))
            //->where($db->qn('a.mgid') . ' = ' . $db->q($mgid))
            ->order($db->qn('a.mgid'));

        // Execute query.
        try
        {
            // Set the query
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $artNum = null;
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return $artNum;
    }
}
