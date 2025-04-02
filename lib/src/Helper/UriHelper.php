<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Class description
 */
final class UriHelper
{
	/**
	 * The URI base path
	 *
	 * @var string
	 */
	protected static string $base = '';

	/**
	 * Private constructor. Class cannot be constructed.
     *
     * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		self::$base = FTKURI_BASE;
	}

	/**
	 * Returns the URI base path.
	 *
	 * @return  string  The processed URI
	 *
	 * @uses self::fixURL()
	 */
	public static function base() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return '' . self::fixURL(self::$base ?? FTKURI_BASE);
	}

	/**
	 * Fixes a given URI by substituting all path separation characters with
	 * the proper URI path separation character.
	 *
	 * @param   string $uri
	 *
	 * @return  string  The processed URI
	 */
	public static function fixURL(string $uri) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$uri = str_ireplace('\\', '/', $uri);
		$uri = str_ireplace('//', '/', $uri);
		$uri = preg_replace('~^http(s?)(:\/)(.*)$~i', 'http$1$2/$3', $uri);

		return '' . $uri;
	}

    /**
	 * Detects if a given URI is an absolute URI starting with (e.g.: http(s)/ftp(s)/...).
	 *
	 * @param   string $uri
	 *
	 * @return  bool  true when it is an absolute URI or false otherwise
	 */
	public static function isAbsolute(string $uri) : bool
	{
		return preg_match('~^https?:\/\/~i', $uri);
	}

	/**
	 * Checks and optionally fixes a given URI to ensure it starts with a leading slash on UNIX base OS'
	 * whereas it must not start with a leading slash on Microsoft Windows based OS'.
	 *
	 * @param   string $uri
	 *
	 * @return  string  The processed URI
	 *
	 * @uses self::isAbsolute()
	 * @uses self::stripProtocol()
	 * @uses self::fixURL()
	 */
	public static function osSafe(string $uri) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (self::isAbsolute($uri))
		{
			return $uri;
		}

		$uri = self::fixURL($uri);

		return '' . (EnvironmentHelper::isWin())
            ? ltrim($uri, '/')
            : (EnvironmentHelper::isUnix()
                ? '/' . ltrim($uri, '/')
                : $uri);
	}

	/**
	 * Transforms a given URI into a relative URI.
	 *
	 * @param   string $uri
	 *
	 * @return  string  The processed URI
	 *
	 * @uses self::isAbsolute()
	 * @uses self::stripProtocol()
	 * @uses self::fixURL()
	 * @uses EnvironmentHelper::isUnix()
	 * @uses EnvironmentHelper::isWin()
	 */
	public static function relativise(string $uri) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (self::isAbsolute($uri))
		{
			$uri = self::stripProtocol($uri);
			$uri = mb_substr($uri, mb_stripos($uri, '/'));
		}

		$uri = self::fixURL($uri);

		return '' . self::osSafe($uri);
	}

	/**
	 * Strips from a given URI the protocol information (e.g.: http(s)/ftp(s)/...).
	 *
	 * @param   string $uri
	 *
	 * @return  string  The processed URI
	 */
	public static function stripProtocol(string $uri) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return '' . preg_replace('~https?:\/{2,}~i', '', $uri);
	}
}
