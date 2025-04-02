<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Nematrack\App;
use Nematrack\Crypto;
use Nematrack\Entity\User;
use Nematrack\Factory;
use Nematrack\Messager;
use Nematrack\Text;
use function array_walk;
use function is_a;
use function is_int;

/**
 * Class description
 */
final class UserHelper
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
	 * Updates the hash for a given password in the database.
	 *
	 * @param  User   $user
	 * @param  string $password
	 *
	 * @return int The number of affected rows
	 *
	 * @throws InvalidArgumentException|Exception when user could not be validated or the password provided doesn't meet the minimum requirement(s)
	 */
	public static function rehashPassword(User $user, string $password) : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($userID = $user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return 0;
		}

		if (mb_strlen(trim($password)) < FTKPARAM_PASSWORD_MIN_LENGTH)
		{
			// TODO - translate
			throw new InvalidArgumentException(
				Text::translate('COM_FTK_HINT_PASSWORD_TOO_SHORT_TEXT')
			);
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$rehashedPassword = Crypto::rehashPassword($password);

		// Build query.
		$query = $db->getQuery(true)
		->update($db->qn('users'))
		->set($db->qn('password') . ' = ' . $db->q($rehashedPassword))
		->where($db->qn('userID') . ' = ' . (int) $userID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			$affectedRows = null;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $affectedRows;
	}

	/**
	 * Updates the last visit date for a given user.
	 *
	 * @param User $user
	 *
	 * @return  int The number of affected rows
	 *
	 * @throws  InvalidArgumentException|Exception when user could not be validated
	 */
	public static function setLastVisitDate(User $user) : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($userID = $user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return 0;
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

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
		->update($db->qn('users'))
		->set($db->qn('lastVisitDate') . ' = ' . $db->q($now))
		->where($db->qn('userID') . ' = ' . (int) $userID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			$affectedRows = null;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $affectedRows;
	}

	/**
	 * Updates the logout date for a given user.
	 *
	 * @param User $user
	 *
	 * @return  int
	 *
	 * @throws  InvalidArgumentException|Exception when user could not be validated
	 */
	public static function setLogoutDate(User $user) : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($userID = $user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return 0;
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

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
		->update($db->qn('users'))
		->set($db->qn('lastLogoutDate') . ' = ' . $db->q($now))
		->where($db->qn('userID') . ' = ' . (int) $userID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			$affectedRows = null;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $affectedRows;
	}

	/**
	 * Stores a user's IP address used when logging in into the database.
	 *
	 * @param User $user
	 *
	 * @return  int
	 *
	 * @throws  InvalidArgumentException|Exception when user could not be validated
	 */
	public static function setUserIP(User $user) : int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($userID = $user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return 0;
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$input   = new Input;
		$server  = $input->server;

		// Get the user's IP address.
		$IP = $server->get('REMOTE_ADDR', '0', 'STRING');

		// Execute query.
		try
		{
			$db
			->setQuery('
				INSERT IGNORE INTO `user_ip` (`userID`, `IP`, `wasIP`)
				VALUES (' .
					$db->q((int) $userID) . ',
					INET_ATON("' . $IP . '"), ' .
					$db->q($server->get('REMOTE_ADDR')) .
				')'
			)
			->execute();

			$affectedRows = $db->getAffectedRows();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			$affectedRows = null;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $affectedRows;
	}

	/**
	 * Returns for a given user its previously tracked process(es).
	 *
	 * @param User $user
	 *
	 * @return  array
	 *
	 * @throws  InvalidArgumentException|Exception when user could not be validated
	 */
	public static function getPreviouslyTrackedProcess(User $user) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return 0;
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build sub-query first.
		$sub = $db->getQuery(true)
		->select('MAX(' . $db->qn('timestamp') . ')')
		->from($db->qn('tracking'))
		->where([
			$db->qn('paramID')    . ' = 2',
			$db->qn('paramValue') . ' = ' . $db->q($user->get('fullname'))
		]);

		// Build query.
		$query = $db->getQuery(true)
		->select(
			$db->qn([
				'partID',
				'procID',
				'paramID',
				'paramValue',
				'timestamp',
				'viaAutoTrack'
			])
		)
		->from($db->qn('tracking'))
		->where([
			$db->qn('timestamp')  . ' = (' . $sub . ')',
			$db->qn('paramValue') . ' = ' . $db->q($user->get('fullname'))
		]);

		try
		{
			$track = (array) $db->setQuery($query)->loadAssoc();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			$track = [];
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $track;
	}

	/**
	 * Returns for a given user its profile data
	 *
	 * @param User $user
	 *
	 * @return  array
	 *
	 * @throws  InvalidArgumentException|Exception when user could not be validated
	 */
	public static function getProfile(User $user) : array
	{
		// UserHelper::getProfile($user->get('userID'))->get('app.language')
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init vars.
		$profile = [];

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return $profile;
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select([
			$db->qn('profile_key')   . ' AS ' . $db->qn('key'),
			$db->qn('profile_value') . ' AS ' . $db->qn('value')
		])
		->from($db->qn('user_profile'))
		->where($db->qn('userID') . ' = ' . (int) $user->get('userID'));

		try
		{
			$rs = $db->setQuery($query)->loadObjectList();

			if (!empty($rs))
			{
				$profile = new Registry;

				array_walk($rs, function($row) use(&$profile)
				{
					$profile->set($row->key,
						(JsonHelper::isValidJSON($row->value)
							? json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR)
							: trim($row->value))
					);
				});

				$profile = $profile->toArray();
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			$profile = [];
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $profile;
	}

	/**
	 * Returns for a given user whether it belongs to either FR�TEK or NEMATECH
	 *
	 * @param User $user
	 *
	 * @return  bool
	 */
	public static function isFroetekOrNematechMember(User $user) : bool
	{
		// UserHelper::getProfile($user->get('userID'))->get('app.language')
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$x = App::getAppUser();

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function argument must be an instance of User');
		}

		if (!is_int($user->get('userID')))
		{
			// TODO - translate
//			throw new InvalidArgumentException('User has no ID');
			return 0;
		}

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->select($db->qn('o.name'))
			->from($db->qn('organisation_user')      . ' AS ' . $db->qn('ou'))
			->join('INNER', $db->qn('organisations') . ' AS ' . $db->qn('o') . ' ON ' . $db->qn('o.orgID') . ' = ' . $db->qn('ou.orgID'))
			->where($db->qn('ou.userID') . ' = ' . (int) $user->get('userID'));

			// Execute query.
			$rs = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => $e->getMessage()
			]);

			return false;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return preg_match('/^(FRÖTEK|Nematech)/i', trim($rs));
	}
}
