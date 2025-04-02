<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use Nematrack\App;
use Nematrack\Helper\UriHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View;

/**
 * Class description
 */
class Quality extends View
{
	use \Nematrack\Traits\View\Quality;

	/**
	 * {@inheritdoc}
	 * @see View::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

		// Assign ref to HTTP Request object.
		$this->input = App::getInput();

		// Don't load display data when there's POST data to process.
		/* if (count($this->input->post->getArray()))
		{
			return;
		} */

		// Access control.
		$this->checkAccess();
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	private function saveAdd(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$action = $this->input->post->getWord('button');
		$post   = $this->input->post->getArray();
		$return = base64_decode($this->input->post->getBase64('return'));

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
					$redirect = new Uri($this->getInstance('quality', ['language' => $this->get('language')])->getRoute());
				break;

				// Return to add-Item-view
				case 'submitAndNew' :
					$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=' . $this->input->getCmd('task'));
				break;

				default :
					$redirect = new Uri($return);
				break;
			}
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		$status = 1;

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.
			$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=' . $this->input->getCmd('task'));

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

			/*// 3rd attempt: If $redirect is still empty, load hardcoded URI into a {@see Joomla\Uri\Uri} object otherwise redirect to $return.
			$redirectToList = (empty($redirect->getPath()))
				? new Uri($this->getInstance('articles', ['language' => $this->language])->getRoute())
				: $redirect;*/

			$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=cplan.edit&cpid=' . $status);
			// $redirectToItem->setVar('return', $this->input->post->getBase64('return'));
			$redirectToItem->setVar('return', base64_encode(basename((new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=cplan.edit&cpid=' . $status))->toString())));
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
	 * @see View::getRoute
	 */
	public function getRoute() : string
	{
		$route = mb_strtolower( sprintf( 'index.php?hl=%s&view=%s', $this->get('language'), $this->get('name') ) );

		return UriHelper::fixURL($route);
	}

	/**
	 * {@inheritdoc}
	 * @see Item::checkAccess
	 */
	protected function checkAccess() : void
	{
		parent::checkAccess();

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		if ($this->user->getFlags() < \Nematrack\Access\User::ROLE_QUALITY_ASSURANCE)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#25)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}
	}
}
