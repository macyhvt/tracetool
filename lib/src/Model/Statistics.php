<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Utilities\ArrayHelper;
use  \Entity;
use  \Helper\DatabaseHelper;
use  \Messager;
use  \Model;
use  \Model\Lizt as ListModel;
use  \Text;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use Throwable;
use function array_key_exists;
use function array_shift;
use function array_walk;
use function is_a;
use function is_null;
use function is_string;

/**
 * Class description
 */
class Statistics extends Model
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

	public function fetchMonitorDataTables(
		int    $proID = null,
		string $fromDate  = '',
		string $toDate = '',
		array  $processAbbreviations = [],
		array  $processIDs = []
	) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init shorthand to database query object.
		$query      = $db->getQuery(true);

		// Prepare lookup date interval.
		$timeZone   = new DateTimeZone(FTKRULE_TIMEZONE);
		$dateFormat = 'Y-m-d';
		$fromDate   = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate   = date_create($fromDate, $timeZone);
		$fromDate->setTime(0,0); // Necessary to ensure this date is included
		$toDate     = (trim($toDate) == '' ? 'NOW' : $toDate);
		$toDate     = date_create($toDate, $timeZone);
		$toDate->setTime(23,59,59); // Necessary to ensure this date is included

		$dateRange  = new DatePeriod($fromDate, new DateInterval('P1D'), $toDate);

		// Init vars.
		$arrResult            = [];
		$arrResultAccumulated = [];
		$separator            = '|';   // Use semicolon for CSV

		// Sanitize abbreviations list.
		$processAbbreviations = array_map(function($elem) { return trim(mb_strtoupper('' . $elem)); }, $processAbbreviations);
		$processGroups        = array_map(function($elem) { return trim(preg_replace('/\d+/i', '', mb_strtoupper('' . $elem))); }, $processAbbreviations);
		$processGroups        = array_flip(array_flip($processGroups)); // Workaround to make array unique without changing value ordering (see {@link https://stackoverflow.com/questions/5350080/array-unique-without-sorting/5350142})
		$totalsAccumulated    = array_combine($processGroups, array_fill(0, count($processGroups), 0)); // Used to accumulate columns and append to $arrResult as last info line

		// Build sub-query.
		$sub = clone($query)
		->clear()
		->from($db->qn('processes', 'pcs'))
		->select($db->qn('procID'))
		->where($db->qn('pcs.abbreviation') . ' REGEXP "^(' . implode($separator, $processAbbreviations) . ')\d?"');

		try
		{
			foreach ($dateRange as $date)
			{
				$totals = array_combine($processGroups, array_fill(0, count($processGroups), 0));   // 1 column per process group (will consider mrk1 and mrk2 as mrk)

				// Build query.
				$query
				->clear()
				->from($db->qn('project_monitoring_data', 'pmda'))
				->leftJoin($db->qn('processes', 'pcs') . ' ON ' . $db->qn('pcs.procID') . ' = ' . $db->qn('pmda.procID'))
				->select($db->qn('pmda.date'))
				->select('UPPER(' . $db->qn('pcs.abbreviation') . ') AS ' . $db->qn('abbreviation'))
				->select('UPPER(REPLACE(REPLACE(' . $db->qn('pcs.abbreviation') . ', 1, ""), 2, "")) AS ' . $db->qn('group'))
				->select($db->qn('pmda.total'))
				->where($db->qn('pmda.proID')  . ' = ' . (int) $proID)
		        ->where($db->qn('pmda.date')   . ' = ' . $db->q($date->format($dateFormat)))
				->where($db->qn('pmda.procID') . ' IN( ' . $sub . ')')
				->order($db->qn('pmda.date'))
				->order($db->qn('pmda.procID'));

				$rs = (array) $db->setQuery($query)->loadAssocList();

				array_walk($rs, function(&$row) use(&$totalsAccumulated, &$totals, &$arrResult, &$arrResultAccumulated)
				{
					$row = (array) $row;

					$groupToIncrement = ArrayHelper::getValue($row, 'group', '', 'STRING');
					$valueToAddUp     = ArrayHelper::getValue($row, 'total', 0,  'INT');

					$totals           [$groupToIncrement] = (int) $totals[$groupToIncrement] + $valueToAddUp;
//					$totalsAccumulated[$groupToIncrement] = (int) $totalsAccumulated[$groupToIncrement] + $valueToAddUp;
				});

				reset($rs);

				// Format alignment of numbers for harmonic display in the browser window.
				// NOTE: This is not required when data shall not be displayed but be further processed.
				/*$totals = array_map(function($value)
				{
					if ((int) $value > 0)
					{
						switch (strlen((string) $value))
						{
							case 5 :
								return $value;

							case 4 :
								return ' ' . $value;

							case 3 :
								return '  ' . $value;

							case 2:
								return '   ' . $value;

							case 1:
								return '    ' . $value;
						}
					}

					return '     ';
				}, $totals);*/
				$arrResult[$date->format($dateFormat)] = sprintf('%s', implode($separator, $totals) );

				/*$totalsAccumulated = array_map(function($value)
				{
					if ((int) $value > 0)
					{
						switch (strlen((string) $value))
						{
							case 5 :
								return $value;

							case 4 :
								return ' ' . $value;

							case 3 :
								return '  ' . $value;

							case 2:
								return '   ' . $value;

							case 1:
								return '    ' . $value;
						}
					}

					return '     ';
				}, $totalsAccumulated);
				$arrResultAccumulated[$date->format($dateFormat)] = sprintf('%s%s%s', $separator, implode($separator, $totalsAccumulated), $separator);*/
			}

			/*// Prepend column headers.
			$arrResult = ['Date' => ' ' . sprintf('%s',
//			$arrResult = ['Date' => ' ' . sprintf('%s%s%s',
//				$separator,
				// Format alignment of column headers for harmonic display in the browser window.
				// NOTE: This is not required when data shall not be displayed but be further processed.
//				implode($separator, array_map(function($value) { return sprintf(' %s ', $value); }, $processGroups)),
				implode($separator, $processGroups),
//				$separator
			)] + $arrResult;*/
			/*$arrResultAccumulated = ['Date' => ' ' . sprintf('%s%s%s',
				$separator,
				implode($separator, array_map(function($value) { return sprintf(' %s ', $value); }, $processGroups)),
				$separator
			)] + $arrResultAccumulated;*/

			// Force numeric data (converts empty strings to 0).
//			$totalsAccumulated = array_map('intval', $totalsAccumulated);
		}
		catch (Throwable $e)
		{
			die($e->getMessage());
		}

//		$arrResult += ['Accumulate' => end($arrResultAccumulated)];

		/*return [
			'data' => $arrResult,
			'data.accumulated' => $arrResultAccumulated,
		];*/

		return $arrResult;
	}

	/**
	 * Returns for a given process and date (range) the time of the first and last tracking entry,
	 * the total trackings count and the total delay between these trackings in minutes.
	 *
	 * @param   int    $procID
	 * @param   string $fromDate  Look up date interval begin date
	 * @param   string $toDate    Look up date interval end date. Will be set to begin date if not provided to limit look up to today's date.
	 *
	 * @return  array  Array containing the datetime of the first and last tracking entry, the total trackings count and the total delay in minutes between all these trackings
	 */
	private function getProcessStats__BAK(int $procID, string $fromDate = '', string $toDate = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init vars.
		$rs = [];
		$threshold  = 15;   // Defined by S. Mathes. It defines the minutes between every tracking entry. Any value below this is not interpreted as a pause time.
		$timeouts   = 0;    // Collection of seconds between every tracking entry where the difference is > threshold
		$arrResult  = [];

		/* 1st QUERY:  Fetch IDs of all parts been tracked on the selected date */

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : new DateTime('NOW'));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		$toDate   = (trim($toDate) == '' ? $fromDate : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : new DateTime('NOW'));
		$toDate   = $toDate->format('Y-m-d 23:59:59');

		// Build first query to fetch partIDs for second query.
		$query    = $db->getQuery(true)
		->select('GROUP_CONCAT(DISTINCT '    . $db->qn('partID') . ') AS ' . $db->qn('parts'))
		->from($db->qn('tracking'))
		->where($db->qn('procID')    . ' = ' . $procID)
		->where($db->qn('paramID')   . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
		->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
		->order($db->qn('partID'));

		// Prepare lookup data for second query.
		$parts = null;

		// Execute query.
		try
		{
			// Expected formats:
			//	NULL  or   a string like '1,2,3,4,5' ready to be used by the next query below in <pre>IN()</pre> condition.
			$parts = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		/* 2nd QUERY:  For every part fetch the tracking time for the selected process */

		if (!is_null($parts))
		{
			// Build query.
			$query
			->clear()
			->select($db->qn('timestamp'))
			->from($db->qn('tracking'))
			->where($db->qn('partID')  . ' IN (' . $parts . ')')
			->where($db->qn('procID')  . ' = ' . $procID)
			->where($db->qn('paramID') . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
			->order($db->qn('timestamp'));

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadColumn();

				if ($rs)
				{
					// Calculate total break time out of working time.
					foreach ($rs as $timestamp)
					{
						$current = $timestamp;
						$next    = next($rs);

						// When $current is last array item, then $next cannot point to a follow-up item.
						// Thus <pre>$next</pre> will be Boolean false. In that case $next will be initialised with current date/time, which is wrong.
						// Hence, we check for data type prior calculation.
						if (is_string($next) && strlen($next))
						{
							// Calculating the delay between 2 dates.
							// Code borrowed with slight changes from {@link https://stackoverflow.com/a/12382882}
							$current = new DateTime($current);
							$next    = new DateTime($next);
							$delay   = $current->diff($next);

							// If delay in minutes is above threshold, sum it up.
							if ($delay->i > $threshold)
							{
								$timeouts += $delay->i;
							}
						}
					}

					// Set pointer back to first array element.
					reset($rs);

					// Calculate statistical values.
					$arrResult = [
						'first'  => current($rs),
						'last'   => end($rs),
						'total'  => count($rs),
						'breaks' => ($timeouts <= $threshold ? null : $timeouts)
					];
				}
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);
			}
		}

		// Free memory.
		unset($rs);

		// Close connection.
		$this->closeDatabaseConnection();

		return $arrResult;
	}
	public function getProcessStats() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', get_class($this->getInstance('processes')))));
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter   = ArrayHelper::getValue($args, 'filter',  ListModel::FILTER_ALL);
		$id       = ArrayHelper::getValue($args, $pkName);
		$id       = (is_null($id)) ? null : (int) $id;
		$dateFrom = ArrayHelper::getValue($args, 'dateFrom', 'NOW', 'STRING');		// fall back to today
		$dateTo   = ArrayHelper::getValue($args, 'dateTo',   'NOW', 'STRING');		// fall back to today
		$timeFrom = ArrayHelper::getValue($args, 'timeFrom', '00:00:01', 'STRING');	// fall back to last midnight
		$timeTo   = ArrayHelper::getValue($args, 'timeTo',   '23:59:59', 'STRING');	// fall back to this midnight
		$order    = ArrayHelper::getValue($args, 'order');
		$sort     = ArrayHelper::getValue($args, 'sort');

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

		// Init vars.
		$bad = [];   // bad parts stack
		$rs  = [];   // query resultset
		$threshold      = 15;   // Defined by S. Mathes. It defines the minutes between every tracking entry. Any value below this is not interpreted as a pause time.
		$thresholdLimit = 240;	// Defined by S. Mathes on 2022-06-13. It defines the upper boundary up to which idle time is evaluated and included. Anything above is ignored.
		$timeouts       = 0;    // Collection of seconds between every tracking entry where the difference is > threshold
		$arrResult      = [];

		/* 1st QUERY:  Fetch IDs of all parts been tracked on the selected date */

		// Prepare lookup date interval.
		$fromDate = date_create(sprintf('%s %s', $dateFrom, $timeFrom), new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');
		$toDate   = date_create(sprintf('%s %s', $dateTo,   $timeTo),   new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');

		// Build first query to fetch partIDs for second query.
		if(isset($_GET['filter'])){
            $filter=$_GET['filter'];
        }else{
            $filter=0;
        }

        if($filter == 0){
            $query    = $db->getQuery(true)
                ->from($db->qn('tracking'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn($pkName)          . ' = ' . $id)
                ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
                //->where($db->qn('paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_DATE . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')

                // ->where($db->qn('paramValue')        . ' = ' . $db->q("NEMATECH Kft."))

                ->where($db->qn('timestamp')      . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->order($order ?? $db->qn('partID'));
        }elseif ($filter == 112){
            $query    = $db->getQuery(true)
                ->from($db->qn('tracking'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn($pkName)          . ' = ' . $id)

                ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)
                ->where($db->qn('paramValue')        . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->where($db->qn('timestamp')      . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->order($order ?? $db->qn('partID'));
            $partsr = $db->setQuery($query)->loadResult();

            if(!empty($partsr)) {
                $query = $db->getQuery(true)
                    ->from($db->qn('tracking'))
                    ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))
                    ->where($db->qn($pkName) . ' = ' . $id)
                    ->where($db->qn('partID') . ' IN (' . $partsr . ')')
                    ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
                    //->where($db->qn('paramID') . ' IN (' . Techparams::STATIC_TECHPARAM_DATE . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                    //->where($db->qn('paramValue') . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                    ->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                    //->group($db->qn('partID'))
                    ->order($order ?? $db->qn('partID'));
            }
        }elseif ($filter == 111){
            $query    = $db->getQuery(true)
                ->from($db->qn('tracking'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn($pkName)          . ' = ' . $id)

                ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)
                ->where($db->qn('paramValue')        . ' = ' . $db->q("NEMATECH Kft."))
                ->where($db->qn('timestamp')      . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->order($order ?? $db->qn('partID'));
            $partsr = $db->setQuery($query)->loadResult();

            if(!empty($partsr)) {
                $query = $db->getQuery(true)
                    ->from($db->qn('tracking'))
                    ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))
                    ->where($db->qn($pkName) . ' = ' . $id)
                    ->where($db->qn('partID') . ' IN (' . $partsr . ')')
                    ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
                    //->where($db->qn('paramID') . ' IN (' . Techparams::STATIC_TECHPARAM_DATE . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                    //->where($db->qn('paramValue') . ' = ' . $db->q("NEMATECH Kft."))
                    ->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                    //->group($db->qn('partID'))
                    ->order($order ?? $db->qn('partID'));
            }
        }elseif ($filter == 113){
            /*echo "<pre>";echo */
            $query    = $db->getQuery(true)
                ->from($db->qn('tracking'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn($pkName)          . ' = ' . $id)

                ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)
                ->where($db->qn('paramValue')        . ' = ' . $db->q("NEMECTEK TOW"))
                ->where($db->qn('timestamp')      . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->order($order ?? $db->qn('partID'));
            $partsr = $db->setQuery($query)->loadResult();

            if(!empty($partsr)) {
                $query = $db->getQuery(true)
                    ->from($db->qn('tracking'))
                    ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))
                    ->where($db->qn($pkName) . ' = ' . $id)
                    ->where($db->qn('partID') . ' IN (' . $partsr . ')')
                    ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
                    //->where($db->qn('paramID') . ' IN (' . Techparams::STATIC_TECHPARAM_DATE . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                    //->where($db->qn('paramValue') . ' = ' . $db->q("NEMECTEK TOW"))
                    ->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                    //->group($db->qn('partID'))
                    ->order($order ?? $db->qn('partID'));
            }
        }else{
            $query    = $db->getQuery(true)
                ->from($db->qn('tracking'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn($pkName)          . ' = ' . $id)
                ->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
                //->where($db->qn('paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_DATE . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')

                // ->where($db->qn('paramValue')        . ' = ' . $db->q("NEMATECH Kft."))

                ->where($db->qn('timestamp')      . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->order($order ?? $db->qn('partID'));
        }
		/*$query    = $db->getQuery(true)
		->from($db->qn('tracking'))
		->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))
		->where($db->qn($pkName)          . ' = ' . $id)
		->where($db->qn('paramID')        . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
		->where($db->qn('timestamp')      . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
		->order($order ?? $db->qn('partID'));*/

		// Prepare lookup data for second query.
		$parts = null;

		// Execute query.
		try
		{
			// Expected formats:
			//	NULL  or   a string like '1,2,3,4,5' ready to be used by the next query below in <pre>IN()</pre> condition.
			$parts = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		if (empty($parts))
		{
			return $rs;
		}

		/* 2nd QUERY:  For every part fetch the tracking time for the selected process */

		if (!is_null($parts))
		{
			// Build query.
			$query
			->clear()
			->from($db->qn('tracking'))
			// Concat error status with timestamp for bad parts calculation further below.
			->select('CONCAT_WS("|", ' . $db->qn('paramValue') . ', ' . $db->qn('timestamp') . ') AS ' . $db->qn('state_timestamp'))
			->where($db->qn('partID')  . ' IN (' . $parts . ')')
			->where($db->qn($pkName)     . ' = ' . $id)
			->where($db->qn('paramID') . ' = ' . Techparams::STATIC_TECHPARAM_ERROR)    // switched from STATIC_TECHPARAM_DATE to STATIC_TECHPARAM_ERROR
			->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
			->order($order ?? $db->qn('timestamp'));

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadColumn();

				if ($rs)
				{
					/*$query
                        ->clear()
                        ->from($db->qn('tracking'))
                        // Concat error status with timestamp for bad parts calculation further below.
                        ->select($db->qn('paramValue'))

                        ->where($db->qn('partID')  . ' IN (' . $parts . ')')
                        ->where($db->qn($pkName)     . ' = ' . $id)

                        ->where($db->qn('paramID') . ' = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)    // switched from STATIC_TECHPARAM_DATE to STATIC_TECHPARAM_ERROR
                        ->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                        ->order($order ?? $db->qn('timestamp'));
                    $rsr = $db->setQuery($query)->loadColumn();*/

					// Calculate total break time out of working time.
					foreach ($rs as $line)
					{
						$currLine = $line;
						$nextLine = next($rs);

						// When $current is last array item, then $next cannot point to a follow-up item.
						// Thus <pre>$next</pre> will be false. In that case $next will be initialised with current date/time, which is wrong.
						// Hence, we check for data type prior calculation.
						if (!is_string($nextLine) || !strlen($nextLine))
						{
							continue;
						}

						[$currCode, $currDateTime] = explode('|', $currLine);
						[$nextCode, $nextDateTime] = explode('|', $nextLine);

						// Calculating the delay between 2 dates.
						// Code borrowed with slight changes from {@link https://stackoverflow.com/a/12382882}
						// TODO - respect the new parameter $thresholdLimit
						$currDateTime = new DateTime($currDateTime);
						$nextDateTime = new DateTime($nextDateTime);
						$delay        = $currDateTime->diff($nextDateTime);

						// If delay in minutes is above threshold, sum it up.
						if ($delay->i > $threshold)
						{
							$timeouts += $delay->i;
						}

						// If current line begins with an error status code, push it to the stack for the bad parts count.
						if ($currCode)
						{
							array_push($bad, $currCode);
						}
					}

					/*foreach($rsr as $orls){
						$orglst = $orls;
					 }*/

					// Set pointer back to first array element.
					reset($rs);

					// Get first and last entry from query resultset.
					[$firstCode, $first] = explode('|', current($rs));
					[$lastCode,  $last]  = explode('|', end($rs));

					// Calculate statistical values.
					$arrResult = [
						'first'  => $first,
						'last'   => $last,
						'total'  => count($rs),
						'breaks' => ($timeouts <= $threshold ? null : $timeouts),
						'bad'    => count($bad),
						//'orgls' => $orglst
					];
				}
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);
			}
		}

		// Free memory.
		unset($rs);

		// Close connection.
		$this->closeDatabaseConnection();

		return $arrResult;
	}

	/**
	 * Add description...
	 *
	 * @param   int    $procID    The related process ID
	 * @param   string $fromDate  Look up date interval begin date
	 * @param   string $toDate    Look up date interval end date
	 *
	 * @return  array  A list of arrays indexed by article name with each array containing the article ID and the total good and bad parts counts
	 */
	private function getProcessArticles__OBSOLETE(int $procID, string $fromDate = '', string $toDate = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		/* 1st QUERY:  Fetch IDs of all parts been tracked on the selected date */

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : new DateTime('NOW'));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		$toDate   = (trim($toDate) == '' ? $fromDate : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : new DateTime('NOW'));
		$toDate   = $toDate->format('Y-m-d 23:59:59');

		// Build first query to fetch all part IDs from the tracking table for the given date and process.
		// This list will serve as lookup condition (parts and processes) in the next query.
		$query    = $db->getQuery(true)
		->select('GROUP_CONCAT(DISTINCT ' . $db->qn('partID') . ') AS ' . $db->qn('parts'))
		->from($db->qn('tracking'))
		->where($db->qn('procID')    . ' = ' . $procID)
		->where($db->qn('paramID')   . ' = ' . Techparams::STATIC_TECHPARAM_DATE)
		->where($db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
		->order($db->qn('partID'));

		// Prepare lookup data for second query.
		$parts = null;

		// Execute query.
		try
		{
			// Expected formats:
			//	NULL  or   a string like '1,2,3,4,5' ready to be passed by the next query below in <pre>IN()</pre> condition.
			$parts = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		/* 2nd QUERY:  For every part fetch the tracking time for the selected process */

		// Init vars.
		$arrResult = [];

		if (!is_null($parts))
		{
			// Build query.
			$query
			->clear()
			->from($db->qn('tracking', 'ptp'))
			->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('ptp.partID') . ' = ' . $db->qn('p.partID'))
			->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')    . ' = ' . $db->qn('a.artID'))
			->select(
				$db->qn([
					'a.artID',
					'a.number'
				])
			)
//			->select('GROUP_CONCAT(' . $db->qn('ptp.paramValue') . ' SEPARATOR "|") AS ' . $db->qn('paramValue'))
			->where($db->qn('ptp.partID') . ' IN (' . $parts . ')')
			->group($db->qn('ptp.partID'))
			->order($db->qn('a.number'));

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadRowList();

				// Count article types.
				while (count($rs))
				{
					[$artID, $articleType] = array_shift($rs);

					if (isset($arrResult[$articleType]))
					{
						$arrResult[$articleType]['count'] += 1;
					}
					else
					{
						$arrResult[$articleType] = [
							'artID' => $artID,
							'count' => 1
						];
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
		}

		// Free memory.
		unset($rs);

		// Close connection.
		$this->closeDatabaseConnection();

		ksort($arrResult);

		return $arrResult;
	}
	private function getProcessArticles__BAK(int $procID, string $fromDate = '', string $toDate = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : new DateTime('NOW'));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		$toDate   = (trim($toDate) == '' ? $fromDate : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : new DateTime('NOW'));
		$toDate   = $toDate->format('Y-m-d 23:59:59');

		// Init vars.
		$arrResult = [];

		// Build query.
		$query    = $db->getQuery(true)
		->clear()
		->from($db->qn('tracking', 'ptp'))
		->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
		->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
		->select(
			$db->qn([
				'p.artID',
				'a.number'
			])
		)
		->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
		->select(
			$db->qn([
				'ptp.partID',
				'ptp.paramValue'
			])
		)
		->select(
			'CASE' .
			' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
			' THEN ' . $db->q('good') .
			' ELSE ' . $db->q('bad') .
			' END' .
			' AS '  . $db->qn('status')
		)
		->where($db->qn('ptp.timestamp') . ' BETWEEN '   . $db->q($fromDate) . ' AND ' . $db->q($toDate))
		->where($db->qn('ptp.procID')  . ' = ' . $procID)
		->where($db->qn('ptp.paramID') . ' = ' . Techparams::STATIC_TECHPARAM_ERROR)
		->order($db->qn('ptp.partID'));

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList('partID');

			// Count article types.
			while (count($rs))
			{
				$artID = $number = $projectNum = $status = null;

				extract(array_shift($rs));

				if (!isset($arrResult[$number]))
				{
					$arrResult[$number] = [
						'artID'   => $artID,
						'project' => $projectNum,
						'good'    => 0,
						'bad'     => 0
					];
				}

				$arrResult[$number][$status] += 1;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Free memory.
		unset($rs);

		// Close connection.
		$this->closeDatabaseConnection();

		ksort($arrResult);

		return $arrResult;
	}
	public function getProcessArticles() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', get_class($this->getInstance('processes')))));
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter   = ArrayHelper::getValue($args, 'filter',  ListModel::FILTER_ALL);
		$id       = ArrayHelper::getValue($args, $pkName);
		$id       = (is_null($id)) ? null : (int) $id;
		$dateFrom = ArrayHelper::getValue($args, 'dateFrom', 'NOW', 'STRING');		// fall back to today
		$dateTo   = ArrayHelper::getValue($args, 'dateTo',   'NOW', 'STRING');		// fall back to today
		$timeFrom = ArrayHelper::getValue($args, 'timeFrom', '00:00:01', 'STRING');	// fall back to last midnight
		$timeTo   = ArrayHelper::getValue($args, 'timeTo',   '23:59:59', 'STRING');	// fall back to this midnight
		$order    = ArrayHelper::getValue($args, 'order');
		$sort     = ArrayHelper::getValue($args, 'sort');

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

		// Prepare lookup date interval.
		$fromDate = date_create(sprintf('%s %s', $dateFrom, $timeFrom), new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');
		$toDate   = date_create(sprintf('%s %s', $dateTo,   $timeTo),   new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');

		// Init vars.
		$arrResult = [];

		// Build query.
		if(isset($_GET['ft'])){
            $filter=$_GET['ft'];
        }else{
            $filter=0;
        }

        if($filter == 0){
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));
        }elseif ($filter == 112){

            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('ptp.partID') . ') AS ' . $db->qn('parts'))
                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                ->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));

            $rset = $db->setQuery($query)->loadAssocList();

            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )
                ->where($db->qn('ptp.partID') . ' IN (' . $rset[0]['parts'] . ')')
                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));
        }elseif ($filter == 111){
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('ptp.partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                ->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("NEMATECH Kft."))
                ->order($db->qn('ptp.partID'));

            $rset = $db->setQuery($query)->loadAssocList();

            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.partID') . ' IN (' . $rset[0]['parts'] . ')')
                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("NEMATECH Kft."))
                ->order($db->qn('ptp.partID'));
        }elseif ($filter == 113){
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select('GROUP_CONCAT(DISTINCT ' . $db->qn('ptp.partID') . ') AS ' . $db->qn('parts'))

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ORGANISATION)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                ->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("NEMECTEK TOW"))
                ->order($db->qn('ptp.partID'));

            $rset = $db->setQuery($query)->loadAssocList();

            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.partID') . ' IN (' . $rset[0]['parts'] . ')')
                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("NEMECTEK TOW"))
                ->order($db->qn('ptp.partID'));
        }else{
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));
        }
		/*$query    = $db->getQuery(true)
		->clear()
		->from($db->qn('tracking', 'ptp'))
		->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
		->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
		->select(
			$db->qn([
				'p.artID',
				'a.number'
			])
		)
		->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
		->select(
			$db->qn([
				'ptp.partID',
				'ptp.paramValue'
			])
		)
		->select(
			'CASE' .
			' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
			' THEN ' . $db->q('good') .
			' ELSE ' . $db->q('bad') .
			' END' .
			' AS '   . $db->qn('status')
		)
		->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
		->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
		->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
		->order($db->qn('ptp.partID'));*/

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList('partID');

			// Count article types.
			while (count($rs))
			{
				$artID = $number = $projectNum = $status = null;

				extract(array_shift($rs));

				if (!isset($arrResult[$number]))
				{
					$arrResult[$number] = [
						'artID'   => $artID,
						'project' => $projectNum,
						'good'    => 0,
						'bad'     => 0
					];
				}

				$arrResult[$number][$status] += 1;
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Free memory.
		unset($rs);

		// Close connection.
		$this->closeDatabaseConnection();

		ksort($arrResult);

		return $arrResult;
	}

	public function getgoodProcessArticles() : array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Calculate class name and primary key name.
        $className  = mb_strtolower(basename(str_replace('\\', '/', get_class($this->getInstance('processes')))));
        $entityName = (new StringInflector)->singularize($className);
        $entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
        $pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

        // Get additional function args.
        $args = func_get_args();
        $args = (array) array_shift($args);

        // There may be arguments for this function.
        $filter   = ArrayHelper::getValue($args, 'filter',  ListModel::FILTER_ALL);
        // print_r($filter);
        $id       = ArrayHelper::getValue($args, $pkName);
        $id       = (is_null($id)) ? null : (int) $id;
        $dateFrom = ArrayHelper::getValue($args, 'dateFrom', 'NOW', 'STRING');		// fall back to today
        $dateTo   = ArrayHelper::getValue($args, 'dateTo',   'NOW', 'STRING');		// fall back to today
        $timeFrom = ArrayHelper::getValue($args, 'timeFrom', '00:00:01', 'STRING');	// fall back to last midnight
        $timeTo   = ArrayHelper::getValue($args, 'timeTo',   '23:59:59', 'STRING');	// fall back to this midnight
        $order    = ArrayHelper::getValue($args, 'order');
        $sort     = ArrayHelper::getValue($args, 'sort');

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

        // Prepare lookup date interval.
        $fromDate = date_create(sprintf('%s %s', $dateFrom, $timeFrom), new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');
        $toDate   = date_create(sprintf('%s %s', $dateTo,   $timeTo),   new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');

        // Init vars.
        $arrResult = [];

        // Build query.

        /*if(isset($_GET['ft'])){
            $filter=$_GET['ft'];
        }else{
            $filter=0;
        }

        if($filter == 0){
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));
        }elseif ($filter == 112){
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                //->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                ->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                ->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));
        }elseif ($filter == 111){
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )


                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                //->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                ->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                ->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("NEMATECH Kft."))
                ->order($db->qn('ptp.partID'));
        }else{
            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));
        }*/


        $query    = $db->getQuery(true)
            ->clear()
            ->from($db->qn('tracking', 'ptp'))
            ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
            ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
            ->select(
                $db->qn([
                    'p.artID',
                    'a.number'
                ])
            )
            ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
            ->select(
                $db->qn([
                    'ptp.partID',
                    'ptp.paramValue'
                ])
            )
            ->select(
                'CASE' .
                ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                ' THEN ' . $db->q('good') .
                ' ELSE ' . $db->q('bad') .
                ' END' .
                ' AS '   . $db->qn('status')
            )

            ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
            ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
            ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
            //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
            //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
            ->order($db->qn('ptp.partID'));

        // Execute query.
        try
        {
            $rs = $db->setQuery($query)->loadAssocList('partID');

            // Count article types.
            while (count($rs))
            {
                $artID = $number = $projectNum = $status = null;

                extract(array_shift($rs));

                if (!isset($arrResult[$number]))
                {
                    $arrResult[$number] = [
                        'artID'   => $artID,
                        'project' => $projectNum,
                        'good'    => 0,
                        'bad'     => 0
                    ];
                }

                $arrResult[$number][$status] += 1;
            }
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
        }

        // Free memory.
        unset($rs);

        // Close connection.
        $this->closeDatabaseConnection();

        ksort($arrResult);

        return $arrResult;
    }
    public function getbadProcessArticles() : array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Calculate class name and primary key name.
        $className  = mb_strtolower(basename(str_replace('\\', '/', get_class($this->getInstance('processes')))));
        $entityName = (new StringInflector)->singularize($className);
        $entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
        $pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

        // Get additional function args.
        $args = func_get_args();
        $args = (array) array_shift($args);

        // There may be arguments for this function.
        $filter   = ArrayHelper::getValue($args, 'filter',  ListModel::FILTER_ALL);
        // print_r($filter);
        $id       = ArrayHelper::getValue($args, $pkName);
        $id       = (is_null($id)) ? null : (int) $id;
        $dateFrom = ArrayHelper::getValue($args, 'dateFrom', 'NOW', 'STRING');		// fall back to today
        $dateTo   = ArrayHelper::getValue($args, 'dateTo',   'NOW', 'STRING');		// fall back to today
        $timeFrom = ArrayHelper::getValue($args, 'timeFrom', '00:00:01', 'STRING');	// fall back to last midnight
        $timeTo   = ArrayHelper::getValue($args, 'timeTo',   '23:59:59', 'STRING');	// fall back to this midnight
        $order    = ArrayHelper::getValue($args, 'order');
        $sort     = ArrayHelper::getValue($args, 'sort');

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

        // Prepare lookup date interval.
        $fromDate = date_create(sprintf('%s %s', $dateFrom, $timeFrom), new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');
        $toDate   = date_create(sprintf('%s %s', $dateTo,   $timeTo),   new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');

        // Init vars.
        $arrResult = [];

        // Build query.

        if(isset($_GET['ft'])){
            $filter=$_GET['ft'];
        }else{
            $filter=0;
        }


            $query    = $db->getQuery(true)
                ->clear()
                ->from($db->qn('tracking', 'ptp'))
                ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
                ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
                ->select(
                    $db->qn([
                        'p.artID',
                        'a.number'
                    ])
                )
                ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
                ->select(
                    $db->qn([
                        'ptp.partID',
                        'ptp.paramValue'
                    ])
                )
                ->select(
                    'CASE' .
                    ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
                    ' THEN ' . $db->q('good') .
                    ' ELSE ' . $db->q('bad') .
                    ' END' .
                    ' AS '   . $db->qn('status')
                )

                ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
                ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
                ->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
                //->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
                //->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
                ->order($db->qn('ptp.partID'));



        /*$query    = $db->getQuery(true)
        ->clear()
        ->from($db->qn('tracking', 'ptp'))
        ->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('p.partID') . ' = ' . $db->qn('ptp.partID'))
        ->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')  . ' = ' . $db->qn('a.artID'))
        ->select(
            $db->qn([
                'p.artID',
                'a.number'
            ])
        )
        ->select('MID(' . $db->qn('a.number') . ', 5, 3) AS ' . $db->qn('projectNum'))
        ->select(
            $db->qn([
                'ptp.partID',
                'ptp.paramValue'
            ])
        )
        ->select(
            'CASE' .
            ' WHEN ' . $db->qn('ptp.paramValue') . ' = 0' .
            ' THEN ' . $db->q('good') .
            ' ELSE ' . $db->q('bad') .
            ' END' .
            ' AS '   . $db->qn('status')
        )

        ->where($db->qn('ptp.timestamp')   . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate))
        ->where($db->qn('ptp.' . $pkName)  . '  = ' . $id)
        //->where($db->qn('ptp.paramID')     . '  = ' . Techparams::STATIC_TECHPARAM_ERROR)
            ->where($db->qn('ptp.paramID')  . ' IN (' . Techparams::STATIC_TECHPARAM_ERROR . ',' . Techparams::STATIC_TECHPARAM_ORGANISATION . ' )')
            ->where($db->qn('ptp.paramValue')  . ' = ' . $db->q("FRÖTEK-Kunststofftechnik GmbH (OHA)"))
        ->order($db->qn('ptp.partID'));*/

        // Execute query.
        try
        {
            $rs = $db->setQuery($query)->loadAssocList('partID');

            // Count article types.
            while (count($rs))
            {
                $artID = $number = $projectNum = $status = null;

                extract(array_shift($rs));

                if (!isset($arrResult[$number]))
                {
                    $arrResult[$number] = [
                        'artID'   => $artID,
                        'project' => $projectNum,
                        'good'    => 0,
                        'bad'     => 0
                    ];
                }

                $arrResult[$number][$status] += 1;
            }
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
        }

        // Free memory.
        unset($rs);

        // Close connection.
        $this->closeDatabaseConnection();

        ksort($arrResult);

        return $arrResult;
    }

	/**
	 * Returns for a given process, article and date (range) the parts gone through that process.
	 *
	 * @param   int    $procID    The related process ID
	 * @param   int    $artID     The related article ID
	 * @param   string $fromDate  Look up date interval begin date
	 * @param   string $toDate    Look up date interval end date
	 *
	 * @return  array  A list of objects that contain the partID, the part type, the part code and the full worker name
	 */
	public function getProcessParts(int $procID, int $artID, string $fromDate = '', string $toDate = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		/* 1st QUERY:  Fetch IDs of all parts been tracked on the selected date */

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : new DateTime('NOW'));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		$toDate   = (trim($toDate) == '' ? $fromDate : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : new DateTime('NOW'));
		$toDate   = $toDate->format('Y-m-d 23:59:59');

		// Build first query to fetch lookup conditions (parts and processes) for second query.
		$query    = $db->getQuery(true)
		->from($db->qn('tracking', 'ptp'))
		->join('LEFT', $db->qn('parts') . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('ptp.partID') . ' = ' . $db->qn('p.partID'))
		// ->select($db->qn('ptp.partID'))
		->select('GROUP_CONCAT(DISTINCT ' . $db->qn('ptp.partID') . ') AS ' . $db->qn('parts'))
		->where([
			$db->qn('p.artID')       . ' = ' . $artID,
			$db->qn('ptp.procID')    . ' = ' . $procID,
			$db->qn('ptp.timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate)
		])
		// NEW condition: This shall catch items with no timestamp but the date tracked, because the timestamp column was added later.
		->orWhere([
			$db->qn('p.artID')        . ' = ' . $artID,
			$db->qn('ptp.procID')     . ' = ' . $procID,
			$db->qn('ptp.paramID')    . ' = ' . Techparams::STATIC_TECHPARAM_DATE,
			$db->qn('ptp.paramValue') . ' = ' . $db->q($fromDate)
		]);

		// Prepare lookup data for second query.
		$parts = null;

		// Execute query.
		try
		{
			// Expected formats:
			//	NULL  or   a string like '1,2,3,4,5' ready to be passed by the next query below in <pre>IN()</pre> condition.
			$parts = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		/* 2nd QUERY:  For every part fetch the tracking time for the selected process */

		// Init vars.
		$arrResult = [];

		if (!is_null($parts))
		{
			// Build query.
			$query = $db->getQuery(true)
			->from($db->qn('tracking', 'ptp'))
			->join('LEFT', $db->qn('parts')    . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('ptp.partID') . ' = ' . $db->qn('p.partID'))
			->join('LEFT', $db->qn('articles') . ' AS ' . $db->qn('a') . ' ON ' . $db->qn('p.artID')    . ' = ' . $db->qn('a.artID'))
			->select([
				$db->qn('ptp.partID'),
				$db->qn('a.number')            . ' AS ' . $db->qn('type'),
				$db->qn('p.trackingcode'),
				$db->qn('ptp.paramValue')      . ' AS ' . $db->qn('editor')
			])
			->where($db->qn('ptp.partID')  . ' IN (' . $parts . ')')
			->where($db->qn('ptp.paramID') . ' = ' . Techparams::STATIC_TECHPARAM_OPERATOR)
			->group($db->qn('ptp.partID'))
			->order($db->qn('a.number'));

			// Execute query.
			try
			{
				$rs = $db->setQuery($query)->loadObjectList();

				if (count($rs))
				{
					$arrResult = $rs;
				}
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);
			}
		}

		return $arrResult;
	}

	public function getErrorStats(string $fromDate = '', string $toDate = '', ...$args) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		//-> BEGIN: NEU
		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		//<- END: NEU

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init vars.
		$arrResult = [];

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : new DateTime('NOW'));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		/*// Disabled on 2022-06-03 because as of the code inspection it is unused
		$toDate   = (trim($toDate) == '' ? $fromDate : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : new DateTime('NOW'));
		$toDate   = $toDate->format('Y-m-d 23:59:59');*/

		/* Get ID of selected language.
		 * IMPORTANT:   It turned out on 2022-06-03 that using lngID over language prevents the necessity of grouping the query resultset.
		 *              While testing the query there were duplicate rows when using the term <em>am.language = 'tag'</em>, whereas there
		 *              were no duplicates when using the term <em>am.lngID = ID</em> - both used without GROUP BY.
		 */
		$lngTag   = ArrayHelper::getValue($args, 'language', $this->language, 'STRING');
		$lngID    = ArrayHelper::getValue($args, 'lngID');
		$lngID    = is_int($lngID)
			? $lngID
			: ArrayHelper::getValue($this->getInstance('language')->getLanguageByTag($lngTag), 'lngID', 'INT');

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('tracking', 'ptp'))
		->join('LEFT', $db->qn('process_meta') . ' AS ' . $db->qn('pm') . ' ON ' . $db->qn('ptp.procID')     . ' = ' . $db->qn('pm.procID'))
		->join('LEFT', $db->qn('errors')       . ' AS ' . $db->qn('e')  . ' ON ' . $db->qn('ptp.paramValue') . ' = ' . $db->qn('e.errID'))
		->select('DISTINCT ' . $db->qn('ptp.paramValue') . ' AS ' . $db->qn('errID'))
		->select($db->qn('e.name') . ' AS ' . $db->qn('error'))
		->select('CONCAT("[", GROUP_CONCAT(' . $db->qn('ptp.partID') . '), "]") AS ' . $db->qn('parts'))
		->select('COUNT(' . $db->qn('ptp.paramValue') . ') AS ' . $db->qn('total'))
		->select($db->qn('ptp.procID'))
		->select($db->qn('pm.name') . ' AS ' . $db->qn('process'))
		->select($lngID             . ' AS ' . $db->qn('lngID'))
		->where($db->qn('ptp.paramID')    . ' = ' . Techparams::STATIC_TECHPARAM_ERROR)
		->where($db->qn('ptp.paramValue') . ' > 0')
		->where($db->qn('pm.lngID') . ' = ' . $db->q($lngID))
		->where($db->qn('e.lngID')  . ' = ' . $db->q($lngID))
		->group($db->qn('ptp.paramValue'))
		->order($db->qn('ptp.procID'));

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList();

			array_walk($rs, function($row) use(&$arrResult)
			{
				$procID  = $row->procID;
				$process = $row->process;
				$errID   = $row->errID;

				unset($row->errID);
				unset($row->procID);
				unset($row->process);

				if (!array_key_exists($procID, $arrResult))
				{
					$arrResult[$procID] = [
						'name'   => $process,
						'errors' => []
					];
				}

				$arrResult[$procID]['errors'][$errID] = $row;
			});
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

		return $arrResult;
	}

	/**
	 * Populates project monitoring data tables with data from tracking table.
	 *
	 * @return \ \Model\Statistics object for chaining
	 *
	 * @throws \Exception
	 */
	public function populateMonitorDataTables() : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Pre-Calc required dates.
		$timeZone   = new DateTimeZone(FTKRULE_TIMEZONE);
		$dateToday  = new DateTime('YESTERDAY',  $timeZone);   // Today's data is fetched and stored via daily executed MySQL store procedure
		$dateBegin  = new DateTime('2020-01-01', $timeZone);
		$dateEnd    = clone($dateToday);
		$dateRange  = new DatePeriod($dateBegin, new DateInterval('P1D'), $dateEnd);
		$dateFormat = 'Y-m-d';

		foreach ($dateRange as $date)
		{
			$date    = $date->format($dateFormat);
			$numbers = $this->fetchDailyNumbers($date);
//			$cnt     = count($numbers);
			$i       = 0;

			if (empty($numbers))
			{
				continue;
			}

			foreach ($numbers as $row)
			{
				$dataStored = $this->storeDailyNumbers($date, $row);

				/*if (true === $dataStored)
				{
					$dataAccumulatedStored = $this->storeDailyNumbersAccumulated($date, $row);
				}*/

				$i += ($dataStored) ? 1 : 0;
//				$i += ($dataStored && $dataAccumulatedStored) ? 1 : 0;
			}
		}

		return $this;
	}


	/**
	 * Fetches daily stats per project/process from the tracking table and populates the tables required for the project output monitor.
	 *
	 * @param   string $date The date to which the data for
	 *
	 * @return  array An index array containing associative array(s) where each of it contains data for 1 project/process combination.
	 *                The following data is return for every combination:  date, project ID, process ID, the number of items that passed this process
	 */
	private function fetchDailyNumbers(string $date = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init shorthands to tables.
		$table = 'tracking';

		// Init database query object.
		$query = $db->getQuery(true)
		->from($db->qn($table, 'ptp'))
		->leftJoin($db->qn('parts', 'pt')     . ' ON ' . $db->qn('pt.partID') . ' = ' . $db->qn('ptp.partID'))
		->leftJoin($db->qn('articles', 'a')   . ' ON ' . $db->qn('pt.artID')  . ' = ' . $db->qn('a.artID'))
		->leftJoin($db->qn('processes', 'pc') . ' ON ' . $db->qn('pc.procID') . ' = ' . $db->qn('ptp.procID'))
		->leftJoin($db->qn('projects', 'p')   . ' ON ' . $db->qn('p.number')  . ' = MID(' . $db->qn('a.number') . ', 5, 3)')
		->select($db->q($date)  . '  AS ' . $db->qn('date'))
		->select($db->qn('p.proID'))
		->select($db->qn('pc.procID'))
		->select('COUNT(' . $db->qn('pt.artID')        . ') AS ' . $db->qn('total'))
		->where($db->qn('ptp.timestamp') . ' BETWEEN DATE_FORMAT(' . $db->q($date) . ', ' . $db->q('%Y-%m-%d 00:00:00') . ') AND DATE_FORMAT(' . $db->q($date) . ', ' . $db->q('%Y-%m-%d 23:59:59') . ')')
		->where($db->qn('ptp.paramID')   . ' = ' . $db->q(Techparams::STATIC_TECHPARAM_DATE))
		->group($db->qn('p.number'))
		->group($db->qn('ptp.procID'))
		->order($db->qn('p.number'))
		->order($db->qn('pc.abbreviation'));

		try
		{
			$arrResult = $db->setQuery($query)->loadAssocList();
		}
		catch (Throwable $e)
		{
			die($e->getMessage());
		}

		return (array) $arrResult;
	}

	private function accumulateDailyNumbers(array $data = []) : array
	{
		return $data;
	}

	/**
	 * Stores daily stats into project monitoring data table.
	 *
	 * @param   string $date The date to which the data to be stored relates to
	 * @param   array  $row  The data for 1 project/process combination
	 *
	 * @return  bool   true on success or false on failure
	 */
	private function storeDailyNumbers(string $date = '', array $row = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init shorthand to database query object.
		$query   = $db->getQuery(true);

		// Init shorthands to tables.
		$table   = 'project_monitoring_data';
		$columns = DatabaseHelper::getTableColumns($table);

		// Unset project name and process abbreviation if they're set. We just need the IDs.
		unset($row['project']);
		unset($row['process']);

		// Add/Replace timestamp column.
		$row['timestamp'] = sprintf('%s 23:59:59', $date);

		// Escape data.
		$row = array_map([$db, 'q'], $row);

		// Build query.
		$query
		->clear()
		->insert($db->qn($table))
		->columns($db->qn($columns))
		->values(implode(',', $row));

		// Change statement to execute insertion skipping existing data via 'INSERT IGNORE'.
		// This hack is necessary, because the Joomla database driver has no implementation for this.
		$sql = str_ireplace('INSERT INTO', 'INSERT IGNORE INTO', $query->dump());

		// Clean query string (strip line break as well as leading and trailing whitespace - OR THE QUERY WILL FAIL !!!)
		$sql = preg_replace('/[\r\n]/i', ' ', $sql);
		$sql = preg_replace('/\s{2,}/i', ' ', $sql);
		$sql = trim($sql);								// This alone DOES NOT SUFFICE !!!

		/* The next clean-up step is mandatory - DO NOT DELETE THIS CODE!
		 *
		 * Previously just passing the query string caused the JDatabaseDriver to fail execution.
		 * It turned out that although {@link https://www.php.net/manual/de/function.trim.php}
		 * was applied there was leading and trailing whitespace that caused the JDatabaseDriver to fail.
		 *
		 * Only the next step can fix that.
		 */
		preg_match('/INSERT.*\)/i', $sql, $matches);
		$sql = current($matches);

		// Execute query.
		try
		{
			$stored = $db->setQuery($sql)->execute();
		}
		catch(Throwable $e)
		{
			die($e->getMessage());
		}

		return $stored;
	}

	/**
	 * Stores daily stats into project monitoring data accumulated table.
	 *
	 * @param   string $date The date to which the data to be stored relates to
	 * @param   array  $row  The data for 1 project/process combination
	 *
	 * @return  bool   true on success or false on failure
	 */
	private function storeDailyNumbersAccumulated(string $date = '', array $row = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init shorthand to database query object.
		$query   = $db->getQuery(true);

		// Init shorthands to tables.
		$table   = 'project_monitoring_data_accumulated';
		$columns = DatabaseHelper::getTableColumns($table);

		// Unset project name and process abbreviation if they're set. We just need the IDs.
		unset($row['project']);
		unset($row['process']);

		// Add/Replace timestamp column.
		$row['timestamp'] = sprintf('%s 23:59:59', $date);

		// We cannot be sure the input data has already been escaped. So we clean it first.
		$row = array_map(function($elem) { return preg_replace("/(^'|'$)/", '', $elem); }, $row);

		// Lookup query sub-query.
		$sub   = $db->getQuery(true)
		->from($db->qn($table))
		->select('MAX(' . $db->qn('date') . ')')
		->where($db->qn('proID')    . ' = ' . $db->q(ArrayHelper::getValue($row, 'proID')))
		->where($db->qn('procID')   . ' = ' . $db->q(ArrayHelper::getValue($row, 'procID')));

		// Lookup query.
		$query
		->clear()
		->from($db->qn($table, 'd'))
		->select($db->qn('d') . '.*')
		->where($db->qn('d.proID')  . ' = ' . $db->q(ArrayHelper::getValue($row, 'proID')))
		->where($db->qn('d.procID') . ' = ' . $db->q(ArrayHelper::getValue($row, 'procID')))
		->where($db->qn('d.date')   . ' = ( ' . $sub . ' )');

//		$isNew = false;

		// Execute query.
		try
		{
			$prevEntry = $db->setQuery($query)->loadAssoc();
			$isNew = empty($prevEntry);
		}
		catch (Throwable $e)
		{
			die($e->getMessage());
		}

		if ($isNew)
		{
			// Insert very first entry for this project/process combination.
		}
		else
		{
			// Add new row for this project/process combination with accumulated total value (rolling sum).

			// Accumulate value of column 'total'.
			$row['total'] = (int) ArrayHelper::getValue($prevEntry, 'total', 0, 'INT') + (int) ArrayHelper::getValue($row, 'total', 0, 'INT');
		}

		// Escape data.
		$row = array_map([$db, 'q'], $row);

		// Build query.
		$query
		->clear()
		->insert($db->qn($table))
		->columns($db->qn($columns))
		->values(implode(',', $row));

		// Execute query.
		try
		{
			$db->setQuery($query)->execute();
		}
		catch(Throwable $e)
		{
			die($e->getMessage());
		}

		return true;
	}
}
