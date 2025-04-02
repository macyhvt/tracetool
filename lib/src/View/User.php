<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Utilities\ArrayHelper;
use JsonException;
use  \Crypto;
use  \Messager;
use  \Model\User as Model;
use  \Text;
use  \View;
use  \View\Item as ItemView;
use function in_array;
use function is_int;
use function is_null;

// Removing this namespaced access may conflict with View because of equal class name 'User'
// Required to call <code>{@link \ \View::getReferer()}</code>
// Removing this namespaced access may conflict with Model because of equal class name 'User'

/**
 * Class description
 */
class User extends ItemView
{
	use \ \Traits\View\User;

	/**
	 * {@inheritdoc}
	 * @see Item::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

		// Don't load display data when there's POST data to process.
		if (count($_POST))
		{
			return;
		}

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		/*if (\is_a($this->user, ' \Entity\User') && $this->user->getFlags() < \ \Access\User::ROLE_MANAGER)
		{
			// $redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}*/

		// Access control. Only registered and authenticated users can view content.
		/*if (!\is_a($this->user, ' \Entity\User'))
		{
			// $redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}*/

		// Prepare item for display.
		/*if (\is_a($this->user, ' \Entity\User') && $this->user->get('fullname'))
		{
			// Do some stuff.
		}*/

		// Assign ref to loaded item.
		$this->item = $this->user;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::getIdentificationKey
	 */
	public function getIdentificationKey() : string
	{
		$this->identificationKey = 'uid';

		return $this->identificationKey;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = '') : void	// An admin created a user account.
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$redirect = (!empty($redirect)) ? $redirect : View::getReferer();
		$post     = $this->input->post->getArray();

		$creatorID = $this->input->post->getInt('user');
		$creator   = $this->model->getItem($creatorID);

		// Access control: get user by $creatorID + validate this user is authorized to create user accounts.
		if ((is_a($creator, ' \Entity\User') && !$creator->getFlags() >= \ \Access\User::ROLE_ADMINISTRATOR) || !is_a($creator, ' \Entity\User'))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_CREATE_USER_ACCOUNTS_TEXT', $this->language)
			]);

			header('Location: ' . $redirect);
			exit;
		}

		$status = $this->model->addUser($post, $creatorID);

		// If a user has been created successfuly the return value is the insertID.
		if (is_int($status) && $status > 0)
		{
			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_USER_WAS_CREATED_TEXT', $this->language)
			]);

			// User successfuly logged in. Redirect to its previous screen or home page.
			// http_response_code('200');
		}
		else
		{
			// User failed to log in. Redirect to log in screen.
			http_response_code('401');
		}

		header('Location: ' . $redirect);
		exit;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveEdit
	 */
	public function saveEdit(string $redirect = '') : void	// Handler for a user editing another user account
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$redirect = (!empty($redirect)) ? $redirect : View::getReferer();
		$post     = $this->input->post->getArray();

		$editorID = $this->input->post->getInt('user', 0);
		$editor   = $this->model->getItem($editorID);

		// Access control: get user by $editorID + validate this user is authorized to manage/edit user accounts.
		if ((is_a($editor, ' \Entity\User') && !$editor->getFlags() >= \ \Access\User::ROLE_ADMINISTRATOR) || !is_a($editor, ' \Entity\User'))
		{
			Messager::setMessage([
				'type' => 'info',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_EDIT_USER_ACCOUNTS_TEXT', $this->language)
			]);

			header('Location: ' . $redirect);
			exit;
		}

		// Save data.
		$status = $this->model->updateUser($post, $editorID);

		// If a user has been created successfuly the return value is the insertID.
		if (is_null($status) || is_int($status))
		{
			switch (true)
			{
				// No changes.
				case (is_null($status)) :
					Messager::setMessage([
						'type' => 'info',
						'text' => sprintf('%s %s',
							Text::translate('COM_FTK_SYSTEM_MESSAGE_NOTHING_SAVED_TEXT', $this->language),
							Text::translate('COM_FTK_SYSTEM_MESSAGE_NO_CHANGES_DETECTED_TEXT', $this->language)
						)
					]);

					// User successfuly logged in. Redirect to its previous screen or home page.
					// http_response_code('200');
				break;

				// Changes applied.
				case ($status > 0) :
					$session = ArrayHelper::getValue($GLOBALS, 'session');

					if (!$session->isStarted())
					{
						$session->start();
					}

					Messager::setMessage([
						'type' => 'success',
						'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
					]);

					// If a user modified its own data, update its session data accordingly.
					if ($this->input->post->getInt('uid') == $this->user->get('userID'))
					{
						$session->set('user', $this->model->getItem((int) $this->user->get('userID', 0)));
					}

					// User successfuly logged in. Redirect to its previous screen or home page.
					// http_response_code('200');
				break;
			}
		}
		else
		{
			// User failed to log in. Redirect to log in screen.
			http_response_code('401');
		}

		header('Location: ' . $redirect);
		exit;
	}

	// A user managed its account.

    public function editPreference(string $redirect = '') : void	// Handler for a user editing own user account
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $redirect = (!empty($redirect)) ? $redirect : View::getReferer();
        $post     = $this->input->post->getArray();

        $profileID = $this->input->post->getInt('uid');
        $profile   = $this->model->getItem($profileID);


        // Access control: get user by $creatorID + validate this user is authorized to create a new user.
        /*if (
            // The current user must be an instance of Entity\User. If not, return to sender.
            !is_a($this->user, ' \Entity\User') ||
            // The user whose profile is edited must exist. If not, return to sender.
            !is_a($profile,    ' \Entity\User') ||
            // Both user IDs must match. If not, return to sender.
            ($this->user->get('userID') != $profile->get('userID'))
        )
        {
            Messager::setMessage([
                'type' => 'warning',
                'text' => sprintf('%s %s',
                    Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
                    Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language)
                )
            ]);

            header('Location: ' . $redirect);
            exit;
        }*/
        $redirect = $post['backtopre'];

        $status   = $this->model->updateUserPreference($profileID, $post);
        /*echo "<pre>";print_r($post);
        echo $redirect;exit;*/
        // If a user has been created successfuly the return value is the insertID.
        if (is_int($status) && $status > 0)
        {
            $session = ArrayHelper::getValue($GLOBALS, 'session');

            if (!$session->isStarted())
            {
                $session->start();
            }

            Messager::setMessage([
                'type' => 'success',
                'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
            ]);

            // If a user modified its own data, update its session accordingly.
            if ($this->input->post->getInt('uid') == $this->user->get('userID'))
            {
                $session->set('user', $this->model->getItem((int) $this->user->get('userID', 0)));
            }

            // User successfuly logged in. Redirect to its previous screen or home page.
            // http_response_code('200');
        }
        else
        {
            // User failed to log in. Redirect to log in screen.
            http_response_code('401');
        }
        $message = 'prefupdate';
        //header('Location: ' .$backsend. '&message=' .$message.'#processes');
        header('Location: ' . $redirect. '&message=' .$message);
        exit;
    }
	/**
	 * Add description...
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	public function saveEditProfile(string $redirect = '') : void	// Handler for a user editing own user account
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$redirect = (!empty($redirect)) ? $redirect : View::getReferer();
		$post     = $this->input->post->getArray();

		$profileID = $this->input->post->getInt('uid');
		$profile   = $this->model->getItem($profileID);

		// Access control: get user by $creatorID + validate this user is authorized to create a new user.
		if (
			// The current user must be an instance of Entity\User. If not, return to sender.
			!is_a($this->user, ' \Entity\User') ||
			// The user whose profile is edited must exist. If not, return to sender.
			!is_a($profile,    ' \Entity\User') ||
			// Both user IDs must match. If not, return to sender.
			($this->user->get('userID') != $profile->get('userID'))
		)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language)
				)
			]);

			header('Location: ' . $redirect);
			exit;
		}

		$status   = $this->model->updateProfile($profileID, $post);

		// Dump generated password with current user object.
		// $this->get('user')->__set('newPassword', $status);
		$this->user->__set('newPassword', $status);

		// If a user has been created successfuly the return value is the insertID.
		if (is_int($status) && $status > 0)
		{
			$session = ArrayHelper::getValue($GLOBALS, 'session');

			if (!$session->isStarted())
			{
				$session->start();
			}

			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
			]);

			// If a user modified its own data, update its session accordingly.
			if ($this->input->post->getInt('uid') == $this->user->get('userID'))
			{
				$session->set('user', $this->model->getItem((int) $this->user->get('userID', 0)));
			}

			// User successfuly logged in. Redirect to its previous screen or home page.
			// http_response_code('200');
		}
		else
		{
			// User failed to log in. Redirect to log in screen.
			http_response_code('401');
		}

		header('Location: ' . $redirect);
		exit;
	}

	// An admin managed a user account.
	// FIXME - this method is executed only on lock symbol pressed, whereas when a user account is edited and the state is changed this function is not involved.
	/**
	 * Add description...
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	public function saveState(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$post     = $this->input->post->getArray();
//		$editor   = $this->input->post->getInt('user');
//		$uid      = $this->input->post->getInt('uid');
		$xid      = $this->input->post->getInt('xid');

		$redirect = (!empty($redirect)) ? $redirect : View::getReferer();

		$state  = $this->input->post->getString('task');
		$state  = (mb_strtolower($state) == 'lock')
			? Model::STATUS_BLOCKED
			: (mb_strtolower($state) == 'unlock'
				? Model::STATUS_UNBLOCKED
				: null);

		$status = ($xid && in_array($state, [Model::STATUS_BLOCKED , Model::STATUS_UNBLOCKED]))
			? $this->model->publishUser($xid, $state)
			: null;

		// Flag whether the user shall be notified via email.
		$sendMail = false;

		// Calculate the message string to be rendered.
		switch ($status)
		{
			case Model::STATUS_UNBLOCKED :
				$message  = 'COM_FTK_SYSTEM_MESSAGE_USER_WAS_UNLOCKED_TEXT';
				$sendMail = true;
			break;

			case Model::STATUS_BLOCKED :
				$message  = 'COM_FTK_SYSTEM_MESSAGE_USER_WAS_LOCKED_TEXT';
				$sendMail = true;
			break;

			default :
				$message = null;
			break;
		}

		// TODO - implement MAIL
		/*if ($sendMail)
		{
//			$mailer = new Mailer();
		}*/

		if ($message)
		{
			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate($message, $this->language)
			]);
		}

		// If a user was successfuly (un)locked the return value is the block-value.
		if (is_int($status) && in_array($status, [Model::STATUS_BLOCKED , Model::STATUS_UNBLOCKED]))
		{
			// User successfuly logged in. Redirect to its previous screen or home page.
			// http_response_code('200');
		}
		else
		{
			// User failed to log in. Redirect to log in screen.
			http_response_code('401');
		}

		header('Location: ' . $redirect);
		exit;
	}

	// FIXME - add missing security pre-flight
	// FIXME - if user itself demanded new password, expire session and redirect to force log in
	/**
	 * Add description...
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	public function genPassword(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$redirect = (!empty($redirect)) ? $redirect : View::getReferer();
		$post     = $this->input->post->getArray();

		$status   = Crypto::generatePassword();
		$status   = ((is_array($status)) ? current($status) : (is_string($status))) ? $status : '';

		// Dump generated password with current user object.
		$this->user->__set('newPassword', $status);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (is_null($status) || !$status)
		{
			$this->user->__set('formData', $post);
		}

		header('Location: ' . $redirect);
		exit;
	}

	// FIXME - add missing security pre-flight
	// FIXME - if user itself demanded new password, expire session and redirect to force log in
	/**
	 * Add description...
	 *
	 * @return string
	 *
	 * @throws JsonException
	 */
	public function genPasswordJSON() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$post     = $this->input->post->getArray();
//		$request  = $this->input->getArray();

//		$redirect = (!empty($redirect)) ? $redirect : View::getReferer();

		$status = Crypto::generatePassword();
		$status = ((is_array($status)) ? current($status) : (is_string($status))) ? $status : '';

		header("Content-type: application/json; charset=utf-8");

		echo json_encode($status, JSON_THROW_ON_ERROR);
		exit;
	}
}
