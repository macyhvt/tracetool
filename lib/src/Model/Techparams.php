<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use function array_filter;
use function array_map;
use function array_walk;
use function property_exists;

/**
 * Class description
 */
class Techparams extends ListModel
{
	protected $tableName = 'techparam';

	/**
	 * The tracking operator's organisation identifier.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_ORGANISATION = 1;

	/**
	 * The tracking operator identifier.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_OPERATOR     = 2;

	/**
	 * The tracking date identifier.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_DATE         = 3;

	/**
	 * The tracking time identifier.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_TIME         = 4;

	/**
	 * The tracked process' drawing identifier.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_DRAWING      = 5;

	/**
	 * The tracked process' error if there is any.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_ERROR        = 6;

	/**
	 * The tracked process' annotion.
	 *
	 * @var    int
	 * @since  1.0
	 */
	public const STATIC_TECHPARAM_ANNOTATION   = 7;

	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	//@todo - implement
	public function getList() : array
	{
		return [];
	}

	public function getStaticTechnicalParameters($fieldnamesOnly = false) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if ($fieldnamesOnly)
		{
			return [
				static::STATIC_TECHPARAM_ORGANISATION => 'organisation',
				static::STATIC_TECHPARAM_OPERATOR     => 'operator',
				static::STATIC_TECHPARAM_DATE         => 'date',
				static::STATIC_TECHPARAM_TIME         => 'time',
				static::STATIC_TECHPARAM_DRAWING      => 'drawing',
				static::STATIC_TECHPARAM_ERROR        => 'error',
				static::STATIC_TECHPARAM_ANNOTATION   => 'annotation'
			];
		}
		else
		{
			return [
				static::STATIC_TECHPARAM_ORGANISATION => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_ORGANISATION_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'organisation'
				],
				static::STATIC_TECHPARAM_OPERATOR => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_OPERATOR_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'operator'
				],
				static::STATIC_TECHPARAM_DATE => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_DATE_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'date'
				],
				static::STATIC_TECHPARAM_TIME => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_TIME_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'time'
				],
				static::STATIC_TECHPARAM_DRAWING => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_DRAWING_NUMBER_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'drawing'
				],
				static::STATIC_TECHPARAM_ERROR => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_STATUS_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'error'
				],
				static::STATIC_TECHPARAM_ANNOTATION => (object) [
					'name'      => Text::translate('COM_FTK_LABEL_ANNOTATION_TEXT', $this->language),
					'language'  => $this->language,
					'fieldname' => 'annotation'
				]
			];
		}
	}

	/**
	 * Add description ...
	 *
	 * @uses   {@link \Symfony\Component\String\Inflector\EnglishInflector}
	 */
	public function getTechnicalParametersByLanguage($lang = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->select("
			GROUP_CONCAT(
				DISTINCT CONCAT_WS(':', `paramID`, `name`) SEPARATOR '|'
			) AS `tparam`
		")
		->from($db->qn('techparameters'))
		->where($db->qn('language') . ' = '  . $db->q(trim($lang)))
		->where($db->qn('name')     . ' <> ' . $db->q('n/a'));

		// Execute query.
		try
		{
			$rows = [];
			$tmp  = [];
			
			$rs   = $db->setQuery($query)->loadObject();

			if (property_exists($rs, 'tparam'))
			{
				$tparams = explode('|', $rs->tparam);
				$tparams = array_map('trim', $tparams);
				$tparams = array_filter($tparams);

				if (count($tparams))
				{
					array_walk($tparams, function($tparam) use(&$tmp)
					{
						[$lang, $text] = explode(':', $tparam);

						$tmp[trim($lang)]  = str_ireplace('~', ',', trim($text));	// revert concatenation delimiter
					});
				}

				asort($tmp, SORT_NATURAL | SORT_FLAG_CASE);	// sort ignoring case
			}

			$rows = $tmp;
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rows = [];
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rows;
	}
}
