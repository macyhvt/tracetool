<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use  \Helper\UriHelper;
use  \View;

/**
 * Class description
 */
class System extends View
{
	/**
	 * {@inheritdoc}
	 * @see View::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);
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
