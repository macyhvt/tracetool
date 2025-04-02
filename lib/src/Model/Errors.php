<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Entity;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function array_filter;
use function array_map;
use function property_exists;

/**
 * Class description
 */
class Errors extends ListModel
{
	protected $tableName = 'error';

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

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// @debug
//		$debug  = $user->isProgrammer();
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

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$errID  = ArrayHelper::getValue($args, $pkName);
		$errID  = (is_null($errID))  ? null : (int) $errID;

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

		// Define database table name.
		$tableName = 'errors__NEW';	// FIXME - change to 'errors'

		// Build query.
//		$table = Entity::getInstance($entityName)->getTableName();	// FIXME - enable after table name was set in entity
		$query = $db->getQuery(true)
		->from($db->qn($tableName, 'e'))
		->join('LEFT', $db->qn('error_meta') . ' AS ' . $db->qn('em') .
			' ON ( ' .
				$db->qn('e.errID')  . ' = ' . $db->qn('em.errID') .
			' AND ' .
				$db->qn('em.lngID') . ' = ' . (int) $lngID .
			')'
		)
		->join('LEFT',  $db->qn('process_error') . ' AS ' . $db->qn('pe') . ' ON ' . $db->qn('e.' . $pkName) . ' = ' . $db->qn('pe.' . $pkName))
//		->join('INNER', $db->qn('processes')     . ' AS ' . $db->qn('p')  . ' ON ' . $db->qn('pe.procID')    . ' = ' . $db->qn('p.procID'))
		->select(
			$db->qn([
				'e.' . $pkName,
				'e.number',
				'e.wincarat',
				'em.name',
				'em.description',
				'e.blocked',
				'e.blockDate',
				'e.blocked_by',
				'e.archived',
				'e.archiveDate',
				'e.archived_by',
				'e.created',
				'e.created_by',
				'e.modified',
				'e.modified_by',
				'e.trashed',
				'e.trashed_by',
				'e.deleted',
				'e.deleted_by'
			])
		)
		/*->select("CASE
			WHEN
				NULLIF(`em`.`errID`, NULL) IS NULL
			THEN
				NULL
			ELSE
				CONCAT(
					'{',
						'\"name\"',        ':', '\"', TRIM( `em`.`name` ),    '\",',
						'\"description\"', ':', '\"', TRIM( CASE WHEN NULLIF(`em`.`description`, NULL) IS NULL THEN '' ELSE `em`.`description` END ), '\"',
					'}'
				)
			END
			AS `metadata`"
		)*/;

		// Limit results to selected application language.
//		$query->where($db->qn('em.lngID') . ' = ' . (int) $lngID);

		/*// Only high privileged users must be allowed to see ALL items.
		if (!empty($user) && $user->getFlags() >= Access\User::ROLE_ADMINISTRATOR)
		{
			$query
			->where($db->qn('e.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
		}
		else
		{
			$query
			->where($db->qn('e.trashed') . ' = ' . $db->q('0'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_MANAGER)))
		{
			$query
			->where($db->qn('e.blocked') . ' = ' . $db->q('0'));
		}*/

		// Limit results to passed item ID.
		if (!is_null($errID))
		{
			$query
			->where($db->qn('e.' . $pkName) . ' = ' . $errID);
		}

		// Apply status filter to control access.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
				->where($db->qn('e.archived') . ' = ' . $db->q('0'))
				->where($db->qn('e.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('e.trashed')  . ' = ' . $db->q('0'));
			break;

			/*case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
				->where($db->qn('e.archived') . ' = ' . $db->q('1'))
				->where($db->qn('e.trashed')  . ' = ' . $db->q('0'));
			break;*/

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('e.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('e.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('e.trashed')  . ' = ' . $db->q('1'));
			break;

			/*case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
				->where($db->qn('e.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('e.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('e.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break;*/

			/*default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
				->where($db->qn('e.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('e.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('e.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
		}

		// Ignore Tino's test item when user is no programmer and not Max Mustermann (the dummy user for testing).
		if (!$user->isProgrammer())
		{
			// TODO
		}

		// Add grouping and ordering.
		$query
		->group($db->qn('e.' . $pkName))
		->order($db->qn('em.name'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row[$pkName]] = $row;
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

	// @todo - move to Process-Model, as it is focused on process(es)

	/**
	 * Add description...
	 *
	 * @param   int   $lngID
	 * @param   array $includeIDs
	 *
	 * @return  array
	 *
	 * @uses   {@link StringInflector}
	 *
	 * called in:
	 *  layouts.forms.article.process_tree
	 *  layouts.forms.part.edit
	 *  layouts.forms.part.item
	 *  \ \Model\Parts
	 *  \ \View\Statistics
	 */
	public function getErrorsByLanguage(int $lngID, array $includeIDs = []) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*//-> BEGIN   Debug who's calling this function.
		$trace  = debug_backtrace();
		$called = current($trace);
		$caller = next($trace);

		$this->logger->log(
			'info',
			sprintf('%s(%s) was called in %s::%s() in line %d ( %s )', __METHOD__, $lngID, $caller['class'], $caller['function'], $caller['line'], $caller['file']),
			$called['args']
		);
		//-> END   Debug who's calling this function*/

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
		->from($db->qn('process_error', 'pe'))
		->join('LEFT', $db->qn('errors') . ' AS ' . $db->qn('e') . ' ON ' . $db->qn('pe.errID') . ' = ' . $db->qn('e.errID'))
		->select($db->qn('pe.procID'))
		->select("CONCAT(
			'{',
				GROUP_CONCAT(
					CONCAT(
						'\"', `pe`.`errID`, '\"',
						':',
						'\"', `e`.`name`, '\"'
					)
				),
			'}') AS `errors`"
		)
		->where($db->qn('e.lngID') . ' = ' . (int) ($lngID ?? '2'));

		if (count($includeIDs))
		{
			$query
			->where($db->qn('pe.procID') . ' IN (' . implode(',', $includeIDs) . ')');
		}

		// Next command has been borrowed with slight changes from {@see: https://stackoverflow.com/a/1631794}
		if (count($includeIDs))
		{
			$query
			->order('FIELD(' . $db->qn('pe.procID') . ', ' . implode(',', $includeIDs) . ')');
		}

		$query
		->group($db->qn('pe.procID'));

		/*// @debug
		if ($user->isProgrammer()) :
			die($query->dump());
		endif;*/

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList('procID');

			foreach ($rs as &$row)
			{
				if (property_exists($row, 'procID'))
				{
					unset($row->procID);
				}

				if (property_exists($row, 'errors'))
				{
					$errors = (array) json_decode($row->errors, null, 512, JSON_THROW_ON_ERROR);
					$errors = array_map('trim', $errors);
					$errors = array_filter($errors);

					$row = $errors;
				}
			}

			$rows = $rs;
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
	private function getErrorsByLanguageNEW(int $lngID, array $includeIDs = []) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*//-> BEGIN   Debug who's calling this function.
		$trace  = debug_backtrace();
		$called = current($trace);
		$caller = next($trace);

		$this->logger->log(
			'info',
			sprintf('%s(%s) was called in %s::%s() in line %d ( %s )', __METHOD__, $lngID, $caller['class'], $caller['function'], $caller['line'], $caller['file']),
			$called['args']
		);
		//-> END   Debug who's calling this function*/

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
		->from($db->qn('process_error', 'pe'))
//		->join('LEFT', $db->qn('errors')     . ' AS ' . $db->qn('e')  . ' ON ' . $db->qn('pe.errID') . ' = ' . $db->qn('e.errID'))
		->join('LEFT', $db->qn('error_meta') . ' AS ' . $db->qn('em') . ' ON ' . $db->qn('pe.errID') . ' = ' . $db->qn('em.errID'))
		->select($db->qn('pe.procID'))
		/*->select("CONCAT(
			'{',
				GROUP_CONCAT(
					CONCAT(
						'\"', `pe`.`errID`, '\"',
						':',
						'\"', `e`.`name`, '\"'
					)
				),
			'}') AS `errors`"
		)*/
		->select("CASE
			WHEN
				NULLIF(`em`.`errID`, NULL) IS NULL
			THEN
				NULL
			ELSE
				CONCAT(
				'{',
					GROUP_CONCAT(
						CONCAT(
							'\"', `pe`.`errID`, '\"',
							':',
							'\"', `em`.`name`, '\"'
						)
					),
				'}')
			END
			AS `errors`"
		)
		->where($db->qn('em.lngID') . ' = ' . (int) ($lngID ?? '2'));

		if (count($includeIDs))
		{
			$query
			->where($db->qn('pe.procID') . ' IN (' . implode(',', $includeIDs) . ')');
		}

		// Next command has been borrowed with slight changes from {@see: https://stackoverflow.com/a/1631794}
		if (count($includeIDs))
		{
			$query
			->order('FIELD(' . $db->qn('pe.procID') . ', ' . implode(',', $includeIDs) . ')');
		}

		$query
		->group($db->qn('pe.procID'));

		/*// @debug
		if ($user->isProgrammer()) :
			die($query->dump());
		endif;*/

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList('procID');

			foreach ($rs as &$row)
			{
				if (property_exists($row, 'procID'))
				{
					unset($row->procID);
				}

				if (property_exists($row, 'errors'))
				{
					$errors = (array) json_decode($row->errors, null, 512, JSON_THROW_ON_ERROR);
					$errors = array_map('trim', $errors);
					$errors = array_filter($errors);

					$row = $errors;
				}
			}

			$rows = $rs;
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
