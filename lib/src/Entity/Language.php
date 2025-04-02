<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Nematrack\Entity;

/**
 * Class description
 */
class Language extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $lngID = null;

	/**
	 * @var    string  The unique language tag.
	 * @since  1.1
	 */
	protected $tag = null;

	/**
	 * @var    string  The language name.
	 * @since  1.1
	 */
	protected $name = null;

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

		return 'languages';
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
}
