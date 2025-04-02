<?php
/* define application namespace */
namespace  \View\Exception;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Exception interface for all custom exceptions thrown by the application.
 */
interface ExceptionInterface extends \Throwable
{
}
