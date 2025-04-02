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
class Fmeas extends ListModel
{
	protected $tableName = 'qm_fmea';

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
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$id     = ArrayHelper::getValue($args, 'eqpID');
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
		$table = 'qs_cplans';
		$query = $db->getQuery(true)
		->from($db->qn($table, 'c'))
		->order($db->qn('planID'));

		if (!is_array($columns = DatabaseHelper::getTableColumns($table)))
		{
			return [];
		}

		$query
		->select(implode(',', $db->qn($columns)));

		if (!is_null($id))
		{
			$query
			->where($db->qn('c.planID') . ' = ' . $id);
		}

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
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}
}
