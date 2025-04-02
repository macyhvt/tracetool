<?php
/* define application namespace */
namespace  ;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use  \Helper\UriHelper;
use RuntimeException;
use Throwable;

/**
 * Class description
 */
final class Router
{
	/**
	 * Object instances container
	 *
	 * @var    array
	 * @since  2.7
	 */
	protected static array $instances = [];

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @since   2.7
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	/**
	 * Method to get an instance of Router class.
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @return  mixed    \Router if instantiation was successful, or null if not.
	 *
	 * @throws  RuntimeException if the model could not be loaded.
	 *
	 * @since   2.7
	 *
	 * @todo    implement FileNotFoundException
	 */
	public static function getInstance(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__, true) . '</pre>' : null;

		// Get the options signature for the database connector.
		$signature = md5(serialize(compact('options')));

		// Create instance.
		if (empty(self::$instances[$signature]))
		{
			$className   = basename(str_replace('\\', '/', __CLASS__));
			$classNameFQ = ' \\' . ucfirst(mb_strtolower($className));

			// If the class still doesn't exist we have nothing left to do but throw an exception.  We did our best.
			if (!class_exists($classNameFQ))
			{
				// TODO - translate
				throw new RuntimeException(sprintf('%s not available: %s', $className, $classNameFQ));
			}

			// Create new Model object based on the options given.
			try
			{
				$instance = new $classNameFQ($options);
			}
			catch (Throwable $e)
			{
				// TODO - translate
				throw new RuntimeException(sprintf('The following error occurred while trying to create an instance of class %s named %s: %s', $className, $classNameFQ, $e->getMessage()));
			}

			// Set the new Model object to the global instances based on signature.
			self::$instances[$signature] = $instance;
		}

		return self::$instances[$signature];
	}

	/**
	 * Add description...
	 *
	 * @param   string $uri
	 *
	 * @return  string
	 */
	public function fixRoute(string $uri) : string
	{
		$uri = trim($uri);

		return (preg_match('~^https?://~', $uri)) ? $uri : UriHelper::osSafe(UriHelper::fixURL($uri));
	}

	/**
	 * Class construct
	 *
	 * @param   string $uri  The URI to process.
	 *
	 * @return  string  The processed URI
	 *
	 * @since   2.7
	 *
	 * @todo    implement
	 */
	public function route(string $uri) : string
	{
		// TODO - implement
		return $uri;
		/*
		switch (true)
		{
			case (strpos($_SERVER['DOCUMENT_ROOT'], '/public/') !== false) :
			case (strpos($_SERVER['CONTEXT_DOCUMENT_ROOT'], '/public/') !== false) :
			case (strpos($_SERVER['SCRIPT_FILENAME'], '/public/') !== false) :
				$uri = '///' . $uri;
			break;
		}

		$uri = UriHelper::osSafe(UriHelper::fixURL($uri));

		return UriHelper::fixURL(UriHelper::osSafe($uri));
		*/
	}
}
