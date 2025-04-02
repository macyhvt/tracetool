<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseQuery;
use Nematrack\Factory;
use Throwable;
use function array_column;

/**
 * Class description
 */
final class DatabaseHelper
{
	/**
	 * Private constructor. Class cannot be constructed.
     *
     * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	/**
	 * Resets a database table's AUTO_INCREMENT counter.
	 *
	 * @param  string $tableName  The name of the table to operate on.
	 *
	 * @return void
	 */
	public static function autoIncrement(string $tableName): void
	{
		$dbo = Factory::getDbo();

		try
		{
			$dbo
			->setQuery(/** @lang MySQL */'ALTER TABLE ' . $dbo->qn($tableName) . ' AUTO_INCREMENT = 1')
			->execute();
		}
		catch (Throwable $e)
		{
			// TODO - log $e->getMessage()
		}
	}

	/**
	 * Shrinks a given SQL query string (removes duplicate blanks).
	 *
	 * @param  string $sql  The SQL query string to process.
	 *
	 * @return string
	 */
	public static function cleanQuery(string $sql = ''): string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$sql = preg_replace('/\s{2,}/', ' ', $sql);
		$sql = preg_replace('/\s*([:,;.?!])/', "$1", $sql);

		return '' . $sql;
	}

	/**
	 * Clears the latest database query and closes the current database connection.
	 *
	 * @param DatabaseDriver $db  The database driver.
	 */
	public static function closeConnection(DatabaseDriver $db): void
	{
		if ($db->connected())
		{
			$db
			->getQuery()
			->clear();

			$db
			->freeResult();
		}
	}

	/**
	 * Creates an HTML-formatted dump of the database query for debugging purposes.
	 *
	 * This method is inspired by the <em>dump()</em> function of the Joomla
	 * database library, which has been marked as deprecated since version 3.0
	 * and is therefore no longer available. This is an independent replacement.
	 *
	 * Usage:
	 *	echo DatabaseHelper::dumpQuery($query);
	 *
	 * @param  DatabaseQuery $query  The database query object to be dumped
	 * @param  bool $prettyPrint  Flag to disable pretty printing of the SQL string and render pure SQL instead.
	 *
	 * @return string
	 *
	 * @since  2.10.1
	 */
	public static function dumpQuery(DatabaseQuery $query, bool $prettyPrint = true): string
	{
		return $prettyPrint ? '<pre class="databasequery"><strong>SQL-statement: </strong>' . $query->__toString() . '</pre>' : $query->__toString();
	}

	/**
	 * Escapes a provided SQL query string as a sanitization step.
	 *
	 * @param  string $sql  The SQL query string to process.
	 *
	 * @return string
	 */
	public static function escapeQuery(string $sql = ''): string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return '' . str_ireplace('\\', '\\\\', $sql);
	}

	/**
	 * Returns the count of all records per table.
	 * If a table name is provided, the count for only that table is returned.
	 *
	 * @param array|null $tableNames Optional list of table names to limit the resultset to.
	 *
	 * @return array
	 */
	public static function getRowsCountPerTable(array $tableNames = null): array
	{
		$dbo   = Factory::getDbo();
		$query = $dbo->getQuery(true);
		$rows  = [];

		try
		{
			/* --
			-- Implementation inspired by https://www.tutorialspoint.com/get-record-count-for-all-tables-in-mysql-database/
			--
			SELECT TABLE_NAME,
				   TABLE_ROWS
			FROM INFORMATION_SCHEMA.tables
			WHERE TABLE_SCHEMA = 'database-name';
			*/

			// Build query
			$query
			->select($dbo->qn([
				'TABLE_NAME', 'TABLE_ROWS'
			]))
			->from($dbo->qn('INFORMATION_SCHEMA.TABLES'))
			->where($dbo->qn('TABLE_SCHEMA') . ' = ' . $dbo->q(Factory::getConfig()->get('db')));

			if (is_countable($tableNames) && count($tableNames))
			{
				$tableNames = array_map(function($tableName) use (&$dbo) { return $dbo->q($tableName); }, $tableNames);

				$query
				->where($dbo->qn('TABLE_NAME') . ' IN(' . implode(',', $tableNames) . ')');
			}

			// Execute query.
			$rows = $dbo->setQuery($query)->loadAssocList('TABLE_NAME', 'TABLE_ROWS');
		}
		catch (Throwable $e)
		{
			// TODO - log $e->getMessage()
		}

		return $rows;
	}

	/**
	 * Creates and returns a list of defined columns for a given table.
	 *
	 * @param   string   $tableName   The name of the table to operate on.
	 * @param   boolean  $withTypes   True to return a map of column names and their corresponding field types. If false (default), only the column names are returned.
	 * @param   boolean  $allDetails  True to return a map of column names and their comlumn complete definition details. Default is false.
	 *
	 * @return array
	 */
	public static function getTableColumns(string $tableName, bool $withTypes = false, bool $allDetails = false): array
	{
		$dbo  = Factory::getDbo();

		// Init return value.
		$list = [];

		try
		{
			if (method_exists($dbo, 'getTableColumns') && is_callable([$dbo, 'getTableColumns']))
			{
				switch (true)
				{
					case $allDetails :
						$list = $dbo->getTableColumns($tableName, false);
					break;

					case $withTypes :
						/* Notice:
						 *
						 * Instead of calling \Joomla\Database\Mysqli\MysqliDriver::getTableColumns($tableName) with
						 * option "typeOnly", we fetch all details and create the return value ourselfes, as there's a bug in
						 * \Joomla\Database\Mysqli\MysqliDriver::getTableColumns() that causes the returned map not displaying
						 * an enum type's options. Instead, for an enum type it returns enum '',''[,'']. We fix that.
						 */
						$list = $dbo->getTableColumns($tableName, false);
						$list = array_combine(
							array_values(array_column($list, 'Field')),
							array_values(array_column($list, 'Type'))
						);
					break;

					default :
						$list = $dbo->getTableColumns($tableName);
						$list = array_keys($list);
				}
			}
		}
		catch (Throwable $e)
		{
			// TODO - log $e->getMessage()
		}

		return $list;
	}

	/**
	 * Fetches for a given database table the primary key name.
	 *
	 * @param  string $tableName  The name of the table to query on.
	 *
	 * @return array
	 *
	 * @return  string
	 */
	public static function getPrimaryKey(string $tableName): string
	{
		$dbo   = Factory::getDbo();
		$query = $dbo->getQuery(true);
		$key   = '';

		try
		{
			/* --
			-- Implementation inspired by https://dataedo.com/kb/query/mariadb/list-all-primary-keys-and-their-columns
			--
			SELECT tco.TABLE_SCHEMA     AS database_name,
				   tco.TABLE_NAME,
				   tco.CONSTRAINT_NAME  AS pk_name,
				   kcu.ORDINAL_POSITION AS column_id,
				   kcu.COLUMN_NAME
			FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS tco
			JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE  AS kcu
				 ON (
						tco.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
					AND tco.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
					AND tco.TABLE_NAME        = kcu.TABLE_NAME
				)
			WHERE tco.CONSTRAINT_TYPE = 'PRIMARY KEY'
			  AND tco.TABLE_SCHEMA NOT IN ('sys', 'information_schema', 'mysql', 'performance_schema')
			  -- put your database name here
			  AND tco.TABLE_SCHEMA = 'the-database-name'
			  AND tco.TABLE_NAME   = 'the-table-name'
			ORDER BY tco.TABLE_SCHEMA,
					 tco.TABLE_NAME,
					 kcu.ORDINAL_POSITION;
			*/

			// Build query
			$query
			->select($dbo->qn('COLUMN_NAME'))
			->from($dbo->qn('INFORMATION_SCHEMA.KEY_COLUMN_USAGE'))
			->where($dbo->qn('TABLE_SCHEMA')    . ' = ' . $dbo->q(Factory::getConfig()->get('db')))
			->where($dbo->qn('TABLE_NAME')      . ' = ' . $dbo->q($tableName))
			->where($dbo->qn('CONSTRAINT_NAME') . ' = ' . $dbo->q('PRIMARY'));

			// Execute query.
			$key = $dbo->setQuery($query)->loadResult();
		}
		catch (Throwable $e)
		{
			// TODO - log $e->getMessage()
		}

		return $key;
	}

	/**
	 * Applies both {@link DatabaseHelper::cleanQuery}
	 * and {@link DatabaseHelper::escapeQuery} to a
	 * given SQL query string as a sanitization step.
	 *
	 * @param  string $sql  The SQL query string to process.
	 *
	 * @return string
	 */
	public static function sanitizeQuery(string $sql): string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return self::escapeQuery( self::cleanQuery($sql) );
	}

	/**
	 * Creates and returns a list of defined columns for a given table.
	 *
	 * @param  string $tableName  The name of the table to operate on.
	 *
	 * @return array
	 */
	public static function tableExists(string $tableName):? bool
	{
		$dbo    = Factory::getDbo();
		$query  = $dbo->getQuery(true);
		$exists = false;

		try
		{
			/* --
			-- Implementation inspired by https://database.guide/5-ways-to-check-if-a-table-exists-in-mysql/
			--
			SELECT
			   TABLE_SCHEMA,
			   TABLE_NAME,
			   TABLE_TYPE
			FROM
			   INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = 'd0350590'
			  AND TABLE_NAME = 'error_meta'
			  AND TABLE_TYPE = 'BASE TABLE';
			*/

			// Build query
			$query
			->select($dbo->qn([
				'TABLE_SCHEMA', 'TABLE_NAME', 'TABLE_TYPE'
			]))
			->from($dbo->qn('INFORMATION_SCHEMA.TABLES'))
			->where($dbo->qn('TABLE_SCHEMA') . ' = ' . $dbo->q(Factory::getConfig()->get('db')))
			->where($dbo->qn('TABLE_NAME')   . ' = ' . $dbo->q($tableName))
			->where($dbo->qn('TABLE_TYPE')   . ' = ' . $dbo->q('BASE TABLE'));

			/*// @debug
			echo '<pre>sql: ' . print_r($query->dump(), true) . '</pre>';
//			die;*/

			// Execute query.
//			$exists = $dbo->setQuery($query)->loadAssoc();	// The query result is NULL if nothing was found, otherwise its an array.
			$exists = $dbo->setQuery($query)->loadResult();	// The query result is NULL if nothing was found, otherwise its a string.

			/*// @debug
			echo '<pre>row (' . gettype($row) . '): ' . print_r($row, true) . '</pre>';
//			die;*/
		}
		catch (Throwable $e)
		{
			// TODO - log $e->getMessage()
		}

		return $exists;
	}

	/**
	 * Get a logger object.
	 *
	 * Returns the global {@link Logger} object.
	 *
	 * @private
	 *
	 * @return  Logger object
	 *
	 * @since  2.10
	 */
	private static function getLogger(): Logger
	{
		return Factory::getLogger([
			'context' => __CLASS__,
			'type'    => 'rotate',
			'path'    => FTKPATH_LOGS . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'db.log',
			'level'   => 'INFO'
		]);
	}
}
