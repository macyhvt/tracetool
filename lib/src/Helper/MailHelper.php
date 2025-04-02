<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */

defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Class description
 */
final class MailHelper
{
	/**
	 * Private constructor. Class cannot be constructed.
	 *
	 * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}
}
