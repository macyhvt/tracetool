<?php
/* define application namespace */
namespace  \Traits\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Traits description
 */
trait Processes
{
	// Sanitize $_POST data
	/*public function sanitizePOST()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (array) filter_input_array(INPUT_POST, [
			// 'canAdd'      => FILTER_SANITIZE_NUMBER_INT,
			// 'canDelete'   => FILTER_SANITIZE_NUMBER_INT,
			// 'canEdit'     => FILTER_SANITIZE_NUMBER_INT,
			'description' => FILTER_SANITIZE_STRING,
			'lng'         => FILTER_SANITIZE_STRING,
			'name'        => FILTER_SANITIZE_STRING,
			'pid'         => FILTER_SANITIZE_NUMBER_INT,
			'task'        => FILTER_SANITIZE_STRING,
			'user'        => FILTER_SANITIZE_NUMBER_INT,
			'params'      => [
				'flags'  => FILTER_FORCE_ARRAY,				// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
				'filter' => FILTER_SANITIZE_STRING
			]
		]);
	}*/
}
