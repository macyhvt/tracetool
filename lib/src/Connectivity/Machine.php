<?php
/* define application namespace */
namespace Nematrack\Connectivity;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Nematrack\Helper\FilesystemHelper;
use RuntimeException;
use function is_file;
use function is_readable;

/**
 * Class description
 */
abstract class Machine implements MachineInterface
{
	// TODO - Implement interface
	// TODO - Implement further functionality shared to child classes

	/**
	 * @var    array  Object instances container.
	 * @since  1.1
	 */
	protected static array $instances = [];

	/**
	 * Method to get an instance of MES class.
	 *
	 * @param   string  $name     The name of the desired MES class.
	 * @param   array   $options  An array of instantiation options.
	 *
	 * @return  mixed   Nematrack\Connectivity\Machine if instantiation was successful, or null if not.
	 *
	 * @since   2.8
	 *
	 * @throws  RuntimeException if the MES could not be loaded.
	 */
	public static function getInstance(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $name . '")', true) . '</pre>' : null;

		// Get the options signature for the database connector.
		$signature = md5(serialize(compact('name', 'options')));

		// Create instance.
		if (empty(static::$instances[$signature]))
		{
			// $className    = basename(__CLASS__);
			$className    = basename(str_replace('\\', '/', __CLASS__));
			$instanceName = 'Nematrack\\Connectivity\\' . $className . '\\' . ucfirst(mb_strtolower($name));

			if (!class_exists($instanceName))
			{
				$filePath = FilesystemHelper::fixPath(__DIR__ . '/' .  $className . '/' . ucfirst(mb_strtolower($name)) . '.php');

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
				$instance = new $instanceName(/* $name,  */$options);
			}
			catch (RuntimeException $e)
			{
				// TODO - translate
				throw new RuntimeException(
					sprintf('Unable to instantiate %s "%s": %s', $className, $instanceName, $e->getMessage())
				);
			}

			// Set the new Model object to the global instances based on signature.
			static::$instances[$signature] = $instance;
		}

		return static::$instances[$signature];
	}
}
