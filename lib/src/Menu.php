<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */

use function array_key_exists;
use function array_merge;
use function array_walk;
use function time;

defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Class description
 */
class Menu extends App
{
	protected array $items  = [];

	protected array $subs   = [];

	protected $active = null;

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   0.1
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		$this->getItems();
	}

	/**
	 * Add description...
	 *
	 * @return mixed The active menu item
	 */
	public function getActive()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		if (isset($this->active))
		{
			return $this->items[$this->active];
		}

		return null;
	}

	/**
	 * Add description...
	 *
	 * @param $name
	 *
	 * @return mixed Either the active menu item or null
	 */
	public function setActive($name)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$name = trim(mb_strtolower($name));

		if (isset($this->items[$name]))
		{
			$this->active = $name;

			return $this->items[$name];
		}

		return null;
	}

	/**
	 * Add description...
	 *
	 * @return  array
	 */
	public function getItems() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->items;
	}

	/**
	 * Add description...
	 *
	 * @param  $item
	 *
	 * @return array
	 */
	public function getItemsSubs($item) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $item->subs;
	}

	/**
	 * Add description...
	 *
	 * @param   array $options
	 *
	 * @return  string
	 *
	 * @todo - remove outta here! Use layout files and move rendering into class Layout or LayoutHelper or a separate class Renderer
	 */
	public function render(array $options = []) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$items = $this->getItems();
		$html  = [];

		$html[] = '<ul class="navbar-nav' . (array_key_exists('class', $options) ? ' ' . $options['class'] : '') . '" ' . (array_key_exists('id', $options) ? ' id="' . $options['id'] . '"' : '') . '>';

		array_walk($items, function($item) use(&$html, &$options)
		{
			if ($item->published && $item->level == '1')
			{
				$subOptions = array_merge($options, ['id' => 'navbarDropdown-' . time(), 'class' => '', 'sub-class' => 'jumbotron-fluid bg-green']);
				$hasSubs    = !empty($item->subs);

				$html[] = '' .
					'<li class="nav-item' .
					(array_key_exists('item-class', $options) ? ' ' . $options['item-class'] : '') .
					( $item->active ? ' active' : '') .
					(($item->active && $hasSubs) ? ' parent' : '') .
					($hasSubs ? ' dropdown' : '') .
					'">' .
					'<a href="' . ($hasSubs ? 'javascript:void(0)' : $item->path) . '"' .
					' class="nav-link' . ($hasSubs ? ' dropdown-toggle' : ' ') . '"' .
					($hasSubs ? ' id="' . ($subOptions['id']) . '"' .
						' role="button"' .
						' data-toggle="dropdown"' .
						' aria-haspopup="true"' .
						' aria-expanded="false" ' : '') .
					' title="' . $item->title . '"' .
					' aria-label="' . $item->title . '"' .
					'>' . $item->text . '</a>' .
					($hasSubs ? $this->renderSubs($item, $subOptions) : '<!-- parent without children -->') .
					'</li>';
			}
		});

		$html[] = '</ul>';

		return implode('', $html);
	}

	/**
	 * Add description...
	 *
	 * @param         $item
	 * @param   array $options
	 *
	 * @return string
	 *
	 * @todo - remove outta here! Use layout files and move rendering into class Layout or LayoutHelper or a separate class Renderer
	 */
	public function renderSubs($item, $options = []) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$subs = $this->getItemsSubs($item);
		$html = [];

		$html[] = '<ul class="nav-sub dropdown-menu border-0 m-0 p-0' . (array_key_exists('sub-class', $options) ? ' ' . $options['sub-class'] : '') . '" aria-labelledby="' . ($options['id']) . '">';

		array_walk($subs, function($sub) use(&$item, &$html, &$options)
		{
			if ($sub->published && $sub->level == $item->level + 1)
			{
				$html[] = '' .
					'<li class="nav-item nav-sub-item dropdown-item' . (array_key_exists('sub-item-class', $options) ? ' ' . $options['sub-item-class'] : '') .
					($sub->active ? ' active' : '') . '" ' .
					'>' .
					'<a href="' . $sub->path . '" class="nav-link cat-link' . (' color-light w-100 pl-5') . '" title="' . $sub->title . '">' . $sub->text . '</a>' .
					'</li>';
			}
		});

		$html[] = '</ul>';

		return implode('', $html);
	}
}
