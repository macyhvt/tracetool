<?php
/* define application namespace */
namespace Nematrack\Utility;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Utility class to handle calculation and conversion
 *
 * @since  2.6
 */
class Math
{
	/**
	 * Utility method to convert a given numerical value from square feet to square meters.
	 *
	 * @param   int|float $val The value to convert
	 *
	 * @return  int
	 */
	public static function squareFeetFromSquareMeters($val) : int
	{
		// 1 m2 is equal to 10.7639104167 sq.ft
		return (int) ($val * 10.7639104167);
	}

	/**
	 * Utility method to convert a given numerical value from square meters to square feet.
	 *
	 * @param   int|float $val The value to convert
	 *
	 * @return  int
	 */
	public static function squareMetersFromSquareFeet($val) : int
	{
		// 1 sq.ft is equal to 0.09290304 m2
		return (int) ($val * 0.09290304);
	}

	/**
	 * Utility method to convert a given numerical value from miles to kilometers.
	 *
	 * @param   int|float $val The value to convert
	 *
	 * @return  int
	 */
	public static function milesFromKilometers($val) : int
	{
		// 1 km = 0.621371192 miles
		return (int) ($val * 0.621371192);
	}

	/**
	 * Utility method to convert a given numerical value from kilometers to miles.
	 *
	 * @param   int|float $val The value to convert
	 *
	 * @return  int
	 */
	public static function kilometersFromMiles($val) : int
	{
		// 1 mile = 1.609344 kilometers
		return (int) ($val * 1.609344);
	}

	/**
	 * Utility method to convert a given numerical value with a unit into bytes.
	 *
	 * @param   int|double|string $str  The value to convert
	 *
	 * @return  int The input value converted into bytes
	 *
	 * @since   2.6
	 */
	public static function toBytes($str) : int
	{
		$str     = trim($str);
		$measure = mb_strtolower(preg_replace('/\d*/', '', $str));
		$value   = intval($str);

		switch (true)
		{
			case in_array($measure, ['g', 'gb', 'gig', 'giga', 'gigabyte', 'gigabytes']) :
				// $value *= (1024 * 1024 * 1024);
				$value *= 1073741824;
			break;

			case in_array($measure, ['m', 'mb', 'meg', 'mega', 'megabyte', 'megabytes']) :
				// $value *= (1024 * 1024);
				$value *= 1048576;
			break;

			case in_array($measure, ['k', 'kb', 'kilobyte', 'kilobytes']) :
				$value *= 1024;
			break;
		}

		return $value;
	}

	/**
	 * Utility method to convert a given numerical value from Bytes into Gigabytes.
	 *
	 * @param   int|float $val  The value to convert
	 * @param   int       $decimals
	 *
	 * @return  float      The transformed value in Gigabytes
	 *
	 * @since    2.6
	 */
	public static function bytesToGigabytes($val, int $decimals = 10) : ?float
	{
		return round(($val / 1024 / 1024 / 1024), $decimals);
	}

	/**
	 * Utility method to convert a given numerical value from Bytes into Kilobytes.
	 *
	 *
	 * @param   int|float $val  The value to convert
	 * @param   int       $decimals
	 *
	 * @return  double|null The transformed value in Kilobytes
	 *
	 * @since   2.6
	 */
	public static function bytesToKilobytes($val, int $decimals = 10) : ?float
	{
		return round(($val / 1024), $decimals);
	}

	/**
	 * Utility method to convert a given numerical value from Bytes into Megabytes.
	 *
	 *
	 * @param   int|float $val  The value to convert
	 * @param   int       $decimals
	 *
	 * @return  float  The transformed value in Megabytes
	 *
	 * @since   2.6
	 */
	public static function bytesToMegabytes($val, int $decimals = 10) : ?float
	{
		return round(($val / 1024 / 1024), $decimals);
	}

	/**
	 * Utility method to convert a given numerical value from Pixel to Centimeters.
	 *
	 * Resolution in dpi means (d)ots (p)er (i)nch.
	 *        1 inch => 2.54 cm
	 *
	 * Typical image resolutions
	 *       72 dpi resolution =>  28 px/cm (pixels per Centimeter) (common resolution used for screen display)
	 *      100 dpi resolution =>  39 px/cm (pixels per Centimeter)
	 *      150 dpi resolution =>  59 px/cm (pixels per Centimeter) (common resolution used for digital print)
	 *      300 dpi resolution => 118 px/cm (pixels per Centimeter) (common resolution used for offset print)
	 *
	 * @param   int $pixels  The input value
	 * @param   int $dpi     The display resolution
	 *
	 * @return  float  The output value
	 *
	 * @link    https://din-formate.info/umrechnung-pixel-in-zentimeter.php
	 */
	public static function pixelToCentimeters(int $pixels = 0, int $dpi = 72) : float
	{
		// 1. Define the number of cm in 1 inch.
		$cmInInch = 2.54;

		// 2. Calculate the number of px in 1 cm.
		$pxInCm   = ($dpi / $cmInInch);

		// 3. Calculate the number of cm in given number of pixels.
		return $pixels / $pxInCm;
	}

	/**
	 * Implementation of Pythagorean theorem of a right-angled triangle.
	 *
	 * @param   float|int $a  Length of adjacent n
	 * @param   float|int $b  Length of opposite n
	 * @param   float|int $c  Length of hypotenuse
	 * @param   int       $precision  Number of decimal places
	 *
	 * @return  float|null
	 *
	 * @author  http://www.phpsnaps.com/snaps/view/pythagorean-theorem-function
	 */
	public static function pythagoras(float $a = 0, float $b = 0, float $c = 0, int $precision = 4) : ?float
	{
		$find = '';

		($a) ? $a = pow($a,2) : $find .= 'a';
		($b) ? $b = pow($b,2) : $find .= 'b';
		($c) ? $c = pow($c,2) : $find .= 'c';

		switch ($find)
		{
			case 'a':
				return round(sqrt($c - $b), $precision);

			case 'b':
				return round(sqrt($c - $a), $precision);

			case 'c':
				return round(sqrt($a + $b), $precision);
		}

		return null;
	}
}
