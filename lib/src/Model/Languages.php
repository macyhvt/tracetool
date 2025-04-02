<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Messager;
use  \Model\Lizt as ListModel;
use  \Text;
use function array_key_exists;

/**
 * Class description
 */
class Languages extends ListModel
{
	protected $tableName = 'language';

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
	 * Returns a list of items filtered by user access rights.
	 *
	 * @return  array
	 *
	 * @uses   {@link \Symfony\Component\String\Inflector\EnglishInflector}
	 */
	public function getList() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user   = App::getAppUser();
		$userID = $user->get('userID');

		// Get additional function args.
		$args   = func_get_args();
		$args   = (array) array_shift($args);

		// There may be arguments for this function.
		$filter   = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$filter   = (is_null($filter)) ? null : (int) $filter;
		$onlyTags = ArrayHelper::getValue($args, 'onlyTags', false, 'BOOLEAN');

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		try
		{
			// Build query.
			$query = $db->getQuery(true)
			->from($db->qn('languages', 'l'))
			->order($db->qn('l.tag'));

			if ($onlyTags)
			{
				$query
				->select(
					$db->qn([
						'l.lngID',
						'l.tag'
					])
				);
			}
			else
			{
				$query
				->select(
					$db->qn([
						'l.lngID',
						'l.tag',
						'l.name'
					])
				);
			}

			// Apply filter.
			switch (true)
			{
				case ($filter == Lizt::FILTER_ACTIVE) :
					$query
					->where($db->qn('l.archived') . ' = ' . $db->q('0'))
					->where($db->qn('l.blocked')  . ' = ' . $db->q('0'))
					->where($db->qn('l.trashed')  . ' = ' . $db->q('0'));
				break;

				/*case ($filter == Lizt::FILTER_ARCHIVED) :
					$query
					->where($db->qn('l.archived') . ' = ' . $db->q('1'))
					->where($db->qn('l.trashed')  . ' = ' . $db->q('0'));
				break;*/

				case ($filter == Lizt::FILTER_LOCKED) :
					$query
					->where($db->qn('l.blocked')  . ' = ' . $db->q('1'))
					->where($db->qn('l.trashed')  . ' = ' . $db->q('0'));
				break;

				case ($filter == Lizt::FILTER_DELETED) :
					$query
					->where($db->qn('l.trashed')  . ' = ' . $db->q('1'));
				break;

				/*case ($filter == Lizt::FILTER_ALL) :
				default :
					$states = ['0','1'];

					$query
					->where($db->qn('l.archived')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('l.blocked') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->andWhere($db->qn('l.trashed') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					;
				break;*/

				/*default :
					$states = $user->getFlags() <= Access\User::ROLE_ADMINISTRATOR ? ['0'] : ['0','1'];

					$query
					->where($db->qn('l.archived') . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('l.blocked')  . ' IN(' . implode(',', $db->q( $states) ) . ')')
					->where($db->qn('l.trashed')  . ' IN(' . implode(',', $db->q( $states) ) . ')');*/
			}

			// Execute query.
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Translate language name.
				if (array_key_exists('name', $row))
				{
					$row['name'] = (strpos($row['name'], 'COM_FTK_LANGUAGE_') === 0
						? Text::translate(ArrayHelper::getValue($row, 'name', null, 'STRING'), $this->language)
						: $row['name']);
				}

				// Add link text language key.
				if (array_key_exists('tag', $row))
				{
					$row['link'] = [
						'text' => Text::untranslate(mb_strtoupper(sprintf('COM_FTK_LINK_LANG_%s_TEXT', $row['tag'])), 'de')
					];
				}

				$rows[$row['tag']] = $row;
			}
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
