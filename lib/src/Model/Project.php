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
class Project extends ItemModel
{
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
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $itemID
	 *
	 * @return  Entity\Project
	 */
	public function getItem(int $itemID) : Entity\Project
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('projects', ['language' => $this->language])->getList(['proID' => $itemID]),
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

	// Proxy-method
	public function getItemByNumber(string $projectNumber)
	{
		return $this->getProjectByNumber($projectNumber);
	}

	public function getItemMeta(int $proID, string $lang, bool $isNotNull = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->hasProjectMeta($proID, $lang, $isNotNull, true);
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
			->select('proID')
			->from($db->qn('projects'))
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

	public function addProject($project)
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

		$formData = ArrayHelper::getValue($project, 'form', [], 'ARRAY');


		// Dupe-check project number.
		if (true === $this->existsProject(ArrayHelper::getValue($formData, 'proid'), ArrayHelper::getValue($formData, 'number')))
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

		$query = $db->getQuery(true)
		->insert($db->qn('projects'))
		->columns(
			$db->qn([
				'number',
				'name',
				'customer',
				'order',
				'created',
				'created_by'
			])
		)
		->values(implode(',', [
			$db->q(filter_var($rowData->get('number'),   FILTER_SANITIZE_STRING)),
			$db->q(filter_var($rowData->get('name'),     FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('customer'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('order'),    FILTER_SANITIZE_NUMBER_INT)),
			$db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			(int) $userID
		]));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();	// WARNING: insert_id will be empty if the targeted table has no primary key
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$insertID = null;
		}

		// Inject insert_id as foreign key value for the metadata store project.
		$formData->proid = $insertID;

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Store project meta information.
		$metaStored = $this->storeProjectMeta($formData->proid, $formData);

		// Get all registered app languages and drop currently active language
		$langs = array_filter($this->getInstance('languages', ['language' => $this->language])->getList(['filter' => Lizt::FILTER_ACTIVE, 'onlyTags' => true]), function($language)
		{
			// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
			$language = new Registry($language);

			return $language->get('tag') !== $this->language;
		});

		// Store placholders for all other languages that are not current language.
		$isError = false;

		array_walk($langs, function($language, $tag) use(&$formData, &$isError)	// in addProject()
		{
			if ($isError)
			{
				return;
			}

			// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
			$language = new Registry($language);

			// Skip prev. stored metadata object.
			if ($language->get('lngID') == $formData->lngID)
			{
				return;
			}

			$formData->lngID = $language->get('lngID');
			$formData->lng   = $language->get('tag');
			$formData->name  = Text::translate('COM_FTK_NA_TEXT', $language->get('tag'));
			$formData->description = null;

			// Store project meta information placeholder.
			$isError = !$this->storeProjectMeta($formData->proid, $formData);
		});

		return (($insertID > 0 && $metaStored) ? $formData->proid : false);
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

	public function lockProject(int $proID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*// Can this user delete content?
		if (!$this->userCanLock())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load item from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($proID);

		if (!is_a($item, ' \Entity\Project') || !$item->get('proID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive projects at all?
		if (false === $this->canLockProject($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this item.
		if (false === $this->itemIsLockable($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('1'),
			$db->qn('blockDate')   . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('blocked_by')  . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('proID')   . ' = ' . $db->q($proID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
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

		return $proID;
	}

	public function unlockProject(int $proID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRestore())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load item from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($proID);

		if (!is_a($item, ' \Entity\Project') || !$item->get('proID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover projects at all?
		if (false === $this->canRestoreProject($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this item.
		if (false === $this->itemIsRestorable($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('0'),
			$db->qn('blockDate')   . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('blocked_by')  . ' = NULL'
		])
		->where($db->qn('proID')   . ' = ' . $db->q($proID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
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

		return $proID;
	}

	public function archiveProject(int $proID)	// This is currently the opposite of restoreProject - it blockes and archives an accessible item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*// Can this user delete content?
		if (!$this->userCanArchivate())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load project from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($proID);

		if (!is_a($item, ' \Entity\Project') || !$item->get('proID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive projects at all?
		if (false === $this->canArchiveProject($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this project.
		if (false === $this->projectIsArchivable($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('1'),
			$db->qn('archiveDate') . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('archived_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('proID')   . ' = ' . $db->q($proID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
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

		return $proID;
	}

	public function restoreProject(int $proID)		// This is currently the opposite of archiveProject - it restored an archived item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRestore())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_ITEM_STATE_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load project from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($proID);

		if (!is_a($item, ' \Entity\Project') || !$item->get('proID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover projects at all?
		if (false === $this->canRestoreProject($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this project.
		if (false === $this->projectIsRestorable($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('0'),
			$db->qn('archiveDate') . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('archived_by') . ' = NULL'
		])
		->where($db->qn('proID')   . ' = ' . $db->q($proID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
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

		return $proID;
	}

	public function deleteProject(int $proID)		// This is currently the opposite of recoverProject - it deletes an accessible item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Can this user delete content?
		if (!$this->userCanDelete())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
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

		// Load project from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($proID);

		if (!is_a($item, ' \Entity\Project') || !$item->get('proID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Is this user allowed to delete projects at all?
		if (false === $this->canDeleteProject($proID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this project.
		if (false === $this->projectIsDeletable($proID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Build query.
		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('1'),
			$db->qn('trashDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('trashed_by') . ' = ' . $db->q((int) $user->get('userID')),
			$db->qn('deleted')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('deleted_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('proID')  . ' = ' . $db->q($proID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Reset AUTO_INCREMENT count.
		try
		{
			$db
			->setQuery('ALTER TABLE `projects` AUTO_INCREMENT = 1')
			->execute();
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

		return $proID;
	}

	public function recoverProject(int $proID)		// This is currently the opposite of deleteProject - it recovers a deleted item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRecover())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_RECOVER_TEXT', $this->language)
			]);

			return false;
		}*/

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load project from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($proID);

		if (!is_a($item, ' \Entity\Project') || !$item->get('proID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover projects at all?
		if (false === $this->canRestoreProject($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this project.
		if (false === $this->projectIsRestorable($proID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('0'),
			$db->qn('trashDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('trashed_by') . ' = NULL',
			$db->qn('deleted')    . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('deleted_by') . ' = NULL'
		])
		->where($db->qn('proID')  . ' = ' . $db->q($proID));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
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

		return $proID;
	}

	/**
	 * Checks whether a specific project is active.
	 *
	 * @param   int|null     $proID          Optional. A project id.
	 * @param   string|null  $projectNumber  Optional. A project number.
	 *
	 * @return  bool  True if project is available, false otherwise
	 */
	public function isAvailable(int $proID = null, string $projectNumber = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Function parameter check.
		if (is_null($proID) && is_null($projectNumber))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function requires at least 1 argument.');
		}

		$item = isset($proID)
			? $this->getItem($proID)
			: (
				isset($projectNumber)
					? $this->getItemByNumber($projectNumber)
					: $this->getItem(0)
			);

		if (!$item->get($item->getPrimaryKeyName()))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return false;
		}

		switch (true)
		{
			case (isset($projectNumber) && $item->get('number') !== $projectNumber)  :
				$message = sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_NUMBER_MISMATCH_TEXT', $this->language), $item->get('number'), $projectNumber);
			break;

			case ($item->get('blocked_by')) :
				$message = Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_IS_BLOCKED_TEXT', $this->language);
			break;

			case ($item->get('archived_by')) :
				$message = Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_IS_ARCHIVED_TEXT', $this->language);
			break;

			case ($item->get('trashed_by')) :
				$message = Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_IS_TRASHED_TEXT', $this->language);
			break;

			case ($item->get('deleted_by')) :
				$message = Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_IS_DELETED_TEXT', $this->language);
			break;
		}

		if (isset($message))
		{
			Messager::setMessage([
				'type' => 'notice',
				'text' => $message
			]);

			return false;
		}

		return true;
	}


	protected function existsProject($proID = null, string $projectNumber = null, $lang = null) : bool
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

	protected function canDeleteProject(int $proID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_null(App::getAppUser()))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_FAILURE_TEXT', $this->language)
			]);

			return false;
		}

		// TODO - Implement ACL and make calculate editor-right from ACL
		$canDelete = true;
		// $canDelete = (\is_object($user) ? (bool) $user->rights->canDelete : false);
		// $canDelete = (\is_object($user) && $user->groups ? (bool) $user->rights->canDelete : false);
		// $canDelete = (($user instanceof User && $user->hasRole(FTKUser::RoleEditor) ? (bool) $user->rights->canDelete : false);

		if (!$canDelete)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

		return true;
	}

	protected function projectIsDeletable(int $proID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (false !== ($dependencies = $this->projectHasDependencies($proID)))
		{
			$dependencies = array_filter($dependencies, function($val, $type)
			{
				return $val > 0;

			}, ARRAY_FILTER_USE_BOTH);

			if (count($dependencies) == '0')
			{
				return true;
			}

			$msg = [''];

			foreach ($dependencies as $type => $count)
			{
				// FIXME - translation of singular or plural $type (articles vs. article, organisations vs. organisation)
				if ($count == 1)
				{
					array_push(
						$msg,
						sprintf(Text::translate(mb_strtoupper('COM_FTK_SYSTEM_MESSAGE_' . ($count == 1 ? mb_substr($type, 0, mb_strlen($type) - 1) : $type) . '_IS_DEPENDENCY_TEXT'), $this->language), $count)
					);
				}
				else
				{
					array_push(
						$msg,
						sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_' . mb_strtoupper($type) . '_ARE_DEPENDENCIES_TEXT', $this->language), $count)
					);
				}
			}

			Messager::setMessage([
				'type' => 'error',
				'text' => sprintf("%s<br/>%s",
					Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_DELETION_DEPENDENCIES_TEXT', $this->language),
					Text::translate(sprintf('%s', implode($msg, '<br>')), $this->language)
				)
			]);

			return false;
		}

		return true;
	}

	//@todo - implement proper check for dependencies
	protected function projectHasDependencies(int $aid) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Project has dependencies when it has members, etc.

		return false;
	}


	protected function hasProjectConfig(int $proID, string $lang, bool $returnData = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$table = 'projects';
		$query = $db->getQuery(true)
		->from($db->qn($table))
		->select($db->qn('config'))
		->where($db->qn('proID') . ' = ' . $proID);

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssoc();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return ($returnData) ? $rs : count($rs) > 0;
	}

	protected function storeProjectConfig(int $proID, $projectConfig) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user  = App::getAppUser();

//		$debug = $user->isProgrammer() && $user->get('userID') == '1';
		$debug = false;

		// @debug
		if ($debug) :
			echo '<pre style="color:blue">' . print_r(__METHOD__, true) . '</pre>';
			echo '<pre><strong>projectConfig:</strong> ' . print_r($projectConfig, true) . '</pre>';
//			die;
		endif;

		if (!count(array_filter((array) $projectConfig)))
		{
			return true;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		if (!is_object($projectConfig))
		{
			$projectConfig = (object) $projectConfig;
		}

		// Prepare project config for storing.
		$config = (property_exists($projectConfig, 'config') && is_array($projectConfig->config)) ? $projectConfig->config : null;

		// @debug
		if ($debug) :
			echo '<pre><strong>config 1:</strong> ' . print_r($config, true) . '</pre>';
//			die;
		endif;

		$config = (is_array($config) ? json_encode($config, JSON_THROW_ON_ERROR) : null);

		// @debug
		if ($debug) :
			echo '<pre><strong>config 2:</strong> ' . print_r($config, true) . '</pre>';
			die;
		endif;

		if (is_null($config))
		{
			return true;
		}

		$query = $db->getQuery(true)
		->update($db->qn('projects'))
		->set($db->qn('config')  . ' = ' . $db->q($config))
		->where($db->qn('proID') . ' = ' . $proID);

		// Execute query.
		try
		{
			$db
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

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}


	protected function hasProjectMeta(int $proID, string $lang, bool $isNotNull = false, bool $returnData = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$table = 'project_meta';
		$query = $db->getQuery(true)
		->from($db->qn($table))
		->where($db->qn('proID')    . ' = ' . $proID)
		->where($db->qn('language') . ' = ' . $db->q(trim($lang)));

		if ($isNotNull)
		{
			$query
			->where($db->qn('description'). ' IS NOT NULL');
		}

		if ($returnData)
		{
			$columns = DatabaseHelper::getTableColumns($table);

			if (!is_array($columns))
			{
				return null;
			}

			$query
			->select(
				implode(',', $db->qn($columns))
			);
		}
		else
		{
			$query
			->select('COUNT(' . $db->qn('proID') . ') AS ' . $db->qn('count'));
		}

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssoc();

			if (!$returnData)
			{
				$rs = array_map('intval', $rs);
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return ($returnData) ? $rs : $rs['count'] > 0;
	}

	protected function storeProjectMeta(int $proID, $projectMeta) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!count(array_filter((array) $projectMeta)))
		{
			return true;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		if (!is_object($projectMeta))
		{
			$projectMeta = (object) $projectMeta;
		}

		if (!property_exists($projectMeta, 'description'))
		{
			return true;
		}

		$hasMeta = $this->hasProjectMeta((int) $projectMeta->proid, $projectMeta->lng);

		// Build query.
		$rowData = new Registry($projectMeta);

		$projDescription = filter_var($rowData->get('description'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		$projDescription = (is_null($projDescription) || trim($projDescription)  == '') ? "NULL" : $db->q(trim($projDescription));

		if (!$hasMeta)
		{
			$query = $db->getQuery(true)
			->insert($db->qn('project_meta'))
			->columns(
				$db->qn([
					'proID',
					'lngID',
					'description',
					'language'
				])
			)
			->values(implode(',', [
				(int)  filter_var($rowData->get('proid'), FILTER_VALIDATE_INT),
				(int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT),
				$projDescription,
				$db->q(filter_var($rowData->get('lng'),   FILTER_SANITIZE_STRING))
			]));
		}
		else
		{
			$query = $db->getQuery(true)
			->update($db->qn('project_meta'))
			->set([
				$db->qn('description') . ' = ' . $projDescription,
				$db->qn('language')    . ' = ' . $db->q(filter_var($rowData->get('lng'),   FILTER_SANITIZE_STRING))
			])
			->where($db->qn('proID')   . ' = ' . (int)  filter_var($rowData->get('proid'), FILTER_VALIDATE_INT))
			->where($db->qn('lngID')   . ' = ' . (int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT));
		}

		// Execute query.
		try
		{
			$db
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

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	protected function deleteProjectMeta(int $proID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('project_meta'))
		->where($db->qn('proID') . ' = ' . $proID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// return $proID;
		return true;
	}


	public function addProjectMembers(int $proID, array $orgIDs = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Sanitize input.
		$orgIDs = array_map('intval', $orgIDs);

		// Build query.
		$query = 'REPLACE INTO `project_organisation` (`proID`, `orgID`, `roleID`) VALUES (? , ? , ?)';

		// FIXME - get rid of the roleID in this table. It is not required at this level. Drop this parameter here afterwards.
		$roleID = 3;	// Internal

		// Clean up first.
		$cleaned = $this->deleteAllProjectMembers($proID);

		if (!$cleaned)
		{
			// Error is set by called function.
			return false;
		}

		// Store project members.
		$tuples = [];

		// Prepare proID<-->orgID tuples.
		foreach($orgIDs as $orgID)
		{
			$tuples[] = $proID . ',' . (int) $orgID . ',' . $roleID;
		}

		$query = $db->getQuery(true)
		->insert($db->qn('project_organisation'))
		->columns(
			$db->qn([
				'proID',
				'orgID',
				'roleID'
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

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	public function getProjectMembers(int $proID, $orgID = null)
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

		$orgID = (is_null($orgID) ? $orgID : (int) $orgID);

		// Build sub-query first.
		$sub = $db->getQuery(true)
		->from($db->qn('project_organisation', 'po'))
		->select($db->qn('po.orgID'));

		if (!is_null($proID))
		{
			$sub->where($db->qn('po.proID') . ' = ' . $proID);
		}

		if (!is_null($orgID))
		{
			$sub->where($db->qn('po.orgID') . ' = ' . (int) $orgID);
		}

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('organisations', 'o'))
		->join('LEFT', $db->qn('organisation_meta') . ' AS ' . $db->qn('om') . ' ON ' . $db->qn('o.orgID')  . ' = ' . $db->qn('om.orgID'))
		->join('LEFT', $db->qn('organisation_role') . ' AS ' . $db->qn('or') . ' ON ' . $db->qn('o.orgID')  . ' = ' . $db->qn('or.orgID'))
		->join('LEFT', $db->qn('roles')             . ' AS ' . $db->qn('r')  . ' ON ' . $db->qn('r.roleID') . ' = ' . $db->qn('or.roleID'))
		->select([
			$db->qn('o.orgID'),
			$db->qn('o.blocked'),
			$db->qn('o.name'),
			$db->qn('or.roleID'),
			"CONCAT('{','\"roleID\":\"', `r`.`roleID`, '\",\"abbreviation\":\"', `r`.`abbreviation`, '\",\"name\":\"', `r`.`name`, '\"}') AS " . $db->qn('role'),
			$db->qn('o.created'),
			$db->qn('o.created_by'),
			$db->qn('o.modified'),
			$db->qn('o.modified_by')
		])
		->where($db->qn('o.orgID')  . ' IN (' . $sub . ')');

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, ' \Entity\User') || (is_a($user, ' \Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('o.blocked') . ' = ' . $db->q('0'));
		}

		$query
		->group($db->qn('o.orgID'))
		->order($db->qn('o.name'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// FIXME - get rid of loading row data into entity. Just collect object and load on demand where it is required.
				// Load row data into a new entity and add it to the collection.
				$rows[$row['orgID']] = Entity::getInstance('organisation', ['id' => $row['orgID'], 'language' => $this->language])->bind($row);
				// $rows[$row->orgID] = $row;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}

	public function getProjectMember(int $proID, int $orgID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return ArrayHelper::getValue(
			$this->getProjectMembers($orgID, $proID),
            $orgID,
			null
		);
	}

	//@return deleted member ID
	public function deleteProjectMember(int $proID, int $orgID) : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Can this user delete content?
		if (!$this->userCanDelete())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

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
		->delete($db->qn('project_organisation'))
		->where($db->qn('proID')    . ' = ' . $proID)
		->andWhere($db->qn('orgID') . ' = ' . $orgID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		return $orgID;
	}

	//@return project ID
	public function deleteAllProjectMembers(int $proID) : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Can this user delete content?
		if (!$this->userCanDelete())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

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
		->delete($db->qn('project_organisation'))
		->where($db->qn('proID') . ' = ' . $proID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		return $proID;
	}

	// REFACTORED on 2023-10-04 - the database query was unperformant and had to be refactored
	public function getBadParts(int $proID, bool $totalOnly = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user  = App::getAppUser();

//		$debug = $user->isProgrammer() && $user->get('userID') == '1';
		$debug = false;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Execute query.
		try
		{
			/*
			--
			-- OLD statement
			--
			SELECT `t`.`partID`
			FROM `tracking` AS `t`
			WHERE `t`.`partID` IN (
				SELECT `p`.`partID`
				FROM `parts` AS `p`
				WHERE `p`.`artID` IN (
					SELECT `a`.`artID`
					FROM `articles` AS `a`
					WHERE `a`.`number` LIKE CONCAT(
					'%.',
					(SELECT `p`.`number` FROM `projects` AS `p` WHERE `p`.`proID` = 37),
					'.%'
					)
				)
			)
			AND `t`.`paramID` = 6
			AND `t`.`paramValue` > 0;
			*/

			// @debug
			if ($debug) :
				// Starting clock time in seconds
				$start_time = microtime(true);
			endif;

			// Build sub-query to get the project number from the passed project ID.
			$sub3 = $db->getQuery(true)
			->from($db->qn('projects', 'p'))
			->select($db->qn('p.number'))
			->where($db->qn('p.proID') . ' = ' . $proID);

			// Build sub-query to get all article IDs that match the project number.
			$sub2 = $db->getQuery(true)
			->from($db->qn('articles', 'a'))
			->select($db->qn('a.artID'))
			->where($db->qn('a.number') . sprintf(' LIKE CONCAT(%s)', join(',', [
				$db->q('%.'),
				'(' . DatabaseHelper::dumpQuery($sub3, false) . ')',
				$db->q('.%')
			])));

			// Build sub-query to get all article IDs that match the project number.
			$sub1 = $db->getQuery(true)
			->from($db->qn('parts', 'p'))
			->select($db->qn('p.partID'))
			->where($db->qn('p.artID') . ' IN (' . DatabaseHelper::dumpQuery($sub2, false) . ')');

			// Build main query.
			$query = $db->getQuery(true)
			->from($db->qn('tracking', 't'))
			->where($db->qn('t.partID')     . ' IN (' . DatabaseHelper::dumpQuery($sub1, false) . ')')
			->where($db->qn('t.paramID')    . ' = '   . Techparams::STATIC_TECHPARAM_ERROR)
			->where($db->qn('t.paramValue') . ' > 0');

			// Decide whether to fetch the row count or the rows.
			if ($totalOnly)
			{
				$query
				->select('COUNT(' . $db->qn('t.partID') . ') AS ' . $db->qn('parts'));
			}
			else
			{
				$query
				->select($db->qn('t.partID'));
			}

			// @debug
			if ($debug) :
				echo '<pre><strong>query old:</strong> ' . print_r(DatabaseHelper::dumpQuery($query, false), true) . '</pre>';
//				die;
			endif;

			// Execute query.
			if ($totalOnly)
			{
				// @debug
				if ($debug) :
					echo '<pre><strong>call:</strong> ' . print_r('query->loadResult()', true) . '</pre>';
				endif;

				$rows = $db->setQuery($query)->loadResult();
			}
			else
			{
				// @debug
				if ($debug) :
					echo '<pre><strong>call:</strong> ' . print_r('query->loadColumn()', true) . '</pre>';
				endif;

				$rows = $db->setQuery($query)->loadColumn();
			}

			// @debug
			if ($debug) :
				// End clock time in seconds
				$end_time = microtime(true);

				// Calculating the script execution time
				$execution_time = $end_time - $start_time;
			endif;

			// @debug
			if ($debug) :
				echo '<pre><strong>Execution time:</strong> ' . print_r(sprintf('%s Seconds', round($execution_time, 2)), true) . '</pre>';
//				die;
			endif;
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		// @debug
		if ($debug) :
			echo '<pre>return: ' . print_r($rows, true) . '</pre>';
			die;
		endif;

		return $rows;
	}

	public function getCertificates(int $proID, string $fromDate = null, string $toDate = null) : array
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

		// Prepare lookup date interval.
		$fromDate = ($fromDate) ? trim($fromDate) : null;
		$fromDate = ($fromDate) ? date_create($fromDate) : null;
		$fromDate = (is_a($fromDate, 'DateTime')) ? $fromDate->format('Y-m-d 00:00:01') : null;
		$toDate   = ($toDate) ? trim($toDate) : null;
		$toDate   = ($toDate) ? date_create($toDate) : null;
		$toDate   = (is_a($toDate, 'DateTime')) ? $toDate->format('Y-m-d 23:59:59') : null;
		$date     = new DateTime('NOW');
		$date     = (is_a($date, 'DateTime')) ? $date->format('Y-m-d 23:59:59') : null;

		// Get project first to ensure that it exists and to fetch its number.
		$project  = $this->getItem($proID);

		// Build sub-queries first.
		$sub1 = $db->getQuery(true)
		->from($db->qn('articles', 'w2'))
		->select($db->qn('w2.artID'))
		->where($db->qn('w2.number') . ' LIKE "%.' . $project->get('number') . '.%"');

		$sub2 = $db->getQuery(true)
		->from($db->qn('parts', 'w1'))
		->select($db->qn('w1.partID'))
		->where($db->qn('w1.artID') . ' IN( ' . $sub1 . ')');

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->join('LEFT', $db->qn('tracking') . ' AS ' . $db->qn('t') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('t.partID'))
		->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('a.artID')  . ' = ' . $db->qn('p.artID'))
		->select($db->qn('p.trackingcode') . ' AS ' . $db->qn('Teil'))
		->select($db->qn('a.number')       . ' AS ' . $db->qn('Artikel'))
		->select($db->qn('t.paramValue')   . ' AS ' . $db->qn('Zertifikat'))
		->select($db->qn('t.timestamp')    . ' AS ' . $db->qn('Datum'))
		->where($db->qn('p.partID')  . ' IN( ' . $sub2 . ')')
		->where($db->qn('t.paramID') . ' = ' . 29) // FIXME - fetch tparam ID from DB
//		->order($db->qn('p.trackingcode'));
		->order($db->qn(['a.number','t.timestamp']));

		switch (true)
		{
			case ( $fromDate && $toDate) :
				$query->where($db->qn('t.timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate));
			break;

			case ( $fromDate && !$toDate) :
				$query->where($db->qn('t.timestamp') . ' >= ' . $db->q($fromDate));
			break;

			case (!$fromDate && $toDate) :
				$query->where($db->qn('t.timestamp') . ' <= ' . $db->q($toDate));
			break;
		}

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList('Teil');
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rs;
	}
}
