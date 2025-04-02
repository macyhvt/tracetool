<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Nematrack\Entity;

/**
 * Class description
 */
class Group extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $groupID = null;

	/**
	 * @var    string  The group name.
	 * @since  1.1
	 */
	protected $name = null;

	/**
	 * @var    integer  Flag indicating the bitwise representation of the role.
	 * @since  1.1
	 */
	protected $flag = null;

	/**
	 * @var    integer  Flag indicating whether this row is blocked.
	 * @since  1.1
	 */
	protected $blocked = null;

	/**
	 * {@inheritdoc}
	 * @see Entity::__construct
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::getTableName
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return 'usergroups';
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::bind
	 */
	public function bind(array $data = []) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		return $this;
	}

	public function getFlag() : int
	{
		return (int) $this->flag;
	}
}
