<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View\Lizt as ListView;

/**
 * Class description
 */
class Processes extends ListView
{
	use \Nematrack\Traits\View\Processes;

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

		// Access control. Only registered and authenticated users can view content.
		if (!is_a($this->user, 'Nematrack\Entity\User'))
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
		if ($this->user->getFlags() < \Nematrack\Access\User::ROLE_WORKER)
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Define list filter to apply.
		/*$filter = ($this->user->isGuest() || $this->user->isCustomer() || $this->user->isSupplier())
			? $this->input->getString('filter', (string) ListModel::FILTER_ALL)		// initially loads all items
			: $this->input->getString('filter', (string) ListModel::FILTER_ACTIVE);	// initially loads only active items*/
		$filter = $this->input->getString('filter', (string) ListModel::FILTER_ACTIVE);

		// Load contents limited by access rights.
		switch (true)
		{
			// Company members may see all items.
			case (!$this->user->isGuest() &&
				  !$this->user->isCustomer() &&
				  !$this->user->isSupplier() &&
				   $this->user->getFlags() >= \Nematrack\Access\User::ROLE_WORKER
			) :
				// FIXME - in model replace function 'getList' with this new implementation and test all js
				$list = $this->model->getList(
					[
						'procID'  => $this->input->getInt('pid', $this->input->getInt('procID')),
						'filter'  => $filter,
						'params'  => true,
						'catalog' => true	// ADDED on 2023-06-09 to have the error catalog available on the list view
					]
				);
			break;

			default :
				$list = [];
		}

		// Assign ref to loaded list data.
		$this->list = $list;
	}
}
