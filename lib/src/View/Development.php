<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use  \App;
use  \Helper\UriHelper;
use  \Messager;
use  \Text;
use  \View;
use function is_a;

/**
 * Class description
 */
class Development extends View
{
	use \ \Traits\View\Statistics;

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

		// Access control. Only registered and authenticated users can view content.
		if (!is_a($this->user, ' \Entity\User'))
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#8)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		if ($this->user->getFlags() < \ \Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#9)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}
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
}
