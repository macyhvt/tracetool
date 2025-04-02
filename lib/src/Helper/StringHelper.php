<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Class description
 */
final class StringHelper
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
	 * Convert HTML entities to their corresponding characters.
	 *
	 * @param   string  $str
	 *
	 * @return  string
	 *
	 * @uses    {@link https://www.php.net/manual/en/function.html-entity-decode.php}
	 */
	public static function entityDecode(string $str = '') : string
	{
		return html_entity_decode($str);
	}

	/**
	 * An improved array_search that allows for partial matching of strings values in associative arrays
	 * (Code borrowed with no changes from class ArrayHelper of CMS Joomla! v3.9.6)
	 *
	 * @param   string  $str  The string to be inspected.
	 *
	 * @return  true if the function parameter is base64 encoded or false if not
	 *
	 * @uses    {@link http://php.net/manual/en/function.base64-decode.php}
	 */
	public static function isBase64Encoded(string $str) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return true == base64_decode($str, true);
	}

	/**
	 * Takes a string and converts it to a proper boolean value or null.
	 *
	 * @param   string  $str  The input string
	 *
	 * @return  mixed   A properly boolean value or null
	 */
	public static function parseBool(string $str) :? bool
	{
		$str = trim($str);

		switch (true)
		{
			case ($str == true) :
			case ($str === 'true') :
				$val = true;
			break;

			case ($str == false) :
			case ($str === 'false') :
				$val = false;
			break;

			default :
				$val = null;
		}

		return $val;
	}

	/**
	 * Takes a numerical string representing a decimal value with either dot- or comma separated digits
	 * and converts it to a proper decimal number.
	 *
	 * @param   string  $str         The input string
	 * @param   boolean $forceFloat  Flag indicating whether to force type of return value being a valid PHP decimal. (Default: false)
	 *
	 * @return  string  when $forceFloat is false or float otherwise
	 *
	 * Code borrowed from:  {@link https://stackoverflow.com/a/44110263}
	 */
	public static function parseFloat(string $str, bool $forceFloat = false) : string
	{
		$str = trim($str);

		$val = str_replace(",", ".", $str);
		$val = preg_replace('/\.(?=.*\.)/', '', $val);

		// Fix input like '.123' to be displayed as '0.123'.
		if (preg_match('/^([.,])\d+/', $val))
		{
			$val = '0' . $val;
		}

		// Strip all characters that do not belong to a number.
		$val = preg_replace('/[^\d.,]/ui', '', $val);

		// FIXME - Should we make this a locale-dependency ?
		if (false === $forceFloat)
		{
			$val = str_replace(".", ",", $val);
		}

		// C A U T I O N :   This final floatval() execution will drop the comma-separated portion and
		// will make the output look like an Integer, although it will be a Double.
		// $val = floatval($val);

		return $val;
	}
}
