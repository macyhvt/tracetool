<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Nematrack\Helper\DatabaseHelper;
use Nematrack\Helper\FilesystemHelper;
use RuntimeException;
use Throwable;
use function is_file;
use function is_readable;

/**
 * Class description
 */
class Entity extends App
{
	/**
	 * @var    array  Entity instances container.
	 * @since  1.1
	 */
	protected static array $instances = [];

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   0.1
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * Method to get an instance of Entity class.
	 *
	 * @param   string $name     The name of the desired Entity class.
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  mixed   Nematrack\Entity if instantiation was successful, or null if not.
	 *
	 * @throws  RuntimeException if the model could not be loaded.
	 * @since   1.1
	 *
	 */
	public static function getInstance(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $name . '")', true) . '</pre>' : null;

		// Get the options signature for the database connector.
		$signature = md5(serialize(compact('name', 'options')));

		// Create instance.
		if (empty(static::$instances[$signature]))
		{
			$className    = basename(str_replace('\\', '/', __CLASS__));
			$instanceName = 'Nematrack\\' . $className . '\\' . ucfirst(mb_strtolower($name));

			if (!class_exists($instanceName))
			{
				$filePath = FilesystemHelper::fixPath(__DIR__ . '/' . $className . '/' . ucfirst(mb_strtolower($name)) . '.php');

				// If the class still doesn't exist we have nothing left to do but throw an exception.  We did our best.
				if (!is_file($filePath) || !is_readable($filePath))
				{
					// TODO - translate
					throw new RuntimeException(sprintf('Unable to load %s file: %s', $className, ucfirst(mb_strtolower($name))));
				}

				require_once $filePath;
			}

			// If the class still doesn't exist we have nothing left to do but throw an exception.  We did our best.
			if (!class_exists($instanceName))
			{
				// TODO - translate
				throw new RuntimeException(sprintf('%s not available: %s', $className, $instanceName));
			}

			// Create our new Entity object based on the options given.
			try
			{
				$instance = new $instanceName($options);
			}
			catch (Throwable $e)
			{
				// TODO - translate
				throw new RuntimeException(sprintf('Unable to instantiate %s "%s": %s', $className, $instanceName, $e->getMessage()));
			}

			// Set the new Entity object to the global instances based on signature.
			static::$instances[$signature] = $instance;
		}

		return static::$instances[$signature];
	}

	/**
	 * Returns the base name of this object without namespace.
	 *
	 * @return string
	 */
	public function getName() : string
	{
		return basename(str_replace('\\', '/', __CLASS__));
	}

	/**
	 * Returns the primary key name of the associated database table.
	 *
	 * @return string
	 */
	public function getPrimaryKeyName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return DatabaseHelper::getPrimaryKey($this->getTableName());
	}

	/**
	 * Returns the name of the associated database table.
	 *
	 * @return string
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return '';
	}

	/**
	 * Add description...
	 *
	 * @param   string $property
	 * @param   mixed   $value
	 */
	public function set(string $property, $value)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $property . '", "' . $value . '")', true) . '</pre>' : null;

		if (property_exists($this, $property))
		{
			$this->$property = $value;
		}
	}
}
