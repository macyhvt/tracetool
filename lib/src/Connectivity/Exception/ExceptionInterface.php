<?php
/* define application namespace */
namespace Nematrack\Connectivity\Exception;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Exception interface for all custom exceptions thrown by the application.
 */
interface ExceptionInterface extends \Throwable
{
}
