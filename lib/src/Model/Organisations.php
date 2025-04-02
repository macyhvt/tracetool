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
use function is_null;

/**
 * Class description
 */
class Organisations extends ListModel
{
	protected $tableName = 'organisation';

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
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$orgID  = ArrayHelper::getValue($args, $pkName);
		$orgID  = (is_null($orgID))  ? null : (int) $orgID;



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
		->from($db->qn($table, 'o'))
		->join('INNER', $db->qn('organisation_meta')    . ' AS ' . $db->qn('om') . ' ON ' . $db->qn('o.' . $pkName) . ' = ' . $db->qn('om.' . $pkName))
		->join('INNER', $db->qn('organisation_role')    . ' AS ' . $db->qn('or') . ' ON ' . $db->qn('o.' . $pkName) . ' = ' . $db->qn('or.' . $pkName))
		->join('LEFT',  $db->qn('process_organisation') . ' AS ' . $db->qn('op') . ' ON ' . $db->qn('o.' . $pkName) . ' = ' . $db->qn('op.' . $pkName))
		->join('LEFT',  $db->qn('project_organisation') . ' AS ' . $db->qn('po') . ' ON ' . $db->qn('o.' . $pkName) . ' = ' . $db->qn('po.' . $pkName))
		->join('INNER', $db->qn('roles')                . ' AS ' . $db->qn('r')  . ' ON ' . $db->qn('r.roleID') . ' = ' . $db->qn('or.roleID'))
		->select(
			$db->qn([
				'o.' . $pkName,
				'o.name',
				'om.description',
				'o.homepage',
                'o.org_color',
                'o.org_abbr',
				'o.blocked',
				'o.blockDate',
				'o.blocked_by',
				'o.archived',
				'o.archiveDate',
				'o.archived_by',
				'o.created',
				'o.created_by',
				'o.modified',
				'o.modified_by',
				'o.trashed',
				'o.trashed_by',
				'o.deleted',
				'o.deleted_by'
			])
		)
		->select("CONCAT('{',
			'\"country\":\"'     , `o`.`country`,     '\", ',
			'\"city\":\"'        , `o`.`city`,        '\", ',
			'\"zip\":\"'         , `o`.`zip`,         '\", ',
			'\"addressline\":\"' , `o`.`addressline`, '\"',
		'}') AS `address`")
		->select("CONCAT('{',
			'\"roleID\":\"'       , `r`.`roleID`,       '\", ',
			'\"abbreviation\":\"' , `r`.`abbreviation`, '\", ',
			'\"name\":\"'         , `r`.`name`,         '\"',
		'}') AS `role`")
		->select("CASE
			WHEN
				NULLIF(`op`.`$pkName`, NULL) IS NULL
			THEN
				'[]'
			ELSE
				CONCAT('[', GROUP_CONCAT(DISTINCT " . $db->qn('op.procID') . "), ']')
			END
			AS `processes`"
		)
		->select("CASE
			WHEN
				NULLIF(`po`.`orgID`, NULL) IS NULL
			THEN
				'[]'
			ELSE
				CONCAT('[', GROUP_CONCAT(DISTINCT " . $db->qn('po.proID') . "), ']')
			END
			AS `projects`"
		);

		// Limit results to selected application language.
		$query
		->where($db->qn('om.language') . ' = ' . $db->q($this->language));

		/*// Only high privileged users must be allowed to see ALL items.
		if (!empty($user) && $user->getFlags() >= Access\User::ROLE_ADMINISTRATOR)
		{
			$query
			->where($db->qn('o.trashed') . ' IN(' . implode(',', ['0','1']) . ')');
		}
		else
		{
			$query
			->where($db->qn('o.trashed') . ' = ' . $db->q('0'));
		}

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_MANAGER)))
		{
			$query
			->where($db->qn('o.blocked') . ' = ' . $db->q('0'));
		}*/

		// Limit results to passed item ID.
		if (!is_null($orgID))
		{
			$query
			->where($db->qn('o.' . $pkName) . ' = ' . $orgID);
		}

		// Apply status filter to control access.
		switch (true)
		{
			case ($filter == Lizt::FILTER_ACTIVE) :
				$query
				->where($db->qn('o.archived') . ' = ' . $db->q('0'))
				->where($db->qn('o.blocked')  . ' = ' . $db->q('0'))
				->where($db->qn('o.trashed')  . ' = ' . $db->q('0'));
			break;

			/*case ($filter == Lizt::FILTER_ARCHIVED) :
				$query
				->where($db->qn('o.archived') . ' = ' . $db->q('1'))
				->where($db->qn('o.trashed')  . ' = ' . $db->q('0'));
			break;*/

			case ($filter == Lizt::FILTER_LOCKED) :
				$query
				->where($db->qn('o.blocked')  . ' = ' . $db->q('1'))
				->where($db->qn('o.trashed')  . ' = ' . $db->q('0'));
			break;

			case ($filter == Lizt::FILTER_DELETED) :
				$query
				->where($db->qn('o.trashed')  . ' = ' . $db->q('1'));
			break;

			/*case ($filter == Lizt::FILTER_ALL) :
			default :
				$states = ['0','1'];

				$query
				->where($db->qn('o.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('o.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->andWhere($db->qn('o.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				;
			break;*/

			/*default :
				$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

				$query
				->where($db->qn('o.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('o.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
				->where($db->qn('o.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
		}

		// Ignore Tino's test item when user is no programmer and not Max Mustermann (the dummy user for testing).
		if (!$user->isProgrammer())
		{
			if (mb_strtolower($user->get('fullname')) != 'max mustermann')
			{
				$query
				->where($db->qn('o.name') . ' NOT LIKE "' . $db->q('Test') . '%"');
			}
		}

		// Add grouping and ordering.
		$query
		->group($db->qn('o.' . $pkName))
		->order($db->qn('o.name'));

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
}
