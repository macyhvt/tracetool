<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Monolog\Logger;
use RuntimeException;

/**
 * Nematrack Factory class.
 *
 * @since  1.2.2
 */
abstract class Factory
{
	/**
	 * Global configuration object
	 *
	 * @var    Registry|null
	 * @since  1.7.0
	 */
	public static ?Registry $config = null;

	/**
	 * Global logger object
	 *
	 * @var    Logger|null
	 * @since  2.8
	 */
	public static ?Logger $logger = null;

	/**
	 * Global database object
	 *
	 * @var    DatabaseDriver|null
	 * @since  1.7.0
	 */
	public static ?DatabaseDriver $database = null;

	/**
	 * Get a configuration object
	 *
	 * Returns the global {@link \JConfig} object, only creating it if it doesn't already exist.
	 *
	 * @param   string|null $file       The path to the configuration file
	 * @param   string      $type       The type of the configuration file
	 * @param   string      $namespace  The namespace of the configuration file
	 *
	 * @return  Registry
	 *
	 * @see     Registry
	 *
	 * @since   1.7.0
	 */
	public static function getConfig(string $file = null, string $type = 'PHP', string $namespace = '') : Registry
	{
		if (!self::$config)
		{
			if ($file === null)
			{
				$file = __DIR__ . '/Configuration.php';
			}

			self::$config = self::createConfig($file, $type, $namespace);
		}

		return self::$config;
	}

	/**
	 * Get a database object.
	 *
	 * Returns the global {@link DatabaseDriver} object, only creating it if it doesn't already exist.
	 *
	 * @return  DatabaseDriver
	 *
	 * @see     DatabaseDriver
	 *
	 * @since   1.7.0
	 */
	public static function getDbo() : DatabaseDriver
	{
		if (!self::$database)
		{
			self::$database = self::createDbo();
		}

		return self::$database;
	}

	/**
	 * Get a logger object.
	 *
	 * Returns the global {@link Logger} object, only creating it if it doesn't already exist.
	 *
	 * @param   array $options  An array containing session options
	 *
	 * @return  Logger object
	 *
	 * @since   2.8
	 */
	public static function getLogger(array $options = []) : Logger
	{
		/*if (!self::$logger)
		{
			self::$logger = self::createLogger($options);
		}

		return self::$logger;*/

		return self::createLogger($options);
	}

	/**
	 * Create a configuration object
	 *
	 * @param   string $file       The path to the configuration file.
	 * @param   string $type       The type of the configuration file.
	 * @param   string $namespace  The namespace of the configuration file.
	 *
	 * @return  Registry
	 *
	 * @see     Registry
	 *
	 * @since   1.7.0
	 */
	protected static function createConfig(string $file, string $type = 'PHP', string $namespace = '') : Registry
	{
		if (is_file($file))
		{
			include_once $file;
		}

		// Create the registry with a default namespace of config
		$registry = new Registry;

		// Sanitize the namespace.
		$namespace = ucfirst((string) preg_replace('/[^A-Z_]/i', '', $namespace));

		// Build the config name.
		$name = 'FTKConfig' . $namespace;

		// Handle the PHP configuration type.
		if ($type == 'PHP' && class_exists($name))
		{
			// Create the JConfig object
			$config = new $name;

			// Load the configuration values into the registry
			$registry->loadObject($config);
		}

		return $registry;
	}

	/**
	 * Create a database object
	 *
	 * @return  DatabaseDriver
	 *
	 * @see     DatabaseDriver
	 *
	 * @since   1.7.0
	 */
	protected static function createDbo() : DatabaseDriver
	{
		$conf = self::getConfig();

		$driver   = $conf->get('dbtype');
		$host     = $conf->get('host');
		$port     = $conf->get('port');
		$user     = $conf->get('user');
		$password = $conf->get('password');
		$database = $conf->get('db');
		$prefix   = $conf->get('dbprefix');
		$debug    = $conf->get('debug');

		$options = [
			'driver'   => $driver,
			'host'     => $host,
			'port'     => $port,
			'user'     => $user,
			'password' => $password,
			'database' => $database,
			'prefix'   => $prefix
		];

		try
		{
			$db = DatabaseDriver::getInstance($options);
		}
		catch (RuntimeException $e)
		{
			header('HTTP/1.1 500 Internal Server Error');

			// TODO - translate
			die('Database connection object creation error: ' . $e->getMessage());
		}

		$db->setDebug($debug);

		return $db;
	}

	/**
	 * Create a Monolog logger object
	 *
	 * @param   array $options  An array containing logger creation options
	 *
	 * @return  Logger
	 *
	 * @throws  RuntimeException If any other than "rotate" or "stream" handler is requested.
	 * @since   2.8
	 *
	 * @uses    Logger
	 */
	protected static function createLogger(array $options = []) : Logger
	{
		$options['context']  =              ($options['context']  ?? self::getConfig()->get('app_name'));
		$options['type']     =              ($options['type']     ?? self::getConfig()->get('debug_type', 'stream'));
		$options['maxFiles'] = (int)        ($options['maxFiles'] ?? self::getConfig()->get('debug_maxFiles', 0));        // The maximal amount of files to keep (0 means unlimited)
		$options['path']     =              ($options['path']     ?? self::getConfig()->get('debug_path', FTKPATH_LOGS . DIRECTORY_SEPARATOR . 'system.log'));
		$options['level']    = mb_strtoupper($options['level']    ?? self::getConfig()->get('debug_level', 'NOTICE'));

		// Init return value.
		$logger = null;

		if (class_exists('Monolog\Logger'))
		{
			$logger = new Logger($options['context']);

			//OLD
//			$handler = new \Monolog\Handler\StreamHandler( $options['path'], constant('Monolog\Logger::' . $options['level']) );

			// Generate logger class name.
			// see {@link https://github.com/Seldaek/monolog/blob/main/doc/02-handlers-formatters-processors.md}
			switch (mb_strtolower($options['type']))
			{
				case 'error' :
					$type = 'ErrorLog';     // Logs records to PHP's error_log() function.
				break;

				case 'rotate' :
				case 'rotating' :
					$type = 'RotatingFile'; // Logs records to a file and creates one logfile per day.
					// It will also delete files older than $maxFiles.
					// You should use logrotate for high profile setups though, this is just meant as a quick and dirty solution.
				break;

				case 'process' :            // Logs records to the STDIN of any process, specified by a command.
				case 'stream' :             // Logs records into any PHP stream, use this for log files.
				case 'syslog' :             // Logs records to the syslog.
					$type = ucfirst($options['type']);
				break;

				default :
					$type = 'Stream';
			}

			$handler = sprintf('Monolog\Handler\%sHandler', $type);

			switch (mb_strtolower($options['type']))
			{
				/*case 'error' :
					$handler = new $handler( ErrorLogHandler::OPERATING_SYSTEM, constant('Monolog\Logger::' . $options['level']) );
				break;*/

				case 'rotate' :
				case 'rotating' :
					$handler = new $handler($options['path'], $options['maxFiles'], constant('Monolog\Logger::' . $options['level']));
				break;

				case 'stream' :
					$handler = new $handler($options['path'], constant('Monolog\Logger::' . $options['level']));
				break;

				default :
					// TODO - translate
					throw new RuntimeException('Unsupported Log type. Only "rotate" and "stream" are supported.');
			}

			$logger->pushHandler($handler);
		}

		return $logger;
	}
}
