<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Joomla\Registry\Registry;
use JsonException;
use Nematrack\Entity;
use Nematrack\Helper\JsonHelper;
use Nematrack\Model;
use function array_filter;
use function array_walk;
use function is_null;
use function is_string;

/**
 * Class description
 */
class Process extends Entity
{
	/**
	 * @var    integer  A unique entity id.
	 * @since  1.1
	 */
	protected $procID = null;

	/**
	 * @var    String  A unique abbreviation.
	 * @since  1.1
	 */
	protected $abbreviation = null;

	/**
	 * @var    string  A process configuration object in JSON-format.
	 * @since  2.10.1
	 */
	protected $config = null;

	/**
	 * @var    integer  Flag indicating whether this row is blocked.
	 * @since  1.1
	 */
	protected $blocked = null;

	/**
	 * @var    DateTime  Date and time when this row was blocked.
	 * @since  1.1
	 */
	protected $blockDate = null;

	/**
	 * @var    string  The name of the blocker of this row.
	 * @since  1.1
	 */
	protected $blocked_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as archived.
	 * @since  1.1
	 */
	protected $archived = null;

	/**
	 * @var    DateTime  Date and time when this row was archived.
	 * @since  1.1
	 */
	protected $archiveDate = null;

	/**
	 * @var    string  The name of the archivator of this row.
	 * @since  1.1
	 */
	protected $archived_by = null;

	/**
	 * @var    integer  Flag indicating whether this row is marked as deleted.
	 * @since  1.4
	 */
	protected $trashed = null;

	/**
	 * @var    DateTime  Date and time when this row was trashed.
	 * @since  1.1
	 */
	protected $trashDate = null;

	/**
	 * @var    string  The name of the trasher of this row.
	 * @since  1.1
	 */
	protected $trashed_by = null;

	/**
	 * @var    DateTime  The row creation date and time.
	 * @since  1.1
	 */
	protected $created = null;

	/**
	 * @var    string  The name of the creator of this row.
	 * @since  1.1
	 */
	protected $created_by = null;

	/**
	 * @var    DateTime  Date and time when this row was last edited.
	 * @since  1.1
	 */
	protected $modified = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $modified_by = null;

	/**
	 * @var    DateTime  Date and time when this row was marked as deleted.
	 * @since  1.1
	 */
	protected $deleted = null;

	/**
	 * @var    string  The name of the last editor of this row.
	 * @since  1.1
	 */
	protected $deleted_by = null;

	/**
	 * @var    integer  The ordering number of this process.
	 * @since  1.1
	 */
	protected $ordering = null;

	/**
	 * @var    Registry  The process name.
	 * @since  1.1
	 */
	protected $name = null;

	/**
	 * @var    string  The item description.
	 * @since  1.1
	 * @deprecated Deprecated in version 2.11
	 */
	protected $description = null;

	/**
	 * @var    string  The item annotation.
	 * @since  1.1
	 */
	protected $annotation = null;

	/**
	 * @var    array  Objects container.
	 * @since  1.1
	 */
	protected $organisations = null;

	/**
	 * @var    array  Objects container.
	 * @since  1.1
	 */
	protected $error_catalog = null;

	/**
	 * @var    array  Objects container.
	 * @since  1.1
	 */
	protected $tech_params = null;

	/**
	 * {@inheritdoc}
	 * @see Entity::__construct
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::getTableName
	 */
	public function getTableName() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return 'processes';
	}

	/**
	 * {@inheritdoc}
	 * @see Entity::bind
	 */
	public function bind(array $data = []) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		/* Convert entity configuration from JSON to Object */
		$config = $this->get('config');

		if (is_string($config) && JsonHelper::isValidJSON($config))
		{
			try
			{
				$config = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$config = [];
			}

			$this->config = new Registry($config);
		}
		else
		{
			if (is_null($config))
			{
				$this->config = new Registry;
			}
		}

		/* Convert entity responsible organisations from JSON to Object */
		$organisations = $this->get('organisations');

		if (is_string($organisations) && JsonHelper::isValidJSON($organisations))
		{
			try
			{
				$organisations = json_decode($organisations, true, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$organisations = [];
			}

			$this->organisations = $organisations;
		}
		else
		{
			if (is_null($organisations))
			{
				$this->organisations = [];
			}
		}

		/* Convert entity technical parameters from JSON to Object */

		// Get static technical parameters (none-editable by user)
		// FIXME - move loading data outta here. This object should only receive data and prepare it.
		$statTechParams = Model::getInstance('techparams', ['language' => $this->language])->getStaticTechnicalParameters();

		$tmp = [];

		$params = $this->get('tech_params');

		if (!is_null($params) && !is_array($params) && !is_object($params) && (is_string($params) || empty($params)))
		{
			$params = explode(',', $params);
			$params = array_filter((array) $params);

			array_walk($params, function($param) use(&$tmp)
			{
				$param = str_ireplace('~', ',', $param);    // revert concatenation delimiter

				[$id, $text] = explode('|', preg_replace('/^(\d+):\{/', '$1|{', $param));

				try
				{
					$text = json_decode((string) $text, null, 512, JSON_THROW_ON_ERROR);
				}
				catch (JsonException $e)
				{
					$text = [];
				}

				$tmp[$id] = new Registry($text);
			});

			// Inject static technical parameters (that kind of params a user cannot edit)
			array_filter($statTechParams, function($statTechParam, $i) use(&$tmp)
			{
				$tmp[$i] = new Registry($statTechParam);
			}, ARRAY_FILTER_USE_BOTH);

			// Sort array keys
			ksort($tmp);

			$tmp = array_filter($tmp);

			$this->tech_params = array_filter($tmp);
		}
		else
		{
			if (is_null($params))
			{
				$this->tech_params = [];
			}
		}

		return $this;
	}

	/**
	 * Returns the whole configuration object or, if given, a specific configuration parameter
	 *
	 * @param  string|null $parameter A specific configuration parameter
	 */
	public function getConfig(string $parameter = null)
	{
		return isset($parameter) ? $this->config->get($parameter) : $this->config;
	}
}
