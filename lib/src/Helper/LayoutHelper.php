<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Joomla\Registry\Registry;
use Nematrack\Layout;
use function is_file;
use function is_readable;

/**
 * Class description
 */
final class LayoutHelper
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

	/**
	 * Method to check whether a layout exists.
	 *
	 * @param   string $layoutFile  Dot separated path to a layout file, relative to base path
	 *
	 * @return  bool   true if exists success or false if not
	 */
	public static function exists(string $layoutFile) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$path = FilesystemHelper::fixPath(FTKPATH_LAYOUTS . DIRECTORY_SEPARATOR . str_replace('.', '/', $layoutFile) . '.php');

		return is_file($path) && is_readable($path);
	}

	/**
	 * Method to render the layout.
	 *
	 * @param   string         $layoutFile   Dot separated path to a layout file, relative to base path
	 * @param   array|object   $displayData  Object which properties are used inside the layout file to build displayed output
	 * @param   array|Registry $options      Optional custom options to load. Registry or Array format
	 *
	 * @return  string
	 */
	public static function render(string $layoutFile, $displayData = null, $options = null) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$layout = new Layout($layoutFile, (array) $options);

		return $layout->render((array) $displayData);
	}
}
