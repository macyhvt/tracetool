<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access;
use Nematrack\App;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use function array_key_exists;
use function array_map;
use function is_a;

/**
 * Class description
 */
class Groups extends ListModel
{
	protected $tableName = 'group';

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

	public function getList(array $groupIDs = []) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Sanitize input.
		$groupIDs = array_map('intval', $groupIDs);

		// Build query.
		$query = $db->getQuery(true)
		->select(
			$db->qn([
				'g.groupID',
				'g.name',
				'g.flag',
				'g.blocked'
			])
		)
		->from($db->qn('usergroups', 'g'));

		if (count($groupIDs))
		{
			$query
			->where($db->qn('g.groupID') . ' IN(' . implode(',', $groupIDs) . ')');
		}

		// Skip group Superuser for non-Superusers.
		// NOTE:  When a guest user logs in, there is no user in Session.
		//        The user being logged in is read from the database identified by its login credentials
		//        and it's groups must be loaded to be assigned. Thus, the query must not be limited.
		if (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_SUPERUSER))
		{
			$sub = $db->getQuery(true)
			->select('MAX(' . $db->qn('groupID') . ')')
			->from($db->qn('usergroups'));

			$query
			->where($db->qn('g.groupID') . ' <> (' . $sub . ')');
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
					$row['name'] = (strpos($row['name'], 'COM_FTK_GROUP_') === 0
						? Text::translate(ArrayHelper::getValue($row, 'name', null, 'STRING'), $this->language)
						: $row['name']);
				}

				$rows[$row['groupID']] = $row;
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
