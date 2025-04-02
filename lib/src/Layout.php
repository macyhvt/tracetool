<?php
/* define application namespace */
namespace  ;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Exception;
use Joomla\Registry\Registry;
use  \Helper\FilesystemHelper;

/**
 * Class description
 */
class Layout extends App
{
	/**
	 * Cached layout paths
	 *
	 * @var    array
	 */
	protected static array $cache = [];

	/**
	 * Options object
	 *
	 * @var    Registry
	 */
	protected Registry $options;

	/**
	 * Path to the layout file
	 *
	 * @var    string
	 */
	protected string $layoutId = '';

	/**
	 * Class construct
	 *
	 * @param   string     $layoutId
	 * @param   array|null $options  An array of instantiation options.
	 *
	 * @since   0.1
	 */
	public function __construct(string $layoutId, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		// Initialise / Load options
		$this->setOptions($options);

		// Main properties
		$this->setLayoutId($layoutId);
	}

	/**
	 * Set the options
	 *
	 * @param   array|Registry $options  Array / Registry object with the options to load
	 *
	 * @return Layout object for chaining
	 */
	public function setOptions($options) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Received Registry
		if ($options instanceof Registry)
		{
			$this->options = $options;
		}
		// Received array
		elseif (is_array($options))
		{
			$this->options = new Registry($options);
		}
		else
		{
			$this->options = new Registry;
		}

		return $this;
	}

	/**
	 * Get the options
	 *
	 * @return Registry The options object
	 */
	public function getOptions() : Registry
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->options;
	}

	/**
	 * Set the active layout id
	 *
	 * @param   string $layoutId  Layout identifier
	 *
	 * @return Layout object for chaining
	 */
	public function setLayoutId(string $layoutId) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->layoutId = $layoutId;

		return $this;
	}

	/**
	 * Get the active layout id
	 *
	 * @return  string
	 */
	public function getLayoutId() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->layoutId;
	}

	/**
	 * Method to render the layout.
	 *
	 * @param   array $displayData  Array of properties available for use inside the layout file to build the displayed output
	 *
	 * @return  string  The HTML to display the layout
	 */
	public function render(array $displayData = []) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$layoutOutput = '';

		// Automatically merge any previously data set if $displayData is an array
		$this->data = $displayData;

		// Check possible overrides, and build the full path to layout file
		try
		{
			$path = $this->getPath();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			return '';
		}

		if (empty($path) || trim($path) === '')
		{
			return '';
		}

		ob_start();

		include $path;

		$layoutOutput .= ob_get_contents();

		ob_end_clean();

		return $layoutOutput;
	}

	/**
	 * Add description...
	 *
	 * @return string
	 */
	public function getName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$name = explode('.', $this->layoutId);
		$name = end($name);

		return trim($name);
	}

	/**
	 * Add description...
	 *
	 * @return string
	 */
	public function getViewName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$vName = explode('.', $this->layoutId);
		$vName = current($vName);

		return trim($vName);
	}


	/**
	 * Method to finds the full real file path, checking possible overrides
	 *
	 * @return  string  The full path to the layout file
	 *
	 * @throws  Exception if the layout file could not be found.
	 */
	protected function getPath() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$layoutId = $this->getLayoutId();

		if (!$layoutId)
		{
			// TODO - translate
			throw new Exception('There is no active layout.');
		}

		$hash = md5('Layout.php');

		if (!empty(static::$cache[$layoutId][$hash]))
		{
			return static::$cache[$layoutId][$hash];
		}

		// Standard version
		$rawPath = FilesystemHelper::fixPath(FTKPATH_LAYOUTS . DIRECTORY_SEPARATOR . str_replace('.', '/', $this->layoutId) . '.php');

		$foundLayout = (file_exists($rawPath) ? $rawPath : null);

		if (!$foundLayout)
		{
			// TODO - translate
			throw new Exception(sprintf('Unable to find layout: <strong>%s</strong>', $layoutId), 404);
		}

		static::$cache[$layoutId][$hash] = $foundLayout;

		return static::$cache[$layoutId][$hash];
	}
}
