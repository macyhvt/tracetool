<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use  \App;
use  \Model;

/**
 * Class description
 */
abstract class Item extends Model
{
	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * Fetch a specific item itendified by ID.
	 *
	 * Must be implemented by the child class.
	 *
	 * @param   int $itemID
	 *
	 * @return  \ \Entity
	 */
	abstract public function getItem(int $itemID);

	/**
	 * Add description...
	 *
	 * @return  bool   true if user can delete or false if not
	 */
	protected function userCanDelete() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__, true) . '</pre>' : null;

		$canDelete = (parent::userCanDelete() && App::getAppUser()->getFlags() > \ \Access\User::ROLE_MANAGER);

		return $canDelete;
	}
}
