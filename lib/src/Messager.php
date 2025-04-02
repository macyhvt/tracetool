<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Joomla\Utilities\ArrayHelper;
use Locale;
use Nematrack\Helper\LayoutHelper;
use Symfony\Component\HttpFoundation\Session\Session;
use function array_combine;
use function array_fill;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_pop;
use function array_push;
use function array_unique;
use function array_walk;
use function is_array;
use function is_null;
use function is_object;
use function is_string;

/**
 * Class description
 */
final class Messager extends App
{
	/**
	 * Collection of message headings
	 *
	 * @var array
	 */
	protected static array $headings = [];

	/**
	 * Collection of message types
	 *
	 * @var array
	 */
	protected static array $types = [];

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @since   0.1
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * Add description...
	 *
	 * @return  array
	 *
	 * @uses   {@link Session}
	 */
	public static function clearMessageQueue() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (class_exists('Symfony\Component\HttpFoundation\Session\Session'))
		{
			$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);	// if 3rd parameter is omitted, TrackingTool will fail to return a part's article number on API-request 'part.artnum'

			return $session->getFlashBag()->clear();
		}
		elseif (!is_array($_SESSION))
		{
			return $_SESSION['messages'] = [];
		}
		else
		{
			return [];
		}
	}

	/**
	 * Method to get the list of available message headings.
	 *
	 * @return  array  The list of available message headings.
	 *
	 * @since   0.1
	 */
	public static function getHeadingTypes() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return [
			'danger'  => 'COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT',
			'default' => 'COM_FTK_SYSTEM_MESSAGE_NOTICE_TEXT',
			'error'   => 'COM_FTK_SYSTEM_MESSAGE_ERROR_TEXT',
			'info'    => 'COM_FTK_SYSTEM_MESSAGE_INFORMATION_TEXT',
			'message' => 'COM_FTK_SYSTEM_MESSAGE_NOTICE_TEXT',
			'notice'  => 'COM_FTK_SYSTEM_MESSAGE_NOTICE_TEXT',
			'success' => 'COM_FTK_SYSTEM_MESSAGE_SUCCESS_TEXT',
			'warning' => 'COM_FTK_SYSTEM_MESSAGE_WARNING_TEXT'
		];
	}

	/**
	 * Method to get a specific message heading.
	 *
	 * @param   string $type  The name of a specific message heading to get.
	 *
	 * @return  null|string  The message heading or null if not found.
	 *
	 * @since   0.1
	 */
	public static function getHeadingType(string $type) : ?string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		self::$headings = (is_array(self::$headings) ? self::$headings : self::getHeadingTypes());

		if (array_key_exists($type, self::$headings))
		{
			return self::$headings[$type];
		}

		return null;
	}

	/**
	 * Method to get the list of available message types.
	 *
	 * @return  array  The list of available message types.
	 *
	 * @since   0.1
	 */
	public static function getMessageTypes() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return [
			'danger'    => 'danger',
			'dark'      => 'dark',
			'default'   => 'light',
			'error'     => 'danger',
			'info'      => 'info',
			'light'     => 'light',
			'message'   => 'warning',
			'notice'    => 'warning',
			'primary'   => 'primary',
			'secondary' => 'secondary',
			'success'   => 'success',
			'warning'   => 'warning'
		];
	}

	/**
	 * Method to get a specific message type.
	 *
	 * @param   string $type  The name of a specific message type to get.
	 *
	 * @return  null|string  The message heading or null if not found.
	 *
	 * @since   0.1
	 */
	public static function getMessageType(string $type) : ?string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		self::$types = (is_array(self::$types) ? self::$types : self::getMessageTypes());

		if (array_key_exists($type, self::$types))
		{
			return self::$types[$type];
		}

		return null;
	}

	/**
	 * Method to fetch the complete message queue.
	 *
	 * @return  array  The complete message queue.
	 *
	 * @since   0.1
	 */
	public static function getMessageQueue() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (class_exists('Symfony\Component\HttpFoundation\Session\Session'))
		{
			$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);	// if 3rd parameter is omitted, TrackingTool will fail to return a part's article number on API-request 'part.artnum'

			return $session->getFlashBag()->all();
		}
		elseif (!is_array($_SESSION))
		{
			return ArrayHelper::getValue($_SESSION, 'messages', [], 'ARRAY');
		}
		else
		{
			return [];
		}
	}

	/**
	 * Method to fetch the latest message from message queue.
	 *
	 * @return  string|null  Tha latest message from message queue.
	 *
	 * @since   1.8
	 */
	public static function getMessage() : ?string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$messages = self::getMessageQueue();
		$messages = ArrayHelper::getValue($messages, 'success', ArrayHelper::getValue($messages, 'message', [], 'ARRAY'), 'ARRAY');

		if (count($messages))
		{
			return array_pop($messages);
		}

		return null;
	}

	// FIXME - how to detect whether an error is set? If an error is set there's no reason to display the other message.
	public static function hasError() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$messages = self::getMessageQueue();
		$errors   = ArrayHelper::getValue($messages, 'error', [], 'ARRAY');

		return count($errors) > 0;
	}

	/**
	 * Method to fetch the latest error from message queue.
	 *
	 * @return  string|null  Tha latest error from message queue.
	 *
	 * @since   1.8
	 */
	public static function getError() : ?string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$messages = self::getMessageQueue();
		$errors   = ArrayHelper::getValue($messages, 'error', [], 'ARRAY');

		if (count($errors))
		{
			return array_pop($errors);
		}

		return null;
	}

	/**
	 * Method to set a message to be displayed.
	 *
	 * @param   array $message  The actual message text.
	 *
	 * @since   0.1
	 */
	public static function setMessage(array $message)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*$lang = ArrayHelper::getValue($options, 'language', substr(Locale::acceptFromHttp(
			ArrayHelper::getValue($_SERVER, 'HTTP_ACCEPT_LANGUAGE', '', 'STRING')
		), 0, 2));*/

		$messages = null;

		if (class_exists('Symfony\Component\HttpFoundation\Session\Session'))
		{
			$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);	// if 3rd parameter is omitted, TrackingTool will fail to return a part's article number on API-request 'part.artnum'

			$messages = $session->getFlashBag()->all();
		}

		// Init collection of all message types. If this collection does not contain any of the potential types
		// then setting the message will fail when using Symfony's FlashBag, as it does not store empty collections.
		// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
		$mockMsgs = array_combine($types = array_keys(self::getMessageTypes()), array_fill(0, count($types), []));
		// Merge messages in.
		$messages = array_merge($mockMsgs, $messages);

		$message = (is_array($message)) ? (object) $message : $message;
		$message = (is_object($message))
			? $message
			: (is_string($message)
				? (object) [
					'type' => 'light',
					'text' => $message,
					'code' => '200'
				]
				: null
			);

		if (is_null($message) || !is_object($message))
		{
			return;
		}

		if (is_array($messages) && array_key_exists($message->type, $messages))
		{
			$messages[$message->type][] = $message->text;
		}

		// Drop dupes.
		array_walk($messages, function(&$collection)
		{
			$collection = array_unique($collection);
		});

		if (class_exists('Symfony\Component\HttpFoundation\Session\Session'))
		{
			$session = ArrayHelper::getValue($GLOBALS, 'session', new Session);	// if 3rd parameter is omitted, TrackingTool will fail to return a part's article number on API-request 'part.artnum'

			if (is_a($session, 'Symfony\Component\HttpFoundation\Session\Session'))
			{
				if (!$session->isStarted())
				{
					$session->start();
				}

				foreach ($messages as $type => $collection)
				{
					foreach ($collection as $message)
					{
						$session->getFlashBag()->add($type, $message);
					}
				}
			}
		}
		elseif (is_array($_SESSION))
		{
			$_SESSION['messages'] = $messages;
		}
	}

	//@todo - remove outta here! Use layout files and move rendering into class Layout or LayoutHelper or a separate class Renderer

	/**
	 * Add description...
	 *
	 * @param   array $messages
	 * @param   array $options
	 *
	 * @return  array
	 */
	public static function render(array $messages, array $options = []) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!count($messages))
		{
			return self::clearMessageQueue();
		}

		$lang = ArrayHelper::getValue($options, 'language', substr(Locale::acceptFromHttp(
			ArrayHelper::getValue($_SERVER, 'HTTP_ACCEPT_LANGUAGE', '', 'STRING')
		), 0, 2));

		echo LayoutHelper::render('system.message', (object) ['messages' => $messages], ['language' => $lang]);

		return self::clearMessageQueue();
	}
}
