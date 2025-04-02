<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use  \Entity;
use  \Helper\DatabaseHelper;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function is_null;

/**
 * Class description
 */
class Cplans extends ListModel
{
	protected $tableName = 'qm_cplan';

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
	 * @uses   {@link \Symfony\Component\String\Inflector\EnglishInflector}
	 */
	public function getList() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', __CLASS__)));
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		// $search = ArrayHelper::getValue($args, 'search', '', 'STRING');
		// $search = (is_null($search)) ? null : (new InputFilter)->clean($search);
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$id     = ArrayHelper::getValue($args, $pkName);
		$id     = (is_null($id)) ? null : (int) $id;
		$procID = ArrayHelper::getValue($args, 'procID');
		$procID = (is_null($procID)) ? null : (int) $procID;

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
		$table = 'qm_cplans';
		$query = $db->getQuery(true)
		->from($db->qn($table, 'c'))
		->join('LEFT', $db->qn('qm_cplan_prefixes'). ' AS ' . $db->qn('cpfx') . ' ON (' .
			$db->qn('cpfx.' . $pkName) . ' = ' . $db->qn('c.' . $pkName) .
			' AND ' .
			$db->qn('cpfx.lngID') . ' = ' . $lngID .
		')')
		->join('LEFT', $db->qn('qm_cplan_suffixes'). ' AS ' . $db->qn('csfx') . ' ON (' .
			$db->qn('csfx.' . $pkName) . ' = ' . $db->qn('c.' . $pkName) .
			' AND ' .
			$db->qn('csfx.lngID') . ' = ' . $lngID .
		')');

		if (!is_array($columns = DatabaseHelper::getTableColumns($table)))
		{
			return [];
		}

		// Add main table prefix.
		array_walk($columns, function(&$column) { $column = sprintf('c.%s', $column); });

		$query
		->select(implode(',', $db->qn($columns)))
		->select($db->qn([
			'cpfx.pfxID',
			'cpfx.planID',
			'cpfx.lngID',
			'cpfx.ordering',
			'cpfx.content'
		]));

		// Limit results to passed item ID.
		if (!is_null($id))
		{
			$query
			->where($db->qn('c.' . $pkName) . ' = ' . $id);
		}

		// Apply filter.
		if (is_null($id))
		{
			switch (true)
			{
				case ($filter == Lizt::FILTER_ACTIVE) :
					$query
					->where($db->qn('c.archived') . ' = ' . $db->q('0'))
					->where($db->qn('c.blocked')  . ' = ' . $db->q('0'))
					->where($db->qn('c.trashed')  . ' = ' . $db->q('0'));
				break;

				/*case ($filter == Lizt::FILTER_ARCHIVED) :
					$query
					->where($db->qn('c.archived') . ' = ' . $db->q('1'))
					->where($db->qn('c.trashed')  . ' = ' . $db->q('0'));
				break;*/

				case ($filter == Lizt::FILTER_LOCKED) :
					$query
					->where($db->qn('c.blocked')  . ' = ' . $db->q('1'))
					->where($db->qn('c.trashed')  . ' = ' . $db->q('0'));
				break;

				case ($filter == Lizt::FILTER_DELETED) :
					$query
					->where($db->qn('c.trashed')  . ' = ' . $db->q('1'));
				break;

				/*case ($filter == Lizt::FILTER_ALL) :
				default :
					$states = ['0','1'];

					$query
					->where($db->qn('c.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('c.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('c.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					;
				break;*/

				/*default :
					$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

					$query
					->where($db->qn('c.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('c.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('c.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
			}
		}

		// Limit results to passed process ID.
		if (!is_null($procID))
		{
			$query
			->where($db->qn('c.procID') . ' = ' . $procID);
		}

		// Add grouping and ordering.
		if (is_null($id))
		{
			$query
			->order($db->qn('c.' . $pkName));
		}

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// If there's a process ID use it to index the results array.
				// This is helpful when a control plan is to be fetched via the related process' ID.
				if (!is_null($procID))
				{
					$rows[$procID] = $row;
				}
				// Otherwise use the plan ID to index the results array.
				else
				{
					$rows[$row[$pkName]] = $row;
				}
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
