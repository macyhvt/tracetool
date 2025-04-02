<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View\Lizt as ListView;

/**
 * Class description
 */
class Cplans extends ListView
{
	use \Nematrack\Traits\View\Roles;

	/**
	 * {@inheritdoc}
	 * @see Lizt::__construct
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

		// Access control.
		$this->checkAccess();
	}


	/**
	 * {@inheritdoc}
	 * @see Item::checkAccess
	 */
	protected function checkAccess() : void
	{
		parent::checkAccess();

		// If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \Nematrack\Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#6)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}
	}
}
