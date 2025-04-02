<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Nematrack\Helper\FilesystemHelper;
use RuntimeException;
use Throwable;
use function array_pop;
use function is_file;
use function is_readable;

/**
 * BITWISE FLAGS for Custom PHP Objects
 *
 * Sometimes I need a custom PHP Object that holds several boolean TRUE or FALSE values.
 * I could easily include a variable for each of them, but as always, code has a way to
 * get unwieldy pretty fast. A more intelligent approach always seems to be the answer,
 * even if it seems to be overkill at first.
 * I start with an abstract base class which will hold a single integer variable called $flags.
 * This simple integer can hold 32 TRUE or FALSE boolean values. Another thing to consider
 * is to just set certain BIT values without disturbing any of the other BITS -- so included
 * in the class definition is the {@link FTKUser::setFlag} function, which will set only
 * the chosen bit.
 *
 * Code borrowed with some modification from: {@link https://gist.github.com/ryanwinchester/3a6d103be500e31dc366}
 *
 * @link http://php.net/manual/en/language.operators.bitwise.php#108679
 */
abstract class BitwiseFlag extends App
{
	/**
	 * Integer variable that contains a user's roles bits summed up.
	 *
	 * @var  int
	 */
	protected $flags;

	/*
	 * Note: these functions are protected to prevent outside code from falsely setting BITS.
	 * See how the extending class {@link Nematrack\Permissions} handles this.
	 *
	 */
	protected function isFlagSet($flag) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $flag . '")', true) . '</pre>' : null;

		return (($this->flags & $flag) == $flag);
	}

	protected function setFlag($flag, $value)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $flag . '", "' . $value . '")', true) . '</pre>' : null;

		if ($value)
		{
			$this->flags |=  $flag;
		}
		else
		{
			$this->flags &= ~$flag;
		}
	}
}

/**
 * The class above is abstract and cannot be instantiated, so an extension is required.
 * Below is a simple extension named {@link \Nematrack\Permissions} -- which is severely truncated for clarity.
 *
 * Notice I am defining constants, variables AND methods to use them.
 *
 * This seems like a lot of work, but we have addressed many issues, for example, using and
 * maintaining the code is easy, and the getting and setting of role values make sense.
 * With the User class, you can now see how easy and intuitive bitwise role operations become.
 */
class Access extends BitwiseFlag
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
	 * @since  2.6
	 */
	protected string $context = '';

	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
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
	 * Method to get an instance of Access class.
	 *
	 * @param   string $name     The name of the desired Access class.
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  mixed   Nematrack\Access if instantiation was successful, or null if not.
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
			// $className    = basename(__CLASS__);
			$className    = basename(str_replace('\\', '/', __CLASS__));
			$instanceName = 'Nematrack\\' . $className . '\\' . ucfirst(mb_strtolower($name));

			if (!class_exists($instanceName))
			{
				$filePath = FilesystemHelper::fixPath(__DIR__ . '/' .  $className . '/' . ucfirst(mb_strtolower($name)) . '.php');

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

			// die('Ready to get instance');

			// Create our new Access object based on the options given.
			try
			{
				$instance = new $instanceName(/* $name,  */$options);
			}
			catch (Throwable $e)
			{
				// TODO - translate
				throw new RuntimeException(sprintf('Unable to instantiate %s "%s": %s', $className, $instanceName, $e->getMessage()));
			}

			// Set the new Access object to the global instances based on signature.
			static::$instances[$signature] = $instance;
		}

		return static::$instances[$signature];
	}

	/**
	 * Add description...
	 *
	 * @return array
	 */
	public function toArray() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		preg_match('#\[([^\]]*)\]#', $this->__toString(), $groups);

		$groups = array_pop($groups);
		$groups = str_ireplace(' ', ',', $groups);

		return (array) explode(',', $groups);
	}

	/**
	 * Add description...
	 *
	 * @return object
	 */
	public function toObject() : object
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (object) $this->toArray();
	}

	/**
	 * Add description...
	 *
	 * @return string
	 */
	public function __toString() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$className = trim(str_ireplace(__NAMESPACE__, '', get_class($this)), '\\/');

		return $className . ' []';
	}
}
