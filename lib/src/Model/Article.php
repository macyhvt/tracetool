<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use finfo;
use ImagickException;
use InvalidArgumentException;
use Joomla\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use  \App;
use  \Entity;
use  \Helper\DatabaseHelper;
use  \Helper\FilesystemHelper;
use  \Helper\ImageHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Messager;
use  \Model\Item as ItemModel;
use  \Model\Lizt as ListModel;
use  \Text;
use RuntimeException;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use function array_column;
use function array_combine;
use function array_fill;
use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_pop;
use function array_replace;
use function array_search;
use function array_unique;
use function array_values;
use function array_walk;
use function hash_file;
use function in_array;
use function is_a;
use function is_array;
use function is_dir;
use function is_int;
use function is_null;
use function is_object;
use function is_readable;
use function is_string;
use function is_writable;
use function mkdir;
use function move_uploaded_file;
use function property_exists;

/**
 * Class description
 */
class Article extends ItemModel
{
	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @since   1.1
	 */
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/***   BEGIN: API-Service(s)   ***/

    public function xhrFileUpload() : ?bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;


		//BEGIN:  Test error reporting
		Messager::setMessage([
			'type' => 'error',
			'text' => Text::translate('Upload failure', $this->language)
		]);

		return false;
		//END:  Test error reporting




		//BEGIN:  Test error reporting
		Messager::setMessage([
			'type' => 'success',
			'text' => Text::translate('Upload successful', $this->language)
		]);

		return true;
		//END:  Test error reporting



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
//		->select($db->q('ETC ARTICLE NAME')  . ' AS ' . $db->qn('artName'))		// FIXME - switch with previous line after fields have been switched in DB and code base
		->select($db->qn('a.custartno')      . ' AS ' . $db->qn('custArtNumber'))
		->select($db->qn('a.custartname')    . ' AS ' . $db->qn('custArtName'))
//		->select($db->q('CUST ARTICLE NAME') . ' AS ' . $db->qn('custArtName'))
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

	/***   END: API-Service(s)   ***/

	// Intended to replace method 'getArticle' in order to implement functions 'getList()' and 'getItem()' to adapt Joomla! style

	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $itemID
	 *
	 * @return  Entity\Article
	 */
	public function getItem(int $itemID) : Entity\Article
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('articles', ['language' => $this->language])->getList(['artID' => $itemID]),
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
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('artID')) {}

		return $row;
	}

	public function getItemMeta(int $artID, string $lang, bool $isNotNull = false)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->hasArticleMeta($artID, $lang, $isNotNull, true);
	}

	public function getArticleByNumber(string $articleNumber)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_a($articleNumber, ' \Entity\Article'))
		{
			$article = $articleNumber;
		}
		else
		{
			// First, fetch the article ID from the database.
			$artID = (int) ArrayHelper::getValue(
				(array) $this->getInstance('articles', ['language' => $this->language])->getArticlesByNumber($articleNumber),
				$articleNumber
			);

			// Second, load the article from the database. Return value is instance of  \Entity\Article.
			$article = $this->getItem($artID);
		}

		return $article;
	}

	public function getArticleProcesses(int $artID, $procID = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$artID  = (is_null($artID)  ? $artID  : (int) $artID);
		$procID = (is_null($procID) ? $procID : (int) $procID);

		// Build query.
		$query = $db->getQuery(true)
            ->from($db->qn('article_process', 'ap') )
            ->join('LEFT', $db->qn('organisations') . ' AS ' . $db->qn('orgs') . ' ON ' . $db->qn('ap.org_abbr') . ' = ' . $db->qn('orgs.orgID'))
		->select(
			$db->qn([
                'ap.artID',
                'ap.procID',
                'ap.hasBanderole',
                'ap.drawingnumber',
                'ap.step',
                'ap.drawing',
                'ap.processState',
                'ap.org_abbr',
                'orgs.org_abbr',
                'orgs.org_color',
			])
		)
		->order($db->qn('step') . ' DESC');

		if (!is_null($artID))
		{
			$query
			->where($db->qn('artID') . ' = ' . $artID);
		}

		if (!is_null($procID))
		{
			$query
			->where($db->qn('procID') . ' = ' . (int) $procID);
		}

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList();

			// Init return value.
			$rows = [];

			// Init tmp array to sort process drawing numbers.
			$ordering = [];

			foreach ($rs as $row)
			{
				// Load process data into a {@see \Joomla\Registry\Registry} object for consistent data access.
				$row = new Registry($row);

				// if (\property_exists($row, 'drawing'))
				if ($row->def('drawing'))
				{
					$drawing = $row->get('drawing');

					$drawing = (is_string($drawing)
						? json_decode($drawing, null, 512, JSON_THROW_ON_ERROR)
						: $drawing);

					$row->set('drawing', $drawing);

					// Dump process drawing number for sorting.
					$ordering[$row->get('procID')] = (is_object($drawing) ? $drawing->number : null);
				}

				$rows[$row->get('procID')] = $row;
			}

			// Sort process drawing numbers.
			arsort($ordering);
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

		// Sort rows loaded according to calculated ordering.
		// Solution borrowed from {@see https://stackoverflow.com/a/9098675}
		return array_replace($ordering, $rows); // FIXME - check where $ordering comes from and what is going on here
	}

    //Active-Deactivate starts
    public function getArticleUpCount($artID, $pid)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        // Build query.
        $query = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition'))
            ->select(
                $db->qn([
                    'artID',
                    'procID',
                    'mp',
                ])
            )
            ->order($db->qn('timestamp') . ' DESC');
        if (!is_null($artID))
        {
            $query
                ->where($db->qn('artID') . ' = ' . $artID);
        }

        if (!is_null($pid))
        {
            $query
                ->where($db->qn('procID') . ' = ' . (int) $pid);
        }

        try {
            $rs = $db->setQuery($query)->loadResult();
        }catch (Exception $e){
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;
    }
    public function updateProcessStatus($artID, $pid, $statcode)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        try{
            $query1 = $db->getQuery(true)
                ->update($db->qn('article_process'))
                ->set($db->qn('processState') . ' = ' . $statcode)
                ->where($db->qn('artID') . ' = ' . $artID)
                ->where($db->qn('procID') . ' = ' . $pid);
            $db->setQuery($query1)->execute();
        }catch (Exception $e){
            $this->logger->log('error', $e->getMessage());
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate('Failed to store the article processes. Check the log file for details.', $this->language)
            ]);
            return false;
        }
        $this->closeDatabaseConnection();
        return true;
    }
    //Active-Deactivate Ends
	public function getProjectnumber(int $artID) : ?string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$artID = (is_null($artID)) ? $artID : $artID;

		if (!$artID)
		{
			return null;
		}

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('articles'))
		->select($db->qn('number'))
		->where($db->qn('artID') . ' = ' . $artID);

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadResult();

			if (!empty($rs))
			{
				$rs = explode('.', $rs)[1];
			}
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

	//@todo - implement
	public function getParts($artID) : array
	{
		return [];
	}

	public function getDefinedMeasuringPoints($artID = null, $procID = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$artID  = (is_null($artID)  ? $artID  : (int) $artID);

		// Build query.
		$table = 'article_process_mp_definition';
		$query = $db->getQuery(true)
		->from($db->qn($table))
		->order($db->qn('procID'))
		->order('RIGHT(' . $db->qn('mp') . ', 3)');

		if (!is_array($columns = DatabaseHelper::getTableColumns($table)))
		{
			return [];
		}

		$query
		->select(implode(',', $db->qn($columns)));

		if (!is_null($artID))
		{
			$query
			->where($db->qn('artID') . ' = ' . (int) $artID);
		}

		if (!is_null($procID))
		{
			$query
			->where($db->qn('procID') . ' = ' . (int) $procID);
		}

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList();

			// Init return value.
			$rows = [];

			if ((int) $artID > 0)
			{
				foreach ($rs as $row)
				{
					if (!array_key_exists($row['procID'], $rows) || empty($rows[$row['procID']]))
					{
						$rows[$row['procID']] = [];
					}

					$rows[$row['procID']][] = $row;
				}

				ksort($rows);
			}
			else
			{
				$rows = &$rs;
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

	/**
	 * Add description...
	 *
	 * @param   int    $artID        The article ID
	 * @param   array  $procIDs      List of process IDs to limit the look-up to
	 * @param   string $fromDate     Look up date interval begin date
	 * @param   string $toDate       Look up date interval end date
	 * @param   string $qualityType  Either 'good' to fetch only 'good parts' or 'bad' to fetch only 'bad parts'
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function getPartsPerProcess__BAK(int $artID, array $procIDs = [], string $fromDate = '', string $toDate = '', string $qualityType = '') : array
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
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		$procIDs  = array_map('intval', $procIDs);
		$rs       = [];

		if (empty($procIDs))
		{
			return $rs;
		}

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE))));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		$toDate   = (trim($toDate) == '' ? 'NOW' : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE))));
		$toDate   = $toDate->format('Y-m-d 23:59:59');

		// Sub-query: Fetch all parts that are of the specified article type (passed via function arg).
		$sub = $db->getQuery(true)
		->from($db->qn('parts'))
		->select($db->qn('partID'))
		->where($db->qn('artID') . ' = ' . $artID);

		// Main-query: Fetch all parts having quality status "good" or "bad" (passed via function arg) and
		//			   having been processed within the defined date interval (passed via function arg).
		$query = $db->getQuery(true)
		->from($db->qn('tracking', 't'))
		->join('LEFT', $db->qn('parts') . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('t.partID') . ' = ' . $db->qn('p.partID'))
		->select([
			$db->qn('t.partID'),
			$db->qn('p.artID'),				// Added on 2021-10-04
			$db->qn('p.trackingcode'),
			$db->qn('p.sample'),			// Added on 2021-10-04
			$db->qn('t.timestamp'),
		])
		->where([
			$db->qn('t.partID') . ' IN(' . $sub . ')',
			$db->qn('t.procID') . ' IN(' . implode(',', $procIDs) . ')',
			// If no quality type was specified, then we assume "good" as default.
			$db->qn('t.paramID') . ' = ' . $db->q('6'),
			$db->qn('t.paramValue') . sprintf(' %s ', (mb_strtolower($qualityType == 'bad') ? '>' : '=')) . $db->q('0'),
			$db->qn('t.timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate)
		])
		->order($db->qn('p.trackingcode'));

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList('partID');
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

		return $rs;
	}
	public function getPartsPerProcess() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Calculate class name and primary key name.
		$className  = mb_strtolower(basename(str_replace('\\', '/', __CLASS__)));
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
		$dateFrom = ArrayHelper::getValue($args, 'dateFrom',      'NOW', 'STRING');	// fall back to today
		$dateTo   = ArrayHelper::getValue($args, 'dateTo',        'NOW', 'STRING');	// fall back to today
		$timeFrom = ArrayHelper::getValue($args, 'timeFrom', '00:00:01', 'STRING');	// fall back to last midnight
		$timeTo   = ArrayHelper::getValue($args, 'timeTo',   '23:59:59', 'STRING');	// fall back to this midnight
		$quality  = ArrayHelper::getValue($args, 'quality',      'good', 'STRING');
		$order    = ArrayHelper::getValue($args, 'order');
		$sort     = ArrayHelper::getValue($args, 'sort');
		$procIDs  = ArrayHelper::getValue($args, 'procIDs',          [], 'ARRAY');

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

		$procIDs  = array_map('intval', $procIDs);
		$rs       = [];

		if (empty($procIDs))
		{
			return $rs;
		}

		// Prepare lookup date interval.
		$fromDate = date_create(sprintf('%s %s', $dateFrom, $timeFrom), new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');
		$toDate   = date_create(sprintf('%s %s', $dateTo,   $timeTo),   new DateTimeZone(FTKRULE_TIMEZONE))->format('Y-m-d H:i:s');

		// Sub-query: Fetch all parts that are of the specified article type (passed via function arg).
		$sub = $db->getQuery(true)
		->from($db->qn('parts'))
		->select($db->qn('partID'))
		->where($db->qn($pkName) . ' = ' . $id);

		// Main-query: Fetch all parts having quality status "good" or "bad" (passed via function arg) and
		//			   having been processed within the defined date interval (passed via function arg).
		$query = $db->getQuery(true)
		->from($db->qn('tracking', 'ptp'))
		->join('LEFT', $db->qn('parts') . ' AS ' . $db->qn('p') . ' ON ' . $db->qn('ptp.partID') . ' = ' . $db->qn('p.partID'))
		->select([
			$db->qn('ptp.partID'),
			$db->qn('p.' . $pkName),
			$db->qn('p.trackingcode'),
			$db->qn('p.sample'),
			$db->qn('ptp.timestamp'),
		])
		->where([
			$db->qn('ptp.partID')     . ' IN(' . $sub . ')',
			$db->qn('ptp.procID')     . ' IN(' . implode(',', $procIDs) . ')',
			// If no quality type was specified, then we assume "good" as default.
			$db->qn('ptp.paramID')    . ' = ' . $db->q('6'),
			$db->qn('ptp.paramValue') . sprintf(' %s ', (mb_strtolower($quality == 'bad') ? '>' : '=')) . $db->q('0'),
			$db->qn('ptp.timestamp')  . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate)
		])
		->order($db->qn($order ?? 'p.trackingcode'));

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadObjectList('partID');
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

		return $rs;
	}

	/**
	 * Add description...
	 *
	 * @param   int    $artID        The article ID
	 * @param   array  $procIDs      List of process IDs to limit the look-up to
	 * @param   string $fromDate     Look up date interval begin date
	 * @param   string $toDate       Look up date interval end date
	 * @param   string $qualityType  Either 'good' to fetch only 'good parts' or 'bad' to fetch only 'bad parts'
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 */
	public function getTotalPartsPerProcess(int $artID, array $procIDs = [], string $fromDate = '', string $toDate = '', string $qualityType = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$rs = [];

		// Get current user object.
//		$user = App::getAppUser();

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

		$procIDs  = array_map('intval', $procIDs);

		if (empty($procIDs))
		{
			return $rs;
		}

		// Prepare lookup date interval.
		$fromDate = (trim($fromDate) == '' ? 'NOW' : $fromDate);
		$fromDate = date_create($fromDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$fromDate = (is_a($fromDate, 'DateTime') ? $fromDate : (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE))));
		$fromDate = $fromDate->format('Y-m-d 00:00:01');
		$toDate   = (trim($toDate) == '' ? 'NOW' : $toDate);
		$toDate   = date_create($toDate, new DateTimeZone(FTKRULE_TIMEZONE));
		$toDate   = (is_a($toDate, 'DateTime') ? $toDate : (new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE))));
		$toDate   = $toDate->format('Y-m-d 23:59:59');

		// Sub-query: Fetch all parts that are of the specified article type (passed via function arg).
		$sub = $db->getQuery(true)
		->from($db->qn('parts'))
		->select($db->qn('partID'))
		->where($db->qn('artID') . ' = ' . $artID);

		// Main-query: Fetch all parts having quality status "good" or "bad" (passed via function arg) and
		//			   having been processed within the defined date interval (passed via function arg).
		$query = $db->getQuery(true)
		->from($db->qn('tracking'))
		->select([
			$db->qn('procID'),
			'COUNT(' . $db->qn('partID') . ') AS ' . $db->qn('parts')
		])
		->where([
			$db->qn('partID') . ' IN(' . $sub . ')',
			$db->qn('procID') . ' IN(' . implode(',', $procIDs) . ')',
			// If no quality type was specified, then we assume "good" as default.
			$db->qn('paramID') . ' = ' . $db->q('6'),
			$db->qn('paramValue') . sprintf(' %s ', (mb_strtolower($qualityType == 'bad') ? '>' : '=')) . $db->q('0'),
			$db->qn('timestamp') . ' BETWEEN ' . $db->q($fromDate) . ' AND ' . $db->q($toDate)
		])
		->group($db->qn('procID'))
		->order($db->qn('procID'));

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadAssocList('procID');

			foreach ($rs as $procID => &$arr)
			{
				$rs[$procID] = ArrayHelper::getValue($arr, 'parts');
			}
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

	// ADDED on 2023-04-09

	/**
	 * Return system path to the item's drawings folder only creating it if not exists.
	 *
	 * @param int|null $artID  The item ID
	 * @param int|null $procID The process ID
	 *
	 * @return string|null  Null if the path does not exist or is not accessible, or string if the path exists and is accessible
	 */
	public function getPathToDrawingsFolder(int $artID = null, int $procID = null) :? string
	{
		$rootPath     = App::getDrawingsPath();
		$pathToFolder = null;

		switch (true)
		{
			case ( $artID &&  $procID) :
				$pathToFolder = FilesystemHelper::fixPath(implode(DIRECTORY_SEPARATOR, [$rootPath, $artID, $procID]));
			break;

			case (!$artID &&  $procID) :
				$pathToFolder = null;
			break;

			case ( $artID && !$procID) :
				$pathToFolder = FilesystemHelper::fixPath(implode(DIRECTORY_SEPARATOR, [$rootPath, $artID]));
			break;

			default :
				$pathToFolder = FilesystemHelper::fixPath($rootPath);
		}

		// Does the folder already exist?
		$exists = is_dir($pathToFolder) && is_readable($pathToFolder) && is_writable($pathToFolder);

		// Attempt to create it.
		if (!$exists)
		{
			FilesystemHelper::makeDirectory($pathToFolder);

			// Does the folder now exist?
			$exists = is_dir($pathToFolder) && is_readable($pathToFolder) && is_writable($pathToFolder);
		}

		return $exists ? $pathToFolder : null;
	}

	// MODiFiED on 2023-04-09
	/**
	 * Stores a new item in the database.
	 *
	 * @param   array  $article  Array containing the POST and FILES data
	 *
	 * @return  int|false  The inserted row ID or false if data was not stored
	 *
	 * @throws  Exception  When the database columns count doesn't equal the form data values count.
	 */
	public function addArticle(array $article) :? int
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

		$filter   = new InputFilter;
		$formData = ArrayHelper::getValue($article, 'form',  [], 'ARRAY');
		$fileData = ArrayHelper::getValue($article, 'files', [], 'ARRAY');
		$retVal   = 0;

		// Validate session userID equals current form editor's userID.
		if ((int) $userID !== (int) ArrayHelper::getValue($formData, 'user', null, 'INT'))
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

		// Dupe-check article number.
		if (true  === $this->existsArticle(ArrayHelper::getValue($formData, 'aid', null, 'INT'), ArrayHelper::getValue($formData, 'number', null, 'STRING')))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ARTICLE_TEXT', $this->language),
					ArrayHelper::getValue($formData, 'number', null, 'STRING')
				)
			]);

			return $retVal;
		}

		// Get number and index.
		$articleNumber = ArrayHelper::getValue($formData, 'number',       '', 'STRING');	// the article number without drawing index
		$articleIndex  = ArrayHelper::getValue($formData, 'drawingindex', '', 'STRING');	// the drawing index
		$artNumber     = trim(sprintf('%s.%s', $articleNumber, $articleIndex));				// the article number including drawing index



		// Pre-check project status.
		// Article(s) must not be addable to non-active project(s).
		// The related project is identifiable from the article number's second characters group.
		$projectNumber = count($numberExploded = explode('.', $artNumber)) >= 5				// a valid article number AAA.BBB.CC.DDDDD.000
			? $numberExploded[1] : null;													// a fully qualified article number (incl. drawing index is) AAA.BBB.CC.DDDDD.000.0

		// If the related project is not active, abort further processing.
		if (!$this->getInstance('project', ['language' => $this->language])->isAvailable(null, $projectNumber))
		{
			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_CREATED_TEXT', $this->language)
			]);

			return $retVal;
		}

		$upload       = &$fileData;
		$uplIndexCUST = ArrayHelper::getValue($upload, 'drawing-cust');    // CUSTOMER-drawing
		$uplIndexFTK  = ArrayHelper::getValue($upload, 'drawing-ftk');     // FRÖTEK-drawing of the same article
		$uplDrawings  = ArrayHelper::getValue($upload, 'drawings');        // Process drawing(s)
		$dummyFiles   = [];

		$isUploadIndexCUST = (is_array($uplIndexCUST) && !empty($uplIndexCUST['tmp_name']));	// Detect whether a new CUSTOMER-drawing is uploading
		$isUploadIndexFTK  = (is_array($uplIndexFTK)  && !empty($uplIndexFTK['tmp_name']));		// Detect whether a new FRÖTEK-drawing is uploading
		$isUploadIndex     = $isUploadIndexCUST || $isUploadIndexFTK;

		$isUplDrawings = (is_array($uplDrawings) && !empty(array_filter(array_column($uplDrawings, 'tmp_name'))));
		$isUpload      = $isUploadIndex || $isUplDrawings;

		// Get reference to received process ids.
		$pids          = ArrayHelper::getValue($formData, 'processes', [], 'ARRAY');
		// Get reference to received process drawings.
		$drawings      = ArrayHelper::getValue($formData, 'drawings',  [], 'ARRAY');
		$pidDrawingMap = array_combine($pids, $drawings);

		// Prepare formData to be stored into the database.
		try
		{
			// No uploads at all.
			if (!$isUpload)
			{
				// Clean up $formData.
				unset($formData['drawings']);

				// Create dummy drawings.
//				$dummyFiles = $this->createDummyFiles($articleNumber, [$artNumber]);	// in addArticle()	DiSABLED on 2023-06-15

				// Init form data to be expected for storing.
				$formData['processes'] = [];

				/*// DiSABLED on 2023-06-15
				$formData['drawing']   = (array_key_exists($artNumber, $dummyFiles)
					? $dummyFiles[$artNumber]
					: [
						'file'   => null,		// holds the full path to the drawing PDF file
						'number' => null,		// holds the drawing number without the index --- NOT required for index pdf because number and index are store in  db-columns
						'index'  => null,		// holds the drawing index number             --- NOT required for index pdf because number and index are store in  db-columns
						'hash'   => null,		// will be md5 hash of uploaded file after storing
						'images' => null		// will hold paths to thumbnail and fullsize images
					]);*/

			}
			// Something is uploading.
			else
			{
				// Article drawing is uploading (CUSTOMER-drawing and/or FRÖTEK-pms).
				if ($isUploadIndex)
				{
					// Clean up $formData.
					if (!$isUplDrawings)	// No process drawing(s) uploading.
					{
						unset($formData['drawings']);

						// Init form data to be expected for storing.
						$formData['processes'] = [];
					}

					// Init form data to be expected for storing.
					if ($isUploadIndexFTK)
					{
						$formData['drawing'] = [
							'file'   => null,    // holds the full path to the drawing PDF file
							'number' => null,    // holds the drawing number without the index --- NOT required for index pdf because number and index are store in separate db-columns
							'index'  => null,    // holds the drawing index number             --- NOT required for index pdf because number and index are store in separate db-columns
							'hash'   => null,    // will be md5 hash of uploaded file after storing
							'images' => null     // will hold paths to thumbnail and fullsize images
						];

						// Read article drawing number from upload data and ...
						$drawingNumber = trim(ArrayHelper::getValue($uplIndexFTK, 'name', '', 'STRING'));
						$drawingNumber = (!empty($drawingNumber) ? pathinfo($drawingNumber, PATHINFO_FILENAME) : $drawingNumber);

						// Compare name of uploaded file to article number and
						// skip this upload (in case of mismatch) along with user feedback.
						if ($drawingNumber !== $artNumber)
						{
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('COM_FTK_ERROR_APPLICATION_ARTICLE_NO_AND_DRAWING_NO_MISMATCH_TEXT', $this->language)
							]);

							return $retVal;
						}

						$drawingNumberPcs = array_map('trim', explode('.', '' . $drawingNumber));

						// ... pre-fill $formData number and index number.
						$formData['drawing']['index']  = array_pop($drawingNumberPcs);    // can be digit or letter - so don't force numeric type
						$formData['drawing']['number'] = implode('.', $drawingNumberPcs);
					}
				}
				// Drawings uploading but no Article drawing.
				else
				{
					// Create dummy files for CUSTOMER-drawing and/or FRÖTEK-pms.
//					$this->createDummyFiles($articleNumber, [$artNumber]);	// in addArticle()	DiSABLED on 2023-06-15
				}

				// Process drawing(s) is/are uploading.
				if ($isUplDrawings && array_key_exists('processes', $formData) && is_array($formData['processes']))
				{
					// Clean up $formData.
					if (!$isUploadIndex)
					{
						// Create dummy drawings.
//						$dummyFiles = $this->createDummyFiles($articleNumber, [$artNumber]);	// in addArticle()

						/*// DiSABLED on 2023-06-15
						// Init form data to be expected for storing.
						$formData['drawing'] = (array_key_exists($artNumber, $dummyFiles)
							? $dummyFiles[$artNumber]
							: [
								'file'   => null,        // holds the full path to the drawing PDF file
								'number' => null,        // holds the drawing number without the index --- NOT required for index pdf because number and index are store in separate db-columns
								'index'  => null,        // holds the drawing index number             --- NOT required for index pdf because number and index are store in separate db-columns
								'hash'   => null,        // will be md5 hash of uploaded file after storing
								'images' => null         // will hold paths to thumbnail and fullsize images
							]);*/
					}

					// Filter drawing numbers in $formData - only accept drawings for associated processes.
					if (array_key_exists('drawings', $formData))
					{
						// Init form data to be expected for storing.
						// Prepare so many process objects to be stored into the database,
						// as there are in uploaded drawings stack.
						$formData['processes'] = array_combine(
							array_keys($pidDrawingMap),
							// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
							array_fill(0, count($pidDrawingMap), [
								'file'   => null,        // holds the full path to the drawing PDF file
								'number' => null,        // holds the drawing number without the index
								'index'  => null,        // holds the drawing index number
								'hash'   => null,        // will be md5 hash of uploaded file after storing
								'images' => null         // will hold paths to thumbnail and fullsize images
							])
						);

						// Separate drawing number and index, as they are stored as separate fields into the database.
						array_filter($pidDrawingMap, function($no, $pid) use(&$formData)
						{
							// Separate drawing number stem and index.
							$drawingNumberPcs = array_map('trim', explode('.', trim('' . $no)));

							$formData['processes'][$pid]['index']  = array_pop($drawingNumberPcs);    // can be digit or letter - so don't force numeric type
							$formData['processes'][$pid]['number'] = implode('.', $drawingNumberPcs);
						}, ARRAY_FILTER_USE_BOTH);

						// Free memory.
						unset($formData['drawings']);
					}
				}
			}
		}
		catch (Exception $e)
		{
			// Do nothing, just suppress the error message
		}

		// NEW - added on 2023-01-25 Sanitize form data object to be stored from data.
		foreach ($formData as &$value)
		{
			if (!is_scalar($value) || !is_string($value)) continue;

			$value = $filter->clean($value, 'STRING');
		}

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = ArrayHelper::toObject($formData, 'stdClass', false);
		}

		// Prepare article data object for file upload processing.
		$articleData = (object) array_filter([
			'formData' => $formData,
			'fileData' => array_filter([
				'drawing-cust' => $uplIndexCUST,       // CUSTOMER-drawing
				'drawing-ftk'  => $uplIndexFTK,        // FRÖTEK-drawing of the same article
				'drawings'     => $uplDrawings         // Process drawing(s)
			])
		]);

		// TODO - As of 2023-04-09 this step requires the article ID to be created first. Save article first.
		/*// DiSABLED on 2023-04-09 - because the upload-step is moved and therefore data modification must not take place at this processing stage.
		// Process file upload.
		if ($isUpload)
		{
			// Upload file.
			try
			{
				$uploaded = $this->uploadArticleFiles($articleData);	// Moved further below to line 1324 ff.
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);

				return $retVal;
			}
		}*/

		// Prepare received article process IDs for storing.
		$processes = array_map('intval', $pids);

		// Convert received article process IDs to JSON object.
		$processes = json_encode($processes, JSON_THROW_ON_ERROR);

		/*// DiSABLED on 2023-04-09 - because the upload-step is moved and therefore data modification must not take place at this processing stage.
		// Prepare article drawing data for storing.
		$formData->drawing = (is_array($formData->drawing) && empty($formData->drawing) ? null : $formData->drawing);
		$formData->drawing = (!is_string($formData->drawing) && !is_null($formData->drawing)
			? json_encode($formData->drawing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
			: $formData->drawing);*/

		// Prepare sanitized data object to be stored from data.
		$rowData = new Registry($formData);

		// Prepare rows columns to store.
		$columns = [
			'number',
			'name',
			'custartno',
			'custartname',
			'processes',
//			'drawingindex',
			'created',
			'created_by'
		];

		// Prepare row data to be stored.
		$values = [
			$db->q($filter->clean($rowData->get('number'))),
			$db->q($filter->clean($rowData->get('name'))),
			$db->q($filter->clean($rowData->get('custartno'))),
			$db->q($filter->clean($rowData->get('custartname'))),
			$db->q($processes),
//			$db->q(filter_var($rowData->get('drawingindex'), FILTER_SANITIZE_STRING)),
			$db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			(int) $userID
		];

		/*// DiSABLED on 2023-04-09 - because the upload-step is moved and therefore this data must not be stored at this processing stage.
		// Store PMS-drawing only if there is one or leave empty to let MySQL set the default column value.
		if (!is_null($formData->drawing))
		{
			$values[] = $db->q($rowData->get('drawing'));
		}

		if (!is_null($formData->customerDrawing))
		{
			$values[] = $db->q($rowData->get('customerDrawing'));
		}

		// Add article drawing only if they're present in the form data.
		if (!is_null($formData->drawing))
		{
			array_push($columns, 'drawing');
		}

		if (!is_null($formData->customerDrawing))
		{
			array_push($columns, 'customerDrawing');
		}*/

		// Check that the columns count equals the values count exactly.
		if (count($columns) != count($values))
		{
			// TODO - translate
			throw new Exception(Text::translate('Columns count and values count mismatch. Data cannot be stored.', $this->language));
		}

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('articles'))
		->columns($db->qn($columns))
		->values(implode(',', $values));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();
		}
		catch (Exception $e)
		{
			$this->logger->log('error', __METHOD__, ['error' => $e->getMessage()]);
			$this->logger->log('info',  __METHOD__, ['query' => strip_tags($query->dump())]);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$insertID = $retVal;
		}

		// Inject item ID into formData object, since it'll be required in the next steps
		// (store metadata, article/process-relations, measuring definitions etc.).
		$formData->aid = $insertID;

		//-> BEGiN: NEW as of 2023-04-09
		try
		{
			// No uploads at all.
			if (!$isUpload)
			{
				// @debug 
if ($user->isProgrammer()) :	// FIXME - why is this still here ???
				// Create dummy drawings.
				$dummyFiles = $this->createDummyFiles($articleNumber, [$artNumber]);	// in addArticle()	Moved here from line 974 on 2023-06-15

				// DiSABLED on 2023-06-15
				$formData->drawing = [
					'file'   => null,		// holds the full path to the drawing PDF file
					'number' => null,		// holds the drawing number without the index --- NOT required for index pdf because number and index are store in  db-columns
					'index'  => null,		// holds the drawing index number             --- NOT required for index pdf because number and index are store in  db-columns
					'hash'   => null,		// will be md5 hash of uploaded file after storing
					'images' => null		// will hold paths to thumbnail and fullsize images
				];
				/*$formData->drawing = (array_key_exists($artNumber, $dummyFiles)
					? $dummyFiles[$artNumber]
					: [
						'file'   => null,		// holds the full path to the drawing PDF file
						'number' => null,		// holds the drawing number without the index --- NOT required for index pdf because number and index are store in  db-columns
						'index'  => null,		// holds the drawing index number             --- NOT required for index pdf because number and index are store in  db-columns
						'hash'   => null,		// will be md5 hash of uploaded file after storing
						'images' => null		// will hold paths to thumbnail and fullsize images
					]);*/

				// Replace property 'formData' to have the article ID available for upload.
				$articleData->formData = $formData;

				// UPDATE created article in the database: Add drawings.
				$rowData = new Registry($formData);

				// Build query.
				$query = $db->getQuery(true)
				->update($db->qn('articles'))
				->where($db->qn('artID') . ' = ' . (int) filter_var($rowData->get('aid'), FILTER_VALIDATE_INT));

				// Add FRÖTEK-drawing DUMMY.
				if ($rowData->get('drawing'))
				{
					$query->set( $db->qn('drawing') . ' = ' . $db->q( json_encode($rowData->get('drawing'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ) );
				}

				// Add CUSTOMER-drawing DUMMY.
				if ($rowData->get('customerDrawing'))
				{
					$query->set( $db->qn('customerDrawing') . ' = ' . $db->q( json_encode( $rowData->get('customerDrawing'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ) );
				}

				// Execute query.
				$db
				->setQuery($query)
				->execute();
endif;
			}
			// Something is uploading.
			else
			{
				// @debug 
if ($user->isProgrammer()) :	// FIXME - why is this still here ???
				// Article drawing is uploading (CUSTOMER-drawing and/or FRÖTEK-pms).
				if ($isUploadIndex)
				{
				}
				// Drawings uploading but no Article drawing.
				else
				{
					// Create dummy files for CUSTOMER-drawing and/or FRÖTEK-pms.
					$this->createDummyFiles($articleNumber, [$artNumber]);	// in addArticle()	DiSABLED on 2023-06-15
				}

				// Process drawing(s) is/are uploading.
				if ($isUplDrawings && array_key_exists('processes', $formData) && is_array($formData['processes']))
				{
					// Clean up $formData.
					if (!$isUploadIndex)
					{
						// Create dummy drawings.
						$dummyFiles = $this->createDummyFiles($articleNumber, [$artNumber]);	// in addArticle()

						// DiSABLED on 2023-06-15
						// Init form data to be expected for storing.
						$formData->drawing = (array_key_exists($artNumber, $dummyFiles)
							? $dummyFiles[$artNumber]
							: [
								'file'   => null,        // holds the full path to the drawing PDF file
								'number' => null,        // holds the drawing number without the index --- NOT required for index pdf because number and index are store in separate db-columns
								'index'  => null,        // holds the drawing index number             --- NOT required for index pdf because number and index are store in separate db-columns
								'hash'   => null,        // will be md5 hash of uploaded file after storing
								'images' => null         // will hold paths to thumbnail and fullsize images
							]);
					}
				}
endif;
				// Replace property 'formData' to have the article ID available for upload.
				$articleData->formData = $formData;

				// Upload file.
				$uploaded = $this->uploadArticleFiles($articleData);	// Moved here from line 1200 ff. on 2023-04-09

				// UPDATE created article in the database: Add drawings.
				$rowData = new Registry($formData);

				// Build query.
				$query = $db->getQuery(true)
				->update($db->qn('articles'))
				->where($db->qn('artID') . ' = ' . (int) filter_var($rowData->get('aid'), FILTER_VALIDATE_INT));

				// Execution flag. Is required because in 1 case the article data must not be updated.
				$executeUpdate = true;

				switch (true)
				{
					// NO article drawing upload.
					case !$isUpload :
						$query
						->set([
							$db->qn('number')       . ' = ' . $db->q(filter_var($rowData->get('number'), FILTER_SANITIZE_STRING)),
							$db->qn('drawingindex') . ' = ' . $db->q(filter_var($rowData->get('drawingindex'), FILTER_SANITIZE_STRING))
						]);
					break;

					// Article drawing(s) upload - but NO process(es) drawing(s) upload.
					case  $isUploadIndex && !$isUplDrawings :
						$query
						->set([
							$db->qn('number')       . ' = ' . $db->q(filter_var($rowData->get('number'), FILTER_SANITIZE_STRING)),
							$db->qn('drawingindex') . ' = ' . $db->q(filter_var($rowData->get('drawingindex'), FILTER_SANITIZE_STRING))
						]);

						// Add FRÖTEK-drawing if there is a new upload in pipeline.
						if ($rowData->get('drawing'))
						{
							$query->set( $db->qn('drawing') . ' = ' . $db->q($rowData->get('drawing')) );
						}

						// Add CUSTOMER-drawing if there is a new upload in pipeline.
						if ($rowData->get('customerDrawing'))
						{
							$query->set( $db->qn('customerDrawing') . ' = ' . $db->q($rowData->get('customerDrawing')) );
						}
					break;

					// Process drawing(s) upload - but NO article drawing(s) upload, but...
					case !$isUploadIndex &&  $isUplDrawings :
						// ... base query covers it.
						$executeUpdate = false;
					break;

					// BOTH article drawing(s)  AND  process drawing(s) upload.
					case  $isUploadIndex &&  $isUplDrawings :
						$query
						->set([
							$db->qn('number')       . ' = ' . $db->q(filter_var($rowData->get('number'), FILTER_SANITIZE_STRING)),
							$db->qn('drawingindex') . ' = ' . $db->q(filter_var($rowData->get('drawingindex'), FILTER_SANITIZE_STRING))
						]);

						// Add FRÖTEK-drawing if there is a new upload in pipeline.
						if ($rowData->get('drawing'))
						{
							$query->set( $db->qn('drawing') . ' = ' . $db->q($rowData->get('drawing')) );
						}

						// Add CUSTOMER-drawing if there is a new upload in pipeline.
						if ($rowData->get('customerDrawing'))
						{
							$query->set( $db->qn('customerDrawing') . ' = ' . $db->q($rowData->get('customerDrawing')) );
						}
					break;
				}

				if ($executeUpdate)
				{
					// Execute query.
					$db
					->setQuery($query)
					->execute();
				}
			}
		}
		catch (Exception $e)
		{
			// Log error.
			$this->logger->log('error', __METHOD__, ['error' => $e->getMessage()]);
			$this->logger->log('info',  __METHOD__, ['query' => strip_tags($query->dump())]);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return $retVal;
		}
		//<- END

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		// This is supposed to be the language this data has been composed with.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Store article meta information.
		$metaData   = array_intersect_key((array) $formData, ['lng' => null, 'lngID' => null, 'description' => null/*, 'instructions' => null*/]);
		$metaStored = $this->storeArticleMeta($formData->aid, $metaData);

		// Store article process(es) drawing(s).
		$processes  = array_map('intval', $pids);

		// Get all registered app languages and drop currently active language
		$langs = array_filter($this->getInstance('languages', ['language' => $this->language])->getList(['filter' => Lizt::FILTER_ACTIVE, 'onlyTags' => true]), function($language)
		{
			// Load language object into Registry for less error-prone data access while further processing.
			$language = new Registry($language);

			return $language->get('tag') !== $this->language;
		});

		// Store placeholders for all other languages that are not the currently active app language.
		$isError = false;

		array_walk($langs, function($language) use(&$formData, &$isError)    // in addArticle()
		{
			// On error continue with next lang.
			if ($isError)
			{
				return;
			}

			// Load language object into Registry for less error-prone data access while further processing.
			$language = new Registry($language);

			// Skip prev. stored metadata object.
			if ($language->get('lngID') == $formData->lngID)
			{
				return;
			}

			$formData->lngID        = $language->get('lngID');
			$formData->lng          = $language->get('tag');
			$formData->description  = null;
//			$formData->instructions = null;				// Disabled on 2022-05-19, as of SM's decision (data is not required)

			// Store article meta information placeholder.
			$metaData = array_intersect_key((array) $formData, ['lng' => null, 'lngID' => null, 'description' => null/*, 'instructions' => null*/]);

			$isError  = !$this->storeArticleMeta($formData->aid, $metaData);
		});

		// Flag variable.
		$metaPlaceholdersStored = !$isError;

		// Store article process(es) drawing(s).
		if ($uplDrawings)
		{
			$processesStored = $this->storeArticleProcesses($formData->aid, $formData->processes);
		}

		$measurementDefinitionsStored = true;

		if (!property_exists($formData, 'procMeasurementDefinition'))
		{
			$formData->procMeasurementDefinition = [];
		}

		// Flag variable.
		$measurementDefinitionsStored = $this->storeArticleMeasurementDefinitions($formData->aid, $formData->procMeasurementDefinition);

		$processesBanderolesStored = true;

		if (!property_exists($formData, 'procMeasurementBanderole'))
		{
			$formData->procMeasurementBanderole = [];
		}

		if (property_exists($formData, 'processes') && is_countable($formData->processes) && count($formData->processes))
		{
			// Flag variable.
			$processesBanderolesStored = $this->storeArticleProcessBanderoles($formData->aid, $formData->processes, $formData->procMeasurementBanderole);
		}

		return ($insertID > 0 ? $formData->aid : null);
	}

	// MODiFiED on 2023-04-09
	/**
	 * Updates an existing item in the database.
	 *
	 * @param   array  $article  Array containing the POST and FILES data
	 *
	 * @return  int|false  The inserted row ID or false if data was not stored
	 *
	 * @throws  Exception  When the database columns count doesn't equal the form data values count.
	 */
	public function updateArticle(array $article) :? int
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

		$filter   = new InputFilter;
		$formData = ArrayHelper::getValue($article, 'form',  [], 'ARRAY');
		$fileData = ArrayHelper::getValue($article, 'files', [], 'ARRAY');
		$retVal   = 0;

		// Validate session userID equals current form editor's userID.
		if ((int) $userID !== (int) ArrayHelper::getValue($formData, 'user', null, 'INT'))
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

			return $retVal;
		}

		// Existence check.
		if (false === $this->existsArticle(ArrayHelper::getValue($formData, 'aid', null, 'INT')))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such article: %s', $this->language), $formData['number'])
			]);

			return $retVal;
		}

		// Dupe-check.
		if ($tmpArticle = $this->getArticleByNumber(ArrayHelper::getValue($formData, 'number', null, 'STRING')))
		{
			// Compare both IDs.
			// If they're different, then another item already uses the number this item shall use, which is not allowed.
			if (is_a($tmpArticle, ' \Entity\Article')
			&&  is_int($tmpArticle->get('artID'))
			&& ($tmpArticle->get('artID') != ArrayHelper::getValue($formData, 'aid', null, 'INT'))
			) {
				Messager::setMessage([
					'type' => 'info',
					'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ARTICLE_TEXT', $this->language),
						ArrayHelper::getValue($formData, 'number', null, 'STRING')
					)
				]);

				return $retVal;
			}
			else
			{
				// Free memory.
				unset($tmpArticle);
			}
		}

		// Get number and index.
		$articleNumber = ArrayHelper::getValue($formData, 'number',       '', 'STRING');	// the article number without drawing index
		$articleIndex  = ArrayHelper::getValue($formData, 'drawingindex', '', 'STRING');	// the drawing index
		$artNumber     = trim(sprintf('%s.%s', $articleNumber, $articleIndex));				// the article number including drawing index

		// Pre-check project status.
		// Article(s) must not be addable to non-active project(s).
		// The related project is identifiable from the article number's second characters group.
		$projectNumber = count($numberExploded = explode('.', $artNumber)) >= 5		// a valid article number AAA.BBB.CC.DDDDD.000
			? $numberExploded[1] : null;											// a fully qualified article number (incl. drawing index is) AAA.BBB.CC.DDDDD.000.0

		// If the related project is not active, abort further processing.
		if (!$this->getInstance('project', ['language' => $this->language])->isAvailable(null, $projectNumber))
		{
			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_NOT_SAVED_TEXT', $this->language)
			]);

			return $retVal;
		}

		$upload        = &$fileData;
		$uplIndexCUST  = ArrayHelper::getValue($upload, 'drawing-cust');    // CUSTOMER-drawing
		$uplIndexFTK   = ArrayHelper::getValue($upload, 'drawing-ftk');     // FRÖTEK-drawing of the same article
		$uplDrawings   = ArrayHelper::getValue($upload, 'drawings');        // Process drawing(s)
//		$dummyFiles    = [];

		$isUploadIndexCUST = (is_array($uplIndexCUST) && !empty($uplIndexCUST['tmp_name']));	// Detect whether a new CUSTOMER-drawing is uploading
		$isUploadIndexFTK  = (is_array($uplIndexFTK)  && !empty($uplIndexFTK['tmp_name']));		// Detect whether a new FRÖTEK-drawing is uploading
		$isUploadIndex     = $isUploadIndexCUST || $isUploadIndexFTK;

		$isUplDrawings = (is_array($uplDrawings)  && !empty(array_filter(array_column($uplDrawings, 'tmp_name'))));
		$isUpload      = $isUploadIndex || $isUplDrawings;

		// Get reference to received process ids.
		$pids          = ArrayHelper::getValue($formData, 'processes', [], 'ARRAY');
		// Get reference to received process drawings.
		$drawings      = ArrayHelper::getValue($formData, 'drawings',  [], 'ARRAY');
		$pidDrawingMap = array_combine($pids, $drawings);

		// Prepare formData to be stored into the database.
		// Something is uploading.
		if ($isUpload)
		{
			// Article drawing is uploading (by CUSTOMER and/or FRÖTEK).
			if ($isUploadIndex)
			{
				// Clean up $formData
				if (!$isUplDrawings)
				{
					try
					{
						unset($formData['drawings']);

						// Init form data to be expected for storing.
						$formData['processes'] = [];
					}
					catch (Exception $e)
					{
						// Do nothing, just suppress the error message
					}
				}

				// Init form data to be expected for storing.
				if ($isUploadIndexFTK)
				{
					$formData['drawing'] = [
						'file'   => null,	// holds the full path to the drawing PDF file
						'number' => null,	// holds the drawing number without the index --- NOT required for index pdf because number and index are store in separate db-columns
						'index'  => null,	// holds the drawing index number             --- NOT required for index pdf because number and index are store in separate db-columns
						'hash'   => null,	// will be md5 hash of uploaded file after storing
						'images' => null	// will hold paths to thumbnail and fullsize images
					];

					// Pre-fill article drawing number and index number
					$drawingNumber = trim(ArrayHelper::getValue($uplIndexFTK, 'name', '', 'STRING'));
					$drawingNumber = (!empty($drawingNumber) ? pathinfo($drawingNumber, PATHINFO_FILENAME) : $drawingNumber);

					// Compare name of uploaded file to article number and
					// skip this upload (in case of mismatch) along with user feedback.
					if ($drawingNumber !== $artNumber)
					{
						Messager::setMessage([
							'type' => 'error',
							'text' => Text::translate('COM_FTK_ERROR_APPLICATION_ARTICLE_NO_AND_DRAWING_NO_MISMATCH_TEXT', $this->language)
						]);

						return $retVal;
					}

					$drawingNumberPcs = array_map('trim', explode('.', '' . $drawingNumber));

					// ... pre-fill $formData number and index number.
					$formData['drawing']['index']  = array_pop($drawingNumberPcs);	// can be digit or letter - so don't force numeric type
					$formData['drawing']['number'] = implode('.', $drawingNumberPcs);
				}
			}
			// Drawing(s) uploading but no Article drawing(s).
			// Consider the creation of dummy files for FRÖTEK- and CUSTOMER-drawing, for new items.
			/*else {}*/

			// Process drawing(s) is/are uploading.
			if ($isUplDrawings && array_key_exists('processes', $formData) && is_array($formData['processes']))
			{
				// Clean up $formData.
				if (!$isUploadIndex)
				{
					// Init form data to be expected for storing.
					$formData['drawing'] = [];


				}

				// Filter drawing numbers in $formData - only accept drawings for associated processes.
				if (array_key_exists('drawings', $formData))
				{
					// Init form data to be expected for storing.
					// Prepare so many process objects to be stored into the database,
					// as there are in uploaded drawings stack.
					$formData['processes'] = array_combine(
						array_keys($pidDrawingMap),
						// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
						array_fill(0, count($pidDrawingMap), [
							'file'   => null,		// holds the full path to the drawing PDF file
							'number' => null,		// holds the drawing number without the index
							'index'  => null,		// holds the drawing index number
							'hash'   => null,		// will be md5 hash of uploaded file after storing
							'images' => null		// will hold paths to thumbnail and fullsize images
						])
					);

					// Separate drawing number and index, as they are stored as separate fields into the database.
					array_filter($pidDrawingMap, function($no, $pid) use(&$formData)
					{
						// Separate drawing number stem and index.
						$drawingNumberPcs = array_map('trim', explode('.', trim('' . $no)));

						$formData['processes'][$pid]['index']  = array_pop($drawingNumberPcs);	// can be digit or letter - so don't force numeric type
						$formData['processes'][$pid]['number'] = implode('.', $drawingNumberPcs);

					}, ARRAY_FILTER_USE_BOTH);

					// Free memory.
					unset($formData['drawings']);
				}
			}
		}

		// Sanitize form data object to be stored from data.
		foreach ($formData as &$value)
		{
			if (!is_scalar($value) || !is_string($value)) continue;

			$value = $filter->clean($value, 'STRING');
		}

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = ArrayHelper::toObject($formData, 'stdClass', false);
		}

		// Prepare article data object for file upload processing.
		$articleData = (object) array_filter([
			'formData' => $formData,
			'fileData' => array_filter([
				'drawing-cust' => $uplIndexCUST,	// CUSTOMER-drawing
				'drawing-ftk'  => $uplIndexFTK,		// FRÖTEK-drawing of the same article
				'drawings'     => $uplDrawings		// Process drawing(s)
			])
		]);

		// Process file upload.
		if ($isUpload)
		{
			// Upload file.
			try
			{
				$uploaded = $this->uploadArticleFiles($articleData);

				// Get reference to updated data.
				$formData = $articleData->formData;
				$fileData = $articleData->fileData;
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);

				return $retVal;
			}
		}

		// Prepare received article process IDs for storing.
		$processes = array_map('intval', $pids);

		// Convert received article process IDs to JSON object.
		$processes = json_encode($processes, JSON_THROW_ON_ERROR);

		// Prepare sanitized data object to be stored from data.
		$rowData = new Registry($formData);

		// Prepare rows to be stored.
		$values  = [
			$db->qn('name')        . ' = ' . $db->q($filter->clean($rowData->get('name'))),
			$db->qn('custartno')   . ' = ' . $db->q($filter->clean($rowData->get('custartno'))),
			$db->qn('custartname') . ' = ' . $db->q($filter->clean($rowData->get('custartname'))),
			$db->qn('processes')   . ' = ' . $db->q($processes),
			$db->qn('modified')    . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			$db->qn('modified_by') . ' = ' . (int) $userID
		];

		// Build query.
		$query   = $db->getQuery(true)
		->update($db->qn('articles'))
		->where($db->qn('artID')   . ' = ' . (int) filter_var($rowData->get('aid'), FILTER_VALIDATE_INT))
		->set($values);

		switch (true)
		{
			// NO article drawing upload.
			case !$isUpload :
				$query
				->set([
					$db->qn('number')       . ' = ' . $db->q($rowData->get('number')),
					$db->qn('drawingindex') . ' = ' . $db->q($rowData->get('drawingindex'))
				]);
			break;

			// Article drawing(s) upload - but NO process(es) drawing(s) upload.
			case  $isUploadIndex && !$isUplDrawings :
				$query
				->set([
					$db->qn('number')       . ' = ' . $db->q($rowData->get('number')),
					$db->qn('drawingindex') . ' = ' . $db->q($rowData->get('drawingindex'))
				]);

				// Add FRÖTEK-drawing if there is a new upload in pipeline.
				if ($rowData->get('drawing'))
				{
					$query->set( $db->qn('drawing') . ' = ' . $db->q($rowData->get('drawing')) );
				}

				// Add CUSTOMER-drawing if there is a new upload in pipeline.
				if ($rowData->get('customerDrawing'))
				{
					$query->set( $db->qn('customerDrawing') . ' = ' . $db->q($rowData->get('customerDrawing')) );
				}
			break;

			// Process drawing(s) upload - but NO article drawing(s) upload, but...
			case !$isUploadIndex &&  $isUplDrawings :
				// ... base query covers it.
			break;

			// BOTH article drawing(s)  AND  process drawing(s) upload.
			case  $isUploadIndex &&  $isUplDrawings :
				$query
				->set([
					$db->qn('number')       . ' = ' . $db->q($rowData->get('number')),
					$db->qn('drawingindex') . ' = ' . $db->q($rowData->get('drawingindex'))
				]);

				// Add FRÖTEK-drawing if there is a new upload in pipeline.
				if ($rowData->get('drawing'))
				{
					$query->set( $db->qn('drawing') . ' = ' . $db->q($rowData->get('drawing')) );
				}

				// Add CUSTOMER-drawing if there is a new upload in pipeline.
				if ($rowData->get('customerDrawing'))
				{
					$query->set( $db->qn('customerDrawing') . ' = ' . $db->q($rowData->get('customerDrawing')) );
				}
			break;
		}

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			// Log error.
			$this->logger->log('error', __METHOD__, ['error' => $e->getMessage()]);
			$this->logger->log('info',  __METHOD__, ['query' => strip_tags($query->dump())]);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

//			$affectedRows = null;

			return false;
		}

		// Get language to fetch its id to be stored with this data.
		$lang = $this->getInstance('language', ['language' => $this->language])->getLanguageByTag($formData->lng ?? null);
		$lang = new Registry($lang);

		// Inject id of currently active app language.
		// This is supposed to be the language this data has been composed with.
		$formData->lngID = (is_a($lang, 'Joomla\Registry\Registry') ? $lang->get('lngID') : '1');    // assign current app lang or fall back to DE

		// Store article meta information.
		$metaData   = array_intersect_key((array) $formData, ['lng' => null, 'lngID' => null, 'description' => null/*, 'instructions' => null*/]);
		$metaStored = $this->storeArticleMeta($formData->aid, $metaData);

		// Store article process(es) drawing(s).
		$processes = array_map('intval', $pids);

		if ($isUpload)
		{


			// Modify processes list for pre-store deletion dropping all IDs for which a new file upload is going on.
			array_walk($formData->processes, function($process, $pid) use(&$processes)
			{
				if (is_string($process))
				{
					$idx = array_search((int) $pid, $processes, true);

					if (false !== $idx)
					{
						unset($processes[$idx]);
					}
				}

				return true;
			});
		}

		// Next block moved here from {@link storeArticleProcesses()} on 2019-10-24
		if (false === $this->deleteArticleProcesses($formData->aid, $processes))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_DATABASE_UPDATING_ARTICLE_PROCESSES_TEXT', $this->language)
			]);

			return $retVal;
		}

		// Store article process(es) drawing(s).
        $abbrStore = true;
        if(isset($formData->org_abbr)){
            //echo "<pre>";print_r($formData->processes);exit;
            $db = $this->db;
            $tuples = [];

            // Prepare artID <--> procID tuples.

            foreach ($formData->org_abbr as $procID => $drawing)
            {
                // Load drawing object into Registry object for less error-prone data access while further processing.
                $tmp = new Registry($drawing);
                //$abbr = 6;
                $drawingNo  = $tmp->get('number');
                $col  = $tmp->get('org_abbr');
                $step       = explode('.', $drawingNo);
                $step       = end($step);
                $drawing    = json_encode($drawing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                //print_r($col);exit;
                /*foreach($_POST['orgAbbr'] as $abbr){
                    $tuples[]   = $artID . ',' . (int) $procID . ',' . $db->q(trim(''. $drawingNo)) . ',' . (int) $step . ',' . $db->q($drawing). ',' . $abbr;

                }*/

                $tuples[] = $formData->aid . ',' . (int) $formData->processes . ',' . $db->q(trim(''. $drawingNo)) . ',' . (int) $step . ',' . $db->q($drawing);
            }
            // Build query.
            $query = $db->getQuery(true)
                ->insert($db->qn('article_process'))
                ->columns(
                    $db->qn([
                        'artID',
                        'procID',
                        'drawingnumber',
                        'step',
                        'drawing'
                        //'orgAbbr'
                    ])
                )
                ->values($tuples);

            // Execute query.
            if (count($tuples))
            {
                $db
                    ->setQuery($query)
                    ->execute();
            }
        }

		$processesStored = true;

		// Store article process(es) drawing(s).
		if ($isUpload)
		{
			// Preserve only those processes that are currently uploading.
			$formData->processes = array_filter($formData->processes, function($process)
			{
				return is_string($process);
			});

			$processesStored = $this->storeArticleProcesses($formData->aid, $formData->processes);
		}

		$measurementDefinitionsStored = true;

		if (!property_exists($formData, 'procMeasurementDefinition'))
		{
			$formData->procMeasurementDefinition = [];
		}

		$measurementDefinitionsStored = $this->storeArticleMeasurementDefinitions($formData->aid, $formData->procMeasurementDefinition);

		$processesBanderolesStored = true;

		if (!property_exists($formData, 'procMeasurementBanderole'))
		{
			$formData->procMeasurementBanderole = [];
		}

		if (property_exists($formData, 'processes') && is_countable($formData->processes) && count($formData->processes))
		{
			$processesBanderolesStored = $this->storeArticleProcessBanderoles($formData->aid, $formData->processes, $formData->procMeasurementBanderole);
		}

		return (($affectedRows > 0 && $metaStored && $processesStored && $measurementDefinitionsStored && $processesBanderolesStored && $abbrStore) ? $formData->aid : false);
	}

	public function lockArticle(int $artID)
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

		// Load article from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($artID);

		if (!is_a($item, ' \Entity\Article') || !$item->get('artID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive articles at all?
		if (false === $this->canLockArticle($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this article.
		if (false === $this->articleIsLockable($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn('articles'))
			->set([
				$db->qn('blocked')     . ' = ' . $db->q('1'),
				$db->qn('blockDate')   . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
				$db->qn('blocked_by')  . ' = ' . $db->q((int) $user->get('userID'))
			])
			->where($db->qn('artID')   . ' = ' . $db->q($artID));

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

		return $artID;
	}

	public function unlockArticle(int $artID)
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

		// Load article from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$item = $this->getItem($artID);

		if (!is_a($item, ' \Entity\Article') || !$item->get('artID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover articles at all?
		if (false === $this->canRestoreArticle($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this article.
		if (false === $this->articleIsRestorable($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('articles'))
		->set([
			$db->qn('blocked')     . ' = ' . $db->q('0'),
			$db->qn('blockDate')   . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('blocked_by')  . ' = NULL'
		])
		->where($db->qn('artID')   . ' = ' . $db->q($artID));

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

		return $artID;
	}

	public function archiveArticle(int $artID)	// This is currently the opposite of restoreArticle - it blockes and archives an accessible item
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

		// Load article from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$article = $this->getItem($artID);

		if (!is_a($article, ' \Entity\Article') || !$article->get('artID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to archive articles at all?
		if (false === $this->canArchiveArticle($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this article.
		if (false === $this->articleIsArchivable($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn('articles'))
			->set([
				$db->qn('archived')    . ' = ' . $db->q('1'),
				$db->qn('archiveDate') . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
				$db->qn('archived_by') . ' = ' . $db->q((int) $user->get('userID')),
			])
			->where($db->qn('artID')   . ' = ' . $db->q($artID));

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

		return $artID;
	}

	public function restoreArticle(int $artID)		// This is currently the opposite of archiveArticle - it restored an archived item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

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

		// Load article from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$article = $this->getItem($artID);

		if (!is_a($article, ' \Entity\Article') || !$article->get('artID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover articles at all?
		if (false === $this->canRestoreArticle($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this article.
		if (false === $this->articleIsRestorable($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('articles'))
		->set([
			$db->qn('archived')    . ' = ' . $db->q('0'),
			$db->qn('archiveDate') . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('archived_by') . ' = NULL',
		])
		->where($db->qn('artID')   . ' = ' . $db->q($artID));

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

		return $artID;
	}

	public function deleteArticle(int $artID)		// This is currently the opposite of recoverArticle - it deletes an accessible item
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

		// Load article from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$article = $this->getItem($artID);

		if (!is_a($article, ' \Entity\Article') || !$article->get('artID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Is this user allowed to delete articles at all?
		if (false === $this->canDeleteArticle($artID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this article.
		if (false === $this->articleIsDeletable($artID))
		{
			return false;	// Messages will be set by the function called.
		}

		try
		{
			$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));

			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn('articles'))
			->set([
				$db->qn('trashed')    . ' = ' . $db->q('1'),
				$db->qn('trashDate')  . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('trashed_by') . ' = ' . $db->q((int) $user->get('userID')),
				$db->qn('deleted')    . ' = ' . $db->q($now->format('Y-m-d H:i:s')),
				$db->qn('deleted_by') . ' = ' . $db->q((int) $user->get('userID'))
			])
			->where($db->qn('artID')  . ' = ' . $db->q($artID));

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			// Reset AUTO_INCREMENT count.
			$db
			->setQuery('ALTER TABLE `articles` AUTO_INCREMENT = 1')
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

		return $artID;
	}

	public function recoverArticle(int $artID)		// This is currently the opposite of deleteArticle - it recovers a deleted item
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

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

		// Load article from db first. This not only prevents us from unnecessary function calls,
		// but it serves us further article data required to call the files' deletion function below.
		$article = $this->getItem($artID);

		if (!is_a($article, ' \Entity\Article') || !$article->get('artID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		/*// Is this user allowed to recover articles at all?
		if (false === $this->canRestoreArticle($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		/*// Check for entities depending on this article.
		if (false === $this->articleIsRestorable($artID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('articles'))
		->set([
			$db->qn('trashed')    . ' = ' . $db->q('0'),
			$db->qn('trashDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('trashed_by') . ' = NULL',
			$db->qn('deleted')    . ' = ' . $db->q(FTKRULE_NULLDATE),
			$db->qn('deleted_by') . ' = NULL'
		])
		->where($db->qn('artID')  . ' = ' . $db->q($artID));

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

		return $artID;
	}


	protected function existsArticle($artID = null, string $articleNumber = null) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Function parameter check.
		if (is_null($artID) && is_null($articleNumber))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function requires at least 1 argument.');
		}

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('articles', 'a'))
		->select($db->qn('a.artID'));

		// This function is not called PRIOR CREATION (no artID available) and PRIOR DELETION (artID is available).
		// Hence, 'artID' must not be the only column to check for!
		switch (true)
		{
			// Should find existing organisation identified by orgID + orgName.
			case (!empty($artID) && !empty($articleNumber)) :
				$query
				->where($db->qn('a.artID') . ' = ' . (int) $artID)
				->where('LOWER(' . $db->qn('a.number') . ') = LOWER( TRIM(' . $db->q(trim($articleNumber)) . ') )');
			break;

			// Should find existing organisation identified by orgID.
			case (!empty($artID) && (int) $artID > 0) :
				$query
				->where($db->qn('a.artID') . ' = ' . (int) $artID);
			break;

			// Should find existing organisation identified by orgName.
			case (!empty($articleNumber) && trim($articleNumber) !== '') :
				$query
				->where('LOWER(' . $db->qn('a.number') . ') LIKE LOWER( TRIM(' . $db->q(trim($articleNumber)) . ') )');
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

	protected function canDeleteArticle(int $artID)	: bool
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

	protected function articleIsDeletable(int $artID) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// An article may have children (parts). In that case it is not deletable.
		/* if (false !== ($dependencies = $this->articleHasDependencies($artID)))
		{
			$dependencies = \array_filter($dependencies, function($val, $type)
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
					\array_push(
						$msg,
						sprintf(
							Text::translate(
								\mb_strtoupper('COM_FTK_SYSTEM_MESSAGE_' . ($count == 1 ? \mb_substr($type, 0, \mb_strlen($type) - 1) : $type) . '_IS_DEPENDENCY_TEXT'),
								$this->language
							),
							$count
						)
					);
				}
				else
				{
					\array_push(
						$msg,
						sprintf(
							Text::translate('COM_FTK_SYSTEM_MESSAGE_' . \mb_strtoupper($type) . '_ARE_DEPENDENCIES_TEXT', $this->language),
							$count
						)
					);
				}
			}

			Messager::setMessage([
				'type' => 'error',
				'text' => sprintf("%s<br/>%s",
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_DELETION_DEPENDENCIES_TEXT', $this->language),
					Text::translate(sprintf('%s', implode($msg, '<br>')), $this->language)
				)
			]);

			return false;
		}*/

		if ($this->articleHasDependencies($artID))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_DELETION_DEPENDENCIES_TEXT', $this->language)
			]);

			return false;
		}

		return true;
	}

	protected function articleHasDependencies(int $artID) : bool
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
		$from1 = $db->getQuery(true)
		->setQuery('
			SELECT `x`.`count` AS `parts`
			FROM (
				SELECT COUNT(`partID`) AS `count`
				FROM `parts`
				WHERE `artID` = ' . $artID . '
			) AS `x`'
		);

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('m.parts'))
		->from('(' . $from1 . ') AS `m`');

		// Execute query.
		try
		{
			$parts = $db->setQuery($query)->loadResult();
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

		return $parts > 0;
	}


	protected function hasArticleMeta(int $artID, string $lang, bool $isNotNull = false, $returnData = false)
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
		$table = 'article_meta';
		$query = $db->getQuery(true)
		->from($db->qn($table))
		->where($db->qn('artID') . ' = ' . $artID)
		->where($db->qn('language') . ' = ' . $db->q(trim($lang)));

		if ($isNotNull)
		{
			$query
//			->where('(' . $db->qn('description') . ' IS NOT NULL OR ' . $db->qn('instruction') . ' IS NOT NULL)');
			->where($db->qn('description') . ' IS NOT NULL');
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
			->select('COUNT(' . $db->qn('artID') . ') AS ' . $db->qn('count'));
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

	protected function storeArticleMeta(int $artID, array $articleMeta = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!count(array_filter($articleMeta)))
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

		// Build query.
		$rowData = new Registry($articleMeta);

		$hasMeta = $this->hasArticleMeta($artID, $rowData->get('lng'));

		$artDescription  = filter_var($rowData->get('description'),  FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		$artDescription  = (is_null($artDescription)  || trim($artDescription)  == '') ? "NULL" : $db->q(trim($artDescription));
//		$artInstructions = filter_var($rowData->get('instructions'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
//		$artInstructions = (is_null($artInstructions) || trim($artInstructions) == '') ? "NULL" : $db->q(trim($artInstructions));

		// Build query.
		if (!$hasMeta)
		{
			$query = $db->getQuery(true)
			->insert($db->qn('article_meta'))
			->columns(
				$db->qn([
					'artID',
					'lngID',
					'description',
//					'instructions',
					'language'
				])
			)
			->values(implode(',', [
				$artID,
				(int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT),
				$artDescription,
//				$artInstructions,
				$db->q(filter_var($rowData->get('lng'), FILTER_SANITIZE_STRING))
			]));
		}
		else
		{
			$query = $db->getQuery(true)
			->update($db->qn('article_meta'))
			->set([
				$db->qn('description')  . ' = ' . $artDescription,
//				$db->qn('instructions') . ' = ' . $artInstructions,
				$db->qn('language')     . ' = ' . $db->q(filter_var($rowData->get('lng'), FILTER_SANITIZE_STRING))
			])
			->where($db->qn('artID')  . ' = ' . $artID)
			->where($db->qn('lngID')  . ' = ' . (int)  filter_var($rowData->get('lngID'), FILTER_VALIDATE_INT));
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

	protected function deleteArticleMeta(int $artID) : bool
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
		->delete($db->qn('article_meta'))
		->where($db->qn('artID') . ' = ' . $artID);

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

		// return $artID;
		return true;
	}


	// MODiFiED on 2023-04-09
	/**
	 * Add description...
	 *
	 * @param   string $articleNumber  The full article number without index. This is used as target directory name.
	 * @param   array  $numbers        A list of target filenames.
	 *
	 * @return  array  Array of arrays where every array holds the following relevant details of the generated file:
	 *                 - the relative path to the generated file
	 *                 - the drawing number without the index
	 *                 - the drawing index number
	 *                 - the md5 hash of uploaded file after storing
	 *                 - array of relative paths to generated thumbnail file(s)
	 *
	 * @todo    Refactor into {@see \ \Utility\Dummy} class
	 */
	protected function createDummyFiles(string $articleNumber, array $numbers = []) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user   = App::getAppUser();
//		$userID = $user->get('userID');

		$item = $this->getArticleByNumber($articleNumber);

		if (!$item->get('artID'))
		{
			throw new RuntimeException(Text::translate('Dummy file creation requires an existing item. The current item has no ID and is therefore considered non-existent.', $this->language));
		}

		$tmp  = $numbers;

		if ($item && count(array_filter($numbers)))
		{
			$dummyPDF  = App::getDrawingDummy()->pdf;
			$dummyPNG  = App::getDrawingDummy()->thumb;

			$storePath = $this->getPathToDrawingsFolder($item->get('artID'));	// in createDummyFiles()

			// Check if dummy file(s) exist(s) or abort and return.

			/*// DiSABLED on 2023-04-09 - Existence check and directory creation is applied when {@see getPathToDrawingsFolder()} is called.
			// Check if store location exists or create it.
			if (!is_dir(FTKPATH_DRAWINGS))	// disabled
			{
				// Create path to storage directory.
				try
				{
					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === mkdir(FTKPATH_DRAWINGS, null, true))	// moved to App-class
					{
						Messager::setMessage([
							'type' => 'error',
							// TODO - translate
							'text' => Text::translate('Asset directory could not be created. Drawing could not be saved.', $this->language)
						]);

						return [];
					}
				}
				catch (Exception $e)
				{
					throw new RuntimeException(sprintf('Asset directory could not be created: %s', $e->getMessage()));
				}
			}*/

			// Check if store location exists or create it.
			if (!is_dir($storePath))
			{
				if (false === mkdir($storePath))
				{
					// TODO - translate
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Asset directory could not be created.', $this->language)
					]);

					return [];
				}
			}

			// Check if store location exists or create it.
			if (!is_readable($storePath))
			{
				// TODO - translate
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate('Asset directory created but not readable.', $this->language)
				]);

				return [];
			}

			// Check if store location exists or create it.
			if (!is_writable($storePath))
			{
				// TODO - translate
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate('Asset directory created but not writable.', $this->language)
				]);

				return [];
			}

			// Build store path (location + file)
			$tmp = [];

			array_walk($numbers, function ($number) use (&$storePath, &$dummyPDF, &$dummyPNG, &$tmp)
			{
				$targetPathPDF = sprintf('%s%s%s.pdf',        $storePath, DIRECTORY_SEPARATOR, $number);
				$targetPathPNG = sprintf('%s%s%s__thumb.png', $storePath, DIRECTORY_SEPARATOR, $number);

				copy($dummyPDF, $targetPathPDF);
				copy($dummyPNG, $targetPathPNG);

				// Calculate hash (md5) from copied dummy file.
				$hashPDF       = hash_file('md5', $targetPathPDF);

				$targetPathPDF = FilesystemHelper::fixPath($targetPathPDF);
				$targetPathPDF = str_ireplace(FTKPATH_BASE, '', $targetPathPDF);
				$targetPathPDF = UriHelper::fixURL($targetPathPDF);

				$targetPathPNG = FilesystemHelper::fixPath($targetPathPNG);
				$targetPathPNG = str_ireplace(FTKPATH_BASE, '', $targetPathPNG);
				$targetPathPNG = UriHelper::fixURL($targetPathPNG);

				$tmp[$number]  = [
					'file'   => $targetPathPDF,									// holds the full path to the drawing PDF file
//					'number' => substr($number, 0, mb_strlen($number) - 2),		// holds the drawing number without the index
					'number' => substr($number, 0, strrpos($number, '.')),		// holds the drawing number without the index
//					'index'  => substr($number, mb_strlen($number) - 1),		// holds the drawing index number
					'index'  => substr($number, strrpos($number, '.') + 1),		// holds the drawing index number
					'hash'   => $hashPDF,										// the md5 hash of uploaded file after storing
					'images' => [$targetPathPNG]								// will hold paths to thumbnail and fullsize images
				];

				/* If the extracted drawing index is not a single character
				 * it is supposed to be a customer drawing, which is a combination
				 * of the FTK article number followed by an underline followed
				 * by the CUSTOMER article number. In this case the separation
				 * is reversed and the index is unset.
				 */
				if (mb_strlen($tmp[$number]['index']) > 1)
				{
					$tmp[$number]['number'] .= '.' . $tmp[$number]['index'];
					$tmp[$number]['index']   = null;
				}

				return true;
			});
		}

		return $tmp;
	}

	// MODiFiED on 2023-04-09
	//@throws RuntimeException on file upload validation
	// TODO - utilize https://blueimp.github.io/jQuery-File-Upload/
	protected function uploadArticleFiles($article)	: bool // files refer to the drawings that belong to an article ( image or pdf )
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$formData = $article->formData ?? [];
		$fileData = $article->fileData ?? [];

		// Get item.
		$item = $this->getArticleByNumber($formData->number);

		$isUploadIndex = false;
		$isUplDrawings = false;

		// Handle file upload.
		try
		{
			if (count(array_filter($fileData)))
			{
				$upload = (!empty($fileData) ? $fileData : []);

				// Get reference(s) to article index drawing(s).
				$uplIndexCUST = ArrayHelper::getValue($upload, 'drawing-cust', [], 'ARRAY');	// CUSTOMER-drawing, will be an array containing these information {"name":"","type":"","tmp_name":"","error":"","size":""}
				$uplIndexFTK  = ArrayHelper::getValue($upload, 'drawing-ftk',  [], 'ARRAY');	// FRÖTEK-drawing of the same article, will be an array containing these information {"name":"","type":"","tmp_name":"","error":"","size":""}

				// Get reference(s) to article process drawing(s).
				$uplDrawings  = ArrayHelper::getValue($upload, 'drawings',     [], 'ARRAY');	// Process drawing(s)

				$isUploadIndexCUST = !empty(ArrayHelper::getValue($uplIndexCUST, 'tmp_name'));	// Detect whether a new CUSTOMER-drawing is uploading
				$isUploadIndexFTK  = !empty(ArrayHelper::getValue($uplIndexFTK,  'tmp_name'));	// Detect whether a new FRÖTEK-drawing is uploading
				$isUploadIndex     = $isUploadIndexCUST || $isUploadIndexFTK;

				$uplDrawings   = array_filter($uplDrawings, function($arr) { return !empty(ArrayHelper::getValue($arr, 'tmp_name')); });
				$isUplDrawings = (is_array($uplDrawings) && !empty($uplDrawings));	// This notation expects files accessed from Joomla\Input\Files

				$isUpload      = $isUploadIndex || $isUplDrawings;

				// ARTICLE DRAWING-Upload (in PDF format)
				if ($isUploadIndex)
				{
					$drawings = [
						'CUSTOMER' => $uplIndexCUST,
						'FTK'      => $uplIndexFTK
					];

					// Process each uploaded article-drawing.
					foreach ($drawings as $author => $arrTmpFile)
					{
						if (empty(ArrayHelper::getValue($arrTmpFile, 'tmp_name')))
						{
							continue;
						}

						// NOTE:  The variable name is semantically wrong.
						//        It refers to an array going to be populated with uploaded file information.
						//        This name is used in advance for the self-named variable used for article process drawings upload.
						$procData = (property_exists($formData, 'drawing')
							? (array) $formData->drawing
							: $procData = [
								'file'   => null,		// holds the full path to the drawing PDF file
								'number' => null,		// holds the drawing number without the index --- NOT required for index pdf because number and index are store in separate db-columns
								'index'  => null,		// holds the drawing index number             --- NOT required for index pdf because number and index are store in separate db-columns
								'hash'   => null,		// will be md5 hash of uploaded file after storing
								'images' => null		// will hold paths to thumbnail and fullsize images
							]
						);

						// Thumbnail-upload.
						// Check $_FILES['upfile']['error'] value(s).
						// see: {@link https://www.php.net/manual/de/features.file-upload.errors.php}
						switch ($arrTmpFile['error'])
						{
							case UPLOAD_ERR_OK:
							break;

							case UPLOAD_ERR_INI_SIZE:	// Wert: 1; die hochgeladene Datei überschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Größe.
								throw new RuntimeException(sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INI_SIZE_TEXT', $this->language), ini_get('upload_max_filesize')));

							case UPLOAD_ERR_FORM_SIZE:	// Wert: 2; die hochgeladene Datei überschreitet die in dem HTML Formular mittels des versteckten Feldes MAX_FILE_SIZE angegebene maximale Dateigröße.
								throw new RuntimeException(sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_FORM_SIZE_TEXT', $this->language), FTKRULE_UPLOAD_MAX_SIZE));

							case UPLOAD_ERR_PARTIAL:	// Wert: 3; die Datei wurde nur teilweise hochgeladen.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_PARTIAL_TEXT', $this->language));

							case UPLOAD_ERR_NO_FILE:	// Wert: 4; es wurde keine Datei hochgeladen.
								// FIXME - wrong reference for index image upload
								break;	// do nothing, just leave drawing data object received as is - preserving pre-filled information

							case UPLOAD_ERR_NO_TMP_DIR:	// Wert: 6; fehlender temporärer Ordner. Eingeführt in PHP 5.0.3.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_NO_TMP_DIR_TEXT', $this->language));

							case UPLOAD_ERR_CANT_WRITE:	// Wert: 7; speichern der Datei auf die Festplatte ist fehlgeschlagen. Eingeführt in PHP 5.1.0.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_UNREADABLE_TEXT', $this->language));

							case UPLOAD_ERR_EXTENSION:	// Wert: 8; eine PHP Erweiterung hat den Upload der Datei gestoppt. Eingeführt in PHP 5.2.0.
														// PHP bietet keine Möglichkeit an, um festzustellen, welche Erweiterung das Hochladen der Datei gestoppt hat.
														// Überprüfung aller geladenen Erweiterungen mittels phpinfo() könnte helfen.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INTERRUPTED_BY_PHP_EXTENSION_TEXT', $this->language));
						}

						// You should also check filesize here.
						// TODO - Check against defined max. upload file size
						if ($arrTmpFile['size'] > FTKRULE_UPLOAD_MAX_SIZE)
						{
							throw new RuntimeException(sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INI_SIZE_TEXT', $this->language), FTKRULE_UPLOAD_MAX_SIZE));
						}

						// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
						// ALWAYS Check MIME Type by yourself !!
						$finfo = new finfo(FILEINFO_MIME_TYPE);

						// Find extension of uploaded file in array of allowed file extensions.
						if (false === $ext = mb_strtolower(array_search( $finfo->file(ArrayHelper::getValue($arrTmpFile, 'tmp_name')), ['pdf' => 'application/pdf'], true )))
						{
							throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_FILE_FORMAT_TEXT', $this->language));
						}

						$fname = ArrayHelper::getValue($arrTmpFile, 'name');

	//					$storePath     = FTKPATH_DRAWINGS . DIRECTORY_SEPARATOR . $formData->number;	// DiSABLED on 2023-04-09 - replaced by next line
						$storePath     = $this->getPathToDrawingsFolder($item->get('artID'));	// in uploadArticleFiles() -> isUploadIndex
	//					$dirname       = $formData->number;		// DiSABLED on 2023-04-09 - replaced by next line
						$dirname       = $item->get('artID');	// ADDED on    2023-04-09 - The article number is no longer the directory name. The article ID is now used.
						$basename      = null;
						$drawingNumber = null;
						$index         = null;

						/*// DiSABLED on 2023-04-09 - Existence check and directory creation is applied when {@see getPathToDrawingsFolder()} is called.
						// Check if store location exists or create it.
						if (!is_dir(FTKPATH_DRAWINGS))	// disabled
						{
							// Create path to storage directory.
							// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
							// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
							try
							{
								if (false === mkdir(FTKPATH_DRAWINGS, null, true))	// moved to App-class
								{
									Messager::setMessage([
										'type' => 'error',
										// TODO - translate
										'text' => Text::translate('Asset directory could not be created. Drawing could not be saved.', $this->language)
									]);

									return false;
								}
							}
							catch (Exception $e)
							{
								throw new RuntimeException(sprintf('Asset directory could not be created: %s', $e->getMessage()));
							}
						}*/

						// Check if store location exists or create it.
						if (!is_dir($storePath))
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
	//							'text' => Text::translate('Asset directory could not be created.', $this->language)	// DiSABLED on 2023-04-09 - replaced by next line
								'text' => Text::translate('Upload aborted. The item\'s asset directory cannot be found.', $this->language)
							]);

							return false;
						}

						/* Validate the uploading drawing file name matches the expected file naming pattern, e.g.: AAA.BBB.CC.DDDDD.000.0.pdf
						 * File name is not the article number - potential customer drawing to handle.
						 * DO NOT USE $_FILES['upfile']['name'] WITHOUT ANY VALIDATION !!
						 */
						if (!preg_match('/' . FTKREGEX_DRAWING_FILE . '/', $fname))
						{
							$procData['number'] = sprintf('%s_%s',
								$formData->number,
								($formData->custartno ?: mb_strtoupper($author))
							);

							$drawingNumber = $basename = $procData['number'];
						}
						// File name is the article number - internal drawing to handle.
						else
						{
							$basename  = explode('.', '' . $fname);
										 array_pop($basename);		// drop drawing-index
							$basename  = implode('.', $basename);	// re-join

							// Separate drawing number stem from index.
							$drawingNumber = array_map('trim', explode('.', trim($basename)));
							$index         = array_pop($drawingNumber);
							$drawingNumber = implode('.', $drawingNumber);
						}

						/*// DiSABLED on 2023-04-09 - Accessibility is checked when the path is fetched
						// Check if store location exists or create it.
						if (!is_readable($storePath))
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Asset directory created but not readable.', $this->language)
							]);

							return false;
						}*/

						/*// DiSABLED on 2023-04-09 - Accessibility is checked when the path is fetched
						// Check if store location exists or create it.
						if (!is_writable($storePath))
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Asset directory created but not writable.', $this->language)
							]);

							return false;
						}*/

						// Build store path (location + file)
						$filePath = sprintf('%s%s%s.%s', $storePath, DIRECTORY_SEPARATOR, $basename, $ext);
						$filePath = FilesystemHelper::fixPath($filePath);

						// Name every file uniquely. In this example, obtain safe unique name from its binary data.
						if (!move_uploaded_file(ArrayHelper::getValue($arrTmpFile, 'tmp_name'), $filePath))  // save file
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Failed to save index file.', $this->language)	// TODO - translate
							]);

							return false;
						}

						/*** Upload success. ***/

						// TODO - create and register the meta data object as it is used with the drawings upload

						// Calculate hash (md5) from uploaded PDF.
						$procData['hash'] = hash_file('md5', $filePath);

	//					$thumbLocations = ImageHelper::makeImageFromPDF($filePath, ['width'  => FTKRULE_DRAWING_THUMB_WIDTH,  'suffix' => '__thumb']);	// in uploadArticleFiles()
						$thumbLocations = ImageHelper::makeImageFromPDF($filePath, ['height' => FTKRULE_DRAWING_THUMB_HEIGHT, 'suffix' => '__thumb']);	// in uploadArticleFiles()

						$tmp = [];

						foreach ($thumbLocations as &$path)
						{
							$path = FilesystemHelper::fixPath($path);
							$path = str_ireplace(FTKPATH_BASE, '', $path);
							$path = UriHelper::fixURL($path);
						}

						$procData['images'] = array_unique(array_merge($thumbLocations, $tmp));

						// Free memory.
						unset($tmp);

						// Prepare the file location(s) to be stored in the database.
						$storePath = FilesystemHelper::fixPath($storePath);
						$storePath = str_ireplace(FTKPATH_BASE, '', $storePath);
						$storePath = UriHelper::fixURL($storePath);

						// Update formData object.
						$filePath = sprintf('%s/%s.%s', $storePath, $basename, $ext);
						$storePath = FilesystemHelper::fixPath($storePath);

						$procData['file'] = $filePath;

						// Convert to JSON object.
						switch (mb_strtoupper($author))
						{
							case 'CUSTOMER' :
								$formData->customerDrawing = json_encode($procData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
							break;

							case 'FTK' :
								$formData->drawing = json_encode($procData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
							break;
						}
					}
				}

				// PROCESS DRAWINGS-Upload (in PDF format)
				if ($isUplDrawings)
				{
					// process drawing(s) uploaded
					$uplDrawings = $upload['drawings'];

					// iterate over article process ids and process the corresponding file upload if there is any.
					array_filter($formData->processes, function(&$procData, $procID) use(&$uplDrawings, &$formData, &$item)
					{
						// Get name of uploaded file.
						$pdfName  = mb_strtolower( implode('.', [ ArrayHelper::getValue($procData, 'number'), ArrayHelper::getValue($procData, 'index'), 'pdf' ]) );

						// Find uploaded file in passed in uploading files.
						// $uploadID = \array_search($pdfName, array_map('mb_strtolower', ArrayHelper::getValue($uplDrawings, 'name')));	// Valid when input is $_FILES and not Joomla\Input\Files
						$uploadID = array_search($pdfName, array_map('mb_strtolower', array_column($uplDrawings, 'name')));	// for info about \array_column() see https://www.php.net/manual/de/function.array-column.php

						// TODO - when in debug mode log skipped file.
						// If drawing name is not the name of an uploaded file, then skip it and continue with next.
						if (false === $uploadID)
						{
							return false;
						}

						// Check $_FILES['upfile']['error'] value(s).
						// see: {@link https://www.php.net/manual/de/features.file-upload.errors.php}
						// switch ($uplDrawings['error'][$uploadID])
						switch (ArrayHelper::getValue(array_column($uplDrawings, 'error'), $uploadID))
						{
							case UPLOAD_ERR_OK:
							break;

							case UPLOAD_ERR_INI_SIZE:	// Wert: 1; die hochgeladene Datei überschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Größe.
								// TODO - translate
								throw new RuntimeException(sprintf('Drawing %d exceeds filesize limit.', $procID));

							case UPLOAD_ERR_FORM_SIZE:	// Wert: 2; die hochgeladene Datei überschreitet die in dem HTML Formular mittels des versteckten Feldes MAX_FILE_SIZE angegebene maximale Dateigröße.
								// TODO - translate
								throw new RuntimeException(sprintf('Drawing %d exceeds filesize limit.', $procID));

							case UPLOAD_ERR_PARTIAL:	// Wert: 3; die Datei wurde nur teilweise hochgeladen.
								throw new RuntimeException(sprintf('Drawing %d uploaded incompletely.', $procID));

							case UPLOAD_ERR_NO_FILE:	// Wert: 4; es wurde keine Datei hochgeladen.

								$formData->processes[$procID] = json_encode($procData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
								return false;	// continue with next file

							case UPLOAD_ERR_NO_TMP_DIR:	// Wert: 6; fehlender temporärer Ordner. Eingeführt in PHP 5.0.3.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_NO_TMP_DIR_TEXT', $this->language));

							case UPLOAD_ERR_CANT_WRITE:	// Wert: 7; speichern der Datei auf die Festplatte ist fehlgeschlagen. Eingeführt in PHP 5.1.0.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_UNREADABLE_TEXT', $this->language));

							case UPLOAD_ERR_EXTENSION:	// Wert: 8; eine PHP Erweiterung hat den Upload der Datei gestoppt. Eingeführt in PHP 5.2.0.
														// PHP bietet keine Möglichkeit an, um festzustellen, welche Erweiterung das Hochladen der Datei gestoppt hat.
														// Überprüfung aller geladenen Erweiterungen mittels phpinfo() könnte helfen.
								throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INTERRUPTED_BY_PHP_EXTENSION_TEXT', $this->language));
						}

						// You should also check filesize here.
						// TODO - Check against defined max. upload file size
						// if ($uplDrawings['size'][$uploadID] > FTKRULE_UPLOAD_MAX_SIZE)
						if (ArrayHelper::getValue(array_column($uplDrawings, 'size'), $uploadID) > FTKRULE_UPLOAD_MAX_SIZE)
						{
							// TODO - translate
							throw new RuntimeException(sprintf('Drawing of process #%d exceeds filesize limit.', $procID));
						}

						// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
						// ALWAYS Check MIME Type by yourself !!
						$finfo = new finfo(FILEINFO_MIME_TYPE);

						// You should also check filesize here.
						// TODO - Check against defined max. upload file size
						if (!is_a($finfo, 'finfo'))
						{
							// TODO - translate
							throw new RuntimeException(sprintf('Failed to detect mime type of process #%d drawing.', $procID));
						}

						// Detect real extension of uploading file.
						$ext = mb_strtolower(array_search( $finfo->file(ArrayHelper::getValue(array_column($uplDrawings, 'tmp_name'), $uploadID)), ['pdf' => 'application/pdf'], true ));

						// Find extension of uploaded file in array of allowed file extensions.
						if (false === $ext)
						{
							// TODO - translate
							throw new RuntimeException(sprintf('Drawing of process #%d has an invalid file format.', $procID));
						}

						/* Validate the uploading drawing file name matches the expected file naming pattern,
						 * which is: AAA.BBB.CC.DDDDD.000.0.pdf
						 * File name is not the article number - potential customer drawing to handle.
						 * DO NOT USE $_FILES['upfile']['name'] WITHOUT ANY VALIDATION !!
						 */
						if (!preg_match('/' . FTKREGEX_DRAWING_FILE . '/', $fname = ArrayHelper::getValue(array_column($uplDrawings, 'name'), $uploadID)))
						{
							$tmpProcess = $this->getInstance('process', ['language' => $this->language])->getItem((int) $procID);	// will be an instance of  \Entity\Process

							throw new RuntimeException(sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_NAMING_SCHEME_TEXT', $this->language), $tmpProcess->get('name')));
						}

						unset($finfo);

	//					$storePath     = FTKPATH_DRAWINGS . DIRECTORY_SEPARATOR . $formData->number;	// DiSABLED on 2023-04-09 - replaced by next line
						$storePath     = $this->getPathToDrawingsFolder($item->get('artID'), $procID);	// in uploadArticleFiles() -> isUplDrawings
	//					$dirname       = $formData->number;		// DiSABLED on 2023-04-09 - replaced by next line
						$dirname       = $procID;				// ADDED on    2023-04-09 - The article number is no longer the directory name. The process ID is now used.
						$basename      = null;
						$drawingNumber = null;
						$index         = null;

						/*// DiSABLED on 2023-04-09 - Existence check and directory creation is applied when {@see getPathToDrawingsFolder()} is called.
						// Check if store location exists or create it.
						if (!is_dir(FTKPATH_DRAWINGS))	// disabled
						{
							if (false === mkdir(FTKPATH_DRAWINGS))	// moved to App-class
							{
								// TODO - translate
								Messager::setMessage([
									'type' => 'error',
									'text' => Text::translate('Asset directory could not be created.', $this->language)
								]);

								return false;
							}
						}*/

						// Check if store location exists or create it.
						if (!is_dir($storePath))
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Asset directory creation failed.', $this->language)
							]);

							return false;
						}

						$basename  = explode('.', '' . $fname);
									 array_pop($basename);		// drop drawing-index
						$basename  = implode('.', $basename);	// re-join

						// Separate drawing number stem and index.
						$drawingNumber = array_map('trim', explode('.', trim('' . $basename)));

						$index = array_pop($drawingNumber);
						$drawingNumber = implode('.', $drawingNumber);

						// TODO - when in debug mode log skipped file.
						// Compare name of uploaded file and process drawing number and silently skip this upload
						if ($drawingNumber !== ArrayHelper::getValue($procData, 'number'))
						{
							return false;	// continue with next file
						}

						// TODO
						//
						// compare dirname's and drawing number's base scheme ( aaa.bbb.cc.ddddd ) to validate the uploaded number belongs this part
						// i.e. dirname: SSS.N8M.EE.00200.000 / number: SSS.N8M.EE.00100.040		--> is a mismatch, because the first 4 blocks do not match
						// i.e. dirname: SSS.N8M.EE.00200.000 / number: SSS.N8M.EE.00200.040		--> is a match

						/*// DiSABLED on 2023-04-09 - Accessibility is checked when the path is fetched
						// Check if store location exists or create it.
						if (!is_readable($storePath))
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Asset directory created, but not readable.', $this->language)
							]);

							return false;
						}*/

						/*// DiSABLED on 2023-04-09 - Accessibility is checked when the path is fetched
						// Check if store location exists or create it.
						if (!is_writable($storePath))
						{
							// TODO - translate
							Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Asset directory created, but not writable.', $this->language)
							]);

							return false;
						}*/

						// Build store path (location + file)
						$filePath = sprintf('%s%s%s.%s', $storePath, DIRECTORY_SEPARATOR, $basename, $ext);
						$filePath = FilesystemHelper::fixPath($filePath);

						// Name every file uniquely. In this example, obtain safe unique name from its binary data.
						if (!move_uploaded_file(ArrayHelper::getValue(array_column($uplDrawings, 'tmp_name'), $uploadID), $filePath))   // save process drawing
						{
							throw new RuntimeException(sprintf('Drawing of process #%d failed to upload. File not saved.', $procID));   // TODO - translate
						}

						/*** Upload success. ***/

						// TODO
						//
						// this step should be timed differently to not block page reload that long
						// can we utilise an AJAX-request?
						// would NGINX behave equally?

						// Calculate hash (md5) from stored file.
						$procData['hash'] = hash_file('md5', $filePath);

						$thumbLocations = ImageHelper::makeImageFromPDF($filePath, ['height' => FTKRULE_DRAWING_THUMB_HEIGHT, 'suffix' => '__thumb']);	// in uploadArticleFiles()

						$tmp = [];

						foreach ($thumbLocations as &$path)
						{
							$path = FilesystemHelper::fixPath($path);
							$path = str_ireplace(FTKPATH_BASE, '', $path);
							$path = UriHelper::fixURL($path);
						}

						$procData['images'] = array_unique(array_merge($thumbLocations, $tmp));

						// Free memory.
						unset($tmp);

						// Now prepare the storage location value for storing into the database
						$storePath = FilesystemHelper::fixPath($storePath);
						$storePath = str_ireplace(FTKPATH_BASE, '', $storePath);
						$storePath = UriHelper::fixURL($storePath);

						// Update formData object.
						$storePath = sprintf('%s/%s.%s', $storePath, $basename, $ext);
						$storePath = FilesystemHelper::fixPath($storePath);

						$procData['file']  = $storePath;

						// Compress data via conversion to a JSON object
						$formData->processes[$procID] = json_encode($procData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

						return true;

					}, ARRAY_FILTER_USE_BOTH);
				}
			}
		}
		catch (ImagickException $e)
		{
			$this->logger->log('error', $e->getMessage());

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('Failed to save the uploaded pictures. Check the log file for details.', $this->language)
			]);

			return false;
		}
		catch (JsonException $e)
		{
			$this->logger->log('error', $e->getMessage());

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('Failed to process the uploaded pictures. Check the log file for details.', $this->language)
			]);

			return false;
		}

		return true;
	}

	// MODiFiED on 2023-04-09
	protected function deleteArticleFiles($article)	: bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!is_object($article))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an object representing a single article.');
		}

//		$dirPath = FTKPATH_DRAWINGS . DIRECTORY_SEPARATOR . $article->get('number');	// DiSABLED on 2023-04-09 - replaced by next line
		$dirPath = $this->getPathToDrawingsFolder($article->get('artID'));	// in deleteArticleFiles()

		if (is_dir($dirPath))
		{
			return FilesystemHelper::deleteDirectory( FilesystemHelper::fixPath($dirPath) );
		}

		return true;
	}

    public function updateArticleOrganisation($artID, $pid, $abbr)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Init shorthand to database object.
        $db = $this->db;
        //print_r($db);exit;
        //echo "artID:-".$artID;echo "procID:-".$pid;echo "Abbr:-".$abbr;exit;
        /* Force UTF-8 encoding for proper display of german Umlaute
         * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
         */
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        try
        {
            $query1 = $db->getQuery(true)
                ->update($db->qn('article_process'))
                ->set($db->qn('org_abbr') . ' = ' . $abbr)
                ->where($db->qn('artID') . ' = ' . $artID)
                ->where($db->qn('procID') . ' = ' . $pid);
            $db->setQuery($query1)->execute();
        }
        catch (Exception $e)
        {
            $this->logger->log('error', $e->getMessage());

            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate('Failed to store the article processes. Check the log file for details.', $this->language)
            ]);

            return false;
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return true;
    }

	protected function storeArticleProcesses(int $artID, array $articleProcesses, array $articleProcessesOrdering = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		if (!is_object($articleProcesses))
		{
			$articleProcesses = (object) $articleProcesses;
		}

		try
		{
			// Prepare arg $articleProcesses for further processing
			switch (true)
			{
				case is_array($articleProcesses) :
					// do nothing - we want it to be an array as we're going to execute array functions
				break;

				case is_object($articleProcesses) :
					$tmp = [];

					array_filter((array) $articleProcesses, function($proc, $pid) use(&$tmp)
					{
						$tmp[$pid] = (is_string($proc) ? json_decode($proc, null, 512, JSON_THROW_ON_ERROR) : $proc);

						return true;

					}, ARRAY_FILTER_USE_BOTH);

					$articleProcesses = $tmp;

					unset($tmp);
				break;

				case is_string($articleProcesses) :
					$articleProcesses = (is_string($articleProcesses) ? json_decode($articleProcesses, null, 512, JSON_THROW_ON_ERROR) : $articleProcesses);
				break;
			}

			// TODO - integrate check for a process' existence to prevent dead ids from being stored.

			$tmp = [];

			array_filter($articleProcesses, function($proc, $pid) use(&$tmp)
			{
				$tmp[$pid] = $proc;

			}, ARRAY_FILTER_USE_BOTH);

			$articleProcesses = $tmp;

			// Free memory.
			unset($tmp);

			// Store article processes.
			$tuples = [];

			// Prepare artID <--> procID tuples.
			foreach ($articleProcesses as $procID => $drawing)
			{
				// Load drawing object into Registry object for less error-prone data access while further processing.
				$tmp = new Registry($drawing);

				$drawingNo  = $tmp->get('number');
				$step       = explode('.', $drawingNo);
				$step       = end($step);
				$drawing    = json_encode($drawing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

				$tuples[]   = $artID . ',' . (int) $procID . ',' . $db->q(trim(''. $drawingNo)) . ',' . (int) $step . ',' . $db->q($drawing);
			}

			// Build query.
			$query = $db->getQuery(true)
			->insert($db->qn('article_process'))
			->columns(
				$db->qn([
					'artID',
					'procID',
					'drawingnumber',
					'step',
					'drawing'
				])
			)
			->values($tuples);

			// Execute query.
			if (count($tuples))
			{
				$db
				->setQuery($query)
				->execute();
			}
		}
		/*catch (JsonException $e)
		{
			$this->logger->log('error', $e->getMessage());

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}*/
		catch (Exception $e)
		{
			$this->logger->log('error', $e->getMessage());

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('Failed to store the article processes. Check the log file for details.', $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	protected function storeArticleProcessBanderoles(int $artID, array $articleProcesses, array $articleProcessBanderoles) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		try
		{
			// Prepare arg $articleProcesses for further processing
			switch (true)
			{
				case is_array($articleProcesses) :
					// do nothing - we want it to be an array as we're going to execute array functions
				break;

				case is_object($articleProcesses) :
					$tmp = [];

					array_filter((array) $articleProcesses, function($proc, $pid) use(&$tmp)
					{
						$tmp[$pid] = (is_string($proc) ? json_decode($proc, null, 512, JSON_THROW_ON_ERROR) : $proc);

						return true;

					}, ARRAY_FILTER_USE_BOTH);

					$articleProcesses = $tmp;

					unset($tmp);
				break;

				case is_string($articleProcesses) :
					$articleProcesses = (is_string($articleProcesses) ? json_decode($articleProcesses, null, 512, JSON_THROW_ON_ERROR) : $articleProcesses);
				break;
			}

			// Build query to reset the 'hasBanderole' flag for all article processes to '0'.
			$query1 = $db->getQuery(true)
			->update($db->qn('article_process'))
			->set($db->qn('hasBanderole') . ' = ' . $db->q('0'))
			->where($db->qn('artID') . ' = ' . $artID);

			// Build query to update the 'hasBanderole' flag for such article process(es) to '1'.
			$query2 = $db->getQuery(true)
			->update($db->qn('article_process'))
			->set($db->qn('hasBanderole') . ' = ' . $db->q('1'))
			->where($db->qn('artID')  . ' = ' . $artID)
			->where($db->qn('procID') . ' IN(' . implode(',', array_keys($articleProcessBanderoles)) . ')');

			// Execute reset query.
			$db
			->setQuery($query1)
			->execute();

			// Execute update query.
			if (count($articleProcessBanderoles))
			{
				$db
				->setQuery($query2)
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

	protected function deleteArticleProcesses(int $artID, array $preserveIDs = []) : bool
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

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->delete($db->qn('article_process'))
			->where($db->qn('artID') . ' = ' . $artID);

			// Consider processes to preserve.
			if (count($preserveIDs))
			{
				$query->where($db->qn('procID') . ' NOT IN(' . implode(',', $preserveIDs) . ')');
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

			return false;
		}

		// return $artID;
		return true;
	}

    public function getOrganisationAbbr()
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        //fetching all the parts id from table parts table where artID is equal to parts tables artID and latest parts limit upto 1000
        $query = $db->getQuery(true)
            ->from($db->qn('organisations', 'p'))
            ->select([$db->qn('p.org_abbr'),$db->qn('p.orgID')])
            ->where($db->qn('p.blocked')       . ' = '  . $db->q('0'))
            ->where($db->qn('p.trashed')       . ' = '  . $db->q('0'))
            ->where($db->qn('p.org_abbr')       . ' != ' . '""')
        ;

        try {
            $artNum = $db->setQuery($query)->loadAssocList();
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
	protected function storeArticleMeasurementDefinitions(int $artID, array $measurementDefinitions = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$user = App::getAppUser();

		//@debug - log what comes in
		/*$this->logger->log(
			'info',
			sprintf('User #%d triggered %s() for article #%d.', $user->get('userID'), __METHOD__, $artID),
			$measurementDefinitions
		);*/

		//@debug - log current total definitions count
		/*$this->logger->log(
			'info',
			sprintf('Current total definitions count #%d.', count($this->getDefinedMeasuringPoints())),
			[]
		);*/

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Init connection paramters.
		$table    = 'article_process_mp_definition';
		$columns  = DatabaseHelper::getTableColumns($table);
		$columns  = array_combine(array_values($columns), array_fill_keys($columns, "NULL"));
		$registry = new Registry;

		// Dump processes to be handled.
		$pids     = [];

		// Dump rows data to store.
		$tuples   = [];

		try
		{
			// Get existing measuring definitions.
			$current  = $this->getDefinedMeasuringPoints($artID);	// TODO - renamo to 'existingDefinitions'

			//@debug - log what is currently there
			/*$this->logger->log(
				'info',
				sprintf('Current measuring definitions for article #%d.', $artID),
				$current
			);*/

			// Filter all collections for empty values.
			array_filter($measurementDefinitions, function($collection, $procID) use(&$db, &$artID, &$current, &$pids, &$columns, &$registry, &$tuples)
			{
				// Collect process id. It's going to be required further below.
				$pids[] = $procID;

				// Prepare row data.
				array_map(function(&$rowData) use(&$db, &$artID, &$procID, &$columns, &$registry, &$tuples)
				{
					// Reset registry.
					$registry->loadArray($columns);

					$registry->set('artID',  $artID);
					$registry->set('procID', $procID);

					// Sanitize data.
					switch (true)
					{
						case is_array($rowData)  :
							$mpDatatype = ArrayHelper::getValue($rowData, 'mpDatatype');

							// Sanitize user input.
							foreach ($rowData as $key => &$val)
							{
								// Trim whitespace.
								$val = trim($val);

								// If input looks like a decimal number, parse proper float value.
								if ($key == 'mp')
								{
									/* Strip out characters that don't satisfy one of these patterns:
									 *
									 * ABC#00.00
									 * ABC.00.00
									 */
									/* DISABLED on 2021-05-03 after introduction of new mp scheme
									if (preg_match('/[^A-Za-z0-9#\.]/ui', $val))
									{
										$tmpVal = $val;

										$val = preg_replace('/[^A-Za-z0-9#\.]/ui', '', $val);

										Messager::setMessage([
											'type' => 'notice',
											'text' => sprintf(Text::translate('COM_FTK_HINT_MEASURING_POINT_NAME_FIXED_TEXT', $this->language), $tmpVal, $val)
										]);

										unset($tmpVal);
									}*/
								}

								// If input looks like a decimal number, parse proper float value.
								if (in_array($key, ['mpInput','mpNominal','mpLowerTol','mpUpperTol']))
								{
									if (preg_match('/^(\d*([.,])?\d+)+/i', $val) && !preg_match('/[a-zA-Z ]/', $val))
									{
										$val = StringHelper::parseFloat($val, true);
									}

									if (in_array($key, ['mpNominal','mpLowerTol','mpUpperTol']) && preg_match('/(\d*([.,])?\d+)+/i', $val))
									{
										$val = StringHelper::parseFloat($val, true);
									}
								}

								// Fix input like '01' to '1'.
								if (preg_match('/^0\d+/', $val))
								{
									$val = ltrim($val, '0');
								}

								// Replace empty value with "NULL" value or add quotes as a pre-requisite for storing into the database .
								$val = (is_null($val) || trim($val) == '' || $val == null ? "NULL" : $db->q($val));
                                //$val = (is_null($val) || trim($val) === '' ? "NULL" : (is_numeric($val) ? $val : $db->q($val)));

                            }

							// Force "NULL" value when 'mpDatatype' = 'boolval'.
							if ($mpDatatype == 'boolval')
							{
								if (array_key_exists('mpLowerTol', $rowData))
								{
									$rowData['mpLowerTol'] = "NULL";
								}

								if (array_key_exists('mpUpperTol', $rowData))
								{
									$rowData['mpLowerTol'] = "NULL";
								}
							}
						break;

						// We accept only data collections.
						default :
							$rowData = null;
					}

					// Load sanitized data into registry.
					$registry->loadArray($rowData);
                   // echo "<pre>";print_r($registry->loadArray($rowData));exit;
					// Generate SQL string (tuples).
					$tuples[] = implode(',', $registry->toArray());

					// Return input object.
					return $rowData;

				}, $collection);

			}, ARRAY_FILTER_USE_BOTH);
            //echo "<pre>";print_r($rowData);exit;
			//@debug - log what is currently there
			/*$this->logger->log(
				'info',
				sprintf('New measuring definitions for article #%d.', $artID),
				[
					'pids'   => $pids,
					'tuples' => $tuples
				]
			);*/

			//
			// 1. Delete existing data.
			//

			// Build query
			$query   = $db->getQuery(true)
			->delete($db->qn($table))
			->where($db->qn('artID')  . ' = ' . $artID);

			//@debug - log what is currently there
			/*$this->logger->log(
				'info',
				sprintf('Delete existing data for article #%d', $artID),
				[
					'sql' => $query->dump()
				]
			);*/

			// Limit deletion to specified processes. DISABLED on 2021-05-19, because it caused that deleted definitions were preserved.
			// Instead, delete all and write back what is passed.
			/*if (!empty($pids))
			{
				$query
				->where($db->qn('procID') . ' IN(' . implode(',', array_map('intval', $pids)) . ')');
			}*/

			// Execute query.
			$db
			->setQuery($query)
			->execute();

			// If there's no new data to be stored we're done.
			if (!count($tuples))
			{
				//@debug - log what is currently there
				$this->logger->log(
					'info',
					sprintf('There are no measuring definitions to store for article #%d.', $artID),
					$current
				);

				//@debug - log new total definitions count
				$this->logger->log(
					'info',
					sprintf('New total definitions count #%d.', count($this->getDefinedMeasuringPoints()))
				);

				//@debug - add ruler to visually separate this execution block
				$this->logger->log(
					'info',
					str_repeat('#', 200)
				);

				return true;
			}

			//
			// 2. Insert new data.
			//

			// Build query.
			$columns = DatabaseHelper::getTableColumns($table);
			echo $query   = $db->getQuery(true)
			->insert($db->qn($table))
			->columns($db->qn($columns))
			->values($tuples);
            //exit;
			//@debug - log what is currently there
            //echo "<pre>" . $query . "</pre>";exit;
			/*$this->logger->log(
				'info',
				sprintf('Write new data for article #%d', $artID),
				[
					'sql' => $query->dump()
				]
			);*/

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

		//@debug - log what is currently there
		/*$this->logger->log(
			'info',
			sprintf('Completed %s() for article #%d', __METHOD__, $artID),
			[]
		);*/

		//@debug - log new total definitions count
		/*$this->logger->log(
			'info',
			sprintf('New total definitions count #%d.', count($this->getDefinedMeasuringPoints())),
			[]
		);*/

		//@debug - add ruler to visually separate this execution block
		/*$this->logger->log(
			'info',
			str_repeat('#', 200),
			[]
		);*/

		return true;
	}
    function standardDeviation(array $data): float
    {
        $count = count($data);

        if ($count === 0) {
            return 0.0; // Or throw an exception, depending on your needs
        }

        $mean = array_sum($data) / $count;

        $variance = 0.0;

        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }


            // For a population standard deviation, divide by n
            $variance /= $count;

        return sqrt($variance);
    }

    public function getDataTypeForBtn($artID, $pid, $mp)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

         $query = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition', 't'))
            ->select($db->qn('mpDataType'))
            ->where($db->qn('t.mp')   . ' = ' . $db->q($mp))
            ->where($db->qn('t.artID')   . ' = ' . $db->q($artID))
            ->where($db->qn('t.procID')   . ' = ' . $db->q($pid));

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
        return $rs;
    }

    public function getLowerUpperForCPK($artID, $pid, $mp)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

          $query = $db->getQuery(true)
            ->from($db->qn('article_process_mp_definition', 't'))
            ->select($db->qn(['mpNominal','mpLowerTol','mpUpperTol']))
            ->where($db->qn('t.mp')   . ' = ' . $db->q($mp))
            ->where($db->qn('t.artID')   . ' = ' . $db->q($artID))
            ->where($db->qn('t.procID')   . ' = ' . $db->q($pid));

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
        return $rs;
    }
    public function getMeasuredPartsInput($artID, $pid, $mp, $countLimit)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

           /*echo $query = $db->getQuery(true)
            ->from($db->qn('part_process_mp_tracking', 't'))
            ->select($db->qn('mpInput'))
            ->where($db->qn('t.mp') . ' = ' . $db->q($mp))
            ->where($db->qn('t.procID') . ' = ' . $db->q($pid))
            ->where($db->qn('t.mpInput') . ' IS NOT NULL')  // Exclude NULL mpInput values
            ->where($db->qn('t.mpInput') . ' != 0')     // Exclude 0 mpInput values
            ->order('t.timestamp DESC') // Replace timestamp_column with your actual column name
            ->setLimit($countLimit);*/

           $query = $db->getQuery(true)
            ->from($db->qn('part_process_mp_tracking', 'pp')) // Use alias 'pp' for part_process
            ->select($db->qn('pp.mpInput'))       // Select mpInput from part_process
            ->join(
                'INNER',                                 // Inner join (only matching rows)
                $db->qn('parts', 'p') . ' ON ' . $db->qn('pp.partID') . ' = ' . $db->qn('p.partID')
            // Join part_process (pp) to parts (p) on the partID column
            )
            ->where($db->qn('p.artID') . ' = ' . $db->q($artID)) // Keep existing where clauses, but use alias
            ->where($db->qn('pp.mp') . ' = ' . $db->q($mp)) // Keep existing where clauses, but use alias
            ->where($db->qn('pp.procID') . ' = ' . $db->q($pid))
            ->where($db->qn('pp.mpInput') . ' IS NOT NULL')
            ->where($db->qn('pp.mpInput') . ' != 0')
            ->order('pp.timestamp DESC')
            ->setLimit($countLimit);

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
        return $rs;
    }

    public function getMeasuredPartsInputByDates($artID, $pid, $mp, $fromDate, $toDate)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

         $query = $db->getQuery(true)
             ->from($db->qn('part_process_mp_tracking', 'pp'))
             ->select($db->qn('pp.mpInput'))       // Select mpInput from part_process
             ->join(
                 'INNER',                                 // Inner join (only matching rows)
                 $db->qn('parts', 'p') . ' ON ' . $db->qn('pp.partID') . ' = ' . $db->qn('p.partID')
            //->select($db->qn('mpInput'))
             )
            ->where($db->qn('p.artID') . ' = ' . $db->q($artID)) // Keep existing where clauses, but use alias
             ->where($db->qn('pp.mp') . ' = ' . $db->q($mp)) // Keep existing where clauses, but use alias
             ->where($db->qn('pp.procID') . ' = ' . $db->q($pid))
             ->where($db->qn('pp.mpInput') . ' IS NOT NULL')
             ->where($db->qn('pp.mpInput') . ' != 0')
            ->where($db->qn('pp.timestamp') . ' >= ' . $db->q($fromDate)) // fromDate
            ->where($db->qn('pp.timestamp') . ' <= ' . $db->q($toDate))   // toDate
            ->order('pp.timestamp DESC');

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
        return $rs;
    }


    public function uploadDropBoxFiles($artID, $procID, $fileInfo, $fileName, $destFilePath)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $user   = App::getAppUser();
        $userID = $user->get('userID');

        $db = $this->db;

        // Setting names and character set is generally not needed for every query.
        // Do it once when establishing the database connection (if necessary).
        // $db->setQuery('SET NAMES utf8')->execute();
        // $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true);

        // Specify the columns to insert
        $columns = array('artID', 'procID', 'file_type', 'file_name', 'file_path', 'added_by', 'status');

        // Specify the values to insert.  Make sure these match the column order!
        $values = array(
            $db->quote($artID),         // Article ID (integer)
            $db->quote($procID),         // Process ID (integer)
            $db->quote($fileInfo),      // Filename (string)
            $db->quote($fileName),      // Filepath (string)
            $db->quote($destFilePath),      // Filetype (string)
            $db->quote($userID),     // Upload date (string, e.g., '2023-12-21 10:00:00')
            $db->quote('0')             // Status (string)
        );

        // Build the INSERT query
        $query->insert($db->quoteName('article_dropbox'))  // Replace 'your_files_table' with the actual table name
        ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);

        try {
            $rs = $db->execute(); // Execute the query
            $insertId = $db->insertid(); // Get the ID of the newly inserted row (optional)

        } catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;
    }

    public function getDropBoxFiles($artID, $pid, $filetype)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->from($db->qn('article_dropbox', 't'))
            ->select($db->qn(['drID','file_name','file_path','file_type']))
            ->where($db->qn('t.file_type')   . ' = ' . $db->q($filetype))
            ->where($db->qn('t.artID')   . ' = ' . $db->q($artID))
            ->where($db->qn('t.procID')   . ' = ' . $db->q($pid));

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
        return $rs;
    }


    public function checkDropBoxFile($fileID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->from($db->qn('article_dropbox', 't'))
            ->select($db->qn('file_path'))
            ->where($db->qn('t.drID')   . ' = ' . $db->q($fileID));

        try {
            $rs = $db->setQuery($query)->loadResult();

        }catch (Exception $e){
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;
    }

    public function deleteDropBoxFile($fileID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        // Removed redundant character set settings (if your DB connection is configured for UTF-8)
        // $db->setQuery('SET NAMES utf8')->execute();
        // $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true);

        // 1.  Get the file path *before* deleting the record
        $query->select($db->qn('file_path'))
            ->from($db->qn('article_dropbox'))
            ->where($db->qn('drID') . ' = ' . $db->q($fileID));

        try {
            $db->setQuery($query);
            $filePath = $db->loadResult();  // Load the file path

            if ($filePath) { // Only delete if there is a file path
                // 2. Now, build the DELETE query
                $query = $db->getQuery(true);  // Create a new query object for the DELETE
                $query->delete($db->quoteName('article_dropbox'))
                    ->where($db->quoteName('drID') . ' = ' . $db->q($fileID));

                $db->setQuery($query);

                $deleteResult = $db->execute();  // Execute the DELETE query

                if ($deleteResult) {
                    $rs = $filePath; // Pass file path to check after the query if the file exists
                }
            }

        } catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;  // Return the file path (or null on error)
    }


    public function insetDropBoxVideo($artID, $procID, $fileInfo, $youtubeId)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $user   = App::getAppUser();
        $userID = $user->get('userID');

        $db = $this->db;

        $query = $db->getQuery(true);
        // Specify the columns to insert
        $columns = array('artID', 'procID', 'evlink_type', 'embed_link', 'added_by', 'status');

        // Specify the values to insert.  Make sure these match the column order!
        $values = array(
            $db->quote($artID),         // Article ID (integer)
            $db->quote($procID),         // Process ID (integer)
            $db->quote($fileInfo),      // Filename (string)
            $db->quote($youtubeId),      // Filepath (string)
            $db->quote($userID),     // Upload date (string, e.g., '2023-12-21 10:00:00')
            $db->quote('0')             // Status (string)
        );

        // Build the INSERT query
        $query->insert($db->quoteName('article_dropbox_evideos'))  // Replace 'your_files_table' with the actual table name
        ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);

        try {
            $rs = $db->execute(); // Execute the query
            $insertId = $db->insertid(); // Get the ID of the newly inserted row (optional)

        } catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;
    }


    public function getDropBoxVideos($artID, $pid, $filetype)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->from($db->qn('article_dropbox_evideos', 't'))
            ->select($db->qn(['evID','evlink_type','embed_link']))
            ->where($db->qn('t.evlink_type')   . ' = ' . $db->q($filetype))
            ->where($db->qn('t.artID')   . ' = ' . $db->q($artID))
            ->where($db->qn('t.procID')   . ' = ' . $db->q($pid));

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
        return $rs;
    }

    public function checkDropBoxVideo($fileID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true)
            ->from($db->qn('article_dropbox_evideos', 't'))
            ->select($db->qn('embed_link'))
            ->where($db->qn('t.evID')   . ' = ' . $db->q($fileID));

        try {
            $rs = $db->setQuery($query)->loadResult();

        }catch (Exception $e){
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;
    }

    public function deleteDropBoxVideo($fileID)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $db = $this->db;

        // Removed redundant character set settings (if your DB connection is configured for UTF-8)
        // $db->setQuery('SET NAMES utf8')->execute();
        // $db->setQuery('SET CHARACTER SET utf8')->execute();

        $query = $db->getQuery(true);

        // 1.  Get the file path *before* deleting the record
        $query->select($db->qn('embed_link'))
            ->from($db->qn('article_dropbox_evideos'))
            ->where($db->qn('evID') . ' = ' . $db->q($fileID));

        try {
            $db->setQuery($query);
            $filePath = $db->loadResult();  // Load the file path

            if ($filePath) { // Only delete if there is a file path
                // 2. Now, build the DELETE query
                $query = $db->getQuery(true);  // Create a new query object for the DELETE
                $query->delete($db->quoteName('article_dropbox_evideos'))
                    ->where($db->quoteName('evID') . ' = ' . $db->q($fileID));

                $db->setQuery($query);

                $deleteResult = $db->execute();  // Execute the DELETE query

                if ($deleteResult) {
                    $rs = $filePath; // Pass file path to check after the query if the file exists
                }
            }

        } catch (Exception $e) {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
            $rs = null;
        }
        $this->closeDatabaseConnection();
        return $rs;  // Return the file path (or null on error)
    }
}
