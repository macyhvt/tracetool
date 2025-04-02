<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use  \View\Lizt as ListView;
use function property_exists;

/**
 * Class description
 */
class Users extends ListView
{
	use \ \Traits\View\Users;

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
		if ($this->user->getFlags() < \ \Access\User::ROLE_ADMINISTRATOR)
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

		// Access control. If a user's flags don't satisfy the minimum requirement, but the user is privileged to manage users, silently redirect it to its organisation users.
		if ($this->user->getFlags() == \ \Access\User::ROLE_ADMINISTRATOR)
		{
			$redirect = new Uri($this->getInstance('organisation', ['language' => $this->language])->getRoute());
			$redirect->setVar('layout', 'users');
			$redirect->setVar('oid',     $this->user->get('orgID'));
			$redirect->setVar('filter',  $this->input->getString('filter'));

			http_response_code('307');  // see: {@link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/307}

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Define list filter to apply.
		/*$filter = ($this->user->isGuest() || $this->user->isCustomer() || $this->user->isSupplier())
			? $this->input->getString('filter', (string) ListModel::FILTER_ALL)		// initially loads all items
			: $this->input->getString('filter', (string) ListModel::FILTER_ACTIVE);	// initially loads only active items*/
		$filter = $this->input->getString('filter', (string) ListModel::FILTER_ACTIVE);

		// Load users list limited by access rights.
		switch (true)
		{
			// Customers/Suppliers may see only their projects.
			// FIXME - refactor model to process function args like in all ...NEW() functions (hint: see view projects)
			case ( $this->user->isGuest() ||  $this->user->isCustomer() ||  $this->user->isSupplier()) :
//				$list = $this->model->getInstance('organisation', ['language' => $this->language])->getOrganisationUsers__OBSOLETE($this->user->get('orgID'));
				$list = $this->model->getInstance('organisation', ['language' => $this->language])->getOrganisationUsers([
					'orgID'  => $this->user->get('orgID'),   // FIXME - getPrimaryKeyName
					'filter' => $filter,
				]);
			break;

			// Company members may see all items.
			case (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier()) :
				// FIXME - in model replace function 'getList' with this new implementation and test all js
				$list = $this->model->getList(
					[
						'userID' => $this->input->getInt('uid', $this->input->getInt('userID')),	// FIXME - getPrimaryKeyName
						'filter' => $filter
					]
				);
			break;

			default :
				$list = [];
		}

		// Assign ref to loaded list data.
		$this->list = $list;
	}

	/**
	 * Saves the deletion of an item.
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @todo - migrate to parent model's deletion function like in other list views
	 */
	public function saveDeletion(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$post   = $this->input->post->getArray();

		$status = $this->model->getInstance('user', ['language' => $this->language])->deleteUser($this->input->post->getInt('xid', 0));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			// Message set by model
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_USER_COULD_NOT_BE_DELETED_TEXT', $this->get('language'))
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
					'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_USER_WAS_DELETED_TEXT', $this->get('language'))
				]);
			}
		}

		header('Location: index.php?hl=' . $this->language . '&view=organisation&layout=users&oid=' . $this->input->post->getInt('oid', 0));
		exit;
	}
}
