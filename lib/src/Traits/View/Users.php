<?php
/* define application namespace */
namespace Nematrack\Traits\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Traits description
 */
trait Users
{
	// Sanitize $_POST data
	/*public function sanitizePOST()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (array) filter_input_array(INPUT_POST, [
			'oid'  => FILTER_SANITIZE_NUMBER_INT,
			'task' => FILTER_SANITIZE_STRING,
			'user' => FILTER_SANITIZE_NUMBER_INT,
			'xid'  => FILTER_SANITIZE_NUMBER_INT	// Refers to the id of the user to be deleted. For some reason 'uid' and 'xid' are filtered to ''.
		]);
	}*/
}
