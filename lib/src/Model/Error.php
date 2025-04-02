<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Messager;
use Nematrack\Model\Item as ItemModel;
use Nematrack\Text;
use function array_filter;
use function is_null;

/**
 * Class description
 */
class Error extends ItemModel
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

    /**
     * Returns for a given error whether it has already been tracked.
     *
     * @param   int|null    $errID
     * @param   string|null $number
     * @return  string|null
     * @throws  Exception
     */
	public function isTracked(int $errID = null, string $number = null) : ?string	// ADDED on 2023-05-25
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*//-> BEGIN   Debug who's calling this function.
		$trace  = debug_backtrace();
		$called = current($trace);
		$caller = next($trace);

		$this->logger->log(
			'info',
			sprintf('%s("%s") was called in %s::%s() in line %d ( %s )', __METHOD__, $trackingCode, $caller['class'], $caller['function'], $caller['line'], $caller['file']),
			$called['args']
		);
		//-> END   Debug who's calling this function*/

		// Either the error ID or the error number is required.
		// Return if none is set.
		if (empty($errID) && empty($number))
		{
			throw new Exception(Text::translate('An error ID or an error number is required for this request.', $this->language));
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init return value.
		$trackedIDs = [];

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->from($db->qn('tracking'))
//			->group($db->qn('paramValue'))
			->select(sprintf('DISTINCT %s', $db->qn('paramValue')))
//			->select('COUNT(' . $db->qn('procID') . ') AS ' . $db->qn('trackings'))
			->where($db->qn('paramID') . ' = ' . Techparams::STATIC_TECHPARAM_ERROR);

			switch (true)
			{
				case (!is_null($errID) && !is_null($number)) :
				case (!is_null($errID) &&  is_null($number)) :
					$query->where($db->qn('paramValue') . ' = ' . $errID);
				break;

				case ( is_null($errID) && !is_null($number)) :
					$query->where($db->qn('paramValue') . ' = ' . intval($number));
				break;
			}

			// Execute query.
			$trackedIDs = $db->setQuery($query)->loadColumn();

		}
		catch (Exception $e) {}

		// Close connection.
		$this->closeDatabaseConnection();

		return (is_array($trackedIDs) && count($trackedIDs));
	}

	/***   END: API-Service(s)   ***/


	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $itemID
	 *
	 * @return  Entity\Error
	 */
	public function getItem(int $itemID) : Entity\Error
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('errors', ['language' => $this->language])->getList(
					[
						'errID' => $itemID
					]
				),
				$itemID
			);
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf('Nematrack\Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf('Nematrack\Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,   'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('errID')) {}

		return $row;
	}

	public function getErrorByNumber(string $number) : Entity\Error
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

		try
		{
			// Define database table name.
			$tableName = 'errors__NEW';	// FIXME - change to 'errors'

			// Build query.
			$query = $db->getQuery(true)
			->from($db->qn($tableName))
			->select($db->qn('errID'))
			->where('LOWER(' . $db->qn('number') . ') = LOWER(TRIM(' . $db->q(trim($number)) . '))');

			// Execute query.
			$id   = $db->setQuery($query)->loadResult();

			$item = $this->getItem((int) $id);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$item = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $item;
	}

	public function getErrorByWincarat(string $code) : Entity\Error
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

		try
		{
			// Define database table name.
			$tableName = 'errors__NEW';	// FIXME - change to 'errors'

			// Build query.
			$query = $db->getQuery(true)
			->from($db->qn($tableName))
			->select($db->qn('errID'))
			->where('LOWER(' . $db->qn('wincarat') . ') = LOWER(TRIM(' . $db->q(trim($code)) . '))');

			// Execute query.
			$id   = $db->setQuery($query)->loadResult();

			$item = $this->getItem((int) $id);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$item = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $item;
	}

	public function getErrorByName(string $name) : Entity\Error
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

		try
		{
			// Define database table name.
			$tableName = 'error_meta';

			// Build query.
			$query = $db->getQuery(true)
			->from($db->qn($tableName))
			->select($db->qn('errID'))
			->where('LOWER(' . $db->qn('name') . ') = LOWER(TRIM(' . $db->q(trim($name)) . '))');

			// Execute query.
			$id   = $db->setQuery($query)->loadResult();

			$item = $this->getItem((int) $id);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$item = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $item;
	}


	/**
	 * Generates for a given error ID an error number.
	 * This number can be publically visible, whereas the error ID shall not.
	 *
	 * @param   int  $errID  The ID to use
	 *
	 * @return  string  The generated error number
	 *
	 * @throws  InvalidArgumentException  When no proper error ID is provided
	 */
	public function createErrorNumber(int $errID) : string
	{
		if ($errID <= 0)
		{
			throw new InvalidArgumentException(
				Text::translate('Error number creation failed. No valid error ID was provided.')
			);
		}

		return str_pad($errID, 6, '0', STR_PAD_LEFT);
	}


	/**
	 * Stores a new item in the database.
	 *
	 * @param   array  $error  Array containing the POST and FILES data
	 *
	 * @return  int|false  The inserted row ID or false if data was not stored
	 *
	 * @throws  Exception  When the database columns count doesn't equal the form data values count.
	 */
	public function addError(array $POST) :? int
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

		// Get data filter.
		$filter   = new InputFilter;

		$formData = $POST['form']  ?? [];
		$fileData = $POST['files'] ?? [];

		// Get ref to vars of interest.
		$errID    = $filter->clean(ArrayHelper::getValue($formData, 'errID', 0), 'INT');
		// FIXME - enable this when the new errors table is active
//		$errID2   = $this->getInstance('errors')->getLastInsertID() + 1;	// Read the last error ID and increment it to get the next free ID. This must be equal to the ID submitted
		$number   = trim($filter->clean(ArrayHelper::getValue($formData, 'number'),   'CMD'));
		$number2  = $this->createErrorNumber($errID);	// This number is generated from $errID and must be equal to the number submitted
		$wincarat = trim($filter->clean(ArrayHelper::getValue($formData, 'wincarat'), 'CMD'));
		$name     = trim($filter->clean(ArrayHelper::getValue($formData, 'name'), 'STRING'));
		$lngID    = $filter->clean(ArrayHelper::getValue($formData, 'lngID'), 'INT');

		// Validate session userID equals current form editor's userID.
		if ((int) $userID !== $filter->clean(ArrayHelper::getValue($formData, 'user'),  'INT'))
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

		// Validate required fields are submitted.
		if (!$wincarat && !$name)
		{
			return null;
		}

		// Validate the error ID that was generated via JavaScript is proper (equal to $errID2).
		if (isset($errID) && isset($errID2) && $errID !== $errID2)
		{
			// Replace submitted error ID with calculated one.
			$errID = $errID2;
		}

		// Validate the error number that was generated via JavaScript is proper (equal to $number2).
		if (isset($number) && isset($number2) && $number !== $number2)
		{
			// Replace submitted error number with calculated one.
			$number = $number2;
		}

		// Dupecheck(s).

		// @test (maybe this can be used in a Test class?)
//		$item = $this->existsError(null, $number);						// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, null,    $wincarat);			// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, null,    null,      $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, $number, $wincarat);			// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, $number, null,      $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, null,    $wincarat, $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, $number, $wincarat, $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
		// end
		$item = $this->existsError(null, $number, $wincarat);			// $errID, $errorNumber, $wincaratCode, $errorName, $lang

		// C H E C K (s) :   Different item found with partially or fully identical parameter(s).
		if ($item && is_a($item, 'Nematrack\Entity\Error') && is_int($item->get('errID')))
		{
			$matches = [];	// Stack for duplicate parameters
			$msg     = [];	// Stack for messages

			switch (true)
			{
				// The error number must be unique.
				CASE (isset($number) && mb_strtolower($number) == mb_strtolower($item->get('number'))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_NUMBER_TEXT',  $this->language), $number);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_ERROR_NUMBER_TEXT', $this->language), $number);
				break;

				// The WinCarat-code + the error name/title number must be unique.
				CASE ((isset($wincarat) && mb_strtolower($wincarat) == mb_strtolower($item->get('wincarat'))) &&
					  (isset($name)     && mb_strtolower($name)     == mb_strtolower($item->get('name')))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_CODE_WINCARAT_TEXT', $this->language), $wincarat);
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_LABEL_TEXT',         $this->language), $name);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_WINCARAT_CODE_TEXT',      $this->language), $wincarat, $item->get('number'));
				break;

				// The WinCarat-code + the error name/title number shall be unique.
				CASE (isset($wincarat) && mb_strtolower($wincarat) == mb_strtolower($item->get('wincarat'))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_CODE_WINCARAT_TEXT', $this->language), $wincarat);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_WINCARAT_CODE_TEXT',      $this->language), $wincarat, $item->get('number'));
				break;

				/*// The error name/title itself can be dupe
				CASE (isset($name) && mb_strtolower($name) == mb_strtolower($item->get('name'))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_LABEL_TEXT', $this->language), $name);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_ERROR_NAME_TEXT', $this->language), $name, $wincarat, $item->get('number'));
				break;*/

				default:
//					$msg[] = Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ERROR_TEXT', $this->language);
			}

			// If item is deleted, add hint.
			if ($item->get('deleted_by') && is_a($item->get('deleted'), 'DateTime'))
			{
				$dateDeleted = $item->get('deleted');

				$msg[] = sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_WAS_DELETED_ON_DATE_X_TEXT', $this->language), $item->get('deleted')->format('Y-m-d'));
				$msg[] = Text::translate('COM_FTK_HINT_REACTIVATION_OF_DELETED_ITEM_TEXT', $this->language);
			}

			if (count($msg))
			{
				Messager::setMessage([
					'type' => 'info',
					'text' => implode('<br>', $msg)
				]);

				return false;
			}
		}

		$rowData = new Registry($formData);

		// Define database table name.
		$tableName = 'errors__NEW';	// FIXME - change to 'errors'

		// Get database query object.
		$query = $db->getQuery(true);

		// Build query.
		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// 1. INSERT into main table

			// Build query.
			$query
			->insert($db->qn($tableName))
			->columns(
				$db->qn([
					'errID',
					'number',
					'wincarat',
					'created',
					'created_by'
				])
			);

			$wicaCode = 'NULL';

			if ($wincarat)
			{
				// FIXME - convert to Symfony validation rule
				$wicaCode = !preg_match('/' . FTKREGEX_ERROR_WINCARAT_CODE . '/', $wincarat) ? 'NULL' : $db->q(mb_strtoupper($wincarat));
			}

			$query
			->values(implode(',', [
				(int) $errID,
				$db->q($number),
				$wicaCode,
				$db->q($now->format('Y-m-d H:i:s')),
				(int) $user->get('userID')
			]));

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();
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
		if (!$rowData->get('errID'))
		{
			$rowData->set('errID', $insertID);
		}

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		if (!$rowData->get('lngID'))
		{
			$rowData->set('lngID', (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1'));    // assign current app lang or fall back to DE
		}

		// Store organisation meta information.
		$metaStored = $this->storeErrorMeta($rowData->get('errID'), $rowData->toArray());

		// Get all registered app languages and drop currently active language
		$langs = array_filter($this->getInstance('languages', ['language' => $this->language])->getList(['filter' => Lizt::FILTER_ACTIVE, 'onlyTags' => true]), function($language)
		{
			// Load language object into {@see \Joomla\Registry\Registry} for less error-prone data access while further processing.
			$language = new Registry($language);

			return $language->get('tag') !== $this->language;
		});

		// Store placholders for all other languages that are not current language.
		$isError = false;

		array_walk($langs, function($language, $tag) use(&$formData, &$isError, &$rowData)	// in addError()
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

			$formData['lngID']       = $language->get('lngID');
			$formData['lng']         = $language->get('tag');
			$formData['name']        = Text::translate('COM_FTK_UNTRANSLATED_TEXT', $tag);
			$formData['description'] = null;

			// Store organisation meta information placeholder.
			$isError = !$this->storeErrorMeta($rowData->get('errID'), $formData);
		});

		return (($insertID > 0 && $metaStored && !$isError) ? $rowData->get('errID') : false);
	}

	/**
	 * Updates an existing item in the database.
	 *
	 * @param   array  $error  Array containing the POST and FILES data
	 *
	 * @return  int|false  The inserted row ID or false if data was not stored
	 *
	 * @throws  Exception  When the database columns count doesn't equal the form data values count.
	 */
	public function updateError(array $POST) :? int
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

		// Get data filter.
		$filter   = new InputFilter;

		$formData = $POST['form']  ?? [];
		$fileData = $POST['files'] ?? [];

		// Get ref to vars of interest.
		$errID    = $filter->clean(ArrayHelper::getValue($formData, 'errID'), 'INT');
		$number   = trim($filter->clean(ArrayHelper::getValue($formData, 'number'), 'CMD'));
		$wincarat = trim($filter->clean(ArrayHelper::getValue($formData, 'wincarat'), 'CMD'));
		$name     = trim($filter->clean(ArrayHelper::getValue($formData, 'name'), 'STRING'));
		$lngID    = $filter->clean(ArrayHelper::getValue($formData, 'lngID'), 'INT');

		// Validate session userID equals current form editor's userID
		if ((int) $userID !== $filter->clean(ArrayHelper::getValue($formData, 'user'),  'INT'))
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

		// @test (maybe this can be used in a Test class?)
//		$item = $this->existsError(null, $number);						// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, null,    $wincarat);			// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, null,    null,      $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, $number, $wincarat);			// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, $number, null,      $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, null,    $wincarat, $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
//		$item = $this->existsError(null, $number, $wincarat, $name);	// $errID, $errorNumber, $wincaratCode, $errorName, $lang
		// end
		$item = $this->existsError($errID);								// $errID, $errorNumber, $wincaratCode, $errorName, $lang

		// The requested item was not found.
		if (!$item || !is_a($item, 'Nematrack\Entity\Error') || !is_int($item->get('errID')) || ($item->get('errID') != $errID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_PROCESS_ERROR_X_COULD_NOT_BE_FOUND_TEXT', $this->language), null)
			]);

			return false;
		}

		// Dupecheck(s).

		// C H E C K (s) :   The requested item was found. Now we check for parameter(s) that must be unique to be unique.
		if ($item && is_a($item, 'Nematrack\Entity\Error') && is_int($item->get('errID'))/* && ($item->get('errID') == $errID)*/)
		{
			$matches = [];	// Stack for duplicate parameters
			$msg     = [];	// Stack for messages

			switch (true)
			{
				// An existing error number must not be changed.
				// If the error number is changed discard the change and return.
				CASE (mb_strtolower($number) != mb_strtolower($item->get('number'))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_NUMBER_TEXT', $this->language),  $number);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_ERROR_NUMBER_TEXT', $this->language), $number);
				break;

				/*// The WinCarat-code + the error name/title number must be unique.
				CASE (isset($name) && ((mb_strtolower($wincarat) != mb_strtolower($item->get('wincarat'))) && (mb_strtolower($name) != mb_strtolower($item->get('name'))))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_CODE_WINCARAT_TEXT', $this->language), $wincarat);
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_LABEL_TEXT',         $this->language), $name);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_WINCARAT_CODE_TEXT', $this->language), $wincarat, $item->get('number'));
				break;*/

				// The WinCarat-code shall be unique.
				// If the WinCarat code has been changed, check if the new code already exists and discard the change and return.
				CASE (mb_strtolower($wincarat) != mb_strtolower($item->get('wincarat'))) :
					$tmpError  = $this->getErrorByWincarat($wincarat);

					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_CODE_WINCARAT_TEXT', $this->language), $wincarat);

					if ($tmpError && is_a($tmpError, 'Nematrack\Entity\Error') && is_int($tmpError->get('errID')))
					{
						$msg[] = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_WINCARAT_CODE_TEXT', $this->language), $wincarat, $tmpError->get('number'));
					}
				break;

				/*// The error name/title itself can be dupe
				CASE (isset($name) && mb_strtolower($name) == mb_strtolower($item->get('name'))) :
					$matches[] = sprintf('<strong>%s</strong>: %s', Text::translate('COM_FTK_LABEL_LABEL_TEXT', $this->language), $name);
					$msg[]     = sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_DUPLICATE_ERROR_NAME_TEXT', $this->language), $name, $wincarat, $item->get('number'));
				break;*/

				default:
//					$msg[] = Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ERROR_TEXT', $this->language);
			}

			// If item is deleted, add hint.
			if ($item->get('deleted_by') && is_a($item->get('deleted'), 'DateTime'))
			{
				$dateDeleted = $item->get('deleted');

				$msg[] = sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_ERROR_WAS_DELETED_ON_DATE_X_TEXT', $this->language), $item->get('deleted')->format('Y-m-d'));
				$msg[] = Text::translate('COM_FTK_HINT_REACTIVATION_OF_DELETED_ITEM_TEXT', $this->language);
			}

			if (count($msg))
			{
				Messager::setMessage([
					'type' => 'info',
					'text' => implode('<br>', $msg)
				]);

				return false;
			}
		}

		$rowData = new Registry($formData);

		// Define database table name.
		$tableName = 'errors__NEW';	// FIXME - change to 'errors'

		// Get database query object.
		$query = $db->getQuery(true);

		// Build query.
		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// 1. Update main table

			// Build query.
			$query
			->update($db->qn($tableName))
			->where($db->qn('errID')   . ' = ' . (int) $errID)
			->set([
				$db->qn('modified')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('modified_by') . ' = ' . (int) $user->get('userID')
			]);

			if ($wincarat)
			{
				// FIXME - convert to Symfony validation rule
				$wicaCode = preg_match('/' . FTKREGEX_ERROR_WINCARAT_CODE . '/', $wincarat) ? mb_strtoupper($wincarat) : null;

				// WinCarat code has been changed. Add to UPDATE-query.
				if (!is_null($wicaCode))
				{
					// Build query.
					$query
					->set($db->qn('wincarat') . ' = ' . $db->q($wicaCode));
				}
			}

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
		if (!$rowData->get('lngID'))
		{
			$rowData->set('lngID', (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1'));    // assign current app lang or fall back to DE
		}

		// Store organisation meta information.
		$metaStored = $this->storeErrorMeta($rowData->get('errID'), $rowData->toArray());

		return (($affectedRows > 0 && $metaStored) ? $rowData->get('errID') : false);
	}

	public function lockError(int $errID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// TODO - implement');

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

		// Load error from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further error data required to call the files' deletion function below.
		$item = $this->getItem($errID);

		if (!is_a($item, 'Nematrack\Entity\Error') || !$item->get('errID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive errors at all?
		if (false === $this->canLockError($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this error.
		if (false === $this->errorIsLockable($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// Define database table name.
			$tableName = 'errors__NEW';	// FIXME - change to 'errors'

			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn($tableName))
			->set([
				$db->qn('blocked')    . ' = ' . $db->q('1'),
				$db->qn('blockDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('blocked_by') . ' = ' . $db->q((int) $user->get('userID'))
			])
			->where($db->qn('errID')  . ' = ' . $db->q($errID));

			// Execute query.
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

		return $errID;
	}

	public function unlockError(int $errID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// TODO - implement');

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

		// Load error from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further error data required to call the files' deletion function below.
		$item = $this->getItem($errID);

		if (!is_a($item, 'Nematrack\Entity\Error') || !$item->get('errID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover errors at all?
		if (false === $this->canRestoreError($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this error.
		if (false === $this->errorIsRestorable($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Define database table name.
		$tableName = 'errors__NEW';	// FIXME - change to 'errors'

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn($tableName))
		->set([
			$db->qn('blocked')    . ' = ' . $db->q('0'),
			$db->qn('blockDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('blocked_by') . ' = NULL'
		])
		->where($db->qn('errID')  . ' = ' . $db->q($errID));

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

		return $errID;
	}

	public function archiveError(int $errID)	// This is currently the opposite of restoreError - it blockes and archives an accessible item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// TODO - implement');

		/*// Can this user delete content?
		if (!$this->userCanArchivate())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
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

		// Load error from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further error data required to call the files' deletion function below.
		$error = $this->getItem($errID);

		if (!is_a($error, 'Nematrack\Entity\Error') || !$error->get('errID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive errors at all?
		if (false === $this->canArchiveError($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this error.
		if (false === $this->errorIsArchivable($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// Define database table name.
			$tableName = 'errors__NEW';	// FIXME - change to 'errors'

			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn($tableName))
			->set([
				$db->qn('archived')    . ' = ' . $db->q('1'),
				$db->qn('archiveDate') . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('archived_by') . ' = ' . $db->q((int) $user->get('userID')),
			])
			->where($db->qn('errID')   . ' = ' . $db->q($errID));

			// Execute query.
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

		return $errID;
	}

	public function restoreError(int $errID)	// This is currently the opposite of archiveError - it restored an archived item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// TODO - implement');

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRestore())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_RESTORE_TEXT', $this->language)
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

		// Load error from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further error data required to call the files' deletion function below.
		$error = $this->getItem($errID);

		if (!is_a($error, 'Nematrack\Entity\Error') || !$error->get('errID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover errors at all?
		if (false === $this->canRestoreError($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this error.
		if (false === $this->errorIsRestorable($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Define database table name.
		$tableName = 'errors__NEW';	// FIXME - change to 'errors'

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn($tableName))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('0'),
			$db->qn('archiveDate') . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('archived_by') . ' = NULL',
		])
		->where($db->qn('errID')   . ' = ' . $db->q($errID));

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

		return $errID;
	}

	public function deleteError(int $errID)		// This is currently the opposite of recoverError - it deletes an accessible item
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

		// Load error from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further error data required to call the files' deletion function below.
//		$error = $this->getItem($errID);	// FIXME - function not implemented yet

		/*// DiSABLED until function 'getItem()' is implemented
		if (!is_a($error, 'Nematrack\Entity\Error') || !$error->get('errID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}*/

		// Is this user allowed to delete errors at all?
		if (false === $this->canDeleteError($errID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this error.
		if (false === $this->errorIsDeletable($errID))
		{
			return false;	// Messages will be set by the function called.
		}

		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// Define database table name.
			$tableName = 'errors__NEW';	// FIXME - change to 'errors'

			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn($tableName))
			->set([
				$db->qn('trashed')    . ' = ' . $db->q('1'),
				$db->qn('trashDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('trashed_by') . ' = ' . $db->q((int) $user->get('userID')),
				$db->qn('deleted')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('deleted_by') . ' = ' . $db->q((int) $user->get('userID'))
			])
			->where($db->qn('errID')  . ' = ' . $db->q($errID));

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			// Reset AUTO_INCREMENT count.
			$db
			->setQuery('ALTER TABLE `errors` AUTO_INCREMENT = 1')
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

		return $errID;
	}

	public function recoverError(int $errID)	// This is currently the opposite of deleteError - it recovers a deleted item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// TODO - implement');

		// FIXME - ACL check doesn't match requirement
		/*// Can this user restore such content?
		if (!$this->userCanRecover())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_RESTORE_TEXT', $this->language)
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

		// Load error from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further error data required to call the files' deletion function below.
		$error = $this->getItem($errID);

		if (!is_a($error, 'Nematrack\Entity\Error') || !$error->get('errID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover errors at all?
		if (false === $this->canRestoreError($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this error.
		if (false === $this->errorIsRestorable($errID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Define database table name.
		$tableName = 'errors__NEW';	// FIXME - change to 'errors'

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn($tableName))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('0'),
			$db->qn('trashDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('trashed_by') . ' = NULL',
			$db->qn('deleted')    . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('deleted_by') . ' = NULL'
		])
		->where($db->qn('errID')  . ' = ' . $db->q($errID));

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

		return $errID;
	}

	// 202306-05 - MODiFiED return type
	public function existsError(int $errID = null, string $errorNumber = null, string $wincaratCode = null, string $errorName = null, string $lang = null) : Entity\Error
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

		// Define database table name.
		$tableName = (!is_null($errorName) || !is_null($lang)) ? 'error_meta' : 'errors__NEW';	// FIXME - change to 'errors'

		// Get database query object.
		$query = $db->getQuery(true);

		try
		{
			// Add ID as search condition.
			if (isset($errID))
			{
				$query
				->from($db->qn($tableName, 'e'))
				->select($db->qn('e.errID'))
				->where($db->qn('e.errID') . ' = ' . (int) $errID);
			}
			else
			{
				// Build query.
				if (isset($wincaratCode) && isset($errorName))
				{
					// Re-define database table name.
					$tableName = 'errors__NEW';	// FIXME - change to 'errors'

					$query
					->from($db->qn($tableName, 'e'))
					->select($db->qn('e.errID'))
					->join('LEFT', $db->qn('error_meta') . ' AS ' . $db->qn('em') .
						' ON ( ' .
							$db->qn('e.errID')  . ' = ' . $db->qn('em.errID') .
	//					' AND ' .
	//						$db->qn('em.lngID') . ' = ' . (int) $lngID .
						')'
					)
					->where('LOWER(' . $db->qn('e.wincarat') . ') = LOWER(' . $db->q(trim($wincaratCode)) . ')')
					->where('LOWER(' . $db->qn('em.name')    . ') = LOWER(' . $db->q(trim($errorName))    . ')');
				}
				elseif (isset($wincaratCode))
				{
					// Re-define database table name.
					$tableName = 'errors__NEW';	// FIXME - change to 'errors'

					$query
					->from($db->qn($tableName, 'e'))
					->select($db->qn('e.errID'))
					->where('LOWER(' . $db->qn('e.wincarat') . ') = LOWER(' . $db->q(trim($wincaratCode)) . ')');
				}
				elseif (isset($errorName))
				{
					// Re-define database table name.
					$tableName = 'error_meta';

					$query
					->from($db->qn($tableName, 'em'))
					->select($db->qn('em.errID'))
					->where('LOWER(' . $db->qn('em.name')    . ') = LOWER(' . $db->q(trim($errorName)) . ')');

					/*if (!is_null($lang))
					{
						$query
						->where($db->qn('em.language') . ' = ' . $db->q($lang))
						->select(
							$db->qn([
								'lngID',
								'language'
							])
						);
					}*/
				}
				elseif (isset($errorNumber))
				{
					// Re-define database table name.
					$tableName = 'errors__NEW';	// FIXME - change to 'errors'

					$query
					->from($db->qn($tableName, 'e'))
					->select($db->qn('e.errID'))
					->where('LOWER(' . $db->qn('e.number')   . ') = LOWER(' . $db->q(trim($errorNumber)) . ')');
				}
			}

			// Execute query.
			$id = $db->setQuery($query)->loadResult() ?? 0;
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

		return $this->getItem($id);
	}


	protected function canDeleteError(int $errID)	: bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$user = App::getAppUser();

		if (is_null($user))
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
		// $canDelete = ($user instanceof User && $user->hasRole(FTKUser::RoleEditor) ? (bool) $user->rights->canDelete : false);

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

	protected function errorIsDeletable(int $errID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if ($this->errorHasDependencies($errID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_DELETION_DEPENDENCIES_TEXT', $this->language)
			]);

			return false;
		}

		return true;
	}

	protected function errorHasDependencies(int $errID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Currently the only check to be applied is whether an error has already been tracked.
		return $this->isTracked($errID);





		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		try
		{
			// Build query.
			$query = $db->getQuery(true);

			// Execute query.
			$rows = $db->setQuery($query)->loadResult();
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

		return $rows > 0;
	}


	protected function hasErrorMeta(int $errID, string $lang, bool $isNotNull = false, $returnData = false)
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

		// Define database table name.
		$tableName = 'error_meta';

		// Get database query object.
		$query = $db->getQuery(true);

		try
		{
			// Build query.
			$query
			->from($db->qn($tableName))
			->where($db->qn('errID')    . ' = ' . $errID)
			->where($db->qn('language') . ' = ' . $db->q(trim($lang)));

			if ($isNotNull)
			{
				$query
				->where($db->qn('description') . ' IS NOT NULL');
			}

			if ($returnData)
			{
				$columns = DatabaseHelper::getTableColumns($tableName);

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
				->select('COUNT(' . $db->qn('errID') . ') AS ' . $db->qn('count'));
			}

			// Execute query.
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

	protected function storeErrorMeta(int $errID, array $errorMeta = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!count(array_filter($errorMeta)))
		{
			return true;
		}

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

		// Get data filter.
		$filter  = new InputFilter;

		// Build query.
		$rowData = new Registry($errorMeta);

		$hasMeta = $this->hasErrorMeta($errID, $rowData->get('lng'));

		$errDescription  = $filter->clean($filter->clean($rowData->get('description'), 'TRIM'), 'STRING');
		$errDescription  = (is_null($errDescription) || trim($errDescription)  == '') ? 'NULL' : $db->q($errDescription);

		// Define database table name.
		$tableName = 'error_meta';

		// Get database query object.
		$query = $db->getQuery(true);

		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// Build query.
			if (!$hasMeta)
			{
				$query
				->insert($db->qn($tableName))
				->columns(
					$db->qn([
						'errID',
						'lngID',
						'name',
						'description',
						'language'
					])
				)
				->values(implode(',', [
					$errID,
					(int) $filter->clean($rowData->get('lngID'), 'INT'),
					$db->q($filter->clean($filter->clean($rowData->get('name'), 'TRIM'), 'STRING')),
					$errDescription,
					$db->q($filter->clean($rowData->get('lng'), 'WORD'))
				]));
			}
			else
			{
				$query
				->update($db->qn($tableName))
				->set([
					$db->qn('name')        . ' = ' . $db->q($filter->clean($filter->clean($rowData->get('name'), 'TRIM'), 'STRING')),
					$db->qn('description') . ' = ' . $errDescription,
					$db->qn('language')    . ' = ' . $db->q($filter->clean($rowData->get('lng'), 'WORD')),
					$db->qn('modified')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
					$db->qn('modified_by') . ' = ' . (int) $user->get('userID')
				])
				->where($db->qn('errID')  . ' = ' . $errID)
				->where($db->qn('lngID')  . ' = ' . (int) $filter->clean($rowData->get('lngID'), 'INT'));
			}

			// Execute query.
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

	protected function deleteErrorMeta(int $errID) : bool
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

		// Define database table name.
		$tableName = 'error_meta';

		// Get database query object.
		$query = $db->getQuery(true);

		try
		{
			// Build query.
			$query
			->delete($db->qn('error_meta'))
			->where($db->qn('errID') . ' = ' . $errID);

			// Execute query.
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
}
