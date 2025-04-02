<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_reverse;
use function array_walk;
use function is_a;
use function is_null;
use function mb_strlen;

/**
 * Class description
 */
class Parts extends ListModel
{
	protected $tableName = 'part';

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

	//@todo - port to JDatabaseQuery
	public function getList($partID = null, $artID = null) : array
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

		$articleModel = $this->getInstance('article', ['language' => $this->language]);
		$partModel    = $this->getInstance('part',    ['language' => $this->language]);

		$partID = (is_null($partID) ? $partID : (int) $partID);
		$artID  = (is_null($artID)  ? $artID  : (int) $artID);

		// Prepare sub-query to fetch previously tracked information first.
		// This fetches tracking data for every tracked process as a ready to use JSON string.
		// TODO - migrate to use JDatabaseQuery
		$sub1   = "SELECT
				  	`t`.`partID`,
				  	CONCAT(
						'\"', `t`.`procID`, '\"', ':',
						CONCAT(
							'{',
							GROUP_CONCAT(
								CONCAT_WS(':',
									CONCAT('\"', `t`.`paramID`, '\"'),
									CASE WHEN `t`.`paramValue` IS NULL OR `t`.`paramValue` = '' THEN CONCAT('\"', '\"') ELSE CONCAT('\"', TRIM(`t`.`paramValue`), '\"') END
								)
							),
							'}'
						)
					) AS `techParams1`
				  	FROM `tracking` `t` ";
		$sub1  .= "WHERE 1 ";

		$sub1  .= (is_null($partID) ? '' : "AND `t`.`partID` = '$partID' ");
		$sub1  .= "GROUP BY `t`.`partID`, `t`.`procID`";

		// Further improve sub-query $sub1.
		// This creates a single JSON string from the single JSON strings of query $sub1.
		// TODO - migrate to use JDatabaseQuery
		$sub2  = "SELECT
				  	`s1`.`partID`,
				  	CONCAT('{', GROUP_CONCAT(`s1`.`techParams1`), '}') AS `techParams2`
				  	FROM ( $sub1 ) `s1` ";
		$sub2  .= "WHERE 1 ";
		$sub2  .= "GROUP BY `s1`.`partID`";

		// Build main query.
		// TODO - migrate to use JDatabaseQuery
		$query  = "SELECT
					  `p`.`partID`,
					  `p`.`artID`,
					  `p`.`sample`,
					  `p`.`trackingcode`,
					  `p`.`lotID`,
					  `p`.`blocked`,
					  `p`.`trashed`,
					  `p`.`created`,
					  `p`.`created_by`,
					  `p`.`modified`,
					  `p`.`modified_by`,
					  `p`.`deleted`,
					  `p`.`deleted_by`,
					  `a`.`number` AS `type`,
					  `am`.`language`,
					  `l`.`number` AS `lotNumber`,
					  CASE WHEN
				  		NULLIF(`t`.`partID`, NULL) IS NULL
					  THEN
				  		'[]'
					  ELSE
				  		CONCAT('', `t`.`techParams2`, '')
					  END AS `trackingData`
				  FROM `parts` `p`
				  LEFT JOIN `lots`          `l` ON `p`.`lotID`  = `l`.`lotID`
				  LEFT JOIN `articles`      `a` ON `p`.`artID`  = `a`.`artID`
				  LEFT JOIN `article_meta` `am` ON (`a`.`artID` = `am`.`artID` AND `am`.`language` = '$this->language' )
				  LEFT JOIN ( $sub2  )  `t` ON `p`.`partID` = `t`.`partID`
				  WHERE 1 ";

		$query .= (is_null($partID) ? '' : "AND `p`.`partID` = '$partID' ");
		$query .= (is_null($artID)  ? '' : "AND `a`.`artID`  = '$artID' ");

		// Only superusers must be allowed to see deleted items.
		// TODO - migrate to use JDatabaseQuery
		if (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_SUPERUSER))
		{
			$query .= 'AND `p`.`trashed` IN(' . implode(',', ['0','1']) . ') ';
		}
		else
		{
			$query .= 'AND `p`.`trashed` = ' . $db->q('0') . ' ';
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_MANAGER)))
		{
			$query .= 'AND `p`.`blocked` = ' . $db->q('0') . ' ';
			// $query .= 'AND `a`.`blocked` = ' . $db->q('0');	// Shouldn't this be considered too?
		}

		// TODO - migrate to use JDatabaseQuery
		$query .= 'GROUP BY `p`.`partID` ';
		$query .= 'ORDER BY `p`.`created` ';

		$rows = [];

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Convert from JSON to String.
				// Do it here instead in entity, because the real array format is required from list items as well
				// and the list does no longer contain Entities but just plain arrays.
				if (array_key_exists('trackingData', $row))
				{
					$row['trackingData'] = (array) json_decode($row['trackingData'], null, 512, JSON_THROW_ON_ERROR);
				}

				// Add parent class' process list.
				$articleProcesses = (array) $articleModel->getArticleProcesses($row['artID']);

				$row['processes'] = $articleProcesses;

				// Add measured data.
				$measuredData = (array) $partModel->getMeasuredData($row['partID']);

				$row['measuredData'] = $measuredData;

				$rows[$row['partID']] = $row;
			}

			// Free memory.
			unset($articleModel);
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

		// Sort array in reverse order to have the latest data output first.
		return array_reverse($rows, true);
	}
	public function getListNEW(/*$partID = null, $artID = null*/) : array	// refactoring of 'getList' that is available to developers only. Shall replace 'getList' once refactoring is finished.
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*//-> BEGIN   Debug who's calling this function.
		$trace  = debug_backtrace();
		$called = current($trace);
		$caller = next($trace);

		$this->logger->log(
			'info',
			sprintf('%s() was called in %s::%s() in line %d ( %s )', __METHOD__, $caller['class'], $caller['function'], $caller['line'], $caller['file']),
			$called['args']
		);
		//-> END   Debug who's calling this function*/

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// @debug
		$debug  = $user->isProgrammer();
		$debug  = false;

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
		$filter      = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$partID      = ArrayHelper::getValue($args, 'partID');
//		$partID      = (is_null($partID)) ? null    : (int) $partID;
		$partID      = (is_null($partID)  ? $partID : (int) $partID);
		$artID       = ArrayHelper::getValue($args, 'artID');
//		$artID       = (is_null($artID))  ? null    : (int) $artID;
		$artID       = (is_null($artID)   ? $artID  : (int) $artID);
		$lang        = ArrayHelper::getValue($args, 'language');
		$lang        = (is_null($lang))   ? $this->language : trim($lang);
		$language    = $this->getInstance('language')->getLanguageByTag($lang);

		/*
		// @debug
		if ($debug) :
			echo '<pre><strong>fn args: </strong>' . print_r(json_encode(['filter' => $filter, 'partID' => $partID, 'artID'  => $artID]), true) . '</pre>';
//			die;
		endif;
		*/

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
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		$articleModel = $this->getInstance('article', ['language' => $this->language]);
		$partModel    = $this->getInstance('part',    ['language' => $this->language]);

		// Build sub query 1.
		// TODO - involve new Table class once it'll be available
//		$table = Table::getInstance('tracking');	// use getTableName() and getPrimaryKeyName()
		$tableName = 'tracking';
		$sub1 = $db->getQuery(true)
		->from($db->qn($tableName, 't'))
		->where(true)
		->select($db->qn('t.partID'))
		->select(" CONCAT(
			'\"', `t`.`procID`, '\"', ':',
				CONCAT(
					'{',
					GROUP_CONCAT(
						CONCAT_WS(':',
							CONCAT('\"', `t`.`paramID`, '\"'),
							CASE WHEN `t`.`paramValue` IS NULL OR `t`.`paramValue` = '' THEN CONCAT('\"', '\"') ELSE CONCAT('\"', TRIM(`t`.`paramValue`), '\"') END
						)
					),
					'}'
				)
			) AS `techParams1`")
		->group($db->qn(['t.partID', 't.procID']));

		if (!is_null($partID))
		{
			$sub1
			->where($db->qn('t.partID') . ' = ' . $partID);
		}

		// Build sub query 2.
		$sub2 = $db->getQuery(true)
		->from($sub1, 's1')
		->where(true)
		->select($db->qn('s1.partID'))
		->select(" CONCAT('{', GROUP_CONCAT(`s1`.`techParams1`), '}') AS `techParams2`")
		->group($db->qn('s1.partID'));

		// Build main query.
		// TODO - involve new Table class once it'll be available
//		$table = Table::getInstance($entityName);	// use getTableName() and getPrimaryKeyName()
		$tableName = 'parts';
		$query  = $db->getQuery(true)
		->from($db->qn($tableName, 'p'))
		->leftJoin($db->qn('lots',          'l') . ' ON ' . $db->qn( 'p.lotID') . ' = ' . $db->qn( 'l.lotID'))
		->leftJoin($db->qn('articles',      'a') . ' ON ' . $db->qn( 'p.artID') . ' = ' . $db->qn( 'a.artID'))
		->leftJoin($db->qn('article_meta', 'am') . ' ON (' . $db->qn('a.artID') . ' = ' . $db->qn('am.artID') . ' AND ' . $db->qn('am.language') . ' = ' . $db->q($this->language) . ')')
		->leftJoin('(' . $sub2->__toString() . ') AS ' . $db->qn('t') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('t.partID'))
		->select(
			$db->qn([
				'p.partID',
				'p.artID',
				'p.sample',
				'p.trackingcode',
				'p.lotID',
				'p.blocked',
				'p.trashed',
				'p.created',
				'p.created_by',
				'p.modified',
				'p.modified_by',
				'p.deleted',
				'p.deleted_by',
			])
		)
		->select($db->qn('a.number') . ' AS ' . $db->qn('type'))
		->select($db->qn('am.language'))
		->select($db->qn('l.number') . ' AS ' . $db->qn('lotNumber'))
		->select("CASE WHEN NULLIF(`t`.`partID`, NULL) IS NULL THEN '[]' ELSE CONCAT('', `t`.`techParams2`, '') END AS `trackingData`")
		->group($db->qn('p.partID'))
		->order($db->qn('p.created'))
		->where(true);

		if (!is_null($partID))
		{
			$query
			->where($db->qn('p.partID') . ' = ' . $partID);
		}

		if (!is_null($artID))
		{
			$query
			->where($db->qn('a.artID') . ' = ' . $artID);
		}

		// Only superusers must be allowed to see deleted items.
		if (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_SUPERUSER))
		{
			$query
			->where($db->qn('p.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
		}
		else
		{
			$query
			->where($db->qn('p.trashed') . ' = ' . $db->q('0'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_MANAGER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'))
//			->where($db->qn('a.blocked') . ' = ' . $db->q('0'))	// Shouldn't this be considered too?
			;
		}

		// @debug
		if ($debug) :
			echo '<pre>SQL: ' . print_r($query->dump(), true) . '</pre>';
			die;
		endif;

		$rows = [];

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Add parent class' process list.
				$articleProcesses = (array) $articleModel->getArticleProcesses($row['artID']);

				// Convert from JSON to String.
				// Do it here instead in entity, because the real array format is required from list items as well
				// and the list does no longer contain Entities but just plain arrays.
				if (array_key_exists('trackingData', $row))
				{
					$row['trackingData'] = (array) json_decode($row['trackingData'], null, 512, JSON_THROW_ON_ERROR);
				}

				// Fetch last approval time to every process, if there is any.
				$procIDs       = array_keys($row['trackingData']);

				$approvalTimes = (array) $partModel->getApprovalTimes($row['partID'], ['procIDs' => $procIDs]);

				// Merge information about approvals per process into every process' data.
				foreach ($articleProcesses as $pid => $registry)
				{
					// Fetch approval timestamps for this process.
					$list = ArrayHelper::getValue($approvalTimes, $pid, [], 'ARRAY');

					// Add to process information.
//					$registry->set('approval.approvals', $list);
					$registry->set('approval', $list);

					// Add last approval timestamp as quick access information.
//					$registry->set('approval.last', end($list));
				}

				$row['processes'] = $articleProcesses;

				// Add measured data.
				$measuredData = (array) $partModel->getMeasuredData($row['partID']);

				$row['measuredData'] = $measuredData;

				$rows[$row['partID']] = $row;
			}

			// Free memory.
			unset($articleModel);
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

		// Sort array in reverse order to have the latest data output first.
		return array_reverse($rows, true);
	}

	// FIXME
	public function findPartsOLD($term = null)	// FIXME - this function serves only to customers/suppliers ... merge with findPartsNEW() to get rid of this one
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (empty($term))
		{
			/*Messager::setMessage([
				'type' => 'info',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_ENTER_A_SEARCH_TERM_TEXT', $this->language)
			]);*/

			return null;
		}

		if (in_array(trim($term), ['%', '000']))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_TYPE_A_MORE_SPECIFIC_SEARCH_TERM_TEXT', $this->language)
			]);

			return null;
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

		// Get user organisation information.
		$orgID    = $user->get('orgID');
		$projects = $this->getInstance('organisation', ['language' => $this->language])->getOrganisationProjectsNEW(['orgID' => $orgID]);

		$tmp = [];

		// Build filter for parts a user (when is a customer) is allowed to find.
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			array_walk($projects, function($project) use(&$tmp, &$user, &$orgID)
			{
				$project = new Registry($project);

				// If the project is not blocked...
				if (!$project->get('blocked'))
				{
					$tmp[] = $project->get('number');
				}
			});
		}
		else
		{
			$tmp = array_column($projects, 'number');
		}

		// Create comma separated list ready to use in database query.
		$userProjects = implode(',', $tmp);

		// Free memory.
		unset($projects);
		unset($tmp);

		// Deny search for universal placeholder '%'.
		$term = trim($term == '%' ? null : $term);

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->join('LEFT', $db->qn('lots')     . ' AS ' . $db->qn('l') . ' ON ' . $db->qn('p.lotID') . ' = ' . $db->qn('l.lotID'))
		->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID') . ' = ' . $db->qn('a.artID'))
		->select(
			$db->qn([
				'p.partID',
				'p.artID',
				'p.sample',
				'p.trackingcode',
				'p.lotID',
				'p.blocked',
				'p.trashed'
			])
		)
		->select($db->qn('a.number') . ' AS ' . $db->qn('type'))
		->select($db->qn('l.number') . ' AS ' . $db->qn('lotNumber'))
		->select(
			$db->qn([
				'l.blocked',
				'l.blockDate',
				'l.blocked_by'
			])
		);

		// Only superusers must be allowed to see deleted items.
		if (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_SUPERUSER))
		{
			$query
			->where($db->qn('p.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
		}
		else
		{
			$query
			->where($db->qn('p.trashed') . ' = ' . $db->q('0'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			// ->where($db->qn('p.blocked') . ' = ' . $db->q('0'))	// Shouldn't this be considered too?
			->where($db->qn('a.blocked') . ' = ' . $db->q('0'));
		}

		// If the search term is not the universal placeholder '%', apply projects filter.
		// Match project number in article number.
		if (!is_null($term) && mb_strlen($term) > 0)
		{
			// TODO - migrate to Joomla findInSet()
			$query
			->where('FIND_IN_SET( SUBSTR(' . $db->qn('a.number') . ', 5, 3), ' . $db->q($userProjects) . ' )');
		}

		$query
		->where('(
			(' . $db->qn('a.number')       . ' LIKE "%' . $db->escape(trim($term)) .'%") OR
			(' . $db->qn('p.trackingcode') . ' LIKE "%' . $db->escape(trim($term)) .'%")
		)');

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['partID']] = $row;
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
	 * Returns a list of parts filtered by user access rights and optionally passed search term.
	 *
	 * @return  array|null
	 */
	public function findParts() : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$search = ArrayHelper::getValue($args, 'search', '', 'STRING');
		$search = (is_null($search)) ? null : (new InputFilter)->clean($search);
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);

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
		$projects = $this->getInstance('organisation', ['language' => $this->language])->getOrganisationProjectsNEW2(['orgID' => $orgID]);

       // echo $orgID;echo "<pre>";print_r($projects);exit;
		$tmp = [];
        //echo $user->getFlags();
		// Build filter for parts a user (when is a customer/supplier) is allowed to find.
		if ($user->getFlags() < Access\User::ROLE_PROGRAMMER)
		{
			array_walk($projects, function($project) use(&$tmp, &$user, &$orgID)
			{
				$project = new Registry($project);

				// If the project is not blocked...
				if (!$project->get('blocked'))
				{
					$tmp[] = $project->get('number');
				}
			});
            //echo "hlo";
            //echo "<pre>";print_r($tmp);exit;
		}
		else
		{
			$tmp = array_column($projects, 'number');
            //echo "<pre>";print_r($tmp);exit;
		}

		// Create comma-separated list from allowed projects ready to use as a filter in the database query.
		$userProjects = implode(',', $tmp);


		// Free memory.
		/*unset($projects);
		unset($tmp);*/
        unset($projects, $tmp);

		// Deny search for universal placeholder '%'.
		$search = trim($search == '%' ? null : $search);

		// Build query.
		 $query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->join('LEFT', $db->qn('lots')     . ' AS ' . $db->qn('l') . ' ON ' . $db->qn('p.lotID') . ' = ' . $db->qn('l.lotID'))
		->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID') . ' = ' . $db->qn('a.artID'))
		->select([
            $db->qn('p.partID'),
            $db->qn('p.artID'),
            $db->qn('p.sample'),
            $db->qn('p.trackingcode'),
            $db->qn('p.lotID'),
            $db->qn('a.number') . ' AS ' . $db->qn('type'),
            $db->qn('l.number') . ' AS ' . $db->qn('lotNumber')
        ]);

        if (!is_null($search) && mb_strlen($search) > 0)
        {
            // Convert $userProjects to an array if it's a comma-separated string
            $userProjectsArray = explode(',', $userProjects);

            // If the userProjectsArray is not empty, modify the query
            if (!empty($userProjectsArray)) {
                // Use the IN clause with SUBSTRING and the prepared list of projects
                $projectList = implode(',', array_map([$db, 'q'], $userProjectsArray));

                 $query->where('SUBSTR(' . $db->qn('a.number') . ', 5, 3) IN (' . $projectList . ')');
            }
        }

        $searchTerm = trim($search);
        $escapedSearch = $db->escape($searchTerm);

         $query->where('(
            ' . $db->qn('a.number')       . ' LIKE "' . $escapedSearch . '%" OR
            ' . $db->qn('p.trackingcode') . ' LIKE "' . $escapedSearch . '%"
        )');

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();
            //echo "<pre>";print_r($rs);exit;
			foreach ($rs as $row)
			{
				$rows[$row['partID']] = $row;
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
    public function findPrevParts($artID, $procID, $mpID, $partID, $lotid, $artLimis) //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Get additional function args.
        $args = func_get_args();
        $args = (array) array_shift($args);

        // There may be arguments for this function.
        $search = ArrayHelper::getValue($args, 'search', '', 'STRING');
        $search = (is_null($search)) ? null : (new InputFilter)->clean($search);
        //echo $search;

        // Init shorthand to database object.
        $db = $this->db;

        /* Force UTF-8 encoding for proper display of german Umlaute
         * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
         */
        $db->setQuery('SET NAMES utf8')->execute();

       // $totalRecentParts = $this->getInstance('part', ['language' => $this->language])->getTFTM($artID,$procID,$partID,$lotid);
        //$totalRecentParts = 500;
        //echo $totalRecentParts[0];

        /*-------    --------*/
        $artNum1 = [];
        $artNum2 = [];

            $trackedParts = $db->getQuery(true)
                 ->from($db->qn('tracking', 't'))
                 ->join('LEFT', $db->qn('parts') . ' AS ' . $db->qn('p')
                     . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('t.partID'))
                 ->select($db->qn([
                     't.partID',
                 ]))
                 ->where([
                     $db->qn('t.procID') . ' = ' . $db->q(trim($procID)),
                     $db->qn('t.paramID') . ' = ' . $db->q(trim(3)),
                     $db->qn('p.artID')       . ' = ' . $db->q(trim($artID)),
                     $db->qn('p.trashed')       . ' = ' . $db->q(trim(0)),
                     $db->qn('p.blocked')       . ' = ' . $db->q(trim(0)),
                //->where($db->qn('t.timestamp') . ' <= NOW()')
                $db->qn('t.timestamp') . ' <= NOW()',
                $db->qn('t.timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                //$db->qn('t.timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 2 MONTH)',
                 ])
            ->order($db->qn('t.timestamp') . ' DESC')->setLimit($artLimis);

            $mkparts2 = $db->setQuery($trackedParts)->loadAssocList();
            $partIDs2 = array_column($mkparts2, 'partID');
            $whereInClause2 = implode(',', array_map('intval', $partIDs2));

        $artNum = null;
        if(!empty($whereInClause2)) {
             $query1 = $db->getQuery(true)
                ->from($db->qn('part_process_mp_tracking'))
                ->select($db->qn([
                    'partID',
                    'procID',
                    'mpInput',
                    'mp',
                    'timestamp'
                ]))
                ->where([
                    $db->qn('partID') . ' IN (' . $whereInClause2 . ')',
                    $db->qn('procID') . ' = ' . $procID,
                    $db->qn('mp') . ' = ' . "'$mpID'",
                    $db->qn('timestamp') . ' <= NOW()',
                    $db->qn('timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                ])
                ->order($db->qn('timestamp') . ' DESC')->setLimit($artLimis);
            $artNum1s = $db->setQuery($query1)->loadAssocList();
            $artNum1 = array_filter($artNum1s, function($entry) {
                return $entry['mpInput'] != 0;
            });
//print_r($artResults);
            $lastTimestampQr1 = isset($artResults[0]['timestamp']) ? $artResults[0]['timestamp'] : null;
              $query2 = $db->getQuery(true)
                ->from($db->qn('part_process_mp_tracking_count'))
                ->select($db->qn([
                    'partID',
                    'procID',
                    'mpInput',
                    'mp',
                    'timestamp'
                ]))
                ->where([
                    $db->qn('partID') . ' IN (' . $whereInClause2 . ')',
                    $db->qn('procID') . ' = ' . $procID,
                    $db->qn('mp') . ' = ' . "'$mpID'",
                    $db->qn('timestamp') . ' > ' . $db->q($lastTimestampQr1),
                    $db->qn('timestamp') . ' <= NOW()',
                    $db->qn('timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                ])
                ->order($db->qn('timestamp') . ' DESC')->setLimit($artLimis);
            $artNum2 = $db->setQuery($query2)->loadAssocList();
        }
        try {
            $artNum = array_merge_recursive($artNum1, $artNum2);
            //print_r($artNum);
            $partIDs = array_column($artNum, 'partID');
            $partIDsList = implode(',', $partIDs);
             $getTrackCode = $db->getQuery(true)
                ->from($db->qn('parts'))
                ->select($db->qn([
                    'partID',
                    'trackingcode'
                ]))
                ->where([
                    $db->qn('partID') . ' IN (' . $partIDsList . ')'
                ]);
            $artResults = $db->setQuery($getTrackCode)->loadAssocList();
            //echo "<pre>";print_r($artNum1);
            //echo "<pre>";print_r($artNum2);
            //echo "<pre>";print_r($artResults);
        }
        catch (Exception $e) {
            /*Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);*/
            $artResults = null;
        }
        $this->closeDatabaseConnection();
        return $artResults;
    }

	public function getPartsCount(string $projectNumber) : int
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
		$sub = $db->getQuery(true)
		->from($db->qn('articles', 'a'))
		->select($db->qn('a.artID'))
		->where($db->qn('a.number') . ' LIKE "%' . $db->escape($projectNumber) . '%"')
		// Ignore the 'DUMMY'.
		->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)')
		->order($db->qn('a.artID'));

		$query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->select('COUNT(`p`.`partID`)  AS '  . $db->qn('parts'))
		->where($db->qn('p.artID') . ' IN (' . $sub . ')');

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadObjectList();

			foreach ($rs as $row)
			{
				$rows[$row->trackingcode] = trim($row->name);
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = 0;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	public function getPartsByLanguageOrCode($lang = null, $code = null) : array
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
		->from($db->qn('parts', 'p'))
		->select($db->qn('p.trackingcode'));

		switch (true)
		{
			case (!is_null($lang) && !is_null($code)) :
				$query
				->select(
					$db->qn([
						'a.artID',
						'a.number'
					])
				)
				->join('LEFT', $db->qn('articles')     . ' AS ' . $db->qn('a')  . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
				->join('LEFT', $db->qn('article_meta') . ' AS ' . $db->qn('am') . ' ON ' . $db->qn('am.artID') . ' = ' . $db->qn('a.artID'))
				->where($db->qn('am.language')         . '  = ' . $db->q($lang))
				->where($db->qn('p.trackingcode') .  ' LIKE "%' . $db->escape(trim($code)) . '%"');
			break;

			case (!is_null($lang)) :
				$query
				->select(
					$db->qn([
						'a.artID',
						'a.number'
					])
				)
				->join('LEFT', $db->qn('articles')     . ' AS ' . $db->qn('a')  . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
				->join('LEFT', $db->qn('article_meta') . ' AS ' . $db->qn('am') . ' ON ' . $db->qn('am.artID') . ' = ' . $db->qn('a.artID'))
				->where($db->qn('am.language') . ' = ' . $db->q($lang));
			break;

			case (!is_null($code)) :
				$query
				->select(
					$db->qn([
						'a.artID',
						'a.number'
					])
				)
				->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a')  . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
				->where($db->qn('p.trackingcode')  . ' LIKE "%' . $db->escape(trim($code)) . '%"');
			break;

			default :
				$query
				->join('LEFT', $db->qn('article_meta') . ' AS ' . $db->qn('am') . ' ON ' . $db->qn('am.artID') . ' = ' . $db->qn('a.artID'))
				->where($db->qn('am.language') . ' = ' . $db->q($lang ?? $this->language));
			break;
		}

		$query
		->order($db->qn('p.trackingcode'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadObjectList();

			foreach ($rs as $row)
			{
				$rows[$row->trackingcode] = trim($row->name);
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

	public function getPartsByProjectID(int $proID, bool $totalOnly = false)
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

		$proID = (is_null($proID) ? $proID : $proID);

		// Build sub-query first.
		$sub1 = $db->getQuery(true)
		->select($db->qn('number'))
		->from($db->qn('projects'))
		->where($db->qn('proID') . ' = ' . (int) $proID);

		$sub2 = $db->getQuery(true)
		->from($db->qn('articles', 'a'))
		->select($db->qn('a.artID'))
		->where($db->qn('a.number') . " LIKE CONCAT('%', (" . $sub1 . "), '%')")
		// Ignore the 'DUMMY'.
		->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)')
		->order($db->qn('a.artID'));

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('parts')    . ' AS  ' . $db->qn('p'))
		->where($db->qn('p.artID') . ' IN (' . $sub2 . ')');

		if ($totalOnly)
		{
			$query
			->select('COUNT(' . $db->qn('p.partID'). ') AS ' . $db->qn('parts'));
		}
		else
		{
			$query
			->select(
				$db->qn([
					'p.partID',
					'p.artID',
					'p.sample',
					'p.trackingcode',
					'p.lotID',
					'p.blocked',
					'p.created',
					'p.created_by',
					'p.modified',
					'p.modified_by'
				])
			)
			->order($db->qn('p.created'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}

		// Execute query.
		try
		{
			if ($totalOnly)
			{
				$rows = $db->setQuery($query)->loadObject()->parts;
			}
			else
			{
				$articleModel = $this->getInstance('article', ['language' => $this->language]);

				$rows = [];

				$rs = $db->setQuery($query)->loadAssocList();

				foreach ($rs as $row)
				{
					// Add parent class' process list.
					$row['processes'] = $articleModel->getItem((int) $row['artID'])->get('processes', []);

					// FIXME - get rid of loading row data into entity. Just collect object and load on demand where it is required.
					// Load row data into a new entity and add it to the collection.
					$rows[$row['partID']] = Entity::getInstance('part', ['id' => $row['partID'], 'language' => $this->language])->bind( $row );
					// $rows[$row['partID']] = $row;
				}

				// Free memory.
				unset($articleModel);
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

	public function getPartsByProjectNumber(string $proNum) : array
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

		// Build sub-query first.
		$sub = $db->getQuery(true)
		->select($db->qn('a.artID'))
		->from($db->qn('articles')  . ' AS     ' . $db->qn('a'))
		->where($db->qn('a.number') . ' LIKE "%' . $db->escape(trim($proNum)) . '%"')
		// Ignore the 'DUMMY'.
		->where('(CASE WHEN ' . $db->qn('a.name') . ' IS NOT NULL THEN ' . $db->qn('a.name') . ' NOT LIKE "%DUMMY%" ELSE 1 END)')
		->order($db->qn('a.artID'));

		// Build query.
		$query = $db->getQuery(true)
		->select(
			$db->qn([
				'p.partID',
				'p.artID',
				'p.sample',
				'p.trackingcode',
				'p.lotID',
				'p.blocked',
				'p.created',
				'p.created_by',
				'p.modified',
				'p.modified_by'
			])
		)
		->from($db->qn('parts')    . ' AS  ' . $db->qn('p'))
		->where($db->qn('p.artID') . ' IN (' . $sub . ')');

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}

		$query
		->order($db->qn('p.created'));

		// Execute query.
		try
		{
			$articleModel = $this->getInstance('article', ['language' => $this->language]);

			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Add parent class' process list.
				$row['processes'] = $articleModel->getItem((int) $row['artID'])->get('processes', []);

				// FIXME - get rid of loading row data into entity. Just collect object and load on demand where it is required.
				// Load row data into a new entity and add it to the collection.
				$rows[$row['partID']] = Entity::getInstance('part', ['id' => $row['partID'], 'language' => $this->language])->bind( $row );
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


	public function getPartsBooked(int $artID, string $quality, array $procIDs = [], string $dateFrom = null, string $dateTo = null) : array
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

		$dateFrom = (mb_strlen(trim('' . $dateFrom)) > 0 ? date_create($dateFrom) : null);
		$dateTo   = (mb_strlen(trim('' . $dateTo))   > 0 ? date_create($dateTo)   : null);
		$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$dateTime = $now->format('Y-m-d 00:00:01');
		$dateFrom = (!empty($dateFrom) ? $dateFrom->format('Y-m-d 00:00:01') : null);
		$dateTo   = (!empty($dateTo)   ? $dateTo->format('Y-m-d 23:59:59')   : null);

		$sub3 = $db->getQuery(true)
		->from($db->qn('parts'))
		->select($db->qn('partID'))
		->where($db->qn('artID') . ' = ' . $db->q($artID));

		$sub2 = $db->getQuery(true)
		->from($db->qn('part_process_booked', 'ppb'))
		->select($db->qn('ppb.partID'))
		->where($db->qn('ppb.partID') . ' IN(' . $sub3 . ')');

		if (count($procIDs))
		{
			$sub2
			->where($db->qn('ppb.procID') . ' IN(' . implode(',', $procIDs) . ')');
		}

		$sub1 = $db->getQuery(true)
		->from($db->qn('tracking', 't'))
		->select($db->qn('t.partID'))
		->where($db->qn('t.paramID')    . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
		->where($db->qn('t.paramValue') . ($quality == 'good' ? ' = 0' : ' > 0'));	// 0 = good, >0 = bad

		if (count($procIDs))
		{
			$sub1
			->where($db->qn('t.procID') . ' IN(' . implode(',', $procIDs) . ')');
		}

		$sub1
		->where($db->qn('t.partID') . ' IN(' . $sub2 . ')');

		$query = $db->getQuery(true)
		->select('CONCAT(
			"{",
			CONCAT_WS(":",
				CONCAT(
					\'"\',
					DATE_FORMAT(' . $db->qn('booked') . ', "%Y-%m-%d"),
					\'"\'
				),
				COUNT(' . $db->qn('partID') . ')
			),
			"}"
		) AS ' . $db->qn('parts'))
		->from($db->qn('part_process_booked'))
		->where($db->qn('partID') . ' IN(' . $sub1 . ')');

		if (count($procIDs))
		{
			$procIDs = array_map('intval', $procIDs);

			$query
			->where($db->qn('procID') . ' IN(' . implode(',', $procIDs) . ')');
		}

		if (!empty($dateFrom) && !empty($dateTo))
		{
			$query
			->where($db->qn('booked') . ' BETWEEN ' . $db->q($dateFrom) . ' AND ' . $db->q($dateTo));
		}

		$query
		->group(
			$db->qn([
				'procID',
				'booked'
			])
		);

		// Configure resultset according to from user configuration (fall back to app default).
		// Get user profile and check if its selected app language differs from currently detected language. If so, switch app language to user preference.
		if (is_a($user, 'Nematrack\Entity\User'))
		{
			$userProfile = new Registry(UserHelper::getProfile($user));

			$query
			->order($db->qn('booked') . ' ' . $userProfile->get('parts.book.retrospective.ordering', 'DESC'))
			->setLimit($userProfile->get('parts.book.retrospective.limit', '5'));
		}

		// Execute query.
		try
		{
			$rs  = $db->setQuery($query)->loadColumn();

			$tmp = [];

			array_walk($rs, function($row) use(&$tmp)
			{
				$tmp = array_merge($tmp, (array) json_decode($row, null, 512, JSON_THROW_ON_ERROR));
			});

			$rows = $tmp;
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

	public function getPartsBookedCount(int $artID, string $quality, array $procIDs = [], string $dateFrom = null, string $dateTo = null) : int
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

		$dateFrom = (mb_strlen(trim('' . $dateFrom)) > 0 ? date_create($dateFrom) : null);
		$dateTo   = (mb_strlen(trim('' . $dateTo))   > 0 ? date_create($dateTo)   : null);
//		$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
//		$dateTime = $now->format('Y-m-d H:i:s');
		$dateFrom = (!empty($dateFrom) ? $dateFrom->format('Y-m-d 00:00:01') : null);
		$dateTo   = (!empty($dateTo)   ? $dateTo->format('Y-m-d 23:59:59')   : null);

		$sub = $db->getQuery(true)
		->select($db->qn('partID'))
		->from($db->qn('parts'))
		->where($db->qn('artID') . ' = ' . $artID);

		$query = $db->getQuery(true)
		->select('COUNT(' . $db->qn('partID') . ') AS ' . $db->qn('parts'))	// requires GROUP BY procID
		->from($db->qn('part_process_booked'))
		->where($db->qn('partID') . ' IN(' . $sub . ')');

		if (count($procIDs))
		{
			$procIDs = array_map('intval', $procIDs);

			$query
			->where($db->qn('procID') . ' IN(' . implode(',', $procIDs) . ')');
		}

		if (!empty($dateFrom) && !empty($dateTo))
		{
			$query
			->where($db->qn('booked') . ' BETWEEN ' . $db->q($dateFrom) . ' AND ' . $db->q($dateTo));
		}

		$query
		->group($db->qn('procID'));

		// Execute query.
		try
		{
			$numRows = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$numRows = 0;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return (int) $numRows;
	}


	public function getPartsUnbooked(int $artID, string $quality, array $procIDs = [], string $dateFrom = null, string $dateTo = null) : array
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

		$procIDs  = array_map('intval', $procIDs);

		$dateFrom = (mb_strlen(trim('' . $dateFrom)) > 0 ? date_create($dateFrom) : null);
		$dateTo   = (mb_strlen(trim('' . $dateTo))   > 0 ? date_create($dateTo)   : null);
		$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$dateTime = $now->format('Y-m-d H:i:s');
		$dateFrom = (!empty($dateFrom) ? $dateFrom->format('Y-m-d 00:00:01') : null);
		$dateTo   = (!empty($dateTo)   ? $dateTo->format('Y-m-d 23:59:59')   : null);

		$sub2 = $db->getQuery(true)
		->select($db->qn('partID'))
		->from($db->qn('parts'))
		->where($db->qn('artID') . ' = ' . $db->q($artID));

		$sub1 = $db->getQuery(true)
		->from($db->qn('part_process_unbooked', 'ppu'))
		->select($db->qn('ppu.partID'))
		->where($db->qn('ppu.partID') . ' IN(' . $sub2 . ')');

		if (count($procIDs))
		{
			$sub1
			->where($db->qn('ppu.procID') . ' IN(' . implode(',', $procIDs) . ')');
		}

		$query = $db->getQuery(true)
		->from($db->qn('tracking', 't'))
		->select($db->qn('t.partID'))
		->where($db->qn('t.paramID')    . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
		->where($db->qn('t.paramValue') . ($quality == 'good' ? ' = 0' : ' > 0'));	// 0 = good, >0 = bad

		if (count($procIDs))
		{
			$query
			->where($db->qn('t.procID') . ' IN(' . implode(',', $procIDs) . ')');
		}

		$query
		->where($db->qn('t.partID') . ' IN(' . $sub1 . ')');

		// Execute query.
		try
		{
			$rows = (array) $db->setQuery($query)->loadColumn();
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

	public function getPartsUnbookedCount(int $artID, string $quality, array $procIDs = [], string $dateFrom = null, string $dateTo = null) : int
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

		$procIDs  = array_map('intval', $procIDs);

		$dateFrom = (mb_strlen(trim('' . $dateFrom)) > 0 ? date_create($dateFrom) : null);
		$dateTo   = (mb_strlen(trim('' . $dateTo))   > 0 ? date_create($dateTo)   : null);
		$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$dateTime = $now->format('Y-m-d H:i:s');
		$dateFrom = (!empty($dateFrom) ? $dateFrom->format('Y-m-d 00:00:01') : null);
		$dateTo   = (!empty($dateTo)   ? $dateTo->format('Y-m-d 23:59:59')   : null);

		$sub2 = $db->getQuery(true)
		->select($db->qn('partID'))
		->from($db->qn('parts'))
		->where($db->qn('artID') . ' = ' . $artID);

		$sub1 = $db->getQuery(true)
		->from($db->qn('part_process_unbooked', 'pp'))
		->select($db->qn('pp.partID'))
		->where($db->qn('pp.partID') . ' IN(' . $sub2 . ')');

		$query = $db->getQuery(true)
		->from($db->qn('tracking', 't'))
		->select('COUNT(' . $db->qn('t.partID') . ') AS ' . $db->qn('parts'))
		->where($db->qn('t.paramID')    . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
		->where($db->qn('t.paramValue') . ($quality == 'good' ? ' = 0' : ' > 0'));	// 0 = good, >0 = bad

		if (count($procIDs))
		{
			$query
			->where($db->qn('t.procID') . ' IN( ' . implode(',', $procIDs) . ' )');
		}

		$query
		->where($db->qn('t.partID') . ' IN(' . $sub1 . ')');

		// Execute query.
		try
		{
			$numRows = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$numRows = 0;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return (int) $numRows;
	}


	public function book(int $artID, string $quality, int $procID, ...$args) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$dateTime = $now->format('Y-m-d');

		// Get additional function args.
		$xtraArgs = (func_num_args() > 1 ? func_get_arg(2) : []);
		$xtraArgs = new Registry($xtraArgs);

		/* 1st - find parts in table 'part_process_unbooked' */

		// Build sub-queries first.
		$sub1 = $db->getQuery(true)
		->select($db->qn('partID'))
		->from($db->qn('parts'))
		->where($db->qn('artID')      . ' = ' . $db->q($artID));

		// Build sub-queries first.
		$sub2 = $db->getQuery(true)
		->select($db->qn('partID'))
		->from($db->qn('tracking'))
		->where($db->qn('partID')     . ' IN(' . $sub1 . ')')
		->where($db->qn('procID')     . ' = ' . $db->q($procID))
		->where($db->qn('paramID')    . ' = ' . $db->q('6'))
		->where($db->qn('paramValue') . (strtolower($quality) == 'good' ? ' = ' : (strtolower($quality) == 'bad' ? ' > ' : ' = ')) . $db->q('0'));

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('partID'))
		->from($db->qn('part_process_unbooked'))
		->where($db->qn('procID') . ' = ' . $db->q($procID))
		->where($db->qn('partID') . ' IN(' . $sub2 . ')');

		$rows = [];
		$diff = (int) $xtraArgs->get('parts');

		// Execute query.
		try
		{
			$rows = $db->setQuery($query)->loadColumn();

			// Update difference count.
			$diff -= count((array) $rows);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		/* 2nd - move parts from table 'part_process_unbooked' to table 'part_process_booked' */

		// Store article processes.
		$tuples = [];

		// Prepare artID<-->procID tuples.
		array_walk($rows, function($partID) use(&$tuples, &$procID, &$userID)
		{
			$tuples[]  = (int) $partID . ',' . $procID . ',' . (int) $userID;
		});

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('part_process_booked'))
		->columns(
			$db->qn([
				'partID',
				'procID',
				'booked_by'
			])
		)
		->values($tuples);

		// Execute query.
		try
		{
			if (count($tuples))
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

			return false;
		}

		/* 3rd - delete moved parts from table 'part_process_unbooked' */

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('part_process_unbooked'))
		->where($db->qn('procID') . ' = ' . $procID)
		->where($db->qn('partID') . ' IN (' . implode(',', $rows) . ')');

		// Execute query.
		try
		{
			if (count($rows))
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

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		// TODO - decide whether to return $diff if it is > 0.

		return true;
	}
}
