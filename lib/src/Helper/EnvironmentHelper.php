<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */

use function mb_strtoupper;

defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Class description
 */
final class EnvironmentHelper
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

    public static function isUnix() : bool
    {
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        return mb_strtoupper(PHP_OS) === 'LINUX';
    }

	public static function isWin() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return mb_strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}
}
