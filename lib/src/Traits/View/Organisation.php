<?php
/* define application namespace */
namespace  \Traits\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Traits description
 */
trait Organisation
{
	// Sanitize $_POST data
	/*public function sanitizePOST()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (array) filter_input_array(INPUT_POST, [
			'addressline' => FILTER_SANITIZE_STRING,
			// 'canAdd'      => FILTER_SANITIZE_NUMBER_INT,
			// 'canDelete'   => FILTER_SANITIZE_NUMBER_INT,
			// 'canEdit'     => FILTER_SANITIZE_NUMBER_INT,
			'city'        => FILTER_SANITIZE_STRING,
			'country'     => FILTER_SANITIZE_STRING,
			'description' => FILTER_SANITIZE_STRING,
			'homepage'    => FILTER_SANITIZE_URL,
			'lng'         => FILTER_SANITIZE_STRING,
			'name'        => FILTER_SANITIZE_STRING,
			'oid'         => FILTER_SANITIZE_NUMBER_INT,
			'rid'         => FILTER_SANITIZE_NUMBER_INT,
			'task'        => FILTER_SANITIZE_STRING,
			'user'        => FILTER_SANITIZE_NUMBER_INT,
			'zip'         => FILTER_SANITIZE_STRING,
			'processes'   => [
				'flags'  => FILTER_FORCE_ARRAY,				// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
				'filter' => FILTER_SANITIZE_NUMBER_INT
			]
		]);
	}*/
}
