<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Registry\Registry;
use Joomla\String\Normalise;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Crypto;
use Nematrack\Entity;
use Nematrack\Factory;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model;
use Nematrack\Text;
use ReflectionClass;
use function array_flip;
use function array_shift;
use function array_walk;
use function in_array;
use function is_a;
use function is_array;
use function is_null;
use function is_string;
use function method_exists;
use function password_needs_rehash;

// use Nematrack\Access\User;		// Removing this namespaced access may conflict with Entity and/or Model because of equal class name 'User'
// Removing this namespaced access may conflict with Access\User and/or Entity because of equal class name 'User'
// Removing this namespaced access may conflict with Access\User and/or Model because of equal class name 'User'

/**
 * Class description
 */
class User extends Entity
{
	use \Nematrack\Traits\Entity\User;

	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $userID = null;

	/**
	 * @var    integer  A user's organisation id.
	 * @since  1.1
	 */
	protected $orgID = null;

	/**
	 * @var    string  A user's real name.
	 * @since  1.1
	 */
	protected $fullname = null;

	/**
	 * @var    string  A user's email address.
	 * @since  1.1
	 */
	protected $email = null;

	/**
	 * @var    string  A user's password hashed.
	 * @since  1.1
	 */
	protected $password = null;

	/**
	 * @var    integer  A user's access state.
	 * @since  1.1
	 */
	protected $blocked = null;

	/**
	 * @var    string  Date when a user was blocked.
	 * @since  1.1
	 */
	protected $blockDate = null;

	/**
	 * @var    string  The name of the blocker of this row.
	 * @since  1.1
	 */
	protected $blocked_by = null;

	/**
	 * @var    array  Nematrack\Entity\Language objects container.
	 * @since  1.1
	 */
	protected $languages = [];

	/**
	 * @var    array  Nematrack\Entity\Group objects container.
	 * @since  1.1
	 */
	protected $groups = [];

	/**
	 * @var    Organisation  The organisation a user belongs to.
	 * @since  1.1
	 */
	// protected $organisation = null;

	/**
	 * @var    string  Date when a user has registered.
	 * @since  1.1
	 */
	protected $registerDate = null;

	/**
	 * @var    string  Date when a user logged in the last time.
	 * @since  1.1
	 */
	protected $lastVisitDate = null;

	/**
	 * @var    string  Date when a user logged off the last time.
	 * @since  1.1
	 */
	protected $lastLogoutDate = null;

	/**
	 * @var    string  Date when a user reset its password the last time.
	 * @since  1.1
	 */
	protected $lastResetTime = null;

	/**
	 * @var    int  User password reset count.
	 * @since  1.1
	 */
	protected $resetCount = null;

	/**
	 * @var    int  Role to require a user's password be reset
	 * @since  1.1
	 */
	protected $requireReset = null;

	/**
	 * @var    DateTime  The row creation date and time.
	 * @since  1.1
	 */
	protected $created = null;

	/**
	 * @var    string  The name of the creator of this row.
	 * @since  1.1
	 */
	protected $created_by = null;

	/**
	 * @var    DateTime  Date and time when this row was last edited.
	 * @since  1.1
	 */
	protected $modified = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $modified_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as deleted.
	 * @since  1.1
	 */
	protected $deleted = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $deleted_by = null;


	/** Properties that are no database table columns */


	// Helper property serving reference to a second class to inherit from.
	protected $access = null;

	/**
	 * {@inheritdoc}
	 * @see Entity::__construct
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		// Init helper property.
		$this->access = new \Nematrack\Access\User;

		// TODO - populate user groups and calc. access rights
		$this->groups = [];
	}

	/**
	 * Magic function utilised to circumvent inheritance is limited to 1 class.
	 * This hack utilises a private helper property, {@link User::access},
	 * to implement inheritance from a second class. Function calls to this class
	 * are passed through.
	 *
	 * (Code borrowed with some modification from: {@link https://stackoverflow.com/a/356431})
	 *
	 * @param   string $method  Name of the function to call.
	 * @param   array  $args    Arguments to be passed to th called function.
	 */
	public function __call(string $method, array $args)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->access->$method($args[0] ?? null);
	}

	/**
	 * Returns a user's organisation.
	 *
	 * @return  Organisation
	 */
	public function getOrganisation() : Organisation
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return Model::getInstance('organisation', ['language' => $this->language])->getItem($this->get('orgID') ?? 0);
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::getTableName
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return 'users';
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::bind
	 */
	public function bind(array $data = []) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$data = (array) $data;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		if ($this->userID == '0')
		{
			return $this;
		}

		/* Load user languages data */
		if (!is_array($this->languages))
		{
			// FIXME - move loading data outta here. This object should only receive data and prepare it.
			$languages = Model::getInstance('languages', ['language' => $this->language])->getList();

			$userLangs = [];

			if (is_string($userLangs = $this->get('languages')))
			{
				$userLangs = json_decode((string) $userLangs, null, 512, JSON_THROW_ON_ERROR);
			}

			$userLangs = (array) $userLangs;
			$tmp       = [];

			array_walk($languages, function($language, $tag) use(&$userLangs, &$tmp)
			{
				$language = new Registry($language);

				if (in_array($language->get('lngID'), $userLangs, true))
				{
					$tmp[$language->get('lngID')] = $language->toObject();
				}
			});

			$this->languages = $tmp;

			// Free memory.
			unset($userLangs);
			unset($tmp);
		}

		/* Load user groups and access data */

		// Get reference to user roles object.
		/* Convert entity groups from JSON to Object */
		$userGroups = $this->get('groups');

		if (!is_null($userGroups) && !is_array($userGroups) && !is_object($userGroups) && (is_string($userGroups) || empty($userGroups)))
		{
			$userGroups = (array) json_decode($userGroups, null, 512, JSON_THROW_ON_ERROR);
		}
		else
		{
			if (is_null($userGroups))
			{
				$userGroups = [];
			}
		}

		// Fetch corresponding groups.
		// FIXME - move loading data outta here. This object should only receive data and prepare it.
		$userGroups = Model::getInstance('groups', ['language' => $this->language])->getList($userGroups);

		// Get all role flags.
		// Code borrowed with some modification from: {@link https://stackoverflow.com/a/9632440}
		$class     = new ReflectionClass('Nematrack\Access\User');
		$constants = array_flip($class->getConstants());
		$user      = &$this;

		if (!is_array($user->groups))
		{
			$user->groups = $userGroups;
		}

		// Get reference to user access object.
		$userAccess = $this->get('access');

		$tmp = [];

		array_walk($userGroups, function($group) use(&$constants, &$user, &$userAccess, &$tmp)
		{
			$group    = Entity::getInstance('group', ['language' => $this->language])->bind($group);
			$role     = ArrayHelper::getValue($constants, $group->getFlag(), '', 'STRING');
			$roleName = explode('_', $role);

			// Skip prefix.
			array_shift($roleName);

			// Convert leftover string to CamelCase format.
			$roleName = Normalise::toCamelCase(mb_strtolower(implode('_', $roleName)));

			// Build name of setter to call.
			$funcName = 'make' . $roleName;

			if (!method_exists($userAccess, $funcName))
			{
				throw new Exception(sprintf('%s: Object %s has no such method: %s()', __METHOD__, get_class($userAccess), $funcName));

				return;
			}

			// Assign group bit.
			$userAccess->$funcName(true);
		});

		return $user;
	}

	public function login($email, $password)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $email . '", "*** HIDDEN ***")', true) . '</pre>' : null;

		$loaded = $this->load($email, $password);    // mixed value: object or false

		if (!is_a($loaded, 'Nematrack\Entity\User'))
		{
			return false;
		}
		else
		{
			$this->userID        = $loaded->userID;
			$this->blocked       = $loaded->blocked;
			$this->fullname      = $loaded->fullname;
			$this->email         = $loaded->email;
			$this->password      = $loaded->password;
			$this->blockDate     = $loaded->blockDate;
			$this->lastVisitDate = $loaded->lastVisitDate;
			$this->registerDate  = $loaded->registerDate;
			$this->lastResetTime = $loaded->lastResetTime;
			$this->requireReset  = $loaded->requireReset;
			$this->resetCount    = $loaded->resetCount;
			$this->languages     = $loaded->languages;

			UserHelper::setLastVisitDate($this);
			UserHelper::setUserIP($this);

			return $this;
		}
	}

	public function logout()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return UserHelper::setLogoutDate($this);
	}

	protected function grantAccess(&$user, $email, $password)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $user->get('name') . '", "' . $email . '", "*** HIDDEN ***")', true) . '</pre>' : null;

		// Check 1: Is user already registered?
		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf('%s %s %s',
					Text::translate('The email address provided is wrong or you don\'t have an account yet.', $this->language),
					Text::translate('Please check, correct and retry.', $this->language),
					Text::translate('Kindly contact the administration if you think this is a mistake.', $this->language)
				)
			]);

			return false;
		}

		/*
		 * https://crackstation.net/hashing-security.htm			// look for section title "The RIGHT Way: How to Hash Properly"
		 * https://php.net/manual/de/function.password-hash.php
		 * https://php.net/manual/de/function.password-verify.php
		 *
		 * https://hotexamples.com/examples/-/-/password_hash/php-password_hash-function-examples.html
		 */

		// Check 2: Is the provided password valid?   (passed clear text password matches stored password hash)
		$validPW = Crypto::verifyPassword($password, $user->get('password', ''));    // currently the default algo is 'bcrypt' with a variable option named 'cost', which may be increased by passing an options array like this ['cost' => 12] with 'cost' having a default value of 10

		if (!$validPW)
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => Text::translate('Invalid login credentials.', $this->language)
			]);

			return false;
		}

		// Is current user password (stored hash) outdated?
		if ($isOutdatedPW = password_needs_rehash($user->get('password'), PASSWORD_DEFAULT))
		{
			// ...update pw in the database.
			UserHelper::rehashPassword($this, $password);
		}

		/* //@test
		$pw1    = 'V{We2V(&XI|V$l;f:9BosV.LfVSkX"9bzNr7XO9;f#}x56Xm7A';		// original pw used for registration
		$pw2    = 'V{We2V(&XI|V$l;f:9BosV.LfVSkX"9bzNr7XO9;f#}x56Xm7AO';	// new pw passed via login form
		$pw3    = $password;												// original pw used for registration
		$hash1  = $user->get('password');											// hash from original pw used for registration
		$hash2  = Crypto::hashPassword($pw2);					// hash for new pw passed via login form
		$hash3  = Crypto::hashPassword($pw3);					// hash for new pw passed via login form
		$valid1 = Crypto::verifyPassword($pw1, $hash1);
		$valid2 = Crypto::verifyPassword($pw2, $hash1);
		$valid3 = Crypto::verifyPassword($pw3, $hash1);
		*/

		// Check 3: Is the user blocked?
		$isBlocked = $user->get('blocked', true);

		if ($isBlocked)
		{
			Messager::setMessage([
				'type' => 'error',
				// TODO - translate
				'text' => sprintf('%s<br>%s',
					Text::translate('COM_FTK_SYSTEM_MESSAGE_USER_ACCESS_DENIED_TEXT', $this->language),
					Text::translate('Kindly contact the administration if you think this is a mistake.', $this->language)
				)
			]);

			return false;
		}

		return $validPW && !$isBlocked;
	}

	protected function load($email, $password = null)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $email . '", "*** HIDDEN ***")', true) . '</pre>' : null;

		// FIXME - move loading data outta here. This object should only receive data and prepare it.
		$user = Model::getInstance('user', ['language' => $this->language])->getUserByCredentials($email, $password);

		// Is this a registered user allowed to log in?
		if (false === $this->grantAccess($user, $email, $password))
		{
			return false;
		}

		return $user;
	}

	// FIXME - bind to login process and move to UserHelper. This entity must not alter database table!

	/**
	 * Store login date/time of previously logged-in user into the database.
	 *
	 * @throws Exception
	 * @deprecated  Use {@link Userhelper::setLastVisitDate()} instead. Will be removed after 2019-09-20
	 */
	protected function setLoginDate()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = Factory::getDbo();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		// MYSQLI_QUERY($db, "SET NAMES utf8"); MYSQLI_QUERY($db, "SET CHARACTER SET utf8;");
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Get reference to current user.
		$user = &$this;

		if (is_null($user))
		{
			Messager::setMessage([
				'type' => 'error',
				// TODO - translate
				'text' => Text::translate('User could not be identified.', $this->language)
			]);

			return false;
		}

		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$now = $now->format('Y-m-d H:i:s');

		// Build query.
		$query = $db->getQuery(true)
			->update($db->qn('users'))
			->set($db->qn('lastVisitDate') . ' = ' . $db->q($now))
			->where($db->qn('userID') . ' = ' . (int) $user->userID);

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
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$affectedRows = null;
		}

		// Close connection.
		DatabaseHelper::closeConnection($db);

		return $affectedRows;
	}
}
