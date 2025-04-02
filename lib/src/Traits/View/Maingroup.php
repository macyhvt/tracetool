<?php
/* define application namespace */
namespace Nematrack\Traits\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Traits description
 */
trait Maingroup
{
	// Sanitize $_POST data
	/*public function sanitizePOST()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (array) filter_input_array(INPUT_POST, [
			// 'canAdd'      => FILTER_SANITIZE_NUMBER_INT,
			// 'canDelete'   => FILTER_SANITIZE_NUMBER_INT,
			// 'canEdit'     => FILTER_SANITIZE_NUMBER_INT,
			'customer'    => FILTER_SANITIZE_STRING,
			'description' => FILTER_SANITIZE_STRING,
			'lng'         => FILTER_SANITIZE_STRING,
			'name'        => FILTER_SANITIZE_STRING,
			'number'      => FILTER_SANITIZE_STRING,
			'oid'         => FILTER_SANITIZE_NUMBER_INT,
			'proid'       => FILTER_SANITIZE_NUMBER_INT,
			'task'        => FILTER_SANITIZE_STRING,
			'user'        => FILTER_SANITIZE_NUMBER_INT,
			'oids'        => [											// list of organisationIDs to be associated to a project
				'filter' => FILTER_SANITIZE_NUMBER_INT,
				'flags'  => FILTER_FORCE_ARRAY							// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
			],
			// Error catalog item(s)
			'config' => [
				'flags'  => FILTER_FORCE_ARRAY,				// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
				'filter' => [
					'flags'  => FILTER_FORCE_ARRAY,
					'filter' => FILTER_SANITIZE_STRING
				]
			]
		]);
	}*/
}
