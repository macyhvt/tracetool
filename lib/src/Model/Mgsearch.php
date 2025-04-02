<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');
use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function array_key_exists;
use function array_map;
use function is_a;
use function is_null;

/**
 * Class description
 */
class Mgsearch extends ListModel
{
	protected $tableName = 'project';

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
		$proID  = ArrayHelper::getValue($args, $pkName);
		$proID  = (is_null($proID))  ? null : (int) $proID;
		$orgID  = ArrayHelper::getValue($args, 'orgID');
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
		->from($db->qn($table, 'p'))
		->select([
			$db->qn('p.'. $pkName),
			$db->qn('p.number'),
			$db->qn('p.status'),
			$db->qn('p.order'),
			$db->qn('p.name'),
			$db->qn('p.customer'),
			$db->qn('p.config'),
			$db->qn('pm.description'),
			"CONCAT('[', CASE WHEN ISNULL(" . $db->qn('po.orgID') . ") THEN '' ELSE GROUP_CONCAT(" . $db->qn('po.orgID') . ") END, ']') AS " . $db->qn('organisations'),
			$db->qn('p.blocked'),
			$db->qn('p.blockDate'),
			$db->qn('p.blocked_by'),
			$db->qn('p.archived'),
			$db->qn('p.archiveDate'),
			$db->qn('p.archived_by'),
			$db->qn('p.created'),
			$db->qn('p.created_by'),
			$db->qn('p.modified'),
			$db->qn('p.modified_by'),
			$db->qn('p.trashed'),
			$db->qn('p.trashDate'),
			$db->qn('p.trashed_by'),
			$db->qn('p.deleted'),
			$db->qn('p.deleted_by'),
            $db->qn('p.mgid')
		])
		->join('LEFT', $db->qn('project_meta')         . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('pm.'. $pkName) . ' = ' . $db->qn('p.'. $pkName))
		->join('LEFT', $db->qn('project_organisation') . ' AS ' . $db->qn('po') . ' ON ' . $db->qn('po.'. $pkName) . ' = ' . $db->qn('p.'. $pkName));

		// Limit results to selected application language.
		$query
		->where($db->qn('pm.language') . ' = ' . $db->q($this->language));
        $query
            ->where($db->qn('p.mgid') . ' != ' . $db->q(0));


		// Limit results to passed item ID.
		if (!is_null($proID))
		{
			$query
			->where($db->qn('p.'. $pkName) . ' = ' . (int) $proID);
		}

		// Apply filter.
		if (is_null($proID))
		{
			switch (true)
			{
				case ($filter == Lizt::FILTER_ACTIVE) :
					$query
					->where($db->qn('p.archived') . ' = ' . $db->q('0'))
					->where($db->qn('p.blocked')  . ' = ' . $db->q('0'))
					->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
				break;

				/*case ($filter == Lizt::FILTER_ARCHIVED) :
					$query
					->where($db->qn('p.archived') . ' = ' . $db->q('1'))
					->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
				break;*/

				case ($filter == Lizt::FILTER_LOCKED) :
					$query
					->where($db->qn('p.blocked')  . ' = ' . $db->q('1'))
					->where($db->qn('p.trashed')  . ' = ' . $db->q('0'));
				break;

				case ($filter == Lizt::FILTER_DELETED) :
					$query
					->where($db->qn('p.trashed')  . ' = ' . $db->q('1'));
				break;

				/*case ($filter == Lizt::FILTER_ALL) :
				default :
					$states = ['0','1'];

					$query
					->where($db->qn('p.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('p.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('p.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					;
				break;*/

				/*default :
					$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

					$query
					->where($db->qn('p.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('p.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('p.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
			}
		}

		// Add grouping and ordering.
		$query
		->group($db->qn('p.'. $pkName))
		->order($db->qn('p.number'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Convert from JSON to String.
				// Do it here instead in entity, because the real array format is required from list items as well
				// and the list does no longer contain Entities but just plain arrays.
				if (array_key_exists('organisations', $row))
				{
					$row['organisations'] = (array) json_decode($row['organisations'], null, 512, JSON_THROW_ON_ERROR);

					array_map('intval', $row['organisations']);
				}
				else
				{
					$row['organisations'] = [];
				}

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

    public function addCMMGroup($formData)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $user = App::getAppUser();

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $formData = ArrayHelper::getValue($formData, 'form', [], 'ARRAY');

        // Convert the array to object.
        /*if (!is_object($formData))
        {
            $formData = (object) $formData;
        }*/
        $userID = $user->get('userID');

        //echo "<pre>";print_r($formData['proID']);exit;
        $proIDArray = $formData['proID'];
        $proIDString = implode(',', array_map('intval', $proIDArray));
        if (true === $this->existsSGValue(ArrayHelper::getValue($formData, 'mgid')))
        {
            $query = $db->getQuery(true)
                ->update($db->qn('maingroup_assigns'))
                ->set([
                    $db->qn('mgid')      . ' = ' . $db->q(filter_var($formData['mgid'],   FILTER_SANITIZE_STRING)),
                    $db->qn('proids')      . ' = ' . $db->q($proIDString),
                    $db->qn('added_on')      . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
                ])
                ->where($db->qn('mgid')   . ' = ' . $db->q(filter_var($formData['mgid'],   FILTER_SANITIZE_STRING)));
        }else {

             $query = $db->getQuery(true)
                ->insert($db->qn('maingroup_assigns'))
                ->columns(
                    $db->qn([
                        'mgid',
                        'proids',
                        'added_on',
                        'added_by'
                    ])
                )
                ->values(implode(',', [
                    $db->q(filter_var($formData['mgid'], FILTER_SANITIZE_STRING)),
                    $db->q($proIDString),
                    $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
                    (int)$userID
                ]));
        }
        // Execute query.
        try
        {
            $varl = $db
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
        $isError = false;
        return $varl;
    }
    protected function existsSGValue($mgid = null) : bool
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        if (is_null($mgid)){
            throw new InvalidArgumentException('Function requires at least 1 argument.');
        }

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->select($db->qn('mgid'))
            ->from($db->qn('maingroup_assigns'));

        switch (true)
        {
            case (!empty($mgid)) :
                $query
                    ->where($db->qn('mgid') . ' = ' . (int) $mgid);
                break;


            case (!empty($mgid) && (int) $mgid > 0) :
                $query
                    ->where($db->qn('mgid') . ' = ' . (int) $mgid);
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

        return $rs > 0;
    }
	public function getProjectNumbers($orgID = null) : array
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

//		$orgID = (is_null($orgID) ? $orgID : (int) $orgID);

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('projects', 'p'))
		->select(
			$db->qn([
				'p.proID',
				'p.number',
				'p.status'
			])
		);

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('p.blocked') . ' = ' . $db->q('0'));
		}

		/* if (!\is_null($orgID))
		{
			$query->where($db->qn('po.orgID') . ' = ' . (int) $orgID);
		}*/

		$query
		->order($db->qn('p.number'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadObjectList();

			foreach ($rs as $row)
			{
				$rows[$row->proID] = $row->number;
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
