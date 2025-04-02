<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use finfo;
use Froetek\Coder\Coder;
use InvalidArgumentException;
use Joomla\Filter\InputFilter;
use Joomla\Image\Image as JImage;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use LogicException;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Entity\Image;
use Nematrack\Entity\Machine\Pressin;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Helper\FilesystemHelper;
use Nematrack\Helper\JsonHelper;
use Nematrack\Helper\MediaHelper;
use Nematrack\Messager;
use Nematrack\Model\Item as ItemModel;
use Nematrack\Text;
use Nematrack\Utility\Math;
use PhpParser\Node\NullableType;
use RuntimeException;
use stdclass;
use Symfony\Component\Validator\Constraints\Image as ImageValidator;
use Symfony\Component\Validator\Validation;
use Nematrack\Helper\UriHelper;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_walk;
use function class_exists;
use function is_a;
use function is_array;
use function is_null;
use function is_numeric;
use function is_object;
use function mb_strlen;

/**
 * Class description
 */
class Part extends ItemModel
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
	 * Returns for a given part code the number of its article type.
	 *
	 * @param   string $trackingCode
	 *
	 * @return  string|null
	 */
	public function getArticleNumber(string $trackingCode) : ?string
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


		if (empty($trackingCode))
		{
			return null;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID') . ' = ' . $db->qn('a.artID'))
		->select($db->qn('a.number'))
		->where($db->qn('a.blocked')       . ' = '  . $db->q('0'))
		->where($db->qn('p.blocked')       . ' = '  . $db->q('0'))
		->where($db->qn('p.trashed')       . ' = '  . $db->q('0'))
		->where($db->qn('p.trackingcode')  . ' = "' . $db->escape(trim($trackingCode)) .'"');

		// Execute query.
		try
		{
			$artNum = $db->setQuery($query)->loadResult();
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

	/**
	 * Returns for a given part code an object of data needed for the Labellae label printer.
	 * The returned object contains the following properties:
	 *    article number, article name, customer article number, customer article name, part code
	 *
	 * @param   string $trackingCode
	 *
	 * @return  array|null
	 */
	public function getLabelData(string $trackingCode) : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (empty($trackingCode))
		{
			return null;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->join('LEFT', $db->qn('articles')   . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID') . ' = ' . $db->qn('a.artID'))
		->select($db->qn('a.number')         . ' AS ' . $db->qn('artNumber'))		// FIXME - switch with next line after fields have been switched in DB and code base
		->select($db->qn('a.name')           . ' AS ' . $db->qn('artName'))			// FIXME - switch with previous line after fields have been switched in DB and code base
		// ->select($db->q('ETC ARTICLE NAME')  . ' AS ' . $db->qn('artName'))		// FIXME - switch with previous line after fields have been switched in DB and code base
		->select($db->qn('a.custartno')      . ' AS ' . $db->qn('custArtNumber'))
		->select($db->qn('a.custartname')    . ' AS ' . $db->qn('custArtName'))
		// ->select($db->q('CUST ARTICLE NAME') . ' AS ' . $db->qn('custArtName'))
		->select($db->qn('p.sample')         . ' AS ' . $db->qn('isSample'))		// Added on 2021-10-04
		->select($db->qn('p.trackingcode')   . ' AS ' . $db->qn('partCode'))
		->where($db->qn('a.blocked')         . ' = '  . $db->q('0'))
		->where($db->qn('a.trashed')         . ' = '  . $db->q('0'))
		->where($db->qn('p.blocked')         . ' = '  . $db->q('0'))
		->where($db->qn('p.trashed')         . ' = '  . $db->q('0'))
		->where($db->qn('p.trackingcode')    . ' = "' . $db->escape(trim($trackingCode)) .'"');

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

			$rs = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rs;
	}

	/**
	 * Add description...
	 *
	 * @param  Pressin $entity
	 *
	 * @return bool  true on success or false on failure
	 *
	 * @throws Exception
	 */
	public function handlePressinData(Pressin $entity) : bool
	{
		// Get current user object.
//		$user     = App::getAppUser();
//		$userID   = $user->get('userID');

		// Init vars.
		$partID   = $entity->get('partID',   $entity->__get('partID'));
		$artID    = $entity->get('artID',    $entity->__get('artID'));
		$procID   = $entity->get('procID',   $entity->__get('procID'));
		$logSaved = $entity->get('logSaved', $entity->__get('logSaved'));
		$dateTime = date_create('NOW');
		$tracked  = true;

		// Machine log not saved? Stop here and return.
		if (!$logSaved)
		{
			return true;
		}

		/** Dupe check whether this part/process is already tracked */

		// Get marker(s) for this process.
		$identifiers = (array) $entity->get('measuringPointsMap')[
			preg_replace('/^([A-Za-z]+)\d+$/', '$1', $entity->get('process'))
		];

		// Init shorthand to database object.
		$db     = $this->db;
		$query  = $db->getQuery(true);
		$tuples = [];

		// Fetch defined measuring points for this article/process.

		// Build query.
		$query
		->clear()
		->from($db->qn('article_process_mp_definition', 'apmpd'))
		->select($db->qn('apmpd') . '.*')
		->where($db->qn('apmpd.artID')         . ' = ' . $db->q($artID))
		->where($db->qn('apmpd.procID')        . ' = ' . $db->q($procID))
		->where($db->qn('apmpd.mpDescription') . ' = ' . $db->q($entity->get('config')))
		->order($db->qn('apmpd.mp'));   // Note: Ordering is important to ensure referencing via index will work correctly

		$mpDefinitions = (array) $db
		->setQuery($query)
		->loadAssocList();

		$mPoints    = array_column($mpDefinitions, 'mp');
		$hasMPoints = count($mPoints);

		// FIXME - why is the measuring points topic relevant here?
		// TODO - decide whether this info should be logged and add relevant code.
		// Even with no measuring points defined the part/process should be trackable, because the process was passed and the log data dumped.
		if ($hasMPoints)
		{
			$time     = date_create('NOW');
			$tracking = $entity->get('tracking');

			/** Track calculated (from machine log) values (Force/Distance) */

			// Fetch related project to get current status and tolerance factor.
			$project   = $this->getInstance('project', ['language' => $this->language])->getProjectByNumber(
				$this->getInstance('article', ['language' => $this->language])->getProjectNumber($artID)
			);
			$tolerance = $project->get('config')->factors->{$project->get('status')};

			// Helper function to calculate mpInput validity.
			// TODO - duplicate code (see line 2818) ... outsource into object function
			$calculateMeasurementResultValidity = function(string $mpValue, array $mpDefinition, float $mpToleranceFactor): string	// in function handlePressinData()
			{
				$mpValue          = floatval($mpValue);
				$mpNominal        = ArrayHelper::getValue($mpDefinition, 'mpNominal',  0, 'FLOAT');
				$mpLowerTolerance = ArrayHelper::getValue($mpDefinition, 'mpLowerTol', 0, 'FLOAT');
				$mpUpperTolerance = ArrayHelper::getValue($mpDefinition, 'mpUpperTol', 0, 'FLOAT');
				$mpLowerLimit     = $mpNominal - ($mpToleranceFactor * $mpLowerTolerance);
				$mpUpperLimit     = $mpNominal + ($mpToleranceFactor * $mpUpperTolerance);

				$isValid = ($mpValue >= $mpLowerLimit) && ($mpValue <= $mpUpperLimit);

				return ($isValid) ? 'valid' : 'invalid';
			};

			for ($i = 0, $j = count($identifiers); $i < $j; $i += 1)
			{
				// Force unique timestamp for every record to be able to sort by timestamp.
				// Otherwise, all records would be added with equal timestamp.
				date_add($time, date_interval_create_from_date_string('1 second'));

				$tuple = [
					'partID'            => $partID,
					'procID'            => $procID,
					'mp'                => $mpDefinitions[$i]['mp'],
					'mpInput'           => $tracking[$identifiers[$i]],
					'mpNominal'         => $mpDefinitions[$i]['mpNominal'],
					'mpLowerTol'        => $mpDefinitions[$i]['mpLowerTol'],
					'mpUpperTol'        => $mpDefinitions[$i]['mpUpperTol'],
					'mpToleranceFactor' => $tolerance,
					'mpValidity'        => null,
					'status'            => null,
					'timestamp'         => $time->format('Y-m-d H:i:s')    // We set this timestamp explicitly, because without it the correct mp-ordering is unintuitive
				];

				$tuple['mpValidity'] = $calculateMeasurementResultValidity($tuple['mpInput'], $mpDefinitions[$i], $tuple['mpToleranceFactor']);

				$tuple['status']     = $tuple['mpValidity'] == 'valid' ? 'success' : "NULL";	// The value corresponds to the TWBS text classes and
																								// is used for the colour representation of the validity of the measured values

				// Push to stack.
				$tuples[$tuple['mp']] = $tuple;
			}
		}

		// Check if there is already a previous tracking entry for this part and process.
		$isAlreadyTracked = false;

		// Build query.
		$query
		->clear()
		->from($db->qn('tracking', 't'))
		->select($db->qn('t.partID'))
		->where($db->qn('t.partID')  . ' = ' . (int) $partID)
		->where($db->qn('t.procID')  . ' = ' . (int) $procID)
		->where($db->qn('t.paramID') . ' = ' . Techparams::STATIC_TECHPARAM_DATE);

		try
		{
			$isAlreadyTracked = $db
			->setQuery($query)
			->loadResult();
		}
		catch (Exception $e)
		{}

		/** Generate data for process tracking, which is then passed to the responsible method */

		// Get related part.
		$item = $this->getItem((int) $partID);

		// Get currently active process drawing number.
		$procDrawingNumber = 'NULL';

		try
		{
			$query
			->clear()
			->from($db->qn('article_process'))
			->select($db->qn('drawing'))
			->where($db->qn('artID')  . ' = ' . $db->q($artID))
			->where($db->qn('procID') . ' = ' . $db->q($procID));

			$procDrawing       = $db->setQuery($query)->loadResult();
			$procDrawing       = (JsonHelper::isValidJSON((string) $procDrawing)) ? json_decode($procDrawing, null, 512, JSON_THROW_ON_ERROR) : null;
			$procDrawingNumber = (is_object($procDrawing)) ? sprintf('%s.%s', $procDrawing->number, $procDrawing->index) : null;

			// Clean up
			unset($procDrawing);
		}
		catch (JsonException $e)
		{
			// TODO - log error
//			echo '<pre>' . print_r(sprintf('Failed to extract the process\' drawing number: %s', $e->getMessage()), true) . '</pre>';
		}
		catch (Exception $e)
		{
			// TODO - log error
//			echo '<pre>' . print_r(sprintf('An unspecified error occurred: %s', $e->getMessage()), true) . '</pre>';
		}

		/* Prepare tracking data object.
		 * This data structure simulates the structure sent via POST when a worker submits tracking data through the app interface
		 * and is what the processing function expects..
		 */
		$processTrackingData = [
			'form' => [
				'procParams' => [
					$procID => [
						Techparams::STATIC_TECHPARAM_ORGANISATION => (is_int($plant = $entity->get('plant'))
							? $this->getInstance('organisation', ['language' => $this->language])->getItem((int) $plant)->get('name')
							: $plant
						),
						Techparams::STATIC_TECHPARAM_OPERATOR     => $entity->get('operator'),
						Techparams::STATIC_TECHPARAM_DATE         => $dateTime->format('Y-m-d'),
						Techparams::STATIC_TECHPARAM_TIME         => $dateTime->format('H:i:s'),
						Techparams::STATIC_TECHPARAM_DRAWING      => $procDrawingNumber,
						Techparams::STATIC_TECHPARAM_ERROR        => 0,    // Relates to this process' error catalog. 0 means the part is good ... no error(s)
						Techparams::STATIC_TECHPARAM_ANNOTATION   => 'Tracked by machine via API',
						// Techparams::STATIC_TECHPARAM_ANNOTATION   => 'Tracked by machine via API.' . ($hasMPoints ? '' : ' No measurement points were defined.'),
						10 => $entity->get('machine'),
						39 => $entity->get('batch')
					]
				]
			]
		];

		// If there is no previous tracking entry, we're good to continue.
		if (!$isAlreadyTracked)
		{
			// Generate data object similar to the HTML form data (POST) object.
			// This is what the function to call expects.
			/*$processTrackingData = [
				'form' => [
					'procParams' => [
						$procID => [
							Techparams::STATIC_TECHPARAM_ORGANISATION => $entity->get('plant'),
							Techparams::STATIC_TECHPARAM_OPERATOR     => $entity->get('operator'),
							Techparams::STATIC_TECHPARAM_DATE         => $dateTime->format('Y-m-d'),
							Techparams::STATIC_TECHPARAM_TIME         => $dateTime->format('H:i:s'),
							Techparams::STATIC_TECHPARAM_DRAWING      => $procDrawingNumber,
							Techparams::STATIC_TECHPARAM_ERROR        => 0,    // Relates to this process' error catalog. 0 means the part is good ... no error(s)
							Techparams::STATIC_TECHPARAM_ANNOTATION   => 'Tracked by machine via API',
							// Techparams::STATIC_TECHPARAM_ANNOTATION   => 'Tracked by machine via API.' . ($hasMPoints ? '' : ' No measurement points were defined.'),
							10 => $entity->get('machine'),
							39 => $entity->get('batch')
						]
					]
				]
			];*/

			$processTrackingData['form']['procParams'][$procID][39] = $entity->get('batch');

			// If there is at least 1 measuring point definition we add to the form data object
			// the calculated tracking data passed to this function.
			if ($hasMPoints)
			{
				$processTrackingData['form']['procMeasurementData'] = [
					$procID => $tuples
				];
			}
		}
		else
		{
			// Get everything that was previously tracked for this part.
			$partTrackedData  = $item->get('trackingData');
			$partTrackedData  = (array) ArrayHelper::getValue($partTrackedData,  $procID, []);

			// Get measured data from passed entity.
			$partMeasuredData = $item->get('measuredData');
			$partMeasuredData = (array) ArrayHelper::getValue($partMeasuredData, $procID, []);

			// Get time of the very last tracking entry.
			$lastTrackedTime  = (array) end($partMeasuredData);
			$lastTrackedTime  = ArrayHelper::getValue($lastTrackedTime, 'timestamp');
			$lastTrackedTime  = date_create($lastTrackedTime);

			// Get list of previously tracked press-fit batch IDs.
			$lastBatchIDs     = ArrayHelper::getValue($partTrackedData, '39', '', 'STRING');
			$lastBatchIDs     = array_unique(array_map('trim', explode(',', $lastBatchIDs)));

			// Add batch ID of currently processed press-fit.
			$newBatchIDs      = $lastBatchIDs;
			$newBatchIDs[]    = $entity->get('batch');
			$newBatchIDs      = array_unique(array_map('trim', $newBatchIDs));

			/*$processTrackingData = [
				'form' => [
					'procParams' => [
						$procID => [
							Techparams::STATIC_TECHPARAM_ORGANISATION => $entity->get('plant'),
							Techparams::STATIC_TECHPARAM_OPERATOR     => $entity->get('operator'),
							Techparams::STATIC_TECHPARAM_DATE         => $dateTime->format('Y-m-d'),
							Techparams::STATIC_TECHPARAM_TIME         => $dateTime->format('H:i:s'),
							Techparams::STATIC_TECHPARAM_DRAWING      => $procDrawingNumber,
							Techparams::STATIC_TECHPARAM_ERROR        => 0,    // Relates to this process' error catalog. 0 means the part is good ... no error(s)
							Techparams::STATIC_TECHPARAM_ANNOTATION   => 'Tracked by machine via API',
							// Techparams::STATIC_TECHPARAM_ANNOTATION   => 'Tracked by machine via API.' . ($hasMPoints ? '' : ' No measurement points were defined.'),
							10 => $entity->get('machine'),
							39 => implode(', ', $newBatchIDs)
						]
					]
				]
			];*/
			$processTrackingData['form']['procParams'][$procID][39] = implode(', ', $newBatchIDs);

			// If for this part/press-fit there are measuring points defined, add measured data to previously tracked measured data
			if ($hasMPoints)
			{
				foreach ($tuples as $mp => $tuple)
				{
					if (array_key_exists($mp, $partMeasuredData))
					{
						// Skip! Was previously tracked.
						unset($partMeasuredData[$mp]);

						continue;
					}

					date_add($dateTime, date_interval_create_from_date_string('1 second'));

					$tuples[$mp]['timestamp'] = $dateTime->format('Y-m-d H:i:s');
				}

				// Append measuring data to preserve previously tracked data while current tracking data.
				$tuples = array_merge($partMeasuredData, $tuples);
			}
			// If for this part/press-fit there are NO measuring points defined, ensure that previously tracked measured data is preserved.
			else
			{
				$tuples = $partMeasuredData;
			}

			// Replace previous measuring data with updated version.
			if (count($tuples))
			{
				$processTrackingData['form']['procMeasurementData'] = [
					$procID => $tuples
				];
			}
		}

		// If there is something to track, hand it over to part model function and let it do this job, since it is already implemented there.
		if (!empty($processTrackingData))
		{
			$tracked = $this->storeTrackingData(
				(int) $item->get('partID'),
				(int) $procID,
				$processTrackingData
			);
		}

		return $tracked;
	}

	/***   END: API-Service(s)   ***/


	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param  int $itemID
	 *
	 * @return Entity\Part
	 */
	public function getItem(int $itemID) : Entity\Part
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		$row = null;

		if ($itemID > 0)
		{
			/*// DiSABLED on 2023-07-20 - replaced by follow-up code
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('parts', ['language' => $this->language])->getList($itemID),
				$itemID
			);*/

			$args = [
				'partID'   => $itemID,
//				'language' => $lang,
//				'filter'   => ListModel::FILTER_ACTIVE
			];

//			if ($user->isProgrammer()) :
				$list = (array) $this->getInstance('parts', ['language' => $this->language])->getListNEW($args);	// FIXME - once implementation is finished drop condition 'user->isProgrammer'
//			else :
//				$list = (array) $this->getInstance('parts', ['language' => $this->language])->getList($itemID);
//			endif;

			$row  = ArrayHelper::getValue($list, $itemID);
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf('Nematrack\Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf('Nematrack\Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('partID'))
		{
			// Is item a component of another item (e.g. joining part) ? If yes, store code of parent part.
			$row->set('isComponentOf', $this->getIsComponentOf( $row->get('trackingcode')) );

			// Add references to media files.
			$row->set('mediaFiles', MediaHelper::getMediafilesForPart($itemID));
		}

		return $row;
	}

	// ADDED on 2023-07-25
	public function getApprovalTimes(int $partID, ...$args) : array
	{
		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// @debug
		$debug  = $user->isProgrammer();
//		$debug  = false;

		// Get additional function args.
//		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be data filter arguments passed to this function.
//		$filter   = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$procID   = ArrayHelper::getValue($args, 'procID', 0, 'INT');
		$procID   = (is_null($procID)  ? $procID : (int) $procID);
		$procIDs  = ArrayHelper::getValue($args, 'procIDs', [], 'ARRAY');
		$procIDs  = (is_null($procIDs) ? [] : $procIDs);
		$procIDs  = array_filter(array_unique(array_merge($procIDs, [$procID])));

		$lang     = ArrayHelper::getValue($args, 'language');
		$lang     = (is_null($lang))   ? $this->language : trim($lang);
		$language = $this->getInstance('language')->getLanguageByTag($lang);

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		/*
		SELECT `ta`.`procID`,
		CONCAT('[',
			GROUP_CONCAT(
				CONCAT('{', '\"approver\"', ':', approverID, ',', '\"permitee\"', ':', permiteeID, '}')
			),
		']') AS `users`,
		CONCAT( '[', '"', REPLACE( GROUP_CONCAT( `ta`.`timestamp` ), ',', '","' ), '"', ']' ) AS `timestamps`
		FROM `tracking_approval` AS `ta`
		WHERE `ta`.`partID` = 47907 AND `ta`.`procID` IN( 25,39 )
		GROUP BY `ta`.`procID`
		ORDER BY `ta`.`procID`,`ta`.`timestamp`;
		*/

		// Build query.
		$tableName = 'tracking_approval';
		$query = $db->getQuery(true)
		->from($db->qn($tableName, 'ta'))
		->select($db->qn('ta.procID'))
		// Create a valid JSON-string from all process approval timestamps for further processing after loading.
		->select("CONCAT('[',
			GROUP_CONCAT(
				CONCAT('{',
					'\"approver\"', ':',  approverID,
					',',
					'\"permitee\"', ':',  permiteeID,
					',',
					'\"timestamp\"', ':', '\"', timestamp, '\"',
				'}')
			),
		']') AS {$db->qn('approvals')}")
//		->select("CONCAT( '[',
//			'\"', REPLACE( GROUP_CONCAT( {$db->qn('ta.timestamp')} ), ',', '\",\"' ), '\"',
//		']') AS {$db->qn('timestamps')}")
		->where($db->qn('ta.partID') . ' = '  . $partID)
		->group($db->qn('ta.procID'))
		->order(
			$db->qn([
				'ta.procID',
				'ta.timestamp'
			])
		);

		// Limit resultset to passed process id(s).
		if (count($procIDs))
		{
			$query
			->where($db->qn('ta.procID') . ' IN( ' . implode(',', $procIDs) . ' )');
		}

		// Init return value.
		$rs = [];

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList('procID');

			array_walk($rs, function(&$arr)
			{
				// Drop unnecessary information.
				unset($arr['procID']);

				// Extract JSON-data.
				$arr = json_decode($arr['approvals'], true, 512, JSON_THROW_ON_ERROR);

				return true;
			});
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

		/*
		// @debug
		if ($debug) :
			echo '<pre>rs: ' . print_r($rs, true) . '</pre>';
			die;
		endif;
		*/

		return $rs;
	}

	// Returns a list of parts where this part is a joining part of (if is one at all)
	public function getIsComponentOf(string $partCode) : array
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

		// Build sub query to fetch parameter IDs for joining part tracking.
		$sub = $db->getQuery(true)
			->from($db->qn('techparameters', 'tp'))
			->select($db->qn(['tp.paramID']))
			->where($db->qn('tp.lngID')   . ' = ' . $db->q('2'))
			->where('LOWER(' . $db->qn('tp.name') . ') LIKE "%joining part%"');

		// Build query.
		$query = $db->getQuery(true)
			->from($db->qn('tracking', 't'))
			->select($db->qn(['t.partID', 't.procID', 't.paramValue']))
			->where($db->qn('t.paramID')    . ' IN( '  . $sub . ' )')
			->where($db->qn('t.paramValue') . ' LIKE ' . $db->q($partCode));

		// Execute query.
		try
		{
			$rows = $db->setQuery($query)->loadAssocList('partID');
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

    //Frequency-Feature Starts
    public function getTFTM(int $artID, int $procID, int $partID, $lotID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
          $trackedParts = $db->getQuery(true)
            ->from($db->qn('tracking', 't'))
            ->join('LEFT', $db->qn('parts') . ' AS ' . $db->qn('p')
                . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('t.partID'))
            ->select($db->qn([
                't.partID',
            ]))
            ->where([
                $db->qn('t.procID') . ' = ' . $db->q(trim($procID)),
                $db->qn('t.paramID') . ' = ' . $db->q(trim(3)),
                $db->qn('p.artID')       . ' = ' . $db->q(trim($artID)),
                $db->qn('p.trashed')       . ' = ' . $db->q(trim(0)),
                $db->qn('p.blocked')       . ' = ' . $db->q(trim(0)),
                $db->qn('t.timestamp') . ' <= NOW()',
                $db->qn('t.timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ])
            ->order($db->qn('t.timestamp') . ' DESC');


        try {
            $artNum = $db->setQuery($trackedParts)->loadAssocList();
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function getHMPNT(int $artID, int $procID, int $partID, $lotID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $artmp = $this->activeArticleMP($artID,$procID);
        $totalParts = $this->getTFTM($artID, $procID, $partID, $lotID);
        $totalParts = array_keys($totalParts);
        //echo "<pre>";print_r(loadAssocList);
        $mpcount = [];
        foreach($artmp as $artmp){
            $val = round($artmp['mpFrequency'] * ( count($totalParts)/ $artmp['mpFrequencyScope']));
            $mpcount[$artmp['mp']] = $val;
        }

        try {
            $artNum = $mpcount;
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        return $artNum;
    }
    public function newCheckNeeded(int $artID, int $procID, int $partID, $artlimis,$mps,$lotID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        $artmp = $this->activeArticleMP($artID,$procID);
        $chkPr = $this->getHMPNT($artID,$procID,$partID, $lotID);
        //echo "<pre>";print_r($chkPr);
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        $user   = App::getAppUser();
        $userID = $user->get('userID');
        $query = $db->getQuery(true);

        $totalRecentParts = 500;

        /*if($userID == 244)
        {
            echo $print = $db->getQuery(true)
                ->select($db->qn('partID'))
                ->from($db->qn('parts'))
                ->where($db->qn('blocked') . ' = ' . $db->q('0'))
                ->where($db->qn('trashed') . ' = ' . $db->q('0'))
                ->where($db->qn('artID') . ' = ' . $db->q(trim($artID)))
                // ->where($db->qn('lotID') . ' = ' . (int)$lotID)
                ->order($db->qn('partID') . ' DESC')
                ->setLimit($totalRecentParts);
        }*/
        /*-------    --------*/
        $artNum1 = [];
        $artNum2 = [];

              $trackedParts = $db->getQuery(true)
                ->from($db->qn('tracking', 't'))
                ->join('LEFT', $db->qn('parts') . ' AS ' . $db->qn('p')
                    . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('t.partID'))
                ->select($db->qn([
                    't.partID',
                ]))
                ->where([
                    $db->qn('t.procID') . ' = ' . $db->q(trim($procID)),
                    $db->qn('t.paramID') . ' = ' . $db->q(trim(3)),
                    $db->qn('p.artID')       . ' = ' . $db->q(trim($artID)),
                    $db->qn('p.trashed')       . ' = ' . $db->q(trim(0)),
                    $db->qn('p.blocked')       . ' = ' . $db->q(trim(0)),
                    //->where($db->qn('t.timestamp') . ' <= NOW()')
                    $db->qn('t.timestamp') . ' <= NOW()',
                    $db->qn('t.timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                    //$db->qn('t.timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 2 MONTH)',
                ])
                ->order($db->qn('t.timestamp') . ' DESC')->setLimit($artlimis);

            $mkparts2 = $db->setQuery($trackedParts)->loadAssocList();
            $partIDs2 = array_column($mkparts2, 'partID');
            $whereInClause2 = implode(',', array_map('intval', $partIDs2));

        $artNum = null;
        if(!empty($whereInClause2)) {
            echo $query1 = $db->getQuery(true)
                ->from($db->qn('part_process_mp_tracking'))
                ->select($db->qn([
                    'partID',
                    'procID',
                    'mpInput',
                    'mp',
                    'timestamp'
                ]))
                ->where([
                    $db->qn('partID') . ' IN (' . $whereInClause2 . ')',
                    $db->qn('procID') . ' = ' . $procID,
                    $db->qn('mp') . ' = ' . "'$mps'",
                    $db->qn('timestamp') . ' <= NOW()',
                    $db->qn('timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                    //$db->qn('timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 2 MONTH)',
                    //$db->qn('timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 3 MONTH)',
                ])
                ->order($db->qn('timestamp') . ' DESC')->setLimit($artlimis);
            $artResults = $db->setQuery($query1)->loadAssocList();
            $artNum1 = array_filter($artResults, function($entry) {
                return $entry['mpInput'] != 0;
            });

            $lastTimestampQr1 = isset($artResults[0]['timestamp']) ? $artResults[0]['timestamp'] : null;
            echo $query2 = $db->getQuery(true)
                ->from($db->qn('part_process_mp_tracking_count'))
                ->select($db->qn([
                    'partID',
                    'procID',
                    'mpInput',
                    'mp',
                    'timestamp'
                ]))
                ->where([
                    $db->qn('partID') . ' IN (' . $whereInClause2 . ')',
                    $db->qn('procID') . ' = ' . $procID,
                    $db->qn('mp') . ' = ' . "'$mps'",
                    $db->qn('timestamp') . ' > ' . $db->q($lastTimestampQr1),
                    $db->qn('timestamp') . ' <= NOW()',
                    $db->qn('timestamp') . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                ])
                ->order($db->qn('timestamp') . ' DESC')->setLimit($artlimis);
            $artNum2 = $db->setQuery($query2)->loadAssocList();
        }
        try {
            $artNum = array_merge_recursive($artNum1, $artNum2);
            //echo "<pre>";print_r($artNum);
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function activeArticleMP(int $artID, int $procID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition'))
            ->select(
                $db->qn([
                    'artID',
                    'procID',
                    'mp',
                    'mpFrequency',
                    'mpDatatype',
                    'mpFrequencyScope',
                    'timestamp'
                ])
            )
            ->where($db->qn('artID')       . ' = '  . $artID)
            ->where($db->qn('procID')       . ' = '  . $procID)
            ->order($db->qn('procID'))
            ->order('RIGHT(' . $db->qn('mp') . ', 3)');
        try {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    //Frequency-Feature Ends


	//@todo - watch how often the date-parameter is used and implement BETWEEN where clause
	public function getMeasuredData(int $partID, $procID = null, string $fromDate = '', string $toDate = '') : array
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
		$table   = 'part_process_mp_tracking';
		$columns = DatabaseHelper::getTableColumns($table);

		$query   = $db->getQuery(true)
		->from($db->qn($table))
		->select(implode(',', $db->qn($columns)))
		->where($db->qn('partID') . ' = ' . $partID)
		->order($db->qn(['procID',/*'mp',*/'timestamp']));  // The attempt to fix unintuitive ordering with multi-structural mps
		// (like those for PressOne data) via disabling 'mp' failed.
		// The data is continuously displayed unintuitive, although it is stored with incremented non-duplicate timestamps.

		if ((int) $procID > 0)
		{
			$query->where($db->qn('procID') . ' = ' . (int) $procID);
		}

		// Execute query.
		try
		{
			$rows = $db->setQuery($query)->loadAssocList('mp');     // NOTED:  Loading by column name may cause data distinction when there are redundant mp names

			if (count($rows))
			{
				$tmp = [];

				array_filter($rows, function($row, $key) use(&$tmp)
				{
					$procID = ArrayHelper::getValue($row, 'procID', null, 'INT');

					$tmp[$procID] = $tmp[$procID] ?? [];

					$tmp[$procID][$key] = $row;
					// $tmp[$procID][] = $row;

				}, ARRAY_FILTER_USE_BOTH);

				$rows = $tmp;

				unset($tmp);
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

	// ADDED on 2023-08-16 by Tino
	// TODO - decide on another entity, Tracking, that binds data for a single tracking dataset. Maybe this could be useful throughout all the part-views?
	public function getTrackingData(int $partID, int $procID) :? array
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
		$table   = 'tracking';
		$columns = DatabaseHelper::getTableColumns($table);

		$query   = $db->getQuery(true)
		->from($db->qn($table))
		->select(implode(',', $db->qn($columns)))
		->where($db->qn('partID') . ' = ' . $partID)
		->order($db->qn(['paramID','timestamp']));

		if ($procID > 0)
		{
			$query
			->where($db->qn('procID') . ' = ' . $procID);
		}

		// Execute query.
		try
		{
			$row = $db->setQuery($query)->loadAssoc();

			if (is_array($row))
			{
				$row = array_filter($row);
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

		return $row;
	}

	public function getItemByCode(string $partCode) : ?Entity\Part
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init return value.
		$part = null;

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->select('partID')
			->from($db->qn('parts'))
			->where('UPPER(' . $db->qn('trackingcode') . ') = UPPER( TRIM(' . $db->q(trim($partCode)) . ') )');

			// Execute query.
			$rs = $db->setQuery($query)->loadResult();

			$part = $this->getItem((int) $rs);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$db
		->getQuery()
		->clear();

		$db
		->freeResult();

		return $part;
	}

	public function getPrototype(int $artID) : ?Entity\Article
	{
		return $this->getInstance('article', ['language' => $this->language])->getItem($artID);
	}

	public function addLot(int $aid, int $copies = 0, array $processParams = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - translate
		if (!class_exists('Froetek\Coder\Coder'))
		{
			// TODO - translate
			throw new RuntimeException(sprintf('Missing dependency: %s', 'FROETEK Code Generator'));
		}

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db   = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Check value for lot size and abort if its <= 0:
		if ($copies <= 0)
		{
			Messager::setMessage([
				'type' => 'danger',
				// TODO - translate
				'text' => 'Not allowed to create an empty lot. Aborted.'
			]);

			return null;
		}

		// Drop empty fieldsets
		foreach ($processParams as $pid => &$fieldset)
		{
			if (empty($fieldset = array_filter($fieldset)))
			{
				unset($processParams[$pid]);
			}
			else
			{
				// Inject default error status 'passed'.
				if (!isset($fieldset[Techparams::STATIC_TECHPARAM_ERROR]))
				{
					$fieldset[Techparams::STATIC_TECHPARAM_ERROR] = '0';
				}

				ksort($fieldset);
			}
		}

		$userID = $user->get('userID');
		$orgID  = $user->get('orgID');
		$userOrganisation = $this->getInstance('organisation', ['language' => $this->language])->getItem($orgID);

		/*// FIXME - Create system message to render when user is invalid or not allowed to create
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
		}*/

		// Get artID from part.
		$artID = $aid;

		// Get article to besure the article to be duplicated exists and to load its drawings.
		$articleModel = $this->getInstance('article', ['language' => $this->language]);
		$article      = $articleModel->getItem($artID);

		if (!is_a($article, 'Nematrack\Entity\Article'))
		{
			Messager::setMessage([
				'type' => 'danger',
				// TODO - translate
				'text' => 'Not allowed to create an empty lot. Aborted.'
			]);

			return null;
		}

		$artProcesses = $article->get('processes');

		// Free memory.
		unset($articleModel);
		unset($article);

		// Init new lot.
		$lotModel  = $this->getInstance('lot', ['language' => $this->language]);
		$lotID     = $lotModel->addLot($artID);

		// Fetch created lot.
		$lot       = $lotModel->getItem($lotID);
		$lotID     = $lot->get('lotID');
		$lotNumber = $lot->get('number', '');

		// If lot creation failed, stop right here.
		if (!is_numeric($lotID) || (int) $lotID <= 0)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_LOT_COULD_NOT_BE_CREATED_TEXT', $this->language)
			]);

			return null;
		}

		if (!mb_strlen(trim($lotNumber)))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_LOT_COULD_NOT_BE_CREATED_TEXT', $this->language)
			]);

			return null;
		}

		// Init temporary stack where parts to be created are dumped to.
		$lot->__set('items', []);
		// Get reference to temporary stack.
		$stack = $lot->__get('items');

		// Free memory.
		unset($lotModel);

		// Get last inserted part tracking code from database and pass it to the coder
		$lastCode = null;
//		$newCode  = null;

		// Prepare a lot of partIDs and dump them on stack.
		for ($i = 0; $i < $copies; $i += 1)
		{
			$lastCode = (is_null($lastCode)) ? $this->getLastCode() : $lastCode;
			$newCode  = Coder::getNextCode($lastCode);

			$stack[$newCode] = null;

			$lastCode = $newCode;
		}

		$now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$dateTime = $now->format('Y-m-d H:i:s');

		// Build query
		$query = $db->getQuery(true)
		->insert($db->qn('parts'))
		->columns(
			$db->qn([
				'lotID',
				'artID',
				'trackingcode',
				'created',
				'created_by'
			])
		);

		// Create parts.
		$processModel    = $this->getInstance('process',    ['language' => $this->get('language')]);
		$techParamsModel = $this->getInstance('techparams', ['language' => $this->get('language')]);

		foreach($stack as $trackingcode => $partID)	/* partID is actually null, because these are new entries having no row id yet */
		{
			/**  1. Store new part  **/

			// Reset query.
			$query
			->clear('values')
			->values(implode(',', [
				(int) $lotID,
				$artID,
				$db->q(trim($trackingcode)),
				$db->q($dateTime),
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

				// Interrupt processing when there's an error.
				/* if (($errNo = $stmt->errno) > 0)
				{
					// Init default error message.
					$error = Text::translate('COM_FTK_ERROR_DATABASE_SAVE_PART_TEXT', $this->language);

					// Show default error message.
					Messager::setMessage([
						'type' => 'error',
						'text' => $error
					]);

					// Additionally show a more specific error message when there's a 'Duplicate entry' - error.
					if ($stmt->errno == '1062')
					{
						$error = parent::getDatabaseError($this->context, null);

						Messager::setMessage([
							'type' => 'error',
							'text' => $error
						]);
					}
				}*/

				// continue;
				throw new RuntimeException(sprintf('Failed to store new row to parts-table for code <strong>%d</strong>: %s', $e->getCode(), $e->getMessage()));	// TODO - translate
			}

			/**  2. Store parameters for new part  **/

			// Interrupt processing when there's no insert id from previous insertion query.
			if (!$insertID)
			{
				// TODO - translate
				throw new RuntimeException(sprintf('The tracking data for part %s cannot be stored. The previous database query did not return a row id.', $trackingcode));	// TODO - translate
			}

			// Now store process parameters (user input) for this part.
			foreach ($processParams as $procID => $techParams)
			{
				$process         = $processModel->getItem($procID, $this->get('language'), true);
				$artProcess      = ArrayHelper::getValue($artProcesses, $procID, new Registry);
				$drawing         = new Registry($artProcess->get('drawing', new stdclass));
				$statTechParams  = $techParamsModel->getStaticTechnicalParameters();
				$techParamIDs    = array_keys((array) $process->get('tech_params'));

				$tmp = [];

				// Prepare technical parameter data for the new part.
				// Injecting current information for static parameters + clone part information for custom parameters.
				foreach ($techParamIDs as $tpid)
				{
					switch ($tpid)
					{
						case Techparams::STATIC_TECHPARAM_ORGANISATION :
							$tmp[$tpid] = $userOrganisation->get('name');
						break;

						case Techparams::STATIC_TECHPARAM_OPERATOR :
							$tmp[$tpid] = $user->get('fullname');
						break;

						case Techparams::STATIC_TECHPARAM_DATE :
							$tmp[$tpid] = $now->format('Y-m-d');
						break;

						case Techparams::STATIC_TECHPARAM_TIME :
							$tmp[$tpid] = $now->format('H:i:s');
						break;

						case Techparams::STATIC_TECHPARAM_DRAWING :
							$tmp[$tpid] = sprintf('%s.%s', $drawing->get('number'), $drawing->get('index'));
						break;

						// Assign what the user has typed (annotation) or selected (error catalog).
						default :
							$userInput = ArrayHelper::getValue($techParams, $tpid, null, 'STRING');
							$userInput = trim($userInput);

							$tmp[$tpid] = $userInput;
					}
				}

				// Skip empty entries.
				$tmp = array_map('trim', $tmp);

				// Construct data object expected by the function to be called to store the part parameters.
				$params = [
					'form' => [
						'procParams' => [
							$procID => $tmp
						]
					]
				];

				$tracked = $this->storeTrackingData(
					$insertID,
					(int) $procID,
					$params
				);
			}

			// Collect partID of new part.
			$stack[$trackingcode] = $insertID;
		}

		// Store lot <--> part map into the database.

		// Build query
		$query = $db->getQuery(true)
		->insert($db->qn('lot_part'))
		->columns(
			$db->qn([
				'lotID',
				'partID'
			])
		);

		foreach($stack as $trackingcode => $partID)
		{
			// Reset query.
			$query
			->clear('values')
			->values(implode(',', [
				(int) $lotID,
				(int) $partID
			]));

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

				continue;
			}
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $lot->get('number');
	}

	public function addPart($part)
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

		$formData = $part['form'] ?? [];

		// Test if tracking code is set.
		if (!array_key_exists('code', $formData) || (array_key_exists('code', $formData) && trim($formData['code']) == ''))
		{
			Messager::setMessage([
				'type' => 'error',
				// TODO - translate
				'text' => Text::translate('The unique tracking code is missing.', $this->language)
			]);

			return false;
		}

		// Test if tracking code is properly formatted. (AAA-BBB-CCC)
		if (!preg_match('/' . FTKREGEX_TRACKINGCODE . '/', $formData['code']))
		{
			Messager::setMessage([
				'type' => 'error',
				// TODO - translate
				'text' => Text::translate('The unique tracking code is invalid.', $this->language)
			]);

			return false;
		}

		// Dupe-check tracking code.
		$exists = $this->existsPart(ArrayHelper::getValue($formData, 'ptid', 0, 'INT'), ArrayHelper::getValue($formData, 'code', null, 'STRING'));

		if (true === $exists)
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_PART_TEXT', $this->language),
					ArrayHelper::getValue($formData, 'code', null, 'STRING')
				)
			]);

			return false;
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

//		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
//		$now = $now->format('Y-m-d H:i:s');

		// Build query.
		$rowData = new Registry($formData);

		$query = $db->getQuery(true)
		->insert($db->qn('parts'))
		->columns(
			$db->qn([
				'artID',
				'trackingcode',
				'created',
				'created_by'
			])
		)
		->values(implode(',', [
			(int) filter_var($rowData->get('type'), FILTER_VALIDATE_INT),
			$db->q(filter_var($rowData->get('code'), FILTER_SANITIZE_STRING)),
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

		// Close connection.
		$this->closeDatabaseConnection();

		return $insertID;
	}


	public function lockPart(int $partID)
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
		$item = $this->getItem($partID);

		if (!is_a($item, 'Nematrack\Entity\Part') || !$item->get('partID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive parts at all?
		if (false === $this->canLockPart($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this item.
		if (false === $this->itemIsLockable($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('parts'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('1'),
			$db->qn('blockDate')   . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('blocked_by')  . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('partID')   . ' = ' . $db->q($partID));

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

		return $partID;
	}

	public function unlockPart(int $partID)
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
		$item = $this->getItem($partID);

		if (!is_a($item, 'Nematrack\Entity\Part') || !$item->get('partID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover parts at all?
		if (false === $this->canRestorePart($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this item.
		if (false === $this->itemIsRestorable($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('parts'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('0'),
			$db->qn('blockDate')   . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('blocked_by')  . ' = NULL'
		])
		->where($db->qn('partID')   . ' = ' . $db->q($partID));

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

		return $partID;
	}

	public function archivePart(int $partID)	// This is currently the opposite of restorePart - it blockes and archives an accessible item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

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

		// Load part from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further part data required to call the files' deletion function below.
		$part = $this->getItem($partID);

		if (!is_a($part, 'Nematrack\Entity\Part') || !$part->get('partID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive parts at all?
		if (false === $this->canArchivePart($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this part.
		if (false === $this->partIsArchivable($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('parts'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('1'),
			$db->qn('archiveDate') . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('archived_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('partID')  . ' = ' . $db->q($partID));

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

		return $partID;
	}

	public function restorePart (int $partID)		// This is currently the opposite of archivePart - it restored an archived item
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

		// Load part from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($partID);

		if (!is_a($item, 'Nematrack\Entity\Part') || !$item->get('partID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover parts at all?
		if (false === $this->canRestorePart($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this part.
		if (false === $this->partIsRestorable($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('parts'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('0'),
			$db->qn('archiveDate') . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('archived_by') . ' = NULL'
		])
		->where($db->qn('partID')   . ' = ' . $db->q($partID));

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

		return $partID;
	}

	public function deletePart(int $partID)
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

		/*// Existence check.
		// Disabled on 2021-09-24 - not really useful. Deletion will silently complete even if the part does not exist.
		$exists = $this->existsPart($partID);

		if (false === $exists)
		{
			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_HINT_PART_HAVING_ID_X_NOT_FOUND_TEXT', $this->language)
			]);

			return false;
		}*/

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load part from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further part data required to call the files' deletion function below.
		$part = $this->getItem($partID);

		if (!is_a($part, 'Nematrack\Entity\Part') || !$part->get('partID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Validate this user is allowed to delete organisation first.
		if (false === $this->canDeletePart($partID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this part.
		if (false === $this->partIsDeletable($partID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Build query.
		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

		$query = $db->getQuery(true)
		->update($db->qn('parts'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('1'),
			$db->qn('trashDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('trashed_by') . ' = ' . $db->q((int) $user->get('userID')),
			$db->qn('deleted')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
			$db->qn('deleted_by') . ' = ' . $db->q((int) $user->get('userID'))
		])
		->where($db->qn('partID') . ' = ' . $db->q($partID));

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
			->setQuery('ALTER TABLE `parts` AUTO_INCREMENT = 1')
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

		return $partID;
	}

	public function recoverPart(int $partID)		// This is currently the opposite of deletePart - it recovers a deleted item
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

		// Load part from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($partID);

		if (!is_a($item, 'Nematrack\Entity\Part') || !$item->get('partID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover parts at all?
		if (false === $this->canRestorePart($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this part.
		if (false === $this->partIsRestorable($partID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('parts'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('0'),
			$db->qn('trashDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('trashed_by') . ' = NULL',
			$db->qn('deleted')    . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('deleted_by') . ' = NULL'
		])
		->where($db->qn('partID')  . ' = ' . $db->q($partID));

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

		return $partID;
	}


	protected function existsPart($partID = null, string $trackingCode = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db   = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Function parameter check.
		if (is_null($partID) && is_null($trackingCode))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function requires at least 1 argument.');
		}

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('parts', 'p'))
		->select($db->qn('p.partID'));

		// This function is not called PRIOR CREATION (no artID available) and PRIOR DELETION (artID is available).
		// Hence, 'partID' must not be the only column to check for!
		switch (true)
		{
			// Should find existing organisation identified by orgID + orgName.
			case (!empty($partID) && !empty($trackingCode)) :
				$query
				->where($db->qn('p.partID') . ' = ' . (int) $partID)
				->where('LOWER(' . $db->qn('p.trackingcode') . ') = LOWER( TRIM(' . $db->q(trim($trackingCode)) . ') )');
			break;

			// Should find existing organisation identified by orgID.
			case (!empty($partID) && (int) $partID > 0) :
				$query
				->where($db->qn('p.partID') . ' = ' . (int) $partID);
			break;

			// Should find existing organisation identified by orgName.
			case (!empty($trackingCode) && trim($trackingCode) !== '') :
				$query
				->where('LOWER(' . $db->qn('p.trackingcode') . ') = LOWER( TRIM(' . $db->q(trim($trackingCode)) . ') )');
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

	//@todo - implement
	protected function canDeletePart(int $partID)
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

	//@todo - implement
	protected function partIsDeletable(int $partID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if ($this->partHasDependencies($partID))
		{
			Messager::setMessage([
				'type' => 'error',
				// TODO - translate
				'text' => Text::translate('This part has dependencies and cannot be deleted.', $this->language)
			]);

			return false;
		}

		return true;
	}

	//@todo - implement proper check for dependencies
	protected function partHasDependencies(int $partID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Part has NO dependencies

		// TODO - implement proper dependency check
		return false;
	}

	public function getLastCode()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$row = null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		$sub = $db->getQuery(true)
		->select('MAX(' . $db->qn('partID') . ')')
		->from($db->qn('parts'));

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('trackingcode'))
		->from($db->qn('parts'))
		->where($db->qn('partID') . ' = (' . $sub . ')');

		// Execute query.
		try
		{
			// FIXME - can we utilize another load-Method just to fetch the property and return it without utilizing ArrayHelper?
			$row = (array) $db->setQuery($query)->loadAssoc();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$row = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return ArrayHelper::getValue($row, 'trackingcode', null, 'STRING');
	}


    public function storeEmptyMeasuredData(int $partID, int $procID, array $measuredData, array $measuringPoints)	//Mike function for saving empty values to count
    {
        //echo "hello";exit;
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user   = App::getAppUser();
//		$userID = $user->get('userID');

        // Init shorthand to database object.
        $db = $this->db;

        /* Force UTF-8 encoding for proper display of german Umlaute
         * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
         */
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();


        $table   = 'part_process_mp_tracking_count';
        $columns = DatabaseHelper::getTableColumns($table);
        // Merge in the status column
        $columns = array_merge(array_slice($columns, 0, 9), [9 => 'status', 10 => 'timestamp', 11 => 'trackcount']);

        $toleranceFactors = ['2.5','1.75','1.25','1'];	// FIXME - get from project model to prevent issues caused by hard coded data

        // Switch from indexed array to assoc. array.
        $tmp = [];

        foreach ($measuringPoints as $pid => $collection)
        {
            foreach ($collection as $arr)
            {
                $mp = ArrayHelper::getValue($arr, 'mp');

                if (!$mp) continue;

                $tmp[$pid][$mp] = $arr;
            }
        }

        $measuringPoints = $tmp;
        // END
        //echo "<pre>";print_r($measuringPoints);exit;
        try
        {
            // Get input filter for data sanitization.
            $filter = new InputFilter;

            // Iterate over the process parameters to be stored and add potentially new technical parameter(s).
            $query = $db->getQuery(true);

            // 1. Delete existing data first.

            $query
                ->delete($db->qn($table))
                ->where(true)
                ->andWhere([
                    $db->qn('partID') . ' = ' . $partID,
                    $db->qn('procID') . ' = ' . $procID
                ], 'AND');

            // Execute query.
            $db
                ->setQuery($query)
                ->execute();

            // 2. Insert new data.

            // Build query.
            echo $query
                ->clear()
                ->insert($db->qn($table))
                ->columns(implode(',', $db->qn($columns)));

            // Prepare artID <--> procID tuples.
            foreach ($measuredData as $pid => $collection)
            {
                $collection = (array) $collection;
                $collection = array_filter($collection);
//echo "<pre>";print_r($measuringPoints);exit;
                // Skip empty collections to prevent the database query from breaking.
                if (!count($collection))
                {
                    continue;
                }

                // NEW as of 2023-04-29
                // Get defined measuring points.
                $definitions = ArrayHelper::getValue($measuringPoints, $pid, [], 'ARRAY');
                // END

                foreach ($collection as $arr)
                {
                    $arr = (array) $arr;

                    // NEW as of 2023-04-29 the data type of mpNominal is deeper inspected + the user input is extracted for sanitization.
                    $mp = $mpDataType = $mpNominalIsBool = $mpNominalIsNumber = $mpNominalIsString = $userInput = null;

                    // NEW as of 2023-04-29
                    $mp              = ArrayHelper::getValue($definitions, ArrayHelper::getValue($arr, 'mp'));
                    $mpDataType      = ArrayHelper::getValue($mp, 'mpDatatype');
                    // END

                    $mpNominal       = ArrayHelper::getValue($arr, 'mpNominal', null, 'STRING');
                    $mpNominal       = trim($mpNominal);
                    $mpNominal       = (!is_null($mpNominal) && mb_strlen($mpNominal) > 0) ? $mpNominal : null;

                    // NEW as of 2023-04-29
                    $mpNominalIsBool   = $mpDataType == 'boolval';	// TODO - create HTML widgets for input fields allowing to pass render options like in this case "preg_match('/^(false|true)$/i', trim($str))"
                    $mpNominalIsNumber = $mpDataType == 'number';	// TODO - create HTML widgets for input fields allowing to pass render options like in this case the regex for live input validation "[-+]?[0-9]+(\.[0-9]+)?([eE][-+]?[0-9]+)?" taken from JInputFilter::cleanFloat()
                    $mpNominalIsString = $mpDataType == 'string';	// TODO - create HTML widgets for input fields allowing to pass render options like in this case "strip_tags((html_entity_decode($str, \ENT_QUOTES, 'UTF-8')))" adapted from JInputFilter::cleanString()
                    // END

                    // NEW as of 2023-04-29 check for mpNominal is number/string
                    if ($mpNominalIsNumber)
                    {
                        // TODO - migrate to Symfony validator
                        // For data type 'number' only numerical input is accepted.
                        $userInput = ArrayHelper::getValue($arr, 'mpInput', null, 'STRING');
                        $userInput = $filter->clean($userInput, 'FLOAT');

                        if (!is_numeric($userInput))
                        {
                            // TODO - translate + log error.
                            throw new InvalidArgumentException(sprintf('Invalid value for measuring point <em>%s</em>. Only numbers are allowed.', $mp));
                        }

                        $mpLowerTol = ArrayHelper::getValue($arr, 'mpLowerTol', 0, 'FLOAT');
                        $mpUpperTol = ArrayHelper::getValue($arr, 'mpUpperTol', 0, 'FLOAT');
                        $mpToleranceFactor = ArrayHelper::getValue($arr, 'mpToleranceFactor', 0, 'FLOAT');

                        // NEW as of 2023-04-29 check for mpNominal is number/string
                        if (!in_array($mpToleranceFactor, $toleranceFactors))
                        {
                            // TODO - translate + log error.
                            throw new InvalidArgumentException(Text::translate('Invalid tolerance factor.', $this->language));
                        }
                    }
                    else
                    {
                        $mpNominal         = 'NULL';
                        $mpLowerTol        = 'NULL';
                        $mpUpperTol        = 'NULL';
                        $mpToleranceFactor = 'NULL';

                        // TODO - migrate to Symfony validator
                        // For data type 'boolval' only 'true/false' is accepted.
                        if ($mpNominalIsBool)
                        {
                            $userInput = ArrayHelper::getValue($arr, 'mpInput', null, 'WORD');
                            $userInput = $filter->clean($userInput, 'TRIM');

                            if (!in_array($userInput, ['false', 'true']))
                            {
                                // TODO - translate + log error.
                                //throw new InvalidArgumentException(sprintf('Invalid value for measuring point <em>%s</em>. Only <strong>true or false</strong> is allowed.', json_encode($mp)));
                            }
                        }

                        // TODO - migrate to Symfony validator
                        // For data type 'string' sanitized and trimmed data is accepted.
                        if ($mpNominalIsString)
                        {
                            $userInput = ArrayHelper::getValue($arr, 'mpInput', null, 'STRING');
                            $userInput = $filter->clean($userInput, 'STRING');
                            $userInput = $filter->clean($userInput, 'TRIM');
                        }

                        // Escape user input.
                        $userInput = $db->q($userInput);
                    }

                    $mpValidity = ArrayHelper::getValue($arr, 'mpValidity', null);
                    $mpValidity = (!is_null($mpValidity) && mb_strlen($mpValidity) > 0 && $mpValidity != 'NULL') ? $db->q($mpValidity) : 'NULL';

                    $status     = ArrayHelper::getValue($arr, 'status', null);
                    $status     = (!is_null($status) && mb_strlen($status) > 0 && $status != 'NULL') ? $db->q($status) : 'NULL';

                    $timestamp  = ArrayHelper::getValue($arr, 'timestamp', null, 'STRING');
                    $timestamp  = (!is_null($timestamp) && mb_strlen($timestamp) > 0 && $timestamp != 'NULL') ? $db->q($timestamp) : 'NULL';
                    $trackcount  = ArrayHelper::getValue($arr, 'trackcount', null);
                    $trackcount     = ($trackcount == 'NULL') ? $db->q(1) : 1;

                    // Inject input validity 'status' column.
                    $row = [
                        $partID,
                        $pid,
                        $db->q(ArrayHelper::getValue($arr, 'mp')),
                        $mpNominal,
                        $mpLowerTol,
                        $mpUpperTol,
                        $mpToleranceFactor,
                        $userInput,
                        $mpValidity,
                        $status,
                        $timestamp,
                        $trackcount
                    ];

                    $query
                        ->values(implode(',', $row));
                }
            }

            echo $db
                ->setQuery($query)
                ->execute();
        }
        catch (Exception $e)
        {
            /*Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);*/

            return false;
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return true;
    }

	// MODiFiED on 2023-04-29
	// TODO - make $measuringPoints non-nullable when everything works
	public function storeMeasuredData(int $partID, int $procID, array $measuredData, array $measuringPoints)	// NEW: as of 2023-04-29 parameter $measuringPoints for the dataTypes being available
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user   = App::getAppUser();
//		$userID = $user->get('userID');

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
        $allEmpty = true;

		/* $formData = $processParams['form'] ?? [];

		if (\array_key_exists('procParams', $formData) && \is_array($formData['procParams']))
		{
			foreach ($formData['procParams'] as &$params)
			{
				switch (gettype($params)) :
					case 'object' :
						$params = (object) (array) $params;
					break;

					case 'array'  :
						// $params = \array_filter($params);
					break;

					default :
						// to nothing or handle strings, numbers, etc. separately
				endswitch;
			}
		}
		else
		{
			$formData['procParams'] = '[]';
		}

		if (!\is_object($formData))
		{
			$formData = (object) $formData;
		}*/

		$table   = 'part_process_mp_tracking';
		$columns = DatabaseHelper::getTableColumns($table);
		// Merge in the status column
		$columns = array_merge(array_slice($columns, 0, 9), [9 => 'status', 10 => 'timestamp']);

		// Drop column 'timestamp'. This is auto-populated by MySQL.
		/*if (in_array('timestamp', $columns))
		{
			unset($columns[array_search('timestamp', $columns)]);
		}*/

		// Store new entries (which include previously selected entries).
		// TODO - DELETE FIRST

		// FIXME - after implementation of a deletion routine replace "REPLACE" by "INSERT" because there's nothing to replace if a value should be deleted.

		// TODO - implement a deletion routine and do a cleanup first
		/* if (false === $this->deleteMeasuredData($procID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_DATABASE_SAVE_PROCESS_MEASURED_DATA_TEXT', $this->language)
			]);

			return false;
		}*/

		// No data. Abort and return.
		if (!count($measuredData))
		{
			return true;
		}

		// NEW as of 2023-04-29
		// Define allowed tolerance factors.
		$toleranceFactors = ['2.5','1.75','1.25','1'];	// FIXME - get from project model to prevent issues caused by hard coded data

		// Switch from indexed array to assoc. array.
		$tmp = [];

		foreach ($measuringPoints as $pid => $collection)
		{
			foreach ($collection as $arr)
			{
				$mp = ArrayHelper::getValue($arr, 'mp');

				if (!$mp) continue;

				$tmp[$pid][$mp] = $arr;
			}
		}

		$measuringPoints = $tmp;
		// END

		try
		{
			// Get input filter for data sanitization.
			$filter = new InputFilter;

			// Iterate over the process parameters to be stored and add potentially new technical parameter(s).
			$query = $db->getQuery(true);

			// 1. Delete existing data first.

			$query
			->delete($db->qn($table))
			->where(true)
			->andWhere([
				$db->qn('partID') . ' = ' . $partID,
				$db->qn('procID') . ' = ' . $procID
			], 'AND');

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			// 2. Insert new data.

			// Build query.
			$query
			->clear()
			->insert($db->qn($table))
			->columns(implode(',', $db->qn($columns)));

			// Prepare artID <--> procID tuples.
			foreach ($measuredData as $pid => $collection)
			{
				$collection = (array) $collection;
				$collection = array_filter($collection);

				// Skip empty collections to prevent the database query from breaking.
				if (!count($collection))
				{
					continue;
				}

				// NEW as of 2023-04-29
				// Get defined measuring points.
				$definitions = ArrayHelper::getValue($measuringPoints, $pid, [], 'ARRAY');
				// END

				foreach ($collection as $arr)
				{
					$arr = (array) $arr;

					// NEW as of 2023-04-29 the data type of mpNominal is deeper inspected + the user input is extracted for sanitization.
					$mp = $mpDataType = $mpNominalIsBool = $mpNominalIsNumber = $mpNominalIsString = $userInput = null;

					// NEW as of 2023-04-29
					$mp              = ArrayHelper::getValue($definitions, ArrayHelper::getValue($arr, 'mp'));
					$mpDataType      = ArrayHelper::getValue($mp, 'mpDatatype');
					// END

					$mpNominal       = ArrayHelper::getValue($arr, 'mpNominal', null, 'STRING');
					$mpNominal       = trim($mpNominal);
					$mpNominal       = (!is_null($mpNominal) && mb_strlen($mpNominal) > 0) ? $mpNominal : null;

					// NEW as of 2023-04-29
					$mpNominalIsBool   = $mpDataType == 'boolval';	// TODO - create HTML widgets for input fields allowing to pass render options like in this case "preg_match('/^(false|true)$/i', trim($str))"
					$mpNominalIsNumber = $mpDataType == 'number';	// TODO - create HTML widgets for input fields allowing to pass render options like in this case the regex for live input validation "[-+]?[0-9]+(\.[0-9]+)?([eE][-+]?[0-9]+)?" taken from JInputFilter::cleanFloat()
					$mpNominalIsString = $mpDataType == 'string';	// TODO - create HTML widgets for input fields allowing to pass render options like in this case "strip_tags((html_entity_decode($str, \ENT_QUOTES, 'UTF-8')))" adapted from JInputFilter::cleanString()
					// END

					// NEW as of 2023-04-29 check for mpNominal is number/string
					if ($mpNominalIsNumber)
					{
						// TODO - migrate to Symfony validator
						// For data type 'number' only numerical input is accepted.
						$userInput = ArrayHelper::getValue($arr, 'mpInput', null, 'STRING');
						$userInput = $filter->clean($userInput, 'FLOAT');

						if (!is_numeric($userInput))
						{
							// TODO - translate + log error.
							throw new InvalidArgumentException(sprintf('Invalid value for measuring point <em>%s</em>. Only numbers are allowed.', $mp));
						}

//						$mpLowerTol = ArrayHelper::getValue($arr, 'mpLowerTol', 'NULL', 'STRING');
//						$mpLowerTol = trim($mpLowerTol);
//						$mpLowerTol = is_null($mpLowerTol) ? "NULL" : StringHelper::parseFloat($mpLowerTol, true);
						$mpLowerTol = ArrayHelper::getValue($arr, 'mpLowerTol', 0, 'FLOAT');

//						$mpUpperTol = ArrayHelper::getValue($arr, 'mpUpperTol', 'NULL', 'STRING');
//						$mpUpperTol = trim($mpUpperTol);
//						$mpUpperTol = is_null($mpUpperTol) ? "NULL" : StringHelper::parseFloat($mpUpperTol, true);
						$mpUpperTol = ArrayHelper::getValue($arr, 'mpUpperTol', 0, 'FLOAT');

//						$mpToleranceFactor = ArrayHelper::getValue($arr, 'mpToleranceFactor', 'NULL', 'STRING');
//						$mpToleranceFactor = trim($mpToleranceFactor);
//						$mpToleranceFactor = is_null($mpToleranceFactor) ? "NULL" : StringHelper::parseFloat($mpToleranceFactor, true);
						$mpToleranceFactor = ArrayHelper::getValue($arr, 'mpToleranceFactor', 0, 'FLOAT');

						// NEW as of 2023-04-29 check for mpNominal is number/string
						if (!in_array($mpToleranceFactor, $toleranceFactors))
						{
							// TODO - translate + log error.
							throw new InvalidArgumentException(Text::translate('Invalid tolerance factor.', $this->language));
						}
					}
					else
					{
						$mpNominal         = 'NULL';
						$mpLowerTol        = 'NULL';
						$mpUpperTol        = 'NULL';
						$mpToleranceFactor = 'NULL';

						// TODO - migrate to Symfony validator
						// For data type 'boolval' only 'true/false' is accepted.
						if ($mpNominalIsBool)
						{
							$userInput = ArrayHelper::getValue($arr, 'mpInput', null, 'WORD');
							$userInput = $filter->clean($userInput, 'TRIM');

							if (!in_array($userInput, ['false', 'true']))
							{
								// TODO - translate + log error.
								//throw new InvalidArgumentException(sprintf('Invalid value for measuring point <em>%s</em>. Only <strong>true or false</strong> is allowed.', json_encode($mp)));
							}
						}

						// TODO - migrate to Symfony validator
						// For data type 'string' sanitized and trimmed data is accepted.
						if ($mpNominalIsString)
						{
							$userInput = ArrayHelper::getValue($arr, 'mpInput', null, 'STRING');
							$userInput = $filter->clean($userInput, 'STRING');
							$userInput = $filter->clean($userInput, 'TRIM');
						}

						// Escape user input.
						$userInput = $db->q($userInput);
					}

					$mpValidity = ArrayHelper::getValue($arr, 'mpValidity', null);
					$mpValidity = (!is_null($mpValidity) && mb_strlen($mpValidity) > 0 && $mpValidity != 'NULL') ? $db->q($mpValidity) : 'NULL';

					$status     = ArrayHelper::getValue($arr, 'status', null);
					$status     = (!is_null($status) && mb_strlen($status) > 0 && $status != 'NULL') ? $db->q($status) : 'NULL';

					$timestamp  = ArrayHelper::getValue($arr, 'timestamp', null, 'STRING');
					$timestamp  = (!is_null($timestamp) && mb_strlen($timestamp) > 0 && $timestamp != 'NULL') ? $db->q($timestamp) : 'NULL';

					// Inject input validity 'status' column.
                    if ($userInput == "") {
                        $this->storeEmptyMeasuredData($partID, $pid, $measuredData, $measuringPoints);

                    } else {


					$row = [
						$partID,
						$pid,
						$db->q(ArrayHelper::getValue($arr, 'mp')),
						$mpNominal,
						$mpLowerTol,
						$mpUpperTol,
						$mpToleranceFactor,
						$userInput,
						$mpValidity,
						$status,
						$timestamp
					];

					$query
					->values(implode(',', $row));
				}
			}
            }

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

		return true;
	}


    public function getuserTrack($userid) //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object.
        $user = App::getAppUser();

        // Init shorthand to database object.
        $db = $this->db;


        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        // Get user organisation information.
        $orgID    = $user->get('orgID');
        $query = $db->getQuery(true)
            ->from($db->qn('users', 'u'))
            ->select($db->qn(
                'u.trackjump'
            ))
            ->where($db->qn('u.userID')  . ' = "' . $db->escape(trim($userid)) .'"');


        // Execute query.
        try {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function getDisabledProcess($partID, $procID) //: ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        // Init shorthand to database object.
        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

         $query = $db->getQuery(true)
            ->from($db->qn('tracking', 'tr'))
            ->select($db->qn('tr.partID'))
            ->select($db->qn('tr.procID'))
            ->where($db->qn('tr.partID')  . ' = "' . $db->escape(trim($partID)) .'"')
            ->where($db->qn('tr.procID') . ' IN (' . $procID . ')')
            ->where($db->qn('tr.paramID')  . ' = "' . $db->escape(trim(6)) .'"');

        // Execute query.
        try {
            $getParts = $db->setQuery($query)->loadAssocList('procID');
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $getParts = null;
        }
        $this->closeDatabaseConnection();
        return $getParts;
    }
	// MODiFiED on 2023-04-29
	// meant like "updatePart", but since "updateXXX" refers to storing updated entity master data and that is not allowed for a part this function name cannot be used.
	/**
	 * Add description ...
	 *
	 * @uses {@link Validation}
	 */
    public function checkInputValue($array, $key) {
        if (array_key_exists($key, $array)) {
            if (is_null($array[$key])) {
                echo "$key is null";
            } else {
                echo "$key is set";
            }
        }
    }//Mikecode

    public function countKeys($array, $key) { //Frequency-Feature
        // Check if the given key exists in the array
        if (isset($array[$key])) {
            // Check if the value corresponding to the key is an array
            if (is_array($array[$key])) {
                // If it's an array, return the count of its elements
                return count($array[$key]);
            } else {
                // If it's not an array, return an error or handle it as needed
                return 0; // Or handle the error condition in a different way
            }
        } else {
            // If the key doesn't exist in the array, return an error or handle it as needed
            return 0; // Or handle the error condition in a different way
        }
    }
    public function storeEmptyTrackingData(int $partID, int $procID, $postData)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Get current user object
        $user = App::getAppUser();
        $userID = $user->get('userID');

        // Init shorthand to database object
        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        // Extract form data
        $formData = $postData['form'] ?? [];
        $isAutoTrack = isset($formData->at) && $formData->at;  // AutoTrack flag

        // If form data is empty, exit the function early
        if (empty($formData)) {
            return false;
        }
        if (empty($formData->artiID) || empty($formData->pid)) {
            echo "<pre style='color:red'>Error: Empty artID or procID. Skipping query execution.</pre>";
            return false; // Exit the function early to avoid executing empty queries
        }

        // Get current time in the required timezone
        $now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

        // Query to fetch measurement points (mp) for the article and process
       echo $trueboolquerydb = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition', 'apmd'))
            ->select($db->qn('apmd.mp'))
            ->where($db->qn('apmd.artID') . ' = ' . $db->q(trim($formData->artiID)))
            ->where($db->qn('apmd.procID') . ' = ' . $db->q(trim($formData->pid)));

        $tbResult = $db->setQuery($trueboolquerydb)->loadAssocList('mp');
        $keyData = array_keys($tbResult);  // Measurement points to track

        // Check if there is already existing tracking data for this part/process
        echo $checkIfDataExist = $db->getQuery(true)
            ->from($db->qn('part_process_mp_tracking_count', 'ppmt'))
            ->select($db->qn('ppmt.mp'))
            ->where($db->qn('ppmt.partID') . ' = ' . $db->q(trim($formData->ptid)))
            ->where($db->qn('ppmt.procID') . ' = ' . $db->q(trim($formData->pid)));

        $ptbResult = $db->setQuery($checkIfDataExist)->loadAssocList('mp');
        $pkeyData = array_keys($ptbResult);  // Existing measurement points
//echo "<pre>";print_r($keyData);print_r($pkeyData);exit;
        // Check if there are keyData and AutoTrack is enabled and no existing tracking data
        if (!empty($keyData) && $isAutoTrack == 1 && empty($pkeyData)) {
            $dateTime = $now->format('Y-m-d H:i:s');
//exit;            // Prepare insert query for the tracking data
            $query = $db->getQuery(true)
                ->insert($db->qn('part_process_mp_tracking_count'))
                ->columns(
                    $db->qn([
                        'partID',
                        'procID',
                        'mp',
                        'mpNominal',
                        'mpInput',
                        'mpValidity',
                        'status',
                        'timestamp'
                    ])
                );

            // Loop over measurement points and insert tracking data
            foreach ($keyData as $mps) {
                $query
                    ->clear('values')  // Clear values for each loop
                    ->values(implode(',', [
                        (int)$partID,
                        $db->q($procID),
                        $db->q($mps),        // Measurement point
                        $db->q('0'),         // mpNominal
                        $db->q('0'),         // mpInput
                        $db->q('valid'),     // mpValidity
                        $db->q('success'),   // status
                        $db->q($dateTime)    // timestamp
                    ]));
                //echo $query->dump();
               // echo "<pre>SQL Query to Execute: " . $query . "</pre>";
                // Execute the query to insert the data
                $db->setQuery($query)->execute();
            }//exit;
        }

        // Close database connection (assuming there's a method for that)
        $this->closeDatabaseConnection();
        return true;
    }

    public function storeTrackingData(int $partID, int $procID, $postData)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - be inspired by storeProcessParameters()

		// Get current user object.
		$user = App::getAppUser();
		$userID = $user->get('userID');

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = $postData['form'] ?? [];

		if (array_key_exists('procParams', $formData) && is_array($formData['procParams']))
		{
			foreach ($formData['procParams'] as &$params)
			{
				switch (gettype($params)) :
					case 'object' :
						$params = (object) ((array) $params);
					break;

					case 'array'  :
						// $params = \array_filter($params);
					break;

					default :
						// to nothing or handle strings, numbers, etc. separately
				endswitch;
			}
		}
		else
		{
			$formData['procParams'] = '[]';
		}

		if (array_key_exists('procMeasurementData', $formData) && is_array($formData['procMeasurementData']))
		{
			foreach ($formData['procMeasurementData'] as $procMeasurementData)
			{
				foreach ($procMeasurementData as &$arr)
				{
					switch (gettype($arr)) :
						case 'object' :
							$arr = (object) ((array) $arr);
						break;

						case 'array'  :
							// $arr = \array_filter($arr);
						break;

						default :
							// to nothing or handle strings, numbers, etc. separately
					endswitch;
				}
			}
		}
		else
		{
			$formData['procMeasurementData'] = [];
		}

		// Existence check.
		// NEW: as of 2023-04-29 this check fetches the item to have its artID available for storing the measuredData along with the definitions
		$exists = $this->getItem($partID);
		// END

		if (!$exists)
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such part: %s', $this->language), $formData['number'])
			]);

			return false;
		}

		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		// ADDED on 2023-07-06 - required for change detection.
		// Extract tracked data for before/after comparison to detect changes.
		$previousTrackings = $exists->get('trackingData');

		// Store new entries (which include previously selected entries).
		// TODO - DELETE FIRST

		// FIXME - after implementation of a deletion routine replace "REPLACE" by "INSERT" because there's nothing to replace if a value should be deleted.

		// TODO - implement a deletion routine and do a cleanup first
		/*if (false === $this->deleteTrackingData($procID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_DATABASE_SAVE_PROCESS_TECH_PARAMS_TEXT', $this->language)
			]);

			return false;
		}*/

		// Iterate over the process parameters to be stored and add potentially new technical parameter(s).
		$query = $db->getQuery(true);

//		$affectedRows = 0;

		$isAutoTrack = isset($formData->at) && $formData->at;
		$isError     = false;
        //$mInputData = $formData->procMeasurementData[$formData->pid];
        //echo "<pre>";print_r($mInputData);exit;
        //echo "<pre>"; print_r($formData);exit;
        $view  = $this->__get('view');
        $input = (is_a($view, 'Nematrack\View') ? $view->get('input') : App::getInput());
        $workinglayout = $input->getCmd('layout');
        $workingdir = $input->getCmd('view');
        //Start of frequency feature
        //if($formData->pid != 29) {
        /*echo "<pre>";
        print_r($formData->procMeasurementData);

        exit;*///echo $workingdir;exit;
        $measuredData = $formData->procMeasurementData;
            if ($workingdir=='part' && $workinglayout == 'edit' && $isAutoTrack == 1) {
                $actualMPart = $this->activeArticleMP($formData->artiID, $formData->pid);

                $ifPartsInLotNew = [];
                foreach ($actualMPart as $valos) {
                    $artLimis = $valos['mpFrequencyScope'];
                    $ifPartsInLotNew[$valos['mp']] = $this->newCheckNeeded($formData->artiID, $formData->pid, $formData->ptid, $artLimis, $artLimis = $valos['mp'], $formData->lotID);
                }
                $mergedArray = $ifPartsInLotNew;
            //$mps = $this->getHMPNT($formData->artiID, $formData->pid, $formData->ptid, $formData->lotID);

            /*$newArray = [];
                foreach ($mps as $mp => $mpFrequency) {
                    if ($mpFrequency == 0) {
                        $mpFrequency = 3;
                    }
                    $newArray[] = [
                        'mp' => $mp,
                        'mpFrequency' => $mpFrequency
                    ];
            }*/
            //$actualMPart = $newArray;

                $actualMPartss = $this->activeArticleMP($formData->artiID, $formData->pid);

            /*$newArray = [];
                $mapArray2 = [];
                foreach ($actualMPartss as $item2) {
                    $mapArray2[$item2['mp']] = $item2;
                }

                foreach ($actualMPart as $item1) {
                    $mp = $item1['mp'];
                    if (isset($mapArray2[$mp])) {
                        $combinedItem = [
                            'mp' => $mp,
                            'mpFrequency' => $item1['mpFrequency'],
                            // Add other details from array2
                            'artID' => $mapArray2[$mp]['artID'],
                            'procID' => $mapArray2[$mp]['procID'],
                            'mpDatatype' => $mapArray2[$mp]['mpDatatype'],
                            'mpFrequencyScope' => $mapArray2[$mp]['mpFrequencyScope'],
                            'timestamp' => $mapArray2[$mp]['timestamp'],
                        ];
                        $newArray[] = $combinedItem;
                    }
            }*/
            $newArraym = [];
            $newArraym = $actualMPart;
                $mInputData = $formData->procMeasurementData[$formData->pid];

                $mp = []; // Initialize outside the loop
                $mpInput = []; // Initialize outside the loop
                foreach ($mInputData as $item) {
                    $mp[] = $item['mp'];
                    $mpInput[] = $item['mpInput'];
                }

            $kioTotal = array();
            for ($i = 0; $i < count($newArraym); $i++) {
                $targetMp = $newArraym[$i]['mp'];

                $matchingPartIDs[$i] = array();
                foreach ($mergedArray[$newArraym[$i]['mp']] as $itemdd) {
                    if ($itemdd['mp'] === $targetMp) {
                        $matchingPartIDs[$i][] = $itemdd['partID'];
                    }
                }
                $kio = is_array($matchingPartIDs[$i]) ? count($matchingPartIDs[$i]) : 0;
                $kioTotal[] = $kio;
            }
            for($i=0;$i<count($kioTotal);$i++){ //$kiototal is how many measurements are there
                if($newArraym[$i]['mpDatatype']!='boolval') {
                    $actualFrequencyValue = $newArraym[$i]['mpFrequency'];
						$lotnumber = $newArraym[$i]['mpFrequencyScope'];
						$alreadyTrackedValue = $kioTotal[$i];
						$actualCountNow = $lotnumber - $alreadyTrackedValue;

                    if($actualFrequencyValue<$alreadyTrackedValue)
                    {
                        if($actualCountNow == 0){
                            //echo "$actualFrequencyValue<$alreadyTrackedValue";exit;
                            if (empty($mpInput[$i])) {
                                echo "more to go";
                                $pdid = "p-" . hash('CRC32', $formData->pid);
                                $dynauri = UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=part&layout=edit&ptid=%d&pid=%d&artid=%d#%s',
                                    $this->language,
                                    $formData->ptid,
                                    $formData->pid,
                                    $formData->artiID,
                                    $pdid
                                )));
                                header("Location:" . $dynauri);
                                exit();
                            }

                        }
                    }else if($actualFrequencyValue>$alreadyTrackedValue)
                    {
                        if (empty($mpInput[$i])) {
                            echo "more to go";
                            $pdid = "p-" . hash('CRC32', $formData->pid);
                            $dynauri = UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=part&layout=edit&ptid=%d&pid=%d&artid=%d#%s',
                                $this->language,
                                $formData->ptid,
                                $formData->pid,
                                $formData->artiID,
                                $pdid
                            )));
                            header("Location:" . $dynauri);
                            exit();
                        }
                    }

                }
            }

            /*for ($i = 0; $i < count($newArray); $i++) {
                    $countKeysInCUT1DD004 = $this->countKeys($mergedArray, $newArray[$i]['mp']);
                //print_r($countKeysInCUT1DD004);

                    if ($newArray[$i]['mpDatatype'] != 'boolval') {
                        if ($newArray[$i]['mpFrequencyScope'] != 1) {
                            if ($newArray[$i]['mpFrequency'] > $countKeysInCUT1DD004) {
                                if (empty($mpInput[$i])) {
                                    echo "more to go";
                                    $pdid = "p-" . hash('CRC32', $formData->pid);
                                    $dynauri = UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=part&layout=edit&ptid=%d&pid=%d&artid=%d#%s',
                                        $this->language,
                                        $formData->ptid,
                                        $formData->pid,
                                        $formData->artiID,
                                        $pdid
                                    )));
                                    header("Location:" . $dynauri);
                                    exit();
                                }
                            }
                        }
                    }


            }*/


                }

        //exit;



        //End of frequency feature

        //Start of Active-Deactivated process check and save
            $now      = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
            $dataTimeaswell = $now->format('Y-m-d H:i:s');
            $dateParam = $now->format('Y-m-d');
            $timeParam = $now->format('H:i:s');

            $query = $db->getQuery(true)
                ->from($db->qn('article_process', 'ap'))
                ->select($db->qn('ap.procID'))
                ->select($db->qn('ap.step'))
                ->select($db->qn('ap.processState'))
                ->select($db->qn('ap.drawing'))
                ->where($db->qn('ap.artID') . ' = ' . $db->q(trim($formData->artiID)))
                ->order($db->qn('ap.step') . ' DESC');

            $mkparts = $db->setQuery($query)->loadAssocList('procID');

            $keysWithProcessStateZero = [];
            foreach ($mkparts as $key => $subArray) {
                if ($subArray['processState'] === 0) {
                    $keysWithProcessStateZero[] = $key;
                }
            }

            $keys = [];
            foreach ($mkparts as $key => $value) {
                if ($key == $formData->pid) {
                    break;
                }
                if ($value['processState'] == 0) {
                    $keys[$key] = $key;
                }
            }

            if(!empty($keys)) {
                $d = implode(', ', array_keys($keys)); //getting ready the key to check in for the function
                $trackedDisabledParts = $this->getDisabledProcess($partID,$d);
                $valuesToRemove = array_keys($trackedDisabledParts);
                $trackDisable = array_diff($keys, $valuesToRemove); //here get the keys which need to track and which are before the current process

                if (!in_array($formData->pid, $keysWithProcessStateZero) && !empty($trackDisable)) {
                    $query = $db->getQuery(true)
                        ->insert($db->qn('tracking'))
                        ->columns(
                            $db->qn([
                                'partID',
                                'procID',
                                'paramID',
                                'paramValue',
                                'timestamp',
                                'viaAutoTrack'
                            ])
                        );
                    foreach ($trackDisable as $value) {
                        echo $processState = $mkparts[$value]['drawing'];
                        $drawingInfo = json_decode($processState, true);
                        $number = $drawingInfo['number'] . '.' . $drawingInfo['index'];

                        for ($i = 1; $i < 7; $i++) {
                            if ($i == 1) {
                                $paramValue = "'Skipped Process'";
                            } elseif ($i == 2) {
                                $paramValue = "'Skipped Process'";
                            } elseif ($i == 3) {
                                $paramValue = "'$dateParam'";
                            } elseif ($i == 4) {
                                $paramValue = "'$timeParam'";
                            } elseif ($i == 5) {
                                $paramValue = "'$number'";
                            } elseif ($i == 6) {
                                $paramValue = 0;
                            }

                            $query
                                ->clear('values')
                                ->values(implode(',', [
                                    (int)$partID,
                                    $processState = $mkparts[$value]['procID'],
                                    $i,
                                    $paramValue,
                                    $db->q($dataTimeaswell),
                                    0,
                                ]));
                            $db
                                ->setQuery($query)
                                ->execute();
                        }
                    }
                } elseif (in_array($formData->pid, $keysWithProcessStateZero)) {
                    echo "<br>current id " . $formData->pid . " is in disable list";
                }
            }
        //End of Active-Deactivated process check and save

        //Start of AutoTrue for Boolean Values when AutoTracking is on
        /*$dataTypeb = "boolval";
        $trueboolquerydb = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition', 'apmd'))
            ->select($db->qn('apmd.mp'))
            ->where($db->qn('apmd.artID') . ' = ' . $db->q(trim($formData->artiID)))
            ->where($db->qn('apmd.mpDatatype') . ' = ' . $db->q(trim($dataTypeb)))
            ->where($db->qn('apmd.procID') . ' = ' . $db->q(trim($formData->pid)));

        $tbReasult = $db->setQuery($trueboolquerydb)->loadAssocList('mp');
        $keyData = array_keys($tbReasult);

        $checkIfDataExist = $db->getQuery(true)
            ->from($db->qn('part_process_mp_tracking', 'ppmt'))
            ->select($db->qn('ppmt.mp'))
            ->where($db->qn('ppmt.partID') . ' = ' . $db->q(trim($formData->ptid)))
            ->where($db->qn('ppmt.mpInput') . ' IN (' . $db->q('true') . ',' . $db->q('false') . ')')
            ->where($db->qn('ppmt.procID') . ' = ' . $db->q(trim($formData->pid)));

        $ptbReasult = $db->setQuery($checkIfDataExist)->loadAssocList('mp');
        $pkeyData = array_keys($ptbReasult);
        if (!empty($keyData) && $isAutoTrack == 1 && empty($pkeyData)){
            $now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
            $dateTime = $now->format('Y-m-d H:i:s');

            $query = $db->getQuery(true)
                ->insert($db->qn('part_process_mp_tracking'))
                ->columns(
                    $db->qn([
                        'partID',
                        'procID',
                        'mp',
                        'mpNominal',
                        'mpInput',
                        'mpValidity',
                        'status',
                        'timestamp'
                    ])
                );
            foreach ($keyData as $mps) {
                $dateTime = $now->format('Y-m-d H:i:s');
                $query
                    ->clear('values')
                    ->values(implode(',', [
                        (int)$partID,
                        $formData->pid,
                        $db->q($mps),
                        $db->q('true'),
                        $db->q('true'),
                        $db->q('valid'),
                        $db->q('success'),
                        $db->q($dateTime)
                    ]));
                $db
                    ->setQuery($query)
                    ->execute();
            }
        }*/
        //End of AutoTrue for Boolean Values when AutoTracking is on

		// 1. Store proof pictures
		//
		// TODO - outsource into {@see self::storeProofPics()}
		//

		$proofPics = (property_exists($formData, 'proofPics') && is_array($formData->proofPics)) ? $formData->proofPics : null;

		if (is_array($proofPics) && count($proofPics))
		{
			// Props
			$item    = $this->getItem($partID);
			$process = $this->getInstance('process', ['language' => $this->get('language')])->getItem($procID);
			$finfo   = new finfo(FILEINFO_MIME_TYPE);

			// Common configuration
			$uploadPath = MediaHelper::getUploadPathForPart($item->get('partID'));
			$storePath  = FilesystemHelper::fixPath(
				sprintf(implode(DIRECTORY_SEPARATOR, ['%s', '%d']),
					MediaHelper::getMediafilesPathForPart($item->get('partID')),
					$process->get('procID')
				)
			);
			$defaults   = [
				'uploadPath'      => $uploadPath,
				'storePath'       => $storePath,
				'originalsPath'   => sprintf('%s/originals', $storePath),
				'thumbsPath'      => sprintf('%s/thumbs',    $storePath),
				'dirPermissions'  => 0750,
				'filePermissions' => 0640,
				'font'            => sprintf('%s/font/arial.ttf', FTKPATH_ASSETS),
				'color'           => ['R' => 255, 'G' => 0, 'B' => 0],
				'shadow'          => ['R' => 255, 'G' => 255, 'B' => 255],
				'resolution'      => 72,     // Display resolution. 72 is common in digital space. If not set PHP falls back to 96dpi.
				'width'           => 1280,
				'height'          => 720,
				'opacity'         => 110,    // The transparency level. A value between  0 and 127.   0 indicates completely opaque while 127 indicates completely transparent.
				'imageQuality'    => 90,     // The compression level.  A value between 70 and 100. 100 indicates no compression while 70 is lower boundary for acceptable quality.
				'thumbsQuality'   => 80,     // The compression level.  A value between 70 and 100. 100 indicates no compression while 70 is lower boundary for acceptable quality.
				'watermark'       => sprintf('Copyright %d nematrack.com. All rights reserved.', date('Y'))
			];

			try
			{
				// 1.1. Create directories.

				// Create upload directory if not exists.
				if (!is_dir($defaults['uploadPath']))
				{
					$isMkDir = mkdir($defaults['uploadPath'], $defaults['dirPermissions'], true);

					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === $isMkDir)
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['uploadPath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}


				// Create target directory if not exists.
				if (!is_dir($defaults['storePath']))
				{
					$isMkDir = mkdir($defaults['storePath'], $defaults['dirPermissions'], true);

					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === $isMkDir)
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['storePath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TARGET_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}


				// Create thumbnails directory if not exists.
				if (!is_dir($defaults['originalsPath']))
				{
					$isMkDir = mkdir($defaults['originalsPath'], $defaults['dirPermissions'], true);

					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === $isMkDir)
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_RAW_FILES_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['originalsPath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_RAW_FILES_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}


				// Create thumbnails directory if not exists.
				if (!is_dir($defaults['thumbsPath']))
				{
					$isMkDir = mkdir($defaults['thumbsPath'], $defaults['dirPermissions'], true);

					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === $isMkDir)
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_THUMBNAILS_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['thumbsPath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_THUMBNAILS_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}

				// 1.2. Process image data.

				array_filter($proofPics, function($strData64) use(&$item, &$defaults, &$finfo)
				{
					// 2.1. Extract mime hint, encoding and image data from base64-string.
					preg_match('/^(data:)([^;]+);([^,]+),(.*)$/', $strData64, $groups);

//					$mime         = $groups[2];
//					$extension    = explode('/', $mime);
//					$extension    = mb_strtolower(end($extension));
					$tmpFileName  = sprintf('%s@%s_%s.png',
						mb_strtoupper($item->get('trackingcode')),
						mb_strtoupper($item->get('type')),
						hrtime(true)    // unique 16-digit number like 3080760748900229 to be used as unique id and for sorting (sys time doesn't work for 1+ uploaded files)
					);
					$tmpFilePath  = sprintf('%s%s%s', $defaults['uploadPath'],    DIRECTORY_SEPARATOR, $tmpFileName); // NEW
					$dataFilePath = sprintf('%s%s%s', $defaults['originalsPath'], DIRECTORY_SEPARATOR, $tmpFileName);
					$imgFilePath  = sprintf('%s%s%s', $defaults['storePath'],     DIRECTORY_SEPARATOR, $tmpFileName);

					// 2.2. Dump base64-image data into PNG file.
					file_put_contents($tmpFilePath, base64_decode($groups[4]));

					// 2.3 Validate dumped PNG file using Symfony Constraints to check if satisfies requirements.
					// see {@link https://symfony.com/doc/current/components/validator.html}
					// and {@link https://symfony.com/doc/current/validation/translations.html}
					$validator  = Validation::createValidator();
//					$violations = $validator->validate($dataFilePath, [
					$violations = $validator->validate($tmpFilePath, [
						new ImageValidator([
							'allowLandscape'  => true,
							'allowPortrait'   => true,
							'allowSquare'     => false,
							'detectCorrupted' => true,
							'mimeTypes'       => ['image/jpeg', 'image/pjpeg', 'image/png'],
							'minWidth'        => 1280,
							'maxWidth'        => 1920,
							'minHeight'       => 720,
							'maxHeight'       => 1080,
							'minRatio'        => 1.33,    //  4:3
							'maxRatio'        => 1.78,    // 16:9
							'maxSize'         => '5120k'
						])
					]);

					// Handle potential validation errors.
					if (0 !== count($violations))
					{
						// Render violation message(s).
						// see: https://stackoverflow.com/a/54219981
						array_walk($violations, function(array $violationList)
						{
							$messages = [];

							array_walk($violationList, function($violation) use(&$messages)
							{
								$messages[] = $violation->getMessage();        // FIXME - how to bind these to our languages?
							});

							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate(implode('<br/>', $messages), $this->language)
							]);

							throw new Exception;
						});
					}

					// 2.4. Generate Image object instance from dumped PNG file to generate JPEGs.
//					$image = new Image($dataFilePath);
					$image = new Image($tmpFilePath);
//					$image = new Image(imagecreatefromstring(base64_decode($groups[4])));   // works but causes issues on thumbs-generation because of the missing 'path' info

					// 2.4.1 Validate we have a proper image resource object.
					if (!is_a($image->getHandle(), 'GdImage') && !is_resource($image->getHandle()))
					{
						throw new LogicException('No valid image was uploaded.');    // TODO - translate
					}

					// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
					// ALWAYS Check MIME Type by yourself !!

					// 2.5 Validate mime type to catch potential security threat.
					if (false === $ext = mb_strtolower(array_search($mime = $image->getMime(), ['png' => 'image/png'], true)))
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_FILE_FORMAT_TEXT', $this->language));
					}

					// Find extension of uploaded file in array of allowed file extensions.
					if (false === $ext)
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_FILE_FORMAT_TEXT', $this->language));
					}

					// 2.6 Ensure image resolution is screen resolution, which is 72dpi (PHP default is 96dpi if not expl. applied).
					imageresolution($image->getHandle(), $defaults['resolution'], $defaults['resolution']);

					// 2.7 Generate thumbnails first (they must not be watermarked).
					$image->setThumbnailGenerate($defaults['thumbsQuality']);
					array_map(function($thumb) use(&$defaults)
					{
//						$pathinfo = @pathinfo($thumb->getPath());

						// Since the dumped PNG file hase no extension we must add file extensions to the generated thumbnails.
//						if (!in_array(mb_strtolower(ArrayHelper::getValue($pathinfo, 'extension')), ['jpg','jpeg','png','bmp']))
						if (!in_array(@pathinfo($thumb->getPath(), PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'bmp']))
						{
							$pathinfo = @pathinfo($thumb->getPath());

							$old = FilesystemHelper::fixPath(sprintf('%s/%s', $defaults['thumbsPath'], ArrayHelper::getValue($pathinfo, 'basename')));
							// Move position of dimension tag in file name.
							$new = preg_replace('/^(.*)_(\d{2,4}x\d{2,4})\.(000)_(.*)$/', "$1.$3_$2_$4", ArrayHelper::getValue($pathinfo, 'basename'));
							// Add extension.
							$new = FilesystemHelper::fixPath(sprintf('%s/%s.jpg', $defaults['thumbsPath'], $new));

							rename($old, $new);

							return $thumb;
						}

						// Fix CHMOD.
						chmod($thumb->getPath(), $defaults['filePermissions']);  // octal; correct value of mode

						// 16:9 landscape dimensions ['200x113','150x84','120x68','100x56']
						//          cubic dimensions ['113x113','150x84','120x68', '56x56']

						return true;
					}, $image->createThumbs(['200x113'], Image::CROP_RESIZE, $defaults['thumbsPath']));

					// Calculate the fullsize image diagonal length and watermark rotation angle.
					$anKathete    = Math::pixelToCentimeters($image->getWidth());
					$gegenKathete = Math::pixelToCentimeters($image->getHeight());
					$fontSize     = Math::pixelToCentimeters($image->getWidth() >= 1920 ? 50 : 45);
//					$hypotenuse   = Math::pythagoras($anKathete, $gegenKathete);
//					$asin         = asin($gegenKathete / $anKathete);
//					$sinAlpha     = ($asin / pi()) * 180;  // see {@link https://www.php.net/manual/en/function.asin.php}
					$atan     = atan(($gegenKathete - ($fontSize / 2)) / $anKathete);
					$tanAlpha = ($atan / pi()) * 180;  // see {@link https://www.php.net/manual/en/function.asin.php}

					// Define watermark props.
					$txtSize    = ($image->getWidth() >= 1920 ? 66 : 45);
					$txtColor   = imagecolorallocatealpha($image->getHandle(), $defaults['color']['R'], $defaults['color']['G'], $defaults['color']['B'], $defaults['opacity']);
					$txtShadow  = imagecolorallocatealpha($image->getHandle(), $defaults['shadow']['R'], $defaults['shadow']['G'], $defaults['shadow']['B'], $defaults['opacity']);
					$txtOffsetX = $txtSize;
					$txtOffsetY = $image->getHeight() - ($txtSize / 2);
					$txtAngle   = $tanAlpha;    // Calculated via Pythagorean Theorem

					// 2.8 Add watermark (the text itself and every effect are added as individual layers).

					// Add text shadow layer first.
					ImageTTFText($image->getHandle(), $txtSize, $txtAngle, $txtOffsetX + 1, $txtOffsetY + 1, $txtShadow, $defaults['font'], $defaults['watermark']);
					// Add text layer next.
					ImageTTFText($image->getHandle(), $txtSize, $txtAngle, $txtOffsetX, $txtOffsetY, $txtColor, $defaults['font'], $defaults['watermark']);

					// 2.9 Scale image if necessary.
					if ($image->getWidth() > $defaults['width'])
					{
						$image->resize($defaults['width'], $defaults['height'], false, JImage::SCALE_FIT);
					}

					// Define image type and quality to apply.
					switch ($mime)
					{
						case 'image/gif' :
							$imageType    = IMAGETYPE_GIF;
							$imageQuality = null;
						break;

						case 'image/png' :
							$imageType    = IMAGETYPE_PNG;
							$imageQuality = 9;  // Compression levels for imagepng: 0 (no compression) to 9.
						break;

						default :
						case 'image/jpg' :
						case 'image/jpeg' :
						case 'image/pjpeg' :
							$imageType    = IMAGETYPE_JPEG;
							$imageQuality = 80;  // Compression levels for imagejpeg: 0 (worst, smaller file) to 100 (best, biggest file). Default is 75.
						break;
					}

					// 2.10 Write out to generated image.
					$image->toFile($imgFilePath, $imageType, ['quality' => $imageQuality]);
					chmod($imgFilePath, $defaults['filePermissions']);  // octal; correct value of mode

					// 2.11 Move original image data file to target directory.
					rename($tmpFilePath, $dataFilePath);
					chmod($dataFilePath, $defaults['filePermissions']);  // octal; correct value of mode

				}, ARRAY_FILTER_USE_BOTH);

				// Free memory.
				unset($finfo);
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);

				return false;
			}
		}

		//
		// 2. Store tracking parameters
		//

		array_filter($formData->procParams, function($params, $pid) use(&$db, &$isAutoTrack, &$isError, &$partID, &$previousTrackings, &$query, &$user, &$userID)
		{
			$isError = false;

			// Skip process if the parameters object is empty (no user input).
			if (empty($params))
			{
				return;
			}

			$previousTracking = (array) ArrayHelper::getValue($previousTrackings, $pid);
			$diffTracking     = array_diff($params, $previousTracking);

			// TODO - refactor to use $tuples and reduce number of queries to execute from count($params) to 1.
			array_walk($params, function($paramValue, $paramID) use(&$affectedRows, &$db, &$diffTracking, &$isAutoTrack, &$isError, &$partID, &$pid, &$previousTracking, &$query, &$user)
			{
				if ($isError)
				{
					die('-14');

					// TODO - register error for display.

					return;
				}

				// ADDED on 2023-07-06 - required for change detection.
				$prevParamValue = ArrayHelper::getValue($previousTracking, $paramID);
				$nowParamValue  = $paramValue;
				$isInDiff       = array_key_exists($paramID, $diffTracking);
				$isValueChanged = $prevParamValue !== $nowParamValue;

				/* Skip empty parameters to prevent the DB from dead entries.
				 * Skip if the parameter value is empty + there is no previous value.
				 * If there's a previous value, the empty new value means, the previous value is deleted.
				 */
				if ($skipParam = ($paramID >= Techparams::STATIC_TECHPARAM_ANNOTATION && trim($paramValue) == '') && !$prevParamValue && $isInDiff)
				{
					// Continue with next parameter.
					return;
				}

				// Build query.
				$query
				->clear()
				->setQuery('REPLACE INTO `tracking` (`partID`, `procID`, `paramID`, `paramValue`, `viaAutoTrack`) VALUES (' .
					implode(',', [
						$partID,
						(int) $pid,
						(int) $paramID,
						$db->q(trim(DatabaseHelper::sanitizeQuery($paramValue))),
						(int) $isAutoTrack
					]) . ')'
				);

				// Execute query.
				try
				{
					$db
					->setQuery($query)
					->execute();
				}
				catch (Exception $e)
				{
					$isError = true;
				}

				if ($isError)
				{
					die('-15');

					// TODO - register error for display.

					return;
				}

				// If process status = '0' it means this part successfully passed this process.
				// If this part successfully passed this processes register its process to be reported back (book) into the system.
				if ((int) $paramID == Techparams::STATIC_TECHPARAM_ERROR && (int) $paramValue == '0')
				{
					$this->registerPartsForBooking([$partID], [(int) $pid]);
				}
			});

			// Update modification time
			$this->setModifiedBy('parts', 'partID', $partID, (int) $userID);

		}, ARRAY_FILTER_USE_BOTH);

		//
		// 3. Store measured data
		//

		$measuredDataStored = true;
        $view  = $this->__get('view');
        $input = (is_a($view, 'Nematrack\View') ? $view->get('input') : App::getInput());
        $workinglayout = $input->getCmd('layout');
        $workingdir = $input->getCmd('view');
        /*echo "oks";
        print_r($formData->procMeasurementData);
        exit;*/
        /*if (isset($formData->procMeasurementData)){
            echo "igen isset";exit;
        }else{
            echo "nem isset";exit;
        }*/
		if (isset($formData->procMeasurementData))
		{
			$measuredData = $formData->procMeasurementData;
            $allEmpty = true;
            if(!empty($measuredData)) {
                foreach ($measuredData[$procID] as $entry) {
                    if (!empty($entry['mpInput'])) {
                        $allEmpty = false;
                        break;
                    }
                }
            }
            if ($allEmpty && $workingdir=='part' && $workinglayout == 'edit') {
                $measuredData = array_filter($measuredData, function(&$collection) use(&$params)
                {
                    $collection = array_filter($collection, function(&$arr) use(&$params)
                    {
                        //echo "<pre>";print_r($collection);exit;
                        // Skip table row if user did not input something.
                        /*if (!ArrayHelper::getValue($arr, 'mpInput'))
                        {
                            //$arr['trackcount'] = 1;
                            return false;
                        }*/

                        $arr['timestamp'] = sprintf('%s %s',
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_DATE),
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_TIME)
                        );
                        $arr['trackcount'] = sprintf('%s %s',
                            1,
                            1
                        );

                        return true;
                    });

                    return count($collection) > 0;
                });

                // Helper function to calculate mpInput validity.
                // TODO - duplicate code (see line 282) ... outsource into object function
                $calculateMeasurementResultValidity = function(string $mpValue, array $mpDefinition, float $mpToleranceFactor) use (&$user): string	// in function storeTrackingData()
                {
                    $isValid = $isConditionallyValid = false;

                    $mpValue          = floatval($mpValue);
                    $mpNominal        = ArrayHelper::getValue($mpDefinition, 'mpNominal',  0, 'FLOAT');
                    $mpLowerTolerance = ArrayHelper::getValue($mpDefinition, 'mpLowerTol', 0, 'FLOAT');
                    $mpUpperTolerance = ArrayHelper::getValue($mpDefinition, 'mpUpperTol', 0, 'FLOAT');
                    $mpLowerLimit     = $mpNominal - $mpLowerTolerance;
                    $mpUpperLimit     = $mpNominal + $mpUpperTolerance;

                    // 1st validity calculation based on defined upper and lower tolerances.
                    // CHANGED on 2023-06-13, because the previous calculation appears to be wrong. Now the code is identical to the JavaScript code.
                    $isValid = ( (($mpNominal - $mpLowerTolerance) <= $mpValue) && ($mpValue <= ($mpNominal + $mpUpperTolerance)) );

                    // The measured value is invalid, but it may be conditionally valid. Do a 2nd calc.
                    if (!$isValid)
                    {
                        // Last chance that the input value is valid.
                        $mpLowerLimit     = $mpNominal - ($mpToleranceFactor * $mpLowerTolerance);
                        $mpUpperLimit     = $mpNominal + ($mpToleranceFactor * $mpUpperTolerance);

                        // CHANGED on 2023-06-13, because the previous calculation appears to be wrong. Now the code is identical to the JavaScript code.
                        $isConditionallyValid = ( (!$isValid) && ( (($mpNominal - ($mpLowerTolerance * $mpToleranceFactor)) <= $mpValue) && ($mpValue <= ($mpNominal + ($mpUpperTolerance * $mpToleranceFactor))) ) );
                    }

                    return ($isValid) ? 'valid' : ($isConditionallyValid ? 'conditionally_valid' : 'invalid');
                };

                // Generate and add timestamp column using timestamp from tracking data stored.
                array_walk($measuredData, function(&$arr) use(&$user, &$params, &$calculateMeasurementResultValidity)
                {
                    array_walk($arr, function(&$col) use(&$user, &$params, &$calculateMeasurementResultValidity)
                    {
                        // Evaluate user input validity and status.
                        // mpNominal is a boolean value.
                        if (in_array($col['mpNominal'], ['false', 'true']))
                        {
                            $col['mpValidity'] = $col['mpInput'] == $col['mpNominal'] ? 'valid' : 'invalid';

                            $col['status']     = $col['mpValidity'] == 'valid' ? 'success' : "NULL";	// The value corresponds to the TWBS text classes and
                            // is used for the colour representation of the validity of the measured values
                        }


                        // mpNominal is a numeric value.
                        if (is_numeric($col['mpNominal']))
                        {
                            if ($col['mpInput'] == $col['mpNominal'])
                            {
                                $col['mpValidity'] = 'valid';

                                $col['status']     = 'success';		// The value corresponds to the TWBS text classes and
                                // is used for the colour representation of the validity of the measured values
                            }
                            else
                            {
                                // Dump validity that was calculated via JavaScript while the user put the data in.
                                $jsValidity = $col['mpValidity'];

                                $phpValidity = $calculateMeasurementResultValidity(
                                    $col['mpInput'],
                                    [
                                        'mpNominal'  => $col['mpNominal' ],
                                        'mpLowerTol' => $col['mpLowerTol'],
                                        'mpUpperTol' => $col['mpUpperTol']
                                    ],
                                    $col['mpToleranceFactor']
                                );

                                $col['mpValidity'] = $phpValidity;

                                $col['status']     = $col['mpValidity'] == 'valid'	// The value corresponds to the TWBS text classes and is used for the colour representation of the validity of the measured values
                                    ? 'success'
                                    : ($col['mpValidity'] == 'conditionally_valid'
                                        ? 'warning'
                                        : ($col['mpValidity'] == 'invalid'
                                            ? 'danger'
                                            : 'NULL')
                                    );
                            }
                        }
                        // mpNominal is any other kind of information (free text) that must not be evaluated.
                        else
                        {
                            $col['status'] = "NULL";
                        }

                        // Inject timestamp.
                        $col['timestamp'] = sprintf('%s %s',
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_DATE),
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_TIME)
                        );

                        return true;
                    });

                    return true;
                });

                $formData->procMeasurementData = $measuredData;
                //echo "<pre>";print_r($formData->procMeasurementData); echo $procID;exit;
                // NEW: as of 2023-04-29 measuring definitions are passed along with the measured data for the dataTypes being available
                //echo "<pre>";print_r($formData->procMeasurementData); echo $procID;exit;

                $measuredDataStored = $this->storeEmptyMeasuredData(
                    $partID,
                    $procID,
                    $formData->procMeasurementData,
                    $this->getInstance('article', ['language' => $this->language])->getDefinedMeasuringPoints($exists->get('artID'))
                );


                // END
            }else{
                $measuredData = array_filter($measuredData, function(&$collection) use(&$params)
                {
                    $collection = array_filter($collection, function(&$arr) use(&$params)
                    {
                        // Skip table row if user did not input something.
                        if (!ArrayHelper::getValue($arr, 'mpInput'))
                        {
                            return false;
                        }

                        $arr['timestamp'] = sprintf('%s %s',
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_DATE),
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_TIME)
                        );

                        return true;
                    });

                    return count($collection) > 0;
                });

                // Helper function to calculate mpInput validity.
                // TODO - duplicate code (see line 282) ... outsource into object function
                $calculateMeasurementResultValidity = function(string $mpValue, array $mpDefinition, float $mpToleranceFactor) use (&$user): string	// in function storeTrackingData()
                {
                    $isValid = $isConditionallyValid = false;

                    $mpValue          = floatval($mpValue);
                    $mpNominal        = ArrayHelper::getValue($mpDefinition, 'mpNominal',  0, 'FLOAT');
                    $mpLowerTolerance = ArrayHelper::getValue($mpDefinition, 'mpLowerTol', 0, 'FLOAT');
                    $mpUpperTolerance = ArrayHelper::getValue($mpDefinition, 'mpUpperTol', 0, 'FLOAT');
                    $mpLowerLimit     = $mpNominal - $mpLowerTolerance;
                    $mpUpperLimit     = $mpNominal + $mpUpperTolerance;

                    // 1st validity calculation based on defined upper and lower tolerances.
                    // CHANGED on 2023-06-13, because the previous calculation appears to be wrong. Now the code is identical to the JavaScript code.
                    $isValid = ( (($mpNominal - $mpLowerTolerance) <= $mpValue) && ($mpValue <= ($mpNominal + $mpUpperTolerance)) );

                    // The measured value is invalid, but it may be conditionally valid. Do a 2nd calc.
                    if (!$isValid)
                    {
                        // Last chance that the input value is valid.
                        $mpLowerLimit     = $mpNominal - ($mpToleranceFactor * $mpLowerTolerance);
                        $mpUpperLimit     = $mpNominal + ($mpToleranceFactor * $mpUpperTolerance);

                        // CHANGED on 2023-06-13, because the previous calculation appears to be wrong. Now the code is identical to the JavaScript code.
                        $isConditionallyValid = ( (!$isValid) && ( (($mpNominal - ($mpLowerTolerance * $mpToleranceFactor)) <= $mpValue) && ($mpValue <= ($mpNominal + ($mpUpperTolerance * $mpToleranceFactor))) ) );
                    }

                    return ($isValid) ? 'valid' : ($isConditionallyValid ? 'conditionally_valid' : 'invalid');
                };

                // Generate and add timestamp column using timestamp from tracking data stored.
                array_walk($measuredData, function(&$arr) use(&$user, &$params, &$calculateMeasurementResultValidity)
                {
                    array_walk($arr, function(&$col) use(&$user, &$params, &$calculateMeasurementResultValidity)
                    {
                        // Evaluate user input validity and status.
                        // mpNominal is a boolean value.
                        if (in_array($col['mpNominal'], ['false', 'true']))
                        {
                            $col['mpValidity'] = $col['mpInput'] == $col['mpNominal'] ? 'valid' : 'invalid';

                            $col['status']     = $col['mpValidity'] == 'valid' ? 'success' : "NULL";	// The value corresponds to the TWBS text classes and
                                                                                                        // is used for the colour representation of the validity of the measured values
                        }

                        // mpNominal is a numeric value.
                        if (is_numeric($col['mpNominal']))
                        {
                            if ($col['mpInput'] == $col['mpNominal'])
                            {
                                $col['mpValidity'] = 'valid';

                                $col['status']     = 'success';		// The value corresponds to the TWBS text classes and
                                                                    // is used for the colour representation of the validity of the measured values
                            }
                            else
                            {
                                // Dump validity that was calculated via JavaScript while the user put the data in.
                                $jsValidity = $col['mpValidity'];

                                $phpValidity = $calculateMeasurementResultValidity(
                                    $col['mpInput'],
                                    [
                                            'mpNominal'  => $col['mpNominal' ],
                                        'mpLowerTol' => $col['mpLowerTol'],
                                        'mpUpperTol' => $col['mpUpperTol']
                                    ],
                                    $col['mpToleranceFactor']
                                );

                                $col['mpValidity'] = $phpValidity;

                                $col['status']     = $col['mpValidity'] == 'valid'	// The value corresponds to the TWBS text classes and is used for the colour representation of the validity of the measured values
                                ? 'success'
                                : ($col['mpValidity'] == 'conditionally_valid'
                                    ? 'warning'
                                    : ($col['mpValidity'] == 'invalid'
                                        ? 'danger'
                                        : 'NULL')
                                    );
                            }
                        }
                        // mpNominal is any other kind of information (free text) that must not be evaluated.
                        else
                        {
                            $col['status'] = "NULL";
                        }

                        // Inject timestamp.
                        $col['timestamp'] = sprintf('%s %s',
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_DATE),
                            ArrayHelper::getValue($params, Techparams::STATIC_TECHPARAM_TIME)
                        );

                        return true;
                    });

                    return true;
                });

                $formData->procMeasurementData = $measuredData;

                // NEW: as of 2023-04-29 measuring definitions are passed along with the measured data for the dataTypes being available
                $measuredDataStored = $this->storeMeasuredData(
                    $partID,
                    $procID,
                    $formData->procMeasurementData,
                    $this->getInstance('article', ['language' => $this->language])->getDefinedMeasuringPoints($exists->get('artID'))
                );
                // END
            }
        }
        /*if ($workingdir=='part' && $workinglayout == 'edit' && $isAutoTrack == 1) {

            if(empty($formData->procMeasurementData)) {
                $isAutoTrack = isset($formData->at) && $formData->at;  // AutoTrack flag
                $now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

                $trueboolquerydb = $db->getQuery(true)
                    ->from($db->qn('article_process_mp_definition', 'apmd'))
                    //->select($db->qn('apmd.mp'))
                    ->select([
                        $db->qn('apmd.mp'),
                        $db->qn('apmd.mpDatatype')  // Select mpDatatype as well
                    ])
                    ->where($db->qn('apmd.artID') . ' = ' . $db->q(trim($formData->artiID)))
                    ->where($db->qn('apmd.procID') . ' = ' . $db->q(trim($formData->pid)));

                $tbResult = $db->setQuery($trueboolquerydb)->loadAssocList('mp');
                $keyData = array_keys($tbResult);  // Measurement points to track

                $checkIfDataExist = $db->getQuery(true)
                    ->from($db->qn('part_process_mp_tracking_count', 'ppmt'))
                    ->select($db->qn('ppmt.mp'))
                    ->where($db->qn('ppmt.partID') . ' = ' . $db->q(trim($formData->ptid)))
                    ->where($db->qn('ppmt.procID') . ' = ' . $db->q(trim($formData->pid)));

                $ptbResult = $db->setQuery($checkIfDataExist)->loadAssocList('mp');
                $pkeyData = array_keys($ptbResult);

                if (!empty($keyData) && $isAutoTrack == 1 && empty($pkeyData)) {
                    $dateTime = $now->format('Y-m-d H:i:s');
                    $query = $db->getQuery(true)
                        ->insert($db->qn('part_process_mp_tracking_count'))
                        ->columns(
                            $db->qn([
                                'partID',
                                'procID',
                                'mp',
                                'mpNominal',
                                'mpInput',
                                'mpValidity',
                                'status',
                                'timestamp'
                            ])
                        );
                    foreach ($tbResult as $mps) {
                        $mpInput = ($mps['mpDatatype'] === 'boolval') ? 'true' : '0';
                        $query
                            ->clear('values')  // Clear values for each loop
                            ->values(implode(',', [
                                (int)$formData->ptid,
                                $db->q($formData->pid),
                                $db->q($mps['mp']),        // Measurement point
                                $db->q('0'),         // mpNominal
                                $db->q($mpInput),         // mpInput
                                $db->q('valid'),     // mpValidity
                                $db->q('success'),   // status
                                $db->q($dateTime)    // timestamp
                            ]));
                        //echo $query->dump();
                        echo "<pre>SQL Query to Execute: " . $query . "</pre>";
                        // Execute the query to insert the data
                        $db->setQuery($query)->execute();
                    }
                }
                //exit;
            }
        }*/
		if ($isError)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_DATABASE_SAVE_PROCESS_TECH_PARAMS_TEXT', $this->language)
			]);

			// return false;
			$partID = -16;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $partID;
	}

	//@todo - implement
	protected function deleteTrackingData(int $partID, int $procID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// return $partID;
	}

	//@todo - implement
	protected function deleteMeasuredData(int $partID)
	{}

	protected function registerPartsForBooking(array $partIDs = [], array $procIDs = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Stop right here if no parts or no processes were passed.
		if (!count($partIDs) || !count($procIDs))
		{
			return null;
		}
		else
		{
			// Sanitize input data.
			$partIDs = array_map('intval', $partIDs);
			$procIDs = array_map('intval', $procIDs);
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

		// Store parts to be booked.
		$tuples = [];

		// Prepare artID<-->procID tuples.
		array_walk($partIDs, function($partID) use(&$procIDs, &$tuples)
		{
			array_walk($procIDs, function($procID) use(&$partID, &$tuples)
			{
				$tuples[]  = (int) $partID . ',' . (int) $procID;
			});
		});

		$query = $db->getQuery(true)
		->insert($db->qn('part_process_unbooked'))
		->columns(
			$db->qn([
				'partID',
				'procID'
			])
		)
		->values($tuples);

		// Change statement to force insertion via 'REPLACE'.
		// This hack is necessary, because the Joomla database driver has no implementation for this.
		$query = str_ireplace('INSERT INTO', 'REPLACE INTO', $query->dump());

		// Clean query string (strip line break as well as leading and trailing whitespace - OR THE QUERY WILL FAIL !!!)
		$query = preg_replace('/[\r\n]/i',  ' ', $query);
		$query = preg_replace('/\s{2,}/i', ' ', $query);
		$query = trim($query);								// This alone DOES NOT SUFFICE !!!

		/* The next clean-up step is mandatory - DO NOT DELETE THIS CODE!
		 *
		 * Previously just passing the query string caused the JDatabaseDriver to fail execution.
		 * It turned out that although {@link https://www.php.net/manual/de/function.trim.php}
		 * was applied there was leading and trailing whitespace that caused the JDatabaseDriver to fail.
		 *
		 * Only the next step can fix that.
		 */
		preg_match('/REPLACE.*\)/i', $query, $matches);
		$query = current($matches);

		// Execute query.
		try
		{
			if (strlen($query) && count($tuples))
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
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

    public function getOriginalDrawing(int $artProcID, int $partID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        $artID = $db->getQuery(true)
            ->from($db->qn('parts'))
            ->select(
                $db->qn([
                    'artID'
                ])
            )
            ->where($db->qn('partID')       . ' = '  . $db->quote($partID));
        $articleID = $db->setQuery($artID)->loadAssocList();

        $query = $db->getQuery(true)
            ->from($db->qn('article_process'))
            ->select(
                $db->qn([
                    'drawing'
                ])
            )
            ->where($db->qn('artID') . ' = ' . $db->quote($articleID[0]['artID']))
            ->where($db->qn('procID') . ' = ' . $db->quote($artProcID));
        try {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function viewPartParams(int $artProcID, int $partID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();
        /*$artID = $db->getQuery(true)
            ->from($db->qn('parts'))
            ->select(
                $db->qn([
                    'artID'
                ])
            )
            ->where($db->qn('partID')       . ' = '  . $db->quote('374011'));
        $articleID = $db->setQuery($artID)->loadAssocList();
        //$articleIDs = array_column($articleID, 'artID');
        echo $querssy = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition', 'amp'))
            ->leftJoin(
                $db->qn('part_process_mp_tracking', 'pm') . ' ON ' .
                $db->qn('amp.procID') . ' = ' . $db->qn('pm.procID') . ' AND ' .
                $db->qn('amp.mp') . ' = ' . $db->qn('pm.mp') . ' AND ' .
                $db->qn('pm.partID') . ' = ' . $db->quote($partID)
            )
            ->select([
                $db->qn('amp.artID'),
                $db->qn('amp.procID'),
                'COALESCE(' . $db->qn('pm.mp') . ', ' . $db->qn('amp.mp') . ') AS mp',
                $db->qn('amp.mpDescription'),
                $db->qn('amp.mpDatatype'),
                'COALESCE(' . $db->qn('pm.mpNominal') . ', ' . $db->qn('amp.mpNominal') . ') AS mpNominal',
                'COALESCE(' . $db->qn('pm.mpLowerTol') . ', ' . $db->qn('amp.mpLowerTol') . ') AS mpLowerTol',
                'COALESCE(' . $db->qn('pm.mpUpperTol') . ', ' . $db->qn('amp.mpUpperTol') . ') AS mpUpperTol',
                'COALESCE(' . $db->qn('pm.mpToleranceFactor') . ', ' . $db->qn('amp.mpToleranceFactor') . ') AS mpToleranceFactor',
                $db->qn('pm.mpInput'),
                $db->qn('pm.mpValidity')
            ])
            ->where($db->qn('amp.artID') . ' = ' . $db->quote($articleID[0]['artID']))
            ->where($db->qn('amp.procID') . ' = ' . $db->quote($artProcID));*/
        $query = $db->getQuery(true)
            ->from($db->qn('tracking'))
            ->select(
                $db->qn([
                    'paramID',
                    'paramValue',
                    'timestamp'
                ])
            )
            ->where($db->qn('procID')       . ' = '  . $artProcID)
            ->where($db->qn('partID')       . ' = '  . $partID);
        try {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function viewParamsNames($langId,$paramID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

          $query = $db->getQuery(true)
            ->from($db->qn('techparameters'))
            ->select(
                $db->qn([
                    'paramID',
                    'name'
                ])
            )
              ->where($db->qn('paramID') . ' IN (' . $paramID . ')')
            ->where($db->qn('language')       . ' = '  . $db->quote($langId));
        try {
            $artNum = $db->setQuery($query)->loadAssocList('paramID');
        }
        catch (Exception $e) {
           /* Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $artNum = null;*/
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }

    public function viewPartMeasuredData($artProcID,$partID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $artID = $db->getQuery(true)
            ->from($db->qn('parts'))
            ->select(
                $db->qn([
                    'artID'
                ])
            )
            ->where($db->qn('partID')       . ' = '  . $db->quote($partID));
        $articleID = $db->setQuery($artID)->loadAssocList();
        $query = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition', 'amp'))
            ->leftJoin(
                $db->qn('part_process_mp_tracking', 'pm') . ' ON ' .
                $db->qn('amp.procID') . ' = ' . $db->qn('pm.procID') . ' AND ' .
                $db->qn('amp.mp') . ' = ' . $db->qn('pm.mp') . ' AND ' .
                $db->qn('pm.partID') . ' = ' . $db->quote($partID)
            )
            ->select([
                $db->qn('amp.artID'),
                $db->qn('amp.procID'),
                'COALESCE(' . $db->qn('pm.mp') . ', ' . $db->qn('amp.mp') . ') AS mp',
                $db->qn('amp.mpDescription'),
                $db->qn('amp.mpDatatype'),
                'COALESCE(' . $db->qn('pm.mpNominal') . ', ' . $db->qn('amp.mpNominal') . ') AS mpNominal',
                'COALESCE(' . $db->qn('pm.mpLowerTol') . ', ' . $db->qn('amp.mpLowerTol') . ') AS mpLowerTol',
                'COALESCE(' . $db->qn('pm.mpUpperTol') . ', ' . $db->qn('amp.mpUpperTol') . ') AS mpUpperTol',
                'COALESCE(' . $db->qn('pm.mpToleranceFactor') . ', ' . $db->qn('amp.mpToleranceFactor') . ') AS mpToleranceFactor',
                $db->qn('pm.mpInput'),
                $db->qn('pm.mpValidity')
            ])
            ->where($db->qn('amp.artID') . ' = ' . $db->quote($articleID[0]['artID']))
            ->where($db->qn('amp.procID') . ' = ' . $db->quote($artProcID));

        try {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            /* Messager::setMessage([
                 'type' => 'error',
                 'text' => Text::translate($e->getMessage(), $this->language)
             ]);
             $artNum = null;*/
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function getErrorName($langId,$errID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

         $query = $db->getQuery(true)
            ->from($db->qn('errors'))
            ->select(
                $db->qn([
                    'name',
                ])
            )
            ->where($db->qn('lngID')       . ' = '  . $db->quote($langId))
            ->where($db->qn('errID')       . ' = '  . $db->quote($errID));
        try {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            /* Messager::setMessage([
                 'type' => 'error',
                 'text' => Text::translate($e->getMessage(), $this->language)
             ]);
             $artNum = null;*/
        }
        $this->closeDatabaseConnection();
        return $artNum;
    }
    public function getBandRoleAjax($procID,$partID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $getArticleID = $db->getQuery(true)
            ->from($db->qn('parts'))
            ->select(
                $db->qn([
                    'artID',
                ])
            )
            ->where($db->qn('partID')       . ' = '  . $db->quote($partID));
        $articleID = $db->setQuery($getArticleID)->loadAssocList();

        $checkForMeasurement = $db->getQuery(true)
            ->from($db->qn('part_process_mp_tracking'))
            ->select(
                $db->qn([
                    'mpInput',
                ])
            )
            ->where($db->qn('partID')       . ' = '  . $db->quote($partID))
            ->where($db->qn('procID')       . ' = '  . $db->quote($procID));
        $bandRoleThere = $db->setQuery($checkForMeasurement)->loadAssocList();

        //print_r($bandRoleThere);exit;
        try {
            //$artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e) {
            /* Messager::setMessage([
                 'type' => 'error',
                 'text' => Text::translate($e->getMessage(), $this->language)
             ]);
             $artNum = null;*/
        }
        $this->closeDatabaseConnection();
        //return $artNum;
    }
}
