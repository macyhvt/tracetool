<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use InvalidArgumentException;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Messager;
use Nematrack\Text;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use function array_diff;
use function is_array;
use function is_dir;
use function is_file;
use function is_readable;
use function mb_strtoupper;
use function rmdir;
use function scandir;
use function unlink;

/**
 * Class description
 */
final class FilesystemHelper
{
	/**
	 * The file system base path
	 *
	 * @var string
	 */
	protected static string $base = FTKPATH_BASE;

	/**
	 * Private constructor. Class cannot be constructed.
     *
     * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	/**
	 * Returns the configured system base path.
	 *
	 * @return  string
	 */
	public static function base() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return self::fixPath( self::$base ?? FTKPATH_BASE );
	}

	/**
	 * Converts a given path into an absolute system path.
	 *
	 * @param   string $relPath
	 *
	 * @return  string
	 */
	public static function absPath(string $relPath) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return self::fixPath( self::$base . DIRECTORY_SEPARATOR . $relPath );
	}

	/**
	 * Fixes a given system path by substituting all directory separation characters with
	 * the proper OS matching directory separation character.
	 *
	 * @param   string $path
	 *
	 * @return  string
	 */
	public static function fixPath(string $path) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$path = str_ireplace('//', '/', $path);
		$path = str_ireplace('/',  DIRECTORY_SEPARATOR, $path);
		$path = str_ireplace('//', '/', $path);

		return str_ireplace('\\', DIRECTORY_SEPARATOR, $path);
	}

	/**
	 * Converts a given absolut path into a relative system path.
	 *
	 * @param   string $absPath
	 *
	 * @return  string
	 */
	public static function relPath(string $absPath) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return str_replace(self::$base, '', $absPath);
	}

	/**
	 * Check if a given absolute path points to an empty directory.
	 *
	 * (Code borrowed with some modification from: {@link https://stackoverflow.com/a/7497848})
	 *
	 * @param   string $absPath
	 *
	 * @return  bool|null
	 */
	public static function isEmpty(string $absPath) :? bool
	{
		if (!is_readable($absPath)) return NULL;

		$handle = opendir($absPath);

		while (false !== ($entry = readdir($handle)))
		{
			if ($entry != '.' && $entry != '..')
			{
				closedir($handle);

				return false;
			}
		}

		closedir($handle);

		return true;
	}

	/**
	 * Returns whether the underlying OS is a version of Microsoft Windows.
	 *
	 * @return  bool   true when is Windows or false when is different OS
	 */
	public static function isWin() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return mb_strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	/**
	 * Deletes a given directory.
	 *
	 * @param   string $absPath  Paths to look-up
	 *
	 * @return  bool   true on success or false on failure
	 *
	 * @throws  InvalidArgumentException if function argument is empty
	 */
	public static function deleteDirectory(string $absPath) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$status = false;

		if (empty($absPath))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be a system path to the directory to be deleted.');
		}

		if (is_dir($absPath) && !is_readable($absPath))
		{
			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_DIRECTORY_FOR_DELETION_NOT_READABLE_TEXT')
			]);

			return $status;
		}

		// $files = glob($absPath . '*', GLOB_MARK | GLOB_NOESCAPE | GLOB_NOSORT);	// doesn't find any files in the directory
		$files = @scandir($absPath, SCANDIR_SORT_NONE);     // Code borrowed with some modification from: {@link https://www.php.net/manual/de/function.rmdir.php#110489}
		$files = (is_array($files) ? $files : []);                      // if $absPath is empty, then the return value of {@link \scandir()} will be 'false'. For further processing is must be an array.
		$files = array_diff($files, ['.','..']);                        // exclude these subdirectories from being deleted

		// Target directory MUST BE EMPTY !!!
		// So files must be deleted first !
		if (!empty($files))
		{
			foreach ($files as $file)
			{
				$filePath = self::fixPath( $absPath . DIRECTORY_SEPARATOR . $file );

				if (is_dir($filePath) && is_readable($filePath))
				{
					self::deleteDirectory($filePath);
				}

				if (is_file($filePath) && is_readable($filePath))
				{
					unlink($filePath);
				}
			}
		}

		// Not directory can be deleted.
		if (is_dir($absPath))
		{
			$status = rmdir($absPath);
		}

		return $status;
	}

	// NEW as of 2023-04-09
	public static function makeDirectory(string $absPath) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return mkdir(FilesystemHelper::fixpath($absPath), 0755, true);
	}

	/**
	 * Add description...
	 *
	 * Provide at least a <em>path</em> and <em>depth</em> property for the scanner to know where and up to which depth to scan.
	 * The following may help to understand the scanner's traversing behaviour:
	 *
	 *    0 means to return contents which are direct children.
	 *    1 returns only level-0-contents if they contain further directories grouped by level 0 directory names.
	 *    2 returns only level-1-contents if they contain further directories grouped by level 1 directory names.
	 *
	 * @return  array
	 */
	public static function getDirectoryList() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init return value.
		$list  = [];

		// Get additional function args.
		$args  = func_get_args();
		$args  = (array) array_shift($args);

		// There may be arguments for this function.
		$path  = ArrayHelper::getValue($args, 'path',  '', 'STRING');
		$depth = ArrayHelper::getValue($args, 'depth',  0, 'INT');

		// Get Symfony file finder handler.
		$finder   = new Finder;	// get directories in directory

		// Get Joomla registry object.
		$registry = new Registry;

		// Configure look-up directory.
		try
		{
			$finder
			->ignoreUnreadableDirs()
			->notPath('.bak')
			->notPath('originals')
			->notPath('thumbs')
			->in(App::getRouter()->fixRoute($path))
			->directories()
			->followLinks()
			->depth($depth)
			->sortByName();

			if ($finder->hasResults())
			{
				// Build array list from paths.
				foreach ($finder as $DIR)
				{
					$absPath = $DIR->getPathName();
					$relPath = str_ireplace(FTKPATH_BASE, '', $absPath);
					$dirs    = array_filter(explode(DIRECTORY_SEPARATOR, '' . $relPath));

					// Convert nested arrays to valid JSON string.
					array_walk($dirs, function (&$val) { $val = sprintf('{"%s"', $val); });

					// NOTE: We inject '. => null' to have at least 1 entry in every array to circumvent Joomla\Registry not loading empty arrays.
					$dirs    = sprintf('%s:{".":null}%s', implode(':', $dirs), str_repeat('}', count($dirs)));

					// Load string into registry.
					$registry->loadString($dirs);
				}

				// Override values of the final level element with absolute path to this directory.
				foreach ($finder as $DIR)
				{
					$absPath = $DIR->getPathName();
					$relPath = str_ireplace(FTKPATH_BASE, '', $absPath);
					$dirs    = array_filter(explode(DIRECTORY_SEPARATOR, '' . $relPath));

					if (count($dirs) >= $depth)
					{
						$registry->set(ltrim($relPath, DIRECTORY_SEPARATOR), $absPath, DIRECTORY_SEPARATOR);
					}
				}
			}
		}
		catch (DirectoryNotFoundException $e)
		{
			// TODO - log error(s)

			return $list;
		}

		// Create Registry-path to be extracted.
		$registryPath = implode('.', array_filter(explode(DIRECTORY_SEPARATOR, str_replace(FTKPATH_BASE, '', '' . $path))));

		// Extract data.
		$list = $registry->extract($registryPath);	// Force array data-type
		$list = is_a($list, 'Joomla\Registry\Registry') ? $list->toArray() : (array) $list;

		// Drop empty array elements that were injected in line 231 to hack Joomla\Registry not loading empty arrays.
		return \Nematrack\Helper\ArrayHelper::filterRecursive($list);
	}

	/**
	 * Add description...
	 *
	 * The <em>path/paths</em> and <em>type/types</em> information are mandatory for the scanner to know where and what to scan for.
	 * Both parameters may be passed as string (single path or extension) or array of paths or extensions.
	 * Paths shall be absolute (relative to system root)
	 *
	 * @return  array  List of Symfony\Component\Finder\SplFileInfo objects
	 */
	public static function getFilesInDirectory() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init return value.
		$list = [];

		// Get additional function args.
		$args = func_get_args();
		$args = (array) array_shift($args);

		// There may be arguments for this function.
		$path  = ArrayHelper::getValue($args, 'path',   [], 'ARRAY');
		$paths = ArrayHelper::getValue($args, 'paths',  [], 'ARRAY');
		$type  = ArrayHelper::getValue($args, 'type',   [], 'ARRAY');
		$types = ArrayHelper::getValue($args, 'types',  [], 'ARRAY');

		// Merge both paths-arrays.
		array_walk($path, function ($str) use (&$paths) { $paths[] = $str; }); array_unique($paths); sort($paths);

		// Merge both file types-arrays.
		array_walk($type, function ($str) use (&$types) { $types[] = $str; }); array_unique($types); sort($types);

		// Get Symfony file finder handler.
		$finder = new Finder;	// get files in directory

		// Configure look-up directory.
		try
		{
			// Configure look-up directory.
			$finder
			->ignoreUnreadableDirs();

			// Configure look-up path(s) and file type(s).
			$finder
			->in($paths)
			->files()
			->followLinks()
			->ignoreDotFiles(true)
			->ignoreVCS(true)
			->name($types)
//			->sortByAccessedTime()
//			->sortByChangedTime()
			->sortByModifiedTime()
//			->sortByName()
			;

			if ($finder->hasResults())
			{
				foreach ($finder as $FILE)
				{
					if (is_readable($FILE))
					{
						$list[] = $FILE;
					}
				}
			}
		}
		catch (DirectoryNotFoundException $e)
		{
			// TODO - log error(s)
		}

		return $list;
	}
}
