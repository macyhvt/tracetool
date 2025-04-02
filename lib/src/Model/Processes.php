<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function array_filter;
use function array_walk;
use function is_null;
use function is_string;
use const true;

// FIXME - Simultaneous creation of a process WITH parameters throws an error for saving the parameters (but subsequent creation/assignment works)

/**
 * Class description
 */
class Processes extends ListModel
{
	protected $tableName = 'process';

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
	 * Returns a list of items filtered by user access rights.
	 *
	 * @return  array
	 *
	 * @uses   {@link StringInflector}
	 */
	public function getList() : array
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
		$filter      = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$procID      = ArrayHelper::getValue($args, 'procID');
		$procID      = (is_null($procID)) ? null : (int) $procID;
		$lang        = ArrayHelper::getValue($args, 'language');
		$lang        = (is_null($lang)) ? $this->language : trim($lang);
		$language    = $this->getInstance('language')->getLanguageByTag($lang);
		$withCatalog = ArrayHelper::getValue($args, 'catalog',  false, 'BOOL');  // FIXME - find all calls for getList(null, null, null, true) and fix call
		$withParams  = ArrayHelper::getValue($args, 'params',   false, 'BOOL');  // FIXME - find all calls for getList(null, null, true) and fix call

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

		// Build query.
		$table = Entity::getInstance($entityName)->getTableName();
		$query = $db->getQuery(true)
		->from($db->qn($table, 'p'))
		->join('LEFT', $db->qn('process_meta')         . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('pm.' . $pkName))
		->join('LEFT', $db->qn('process_organisation') . ' AS ' . $db->qn('po') . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('po.' . $pkName))
		->select(
			$db->qn([
				'p.' . $pkName,
				'p.abbreviation',
				'p.config',
				'p.blocked',
				'p.blockDate',
				'p.blocked_by',
				'p.archived',
				'p.archiveDate',
				'p.archived_by',
				'p.created',
				'p.created_by',
				'p.modified',
				'p.modified_by',
				'p.trashed',
				'p.trashed_by',
				'p.deleted',
				'p.deleted_by',
//				'p.ordering',		// DiSABLED on 2023-09-12 - because this column is nowhere used and therefore dropped in the database
				'pm.name'
			])
		)
		->select("TRIM(
				CASE
				WHEN
					NULLIF(`pm`.`description`, NULL) IS NULL
				THEN
					''
				ELSE
					`pm`.`description`
				END
			) AS `description`"
		)
		->select('CONCAT( "[", GROUP_CONCAT( DISTINCT ' . $db->qn('po.orgID') . '), "]" ) AS ' . $db->qn('organisations'));

		// Extend query if error catalog should be included.
		if ($withCatalog)
		{
			$query
			->select("CASE
				WHEN
					NULLIF(`e`.`errID`, NULL) IS NULL
				THEN
					NULL
				ELSE
					GROUP_CONCAT( DISTINCT CONCAT_WS(':', `e`.`errID`,
						CONCAT(
							'{',
								'\"name\"', ':', '\"', TRIM( `e`.`name` ), '\",',
								'\"description\"', ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`description`, NULL) IS NULL THEN '' ELSE `e`.`description` END ), '\"',
							'}'
						)
					) SEPARATOR '|' )
				END
				AS `error_catalog`"
			)
			->join('LEFT', $db->qn('process_error') . ' AS ' . $db->qn('pe') . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('pe.' . $pkName))
			->join('LEFT', $db->qn('errors')        . ' AS ' . $db->qn('e')  .
				' ON ( ' .
					$db->qn('e.errID')  . ' = '  . $db->qn('pe.errID') .
				' AND ' .
					$db->qn('e.lngID') . ' = '   . ArrayHelper::getValue($language, 'lngID', 0, 'INT') .
				')'
			);
		}

		// Extend query if technical parameters should be included.
		if ($withParams)
		{
			$query
			->select("CASE
				WHEN
					NULLIF(`tp`.`paramID`, NULL) IS NULL
				THEN
					NULL
				ELSE
					GROUP_CONCAT( DISTINCT CONCAT_WS(':', `tp`.`paramID`,
						CONCAT(
							'{',
							'\"name\"', ':', '\"', TRIM( `tp`.`name` ), '\"',
							'}'
						)
					) SEPARATOR '|' )
				END
				AS `tech_params`"
			)
			->join('LEFT', $db->qn('process_techparameter') . ' AS ' . $db->qn('t')  . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('t.' . $pkName))
			->join('LEFT', $db->qn('techparameters')        . ' AS ' . $db->qn('tp') .
				' ON ( ' .
					$db->qn('tp.paramID')  . ' = ' . $db->qn('t.paramID') .
				' AND ' .
					$db->qn('tp.language') . ' = ' . $db->q($lang ?? $this->language) .
				')'
			);
		}

		// Limit results to selected application language.
		$query
		->where($db->qn('pm.language') . ' = ' . $db->q($lang));

		/*// Only high privileged users must be allowed to see ALL items.
		if (!empty($user) && $user->getFlags() >= Access\User::ROLE_ADMINISTRATOR)
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
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}*/

		// Limit results to passed item ID.
		if (!is_null($procID))
		{
			$query
			->where($db->qn('p.' . $pkName) . ' = ' . $procID);
		}

		// Apply status filter to control access.
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

		/*// Ignore Tino's test item when user is no programmer and not Max Mustermann (the dummy user for testing).
		if (!$user->isProgrammer())
		{
			if (mb_strtolower($user->get('fullname')) != 'max mustermann')
			{
				$query
				->where($db->qn('p.abbreviation') . ' <> ' . $db->q('tst'));
			}
		}*/

		// Add grouping and ordering.
		$query
		->group($db->qn('p.' . $pkName))
//		->order($db->qn('pm.name'));
		->order($db->qn('p.abbreviation'));

		// Execute query.
		try
		{
			$rows = [];

			// Get static technical parameters (Forced metadata, that kind of params a user cannot edit).
			$statTechParams = $this->getInstance('techparams', ['language' => $this->language])->getStaticTechnicalParameters();

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Convert from JSON to String.
				// Do it here instead in entity, because the real array format is required from list items as well
				// and the list does no longer contain Entities but just plain arrays.
				if (isset($row['organisations']))
				{
					$row['organisations'] = (array) json_decode($row['organisations'], null, 512, JSON_THROW_ON_ERROR);

					array_map('intval', $row['organisations']);
				}
				else
				{
					$row['organisations'] = [];
				}

				// Extract tech_params string into array. (identical code as in {@link Nematrack\Entity\Process::bind()})
				if (isset($row['error_catalog']))
				{
					$tmp = [];

					$catalog = ArrayHelper::getValue($row, 'error_catalog');
					$catalog = str_ireplace('},', '}|', $catalog);

					if (is_string($catalog))
					{
						$tmpItems = explode('|', $catalog);
						$tmpItems = array_filter((array) $tmpItems);

						array_walk($tmpItems, function($tmpItem) use(&$tmp)
						{
							// $tmpItem = str_ireplace('~', ',', $tmpItem);	// revert concatenation delimiter
							// $tmpItem = str_ireplace(':{', '|{', $tmpItem);	// revert concatenation delimiter
							$tmpItem = preg_replace('/^(\d+):\{/', '$1|{', $tmpItem);	// revert concatenation delimiter

							[$id, $text] = explode('|', preg_replace('/^(\d+):\{/', '$1|{', $tmpItem));

							$tmp[$id] = (!empty($text) ? json_decode((string) $text, null, 512, JSON_THROW_ON_ERROR) : null);
						});
					}

					// Sort array keys
					ksort($tmp);

					// Replace row property.
					$row['error_catalog'] = array_filter($tmp);
				}
				else
				{
					// Assign at least the static technical parameters.
					$row['error_catalog'] = [];
				}

				// Extract tech_params string into array. (identical code as in {@link Nematrack\Entity\Process::bind()})
				if (isset($row['tech_params']))
				{
					$tmp = [];

					$params = ArrayHelper::getValue($row, 'tech_params');

					if (is_string($params))
					{
						$tmpParams = explode('|', $params);
						$tmpParams = array_filter((array) $tmpParams);

						array_walk($tmpParams, function($tmpParam) use(&$tmp)
						{
							// $tmpParam = str_ireplace('~', ',', $tmpParam);	// revert concatenation delimiter
							// $tmpParam = str_ireplace(':{', '|{', $tmpParam);	// revert concatenation delimiter
							$tmpParam = preg_replace('/^(\d+):\{/', '$1|{', $tmpParam);	// revert concatenation delimiter

							[$id, $text] = explode('|', preg_replace('/^(\d+):\{/', '$1|{', $tmpParam));

							$tmp[$id] = (!empty($text) ? json_decode((string) $text, null, 512, JSON_THROW_ON_ERROR) : null);
						});
					}

					// Inject static technical parameters (that kind of params a user cannot edit)
					if (empty($params) || is_string($params))
					{
						array_filter($statTechParams, function($statTechParam, $i) use(&$tmp)
						{
							$tmp[$i] = $statTechParam;

						}, ARRAY_FILTER_USE_BOTH);
					}

					// Sort array keys
					ksort($tmp);

					// Replace row property.
					$row['tech_params'] = array_filter($tmp);
				}
				else
				{
					// Assign at least the static technical parameters.
					$row['tech_params'] = $statTechParams;
				}

				$rows[$row['procID']] = $row;
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
	public function getListNEW() : array	// refactoring of 'getList' that is available to developers only. Shall replace 'getList' once refactoring is finished.
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
		$procID      = ArrayHelper::getValue($args, 'procID');
		$procID      = (is_null($procID)) ? null : (int) $procID;
		$lang        = ArrayHelper::getValue($args, 'language');
		$lang        = (is_null($lang)) ? $this->language : trim($lang);
		$language    = $this->getInstance('language')->getLanguageByTag($lang);
		$withCatalog = ArrayHelper::getValue($args, 'catalog', false, 'BOOL');  // FIXME - find all calls for getList(null, null, null, true) and fix call
		$withParams  = ArrayHelper::getValue($args, 'params',  false, 'BOOL');  // FIXME - find all calls for getList(null, null, true) and fix call

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

		// Build query.
		$table = Entity::getInstance($entityName)->getTableName();
		$query = $db->getQuery(true)
		->from($db->qn($table, 'p'))
		->join('LEFT', $db->qn('process_meta')         . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('pm.' . $pkName))
		->join('LEFT', $db->qn('process_organisation') . ' AS ' . $db->qn('po') . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('po.' . $pkName))
		->select(
			$db->qn([
				'p.' . $pkName,
				'p.abbreviation',
				'p.config',
				'p.blocked',
				'p.blockDate',
				'p.blocked_by',
				'p.archived',
				'p.archiveDate',
				'p.archived_by',
				'p.created',
				'p.created_by',
				'p.modified',
				'p.modified_by',
				'p.trashed',
				'p.trashed_by',
				'p.deleted',
				'p.deleted_by',
//				'p.ordering',		// DiSABLED on 2023-09-12 - because this column is nowhere used and therefore dropped in the database
				'pm.name'
			])
		)
		->select("TRIM(
				CASE
				WHEN
					NULLIF(`pm`.`description`, NULL) IS NULL
				THEN
					''
				ELSE
					`pm`.`description`
				END
			) AS `description`"
		)
		->select('CONCAT( "[", GROUP_CONCAT( DISTINCT ' . $db->qn('po.orgID') . '), "]" ) AS ' . $db->qn('organisations'));

		// Extend query if error catalog should be included.
		if ($withCatalog)
		{
			$query
			->join('LEFT', $db->qn('process_error') . ' AS ' . $db->qn('pe') . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('pe.' . $pkName))
			->join('LEFT', $db->qn('errors__NEW')   . ' AS ' . $db->qn('e')  . ' ON ' . $db->qn('e.errID')      . ' = ' . $db->qn('pe.errID'))	// FIXME - rename to 'errors' when done
			->join('LEFT', $db->qn('error_meta')    . ' AS ' . $db->qn('em') .
				' ON ( ' .
					$db->qn('e.errID')  . ' = ' . $db->qn('em.errID') .
				' AND ' .
					$db->qn('em.lngID') . ' = ' . ArrayHelper::getValue($language, 'lngID', 0, 'INT') .
				')'
			)
			->select("CASE
				WHEN
					NULLIF(`em`.`errID`, NULL) IS NULL
				THEN
					NULL
				ELSE
					GROUP_CONCAT( DISTINCT CONCAT_WS(':', `em`.`errID`,
						CONCAT(
							'{',
								'\"errID\"',       ':', '\"', TRIM( `e`.`errID` ),  '\",',
								'\"number\"',      ':', '\"', TRIM( `e`.`number` ), '\",',
								'\"wincarat\"',    ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`wincarat`, NULL) IS NULL THEN '' ELSE `e`.`wincarat` END ), '\",',
								'\"name\"',        ':', '\"', TRIM( `em`.`name` ),  '\",',
								'\"description\"', ':', '\"', TRIM( CASE WHEN NULLIF(`em`.`description`, NULL) IS NULL THEN '' ELSE `em`.`description` END ), '\",',
								'\"blocked\"',     ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`blocked`,      NULL) IS NULL THEN '' ELSE `e`.`blocked`      END ), '\",',
								'\"blocked_by\"',  ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`blocked_by`,   NULL) IS NULL THEN '' ELSE `e`.`blocked_by`   END ), '\",',
								'\"archived\"',    ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`archived`,     NULL) IS NULL THEN '' ELSE `e`.`archived`     END ), '\",',
								'\"archived_by\"', ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`archived_by`,  NULL) IS NULL THEN '' ELSE `e`.`archived_by`  END ), '\",',
								'\"trashed\"',     ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`trashed`,      NULL) IS NULL THEN '' ELSE `e`.`trashed`      END ), '\",',
								'\"trashed_by\"',  ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`trashed_by`,   NULL) IS NULL THEN '' ELSE `e`.`trashed_by`   END ), '\",',
								'\"deleted\"',     ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`deleted`,      NULL) IS NULL THEN '' ELSE `e`.`deleted`      END ), '\",',
								'\"deleted_by\"',  ':', '\"', TRIM( CASE WHEN NULLIF(`e`.`deleted_by`,   NULL) IS NULL THEN '' ELSE `e`.`deleted_by`   END ), '\"',
							'}'
						)
					) SEPARATOR '|' )
				END
				AS `error_catalog`"
			);
		}

		// Extend query if technical parameters should be included.
		if ($withParams)
		{
			$query
			->join('LEFT', $db->qn('process_techparameter') . ' AS ' . $db->qn('t')  . ' ON ' . $db->qn('p.' . $pkName) . ' = ' . $db->qn('t.' . $pkName))
			->join('LEFT', $db->qn('techparameters')        . ' AS ' . $db->qn('tp') .
				' ON ( ' .
					$db->qn('tp.paramID')  . ' = ' . $db->qn('t.paramID') .
				' AND ' .
					$db->qn('tp.language') . ' = ' . $db->q($lang ?? $this->language) .
				')'
			)
			->select("CASE
				WHEN
					NULLIF(`tp`.`paramID`, NULL) IS NULL
				THEN
					NULL
				ELSE
					GROUP_CONCAT( DISTINCT CONCAT_WS(':', `tp`.`paramID`,
						CONCAT(
							'{',
							'\"name\"', ':', '\"', TRIM( `tp`.`name` ), '\"',
							'}'
						)
					) SEPARATOR '|' )
				END
				AS `tech_params`"
			);
		}

		// Limit results to selected application language.
		$query
		->where($db->qn('pm.language') . ' = ' . $db->q($lang));

		/*// Only high privileged users must be allowed to see ALL items.
		if (!empty($user) && $user->getFlags() >= Access\User::ROLE_ADMINISTRATOR)
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
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}*/

		// Limit results to passed item ID.
		if (!is_null($procID))
		{
			$query
			->where($db->qn('p.' . $pkName) . ' = ' . $procID);
		}

		// Apply status filter to control access.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
				->where($db->qn('p.archived') . ' = ' . $db->q('0'))
				->where($db->qn('p.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));

				if ($withCatalog)
				{
					$query
					->where($db->qn('e.archived') . ' = ' . $db->q('0'))
					->where($db->qn('e.blocked')  . ' = ' . $db->q('0'))
					->where($db->qn('e.trashed')  . ' = ' . $db->q('0'));
				}
			break;

			/* case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
				->where($db->qn('p.archived') . ' = ' . $db->q('1'))
				->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
			break; */

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('p.blocked') . ' = ' . $db->q('1'))
				->where($db->qn('p.trashed') . ' = ' . $db->q('0'));

				if ($withCatalog)
				{
					$query
					->where($db->qn('e.blocked') . ' = ' . $db->q('0'))
					->where($db->qn('e.trashed') . ' = ' . $db->q('0'));
				}
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('p.trashed') . ' = ' . $db->q('1'));

				if ($withCatalog)
				{
					$query
					->where($db->qn('e.trashed') . ' = ' . $db->q('0'));
				}
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

		/*// Ignore Tino's test item when user is no programmer and not Max Mustermann (the dummy user for testing).
		if (!$user->isProgrammer())
		{
			if (mb_strtolower($user->get('fullname')) != 'max mustermann')
			{
				$query
				->where($db->qn('p.abbreviation') . ' <> ' . $db->q('tst'));
			}
		}*/

		// Add grouping and ordering.
		$query
		->group($db->qn('p.' . $pkName))
//		->order($db->qn('pm.name'));
		->order($db->qn('p.abbreviation'));

		// Execute query.
		try
		{
			$rows = [];

			// Get static technical parameters (Forced metadata, that kind of params a user cannot edit).
			$statTechParams = $this->getInstance('techparams', ['language' => $this->language])->getStaticTechnicalParameters();

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Convert from JSON to String.
				// Do it here instead in entity, because the real array format is required from list items as well
				// and the list does no longer contain Entities but just plain arrays.
				if (isset($row['organisations']))
				{
					$row['organisations'] = (array) json_decode($row['organisations'], null, 512, JSON_THROW_ON_ERROR);

					array_map('intval', $row['organisations']);
				}
				else
				{
					$row['organisations'] = [];
				}

				// Extract tech_params string into array. (identical code as in {@link Nematrack\Entity\Process::bind()})
				if (isset($row['error_catalog']))
				{
					$tmp = [];

					$catalog = ArrayHelper::getValue($row, 'error_catalog');
					$catalog = str_ireplace('},', '}|', $catalog);

					if (is_string($catalog))
					{
						$tmpItems = explode('|', $catalog);
						$tmpItems = array_filter((array) $tmpItems);

						array_walk($tmpItems, function($tmpItem) use(&$tmp)
						{
//							$tmpItem = str_ireplace('~', ',', $tmpItem);				// revert concatenation delimiter
//							$tmpItem = str_ireplace(':{', '|{', $tmpItem);				// revert concatenation delimiter
							$tmpItem = preg_replace('/^(\d+):\{/', '$1|{', $tmpItem);	// revert concatenation delimiter

							[$id, $text] = explode('|', preg_replace('/^(\d+):\{/', '$1|{', $tmpItem));

							$tmp[$id] = (!empty($text) ? json_decode((string) $text, null, 512, JSON_THROW_ON_ERROR) : null);
						});
					}

					// Sort array keys
					ksort($tmp);

					// Replace row property.
					$row['error_catalog'] = array_filter($tmp);
				}
				else
				{
					// Assign at least the static technical parameters.
					$row['error_catalog'] = [];
				}

				// Extract tech_params string into array. (identical code as in {@link Nematrack\Entity\Process::bind()})
				if (isset($row['tech_params']))
				{
					$tmp = [];

					$params = ArrayHelper::getValue($row, 'tech_params');

					if (is_string($params))
					{
						$tmpParams = explode('|', $params);
						$tmpParams = array_filter((array) $tmpParams);

						array_walk($tmpParams, function($tmpParam) use(&$tmp)
						{
//							$tmpParam = str_ireplace('~', ',', $tmpParam);				// revert concatenation delimiter
//							$tmpParam = str_ireplace(':{', '|{', $tmpParam);			// revert concatenation delimiter
							$tmpParam = preg_replace('/^(\d+):\{/', '$1|{', $tmpParam);	// revert concatenation delimiter

							[$id, $text] = explode('|', preg_replace('/^(\d+):\{/', '$1|{', $tmpParam));

							$tmp[$id] = (!empty($text) ? json_decode((string) $text, null, 512, JSON_THROW_ON_ERROR) : null);
						});
					}

					// Inject static technical parameters (that kind of params a user cannot edit)
					if (empty($params) || is_string($params))
					{
						array_filter($statTechParams, function($statTechParam, $i) use(&$tmp)
						{
							$tmp[$i] = $statTechParam;

						}, ARRAY_FILTER_USE_BOTH);
					}

					// Sort array keys
					ksort($tmp);

					// Replace row property.
					$row['tech_params'] = array_filter($tmp);
				}
				else
				{
					// Assign at least the static technical parameters.
					$row['tech_params'] = $statTechParams;
				}

				$rows[$row['procID']] = $row;
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
}
