<?php
/* define application namespace */
namespace  ;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use  \Helper\FilesystemHelper;
use RuntimeException;
use stdClass;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ServerBag;
use Throwable;
use function array_key_exists;
use function is_string;
use function property_exists;

/**
 * Class description
 */
abstract class App
{
	/**
	 * Flag variable indicating whether debug mode is enabled/disabled
	 *
	 * @var   boolean
	 */
	protected $debug;

	/**
	 * Application language
	 *
	 * @var   string
	 */
	protected $language = null;

	/**
	 * Location for overloaded data.
	 * This data is set via the magic method __set() and retrieved via the magic method __get().
	 *
	 * @var   array
	 */
	protected $data = [];

	/**  Overloading is not used on declared properties.  */
	/**  Overloading is only used on protected or private properties when accessed outside the class.  */

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @since   0.1
	 */
	public function __construct(array $options = [])
	{
		$this->language = ArrayHelper::getValue($options, 'language', $this->get('language', Factory::getConfig()->get('app_language')), 'STRING');
		$this->debug    = ArrayHelper::getValue($options, 'debug', false, 'BOOLEAN');
	}

	/**
	 * Magic method to check whether a protected variable value is set.
	 *
	 * @param   string $prop  The name of the variable.
	 *
	 * @return  bool
	 */
	public function __isset(string $prop) : bool
	{
		switch (gettype($this->data))
		{
			case 'object' :
				return (property_exists($this->data, $prop)  && isset($this->data->$prop));

			case 'array' :
				return (array_key_exists($prop, $this->data) && isset($this->data[$prop]));

			default :
				return false;
		}
	}

	/**
	 * Magic method to unset a protected variable value
	 *
	 * @param   string $prop  The name of the variable.
	 *
	 * @return  void
	 */
	public function __unset(string $prop) : void
	{
		switch (gettype($this->data))
		{
			case 'object' :
				unset($this->data->$prop);
			break;

			case 'array' :
				unset($this->data[$prop]);
			break;
		}
	}

	/**
	 * Magic function to set protected variable value
	 *
	 * @param   string $prop   The name of the variable.
	 * @param   mixed  $value  The value of the variable.
	 *
	 * @return  void
	 */
	public function __set(string $prop, $value) : void
	{
		switch (gettype($this->data))
		{
			case 'object' :
				$this->data->$prop = $value;
			break;

			case 'array' :
				$this->data[$prop] = $value;
			break;
		}
	}

	/**
	 * Magic function to get protected variable value
	 *
	 * @param   string $prop  The name of the variable.
	 *
	 * @return  mixed   The property value or null
	 */
	public function __get(string $prop)
	{
		switch (gettype($this->data))
		{
//			case 'object' :
//				return ArrayHelper::getValue($this->data, $prop);

			case 'array' :
				return ArrayHelper::getValue($this->data, $prop);

			default :
				return null;
		}
	}

	/**
	 * Function to get a property.
	 *
	 * @param   string  $name      The name of the property to get
	 * @param   null    $fallback  The fallback value if there is no such property
	 *
	 * @return  mixed|null
	 */
	public function get(string $name, $fallback = null)
	{
		if (!isset($this->$name))
		{
			return $fallback;
		}
		else
		{
			switch (true)
			{
				case (is_string($this->$name) && trim($this->$name) === '' && isset($fallback)) :
					return $fallback;

				case (isset($this->$name)) :
					return $this->$name;

				default :
					return $this->$fallback;
			}
		}
	}

	/**
	 * Add description...
	 *
	 * @return mixed
	 */
	public static function getAppUser() : Entity\User
	{
		// $session     = ArrayHelper::getValue($GLOBALS, 'session', new Session(new NativeSessionStorage([], new NativeFileSessionHandler())));
		$session     = ArrayHelper::getValue($GLOBALS, 'session');
		$sessionUser = $session ? $session->get('user') : null;
		$requestLang = ArrayHelper::getValue($_GET, 'hl', null, 'STRING');

		return $sessionUser ?: Model::getInstance('user', ['language' => $requestLang])->getItem(0);
	}

	/**
	 * Return the picture batch upload path.
	 *
	 * @return  string The absolute path
	 *
	 * @since   2.11
	 */
	public static function getBatchUploadPath() : string
	{
		return FTKPATH_UPLOAD_BATCH;    // defined in Defines.php
	}

	/**
	 * Return the drawings root path.
	 *
	 * @return  string The absolute path
	 *
	 * @since   2.11
	 */
	public static function getDrawingsPath() : string
	{
		return FTKPATH_DRAWINGS;    // defined in Defines.php
	}

	public static function getDrawingDummy() : stdClass
	{
		$pdf   = FilesystemHelper::fixPath(implode(DIRECTORY_SEPARATOR, [App::getDrawingsPath(), '__DUMMY-DO-NOT-TOUCH__', 'AAA.BBB.CC.DDDDD.000.0.pdf']));
		$thumb = FilesystemHelper::fixPath(implode(DIRECTORY_SEPARATOR, [App::getDrawingsPath(), '__DUMMY-DO-NOT-TOUCH__', 'AAA.BBB.CC.DDDDD.000.0__thumb.png']));

		if (!is_file($pdf) || !is_readable($pdf))
		{
			throw new RuntimeException('There is no dummy drawing in the specified path.', 500);
		}

		if (!is_file($thumb) || !is_readable($thumb))
		{
			throw new RuntimeException('There is no dummy drawing preview thumbnail in the specified path.', 500);
		}

		return (object) [
			'pdf'   => $pdf,
			'thumb' => $thumb
		];
	}

	/**
	 * Function to get reference to one of the super globals (GET|POST|REQUEST|FILES|SERVER) .
	 *
	 * @param   string|null $source  Name of the super global to get. If omitted .
	 *
	 * @return  Input  The requested super global object.
	 *
	 * @uses    Input
	 *
	 * @since   2.0.0
	 *
	 * @deprecated Deprecated since version 2.7.0 in favour of Symfony\Component\HttpFoundation\Request
	 */
	public static function getInput(string $source = null) : Input
	{
		$input = new Input;

		$source = strtolower(trim('' . $source));
		$source = ($source ?? null);

		switch (strtoupper($source))
		{
			case 'GET' :
			case 'POST' :
			case 'JSON' :
			case 'FILES' :
			case 'COOKIE' :
			case 'SERVER' :
				return $input->{$source};

			case 'REQUEST' :
			default:
				return $input->request;
		}
	}

	/**
	 * Return the media-files root path.
	 *
	 * @return  string The absolute path
	 *
	 * @since   2.11
	 */
	public static function getMediafilesPath() : string
	{
		return FTKPATH_MEDIA;    // defined in Defines.php
	}

	/**
	 * Function to get reference to one of the super globals (GET|POST|REQUEST|FILES|SERVER) .
	 *
	 * @param   string|null $bag  Name of the InputBag to get.
	 *
	 * @return  Request|FileBag|HeaderBag|InputBag|ServerBag
	 *
	 * @uses    {@link Request}
	 *
	 * @since   2.0.0
	 *
	 * @todo    Replace all calls for ::getInput with ::getRequest
	 */
	public static function getRequest(string $bag = null)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $bag . ')', true) . '</pre>' : null;

		//@test
		return new Registry;

		// Get globals like (GET|POST|...)
		$request = Request::createFromGlobals();

		$bag = mb_strtolower(trim('' . $bag));
		$bag = ($bag ?? null);

		try
		{
			switch ($bag)
			{
				/* Return type:
				 *
				 *	Symfony\Component\HttpFoundation\ParameterBag or
				 *	Symfony\Component\HttpFoundation\InputBag if the data is coming from $_POST parameters
				 */
				case 'post' :       // for convenience only - is in fact not a property, but may be requested
				case 'request' :    // equivalent of $_POST
					$retVal = $request->request;
				break;

				// Return type: Symfony\Component\HttpFoundation\InputBag
				case 'get' :        // for convenience only - is in fact not a property, but may be requested
				case 'query' :      // equivalent of $_GET ($request->query->get('name'))
					$retVal = $request->query;
				break;

				// Return type: Symfony\Component\HttpFoundation\ParameterBag
				case 'attributes' : // no equivalent - used by your app to store other data

				// Return type: Symfony\Component\HttpFoundation\HeaderBag
				case 'headers' :    // mostly equivalent to a subset of $_SERVER ($request->headers->get('User-Agent'))

				// Return type: Symfony\Component\HttpFoundation\InputBag
				case 'cookies' :    // equivalent of $_COOKIE

				// Return type: Symfony\Component\HttpFoundation\ServerBag
				case 'server' :     // equivalent of $_SERVER

				// Return type: Symfony\Component\HttpFoundation\FileBag
				case 'files' :      // equivalent of $_FILES
//				case 'json' :
					$retVal = $request->{$bag};
				break;

				default :
					$retVal = $request;
			}
		}
		catch (Throwable $e)
		{
			throw new RuntimeException(sprintf('An error occurred while accessing the requested resource <strong>%s</string>: %s', $bag, $e->getMessage()));
		}

		return $retVal;
	}

	/**
	 * Add description...
	 *
	 * @return  RequestStack
	 */
	public static function getRequests() : RequestStack
	{
		return new RequestStack;
	}

	/**
	 * Add description...
	 *
	 * @return  Router
	 */
	public static function getRouter() : Router
	{
		return Router::getInstance();
	}

	/**
	 * Detect if PHP is x86 or x64.
	 *
	 * Therefore, var_dump the PHP_INT_SIZE constant.
	 * It'll vary based on the size of the register (i.e. 32-bit vs 64-bit).
	 *
	 * In 32-bit systems it should be 4.
	 * In 64-bit systems it should be 8.
	 */
	public static function getPHPArchitecture() : string
	{
		return ((PHP_INT_SIZE === 8) ? '64-bit' : '32-bit');
	}

	/**
	 * Method to detect whether the application is running in development context.
	 *
	 * @return  bool
	 */
	public static function isDevEnv() : bool
	{
		return in_array($_SERVER['HTTP_HOST'], [
			'dev. .com',
			'webservice.froetek.website'
		]);
	}

	public static function sayHelloJson() : JsonResponse
	{
		$response = new JsonResponse(
			['message' => 'Hello Jason!'],
			200
		);

		$response->headers->set('Content-Type', 'application/json');
//		$response->headers->set('Content-Type', 'application/problem+json');

		return $response;
	}

	/**
	 * Function to bind values to instance properties if existing.
	 *
	 * @param   mixed $data  Array or Object holding the values to bind
	 *
	 * @return App object to support chaining
	 *
	 * @throws Exception When something goes wrong while (pre)processing date properties
	 */
	protected function bind(array $data = []) : self
	{
		foreach ($data as $key => $val)
		{
			if (property_exists($this, $key))
			{
				if (is_string($val))
				{
					$val = trim($val);
				}

				if (is_string($val) && preg_match('#^(COM_FTK|MENU_ITEM)_#', $val))
				{
					$this->$key = Text::translate($val, $this->language);
				}
				else
				{
					$this->$key = $val;
				}
			}
		}

		// Prepare any date types
		try
		{
			if (property_exists($this, 'created') && is_string($this->created))
			{
				$this->created = ($this->created !== FTKRULE_NULLDATE ? new DateTime($this->created, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			/*// FIXME - recursion error
			if (\property_exists($this, 'created_by'))
			{
				$this->created_by = Model::getInstance('user', ['language' => $this->language])->getItem((int) $this->created_by);
			}*/

			if (property_exists($this, 'modified') && is_string($this->modified))
			{
				$this->modified = ($this->modified !== FTKRULE_NULLDATE ? new DateTime($this->modified, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			/*// FIXME - recursion error
			if (\property_exists($this, 'modified_by'))
			{
				$this->modified_by = Model::getInstance('user', ['language' => $this->language])->getItem((int) $this->modified_by);
			}*/

			if (property_exists($this, 'deleted') && is_string($this->deleted))
			{
				$this->deleted = ($this->deleted !== FTKRULE_NULLDATE ? new DateTime($this->deleted, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			/*// FIXME - recursion error
			if (\property_exists($this, 'deleted_by'))
			{
				$this->deleted_by = Model::getInstance('user', ['language' => $this->language])->getItem((int) $this->deleted_by);
			}*/

			if (property_exists($this, 'blockDate') && is_string($this->blockDate))
			{
				$this->blockDate = ($this->blockDate !== FTKRULE_NULLDATE ? new DateTime($this->blockDate, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			if (property_exists($this, 'lastLogoutDate	') && is_string($this->lastLogoutDate))
			{
				$this->lastLogoutDate = ($this->lastLogoutDate !== FTKRULE_NULLDATE ? new DateTime($this->lastLogoutDate, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			if (property_exists($this, 'lastResetTime') && is_string($this->lastResetTime))
			{
				$this->lastResetTime = ($this->lastResetTime !== FTKRULE_NULLDATE ? new DateTime($this->lastResetTime, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			if (property_exists($this, 'lastVisitDate') && is_string($this->lastVisitDate))
			{
				$this->lastVisitDate = ($this->lastVisitDate !== FTKRULE_NULLDATE ? new DateTime($this->lastVisitDate, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}

			if (property_exists($this, 'registerDate') && is_string($this->registerDate))
			{
				$this->registerDate = ($this->registerDate !== FTKRULE_NULLDATE ? new DateTime($this->registerDate, new DateTimeZone(FTKRULE_TIMEZONE)) : FTKRULE_NULLDATE);
			}
		}
		catch (Exception $e) {}

		return $this;
	}
}
