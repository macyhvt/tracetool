<?php
/* define application namespace */
namespace  \Traits\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Traits description
 */
trait User
{
	// Sanitize $_POST data
	/*public function sanitizePOST()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (array) filter_input_array(INPUT_POST, [
			'company'   => FILTER_SANITIZE_STRING,
			'email'     => FILTER_SANITIZE_EMAIL,		// FILTER_SANITIZE_EMAIL,
			'fullname'  => FILTER_SANITIZE_STRING,
			'groups' => [
				'flags'  => FILTER_FORCE_ARRAY,			// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
				'filter' => FILTER_SANITIZE_NUMBER_INT
			],
			'languages' => [
				'flags'  => FILTER_FORCE_ARRAY,			// where FILTER_REQUIRE_ARRAY just requires an array as input value FILTER_FORCE_ARRAY always returns an array
				'filter' => FILTER_SANITIZE_NUMBER_INT
			],
			'oid'       => FILTER_SANITIZE_NUMBER_INT,
			'password'  => FILTER_UNSAFE_RAW,			// Do not use FILTER_SANITIZE_STRING here as this may encode special chars and thus make the password invalid !!!,
			'status'    => FILTER_SANITIZE_NUMBER_INT,
			'task'      => FILTER_SANITIZE_STRING,
			'user'      => FILTER_SANITIZE_NUMBER_INT,	// language of the creator
			'uid'       => FILTER_SANITIZE_NUMBER_INT,	// Refers to the id of the user to be added/edited.
			'xid'       => FILTER_SANITIZE_NUMBER_INT	// Refers to the id of the user to be deleted. For some reason 'uid' and 'xid' are filtered to ''.
		]);
	}*/
}
