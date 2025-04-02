<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use  \Entity;
use  \Model\Item as ItemModel;

//use  \User;

/**
 * Class description
 */
class Contact extends ItemModel
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
	 * @param   int $itemID
	 *
	 * @return  \ \Entity\Contact
	 */
	public function getItem(int $itemID) : Entity\Contact
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$className = basename(str_replace('\\', '/', __CLASS__));

		//@note - until proper implementation we return from here.
//		return $this->getVcard($itemID);
		return Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind( [] );

		/*$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				$this->getInstance('contacts', ['language' => $this->language])->getList(
					[
						'contID' => $itemID
					]
				),
				$itemID
			);
		}

		return (is_a($itemID, ' \Entity\Contact')
			? $itemID
			: (is_a($row, ' \Entity\Contact')
				? $row
				: (is_array($row)
					? Entity::getInstance('contact', ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance('contact', ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);*/
	}

	/*public function getVcard(string $hash) : Entity\Contact
	{
		// TODO
	}*/
}
