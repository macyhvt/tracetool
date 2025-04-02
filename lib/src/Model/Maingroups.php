<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');
use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Registry\Registry;
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
class Maingroups extends ListModel
{
	protected $tableName = 'maingroups';

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

		$lngTag = ArrayHelper::getValue($args, 'language', $this->language, 'STRING');
		$lngID  = ArrayHelper::getValue($args, 'lngID');
		$lngID  = is_int($lngID)
			? $lngID
			: ArrayHelper::getValue($this->getInstance('language')->getLanguageByTag($lngTag), 'lngID', 'INT');

		// Init shorthand to database object.
		$db = $this->db;

		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
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
			$db->qn('p.deleted_by')
		]);

		// Limit results to selected application language.
		$query
		->where($db->qn('pm.language') . ' = ' . $db->q($this->language));

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
        //$rows = 'hello';
		return $rows;
	}

    public function addMainGroupProjectMeta($mgid, $lngid, $language)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Init shorthand to database object.
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        //$formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');

        /// echo "<pre>";print_r($rowData);exit;
        $query = $db->getQuery(true)
            ->insert($db->qn('project_meta'))
            ->columns(
                $db->qn([
                    'proID',
                    'lngID',
                    'language',
                ])
            )
            ->values(implode(',', [
                $db->q(filter_var($mgid,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($lngid,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($language,   FILTER_SANITIZE_STRING)),
            ]));

        // Execute query.
        try
        {
            $db
                ->setQuery($query)
                ->execute();
            $insertID = (int) $db->insertid();
            //echo $insertID;exit;
            // WARNING: insert_id will be empty if the targeted table has no primary key
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $insertID = null;
        }

        $isError = false;

        return (($insertID > 0) ? $mgid : false);
    }
    public function addMainGroupProjectOrg($mgid, $orgID,$roleID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Init shorthand to database object.
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        //$formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');

        /// echo "<pre>";print_r($rowData);exit;
        $query = $db->getQuery(true)
            ->insert($db->qn('project_organisation'))
            ->columns(
                $db->qn([
                    'proID',
                    'orgID',
                    'roleID',
                ])
            )
            ->values(implode(',', [
                $db->q(filter_var($mgid,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($orgID,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($roleID,   FILTER_SANITIZE_STRING)),
            ]));

        // Execute query.
        try
        {
            $db
                ->setQuery($query)
                ->execute();
            $insertID = (int) $db->insertid();
            //echo $insertID;exit;
            // WARNING: insert_id will be empty if the targeted table has no primary key
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $insertID = null;
        }

        $isError = false;

        return (($insertID > 0) ? $mgid : false);
    }

    public function addCMainGroup($project)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Init shorthand to database object.
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');

        //print_r($formData);exit;
        // Dupe-check project number.
        if (true === $this->existsMGProject(ArrayHelper::getValue($formData, 'proID'), ArrayHelper::getValue($formData, 'number')))
        {
            Messager::setMessage([
                'type' => 'info',
                'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROJECT_TEXT', $this->language),
                    ArrayHelper::getValue($formData, 'number', null, 'STRING')
                )
            ]);

            return false;
        }

        // Convert the array to object.
        if (!is_object($formData))
        {
            $formData = (object) $formData;
        }

        $userID = $user->get('userID');

        // Validate session userID equals current form editor's userID
        if ((int) $userID !== (int) $formData->user)
        {
            Messager::setMessage([
                'type' => 'warning',
                'text' => sprintf('%s: %s %s %s',
                    Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
                    Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
                    Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
                    Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
                )
            ]);

            return false;
        }

        // Build query.
        //$rowData = new Registry($formData);
        /// echo "<pre>";print_r($rowData);exit;
       echo $query = $db->getQuery(true)
            ->insert($db->qn('projects'))
            ->columns(
                $db->qn([
                    'number',
                    'name',
                    'customer',
                    'status',
                    'created',
                    'created_by',
                    'mgid',
                ])
            )
            ->values(implode(',', [
                $db->q(filter_var($formData->number,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($formData->number,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($formData->number,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var('P',   FILTER_SANITIZE_STRING)),
                $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
                (int) $userID,
                $db->q(2),
            ]));//exit;

        // Execute query.
        try
        {
            $varl = $db
                ->setQuery($query)
                ->execute();
            $insertID = (int) $db->insertid();
            $this->addMainGroupProjectMeta($insertID, 5, 'uk');
            $this->addMainGroupProjectMeta($insertID, 4, 'hu');
            $this->addMainGroupProjectMeta($insertID, 2, 'en');
            $this->addMainGroupProjectMeta($insertID, 1, 'de');
            $this->addMainGroupProjectOrg($insertID, 21, 3);
            $this->addMainGroupProjectOrg($insertID, 2, 3);
            $this->addMainGroupProjectOrg($insertID, 13, 3);
            $this->addMainGroupProjectOrg($insertID, 1, 3);//exit;
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $insertID = null;
        }


        // Store placholders for all other languages that are not current language.
        $isError = false;
        return $varl;
        //return (($insertID > 0) ? $formData->mgid : false);
    }
    protected function existsMGProject($proID = null, string $projectNumber = null, $lang = null) : bool
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Init shorthand to database object.
        $db = $this->db;

        // Function parameter check.
        if (is_null($proID) && is_null($projectNumber))
        {
            // TODO - translate
            throw new InvalidArgumentException('Function requires at least 1 argument.');
        }

        /* Force UTF-8 encoding for proper display of german Umlaute
         * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
         */
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        // Build query.
        $query = $db->getQuery(true)
            ->select($db->qn('proID'))
            ->from($db->qn('projects'));

        switch (true)
        {
            // Should find existing organisation identified by orgID + orgName.
            case (!empty($proID) && !empty($projectNumber)) :
                $query
                    ->where($db->qn('proID') . ' = ' . (int) $proID)
                    ->where('LOWER(' . $db->qn('number') . ') = LOWER( TRIM(' . $db->q(trim($projectNumber)) . ') )');
                break;

            // Should find existing organisation identified by orgID.
            case (!empty($proID) && (int) $proID > 0) :
                $query
                    ->where($db->qn('proID') . ' = ' . (int) $proID);
                break;

            // Should find existing organisation identified by orgName.
            case (!empty($projectNumber) && trim($projectNumber) !== '') :
                $query
                    ->where('LOWER(' . $db->qn('number') . ') = LOWER( TRIM(' . $db->q(trim($projectNumber)) . ') )');
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
