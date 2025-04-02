<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Joomla\Filter\InputFilter;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Nematrack\App;
use Nematrack\Crypto;
use Nematrack\Entity;
use Nematrack\Factory;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Model\Item as ItemModel;
use Nematrack\Text;
use function array_keys;
use function array_map;
use function is_a;
use function is_array;
use function is_null;
use function is_object;

// use Nematrack\Access\User;		// Removing this namespaced access may conflict with Entity and/or Model because of equal class name 'User'
// Removing this namespaced access may conflict with Access\User and/or Entity because of equal class name 'User'

/**
 * Class description
 */
class User extends ItemModel
{
	/**
	 * The user's blocked status representation.
	 *
	 * @var    int
	 * @since  2.6
	 */
	public const STATUS_BLOCKED = 1;

	/**
	 * The user's blocked status representation.
	 *
	 * @var    int
	 * @since  2.6
	 */
	public const STATUS_UNBLOCKED = 0;

	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/***   BEGIN: API-Service(s)   ***/

	public function authorize(array $data, ...$args)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get additional function args.
		$args = (array) array_shift($args);
		$args = new Registry($args);

		// Get current user object.
		$db     = $this->db;
//		$table  = $this->getTable();
//		$pkName = $table->getPrimaryKeyName();

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery(/** @lang MySQL */'SET NAMES utf8')->execute();
		$db->setQuery(/** @lang MySQL */'SET CHARACTER SET utf8')->execute();

		// Get current user object.
//		$user   = App::getAppUser();
//		$userID = $user->get($pkName);

		$formData = &$data;

		$email    = ArrayHelper::getValue($formData, 'email',    null, 'STRING');
		$password = ArrayHelper::getValue($formData, 'password', null, 'STRING');

		/*// Validate session userID equals current form editor's userID
		if ((int) $userID !== (int) ArrayHelper::getValue($formData, 'user', null, 'INT'))
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}*/

		// Dupe-check user.
		$exists = $this->existsUser(null, ['email' => $email/*, 'password' => $password*/]);

		if (!$exists)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('User not found', $this->language)
			]);

			return $exists;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	/***   END: API-Service(s)   ***/

	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $itemID
	 *
	 * @return  Entity\User
	 */
	public function getItem(int $itemID) : Entity\User
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $itemID . '")', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('users', ['language' => $this->language])->getList($itemID),
				$itemID
			);
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf('Nematrack\Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf('Nematrack\Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('userID')) {}

		return $row;
	}

    public function getActiveUserRole(string $activeUser) //: ?string
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        if (empty($activeUser))
        {
            return null;
        }

        $db = $this->db;

        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        // Build query.
        $query = $db->getQuery(true)
            ->from($db->qn('user_usergroup', 'ug'))
            ->leftJoin( $db->qn('usergroups') . ' AS ' . $db->qn('g') . ' ON ' . $db->qn('ug.groupID') . ' = ' . $db->qn('g.groupID'))
            ->select($db->qn('g.name'))
            ->where($db->qn('ug.userID')  . ' = ' . $activeUser);

        // Execute query.
        try
        {
            $artNum = $db->setQuery($query)->loadAssocList();
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);

            $artNum = null;
        }

        // Close connection.
        $this->closeDatabaseConnection();

        return $artNum;
    }
	public function getUserByCredentials($email, $password = null) : ?Entity\User
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $email . '", "*** HIDDEN ***")', true) . '</pre>' : null;

		// Check if required credentials have been provided
		if (empty($email))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_HINT_INVALID_EMAIL_ADDRESS_TEXT', $this->language)
			]);

			return Entity::getInstance('user'); // prev. it was null
		}

		// Sanitize query data.
		$email    = trim($email);
		$password = trim($password);
		$validPW  = null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		// MYSQLI_QUERY($db, "SET SESSION group_concat_max_len = 100000;");
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		// Build query.
		try
		{
			// 1. Load user identified by email.

			// Build query.
			$query = $db->getQuery(true)
			->select([
				$db->qn('u.userID'),
				$db->qn('ou.orgID'),
				$db->qn('u.fullname'),
				$db->qn('u.email'),
				$db->qn('u.password'),
				$db->qn('u.blocked'),
				"CONCAT('[', GROUP_CONCAT(DISTINCT " . $db->qn('ug.groupID') . "), ']') AS " . $db->qn('groups'),
				$db->qn('u.languages'),
				$db->qn('u.blockDate'),
				$db->qn('u.registerDate'),
				$db->qn('u.lastVisitDate'),
				$db->qn('u.lastLogoutDate'),
				$db->qn('u.lastResetTime'),
				$db->qn('u.resetCount'),
				$db->qn('u.requireReset'),
				$db->qn('u.created'),
				$db->qn('u.created_by'),
				$db->qn('u.modified'),
				$db->qn('u.modified_by')
			])
			->from('users AS u')
			->join('LEFT', $db->qn('organisation_user') . ' AS ' . $db->qn('ou') . ' ON ' . $db->qn('u.userID') . ' = ' . $db->qn('ou.userID'))
			->join('LEFT', $db->qn('user_usergroup')    . ' AS ' . $db->qn('ug') . ' ON ' . $db->qn('u.userID') . ' = ' . $db->qn('ug.userID'))
			->where($db->qn('u.email') . ' = ' . $db->q($email));

			// Execute query.
			$row = $db->setQuery($query)->loadAssoc();

			// Verify passed clear text password matches stored password hash.
			if (!is_null($password))
			{
				$validPW = Crypto::verifyPassword($password, ArrayHelper::getValue($row, 'password', '', 'STRING'));
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Validate user password.
//		if (empty($validPW))							// DiSABLED on 2023-07-06 - replaced by next line
		if (!is_null($password) && empty($validPW))
		{
			$row = Entity::getInstance('user');
		}
		else
		{
			// Fetch proper user object.
			$row = Entity::getInstance('user', ['id' => $row['userID'], 'language' => $this->language])->bind($row);
		}

		return $row;
	}

	public function addUser(array $userData, int $creatorID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$creator = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = $userData;

		// Dupe-check user.
		$exists = $this->existsUser(null, ['email' => ArrayHelper::getValue($formData, 'email', null, 'STRING')]);

		if (true === $exists)
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_USER_TEXT', $this->language), null)
			]);

			return false;
		}

		// TODO - implement called function
		/* if ($this->existsOrganisationUser(ArrayHelper::getValue($formData, 'company', null, 'STRING'), ArrayHelper::getValue($formData, 'fullname', null, 'STRING'), ArrayHelper::getValue($formData, 'email', null, 'STRING')))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => sprintf(Text::translate('COM_FTK_ERROR_APPLICATION_FORCE_UNIQUE_ORGANISATION_USER_TEXT', $this->language),
					ArrayHelper::getValue($formData, 'fullname', null, 'STRING')
				)
			]);

			return false;
		}*/

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		// Validate session userID equals current form editor's userID.
		if (is_a($creator, 'Nematrack\Entity\User') && (int) $creator->get('userID') !== (int) $formData->user)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}

		$now = new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE));
		$now = $now->format('Y-m-d H:i:s');

		$formData->passwordHash = Crypto::hashPassword($formData->password);

		$usrLanguages = json_encode(array_map('intval', $formData->languages), JSON_THROW_ON_ERROR);

		// Build query.
		$rowData = new Registry($formData);

		$query = $db->getQuery(true)
		->insert('users')
		->columns(
			$db->qn([
				'fullname',
				'email',
				'password',
				'blocked',
				'registerDate',
				'languages',
				'created',
				'created_by'
			])
		)
		->values(implode(',', [
			$db->q(filter_var($rowData->get('fullname'),     FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
			$db->q(filter_var($rowData->get('email'),        FILTER_SANITIZE_EMAIL)),
			$db->q(filter_var($rowData->get('passwordHash'), FILTER_UNSAFE_RAW)),
			$db->q(filter_var($rowData->get('status'),       FILTER_VALIDATE_INT)),
			$db->q($now),
			$db->q($usrLanguages),
			$db->q($now),
			$creatorID
		]));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();	// WARNING: insert_id will be empty if the targeted table has no primary key
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$insertID = null;
		}

		// Inject insert_id as foreign key value for the metadata store process.
		$formData->userID = $insertID;

		// Update rowData object.
		$rowData->loadObject($formData);

		// Store user organisation information.
		$orgStored = $this->storeUserOrganisation($rowData->get('userID'), $rowData->get('oid'));

		// Store user groups information.
		$groupsStored = $this->storeUserGroups($rowData->get('userID'), $rowData->get('groups', []));

		// Create user profile.
		$profileCreated = $this->createProfile($rowData->get('userID'));

		// Close connection.
		$this->closeDatabaseConnection();

		return (($insertID > 0 && $orgStored && $groupsStored && $profileCreated) ? $formData->userID : false);
	}

	public function updateUser(array $userData, int $editorID): ?int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$editor = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = $userData;

		// TODO - implement called function
		// Existence check.
		/*if (!$this->existsUser( ArrayHelper::getValue($formData, 'xid', null, 'STRING') ) )
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such user: %s', $this->language), $formData['xid'])
			]);

			return false;
		}*/

		$xUser   = $this->getItem( ArrayHelper::getValue($formData, 'xid', 0, 'INT') );

		if (!is_a($xUser, 'Nematrack\Entity\User'))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such user: %s', $this->language), $formData['xid'])
			]);

			return false;
		}

		$xUserID = $xUser->get('userID');

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		// Validate session userID equals current form editor's userID
		if (is_a($editor, 'Nematrack\Entity\User') && (int) $editor->get('userID') !== (int) $formData->user)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}

		$usrLanguages = array_map('intval', (array) $formData->languages ?? []);
		$usrGroups    = array_map('intval', (array) $formData->groups    ?? []);

		// If password has changed, generate hash.
		if (property_exists($formData, 'password') && !empty($formData->password))
		{
			// Generate password hash. We don't store passwords in clear text.
			$formData->passwordHash = Crypto::hashPassword($formData->password);
		}

		$rowData = new Registry($formData);
        $filter  = new InputFilter;

		$isDataChanged = $isGroupsChanged = false;

		// Build query.
		$query = $db->getQuery(true);
		$affectedRows = 0;

		try
		{
			// If user group(s) is changed, update it.
			if ($usrGroups != array_keys($xUser->get('groups')))
			{
				$isGroupsChanged = true;
			}

			// If user language(s) is changed, update it.
			if ($usrLanguages != array_keys($xUser->get('languages')))
			{
				$isDataChanged = true;

				$query
				->set($db->qn('languages') . ' = ' . $db->q(json_encode($usrLanguages, JSON_THROW_ON_ERROR)));	// ADDED on 2023-08-18 - replacement for previous line
			}

			// If email has changed, update it.
			if ($rowData->get('fullname') != $xUser->get('fullname'))
			{
				$isDataChanged = true;

				$query
				->set($db->qn('fullname') . ' = ' . $db->q(filter_var($rowData->get('fullname'), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)));	// FIXME convert to InputFilter::clean() because FILTER_SANITIZE_STRING is deprecated
			}

			// If email has changed, update it.
			if ($rowData->get('email') != $xUser->get('email'))
			{
				$isDataChanged = true;

				$query
				->set($db->qn('email') . ' = ' . $db->q(filter_var($rowData->get('email'), FILTER_SANITIZE_EMAIL)));
			}

			// If password has changed, update it.
			if (isset($formData->passwordHash))
			{
				$isDataChanged = true;

				$query
				->set($db->qn('password') . ' = ' . $db->q($formData->passwordHash));
			}

			// If user status has changed, update it.
			$status = filter_var($rowData->get('status'), FILTER_VALIDATE_INT);

			if ($status != $xUser->get('blocked'))
			{
				$isDataChanged = true;

				switch ($status)
				{
					case static::STATUS_BLOCKED :
						$query
						->set([
							$db->qn('blocked')    . ' = ' . $db->q($status),
							$db->qn('blockDate')  . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
							$db->qn('blocked_by') . ' = ' . $editorID
						]);
					break;

					case static::STATUS_UNBLOCKED :
						$query
						->set([
							$db->qn('blocked')    . ' = ' . $db->q($status),
							$db->qn('blockDate')  . ' = ' . $db->q(FTKRULE_NULLDATE),
							$db->qn('blocked_by') . ' = NULL'
						]);
					break;
				}
			}

			// No changes detected. Return to sender.
			if (!$isDataChanged && !$isGroupsChanged)
			{
				return null;
			}

			// Changes detected. Save 'em.
			if ($isDataChanged)
			{
				$query
				->update($db->qn('users'))
				->where($db->qn('userID')  .' = ' . (int) $xUserID)
				->set([
					$db->qn('modified')    . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
					$db->qn('modified_by') . ' = ' . $editorID,
				]);

				// Execute query.
				$db
				->setQuery($query)
				->execute();

				$affectedRows = $db->getAffectedRows();
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Update primary key value in formData and rowData.
		$formData->userID = $xUser->get('userID', 0);
		$rowData->set('userID', $formData->userID);

		if ($isGroupsChanged)
		{
			// Next block moved here from {@link storeArticleProcesses()} on 2019-10-24
			if (false === $this->deleteUserGroups($formData->userID))
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate('COM_FTK_ERROR_DATABASE_UPDATING_USER_GROUPS_TEXT', $this->language)
				]);

				return false;
			}

			// Store user groups information.
			$groupsStored = $this->storeUserGroups((int) $rowData->get('userID'), $rowData->get('groups'));
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return (($affectedRows > 0 || $groupsStored) ? $rowData->get('userID') : false);
	}

	// Method to initialize a new user's profile with default configuration.
	public function createProfile(int $userID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Prepare default profile data processes.
		$tuples = [
			$userID . ',' . $db->q('user.language') . ',' . $db->q(Factory::getConfig()->get('language')),    // config->language = 'en-GB' whereas the previous parameter config->app_language = 'en'
			$userID . ',' . $db->q('user.locale')   . ',' . $db->q(json_encode([
					'date' => 'd.m.Y',
					'time' => 'H:i',
				'timezone' => FTKRULE_TIMEZONE
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
			$userID . ',' . $db->q('parts.booked.retrospective')   . ',' . $db->q(json_encode([
				'limit' => 6
				], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
			$userID . ',' . $db->q('parts.unbooked.retrospective') . ',' . $db->q(json_encode([
				'limit' => 5
			], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
		];

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('user_profile'))
		->columns(
			$db->qn([
				'userID',
				'profile_key',
				'profile_value'
			])
		)
		->values($tuples);

		try
		{
			if (count($tuples))
			{
				$db
				->setQuery($query)
				->execute();
			}
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

    public function updateUserPreference(int $userID, array $userData)
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        // Init shorthand to database object.
        $db = $this->db;

        /* Force UTF-8 encoding for proper display of german Umlaute
         * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
         */
        $db->setQuery('SET NAMES utf8')->execute();
        $db->setQuery('SET CHARACTER SET utf8')->execute();

        $formData = &$userData;

        $user   = $this->getItem( ArrayHelper::getValue($formData, 'uid', 0, 'INT') );
//		$userID = null;

        if (!is_a($user, 'Nematrack\Entity\User'))
        {
            Messager::setMessage([
                'type' => 'notice',
                // TODO - translate
                'text' => sprintf(Text::translate('No such user: %s', $this->language), $formData['uid'])
            ]);

            return false;
        }

        $userID = $user->get('userID');

        // Convert array to object.
        if (!is_object($formData))
        {
            $formData = (object) $formData;
        }

        // Validate session userID equals current form editor's userID
        if ((int) $user->get('userID') !== (int) $formData->uid)
        {
            Messager::setMessage([
                'type' => 'warning',
                'text' => sprintf('%s: %s %s %s',
                    Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
                    Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
                    Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
                    Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
                )
            ]);

            return false;
        }

        /* 1st Step: Store user base data */

        // Build query.
        $rowData = new Registry($formData);

        // Convert user languages to JSON string.
        $usrLanguages = $rowData->get('languages', array_keys($user->get('languages')), 'ARRAY');
        $usrLanguages = array_map('intval', $usrLanguages);
        $usrLanguages = json_encode((count($usrLanguages)) ? $usrLanguages : [], JSON_THROW_ON_ERROR);

        // Build query.
        $query = $db->getQuery(true);
        $affectedRows  = 0;
        //echo $rowData->get('trackjump'); echo $userID;
        //echo "<pre>";print_r($rowData);echo $rowData->get('trackjump');exit;

        try
        {
            $query
                ->update($db->qn('users'))
                ->set(
                    $db->qn('trackjump')    . ' = ' . $db->q(($rowData->get('trackjump')))
                );

            $query
                ->where('userID = ' . (int) $userID);
//echo $query;
            // Execute query.
            $db
                ->setQuery($query)
                ->execute();

            $affectedRows = $db->getAffectedRows();
            //echo "<pre>"; print_r($affectedRows);exit;
        }
        catch (Exception $e)
        {
            Messager::setMessage([
                'type' => 'error',
                'text' => Text::translate($e->getMessage(), $this->language)
            ]);
        }
        // Close connection.
        $this->closeDatabaseConnection();

        return ($affectedRows > 0 ? $formData->userID : false);
    }
	public function updateProfile(int $userID, array $userData)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$formData = &$userData;

		$user   = $this->getItem( ArrayHelper::getValue($formData, 'uid', 0, 'INT') );
//		$userID = null;

		if (!is_a($user, 'Nematrack\Entity\User'))
		{
			Messager::setMessage([
				'type' => 'notice',
				// TODO - translate
				'text' => sprintf(Text::translate('No such user: %s', $this->language), $formData['uid'])
			]);

			return false;
		}

		$userID = $user->get('userID');

		// Convert array to object.
		if (!is_object($formData))
		{
			$formData = (object) $formData;
		}

		// Validate session userID equals current form editor's userID
		if ((int) $user->get('userID') !== (int) $formData->uid)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}

		/* 1st Step: Store user base data */

		// Build query.
		$rowData = new Registry($formData);

		// Convert user languages to JSON string.
		$usrLanguages = $rowData->get('languages', array_keys($user->get('languages')), 'ARRAY');
		$usrLanguages = array_map('intval', $usrLanguages);
		$usrLanguages = json_encode((count($usrLanguages)) ? $usrLanguages : [], JSON_THROW_ON_ERROR);

		// Build query.
		$query = $db->getQuery(true);
		$affectedRows  = 0;

		try
		{
			$query
			->update($db->qn('users'))
			->set([
				$db->qn('fullname')    . ' = ' . $db->q(filter_var($rowData->get('fullname', $user->get('fullname')), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES)),
				$db->qn('languages')   . ' = ' . $db->q($usrLanguages),
				$db->qn('modified')    . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
				$db->qn('modified_by') . ' = ' . (int) $userID,
			]);

			// TODO - decide whether this check should happen in View prior calling model to store the data.
			if (is_object($user) && $user->isCustomer())
			{
				$query
				->set($db->qn('email') . ' = ' . $db->q(filter_var($rowData->get('email'), FILTER_SANITIZE_EMAIL)));
			}

			// if (property_exists($formData, 'password') && !empty($formData->password))
			if ($rowData->def('password') && !empty($rowData->get('password')))
			{
				// If password has changed, generate hash. We don't store passwords in clear text.
				// $formData->passwordHash = Crypto::hashPassword($formData->password);
				$rowData->set('passwordHash', Crypto::hashPassword($rowData->get('password')));

				$query
				->set($db->qn('password') . ' = ' . $db->q(filter_var($rowData->get('passwordHash'), FILTER_UNSAFE_RAW)));
			}

			$query
			->where('userID = ' . (int) $userID);

			// Execute query.
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
		}

		// Inject insert_id as foreign key value for the metadata store process.
		$formData->userID = $user->get('userID');

		/* 2nd Step: Store user base data */

		if ($rowData->def('profile'))
		{
			// Get user profile data from database first. There might be something to merge.
			$oldProfile    = new Registry(UserHelper::getProfile($user));

			// Now get profile data from passed in form data.
			$newProfile    = $rowData->extract('profile');

			$mergedProfile = $oldProfile->merge($newProfile, true);
			$mergedProfile = $mergedProfile->toArray();

			ksort($mergedProfile);

			$mergedProfile = new Registry($mergedProfile);

			// Free memory.
			unset($oldProfile);
			unset($newProfile);

			/*** IMPORTANT:   Do not call Joomla\Utilities\ArrayHelper::flatten() as it will throw an error and fail !!! ***/

			// Get profile data from input data object.
			// $profile = $rowData->extract('profile');

			// $profile = \Nematrack\Utility\ArrayHelper::flatten('.', $profile->toArray());
			$mergedProfile = \Nematrack\Helper\ArrayHelper::flatten('.', $mergedProfile->toArray());

			// Prepare rows to be inserted.
			$tuples = [];

			foreach ($mergedProfile as $path => $data)
			{
				switch (gettype($data))
				{
					case 'bool' :
					case 'boolean' :
						$data = (bool) $data;
					break;

					case 'double' :
					case 'float' :
						$data = floatval($data);
					break;

					case 'integer' :
						$data = intval($data);
					break;

					case 'string' :
						$data = trim('' . $data);
					break;

					case 'array' :
					case 'object' :
						$data = json_encode($data, JSON_THROW_ON_ERROR);
					break;
				}

				$tuples[] = (int) $userID . ',' . $db->q(trim(''. $path)) . ',' . $db->q($data);
			}

			/* Step 2.1:  Delete old data first (Necessary because JDatabaseQuery does not support MySQL command 'REPLACE') */

			// Build query.
			$query
			->clear()
			->delete($db->qn('user_profile'))
			->where($db->qn('userID') . ' = ' . (int) $userID);

			// Execute query.
			try
			{
				$db
				->setQuery($query)
				->execute();
			}
			catch (Exception $e)
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);
			}

			/* Step 2.2:  Insert new data */

			// Build query.
			$query
			->clear()
			->insert($db->qn('user_profile'))
			->columns(
				$db->qn([
					'userID',
					'profile_key',
					'profile_value'
				])
			)
			->values($tuples);

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
			}
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return ($affectedRows > 0 ? $formData->userID : false);
	}

	public function deleteUser(int $userID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Can this user delete content?
		if (!$this->userCanDelete())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load user from db first.
		$user = $this->getItem($userID);

		if (!is_a($user, 'Nematrack\Entity\User') || !$user->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_USER_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Validate this user is allowed to delete users first.
		if (!$this->canDeleteUser($userID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this user.
		if (!$this->userIsDeletable($userID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('users'))
		->where($db->qn('userID') . ' = ' . $userID);

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Reset AUTO_INCREMENT count.
		try
		{
			$db
			->setQuery('ALTER TABLE `users` AUTO_INCREMENT = 1')    // FIXME - get table name from Entity
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $userID;
	}

	public function publishUser(int $userID, int $state = 0)	// state = 0 means block, state = 1 means unblock
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Load user from db first.
		$user = $this->getItem($userID);

		/* // @debug
//		if ($user->isProgrammer()) :
			echo '<pre style="color:blue">' . print_r(__METHOD__, true) . '</pre>';
			echo '<pre>userID: ' . print_r($userID, true) . '</pre>';
//			echo '<pre>user: ' . print_r($user, true) . '</pre>';
			echo '<pre>state: ' . print_r($state, true) . '</pre>';
//			die;
//		endif; */

		if ($state != static::STATUS_BLOCKED && $state != static::STATUS_UNBLOCKED)
		{
			Messager::setMessage([
				'type' => 'error',
				// TODO - translate
				'text' => sprintf('Invalid user state *%s*.', $state)
			]);

			return -1;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		if (!is_a($user, 'Nematrack\Entity\User') || !$user->get('orgID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_USER_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// FIXME
		/*// Validate this user is allowed to edit another users first.
		// WRONG: $usreID refers to the account to be edited
		if (!$this->canEditUser($userID))
		{
			return false;	// Messages will be set by the function called.
		}*/

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->update($db->qn('users'))
			->where($db->qn('userID') . ' = ' . $userID)
			->set($db->qn('blocked')  . ' = ' . $db->q($state));

			if ($state == static::STATUS_UNBLOCKED)
			{
				$query
				->set($db->qn('blockDate')  . ' = ' . $db->q(FTKRULE_NULLDATE))
				->set($db->qn('blocked_by') . ' = ' . 'NULL');
			}
			else
			{
				$query
				->set($db->qn('blockDate')  . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')))
				->set($db->qn('blocked_by') . ' = ' . $db->q(App::getAppUser()->get('userID')));
			}

			/* // @debug
//			if ($user->isProgrammer()) :
				echo '<pre>$query: ' . print_r($query->dump(), true) . '</pre>';
				die;
//			endif; */

			// Execute query.
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		// Load user again and return its current 'blocked' status.
		$user = $this->getItem($userID);

		return $user->get('blocked');
	}

	protected function existsUser($userID = null, ... $args) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get additional function args.
//		$xtraArgs = (func_num_args() > 1 ? func_get_arg(1) : null);

		// Get additional function args.
		$args = (array) array_shift($args);
		$args = new Registry($args);

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Function parameter check.
		if (is_null($userID) && !count($args->toArray()))
		{
			// TODO - translate
			throw new InvalidArgumentException('Function requires at least 1 argument.');
		}

		// Init return value.
		$rs = 0;

		// Build query.
		try
		{
			$query = $db->getQuery(true)
			->select($db->qn('userID'))
			->from($db->qn('users'));

			if (!is_null($userID) && (int) $userID > 0)
			{
				$query->where($db->qn('userID') . ' = ' . (int) $userID);
			}

			foreach ($args->toArray() as $key => $value)
			{
				$query->where($db->qn($key) . ' = ' . $db->q($value));
			}

			// Execute query.
			$rs = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rs > 0;
	}

	//@todo - implement existance check
	protected function existsOrganisationUser(string $company = null, string $fullname = null, string $email = null)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return;



		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select($db->qn('orgID'))
		->from($db->qn('organisations'))
		->where('LOWER(' . $db->qn('name') . ') = LOWER(' . $db->q(trim($orgName)) . ')');

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rs > 0;
	}

	//@todo - implement
	protected function canDeleteUser(int $userID)
	{
		return true;
	}

	//@todo - implement
	protected function canEditUser(int $userID)
	{
		return true;
	}

	//@todo - implement
	protected function userIsDeletable(int $userID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - check user is not the Super Admin.
		// TODO - check no 'primitive' user can delete another user of higher class
		return true;
	}

	//@todo - implement
	protected function userHasDependencies(int $userID)
	{
		return false;
	}

	//@todo - implement
	protected function hasUserOrganisation(int $userID)
	{
		// TODO
	}

	protected function deleteUserGroups(int $userID, array $preserveIDs = []) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Sanitize input.
		$preserveIDs = array_map('intval', $preserveIDs);

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('user_usergroup'))
		->where($db->qn('userID') . ' = ' . $userID);

		// Consider groups to preserve.
		if (count($preserveIDs))
		{
			$query->where($db->qn('groupID') . ' NOT IN(' . implode(',', $preserveIDs) . ')');
		}

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $userID;
	}

	protected function storeUserGroups(int $userID, array $groups = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Store user groups.
		$tuples = [];

		// Prepare userID<-->groupID tuples.
		foreach ($groups as $gid)
		{
			$tuples[] = $userID . ',' . (int) $gid;
		}

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('user_usergroup'))
		->columns(
			$db->qn([
				'userID',
				'groupID'
			])
		)
		->values($tuples);

		// Execute query.
		try
		{
			if (count($tuples))
			{
				$db
				->setQuery($query)
				->execute();
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	protected function storeUserOrganisation(int $userID, int $orgID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->insert($db->qn('organisation_user'))
		->columns(
			$db->qn([
				'userID',
				'orgID'
			])
		)
		->values(implode(',', [
			$userID,
			$orgID
		]));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}

	//@todo - implement
	protected function deleteUserOrganisation(int $userID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		return $userID;
	}
}
