<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Class description
 */
final class JsonHelper
{
	/**
	 * Private constructor. Class cannot be constructed.
     *
     * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	/**
	 * Utility method to check a string is properly encoded JSON.
	 *
	 * Code borrowed with slight changes from {@link https://stackoverflow.com/a/15198925}
	 *
	 * @param   string  $string  The string to be inspected
	 *
	 * @return  bool    True if the input string is proper JSON or false if not.
	 *
	 * @todo    translate
	 */
	public static function isValidJSON(string $string) : bool
	{
		// Decode the JSON data to see whether this throws an error.
		json_decode($string);

		// switch and check possible JSON errors
		switch (json_last_error())
		{
			case JSON_ERROR_NONE:
				$error = ''; // JSON is valid // No error has occurred
			break;

			case JSON_ERROR_DEPTH:
				$error = 'The maximum JSON-data stack depth has been exceeded.';
			break;

			case JSON_ERROR_STATE_MISMATCH:
				$error = 'Invalid or malformed JSON-data.';
			break;

			case JSON_ERROR_CTRL_CHAR:
				$error = 'Control character error, possibly incorrectly encoded JSON-data.';
			break;

			case JSON_ERROR_SYNTAX:
				$error = 'Syntax error, malformed JSON-data.';
			break;

			// PHP >= 5.3.3
			case JSON_ERROR_UTF8:
				$error = 'Malformed UTF-8 characters, possibly incorrectly encoded JSON-data.';
			break;

			// PHP >= 5.5.0
			case JSON_ERROR_RECURSION:
				$error = 'One or more recursive references in the value to be encoded.';
			break;

			// PHP >= 5.5.0
			case JSON_ERROR_INF_OR_NAN:
				$error = 'One or more NAN or INF values in the value to be encoded.';
			break;

			case JSON_ERROR_UNSUPPORTED_TYPE:
				$error = 'A value of a type that cannot be encoded was given.';
			break;

			default:
				$error = 'Unknown JSON-error occurred.';
		}

		if ($error !== '')
		{
			// throw the Exception or exit // or whatever
			// exit($error);
			return false;
		}

		// Everything is OK
		return true;
	}
}
