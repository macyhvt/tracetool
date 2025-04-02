<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\DatabaseHelper;
use  \Messager;
use  \Text;
use  \View;
use  \View\Item as ItemView;
use function array_combine;
use function array_diff;
use function array_fill;
use function array_filter;
use function array_keys;
use function in_array;
use function is_a;
use function is_null;
use function is_object;
use function property_exists;

/**
 * Class description
 */
class Maingroup extends ItemView
{
	use \ \Traits\View\Maingroup;

	/**
	 * {@inheritdoc}
	 * @see Item::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

	}

	/**
	 * {@inheritdoc}
	 * @see Item::getIdentificationKey
	 */
	public function getIdentificationKey(): string
	{
		$this->identificationKey = 'mgid';

		return $this->identificationKey;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$action   = $this->input->post->getWord('button');
		$post     = $this->input->post->getArray();
		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			switch ($action)
			{
				// Return to List-view
				case 'submitAndClose' :
				case 'submit' :
				case 'cancel' :
					$redirect = new Uri($this->getInstance('maingroups', ['language' => $this->get('language')])->getRoute());
				break;

				// Return to add-Item-view
				case 'submitAndNew' :
					$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=add');
				break;

				default :
					$redirect = new Uri($return);
				break;
			}
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

        //echo $redirect->toString();exit;
        //echo "before save";exit;
		$status   = $this->model->addMainGroup(array_filter([
			'form' => $post
		]));
        //print_r($status);exit;
        if($status){
            if (in_array($action, ['submitAndClose']))
            {
                //echo "hello";exit;
                Messager::setMessage([
                    'type' => 'success',
                    'text' => 'Main Group Added'
                ]);


                header('Location: ' . $redirect->toString());
                exit;


                $script = <<<JS
				// A window.unload-event will be triggered and a handler is implemented to reload the list view.
				if (window.opener !== null) {
					window.opener.location.reload();
					window.opener.focus();
					setTimeout(function() { window.close() }, 200);
				} else {
					window.location.assign("{$redirect->toString()}");
				}
JS;

                echo "<script>$script</script>";
                exit;
            }
           // echo "ss";exit;
        }
        /*$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=edit&proid=' . $status);
        // $redirectToItem->setVar('return', $this->input->post->getBase64('return'));
        $redirectToItem->setVar('return', base64_encode( basename( ( new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=item&proid=' . $status) )->toString() ) ));
        // If a URI fragment was sent via POST, set it as URI var.
        $redirectToItem->setFragment($this->input->getString('fragment'));

        echo $this->get('name');
        echo $status;
        echo $redirectToItem->toString();
        exit;*/
        //$this->get('name')}&layout=team.members&mgid={$status}');
		//$redirectToItem->toString()
        //print_r($redirect);exit;
		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.
			$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=add');

			// Add the page to return to, that was sent via POST.
			$redirect->setVar('return', base64_encode($return));

			// If a URI fragment was sent via POST, set it as URI var.
			$redirect->setFragment($this->input->getString('fragment'));

			// Error messages are set in model

			header('Location: ' . $redirect->toString());
		}
		else
		{
            echo "else now";exit;
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			$message = Text::translate(mb_strtoupper(sprintf('COM_FTK_SYSTEM_MESSAGE_%s_WAS_CREATED_TEXT', $this->get('name'))), $this->language);

			if (in_array($action, ['cancel']))
			{
				header('Location: ' . $redirect->toString());
				exit;
			}

			if (in_array($action, ['submitAndNew']))
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);

				header('Location: ' . $redirect->toString());
				exit;
			}



			if (in_array($action, ['submit']))
			{
                echo "Just save";exit;
				// Extend system message.
				$message .= '\r\n' . Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_POST_CREATION_ADD_TEAM_TEXT', $this->language);
			}

			$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=edit&proid=' . $status);
			// $redirectToItem->setVar('return', $this->input->post->getBase64('return'));
			$redirectToItem->setVar('return', base64_encode( basename( ( new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=item&proid=' . $status) )->toString() ) ));
			// If a URI fragment was sent via POST, set it as URI var.
			$redirectToItem->setFragment($this->input->getString('fragment'));

			$script = <<<JS
			if (confirm("$message") == true) {
				window.location.assign('index.php?hl={$this->language}&view={$this->get('name')}&layout=team.members&mgid={$status}');
			} else {
				window.location.assign("{$redirectToItem->toString()}");
			}
JS;
			echo "<script>$script</script>";
		}
        echo "Before exit";exit;
		exit;
	}

	/**
	 * Saves the addition of a new project team member.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveAddProjectMembers(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post        = $this->input->post->getArray();
		$proid       = $this->input->post->getInt('proid', 0);
		$oids        = ArrayHelper::getValue($post, 'oids', [], 'ARRAY');
//		$redirect    = (!is_null($redirect) ? $redirect : View::getReferer());

		$status      = $this->model->addProjectMembers($proid, $oids);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_NOT_SAVED_TEXT', $this->language)
			]);
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
			]);
		}

		header('Location: index.php?hl=' . $this->language . '&view=project&layout=team.members&proid=' . $proid);
		exit;
	}

	/**
	 * Saves the deletion of a project team member.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveDeleteMember(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$post   = $this->input->post->getArray();
		$redirect    = (!is_null($redirect) ? $redirect : View::getReferer());

		$status      = $this->model->deleteProjectMember(
			$this->input->post->getInt('proid', 0),
			$this->input->post->getInt('oid',   0)
		);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			// Message set by model
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_COULD_NOT_BE_DELETED_TEXT', $this->language)
			]);
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			if ($status != '-1')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PROJECT_WAS_DELETED_TEXT', $this->language)
				]);
			}
		}

		header('Location: ' . $redirect);
		exit;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveEdit
	 */
	public function saveEdit(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$action   = $this->input->post->getWord('button');
		$post     = $this->input->post->getArray();
//		$task     = $this->input->getWord('task');

		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			switch ($action)
			{
				// Return to List-view
				case 'submit' :
					$redirect = new Uri(parent::getReferer());
				break;

				// Return to add-Item-view
				case 'submitAndClose' :
				case 'cancel' :
				default :
					$redirect = new Uri($return);
				break;
			}
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Save data.
		$status = $this->model->updateProject(array_filter([
			'form' => $post
		]));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// Error messages are set in model
		}
		else
		{
			// Delete POST data from user session as it is not required anymore.
			// The item will be populated from current data after page reload.
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			$message = Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language);

			/*// Code block moved to {@see self::closeEdit()}
			if (in_array($action, ['cancel']))
			{
				if ($redirect->getVar('layout') == 'list')
				{
					$script = <<<JS
					// A window.unload-event will be triggered and a handler is implemented to reload the list view.
					if (window.opener !== null) {
						window.opener.location.reload();
						window.opener.focus();
						setTimeout(function() { window.close() }, 200);
					} else {
						window.location.assign("{$redirect->toString()}");
					}
JS;
					echo "<script>$script</script>";
				}
				else
				{
					header('Location: ' . $redirect->toString());
				}

				exit;
			}*/

			if (in_array($action, ['submitAndClose']))
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);

				/*// Code block moved to {@see self::saveAndCloseEdit()}
				if ($redirect->getVar('layout') == 'list')
				{
					$script = <<<JS
					// A window.unload-event will be triggered and a handler is implemented to reload the list view.
					if (window.opener !== null) {
						window.opener.location.reload();
						window.opener.focus();
						setTimeout(function() { window.close() }, 200);
					} else {
						window.location.assign("{$redirect->toString()}");
					}
JS;
					echo "<script>$script</script>";
				}
				else
				{
					header('Location: ' . $redirect->toString());
				}

				exit;*/

				return;
			}

			if (in_array($action, ['submit']))
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);
			}
		}

		// Add the page to return to, that was sent via POST.
		// $redirect->setVar('return', base64_encode($return));	// Don't set it, when it was loaded into new Uri instead of $redirect, as that would redirect to itself.
		// If a URI fragment was sent via POST, set it as URI var.
		// $redirect->setFragment($this->input->getString('fragment'));

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Saves a user's individual project matrix configuration.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveMatrixConfig(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post     = $this->input->post->getArray();
		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		$uid    = $this->input->post->getInt('user', 0);
		$config = ArrayHelper::getValue($post, 'config', [], 'ARRAY');

		// Save data.
		$status = $this->model->getInstance('user', ['language' => $this->language])->updateProfile(
			$uid,
			[
				'uid'     => $uid,
				'profile' => $config
			]
		);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// Error messages are set in model

			// http_response_code('500');
		}
		else
		{
			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
			]);

			// http_response_code('200');

			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}
		}

		// Add the page to return to, that was sent via POST.
		// $redirect->setVar('return', base64_encode($return));	// Don't set it, when it was loaded into new Uri instead of $redirect, as that would redirect to itself.

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		header('Location: ' . $redirect->toString());
		exit;
	}
}
