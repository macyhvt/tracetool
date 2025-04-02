<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Nematrack\Access;
use Nematrack\App;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use function is_a;
use function is_null;

/**
 * Class description
 */
class Lots extends ListModel
{
	protected $tableName = 'lot';

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
	 * @param   null $lotID
	 *
	 * @return  array
	 *
	 * @uses    {@link \Symfony\Component\String\Inflector\EnglishInflector}
	 *
	 * @todo    refactor to handle variable function arguments
	 */
	public function getList($lotID = null) : array
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

		$lotID = (is_null($lotID) ? $lotID : (int) $lotID);

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('lots', 'l'))
		->select(
			$db->qn([
				'l.lotID',
				'l.number',
				'l.artID',
				'l.blocked',
				'l.blockDate',
				'l.blocked_by',
				'l.created',
				'l.created_by',
				'l.modified',
				'l.modified_by'
			])
		);

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('l.blocked') . ' = ' . $db->q('0'));
		}

		if (!is_null($lotID))
		{
			$query
			->where($db->qn('l.lotID') . ' = ' . (int) $lotID);
		}

		$query
		->group($db->qn('l.lotID'));

		// Execute query.
		try
		{
			$rows = [];

			$rs = $db->setQuery($query)->loadAssocList();

			foreach ($rs as $row)
			{
				$rows[$row['lotID']] = $row;
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
