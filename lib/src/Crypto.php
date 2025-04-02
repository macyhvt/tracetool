<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use RuntimeException;
use function array_walk;
use function class_exists;
use function is_null;
use function password_hash;
use function password_verify;

defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Class description
 */
abstract class Crypto
{
	/**
	 * Add description...
	 *
	 * @param   string|null $email
	 *
	 * @return  string
	 */
	public static function encryptEmailAddress(string $email = null) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_null($email))
		{
			return $email;
		}

		$chars   = str_split($email);
		$numbers = [];

		array_walk($chars, function($char) use(&$numbers)
		{
			$numbers[] = ord($char);
		});

		return implode('|', $numbers);
	}

	/**
	 * Add description...
	 *
	 * @param   string|null $email
	 *
	 * @return  string
	 */
	public static function decryptEmailAddress(string $email = null) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (is_null($email))
		{
			return $email;
		}

		$numbers = explode('|', $email);
		$chars   = [];

		array_walk($numbers, function($number) use(&$chars)
		{
			$chars[] = chr($number);
		});

		return implode('', $chars);
	}


	/**
	 * Add description...
	 *
	 * @param   int $length
	 *
	 * @return  string
	 */
	public static function generateAlNum(int $length = 10) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - translate
		if (!class_exists('Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator'))
		{
			throw new RuntimeException(sprintf('Missing dependency: %s', 'Hackzilla Password Generator'));
		}

		$generator = new ComputerPasswordGenerator;
		$generator
			->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
			->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
			->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
			->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
			->setLength($length);

		return $generator->generatePassword();
	}


	/**
	 * Add description...
	 *
	 * @return string
	 */
	public static function generatePassword() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - translate
		if (!class_exists('Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator'))
		{
			throw new RuntimeException(sprintf('Missing dependency: %s', 'Hackzilla Password Generator'));
		}

		$generator = new ComputerPasswordGenerator;
		$generator
			->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, FTKPARAM_PASSWORD_REQUIRE_UPPERCASE)
			->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, FTKPARAM_PASSWORD_REQUIRE_LOWERCASE)
			->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, FTKPARAM_PASSWORD_REQUIRE_NUMBERS)
			->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, FTKPARAM_PASSWORD_REQUIRE_SYMBOLS)
			->setLength(FTKPARAM_PASSWORD_MIN_LENGTH);

		return $generator->generatePassword();
	}

	/**
	 * Add description...
	 *
	 * @param   string $password
	 * @param   string $algo
	 *
	 * @return  string
	 */
	public static function hashPassword(string $password, string $algo = PASSWORD_DEFAULT) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return password_hash(trim($password), $algo);
	}

	/**
	 * Add description...
	 *
	 * @param   string $password
	 * @param   string $algo
	 *
	 * @return  string
	 */
	public static function rehashPassword(string $password, string $algo = PASSWORD_DEFAULT) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return password_hash(trim($password), $algo);
	}

	/**
	 * Add description...
	 *
	 * @param   string $password1
	 * @param   string $password2
	 *
	 * @return  bool   true on success or false on failure
	 */
	public static function verifyPassword(string $password1, string $password2) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return password_verify($password1, $password2);
	}
}
