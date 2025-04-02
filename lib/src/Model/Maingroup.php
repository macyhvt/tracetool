<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \Access;
use  \App;
use  \Entity;
use  \Helper\DatabaseHelper;
use  \Messager;
use  \Model\Item as ItemModel;
use  \Text;
use function array_filter;
use function array_map;
use function array_push;
use function array_walk;
use function is_a;
use function is_array;
use function is_null;
use function is_object;
use function is_scalar;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function property_exists;

/**
 * Class description
 */
class Maingroup extends ItemModel
{
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
		parent::__construct($options);
	}

	public function getItem(int $itemID) : Entity\Maingroup
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;
		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('maingroups', ['language' => $this->language])->getList(['mgid' => $itemID]),
				$itemID
			);
		}
		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf(' \Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf(' \Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null, 'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('proID')) {}
		return $row;
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
         $query->select('*')
            ->from($db->qn('maingroups', 'a'))
            ->order($db->qn('a.group_name'));
        // Execute query.
        try
        {
            $db->setQuery($query);
            $artNum = $db->loadAssocList();
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
	public function getProjectByNumber(string $projectNumber)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_a($projectNumber, ' \Entity\Project'))
		{
			$project = $projectNumber;
		}
		else
		{
			// Init shorthand to database object.
			$db = $this->db;

			/* Force UTF-8 encoding for proper display of german Umlaute
			 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
			 */
			$db->setQuery('SET NAMES utf8')->execute();
			$db->setQuery('SET CHARACTER SET utf8')->execute();

			// Build query.
			$query = $db->getQuery(true)
			->select('mgid')
			->from($db->qn('maingroups'))
			->where('LOWER(' . $db->qn('number') . ') = LOWER( TRIM(' . $db->q(trim($projectNumber)) . ') )');

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadResult();

				$project = $this->getItem((int) $rs);
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
			$db
			->getQuery()
			->clear();

			$db
			->freeResult();
		}

		return $project;
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
    public function addMainGroupAssigns($mgid,$proids)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();
        $userID = $user->get('userID');
        // Init shorthand to database object.
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        //$formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');

        /// echo "<pre>";print_r($rowData);exit;
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
                $db->q(filter_var($mgid,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($proids,   FILTER_SANITIZE_STRING)),
                $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
                (int) $userID,


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
    public function addMainGroupProject($mgid, $projectno)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Init shorthand to database object.
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        //$formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');

        // Dupe-check project number.
        if (true === $this->existsMGProject($mgid,$projectno))
        {
            Messager::setMessage([
                'type' => 'info',
                'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROJECT_TEXT', $this->language),
                    ArrayHelper::getValue($projectno, 'number', null, 'STRING')
                )
            ]);

            return false;
        }


        $userID = $user->get('userID');

        /// echo "<pre>";print_r($rowData);exit;
        $query = $db->getQuery(true)
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
                $db->q(filter_var($projectno,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($projectno,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($projectno,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var('P',   FILTER_SANITIZE_STRING)),
                $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
                (int) $userID,
                $db->q(filter_var($mgid,   FILTER_SANITIZE_STRING)),
            ]));

        // Execute query.
        try
        {
            $db
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
            $this->addMainGroupProjectOrg($insertID, 1, 3);

            $this->addMainGroupAssigns($mgid, $insertID);
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
	public function addMainGroup($project)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');


		// Dupe-check project number.
		if (true === $this->existsProject(ArrayHelper::getValue($formData, 'mgid'), ArrayHelper::getValue($formData, 'group_name')))
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
		$rowData = new Registry($formData);
       /// echo "<pre>";print_r($rowData);exit;
		$query = $db->getQuery(true)
		->insert($db->qn('maingroups'))
		->columns(
			$db->qn([
				'group_name',
                'added_by',
				'created_on',
                'status',
                'explaination'
			])
		)
		->values(implode(',', [
			$db->q(filter_var($rowData->get('group_name'),   FILTER_SANITIZE_STRING)),
            (int) $userID,
			$db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
            $db->q(filter_var('0',   FILTER_SANITIZE_STRING)),
            $db->q(filter_var($rowData->get('explaination'),   FILTER_SANITIZE_STRING)),
		]));

		// Execute query.
		try
		{
			$varl = $db
			->setQuery($query)
			->execute();
			$insertID = (int) $db->insertid();
            //echo $insertID;exit;
            //addMainGroupProject
            $this->addMainGroupProject($insertID, $formData->number);

            //exit;
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


		// Store placholders for all other languages that are not current language.
		$isError = false;
        return $varl;
		//return (($insertID > 0) ? $formData->mgid : false);
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

print_r($formData);exit;
        // Dupe-check project number.
        if (true === $this->existsMGProject(ArrayHelper::getValue($formData, 'mgid'), ArrayHelper::getValue($formData, 'number')))
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
        $rowData = new Registry($formData);
        /// echo "<pre>";print_r($rowData);exit;
        $query = $db->getQuery(true)
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
                $db->q(filter_var($projectno,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($projectno,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var($projectno,   FILTER_SANITIZE_STRING)),
                $db->q(filter_var('P',   FILTER_SANITIZE_STRING)),
                $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
                (int) $userID,
                $db->q(filter_var($mgid,   FILTER_SANITIZE_STRING)),
            ]));

        // Execute query.
        try
        {
            $varl = $db
                ->setQuery($query)
                ->execute();
            $insertID = (int) $db->insertid();
            //echo $insertID;exit;
            //addMainGroupProject
            $this->addMainGroupProject($insertID, $formData->number);

            //exit;
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


        // Store placholders for all other languages that are not current language.
        $isError = false;
        return $varl;
        //return (($insertID > 0) ? $formData->mgid : false);
    }
	public function updateProject($project)
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

		$formData = $project['form'] ?? [];

		// Existence check.
		if (!$this->existsProject(ArrayHelper::getValue($formData, 'proid', null, 'INT')))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such project: %s', $this->language), $formData['number'])
			]);

			return false;
		}

		// Dupe check.
		/*// Old solution disabled for backup purpose in case the new solution below won't reliably work.
		if ($tmpProject = $this->getProjectByNumber(ArrayHelper::getValue($formData, 'number', null, 'STRING')))
		{
			// Compare both IDs. If they're different, then another item already uses the number this item shall use, which is not allowed.
			if (\is_a($tmpProject, ' \Entity\Project')
			&& \is_int($tmpProject->get('proID'))
			&& ($tmpProject->get('proID') != ArrayHelper::getValue($formData, 'proid', null, 'INT')))
			) {
				Messager::setMessage([
					'type' => 'info',
					'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROJECT_TEXT', $this->language),
						ArrayHelper::getValue($formData, 'number', null, 'STRING')
					)
				]);

				return false;
			}
			else
			{
				// Free memory.
				unset($tmpProject);
			}
		}*/

		$thisProject = $this->getItem(ArrayHelper::getValue($formData, 'proid', 0, 'INT'));
		$thatProject = $this->getProjectByNumber(ArrayHelper::getValue($formData, 'number'));
//		$nameChanged = false;

		if (isset($thisProject) && isset($thatProject))
		{
			// No conflict - No other project found.
			if (!is_a($thatProject, ' \Entity\Project'))
			{
				// Free memory.
				unset($thatProject);
			}
			// Conflict - Another project exists with this number.
			else
			{
				// If such a project exists compare both project IDs ($_POST vs. search result).
				// If $thatProject is a different project (IDs are different) then the project to be edited
				// must use a different number.
				if (!is_null($thatProject->get('proID')) && $thatProject->get('proID') != $thisProject->get('proID'))
				{
					Messager::setMessage([
						'type' => 'info',
						'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROJECT_TEXT', $this->language),
							ArrayHelper::getValue($formData, 'number', null, 'STRING')
						)
					]);

					// Free memory.
					// unset($thisProject);
					unset($thatProject);

					return false;
				}
				// No conflict - Editing same project.
				/* @note - block disabled on 2021-Jan-03 because property $nameChanged isn't further used
				else
				{
					$nameChanged = $thatProject->get('name') != $thisProject->get('name');
				}*/
			}

			// Free memory.
			// unset($thisProject);
			unset($thatProject);
		}

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		$userID = $user->get('userID');

		// Get changed data.
		$rowData = new Registry;

		foreach ($formData as $key => $value)
		{
			if (property_exists($thisProject, $key) && is_scalar($value) && $thisProject->get($key) !== $value)
			{
				$rowData->def($key, $value);
			}
		}

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('number')      . ' = ' . $db->q(filter_var($rowData->get('number',   $thisProject->get('number')),   FILTER_SANITIZE_STRING)),
			$db->qn('status')      . ' = ' . $db->q(filter_var($rowData->get('status',   $thisProject->get('status')),   FILTER_SANITIZE_STRING)),
			$db->qn('name')        . ' = ' . $db->q(filter_var($rowData->get('name',     $thisProject->get('name')),     FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->qn('customer')    . ' = ' . $db->q(filter_var($rowData->get('customer', $thisProject->get('customer')), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->qn('order')       . ' = ' . $db->q(filter_var($rowData->get('order',    $thisProject->get('order')),    FILTER_SANITIZE_NUMBER_INT)),
			$db->qn('modified')    . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('modified_by') . ' = ' . (int) $userID
		])
		->where($db->qn('proID')   . ' = ' . (int) $thisProject->get('proID'));

		// Execute query.
		try
		{
			if (count($rowData->toArray()))
			{
				$db
				->setQuery($query)
				->execute();

//				$affectedRows = $db->getAffectedRows();
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

//			$affectedRows = null;
		}

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		$metaStored   = $this->storeProjectMeta($formData->proid, $formData);

		$configStored = true;

		if (property_exists($formData, 'config') && isset($formData->config))
		{
			$configStored = $this->storeProjectConfig($formData->proid, $formData);
		}

		return (($metaStored && $configStored) ? $formData->proid : false);
	}

	protected function existsProject($mgid = null, string $group_name = null, $lang = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;
		// Function parameter check.
		if (is_null($mgid) && is_null($group_name))
		{
			throw new InvalidArgumentException('Function requires at least 1 argument.');
		}

		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('mgid'))
		->from($db->qn('maingroups'));

		switch (true)
		{
			case (!empty($mgid) && !empty($group_name)) :
				$query
				->where($db->qn('mgid') . ' = ' . (int) $mgid)
				->where('LOWER(' . $db->qn('group_name') . ') = LOWER( TRIM(' . $db->q(trim($group_name)) . ') )');
			break;

			// Should find existing organisation identified by orgID.
			case (!empty($mgid) && (int) $mgid > 0) :
				$query
				->where($db->qn('mgid') . ' = ' . (int) $mgid);
			break;

			// Should find existing organisation identified by orgName.
			case (!empty($group_name) && trim($group_name) !== '') :
				$query
				->where('LOWER(' . $db->qn('group_name') . ') = LOWER( TRIM(' . $db->q(trim($group_name)) . ') )');
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

    public function getAllMaingroup($groupname = null)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Init shorthand to database object.
        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->qn('maingroups'))
            ->where($db->qn('group_name')   . ' = ' . $db->q($groupname));

        try {
            $rs = $db->setQuery($query)->loadAssocList();
        }catch (Exception $e){
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs ;
    }

    public function getMainGroupProjects(int $nestingLevel = 3) //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        $db = $this->db;
        // Get current user object.
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        $query = $db->getQuery(true);
        // Build query.
        $query->select('*')
            ->from($db->qn('projects', 'a'))
            ->where($db->qn('mgid') . ' != ' . (int) 0)
            ->order($db->qn('a.number'));
        // Execute query.
        try
        {
            $db->setQuery($query);
            $artNum = $db->loadAssocList();
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
