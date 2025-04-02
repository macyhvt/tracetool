<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use  \Messager;
use  \Text;
use  \View\Item as ItemView;
use function is_a;
use function is_null;

/**
 * Class description
 */
class Role extends ItemView
{
	use \ \Traits\View\Role;

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

		// If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \ \Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#27)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}
	
		// Get id of item to fetch from the database.
		$id   = $this->input->get->getInt('rid');

		// Fetch item.
		$item = (isset($id) && $id) ? $this->model->getItem($id, $this->model->get('language')) : null;

		// Access control. Block the attempt to open a non-existing item.
		if (!is_null($id) && !is_object($item))
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))), $this->language), $item->get('roleID'))
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Prepare item for display.
		if (is_a($item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) && $item->get('name'))
		{
			// Do some stuff.
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
		$this->identificationKey = 'rid';

		return $this->identificationKey;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveEdit
	 */
	public function saveEdit(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}
}
