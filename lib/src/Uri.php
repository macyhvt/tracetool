<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Class description
 */
class Uri extends App
{
	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @since   0.1
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}
}
