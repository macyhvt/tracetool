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
use function array_slice;
use function in_array;
use function is_a;
use function is_null;
use function property_exists;

/**
 * Class description
 */
class Process extends ItemView
{
	use \ \Traits\View\Process;

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
		$id   = $this->input->get->getInt('pid');

		// Fetch item.
		$item = (isset($id) && $id)
				? $this->model->getItem($id, $this->model->get('language'), true, true)
				: $this->model->getItem(0, $this->model->get('language'), true, true);

		// Access control. Block the attempt to open a non-existing item.
		if (!is_null($id) && !is_object($item))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))), $this->language), $item->get('procID'))
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Prepare item for display.
		if (is_a($item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) && $item->get('abbreviation'))
		{
			// Check for item metadata being completely translated.
			$model   = $this->model->getInstance('languages');
			$langs   = (array) $model->getList();
			// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
			$metas   = array_combine(array_keys($langs), array_fill(0, count($langs), null));

			// Get list of metadata fields relevant for translation.
			$fields  = DatabaseHelper::getTableColumns($this->get('name') . '_meta');
			$fields  = array_diff($fields, ['procID','lngID','language']);

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

			// Get common technical parameters required by every technical parameter.
			// These will be filled from system- or user data.
			$staticTechParams = $this->model->getInstance('techparams', ['language' => $this->model->get('language')])->getStaticTechnicalParameters(true);

			// Drop static technical parameters. These must not be rendered.
			$techParams = array_slice($item->get('tech_params', []), count($staticTechParams), null, true);

			// Assign ref to modified technical parameters for output.
			$item->__set('techParams', $techParams);

			// Get this item's error catalog.
			// $errCatalog = $this->model->getCatalog($id);
			$errCatalog = $item->get('error_catalog', []);

			// Assign ref.
			$item->__set('errCatalog', $errCatalog ?? new Registry);
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
		return $this->identificationKey = 'pid';
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
					$redirect = new Uri($this->getInstance('processes', ['language' => $this->get('language')])->getRoute());
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

		// Save data.
		$status = $this->model->addProcess(array_filter([
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
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);
			}

			$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=edit&pid=' . $status);
			// $redirectToItem->setVar('return', $this->input->post->getBase64('return'));
			$redirectToItem->setVar('return', base64_encode( basename( ( new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=item&pid=' . $status) )->toString() ) ));
			// If a URI fragment was sent via POST, set it as URI var.
			$redirectToItem->setFragment($this->input->getString('fragment'));

			$script = <<<JS
			// A window.unload-event will be triggered and a handler is implemented to reload the list view.
			window.location.assign("{$redirectToItem->toString()}");
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
		$status = $this->model->updateProcess(array_filter([
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
	 * Saves the error catalog items for a specific item.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveErrorCatalog(string $redirect = '') : void
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

		$status = $this->model->updateCatalog(
			$this->input->post->getInt('pid', 0),
			[
				'form' => $post
			]
		);

		if (empty($redirect->getPath()))
		{
			$redirect = new Uri('index.php?hl=' . $this->language . '&view=process&layout=edit.catalog&pid=' . $this->input->post->getInt('pid', 0));

			if (($eid = $this->input->post->getInt('eid', 0)) > 0)
			{
				$redirect->setVar('eid', $eid);
			}

			$redirect = $redirect->toString();
		}

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

		header('Location: ' . $redirect);
		exit;
	}
}
