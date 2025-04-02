<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Joomla\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Entity;
use  \Helper\DatabaseHelper;
use  \Messager;
use  \Model\Item as ItemModel;
use  \Model\Lizt as ListModel;
use  \Text;
use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_walk;
use function is_a;
use function is_array;
use function is_int;
use function is_null;
use function is_object;
use function is_string;

// FIXME - Gleichzeitiges Anlegen eines Prozesses MIT Parametern wirft einen Fehler für das Speichern der Parameter (nachträgliches erstellen/zuweisen funzt aber)

/**
 * Class description
 */
class Process extends ItemModel
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

	/***   BEGIN: API-Service(s)   ***/

	// Adds a row into the tracking.approval table in the database.
    public function approve(array $data) : ?bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/* Pre-Flight - execute these checks first */

		// Generate variables from function arguments.
		extract($data);

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$tableName = 'tracking_approval';

		if (!is_array($columns = DatabaseHelper::getTableColumns($tableName)))
		{
			return false;
		}

		// Drop pk-column as we don't add a value for that.
		array_shift($columns);

		// Drop timestamp-column, as this is automatically set on row insertion.
		array_pop($columns);

		// @debug - FIXME get userID via email + password.
		$data = array_merge($data, ['userID' => 1]);

		// Combine table columns and the data received to ensure every columns's gonna receive a value.
		// The order of the variables is important because it must match the names of the table columns.
		// D O   N O T   C H A N G E  !!!!
		$values = array_combine($columns, [
			$partID,
			$procID,
			$approverID,	// ID of the approval issuer
			$permiteeID,	// ID of the approval recipient
			$IP,
			$dateISO8601,
			$token
		]);

		// Build query.
		try
		{
			$query = $db->getQuery(true)
			->insert($db->qn($tableName))
			->columns($db->qn($columns))
			->values(implode(',', $db->q(array_values($values))));

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();
		}
		catch (Exception $e)
		{
//			throw new Exception($e->getMessage());

			/*Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);*/

			$insertID = 0;

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $insertID > 0;
	}

	/***   END: API-Service(s)   ***/

	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param   int         $itemID
	 * @param   string|null $lang
	 * @param   false       $withParams
	 * @param   false       $withCatalog
	 *
	 * @return  Entity\Process
	 */
	public function getItem(int $itemID, string $lang = null, bool $withParams = false, bool $withCatalog = false) : Entity\Process
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		$row = null;

		if ($itemID > 0)
		{
			$args = [
				'procID'   => $itemID,
				'language' => $lang,
				'params'   => $withParams,
				'catalog'  => $withCatalog,
//				'filter'   => ListModel::FILTER_ACTIVE
			];

//			if ($user->isProgrammer()) :
				$list = (array) $this->getInstance('processes', ['language' => $this->language])->getListNEW($args);	// FIXME - once implementation is finished drop condition 'user->isProgrammer'
//			else :
//				$list = (array) $this->getInstance('processes', ['language' => $this->language])->getList($args);
//			endif;

			$row  = ArrayHelper::getValue($list, $itemID);
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf(' \Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf(' \Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('procID')) {}

		return $row;
	}

	public function getProcessByAbbreviation(string $processAbbreviation) : Entity\Process
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_a($processAbbreviation, ' \Entity\Process'))
		{
			$process = $processAbbreviation;
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
			->select($db->qn('procID'))
			->from($db->qn('processes'))
			->where('LOWER(' . $db->qn('abbreviation') . ') = LOWER( TRIM(' . $db->q(trim($processAbbreviation)) . ') )');

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadResult();

				$process = $this->getItem((int) $rs);
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
			->freeResult();
		}

		return $process;
	}

	public function getProcessByName(string $processName, int $lngID = null) : Entity\Process
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_a($processName, ' \Entity\Process'))
		{
			$process = $processName;
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
			->select($db->qn('procID'))
			->from($db->qn('process_meta'))
			->where('LOWER(' . $db->qn('name') . ') = LOWER( TRIM(' . $db->q(trim($processName)) . ') )');

			if (!is_null($lngID))
			{
				$query
				->where($db->qn('lngID') . ' = ' . $lngID);
			}

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadResult();

				$process = $this->getItem((int) $rs);
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

		return $process;
	}


	public function getItemMeta(int $procID, string $lang, bool $isNotNull = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->hasProcessMeta($procID, $lang, $isNotNull, true);
	}


	public function addProcess($process)
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

		$formData = ArrayHelper::getValue($process, 'form', [], 'ARRAY');


		// Dupecheck process name.
		if ($this->existsProcess(ArrayHelper::getValue($formData, 'pid'), ArrayHelper::getValue($formData, 'abbreviation'), $this->language))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROCESS_TEXT', $this->language),
					ArrayHelper::getValue($formData, 'abbreviation', null, 'STRING')
				)
			]);

			return false;
		}

		// Prepare formData to be stored into the database.
		if (array_key_exists('organisations', $formData) && is_array($formData['organisations']))
		{
			$formData['organisations'] = array_map('intval', $formData['organisations']);
			$formData['organisations'] = json_encode($formData['organisations'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		}
		else
		{
			$formData['organisations'] = '[]';
		}

		// Prepare formData to be stored into the database.
		if (array_key_exists('params', $formData) && is_array($formData['params']))
		{
			$formData['params'] = array_map('trim', $formData['params']);
			$formData['params'] = json_encode($formData['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		}
		else
		{
			$formData['params'] = '[]';
		}

		// Convert array to object.
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

		// Prepare process owner(s) for storing.
		$organisations = (is_string($formData->organisations ?? '[]') ? json_decode($formData->organisations, null, 512, JSON_THROW_ON_ERROR) : $formData->organisations);
		$organisations = (is_array($organisations) ? array_map('intval', $organisations) : $organisations);
		$organisations = (is_array($organisations) ? json_encode($organisations, JSON_THROW_ON_ERROR) : '[]');

		// Build query.
		$rowData = new Registry($formData);

		$query = $db->getQuery(true)
		->insert($db->qn('processes'))
		->columns(
			$db->qn([
				'abbreviation',
				'created',
				'created_by'
			])
		)
		->values(implode(',', [
			$db->q(mb_strtolower(preg_replace('#\s+#', '', $rowData->get('abbreviation')))),
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

		// Inject insert_id as foreign key value for the metadata store process.
		$formData->pid = $insertID;

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Store process tracking parameters in current language.
		// Do NOT do this at last, because then it will be stored using wrong language.
		try
		{
			$paramsStored = $this->storeProcessParameters($formData->pid, $formData->lngID, (array) json_decode($formData->params, null, 512, JSON_THROW_ON_ERROR));
		}
		catch (Exception $e)
		{
			$paramsStored = false;

			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);
		}

		// Store process meta information.
		$metaStored = $this->storeProcessMeta($formData->pid, $formData);

		// Store process owner(s).
		$ownerStored = $this->storeProcessOwner($formData->pid, json_decode($organisations, null, 512, JSON_THROW_ON_ERROR));

		// Get all registered app languages and drop currently active language
		$langs = array_filter($this->getInstance('languages', ['language' => $this->language])->getList(['filter' => Lizt::FILTER_ACTIVE, 'onlyTags' => true]), function($language)
		{
			// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
			$language = new Registry($language);

			return $language->get('tag') !== $this->language;
		});

		// Store placholders for all other languages that are not current language.
		$isError = false;

		array_walk($langs, function($language, $tag) use(&$formData, &$isError)	// in addProcess()
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
			$formData->name  = Text::translate('COM_FTK_UNTRANSLATED_TEXT', $language->get('tag'));
			$formData->description = null;

			// Store process meta information placeholder.
			$isError = !$this->storeProcessMeta($formData->pid, $formData);

			/*if ($isError) // Disabled on 2022-07-24 because it is the last statement in a function and must therefore not return on error
			{
				return;
			}*/
		});

		return (($insertID > 0 && $metaStored && $ownerStored && $paramsStored && !$isError) ? $formData->pid : false);
	}


	public function updateProcess($process)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = $process['form'] ?? [];

		// Existence check.
		if (!$this->existsProcess(ArrayHelper::getValue($formData, 'pid')))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such process: %s', $this->language), $formData['name'])
			]);

			return false;
		}

		// Dupe check.
		/*// Old solution disabled for backup purpose in case the new solution below won't reliably work.
		if ($tmpProcess = $this->getProcessByName(ArrayHelper::getValue($formData, 'name'), ArrayHelper::getValue($formData, 'lngID')))
		{
			// Compare both IDs. If they're different, then another item already uses the name this item shall use, which is not allowed.
			if (\is_a($tmpProcess, ' \Entity\Process')
			&& \is_int($tmpProcess->get('procID'))
			&& ($tmpProcess->get('procID') != ArrayHelper::getValue($formData, 'pid'))
			) {
				Messager::setMessage([
					'type' => 'info',
					'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROCESS_TEXT', $this->language),
						ArrayHelper::getValue($formData, 'name', null, 'STRING')
					)
				]);

				return false;
			}
			else
			{
				// Free memory.
				unset($tmpProcess);
			}
		}*/

		$thisProcess = $this->getItem(ArrayHelper::getValue($formData, 'pid', 0, 'INT'));
		$thatProcess = $this->getProcessByName(ArrayHelper::getValue($formData, 'name'), ArrayHelper::getValue($formData, 'lngID'));
		$nameChanged = false;

		if (isset($thisProcess) && isset($thatProcess))
		{
			// No conflict - No other process found.
			if (!is_a($thatProcess, ' \Entity\Process'))
			{
				// Free memory.
				unset($thatProcess);
			}
			// Conflict - Another process exists with this name.
			else
			{
				// If such a process exists compare both process IDs ($_POST vs. search result).
				// If $thatProcess is a different process (IDs are different) then the process to be edited
				// must use a different name.
				if (!is_null($thatProcess->get('procID')) && $thatProcess->get('procID') != $thisProcess->get('procID'))
				{
					Messager::setMessage([
						'type' => 'info',
						'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PROCESS_TEXT', $this->language),
							ArrayHelper::getValue($formData, 'name', null, 'STRING')
						)
					]);

					// Free memory.
					// unset($thisProcess);
					unset($thatProcess);

					return false;
				}
				// No conflict - editing same process.
				else
				{
					$nameChanged = $thatProcess->get('name') != $thisProcess->get('name');
				}
			}
		}

		// Free memory.
		unset($thatProcess);

		// Prepare formData to be stored into the database.
		if (array_key_exists('organisations', $formData) && is_array($formData['organisations']))
		{
			$formData['organisations'] = array_map('intval', $formData['organisations']);
			$formData['organisations'] = json_encode($formData['organisations'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		}
		else
		{
			$formData['organisations'] = '[]';
		}

		// Prepare formData to be stored into the database.
		if (array_key_exists('params', $formData) && is_array($formData['params']))
		{
			$formData['params'] = array_map('trim', $formData['params']);
			$formData['params'] = json_encode($formData['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		}
		else
		{
			$formData['params'] = '[]';
		}

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

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

		// Prepare process owner(s) for storing.
		$organisations = (is_string($formData->organisations ?? '[]') ? json_decode($formData->organisations, null, 512, JSON_THROW_ON_ERROR) : $formData->organisations);
		$organisations = (is_array($organisations) ? array_map('intval', $organisations) : $organisations);
		$organisations = (is_array($organisations) ? json_encode($organisations, JSON_THROW_ON_ERROR) : '[]');

		// Build query.
		$rowData = new Registry($formData);

		// Prepare process configugration for storing.
		$config = $thisProcess->get('config');

		$config->loadArray($rowData->get('config'));

		$query = $db->getQuery(true);

		try
		{
			$query
			->update($db->qn('processes'))
			->set([
				$db->qn('abbreviation') . ' = ' . $db->q(mb_strtolower(preg_replace('#\s+#', '', $rowData->get('abbreviation')))),
				$db->qn('config')       . ' = ' . $db->q(json_encode($config, JSON_THROW_ON_ERROR)),
				$db->qn('modified')     . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
				$db->qn('modified_by')  . ' = ' . (int) $userID
			])
			->where($db->qn('procID')   . ' = ' . (int) filter_var($rowData->get('pid'), FILTER_VALIDATE_INT));

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$affectedRows = null;
		}

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Update process parameters in current language.
		try
		{
			$paramsStored = $this->storeProcessParameters($formData->pid, $formData->lngID, (array) json_decode($formData->params, null, 512, JSON_THROW_ON_ERROR));
		}
		catch (Exception $e)
		{
			$paramsStored = false;

			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);
		}

		// Update process meta information.
		$metaStored = $this->storeProcessMeta($formData->pid, $formData);

		// Update process owner(s). // Store organisation processes.
		if (false === $this->deleteProcessOwner($formData->pid))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_DATABASE_UPDATING_PROCESS_OWNER_TEXT', $this->language)
			]);

			return false;
		}

		$ownerStored = $this->storeProcessOwner($formData->pid, json_decode($organisations, null, 512, JSON_THROW_ON_ERROR));

		return (($affectedRows > 0 && $metaStored && $ownerStored && $paramsStored) ? $formData->pid : false);
	}

	public function lockProcess(int $procID)
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

		// Load item from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($procID);

		if (!is_a($item, ' \Entity\Process') || !$item->get('procID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive processes at all?
		if (false === $this->canLockProcess($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this item.
		if (false === $this->itemIsLockable($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('processes'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('1'),
			$db->qn('blockDate')   . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('blocked_by')  . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('procID') . ' = ' . $db->q($procID));

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

		return $procID;
	}

	public function unlockProcess(int $procID)
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

		// Load item from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($procID);

		if (!is_a($item, ' \Entity\Process') || !$item->get('procID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover processes at all?
		if (false === $this->canRestoreProcess($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this item.
		if (false === $this->itemIsRestorable($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('processes'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('0'),
			$db->qn('blockDate')   . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('blocked_by')  . ' = NULL'
		])
		->where($db->qn('procID') . ' = ' . $db->q($procID));

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

		return $procID;
	}

	public function archiveProcess(int $procID)	// This is currently the opposite of restoreProcess - it blockes and archives an accessible item
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

		// Load process from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($procID);

		if (!is_a($item, ' \Entity\Process') || !$item->get('procID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive processes at all?
		if (false === $this->canArchiveProcess($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this process.
		if (false === $this->processIsArchivable($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('processes'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('1'),
			$db->qn('archiveDate') . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('archived_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('procID') . ' = ' . $db->q($procID));

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

		return $procID;
	}

	public function restoreProcess(int $procID)	// This is currently the opposite of archiveProcess - it restored an archived item
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

		// Load process from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($procID);

		if (!is_a($item, ' \Entity\Process') || !$item->get('procID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover processes at all?
		if (false === $this->canRestoreProcess($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this process.
		if (false === $this->processIsRestorable($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('processes'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('0'),
			$db->qn('archiveDate') . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('archived_by') . ' = NULL'
		])
		->where($db->qn('procID') . ' = ' . $db->q($procID));

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

		return $procID;
	}

	public function deleteProcess(int $procID)	// This is currently the opposite of recoverProcess - it deletes an accessible item
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

		// Load process from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($procID);

		if (!is_a($item, ' \Entity\Process') || !$item->get('procID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Is this user allowed to delete processes at all?
		if (false === $this->canDeleteProcess($procID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this process.
		if (false === $this->processIsDeletable($procID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Build query.
		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

		$query = $db->getQuery(true)
		->update($db->qn('processes'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('1'),
			$db->qn('trashDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('trashed_by') . ' = ' . $db->q((int) $user->get('userID')),
			$db->qn('deleted')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('deleted_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('procID') . ' = ' . $db->q($procID));

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
			->setQuery('ALTER TABLE `processes` AUTO_INCREMENT = 1')
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

		return $procID;
	}

	public function recoverProcess(int $procID)	// This is currently the opposite of deleteProcess - it recovers a deleted item
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

		// Load process from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$item = $this->getItem($procID);

		if (!is_a($item, ' \Entity\Process') || !$item->get('procID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover processes at all?
		if (false === $this->canRestoreProcess($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this process.
		if (false === $this->processIsRestorable($procID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('processes'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('0'),
			$db->qn('trashDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('trashed_by') . ' = NULL',
			$db->qn('deleted')    . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('deleted_by') . ' = NULL'
		])
		->where($db->qn('procID') . ' = ' . $db->q($procID));

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

		return $procID;
	}

	private function updateCatalog__BAK(int $procID, $errorCatalog)
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

		$filter   = new InputFilter;
		$formData = $errorCatalog['form'] ?? [];

		// Existence check.
		if (!$this->existsProcess($procID))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such process: %s', $this->language), $formData['name'])
			]);

			return false;
		}

		// Prepare formData to be stored into the database.
		if (array_key_exists('errors', $formData) && is_array($formData['errors']))
		{
			// Define clean-up callback function.
			$myFunction = function (string $str) use (&$filter): string
			{
				return $filter->clean(
					$filter->clean(
						str_ireplace('"', "'", $str),
						'STRING'
					),
					'TRIM'
				);
			};

			$formData['errors'] = array_map(function ($item) use (&$filter, &$myFunction, &$user)
			{
				return array_map($myFunction, $item);

			}, $formData['errors']);

			// Since new catalog items may be prepended instead of appended, ordering of indices may be mixed.
			// Force ascending order of error IDs prior processing.
			ksort($formData['errors']);
		}
		else
		{
			$formData['errors'] = [];
		}

		$userID = $user->get('userID');

		// Validate session userID equals current form editor's userID
		if ((int) $userID !== ArrayHelper::getValue($formData, 'user', 0, 'INT'))
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

		// Get reference to errors the user has submitted.
		$errors = ArrayHelper::getValue($formData, 'errors', [], 'ARRAY');

		/*   1.1 FETCH existing catalog items from database first  */

		$errIDs = [];

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('errID'))
		->from($db->qn('process_error'))
		->where($db->qn('procID') . ' = ' . $procID);

		// Execute query.
		try
		{
			$errIDs = (array) $db->setQuery($query)->loadColumn();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// No previous errors found. No new errors to store. Abort and return!
		if (!count($errIDs) && !count($errors))
		{
			return true;
		}

		// Separate deleted errors from modified errors.
		$delete = array_diff($errIDs, array_keys($errors));

		/*   1.2 DELETE catalog items that have been removed by the user  */

		// Build query.
		$query
		->clear()
		->delete($db->qn('errors'))
		->where($db->qn('errID') . ' IN (' . implode(',', $delete) . ')');

		// Execute query.
		try
		{
			if (count($delete))
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

		// Free memory.
		unset($delete);

		/*   1.3 DELETE catalog items belonging to this process, that have been modified (limit to lngID)  */

		// Build query.
		$query
		->clear()
		->delete($db->qn('errors'))
		->where($db->qn('lngID') . ' = ' . ArrayHelper::getValue($formData, 'lngID', 0, 'INT'))
		->where($db->qn('errID') . ' IN (' . implode(',', $errIDs) . ')');

		// Execute query.
		try
		{
			if (count($errIDs))
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

		// Previous errors found and deleted. No new errors to store. Return!
		if (!count($errIDs) && !count($errors))
		{
			return true;
		}

		/*   1.4 INSERT left catalog items  */

		// Build query.
		$rowData = new Registry($formData);

		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$now = $now->format('Y-m-d H:i:s');

		// Prepare additional row data.
		$createData = ['blocked' => '0', 'created'  => $now, 'created_by' => $userID, 'modified' => FTKRULE_NULLDATE, 'modified_by' => '0'];
		$modifyData = ['modified' => $now, 'modified_by' => $userID];
//		$tmpItems   = [];

		// Init required variables.
		$errors = (array) $rowData->get('errors', []);
		$langs  = $this->getInstance('languages')->getList(['onlyTags' => true]);

		/*   Store database entries.  */
		$tuples = [];

		// Add/update received catalog entries.
		try
		{
			$query = $db->getQuery(true);

			foreach ($errors as $errID => $error)
			{
				$exists = $this->existsErrorDefinition($errID, $rowData->get('lngID'));

				// INSERT data.
				// This should in fact never happen, because all data is deleted before (see line 724 ff.)
				if (!$exists)
				{
					// Build query.
					$query
					->clear()
					->insert($db->qn('errors'));

					array_walk($langs, function($language, $tag) use(&$procID, &$errID, &$error, &$rowData, &$createData, &$db, &$query, &$tuples, &$user)
					{
						// Load language object into Registry for less error-prone data access while further processing.
						$language = new Registry($language);

						// Store error using current app language first.
						if ($language->get('lngID') == $rowData->get('lngID'))
						{
							$description = trim($error->description);
							$description = (strlen($description) > 0 ? $db->q($description) : 'NULL');

							$query
							->clear()
							->insert($db->qn('errors'))
							->columns(
								$db->qn([
									'errID',
									'lngID',
									'name',
									'description',
									'blocked',
									'created',
									'created_by'
								])
							)
							->values(implode(',', [
								(int) $errID,
								(int) $language->get('lngID'),
								$db->q(trim($error->name)),
								$description,
								$db->q($createData['blocked']),
								$db->q($createData['created']),
								$db->q($createData['created_by'])
							]));

							// Execute query.
							$db
							->setQuery($query)
							->execute();

							// Collect this entry for the map.
							$tuples[] = $procID . ',' . (int) $errID;
						}
						// Store error placeholders for all left languages.
						else
						{
							// ADD
							if (!$this->existsErrorDefinition($errID, $language->get('lngID')))
							{
								$query
								->clear()
								->insert($db->qn('errors'))
								->columns(
									$db->qn([
										'errID',
										'lngID',
										'name',
										'blocked',
										'created',
										'created_by'
									])
								)
								->values(implode(',', [
									(int) $errID,
									(int) $language->get('lngID'),
									$db->q(Text::translate('COM_FTK_UNTRANSLATED_TEXT', $this->language)),
									$db->q($createData['blocked']),
									$db->q($createData['created']),
									$db->q($createData['created_by'])
								]));

								// Execute query.
								$db
								->setQuery($query)
								->execute();
							}
						}
					});
				}
				// UPDATE existing item(s).
				else
				{
					// Build query.
					$query
					->clear()
					->update($db->qn('errors'))
					->set([
						$db->qn('name')        . ' = ' . $db->q(trim($error->name)),
						$db->qn('description') . ' = ' . $db->q(trim($error->description)),
						$db->qn('modified')    . ' = ' . $db->q($modifyData['modified']),
						$db->qn('modified_by') . ' = ' . $db->q($modifyData['modified_by'])
					])
					->where($db->qn('errID') . ' = ' . (int) $errID)
					->where($db->qn('lngID') . ' = ' . (int) $rowData->get('lngID'));

					// Execute query.
					$db
					->setQuery($query)
					->execute();

					// Collect this entry for the map.
					$tuples[] = $procID . ',' . (int) $errID;
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

		/*   1.5 MAP catalog items to currently edited process */

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('process_error'))
		->columns(
			$db->qn([
				'procID',
				'errID'
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

		return $procID;
	}
	public function updateCatalog(int $procID, $POST)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// @debug
		$debug  = $user->isProgrammer();
		$debug  = false;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$filter   = new InputFilter;

		$formData = ArrayHelper::getValue($POST, 'form', [], 'ARRAY');

		// Validate session userID equals current form editor's userID
		if ((int) $userID !== ArrayHelper::getValue($formData, 'user', 0, 'INT'))
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

		// Existence check.
		if (!$this->existsProcess($procID))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such process: %s', $this->language), $formData['name'])
			]);

			return false;
		}

		$langs  = $this->getInstance('languages')->getList(['filter' => ListModel::FILTER_ACTIVE, 'onlyTags' => true]);

		// Get reference to errors the user has submitted.
		$errors = ArrayHelper::getValue($formData, 'errors', [], 'ARRAY');
		// Remove 'errors' from $formData as it'll be gonna passed to the error model with just a single error per request.
		unset($formData['errors']);

		// Prepare submitted errors to be stored into the database.
		if (count($errors))
		{
			// Define clean-up callback function.
			$myFunction = function (string $str) use (&$filter): string
			{
				return $filter->clean(
					$filter->clean(
						str_ireplace('"', "'", $str),
						'STRING'
					),
					'TRIM'
				);
			};

			$errors = array_map(function ($item) use (&$filter, &$myFunction, &$user)
			{
				return array_map($myFunction, $item);

			}, $errors);

			// Since new catalog items may be prepended instead of appended, ordering of indices may be mixed.
			// Force ascending order of error IDs prior processing.
			ksort($errors);
		}
		// Init vars.
		$rowData = new Registry($formData);

		// Get Error-Model.
		$errorModel = $this->getInstance('error', ['language' => $this->language]);

		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// Define database table name.
			$tableName = 'errors__NEW';	// FIXME - change to 'errors'

			/*   1.1 FETCH existing catalog items from database first  */

			// Build sub-query first.
			$sub   = $db->getQuery(true)
			->from($db->qn('process_error'))
			->select($db->qn('errID'))
			->where($db->qn('procID') . ' = ' . $procID);

			$query = $db->getQuery(true)
			->from($db->qn($tableName, 'e'))
			->leftJoin($db->qn('error_meta', 'em') . ' ON ' . $db->qn('e.errID') . ' = ' . $db->qn('em.errID'))
			->select(
				$db->qn([
					'e.errID',
					'e.number',
					'e.wincarat'
				])
			)
			->select(
				$db->qn([
					'em.name',
					'em.description'
				])
			)
			->where($db->qn('e.errID')  . ' IN (' . $sub . ')')
			->where($db->qn('em.lngID') . ' = ' . $rowData->get('lngID'));

			$oldErrors = $db->setQuery($query)->loadAssocList('errID');
			$errIDs    = array_keys($oldErrors);

			// No previous errors found. No new errors to store. Abort + Return!
			if (!count($errIDs) && !count($errors))
			{
				return true;
			}


			/*   1.2 DELETE catalog items the user has deleted */

			// Separate deleted errors from modified errors.
			$deleteIDs = array_diff($errIDs, array_keys($errors));	// Compare old error IDs with received error IDs and detect missing IDs. These will be deleted.
			$delete    = array_filter($oldErrors, function ($oldError, $eid) use (&$deleteIDs) { return in_array($eid, $deleteIDs); }, ARRAY_FILTER_USE_BOTH);

			if (count($delete))
			{
				foreach ($delete as $eid => $oldError)
				{
					// Pass job over to error model.
					$deleted = $errorModel->deleteError($eid);

					// Generate deletion failure notification for the user.
					if ($deleted != $eid)
					{
						$tmp      = ArrayHelper::getValue($delete, $eid);
						$number   = ArrayHelper::getValue($tmp, 'number',   null, 'CMD');
						$wincarat = ArrayHelper::getValue($tmp, 'wincarat', null, 'CMD');
						$msg      = sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_DELETION_OF_X_HAS_FAILED_TEXT', $this->language),
							sprintf('%s %s/%s', Text::translate('COM_FTK_LABEL_ERROR_TEXT', $this->language), $number, $wincarat ?? '<span class="text-muted">xxxx</span>')
						);

						Messager::setMessage([
							'type' => 'error',
							'text' => $msg
						]);
					}

					// Drop error from both the deletion list and the submitted errors, because it is processed.
					unset($delete[$eid]);
					unset($errors[$eid]);
				}
			}

			// Free memory.
			unset($deleteIDs);


			/*   1.3 UPDATE catalog items belonging to this process, that have been modified (limit to lngID)  */

			array_walk($oldErrors, function ($oldError, $eid) use (&$db, &$debug, &$errorModel, &$errors, &$filter, &$formData/*, &$langs*/, &$query, &$rowData, &$tableName, &$user)
			{
				$error = ArrayHelper::getValue($errors, $eid);

				if (!$error)
				{
					// Continue.
					return false;
				}

				// Drop primary key to detect only content changes.
				$tmp = $oldError;

				if (array_key_exists('errID', $tmp))
				{
					unset($tmp['errID']);
				}

				// Detect changed data.
				$changes = array_diff_assoc($error, $tmp);

				// Changes found. Call error model.
				if (count($changes))
				{
					$data = array_merge($formData, array_merge($oldError, $error));

					// Pass job over to error model.
					$updated = $errorModel->updateError(['form' => $data]);
				}

				// Drop from received errors, because it's and must therefore no longer be available.
				unset($errors[$eid]);
			});

			// Free memory.
			unset($oldErrors);


			/*   1.4 INSERT remaining catalog items  */

			// Init procID <==> errID map.
			$tuples = [];

			if (count($errors))
			{
				// INSERT new error into main table.
				foreach ($errors as $eid => $error)
				{
					$data = array_merge($formData, ['errID' => $eid], $error);

					// Pass job over to error model.
					// FIXME - get rid of all the CRUD-code after the next line + ensure the data object contains all expected information.
					$added = $errorModel->addError(['form' => $data]);

					if ($added)
					{
						// Collect this entry for the map.
						$tuples[] = $procID . ',' . (int) $eid;
					}
				}
			}


			/*   1.5 MAP catalog items to currently edited process */

			if (count($tuples))
			{
				// Build query.
				$query
				->clear()
				->insert($db->qn('process_error'))
				->columns(
					$db->qn([
						'procID',
						'errID'
					])
				)
				->values($tuples);

				// Execute query.
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

		return $procID;
	}


	protected function existsErrorDefinition(int $errID, int $lngID) : bool
	{

		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		// Function parameter check.
		if (empty($errID) && empty($lngID))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function requires both error ID and language ID.');
		}

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('errID'))
		->from($db->qn('errors'))
		->where($db->qn('errID') . ' = ' . $errID)
		->where($db->qn('lngID') . ' = ' . $lngID);

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


	protected function existsProcess__BAK($procID = null, string $processName = null, $lang = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		// Function parameter check.
		if (is_null($procID) && is_null($processName))
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
		->select($db->qn('procID'))
		->from($db->qn('process_meta'));

		// This function is not called PRIOR CREATION (no artID available) and PRIOR DELETION (artID is available).
		// Hence, table 'processes' must not be the table to look up, because process name is stored in 'process_meta'
		// and 'procID' must not be the only column to check for!
		switch (true)
		{
			// Should find existing process identified by ID + Name.
			case (!empty($procID) && !empty($processName)) :
				$query
				->where($db->qn('procID') . ' = ' . (int) $procID)
				->where('LOWER(' . $db->qn('name') . ') = LOWER( TRIM(' . $db->q(trim($processName)) . ') )');
			break;

			// Should find existing process identified by ID.
			case (!empty($procID) && (int) $procID > 0) :
				$query
				->where($db->qn('procID') . ' = ' . (int) $procID);
			break;

			// Should find existing item identified by Name.
			case (!empty($processName) && trim($processName) !== '') :
				$query
				->where('LOWER(' . $db->qn('name') . ') LIKE SOUNDEX( TRIM(' . $db->q(trim($processName)) . ') )');
			break;
		}

		$query
		->where($db->qn('language') . ' = ' . $db->q($lang ?? $this->language));

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
	protected function existsProcess($procID = null, string $processAbbreviation = null, $lang = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		// Function parameter check.
		if (is_null($procID) && is_null($processAbbreviation))
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
		try
		{
			$query = $db->getQuery(true)
			->select($db->qn('procID'))
			->from($db->qn('processes'));

			// This function is not called PRIOR CREATION (no artID available) and PRIOR DELETION (artID is available).
			// Hence, table 'processes' must not be the table to look up, because process name is stored in 'process_meta'
			// and 'procID' must not be the only column to check for!
			switch (true)
			{
				// Should find existing item identified by ID + Name.
				case (!empty($procID) && !empty($processAbbreviation)) :
					$query
					->where($db->qn('procID') . ' = ' . (int) $procID)
					->where('LOWER(' . $db->qn('abbreviation') . ') = LOWER( TRIM(' . $db->q(trim($processAbbreviation)) . ') )');
				break;

				// Should find existing item identified by ID.
				case (!empty($procID) && (int) $procID > 0) :
					$query
					->where($db->qn('procID') . ' = ' . (int) $procID);
				break;

				// Should find existing item identified by Name.
				case (!empty($processAbbreviation) && trim($processAbbreviation) !== '') :
					$query
					->where('LOWER(' . $db->qn('abbreviation') . ') = LOWER( TRIM(' . $db->q(trim($processAbbreviation)) . ') )');
				break;
			}

			// Execute query.
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


	protected function canDeleteProcess(int $procID) : bool
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

	protected function processIsDeletable(int $procID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if ($this->processHasDependencies($procID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_DELETION_DEPENDENCIES_TEXT', $this->language)
			]);

			return false;
		}

		return true;
	}

	protected function processHasDependencies(int $procID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		/*$from1 = $db->getQuery(true)
		->setQuery('
			SELECT `x`.`count` AS `articles`
			FROM (
				SELECT COUNT(`artID`) AS `count`
				FROM `articles`
				WHERE `processes` LIKE "%' . (int) $procID . '%"
			) AS `x`'
		);*/

		/*$from2 = $db->getQuery(true)
		->setQuery('
			SELECT `y`.`count` AS `processes`
			FROM (
				SELECT COUNT(`orgID`) AS `count`
				FROM `organisations`
				WHERE `processes` LIKE "%' . (int) $procID . '%"
			) AS `y`'
		);*/

		$from3 = $db->getQuery(true)
		->setQuery('
			SELECT `z`.`count` AS `parts`
			FROM (
				SELECT COUNT(`procID`) AS `count`
				FROM `tracking`
				WHERE `procID` = ' . $procID . '
			) AS `z`'
		);

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn(['o.parts']))
		->from('(' . $from3 . ') AS `o`');

		// Execute query.
		try
		{
			$row = $db->setQuery($query)->loadObject();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return true;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $row->parts > 0;
	}


	protected function hasProcessMeta(int $procID, string $lang, bool $isNotNull = false, $returnData = false)
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
		$table = 'process_meta';
		$query = $db->getQuery(true)
		->from($db->qn($table))
		->where($db->qn('procID')   . ' = ' . $procID)
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
			->select('COUNT(' . $db->qn('procID') . ') AS ' . $db->qn('count'));
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

	protected function storeProcessMeta(int $procID, $processMeta) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!count(array_filter((array) $processMeta)))
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

		if (!is_object($processMeta))
		{
			$processMeta = (object) $processMeta;
		}

		$hasMeta = $this->hasProcessMeta((int) $processMeta->pid, $processMeta->lng);

		// Build query.
		$rowData = new Registry($processMeta);

		$procDescription = filter_var($rowData->get('description'),  FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		$procDescription = (is_null($procDescription) || trim($procDescription)  == '') ? "NULL" : $db->q(trim($procDescription));

		// Build query.
		if (!$hasMeta)
		{
			$query = $db->getQuery(true)
			->insert($db->qn('process_meta'))
			->columns(
				$db->qn([
					'procID',
					'lngID',
					'name',
					'description',
					'language'
				])
			)
			->values(implode(',', [
				(int)  filter_var($rowData->get('pid'),   FILTER_VALIDATE_INT),
				(int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT),
				$db->q(filter_var($rowData->get('name'),  FILTER_SANITIZE_STRING)),
				$procDescription,
				$db->q(filter_var($rowData->get('lng'),   FILTER_SANITIZE_STRING))
			]));
		}
		else
		{
			$query = $db->getQuery(true)
			->update($db->qn('process_meta'))
			->set([
				$db->qn('name')        . ' = ' . $db->q(filter_var($rowData->get('name', null), FILTER_SANITIZE_STRING)),
				$db->qn('description') . ' = ' . $procDescription,
				$db->qn('language')    . ' = ' . $db->q(filter_var($rowData->get('lng'),   FILTER_SANITIZE_STRING))
			])
			->where($db->qn('procID')  . ' = ' . (int)  filter_var($rowData->get('pid'),   FILTER_VALIDATE_INT))
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

	protected function deleteProcessMeta(int $procID) : bool
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
		->delete($db->qn('process_meta'))
		->where($db->qn('procID') . ' = ' . $procID);

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

		return true;
	}






	protected function storeProcessOwner(int $procID, array $processOwner = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		/*if (!\is_object($processOwner))
		{
			$processOwner = (object) $processOwner;
		}*/

		// Prepare arg $processOwner for further processing
		switch (true)
		{
			case is_string($processOwner) :
				$processOwner = (array) json_decode($processOwner, null, 512, JSON_THROW_ON_ERROR);
			break;

			case is_array($processOwner) :
				// do nothing - we want it to be an array as we're going to execute array functions
			break;

			case is_object($processOwner) :
				$tmp = [];

				array_filter((array) $processOwner, function($process, $procID) use(&$tmp)
				{
					$tmp[$procID] = json_decode($process, null, 512, JSON_THROW_ON_ERROR);

					return true;

				}, ARRAY_FILTER_USE_BOTH);

				$processOwner = $tmp;

				unset($tmp);
			break;
		}

		$tmp = [];

		array_filter($processOwner, function($process, $procID) use(&$tmp)
		{
			$tmp[$procID] = $process;

		}, ARRAY_FILTER_USE_BOTH);

		$processOwner = $tmp;

		// Free memory.
		unset($tmp);

		// Store organisation processes.
		$tuples = [];

		// Prepare artID<-->procID tuples.
		foreach($processOwner as $orgID)
		{
			$tuples[] = $procID . ',' . (int) $orgID;
		}

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('process_organisation'))
		->columns(
			$db->qn([
				'procID',
				'orgID'
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

	protected function deleteProcessOwner(int $procID, array $preserveIDs = []) : bool
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
		$preserveIDs = array_map('intval', $preserveIDs);

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('process_organisation'))
		->where($db->qn('procID') . ' = ' . $procID);

		// Consider processes to preserve.
		if (count($preserveIDs))
		{
			$query->where($db->qn('procID') . ' NOT IN(' . implode(',', $preserveIDs) . ')');
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

			return false;
		}

		// return $orgID;
		return true;
	}


	protected function storeProcessParameters(int $procID, $langID, array $procParams = [])
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

//		$userID = $user->get('userID');

		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$now = $now->format('Y-m-d H:i:s');

		// Iterate over the process parameters to be stored and add potentially new technical parameter(s).
		$model = $this->getInstance('techparam', ['language' => $this->language]);
		$langs = $this->getInstance('languages', ['language' => $this->language])->getList(['onlyTags' => true]);
		$lng   = $this->language;
		$map   = [];

		$isError = false;

		array_walk($procParams, function($paramValue, $id) use(&$langs, &$lng, &$langID, &$map, &$model)
		{
			$technicalParameters = (array) $model->existsTechnicalParameter($id, $langID);	// return value will be an object or 0 (which is interpreted as false)

			$isNew = !count($technicalParameters);

			// Second dupe check for language id and param value
			if ($isNew)
			{
				$technicalParameters = (array) $model->existsTechnicalParameter(null, $langID, $paramValue);	// return value will be an object or 0 (which is interpreted as false)

				$isNew = ($isNew && !count($technicalParameters));

				// Param is not new but must be preserved. Track its ID.
				if (!$isNew)
				{
					$techParam = new Registry(current($technicalParameters));

					$map[] = $techParam->get('paramID');
				}
			}

			// Nothing found. Store as new param.
			if ($isNew)
			{
				$paramID = $model->addTechnicalParameter(null, $langID, $paramValue, $lng);

				// Report parameter creation error.
				if (!is_int($paramID))
				{
					Messager::setMessage([
						'type' => 'danger',
						// TODO - translate
						'text' => sprintf('New technical parameter *%s* could not be stored.', $paramValue)
					]);

					return;
				}

				// Fetch stored parameter to proof it has been stored and to use it to store language placeholders.
				$technicalParameters = $model->existsTechnicalParameter($paramID, $langID, $paramValue);

				// Add placeholders for other languages.
				foreach ($technicalParameters as $techParam)
				{
					$techParam = new Registry($techParam);

					// Collect the parameter ID
					$map[] = $techParam->get('paramID');

					// Store placeholders for other languages.
					foreach ($langs as $tag => $language)
					{
						// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
						$language = new Registry($language);

						if ($language->get('lngID') == $langID)
						{
							continue;
						}

						$paramID = $model->addTechnicalParameter(
							$techParam->get('paramID'),
							$language->get('lngID'),
							Text::translate('COM_FTK_UNTRANSLATED_TEXT', $language->get('tag')),
							$language->get('tag')
						);
					}
				}
			}
			// Existing param found.
			else
			{
				foreach ($technicalParameters as $techParam)
				{
					$techParam = new Registry($techParam);

					if ($techParam->get('name') != trim($paramValue))	// If old value equals new value, continue.
					{
						if ($techParam->get('name') !== trim($paramValue))
						{
							$updated = $model->updateTechnicalParameter($id, $langID, $paramValue, $lng);
						}

						// Collect ID of technical parameter to be updated.
						$map[] = $techParam->get('paramID');
					}
					else
					{
						// Collect ID of technical parameter to be updated.
						$map[] = $techParam->get('paramID');
					}
				}
			}
		});

		$map = array_unique($map);

		// Clean up first.
		$cleaned = $this->deleteProcessParameters($procID);

		if (false === $cleaned)
		{
			// Error is set by called function.
			return false;
		}

		// Store process parameters.
		$tuples = [];

		// Prepare procID<-->paramID tuples.
		foreach ($map as $paramID)
		{
			$tuples[] = $procID . ',' . (int) $paramID;
		}

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('process_techparameter'))
		->columns(
			$db->qn([
				'procID',
				'paramID'
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

	protected function deleteProcessParameters(int $procID) : bool
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
		->delete($db->qn('process_techparameter'))
		->where($db->qn('procID') . ' = ' . $procID);

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
}
