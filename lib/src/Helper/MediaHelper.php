<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */

use Exception;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Entity;
use Nematrack\Factory;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;

defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Class description
 */
final class MediaHelper
{
	/**
	 * Private constructor. Class cannot be constructed.
	 *
	 * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	public static function getHelpFiles() : array
	{
		// Calculate class name and primary key name.
//		$className  = mb_strtolower(basename(str_replace('\\', '/', __CLASS__)));
		$className  = 'Document';   // We are not in ListModel, hence, the above line is invalid
		$entityName = (new StringInflector)->singularize($className);
		$entityName = count($entityName) == 1 ? current($entityName) : (count($entityName) > 1 ? end($entityName) : rtrim($className, 's'));
		$pkName     = Entity::getInstance($entityName)->getPrimaryKeyName();

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// Init shorthand to database object.
		$db = Factory::getDbo();

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
		$table = Entity::getInstance('document')->getTableName();
		$query = $db->getQuery(true)
		->from($db->qn($table) . ' AS ' . $db->qn('d'));

		if (!is_array($columns = DatabaseHelper::getTableColumns($table)))
		{
			return [];
		}

		// Add main table prefix.
		array_walk($columns, function(&$column) { $column = sprintf('d.%s', $column); });

		$query
		->select(implode(',', $db->qn($columns)))
		->where($db->qn('lngID')   . ' = ' . $db->q(ArrayHelper::getValue($args, 'lngID',   2,        'INT')))
		->where($db->qn('section') . ' = ' . $db->q(ArrayHelper::getValue($args, 'section', 'help',   'STRING')))
		->where($db->qn('context') . ' = ' . $db->q(ArrayHelper::getValue($args, 'context', 'global', 'STRING')))
		->where($db->qn('topic')   . ' = ' . $db->q(ArrayHelper::getValue($args, 'topic',   '',       'STRING')))
		->where($db->qn('mobile')  . ' = ' . $db->q(ArrayHelper::getValue($args, 'mobile',  '',       'STRING')));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row[$pkName]] = Entity::getInstance('document', ['language' => ArrayHelper::getValue($args, 'language')])->bind($row);
			}

			// Close connection.
			DatabaseHelper::closeConnection($db);
		}
		catch (Exception $e)
		{
			throw new Exception($e->getMessage());
		}

		return $rows;
	}


	/**
	 * Returns the system path to the media files directory for a specific article.
	 *
	 * @param   int $artID
	 *
	 * @return  string
	 */
	public static function getMediafilesPathForArticle(int $artID) : string
	{
		$mediaFilesPathBASE = FTKPATH_MEDIA_ARTICLES;

		return FilesystemHelper::fixPath(
			sprintf(implode(DIRECTORY_SEPARATOR, ['%s', '%d']), $mediaFilesPathBASE, $artID)
		);
	}

	/**
	 * Returns the system path to the media files directory for a specific part.
	 *
	 * @param   int $partID
	 *
	 * @return  string
	 */
	public static function getMediafilesPathForPart(int $partID) : string
	{
		$basePath = FTKPATH_MEDIA_PART;

		return FilesystemHelper::fixPath(
			sprintf(implode(DIRECTORY_SEPARATOR, ['%s', '%d']), $basePath, $partID)
		);
	}


	/**
	 * Returns the system path to the media files upload directory for a specific article.
	 *
	 * @param   int $artID
	 *
	 * @return  string
	 */
	public static function getUploadPathForArticle(int $artID) : string
	{
		return FTKPATH_TEMP;

		/*$basePath = FTKPATH_TEMP;

		return FilesystemHelper::fixPath(
			sprintf(implode(DIRECTORY_SEPARATOR, ['%s', 'uploads', 'article', '%d']), $basePath, $artID)
		);*/
	}

	/**
	 * Returns the system path to the media files upload directory for a specific part.
	 *
	 * @param   int $partID
	 *
	 * @return  string
	 */
	public static function getUploadPathForPart(int $partID) : string
	{
		return FTKPATH_TEMP;

		/*$basePath = FTKPATH_TEMP;

		return FilesystemHelper::fixPath(
			sprintf(implode(DIRECTORY_SEPARATOR, ['%s', 'uploads', 'part', '%d']), $basePath, $partID)
		);*/
	}


	/**
	 * Returns all available media files for a specific article.
	 *
	 * @param   int $artID
	 *
	 * @return  array
	 *
	 * @todo
	 */
	public static function getMediafilesForArticle(int $artID) : array
	{
		// TODO
		return [];
	}

	/**
	 * Returns all available media files for a specific part.
	 *
	 * @param   int   $partID
	 * @param   array $procIDs  Optional list of process IDs to be used as filter.
	 *
	 * @return  array
	 *
	 * @uses   {@link Finder}
	 */
	public static function getMediafilesForPart(int $partID, array $procIDs = []) : array
	{
		// Init return value.
		$mediaFiles = [];

		// Define allowed media file types.
		$mediaTypeMap = [
//			'bmp'  => 'images', // not allowed
			'gif'  => 'images', // not allowed
			'jpg'  => 'images',
			'jpeg' => 'images',
			'png'  => 'images',
//			'tif'  => 'images', // not allowed
//			'tiff' => 'images', // not allowed

			'csv'  => 'documents',
			'pdf'  => 'documents',
			'doc'  => 'documents',
			'docx' => 'documents',
			'xls'  => 'documents',
			'xlsx' => 'documents',
		];

		// Get path to item media files.
		$mediaFilesPath = self::getMediafilesPathForPart($partID);

		// Get Symfony file finder handler.
		$finder = new Finder;	// get mediafiles for a part

		// Configure lookup directory.
		try
		{
			$finder
			->ignoreUnreadableDirs()
			->notPath('.bak')
			->notPath('originals')
			->notPath('thumbs');

			if (count($procIDs))
			{
				array_walk($procIDs, function($pid) use (&$finder, $mediaFilesPath)
				{
					$finder
					->in(App::getRouter()->fixRoute($mediaFilesPath . DIRECTORY_SEPARATOR . $pid));
				});
			}
			else
			{
				$finder
				->in(App::getRouter()->fixRoute($mediaFilesPath));
			}
		}
		catch (DirectoryNotFoundException $e)
		{
			// TODO - log error(s)

			return $mediaFiles;
		}

		// Find all media files belonging to this item.
		$finder
		->files()
		->followLinks()
		->name('/\.(csv|jpe?g|png|pdf|docx?|xls(b|m|x)?)$/i')
		->sortByName();

		// Iterate over the result(s) and collect information.
		if ($finder->hasResults())
		{
			foreach ($finder as $FILE)
			{
				// Extract process ID from file path.
				$procID = $FILE->getRelativePath();

				$collection = ArrayHelper::getValue($mediaTypeMap, $FILE->getExtension(), 'unknown');

				// File is not readable.
				if (!$FILE->isReadable())
				{
					$collection = 'unreadable';
				}

				// Make sure all required data collections exist prior usage.
				$mediaFiles[$procID]                          = $mediaFiles[$procID] ?? [];
				$mediaFiles[$procID][$collection]             = $mediaFiles[$procID][$collection] ?? [];
				$mediaFiles[$procID][$collection]['fullsize'] = $mediaFiles[$procID][$collection]['fullsize'] ?? [];

				if ($collection == 'images')
				{
					// Extract unique id (hrtime) from file name and use it as array key.
//					preg_match('/^.*_(\d{8,10}_\d{8,10})\.[a-z]{3}$/i', $FILE->getPathName(), $match);  // match microtime() substring
					preg_match('/^.*_(\d{10,})\.[a-z]{3}$/i', $FILE->getPathName(), $match);  // match hrtime() substring

					$key = array_pop($match);

					// Push file to collection.
					$mediaFiles[$procID][$collection]['fullsize'][$key] = $FILE->getPathName();
				}
				else
				{
					$mediaFiles[$procID][$collection][] = $FILE->getPathName();
				}
			}

			// Now load thumbnails.
			$finder = new Finder;	// get mediafiles for a part

			// Configure lookup directory.
			try
			{
				$finder
				->ignoreUnreadableDirs()
				->notPath('.bak')
				->path('thumbs');

				if (count($procIDs))
				{
					array_walk($procIDs, function($pid) use (&$finder, $mediaFilesPath)
					{
						$finder
						->in(App::getRouter()->fixRoute($mediaFilesPath . DIRECTORY_SEPARATOR . $pid));
					});
				}
				else
				{
					$finder
					->in(App::getRouter()->fixRoute($mediaFilesPath));
				}
			}
			catch (DirectoryNotFoundException $e)
			{
				// TODO - log error(s)
			}

			// Find all media files belonging to this item.
			$finder
			->files()
			->followLinks()
			->name('/\.(jpe?g|png)$/i')
			->sortByName();

			if ($finder->hasResults())
			{
				foreach ($finder as $FILE)
				{
					// Extract process ID from file path.
					$procID = intval($FILE->getRelativePath());

					$collection = 'images';

					if (!$FILE->isReadable())
					{
						$collection = 'unreadable';
					}

					// Make sure all required data collections exist prior usage.
					$mediaFiles[$procID]                        = $mediaFiles[$procID] ?? [];
					$mediaFiles[$procID][$collection]           = $mediaFiles[$procID][$collection] ?? [];
					$mediaFiles[$procID][$collection]['thumbs'] = $mediaFiles[$procID][$collection]['thumbs'] ?? [];

					// Extract unique id (hrtime) from file name and use it as array key.
//					preg_match('/^.*_(\d{8,10}_\d{8,10})\.[a-z]{3}$/i', $FILE->getPathName(), $match);  // match microtime() substring
					preg_match('/^.*_(\d{10,})_\d{2,3}x\d{2,3}\.[a-z]{3}$/i', $FILE->getPathName(), $match);  // match hrtime() substring

					$key = array_pop($match);

					// Push file to collection.
					$mediaFiles[$procID][$collection]['thumbs'][$key] = $FILE->getPathName();
				}
			}
		}

		// Sort process ids in ASC order.
		ksort($mediaFiles);

		return $mediaFiles;
	}
}
