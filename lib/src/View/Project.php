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
class Project extends ItemView
{
	use \ \Traits\View\Project;

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

		// Access control. Only registered and authenticated users can view content.
		if (!is_a($this->user, ' \Entity\User'))
		{
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \ \Access\User::ROLE_WORKER)
		{
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Get id of item to fetch from the database.
		$id   = $this->input->get->getInt('proid');

		// Fetch item.
//		$item = (isset($id) && $id) ? $this->model->getItem($id, $this->model->get('language')) : null;
		$item = (isset($id) && $id)
				? $this->model->getItem($id, $this->model->get('language'))
				: $this->model->getItem(0, $this->model->get('language'));

		// Access control. Block the attempt to open a non-existing item.
		if (!is_null($id) && !is_object($item))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))), $this->language), $item->get('proID'))
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// FIXME - move into function checkAccess()
		// Access control. Customers/Suppliers may see only their item(s).
		$customerProjects = null;

		if (is_object($this->user) && $this->user->isCustomer())
		{
			$customerProjects = (array) $this->model->getInstance('organisation', ['language' => $this->model->get('language')])->getOrganisationProjectsNEW(['orgID' => $this->user->get('orgID')]);

			$customerProjects = array_column($customerProjects, 'number');
		}

		if (is_array($customerProjects) && !in_array($item->get('number'), $customerProjects))
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#23)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Prepare item for display.
		if (is_a($item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) && $item->get('number'))
		{
			// Check for item metadata being completely translated.
			$model   = $this->model->getInstance('languages');
			$langs   = (array) $model->getList();
			// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
			$metas   = array_combine(array_keys($langs), array_fill(0, count($langs), null));

			// Get list of metadata fields relevant for translation.
			$fields  = DatabaseHelper::getTableColumns($this->get('name') . '_meta');
			$fields  = array_diff($fields, ['proID','lngID','language']);

			// Rename field 'name' to 'label' to pass hint translation later on.
			if ($idx = array_search('name', $fields))
			{
				$fields[$idx] = 'label';
			}

			// Rename field 'description' to 'annotation' to pass hint translation later on.
			if ($idx = array_search('description', $fields))
			{
				$fields[$idx] = 'annotation';
			}

			// Prepare object holding missing translation details.
			// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
			$incomplete = new Registry([
				'translation' => array_combine(
					$fields, array_fill(0, count($fields), $metas)
				)
			]);

			// Find missing field translation(s).
			array_walk($langs, function($lang) use(&$model, &$id, &$langs, &$incomplete)
			{
				$lng  = ArrayHelper::getValue($lang, 'tag');
				$meta = $this->model->getItemMeta($id, $lng, true); // empty result means no metadata at all, whereas an array holds a single row of metadata

				if ($meta)
				{
					if (!empty($meta['name']))
					{
						$incomplete->remove('translation.label.' . $lng);
					}

					if (!empty($meta['description']))
					{
						$incomplete->remove('translation.annotation.' . $lng);
					}
				}

				// Skip collection if empty.
				$collection = (array) $incomplete->get('translation.label');
				if (!count($collection) || (count($collection) == count($langs)))
				{
					$incomplete->remove('translation.label');
				}

				$collection = (array) $incomplete->get('translation.annotation');
				if (!count($collection) || (count($collection) == count($langs)))
				{
					$incomplete->remove('translation.annotation');
				}
			});

			// Add list to item for rendering.
			if (count($incomplete))
			{
				$item->__set('incomplete', $incomplete);
			}

			// Free memory.
			unset($langs);
			unset($metas);
			unset($incomplete);
			unset($model);

			// Inject bad parts count.
			$item->__set('badParts', $this->model->getBadParts($id, true));
		}

		// Assign ref to loaded item.
		$this->item   = $item;

		// Assign ref to project config.
		$this->config = new Registry($item->get('config', []));

		// Define list of project lifecyle stages.
		$this->statusTypes   = ArrayHelper::toObject([
			'P'   => [
				'factor'      => $this->config->get('factors.P',   2.5),
				'name'        => Text::translate('COM_FTK_LIST_OPTION_PROTOTYPE_TEXT',         $this->get('language')),
				'description' => Text::translate('COM_FTK_LIST_OPTION_PROTOTYPE_DESC',         $this->get('language'))
			] ,
			'PVS' => [
				'factor'      => $this->config->get('factors.PVS', 1.75),
				'name'        => Text::translate('COM_FTK_LIST_OPTION_PRESERIES_TEXT',         $this->get('language')),
				'description' => Text::translate('COM_FTK_LIST_OPTION_PRESERIES_DESC',         $this->get('language'))
			],
			'0S' => [
				'factor'      => $this->config->get('factors.0S',  1.25),
				'name'        => Text::translate('COM_FTK_LIST_OPTION_PILOT_SERIES_TEXT',      $this->get('language')),
				'description' => Text::translate('COM_FTK_LIST_OPTION_PILOT_SERIES_DESC',      $this->get('language'))
			],
			'S'  => [
				'factor'      => $this->config->get('factors.S',   1),
				'name'        => Text::translate('COM_FTK_LIST_OPTION_SERIAL_PRODUCTION_TEXT', $this->get('language')),
				'description' => Text::translate('COM_FTK_LIST_OPTION_SERIAL_PRODUCTION_DESC', $this->get('language'))
			]
		], 'stdclass', true);
	}

	/**
	 * {@inheritdoc}
	 * @see Item::getIdentificationKey
	 */
	public function getIdentificationKey(): string
	{
		$this->identificationKey = 'proid';

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
					$redirect = new Uri($this->getInstance('projects', ['language' => $this->get('language')])->getRoute());
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

		$status   = $this->model->addProject(array_filter([
			'form' => $post
		]));

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

			if (in_array($action, ['submitAndClose']))
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);

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

			if (in_array($action, ['submit']))
			{
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
				window.location.assign('index.php?hl={$this->language}&view={$this->get('name')}&layout=team.members&proid={$status}');
			} else {
				window.location.assign("{$redirectToItem->toString()}");
			}
JS;
			echo "<script>$script</script>";
		}

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
