<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Nematrack\Model\Lizt as ListModel;

/**
 * Class description
 */
class Contacts extends ListModel
{
	protected $tableName = 'contact';

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

	public function getList(): array
	{
		return [];
	}
}
