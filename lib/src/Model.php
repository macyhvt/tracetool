<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Database\Mysqli\MysqliDriver;
use Monolog\Logger;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Helper\FilesystemHelper;
use RuntimeException;
use Throwable;
use function is_a;
use function is_file;
use function is_readable;

/**
 * Class description
 */
class Model extends App
{
	/**
	 * @var    array  Object instances container.
	 * @since  1.1
	 */
	protected static array $instances = [];

	/**
	 * The instance identifier
	 *
	 * @var    string
	 * @since  1.1
	 */
	protected string $context = '';

	/**
	 * The shared database connection object
	 *
	 * @var    MysqliDriver
	 * @since  0.1
	 */
	protected $db = null;

	/**
	 * The shared logger object
	 *
	 * @var    Logger
	 * @since  2.8.0
	 */
	protected Logger $logger;

	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException
	 * @since   0.1
	 *
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		// Get instance name.
		$this->context = basename(get_class($this));

		// Get logger object.
		$this->logger = Factory::getLogger([
			'context' => get_class($this),
			'type'    => 'rotate',
			'path'    => FTKPATH_LOGS . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'system.log',
			'level'   => 'INFO'
		]);

		// Get database connection object.
		$db = Factory::getDbo();

		if (!is_a($db, 'Joomla\Database\Mysqli\MysqliDriver'))
		{
			throw new RuntimeException(sprintf('Error in Model %s: Failure creating the database connection.', $this->context));
		}

		$this->db = $db;
	}

	/**
	 * Method to get an instance of Model class.
	 *
	 * @param   string  $name     The name of the desired Model class.
	 * @param   array   $options  An array of instantiation options.
	 *
	 * @return  mixed   Nematrack\Model if instantiation was successful, or null if not.
	 *
	 * @throws  RuntimeException if the model could not be loaded.
	 *@since   1.1
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

	/*public function getDatabaseError($context = null, ...$args) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $context . '")', true) . '</pre>' : null;

		// Prepare argument placeholders in message.
		// Code borrowed with some modification from: {@link https://stackoverflow.com/a/9924340}
		$placeholders = trim(str_repeat("%s ", count($args)));
		$placeholders = vsprintf($placeholders, $args);

		// Create specific error.
		$error = vsprintf($error . ' ' . $placeholders, $args);

		return trim($error);
	}*/

	/**
	 * Add description...
	 */
	protected function closeDatabaseConnection() : void
	{
		try
		{
			DatabaseHelper::closeConnection($this->db);
		}
		catch (Exception $e) {}
	}

	/**
	 * Add description...
	 *
	 * @param   string $tableName
	 * @param   string $pkName
	 * @param   int    $pkValue
	 * @param   int    $userID
	 *
	 * @return  int|void|null
	 *
	 * @throws  Exception
	 */
	protected function setModifiedBy(string $tableName, string $pkName, int $pkValue, int $userID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $tableName . '", "' . $pkName . '", "' . $pkValue . '", "' . $userID . '")', true) . '</pre>' : null;

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $userID ?? $user->get('userID');

		if (!$userID)
		{
			return;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$now = $now->format('Y-m-d H:i:s');

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn($tableName))
		->set($db->qn('modified')    . ' = ' . $db->q($now))
		->set($db->qn('modified_by') . ' = ' . (int) $userID)
		->where($db->qn($pkName)  . ' = ' . $pkValue);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Throwable $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$affectedRows = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $affectedRows;
	}

	/**
	 * Add description...
	 *
	 * @return bool
	 */
	protected function userCanDelete() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__, true) . '</pre>' : null;

		$user = App::getAppUser();

		return ($user->isRegistered() && $user->isActive());
	}
}
