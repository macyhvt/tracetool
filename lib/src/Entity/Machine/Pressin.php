<?php
/* define application namespace */
namespace Nematrack\Entity\Machine;

/* no direct script access */

use Exception;
use Nematrack\Entity;

defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Class description
 */
class Pressin extends Entity
{
	/**
	 * @var    string  The unique tracking code.
	 * @since  2.8
	 */
	protected $code = null;

	/**
	 * @var    string  The article name.
	 * @since  2.8
	 */
	protected $article = null;

	/**
	 * @var    String  A unique abbreviation.
	 * @since  2.8
	 */
	protected $process = null;

	/**
	 * @var    string  The press-in machine name.
	 * @since  2.8
	 */
	protected $machine = null;

	/**
	 * @var    string  The press-in step config.
	 * @since  2.8
	 */
	protected $config = null;

	/**
	 * @var    string  The full machine operator name.
	 * @since  2.8
	 */
	protected $operator = null;

	/**
	 * @var    string  The full organisation name.
	 * @since  2.8
	 */
	protected $plant = null;

	/**
	 * @var    int  The number of the press-fit (a part may have to pass more than 1 press-in step).
	 * @since  2.8
	 */
	protected $pressFit = null;

	/**
	 * @var    string  The vendor's press-fit batch number.
	 * @since  2.8
	 */
	protected $batch = null;

	/**
	 * @var    string  The physical quantity to be measured like e.g. Force or Temparture.
	 * @since  2.8
	 */
	protected $measurement = null;

	/**
	 * @var    string  The physical quantity's unit like e.g. N(ewton) or Deg(ree)
	 * @since  2.8
	 */
	protected $unit = null;

	/**
	 * @var    array  The processed log data's key information.
	 * @since  2.8
	 */
	protected array $analysis = [];   // the measuring data analysis like it was done in Excel

	/**
	 * @var    array  The processed log data's tracking information.
	 * @since  2.8
	 */
	protected array $tracking = [];

	/**
	 * @var    array  The actual physical quantity's measured data logged by the machine.
	 * @since  2.8
	 */
	protected array $measuredData = [];

	/**
	 * @var    array
	 * @since  2.8
	 */
	protected array $measuringPointsMap = [];


	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		$this->measuringPointsMap = [
			'mnt' => ['F', 'S'], // F = force, S = distance
			'scf' => ['F', 'S'], // F = force, S = distance
		];

		$this->analysis = [
			'DistanceMax' => null,
			'Fmax'        => null,
			'FmaxGLMW5'   => null,  // where GLMW means "moving average (gleitender Durchschnitt)" and  5 means "calculated over 5 values"
			'FmaxGLMW20'  => null,  // where GLMW means "moving average (gleitender Durchschnitt)" and 20 means "calculated over 5 values"
			'Similarity'  => null   // a percentage value to which the moving average value is related
		];

		$this->tracking = [
			'F' => null,    // will be the calculated value for the "force" parameter (F is physical formula symbol)
			'S' => null     // will be the calculated value for the "distance" parameter (S is physical formula symbol)
		];
	}

	/**
	 * Function to bind values to instance properties if existing.
	 *
	 * @param   mixed $data  Array or Object holding the values to bind.
	 *
	 * @return  Pressin object to support chaining
	 *
	 * @throws  Exception
	 */
	public function bind(array $data = []) : Entity
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Let parent do initial bind (common bindings) first.
		parent::bind($data);

		// Force capitalization of item code property value.
		if (isset($this->code))
		{
			$this->code = mb_strtoupper($this->code);
		}

		// Extract pressFix and process from item config property value.
		if (isset($this->config))
		{
			$value    = ltrim($this->config, '-');
			$pressFit = explode('-', $value);

			$this->set('process', mb_strtolower(array_shift($pressFit)));
			$this->set('pressFit', array_pop($pressFit));
		}

		// Fix German Umplauts on item operator property value.
		if (isset($this->operator))
		{
			$value = mb_strtolower($this->operator);

			$value = preg_replace('/ae/', 'ä', $value);
			$value = preg_replace('/oe/', 'ö', $value);
			
			if (!preg_match('/^manuel/i', $value))
			{
				$value = preg_replace('/ue/', 'ü', $value);
			}

			$value = utf8_ucwords($value);

			$this->operator = $value;
		}

		return $this;
	}
}
