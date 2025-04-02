<?php
/* define application namespace */
namespace  \Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use  \App;
use  \Messager;
use  \Model\Item as ItemModel;
use  \Text;
use function array_filter;
use function is_null;

/**
 * Class description
 */
class Techparam extends ItemModel
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

	public function existsTechnicalParameter($paramID = null, $lngID = null, $paramName = null, $lang = null) : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query.
		$query = $db->getQuery(true)
			->from($db->qn('techparameters', 'tp'))
			->select(
				$db->qn([
					'tp.paramID',
					'tp.lngID',
					'tp.name',
					'tp.language',
					'tp.created',
					'tp.created_by',
					'tp.modified',
					'tp.modified_by'
				])
			);

		switch (true)
		{
			case (!is_null($paramID) && !is_null($paramName) && !is_null($lngID)) :
				$query
					->where($db->qn('tp.paramID') . ' = ' . intval($paramID))
					->where($db->qn('tp.lngID')   . ' = ' . intval($lngID))
					->where($db->qn('tp.name')    . ' = ' . $db->q(trim($paramName)));
				break;

			case (!is_null($paramName) && !is_null($lngID)) :
				$query
					->where($db->qn('tp.lngID')   . ' = ' . intval($lngID))
					->where($db->qn('tp.name')    . ' = ' . $db->q(trim($paramName)));
				break;

			case (!is_null($paramID) && !is_null($lngID)) :
				$query
					->where($db->qn('tp.paramID') . ' = ' . intval($paramID))
					->where($db->qn('tp.lngID')   . ' = ' . intval($lngID));
				break;

			case (!is_null($paramID)) :
				$query
					->where($db->qn('tp.paramID') . ' = ' . intval($paramID));
				break;

			case (!is_null($lngID)) :
				$query
					->where($db->qn('tp.lngID')   . ' = ' . intval($lngID));
				break;
		}

		// Execute query.
		try
		{
			$rows = [];

			switch (true)
			{
				case (!is_null($lngID) && !is_null($paramID) && !is_null($paramName)) :
					$rows = [$db->setQuery($query)->loadObject()];
					break;

				case (!is_null($lngID) && !is_null($paramID)) :
					$rows = [$db->setQuery($query)->loadObject()];
					break;

				case (!is_null($lngID)                        && !is_null($paramName)) :
					$rows = [$db->setQuery($query)->loadObject()];
					break;

				case (!is_null($lngID)) :
				case (!is_null($paramID)) :
					$rows = $db->setQuery($query)->loadObjectList();
					break;
			}

			$rows = array_filter($rows);
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

	public function addTechnicalParameter($paramID = null, $lngID, $paramName, $lang = null)
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

		$userID = $user->get('userID');

		// Calculate new row ID.
		// W A R N I N G :   As by design the targeted database table has no primary key and thus no AUTO_INCREMENT capability.
		//                   It DOES NOT create a new row ID automatically. Hence, it must be calculated prior insertion.
		if (is_null($paramID))
		{
			$newID = (int) $this->getInstance('techparams', ['language' => $this->language])->getLastInsertID();
			$newID = $newID + 1;
		}
		else
		{
			$newID = $paramID;
		}

		// Build query.
		$query = $db->getQuery(true)
			->insert($db->qn('techparameters'))
			->columns(
				$db->qn([
					'paramID',
					'lngID',
					'name',
					'language',
					'created',
					'created_by'
				])
			)
			->values(implode(',', [
				(int) $newID,
				(int) $lngID,
				$db->q(trim($paramName)),
				$db->q(trim($lang)),
				$db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
				(int) $userID
			]));

		// Execute query.
		try
		{
			$db
				->setQuery($query)
				->execute();

//			$insertID = (int) $db->insertid();	// WARNING: insert_id will be empty if the targeted table has no primary key
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

//			$insertID = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $newID;
	}

	public function updateTechnicalParameter($paramID, $lngID, $paramName, $lang = null)
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

		$userID = $user->get('userID');

		// Build query.
		$query = $db->getQuery(true)
			->update($db->qn('techparameters'))
			->set([
				$db->qn('name')        . ' = ' . $db->q(trim($paramName)),
				$db->qn('modified')    . ' = ' . $db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
				$db->qn('modified_by') . ' = ' . (int) $userID
			])
			->where($db->qn('paramID') . ' = ' . intval($paramID))
			->where($db->qn('lngID')   . ' = ' . (int) $lngID);

		// Execute query.
		try
		{
			$db
				->setQuery($query)
				->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return true;
	}
}
