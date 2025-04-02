<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Access;
use Nematrack\App;
use Nematrack\Crypto;
use Nematrack\Entity;
use Nematrack\Messager;
use Nematrack\Model\Item as ItemModel;
use Nematrack\Text;
use function is_a;
use function is_array;

/**
 * Class description
 */
class Lot extends ItemModel
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
	 * @return  \Nematrack\Entity\Lot
	 */
	public function getItem(int $itemID) : Entity\Lot
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = null;

		if ($itemID > 0)
		{
			$row = ArrayHelper::getValue(
				(array) $this->getInstance('lots', ['language' => $this->language])->getList($itemID),
				$itemID
			);
		}

		$className = basename(str_replace('\\', '/', __CLASS__));

		$row = (is_a($itemID, sprintf('Nematrack\Entity\%s', $className))
			? $itemID
			: (is_a($row, sprintf('Nematrack\Entity\%s', $className))
				? $row
				: (is_array($row)
					? Entity::getInstance($className, ['id' => $itemID, 'language' => $this->get('language')])->bind($row)
					// fall back to an empty item
					: Entity::getInstance($className, ['id' => null,    'language' => $this->get('language')])
				  )
			  )
		);

		// For non-empty item fetch additional data.
		if ($itemID = $row->get('lotID')) {}

		return $row;
	}

	public function getLotByNumber(string $lotNumber) :? Entity\Lot
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$row = [];

		// Get current user object.
		$user = App::getAppUser();

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();
		/* Override the default max. length limitation of the GROUP_CONCAT command, which is 1024.
		 * It's limited only by the unsigned word length of the platform, which is:
		 *    2^32-1 (2.147.483.648) on a 32-bit platform and
		 *    2^64-1 (9.223.372.036.854.775.808) on a 64-bit platform.
		 */
		$db->setQuery('SET SESSION group_concat_max_len = 100000')->execute();

		// Build query.
		$query = $db->getQuery(true)
		->from($db->qn('lots', 'l'))
		->join('INNER', $db->qn('articles') . ' AS ' . $db->qn('a')  . ' ON ' . $db->qn('l.artID')   . ' = ' . $db->qn('a.artID'))
		->join('INNER', $db->qn('lot_part') . ' AS ' . $db->qn('lp') . ' ON ' . $db->qn('l.lotID')   . ' = ' . $db->qn('lp.lotID'))
		->join('INNER', $db->qn('parts')    . ' AS ' . $db->qn('p')  . ' ON ' . $db->qn('lp.partID') . ' = ' . $db->qn('p.partID'))
		->select($db->qn('a.number') . ' AS ' . $db->qn('type'))
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
		)
		->select("CONCAT(
			'{',
				GROUP_CONCAT(
					CONCAT(
					'\"', `lp`.`partID`, '\"',
					':',
						CONCAT(
						'{',
							CONCAT('\"partID\"',       ':', '\"', `p`.`partID`,       '\",'),
							CONCAT('\"artID\"',        ':', '\"', `p`.`artID`,        '\",'),
							CONCAT('\"blocked\"',      ':', '\"', `p`.`blocked`,      '\",'),
							CONCAT('\"trackingcode\"', ':', '\"', `p`.`trackingcode`, '\"'),
						'}')
					)
				),
			'}') AS `parts`"
		)
		->where($db->qn('l.number') . ' = ' . $db->q($lotNumber = trim($lotNumber)));

		// Only users with higher privileges must be allowed to see blocked items.
		if (!is_a($user, 'Nematrack\Entity\User') || (is_a($user, 'Nematrack\Entity\User') && ($user->getFlags() < Access\User::ROLE_PROGRAMMER)))
		{
			$query
			->where($db->qn('l.blocked') . ' = ' . $db->q('0'));
		}

		// Execute query.
		try
		{
			$row = $db->setQuery($query)->loadAssoc();

			$row = Entity::getInstance('lot', ['id' => $row['lotID'], 'language' => $this->language])->bind($row);
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$row = null;
		}

		// Lot found. Return.
		if ($row->get('lotID'))
		{
			return $row;
		}

		// Lot NOT found. Try similar search and suggest found result(s).

		$query
		->clear('where')
		->where('LOWER(' . $db->qn('l.number') . ') LIKE LOWER("%' . $db->escape(mb_substr($lotNumber, 3, strlen($lotNumber) - 6)) . '%")');

		// Execute query.
		try
		{
			$rows = $db->setQuery($query)->loadAssocList();

			$row  = array_shift($rows);

			$row  = Entity::getInstance('lot', ['id' => $row['lotID'], 'language' => $this->language])->bind($row);

			// Show notification to the user that this might not be the requested item.
			if ($row->get('number'))
			{
				Messager::setMessage([
					'type' => 'notice',
					'text' => sprintf('%s<br><br>%s',
						sprintf(Text::translate('COM_FTK_HINT_LOT_WITH_NUMBER_X_NOT_FOUND_TEXT', $this->language), $lotNumber),
						Text::translate('COM_FTK_HINT_LOT_WITH_ALTERNATIVE_NUMBER_FOUND_TEXT', $this->language)
					)
				]);
			}
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$row = null;
		}

		return $row;
	}

	public function addLot(int $artID)
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

		/*// FIXME
		// Validate userID from SESSION equals current form editor's userID
		if ((int) $userID !== (int) $formData->user)
		{
			Messager::setMessage([
				'type' => 'warning',
				'text' => sprintf('%s: %s %s %s',
					Text::translate('COM_FTK_ERROR_APPLICATION_SUSPECTED_MANIPULATION_TEXT', $this->language),
					Text::translate('COM_FTK_ERROR_APPLICATION_USER_ID_MISMATCH_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_ACTION_ABORTED_TEXT', $this->language),
					Text::translate('COM_FTK_SYSTEM_MESSAGE_INCIDENT_IS_REPORTED_TEXT', $this->language)
				)
			]);

			return false;
		}*/

		// Build query
		$query = $db->getQuery(true)
		->insert($db->qn('lots'))
		->columns(
			$db->qn([
				'artID',
				'number',
				'created',
				'created_by'
			])
		)
		->values(implode(',', [
			$artID,
			$db->q(Crypto::generateAlNum(50)),
			$db->q((new DateTime('NOW', new DateTimeZone(FTKRULE_TIMEZONE)))->format('Y-m-d H:i:s')),
			(int) $userID
		]));

		// Execute query.
		try
		{
			$db
			->setQuery($query)
			->execute();

			$insertID = (int) $db->insertid();	// WARNING: insert_id will be empty if the targeted table has no primary key
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$insertID = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $insertID;
	}

	//@todo - ensure a Lot can be updated and implement or delete
	public function updateLot($lot)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return null;
	}

	public function deleteLot(int $lotID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Can this user delete content?
		if (!$this->userCanDelete())
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_USER_NOT_ALLOWED_TO_DELETE_TEXT', $this->language)
			]);

			return false;
		}

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Load lot from db first. This not only prevents us from unnecessary function calls, but it serves us further article data required to call the files deletion function below.
		$lot = $this->getItem($lotID);

		if (!is_a($lot, 'Nematrack\Entity\Lot') || !$lot->get('lotID'))
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_LOT_COULD_NOT_BE_FOUND_TEXT', $this->language)
			]);

			return -1;
		}

		// Is this user allowed to delete lots at all?
		if (!$this->canDeleteLot($lotID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Check for entities depending on this lot.
		if (!$this->lotIsDeletable($lotID))
		{
			return false;	// Messages will be set by the function called.
		}

		// Build query.
		$query = $db->getQuery(true)
		->delete($db->qn('lots'))
		->where($db->qn('lotID') . ' = ' . $lotID);

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

			return false;
		}

		// Reset AUTO_INCREMENT count.
		try
		{
			$db
			->setQuery('ALTER TABLE `lots` AUTO_INCREMENT = 1')
			->execute();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			return false;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $lotID;
	}

	protected function existsLot(int $lotID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init shorthand to database object.
		$db = $this->db;

		/* Force UTF-8 encoding for proper display of german Umlaute
		 * see: http://www.sebastianviereck.de/mysql-php-umlaute-sonderzeichen-utf8-iso/
		 */
		$db->setQuery('SET NAMES utf8')->execute();
		$db->setQuery('SET CHARACTER SET utf8')->execute();

		// Build query
		$query = $db->getQuery(true)
		->select($db->qn('lotID'))
		->from($db->qn('lots'))
		->where($db->qn('lotID') . ' = ' . $lotID);

		// Execute query.
		try
		{
			$rs = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate($e->getMessage(), $this->language)
			]);

			$rs = null;
		}

		// Close connection.
		$this->closeDatabaseConnection();

		return $rs > 0;
	}

	//@todo - implement
	protected function canDeleteLot(int $lotID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// FIXME - not yet implemented');

		return true;
	}

	//@todo - implement
	protected function lotIsDeletable(int $lotID)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		die('// FIXME - not yet implemented');

		return true;
	}
}
