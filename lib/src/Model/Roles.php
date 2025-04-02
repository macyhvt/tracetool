<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use function array_key_exists;
use function is_null;

/**
 * Class description
 */
class Roles extends ListModel
{
	protected $tableName = 'role';

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
	 * @param   null $roleID
	 *
	 * @return  array
	 *
	 * @todo    refactor to handle function arguments
	 *
	 * @uses    {@link \Symfony\Component\String\Inflector\EnglishInflector}
	 */
	public function getList($roleID = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
//		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		$roleID = (is_null($roleID) ? $roleID : (int) $roleID);

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('roles', 'r'))
		->select(
			$db->qn([
				'r.roleID',
				'r.abbreviation',
				'r.name',
				'r.blocked'
			])
		);

		if (!is_null($roleID))
		{
			$query->where($db->qn('r.roleID') . ' = ' . (int) $roleID);
		}

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				// Translate language name.
				if (array_key_exists('name', $row))
				{
					$row['name'] = (strpos($row['name'], 'COM_FTK_ROLE_') === 0
						? Text::translate(ArrayHelper::getValue($row, 'name', null, 'STRING'), $this->language)
						: $row['name']);
				}

				$rows[$row['roleID']] = $row;
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
