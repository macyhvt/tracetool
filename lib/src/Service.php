<?php
/* define application namespace */
namespace  ;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use  \Helper\FilesystemHelper;
use RuntimeException;
use Throwable;
use function is_file;
use function is_readable;

/**
 * Class description
 */
abstract class Service extends Model
{
	/**
	 * The instance identifier
	 *
	 * @var    string|null
	 * @since  2.6
	 */
	protected string $context = '';

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

		// Get instance name.
		$this->context = basename(get_class($this));
	}

	/**
	 * Method to get an instance of Model class.
	 *
	 * @param   string $name     The name of the desired Model class.
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  mixed    \Model if instantiation was successful, or null if not.
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
			$instanceName = ' \\' . $className . '\\' . ucfirst(mb_strtolower($name));

			if (!class_exists($instanceName))
			{
				$filePath = FilesystemHelper::fixPath(__DIR__ . '/' . $className . '/' . ucfirst(mb_strtolower($name)) . '.php');

				// If the class still doesn't exist we have nothing left to do but throw an exception.  We did our best.
				if (!is_file($filePath) || !is_readable($filePath))
				{
					// TODO - implement FileNotFoundException
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

			// Create new View object based on the options given.
			try
			{
				$instance = new $instanceName(/* $name,  */ $options);
			}
			catch (Throwable $e)
			{
				// TODO - translate
				throw new RuntimeException(sprintf('Unable to instantiate %s "%s": %s', $className, $instanceName, $e->getMessage()));
			}

			// Set the new Model object to the global instances based on signature.
			static::$instances[$signature] = $instance;
		}

		return static::$instances[$signature];
	}
}
