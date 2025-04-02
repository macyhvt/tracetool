<?php
/* define application namespace */
namespace Nematrack\Utility\Filter;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Utility class for filtering input from any data source.
 * It extends the {@see \Joomla\Filter\InputFilter} class and adds filtering not provided that class.
 *
 * @since  2.10.1
 */
class InputFilter extends \Joomla\Filter\InputFilter
{
    /**
     * E-mail filter
     *
     * @param   string  $source  The string to be filtered
     *
     * @return  string  The filtered string
     */
    protected function cleanEmail(string $source): string
    {
//		die(sprintf('input: %s',  $source));
//		die(sprintf('return: %s', filter_var($source, FILTER_SANITIZE_EMAIL)));
        return filter_var($source, FILTER_SANITIZE_EMAIL);
    }
}
