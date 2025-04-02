<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Utilities\ArrayHelper;
use Nematrack\Factory;
use Nematrack\Model\Item as ItemModel;

/**
 * Class description
 */
class Language extends ItemModel
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

	//@todo - implement
	public function getItem(int $itemID)
	{
		return null;
	}

	public function getLanguageByTag($langTag = '') : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$langTag   = (empty(trim($langTag))) ? $this->get('language', Factory::getConfig()->get('app_language')) : $langTag;

		$languages = $this->getInstance('languages', ['language' => $this->language])->getList();

		return ArrayHelper::getValue($languages, $langTag, [], 'ARRAY');
	}
}
