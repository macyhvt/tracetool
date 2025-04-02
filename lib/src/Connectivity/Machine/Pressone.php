<?php
/* define application namespace */
namespace Nematrack\Connectivity\Machine;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use Nematrack\Connectivity\Machine;
use Nematrack\Connectivity\MachineInterface;
use Nematrack\Entity;
use Nematrack\Entity\Machine\Pressin;
use Nematrack\Factory;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Helper\JsonHelper;
use Nematrack\Model;
use RuntimeException;

/**
 * Class description
 */
class Pressone extends Machine implements MachineInterface
{
	// TODO - Implement interface
	// TODO - Implement further functionality shared by parent class

	/**
	 * @var \Nematrack\Entity\Machine\Pressin Object that maps log file properties to the related entity's properties.
	 */
	private Entity $item;

	/**
	 * @var    string  The press in process log data table name.
	 * @since  2.8
	 */
	private string $tblMachineLog = 'part_process_scf_tracking';


	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   2.8
	 */
	public function __construct(array $options = [])
	{
	}

	/**
	 * Add description...
	 *
	 * @param   array $rawData
	 *
	 * @return  bool   true on success or false on failure
	 *
	 * @throws \Exception
	 */
	public function persist(array $rawData = []) : bool
	{
		// Get instance of press in entity from received data.
		$item = $this->getItem($rawData);

		// Don't interrupt data processing to prevent data loss.
		// The machine log   m u s t   be saved no matter what!

		// Init vars.
//		$isLogSaved    = false;
		$isPartTracked = false;
		
		// These will be populated within the next 2 database queries.
		$partID = $artID = $procID = null;

		// Get database connection object.
		$dbo    = Factory::getDbo();
		$query  = $dbo->getQuery(true);
//		$ids    = [];

		// Fetch required IDs first (part ID and article ID).
		try
		{
			$ids = (array) $dbo
			->setQuery(
				$query
				->clear()
				->from($dbo->qn('parts'))
				->select($dbo->qn(['partID', 'artID']))
				->where($dbo->qn('trackingcode') . ' = ' . $dbo->q($this->item->get('code')))
				)
			->loadAssoc();
		}
		catch (Exception $e)
		{
			// TODO - translate
			throw new Exception('Failed to process log data for this article/process combination. A required information could not be found.');
		}

		// Extract fetched ids.
		extract($ids);

		// Fetch additional ID (process ID).
		try
		{
			$dbo
			->setQuery(
				$query
				->clear()
				->from($dbo->qn('processes'))
				->select($dbo->qn('procID'))
				->where($dbo->qn('abbreviation') . ' = ' . $dbo->q($this->item->get('process')))
			);

			$ids = (array) $dbo->loadAssoc();
		}
		catch (Exception $e)
		{
			// TODO - translate
			throw new Exception('Failed to process log data for this article/process combination. A required information could not be found.');
		}

		// Extract fetched id.
		extract($ids);

		/** DUPE check the log data. */

		// Check if press-in for this part/press-fit combination was previously tracked.
		try
		{
			$isDuplicate = $dbo
			->setQuery(
				$query
				->clear()
				->from($dbo->qn($this->tblMachineLog))
				->select($dbo->qn('partID'))
				->where($dbo->qn('partID')   . ' = ' . $dbo->q($partID))
				->where($dbo->qn('procID')   . ' = ' . $dbo->q($procID))
				->where($dbo->qn('config')   . ' = ' . $dbo->q($this->item->get('config')))
				->where($dbo->qn('pressFit') . ' = ' . $dbo->q($this->item->get('pressFit')))
			)
			->loadResult();
		}
		catch (Exception $e)
		{
			// TODO - translate
			throw new Exception('Failed to process log data for this article/process combination. DUPE check error.');
		}

		// If this article/process combination was previously tracked, stop right here.
		if ($isDuplicate)
		{
			// TODO - translate
			throw new Exception('Already tracked.');
		}

		// Prepare log data for storing.
//		$analysisDataJSON = $measuredDataJSON = '';

		// JSON-encode analysis data
		try
		{
			$analysisDataJSON = json_encode($this->item->get('analysis'), JSON_FORCE_OBJECT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			// TODO - translate
			throw new Exception(implode(' ', [
					'Failed to save log data for this article/process combination.'.
					'JSON-encoding error.'
				])
			);
		}

		// JSON-encode measured data
		try
		{
			$measuredDataJSON = json_encode($this->item->get('measuredData'), JSON_FORCE_OBJECT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			// TODO - translate
			throw new Exception(implode(' ', [
					'Failed to save log data for this article/process combination.'.
					'JSON-encoding error.'
				])
			);
		}

		// Prepare data to be stored into the press in process log data table.
		$logData = [
			'logID'        => "NULL",
			'partID'       => $dbo->q($partID),
			'procID'       => (isset($procID) ? $dbo->q($procID) : "NULL"),
			'operator'     => $dbo->q($this->item->get('operator')),
			'machine'      => $dbo->q($this->item->get('machine')),
			'config'       => $dbo->q($this->item->get('config')),
			'pressFit'     => $dbo->q($this->item->get('pressFit')),
			'batch'        => $dbo->q($this->item->get('batch')),
			'measurement'  => $dbo->q($this->item->get('measurement')),
			'unit'         => $dbo->q($this->item->get('unit')),
			'analysis'     => $dbo->q($analysisDataJSON),
			'measuredData' => $dbo->q($measuredDataJSON),
			'timestamp'    => "NULL",    // Set by MySQL, but required to satisfy columns count.
		];

		/** Store machine log no matter what to prevent data loss. */

		try
		{
			$isLogSaved = $this->saveLog($logData);
		}
		catch (Exception $e)
		{
			// TODO - translate
			if (!preg_match('/^duplicate entry/i', $e->getMessage()))
			{
				throw new Exception(
					sprintf('Failed to save log data for this article/process combination for the following reason: %s', $e->getMessage())
				);
			}
			// We don't care for duplicate entry error. Hence, we ensure that the flag variable is always true for the return value.
			else
			{
				// TODO - translate
				throw new Exception('This part/process/press-fit combination was previously tracked.');
			}
		}

		/** Track part/process. */

		if ($partID && $procID && $artID)
		{
			try
			{
				// Add additional information needed to track this part/process.
				$item->__set('partID',   $partID);
				$item->__set('artID',    $artID);
				$item->__set('procID',   $procID);
				$item->__set('logSaved', $isLogSaved);

				$isPartTracked = Model::getInstance('part')->handlePressinData($this->item);
			}
			catch (RuntimeException $e)
			{
				// TODO - translate
				throw new Exception(
					sprintf('Failed to track process for the following reason: %s', $e->getMessage())
				);
			}
		}

//		return $isLogSaved && $isPartTracked;
		return $isPartTracked;
//		return true;
	}


	/**
	 * Returns a {@link \Nematrack\Entity\Machine\Pressin} object always creating it if doesn't exist.
	 *
	 * @param   array $rawData  An object of data to be bound
	 *
	 * @return  \Nematrack\Entity\Machine\Pressin
	 *
	 * @throws  \Exception
	 *
	 * @since   2.8
	 */
	protected function getItem(array $rawData = []) : Pressin
	{
		if (!isset($this->item) || !is_a($this->item, 'Nematrack\Entity\Machine\Pressin'))
		{
			$this->item = new Pressin;
			$this->item->bind(
				$this->processRawData($rawData)
			);
		}

		return $this->item;
	}

	/**
	 * Add description...
	 *
	 * @param   $rawData
	 *
	 * @return  array
	 *
	 * @since   2.8
	 */
	private function processRawData($rawData) : array
	{
		$logData = $this->mapLogData($rawData);

		if (isset($logData['logdata']))
		{
			$logData = array_merge($logData, $this->analyseMeasuringData($logData['measuredData']));
		}

		return $logData;
	}

	/**
	 * Takes an input object, extracts its data thereby converting JSON-data into PHP-data types.
	 *
	 * @param   array $data  The input data object
	 *
	 * @return  array The extracted data
	 *
	 * @since   2.8
	 */
	private function mapLogData(array $data) : array
	{
		$result = [];

		foreach ($data as $key => $value)
		{
			if (JsonHelper::isValidJSON($value))
			{
				$value = (is_string($value)) ? trim($value) : $value;

				try
				{
					$value = json_decode($value, null, 512, JSON_THROW_ON_ERROR);
				}
				catch (JsonException $e)
				{
					// TODO - log error
					// Leave $value unchanged
				}
			}

			$result[$key] = $value;
		}

		if (isset($result['logdata']))
		{
			$result['measuredData'] = $this->mapMeasuringData($result['logdata']);
		}

		return $result;
	}

	/**
	 * Takes a list of indexed arrays and maps it to a list of associated arrays (measuring points)
	 * thereby mapping values to Force/Distance accordingly.
	 *
	 * @param   array $data  The input data object
	 *
	 * @return  array The mapped data
	 *
	 * @since   2.8
	 */
	private function mapMeasuringData(array &$data) : array
	{
		$tmp = [];

		foreach ($data as $keyValue)
		{
			array_push($tmp, [
				'force'    => current($keyValue),
				'distance' => end($keyValue)
			]);
		}

		$data = $tmp;

		unset($tmp);

		return $data;
	}

	/**
	 * Takes an associative array of measuring points and calculates analysis and tracking information.
	 *
	 * @param   array $data  The input data object
	 *
	 * @return  array[] The calculated analysis and tracking data
	 *
	 * @since   2.8
	 */
	private function analyseMeasuringData(array $data) : array
	{
		// Helper function.
		$calculateFmax = function($Fmax, $FmaxGLMW5, $FmaxGLMW20, $similarity) : int
		{
			$result = 0;

			try
			{
				$result = @((($FmaxGLMW20 / $FmaxGLMW5) * 100) < $similarity ? $FmaxGLMW20 : (($FmaxGLMW5 / $Fmax) * 100) < $similarity) ? $FmaxGLMW5 : $Fmax;
			}
			catch (Exception $e)
			{
				// Do nothing
			}

			return $result;
		};

		$distanceDeltas = $this->calculateDistances(array_column($data,     'distance'));
		$forceGLMW5     = $this->calculateMovingAverage(array_column($data, 'force'), 5);
		$forceGLMW20    = $this->calculateMovingAverage(array_column($data, 'force'), 20, 4);

		$analysis                = [];
		$analysis['DistanceMax'] = (count($distanceDeltas)) ? round(max($distanceDeltas), 2) : 0;
		$analysis['Fmax']        = (count(array_column($data, 'force'))) ? max(array_column($data, 'force')) : 0;
		$analysis['FmaxGLMW5']   = (count($distanceDeltas)) ? round(max($forceGLMW5),     2) : 0;
		$analysis['FmaxGLMW20']  = (count($distanceDeltas)) ? round(max($forceGLMW20),    4) : 0;
		$analysis['Similarity']  = 99.0;   // percentage

		$Fmax       = ArrayHelper::getValue($analysis, 'Fmax',       0, 'INT');
		$FmaxGLMW5  = ArrayHelper::getValue($analysis, 'FmaxGLMW5',  0, 'FLOAT');
		$FmaxGLMW20 = ArrayHelper::getValue($analysis, 'FmaxGLMW20', 0, 'FLOAT');
		$Similarity = ArrayHelper::getValue($analysis, 'Similarity', 0, 'FLOAT');

		$Fmax = $calculateFmax($Fmax, $FmaxGLMW5, $FmaxGLMW20, $Similarity);
		$Smax = ArrayHelper::getValue($analysis, 'DistanceMax', 0, 'FLOAT');

		$tracking      = [];
		$tracking['F'] = ($Fmax > 0) ? $Fmax : 0;
		$tracking['S'] = ($Smax > 0) ? $Smax : 0;

		return [
			'analysis' => $analysis,
			'tracking' => $tracking,
		];
	}

	/**
	 * Takes a list of press-in distance values and calculates for every value its difference to the very first value.
	 *
	 * @param   array $data  The input data object
	 *
	 * @return  array The calculated deltas
	 *
	 * @since   2.8
	 */
	private function calculateDistances(array $data) : array
	{
		// Convert negative values to positive values.
		array_walk($data, function(&$value)
		{
//			$value = $value < 0 ? ($value * -1) : $value;
			$value = abs($value);
		});

		$reference = current($data);
		$list      = [];

		array_walk($data, function($value) use(&$reference, &$list)
		{
			array_push($list, $value - $reference);
		});

		return $list;
	}

	/**
	 * Takes a list of values and calculates the moving average as per defined grouping size.
	 *
	 * @param   array $data      The input data object
	 * @param   int   $offset    The grouping size
	 * @param   int   $decimals  The number of decimals the return value shall have
	 *
	 * @return  array
	 *
	 * @since   2.8
	 */
	private function calculateMovingAverage(array $data, int $offset, int $decimals = 2) : array
	{
		$cnt  = count($data);
		$list = [];

		for ($i = 0, $j = $offset; $i < $cnt; $i += 1)
		{
			$slice = array_slice($data, $i, $j);

			if (count($slice) == $offset)
			{
				$avg = \Nematrack\Helper\ArrayHelper::average($slice, $decimals);

				array_push($list, $avg);
			}
		}

		return $list;
	}

	/**
	 * Add description...
	 *
	 * @param   array $logData
	 *
	 * @return  bool* @return  bool   true on success or false on failure
	 *
	 * @throws  \RuntimeException
	 */
	private function saveLog(array $logData) : bool
	{
		// Init return value.
		$stored = false;

		// Get database connection object.
		$dbo    = Factory::getDbo();

		//  Build query.
		$query  = $dbo->getQuery(true)
		->insert($dbo->qn($this->tblMachineLog))
		->columns($dbo->qn(DatabaseHelper::getTableColumns($this->tblMachineLog)))
		->values([
			implode(',', $logData)
		]);

		// Execute query.
		try
		{
			if (count($logData))
			{
				$stored = $dbo
				->setQuery($query)
				->execute();
			}
		}
		catch(Exception $e)
		{
			throw new RuntimeException($e->getMessage());
		}

		return $stored;
	}
}
