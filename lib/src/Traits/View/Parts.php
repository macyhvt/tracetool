<?php
/* define application namespace */
namespace  \Traits\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Traits description
 */
trait Parts
{
	// Sanitize $_POST data
	/*public function sanitizePOST()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (array) filter_input_array(INPUT_POST, [
			'action'      => FILTER_SANITIZE_STRING,
			'batch'       => FILTER_SANITIZE_STRING,
			// 'canAdd'      => FILTER_SANITIZE_NUMBER_INT,
			// 'canDelete'   => FILTER_SANITIZE_NUMBER_INT,
			// 'canEdit'     => FILTER_SANITIZE_NUMBER_INT,
			// 'canEditPart' => FILTER_SANITIZE_NUMBER_INT,
			// 'canEditProc' => FILTER_SANITIZE_NUMBER_INT,
			'lng'         => FILTER_SANITIZE_STRING,
			'ptid'        => FILTER_SANITIZE_NUMBER_INT,
			'task'        => FILTER_SANITIZE_STRING,
			'type'        => FILTER_SANITIZE_NUMBER_INT,
			'user'        => FILTER_SANITIZE_NUMBER_INT,
			'code'        => [
				'filter'  => FILTER_VALIDATE_REGEXP,
				'options' => [
					'regexp' => '/' . FTKREGEX_TRACKINGCODE . '/'
				]
			],
			'procParams'   => [
				'filter' => FILTER_SANITIZE_STRING,
				'flags'  => FILTER_FORCE_ARRAY					// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
			]
		]);
	}*/
}
