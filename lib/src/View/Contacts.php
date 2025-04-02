<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Nematrack\View\Lizt as ListView;

/**
 * Class description
 */
class Contacts extends ListView
{
	use \Nematrack\Traits\View\Articles;

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

		$list = $this->model->getList();

		$this->list = $list;
	}
}
