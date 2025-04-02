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
use  \View\Item as ItemView;
use function array_combine;
use function array_diff;
use function array_fill;
use function array_filter;
use function array_keys;
use function array_walk;
use function in_array;
use function is_a;
use function is_null;
use function property_exists;

/**
 * Class description
 */
class Organisation extends ItemView
{
	use \ \Traits\View\Organisation;

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

		// Prepare view for rendering.
		$this->prepareDocument();

		// Get id of item to fetch from the database.
		$id   = $this->input->get->getInt('oid');

		// Fetch item.
		$item = (isset($id) && $id)
			? $this->model->getItem($id, $this->model->get('language'))
			: $this->model->getItem(0,   $this->model->get('language'));

		// Access control.
		$this->checkAccess();

		// Prepare item for display.
		if (is_a($item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) && $item->get('name'))
		{
			// Check for item metadata being completely translated.
			$model   = $this->model->getInstance('languages');
			$langs   = (array) $model->getList();
			// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
			$metas   = array_combine(array_keys($langs), array_fill(0, count($langs), null));

			// Get list of metadata fields relevant for translation.
			$fields  = DatabaseHelper::getTableColumns($this->get('name') . '_meta');
			$fields  = array_diff($fields, ['orgID','lngID','language']);

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
				$meta = $this->model->getItemMeta($id, $lng, true);

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

			// Prepare item processes for display.
			$tmp = [];
			$processes    = $this->model->getInstance('processes', ['language' => $this->language])->getList();

			array_walk($processes, function($arr) use(&$processes, &$tmp) {
				$tmp[ArrayHelper::getValue($arr, 'procID')] = ArrayHelper::getValue($arr, 'name');
			});
			$processes    = $tmp;

			$tmp = [];
			$orgProcesses = $item->get('processes', []);
			array_walk($orgProcesses, function($procID) use(&$processes, &$tmp) {
				$tmp[$procID] = ArrayHelper::getValue($processes, $procID);
			});
			$orgProcesses = $tmp;

			asort($orgProcesses);

			$item->set('processes', $orgProcesses);

			// Free memory.
			unset($orgProcesses);
			unset($processes);
			unset($tmp);
		}

		// Assign ref to loaded item.
		$this->item = $item;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::getIdentificationKey
	 */
	public function getIdentificationKey(): string
	{
		return $this->identificationKey = 'oid';
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
//				case 'submit' :
				case 'cancel' :
					$redirect = new Uri($this->getInstance('organisations', ['language' => $this->language])->getRoute());
				break;

				// Return to add-Item-view
				case 'submit' :
					$redirect = new Uri('index.php');
					$redirect->setVar('hl',     $this->language);
					$redirect->setVar('view',   $this->get('name'));
					$redirect->setVar('layout', 'item');
				break;

				// Return to add-Item-view
				case 'submitAndNew' :
					$redirect = new Uri('index.php');
					$redirect->setVar('hl',     $this->language);
					$redirect->setVar('view',   $this->get('name'));
					$redirect->setVar('layout', $this->input->getCmd('layout'));
				break;

				default :
					$redirect = new Uri($return);
				break;
			}
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Save data.
		$status = $this->model->addOrganisation(array_filter([
			'form' => $post
		]));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.
			$redirect->setVar('view',   mb_strtolower($this->get('name')));
			$redirect->setVar('layout', $this->input->getCmd('layout'));

			// Add the page to return to, that was sent via POST.
			$redirect->setVar('return', base64_encode($return));

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
				$message .= '\r\n' . Text::translate('COM_FTK_SYSTEM_MESSAGE_ORGANISATION_POST_CREATION_ADD_USERS_TEXT', $this->language);
			}

			$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=edit&oid=' . $status);
			// $redirectToItem->setVar('return', $this->input->post->getBase64('return'));
			$redirectToItem->setVar('return', base64_encode( basename( ( new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=item&oid=' . $status) )->toString() ) ));
			// If a URI fragment was sent via POST, set it as URI var.
			$redirectToItem->setFragment($this->input->getString('fragment'));

			$script = <<<JS
			if (confirm("$message") == true) {
				window.location.assign('index.php?hl=$this->language&view={$this->get('name')}&layout=users&oid=$status');
			} else {
				window.location.assign("{$redirectToItem->toString()}");
			}
JS;
			echo "<script>$script</script>";
		}

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
		$status = $this->model->updateOrganisation(array_filter([
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
	 * {@inheritdoc}
	 * @see Item::checkAccess
	 *
	 * @fixme - last check causing an issue
	 */
	protected function checkAccess() : void
	{
		parent::checkAccess();

		// If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \ \Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#17)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// An (empty) item may be requested by another resource and must not be rendered.
		// Hence, we return right here.
		if (!isset($this->layoutName) ||			// item requested without layout
			$this->layoutName == 'add'/*  ||	// new item to be created
			// FIXME - why does the next line cause the main navigation to break when rendering the link to the organisation here while it works in DEV copy?
			!$this->item->get('orgID')   			// empty item instance requested to use any of its methods */
		) return;

		// Block the attempt to access a non-existing item.
		// If no item id has been passed then chances are that any view method shall be executed. Abort further loading.
		// Important:  We're checking for layout 'add' because whenever this layout is requested there is no item id.
		//             Hence, method <pre>prepareDocument()</pre> would not execute.
		if (isset($this->layoutName) && isset($this->item) && is_null( $this->item->get('orgID') ))
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(
					Text::translate(
						mb_strtoupper(
							sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))
						),
						$this->language
					),
					$this->item->get('orgID')
				)
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// FIXME - move into function checkAccess()
		// Access control. Customers/Suppliers may see only their item(s).
		/*$customerOrganisation = null;

		if (is_object($this->user) && ($this->user->isCustomer() || $this->user->isSupplier()))
		{
			$customerOrganisation = $this->model->getItem($this->user->get('orgID'), $this->model->get('language'));
		}*/

		// Only organisation members or Programmer(s) and the Superuser can access.
		if (is_a($this->item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name')))))
			&& ($this->user->get('orgID') != '1'									// user must be a FRÖTEK-member ... 1 = FRÖTEK-organisation
			&&  $this->user->get('orgID') != $this->item->get('orgID')				// the user's registered organisation ID must match the requested organisation's ID
			&&  $this->user->getFlags() < \ \Access\User::ROLE_PROGRAMMER	// the user must be highly privileged (minimum Programmer)
		)) {
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#18)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		/*// FIXME causing the main menu link to Organisations to break
		// Only Programmer(s) and the Superuser can access other organisational data.
		if ($this->input->getInt('oid') != $this->user->get('orgID'))
		{
			$referer = $this->input->server->getUrl('HTTP_REFERER');

			// When directly called there'll be no referer...
			if (isset($referer))
			{
				$redirect = new Uri($referer);

				Messager::setMessage([
					'type' => 'notice',
					'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#18)' : '')
				]);

				http_response_code('401');

				header('Location: ' . $redirect->toString());
			}
			// ..., in this case we utilize a JS-script to go 1 navigation step back in the browser history.
			else
			{
				Messager::setMessage([
					'type' => 'notice',
					'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#18)' : '')
				]);

				echo "<script>window.history.back(-1);</script>";
			}

			exit;
		}*/
	}
}
