<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */

use InvalidArgumentException;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use function array_merge;
use function is_array;
use function is_object;

defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * ArrayHelper is an array utility class for doing all sorts of odds and ends with arrays.
 * (Code borrowed with no changes from class ArrayHelper of CMS Joomla! v3.9.6)
 */
final class ArrayHelper
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
	 * Add description ...
	 *
	 * @param   array $data
	 * @param   int   $decimals
	 *
	 * @return  float
	 */
	public static function average(array $data, int $decimals = 2): float
	{
		$avg = (!empty($data)) ? array_sum($data) / count($data) : 0;

		return round($avg, $decimals);
	}

	/**
	 * Add description ...
	 *
	 * @param   array|object  $A  An array or object to be compared
	 * @param   array|object  $B  An array or object to be compared
	 *
	 * @return  int
	 *
	 * Code borrowed from:  {@link https://stackoverflow.com/questions/6053994/using-usort-in-php-with-a-class-private-function}
	 */
	public static function compareByName($A, $B): int
	{
		switch (true)
		{
			case (is_array($A) && is_array($B)) :
				return strcmp($A['name'], $B['name']);

			case (is_object($A) && is_object($B)) :
				return strcmp($A->name, $B->name);

			default :
				// TODO - translate
				throw new InvalidArgumentException('Both arguments must be of equal type.');
		}
	}

	/**
	 * Method to recursively convert data to a one-dimensional array.
	 * This method improves Joomla!'s {@see Registry::toFlatten()} in a way that
	 * the last element in the path is stored as array value of its predecessor.
	 *
	 * Code borrowed with some modification from {@link https://packagist.org/packages/joomla/registry}
	 *
	 * @param   string       $separator  The key separator
	 * @param   array|object $data       Data source of this scope
	 * @param   string       $prefix     Last level key prefix
	 *
	 * @return  array  The flattened array
	 *
	 * @since   1.6.0
	 */
	public static function flatten(string $separator, $data, string $prefix = ''): array
	{
		$separator = '' . trim($separator);
		$data      = (array) $data;
		$return    = [];

		if (empty($separator))
		{
			$separator = '.';
		}

		foreach ($data as $key => $value)
		{
			$keyA = ($prefix ? $prefix . $separator . $key : $key);

			if ((is_object($value) || is_array($value)) && self::isMulti($value))
			{
				$return = array_merge($return, self::flatten($separator, (array) $value, $keyA));
			}
			else
			{
				$return[$keyA] = $value;
			}
		}

		return $return;
	}

	/**
	 * Method to filter an array of nested arrays recursively.
	 *
	 * It works exactly the same as array_filter except it filters within multidimensional arrays.
	 *
	 * Code borrowed with minimal change to fit in this class from Benjam Welker
	 *
	 * @param   array         $array
	 * @param   callable|null $callback             optional filter callback function
	 * @param   bool          $remove_empty_arrays  optional flag removal of empty arrays after filtering
	 *
	 * @return  array  The filtered array
	 *
	 * @author  Benjam Welker
	 * @link    https://github.com/benjamw
	 * @link    https://gist.github.com/benjamw/1690140
	 *
	 * @since   2.10.1
	 */
	public static function filterRecursive(array $array, callable $callback = null, bool $remove_empty_arrays = false): array
	{
		foreach ($array as $key => &$value)     // mind the reference
		{
			if (is_array($value))
			{
				$value = call_user_func_array(__CLASS__ .'::'. __FUNCTION__, array($value, $callback, $remove_empty_arrays));

				if ($remove_empty_arrays && !$value)
				{
					unset($array[$key]);
				}
			}
			else
			{
				if ( ! is_null($callback) && ! $callback($value))
				{
					unset($array[$key]);
				}
				elseif ( !$value)
				{
					unset($array[$key]);
				}
			}
		}

		unset($value); // kill the reference

		return $array;
	}

	/**
	 * Method to check if an array is multidimensional.
	 *
	 * @param   array $data
	 *
	 * @return  bool  True if array is multidimensional, false if not
	 *
	 * @since   1.6.0
	 */
	public static function isMulti(array $data): bool
	{
		foreach ($data as $value)
		{
			if (is_object($value) || is_array($value))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Method to recursively search an array for a given value.
	 * Returns the root element key if $needle is found, or FALSE if $needle cannot be found.
	 *
	 * Code borrowed with minimal change to fit in this class from Joseph Wynn
	 *
	 * @param   mixed  $needle
	 * @param   array  $haystack
	 * @param   bool   $strict
	 *
	 * @return  mixed|bool
	 *
	 * @author  Joseph Wynn <joseph@wildlyinaccurate.com>
	 * @link    https://github.com/wildlyinaccurate
	 * @link    https://gist.github.com/wildlyinaccurate/2474033
	 *
	 * @since   2.10.1
	 */
	public static function searchRecursive($needle, array $haystack, bool $strict = true)
	{
		$iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack), RecursiveIteratorIterator::SELF_FIRST);

		$current_key = null;

		while ($iterator->valid())
		{
			if ($iterator->getDepth() === 0)
			{
				$current_key = $iterator->key();
			}

			if ($strict && $iterator->current() === $needle)
			{
				return $current_key;
			}
			elseif ($iterator->current() == $needle)
			{
				return $current_key;
			}

			$iterator->next();
		}

		return false;
	}

	/**
	 * Method to recursively convert a PHP object incl. all child objects to an array.
	 *
	 * Code borrowed with minimal change to fit in this class from Joseph Wynn
	 *
	 * @param   mixed  $obj  The element to convert
	 *
	 * @return  array|mixed  The converted array or the input value
	 *
	 * @author  James Geldart
	 * @link    https://stackoverflow.com/a/54131002
	 *
	 * @since   2.10.1
	 */
	public static function toArray($obj)
	{
		// only process if it's an object or array being passed to the function
		if (is_object($obj) || is_array($obj))
		{
			$ret = (array) $obj;

			foreach ($ret as &$item)
			{
				// recursively process EACH element regardless of type
				$item = self::toArray($item);
			}

			return $ret;
		}
		// otherwise, (i.e. for scalar values) return without modification
		else
		{
			return $obj;
		}
	}

}
