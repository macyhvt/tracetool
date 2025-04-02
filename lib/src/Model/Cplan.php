<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Utilities\ArrayHelper;
use  \Entity;
use  \Model\Item as ItemModel;
use function is_a;
use function is_array;

/**
 * Class description
 */
class Cplan extends ItemModel
{
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

	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $itemID  Unique item ID
	 *
	 * @return  \ \Entity\Cplan
	 */
	public function getItem(int $itemID) : Entity\Cplan
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$list = $this->getInstance('cplans', ['language' => $this->language])->getList([
				'filter' => Lizt::FILTER_ALL,
				Entity::getInstance(mb_strtolower(basename(str_replace('\\', '/', __CLASS__))))->getPrimaryKeyName() => $itemID
			]);
			$row  = ArrayHelper::getValue($list, $itemID, Entity::getInstance(mb_strtolower(basename(str_replace('\\', '/', __CLASS__)))));
			/*$row = ArrayHelper::getValue(
				// TODO - refactor function "getList()" to accept multiple args
				$this->getInstance('cplans', ['language' => $this->language])->getList(
					[
						'filter' => Lizt::FILTER_ALL,
						Entity::getInstance(mb_strtolower(basename(str_replace('\\', '/', __CLASS__))))->getPrimaryKeyName() => $itemID
					]
				),
				$itemID
			);*/
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf(' \Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf(' \Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		$id = $row->get($row->getPrimaryKeyName());

		if ($id > 0 && $id == $itemID) {}

		return $row;
	}

	/**
	 * Returns a specific item identified by ID.
	 *
	 * @param   int $procID  Unique process ID
	 *
	 * @return  \ \Entity\Cplan
	 */
	public function getItemByProcessID(int $procID) : Entity\Cplan
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($procID > 0)
		{
			$list = $this->getInstance('cplans', ['language' => $this->language])->getList([
				'filter' => Lizt::FILTER_ALL,
				Entity::getInstance('process')->getPrimaryKeyName() => $procID
			]);
			 $row = ArrayHelper::getValue($list, $procID, Entity::getInstance(mb_strtolower(basename(str_replace('\\', '/', __CLASS__)))));
			/*$row = ArrayHelper::getValue(
				// TODO - refactor function "getList()" to accept multiple args
				$this->getInstance('cplans', ['language' => $this->language])->getList(
					[
						'filter' => Lizt::FILTER_ALL,
						Entity::getInstance(mb_strtolower(basename(str_replace('\\', '/', __CLASS__))))->getPrimaryKeyName() => $itemID
					]
				),
				$itemID
			);*/
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($procID, sprintf(' \Entity\%s', $className))
			? $procID
			: (is_a($row, sprintf(' \Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $procID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				)
			)
		);

		// For non-empty item fetch additional data.
		$id = $row->get($row->getPrimaryKeyName());

		if ($id > 0 && $id == $procID) {}

		return $row;
	}
}
