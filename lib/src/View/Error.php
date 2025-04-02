<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Nematrack\View;
use Nematrack\View\Item as ItemView;

/**
 * Class description
 */
class Error extends ItemView
{
	/**
	 * {@inheritdoc}
	 * @see View::__construct
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

		// Get id of item to fetch from the database.
		$id   = $this->input->get->getInt('hash');

		// Fetch item.
		$item = (isset($id) && $id) ? $this->model->getItem($id, $this->model->get('language')) : null;

		// Assign ref to loaded item.
		$this->item = $item;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::__construct
	 */
	public function getIdentificationKey(): string
	{
		return $this->identificationKey = 'eid';
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = ''): void
	{
		// TODO: Implement saveAdd() method.
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveEdit
	 */
	public function saveEdit(string $redirect = ''): void
	{
		// TODO: Implement saveEdit() method.
	}
}
