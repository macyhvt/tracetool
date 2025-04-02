<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\UserHelper;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View\Lizt as ListView;
use function array_diff_key;
use function is_array;
use function is_null;
use function is_numeric;
use function is_object;
use function property_exists;

// Required to call <code>{@link \Nematrack\View::getReferer()}</code>

/**
 * Class description
 */
class Parts extends ListView
{
	use \Nematrack\Traits\View\Parts;

	/**
	 * {@inheritdoc}
	 * @see Lizt::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);

		// Don't load display data when there's POST data to process.
		if (count($_POST))
		{
			return;
		}

		// Access control. Only registered and authenticated users can view content.
		if (!is_a($this->user, 'Nematrack\Entity\User'))
		{
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// TODO - adapt conditional loading as is implemented in list view
		// $list = $this->model->getList();
		$list = [];

		// Assign ref to loaded list data.
		$this->list = $list;
	}

	/**
	 * Returns search results
	 *
	 * @param   string|null $q
	 * @param   string|null $redirect
	 *
	 * @return  array|null
	 *
	 * @throws  Exception
	 */
	public function doSearch__OLD(string $q = null, string $redirect = null) : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post     = $this->input->post->getArray();
		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = (!empty($return)) ? new Uri($return) : new Uri(static::getReferer());
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Execute search.
		if ($this->user->isGuest() || $this->user->isSupplier())	// TODO - after merging both search functions in the model get rid of this and grant usage on findPartsNEW/() to everybody
		{
			$status = $this->model->findPartsOLD(htmlentities($q));
		}
		else
		{
			$status = $this->model->findParts([
				'partID' => $this->input->getInt('ptid', $this->input->getInt('partID')),
				'search' => $this->input->post->getString('searchword') ?? $this->input->getString('searchword'),
				'filter' => $this->input->getString('filter', (string) \Nematrack\Model\Lizt::FILTER_ALL)
			]);
		}

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			$redirect = new Uri($this->getRoute());
			$redirect->setVar('at', $this->input->getInt('at'));

			header('Location: ' . $redirect->toString());
			exit;
		}
		else
		{
			if (is_array($status) || is_object($status))
			{
				$status = (array) $status;	// force variable type

				// 1 item found.
				if (count($status) == 1)
				{
					// Init vars.
					$at = $this->input->getCmd('at');
					$at = (is_numeric($at) ? intval($at) : null);
					$af = $this->input->getCmd('af');
					$af = (is_numeric($af) ? intval($af) : null);
					$as = $this->input->getCmd('as');
					$as = (is_numeric($as) ? intval($as) : null);

					$interruptAT     = false;
					$partSearched    = $typeOfPartSearched = null;
					$partPrevious    = $typeOfPartPrevious = null;
					$searchResult    = (array) current($status);

					// Get the process this user has previously tracked to decide
					// to which process automatically scroll to.
					$lastUserProcess = UserHelper::getPreviouslyTrackedProcess($this->user);

					/* If the time when this tracking was created occurred more than 8 hours ago, then
					 * the user's shift has most likely ended and $lastUserProcessID becomes obsolete.
					 * The user's previous tracking is considered within its current shift only to
					 * prevent strange behaviour.
					 */
					$tracked   = date_create(ArrayHelper::getValue($lastUserProcess, 'timestamp', 'NOW', 'DATE'));
					$now       = date_create('NOW');
					$delta     = $tracked->diff($now);

					// Offset in hours the last tracking entry's process ID is valid.
					$maxOffset = ($at == '1' ? 1 : 4);

					// Calculate the preveously tracked process' ID.
					$lastUserProcessID = ($delta->h <= $maxOffset ? ArrayHelper::getValue($lastUserProcess, 'procID', 0, 'INT') : null);

					// Get search result.
					if ($at == '1')
					{
						$partSearched       = $this->model->getInstance('part', ['language' => $this->language])->getItem((int) ArrayHelper::getValue($searchResult, 'partID', 0, 'INT') );
						$typeOfPartSearched = $partSearched->get('artID');
					}

					/** Calculate AutoTrack-, AutoFill- and AutoSubmit-flags. **/

					/* // Interrupt AutoTrack when there is no previous tracking to be cloned.
					if (!$lastUserProcessID)
					{
						$interruptAT = true;
						$lastUserProcessID = null;	// Prevent URI hash generation

						// Message to render when in AutoTrack mode.
						if ($at == '1')
						{
							Messager::setMessage([
								'type' => 'info',
								'text' => sprintf('%s<br>%s<br>%s',
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT',       $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_DATA_EMPTY_TEXT',    $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_DATA_REQUIRED_TEXT', $this->language)
								)
							]);
						}
						// Message to render otherwise.
						else
						{
							Messager::setMessage([
								'type' => 'info',
								// TODO - translate
								'text' => Text::translate('Can\'t remember your last tracking.<br>It is way too long ago.', $this->language)
							]);
						}

						$at = '0';
						$af = '0';
						$as = '0';
					} */

					// Interrupt AutoTrack when search result is blocked.
					if ($at == '1' && !$interruptAT && $partSearched->get('blocked') == '1')
					{
						$interruptAT = true;
						$lastUserProcessID = null;	// Prevent URI hash generation

						Messager::setMessage([
							'type' => 'info',
							'text' => sprintf('%s<br>%s',
								Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT', $this->language),
								Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_IS_BLOCKED_TEXT',   $this->language)
							)
						]);

						$at = '0';
						$af = '0';
						$as = '0';
					}

					// Interrupt AutoTrack when search result is a bad part.
					if ($at == '1' && !$interruptAT && $partSearched->isBad())
					{
						$interruptAT = true;
						$lastUserProcessID = null;	// Prevent URI hash generation

						Messager::setMessage([
							'type' => 'info',
							'text' => sprintf('%s<br>%s',
								Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT', $this->language),
								Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_IS_BAD_TEXT',       $this->language)
							)
						]);

						$at = '0';
						$af = '0';
						$as = '0';
					}

					// Interrupt AutoTrack when type of search result does not equal
					// type of previously edited part.
					if ($at == '1' && !$interruptAT && $lastUserProcessID)
					{
						// Load part previously edited.
						$partPrevious       = $this->model->getInstance('part', ['language' => $this->language])->getItem((int) ArrayHelper::getValue($lastUserProcess, 'partID', 0, 'INT') );
						$typeOfPartPrevious = $partPrevious->get('artID');

						// Search result is a different article than the previously edited part.
						// Disable AutoTrack.
						if ($typeOfPartSearched !== $typeOfPartPrevious)
						{
							$interruptAT = true;
							$lastUserProcessID = null;	// Prevent URI hash generation

							Messager::setMessage([
								'type' => 'warning',
								'text' => sprintf('%s<br>%s',
									Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_IS_DIFFERENT_TEXT', $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT',    $this->language)
								)
							]);

							$at = '0';
							$af = '0';
							$as = '0';
						}
					}

					// Interrupt AutoTrack when if there's an inconsitency in the search results
					// process status.
					if ($at == '1' && !$interruptAT && $lastUserProcessID)
					{
						// $at = '1';
						$af = '1';
						$as = '1';

						// Search result type is equal to previously edited part type.
						// Check for potential inconsistencies in processing state.
						if ($typeOfPartSearched === $typeOfPartPrevious)
						{
							// Filter params of part previously edited.
							$partPreviousProcesses  = (array) $partPrevious->get('processes');
							$partPreviousTechParams = (array) $partPrevious->get('trackingData');

							// When comparing each part's processes stack we must ignore the process going to be AutoTracked
							// because this cannot already be filled.
							foreach ($partPreviousProcesses as $pid => $process)
							{
								// Skip previously edited process.
								// It must not be part of the processes chains comparison.
								if ($pid == $lastUserProcessID)
								{
									unset($partPreviousProcesses[$pid]);
									continue;
								}

								$techParams = ArrayHelper::getValue($partPreviousTechParams, $pid);

								if (empty($techParams))
								{
									unset($partPreviousProcesses[$pid]);
									continue;
								}

								// Associate tech params to process id for next step (comparison);
								$partPreviousProcesses[$pid] = $techParams;
							}

							// Free memory.
							unset($partPreviousTechParams);

							ksort($partPreviousProcesses);

							// Filter params of part searched for.
							$partSearchedProcesses  = (array) $partSearched->get('processes');
							$partSearchedTechParams = (array) $partSearched->get('trackingData');

							// When comparing each part's processes stack we must ignore the process going to be AutoTracked
							// because this cannot already be filled.
							foreach ($partSearchedProcesses as $pid => $process)
							{
								// Skip previously edited process.
								// It must not be part of the processes chains comparison.
								if ($pid == $lastUserProcessID)
								{
									unset($partSearchedProcesses[$pid]);
									continue;
								}

								$techParams = ArrayHelper::getValue($partSearchedTechParams, $pid);

								if (empty($techParams))
								{
									unset($partSearchedProcesses[$pid]);
									continue;
								}

								// Associate tech params to process id for next step (comparison);
								$partSearchedProcesses[$pid] = $techParams;
							}

							// Free memory.
							unset($partSearchedTechParams);

							ksort($partSearchedProcesses);

							// Compare \array_keys (pids) of both arrays.
							// If they are not identical then AutoTrack must be stopped,
							// because there's something strange to be inspected and maybe fixed.
							$diff1 = array_diff_key($partPreviousProcesses, $partSearchedProcesses);
							$diff2 = array_diff_key($partSearchedProcesses, $partPreviousProcesses);

							// Free memory.
							unset($partPreviousProcesses);
							unset($partSearchedProcesses);
							unset($diff1);
							unset($diff2);
						}
					}

					if ($at == '1' && !$interruptAT && $lastUserProcessID)
					{
						// If either comparison result is not empty, something is strange and must be checked.
						if (!empty($diff1) || !empty($diff2))
						{
							$lastUserProcessID = null;	// Prevent URI hash generation

							Messager::setMessage([
								'type' => 'info',
								'text' => sprintf('%s<br>%s<br>%s',
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT',                 $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_TRACKING_STATUS_IRREGULARITY_TEXT', $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_CHECK_TEXT',                      $this->language)
								)
							]);

							// $at = '0';
							$af = '0';
							$as = '0';
						}
					}

					// Build redirect URI.
					$redirect = new Uri( 'index.php' );
					$redirect->setVar('hl',   $this->language);
					$redirect->setVar('view', 'part');

					if ($at != '1' || $af != '1' || empty($lastUserProcess))
					{
						$redirect->setVar('layout', 'item');
					}
					else
					{
						$redirect->setVar('layout', 'edit');
					}

					$redirect->setVar('ptid', ArrayHelper::getValue($searchResult, 'partID', 0, 'INT'));

					if ($lastUserProcessID)
					{
						$redirect->setVar('pid', $lastUserProcessID);
					}

					// Send "AutoTrack" flag.
					if ($at == '1')
					{
						$redirect->setVar('at', $at);

						// Send "AutoFill" flag + "AutoSubmit" flag.
						if ($af == '1')
						{
							$redirect->setVar('af', $af);
							$redirect->setVar('as', $as);
						}
					}

					if ($lastUserProcessID)
					{
						if ($at != '1' || empty($lastUserProcess))
						{
							$redirect->setFragment('p-' . hash('MD5',   $lastUserProcessID));
						}
						else
						{
							$redirect->setFragment('p-' . hash('CRC32', $lastUserProcessID));
						}
					}

					header('Location: ' . $redirect->toString());
					exit;
				}
			}
		}

		return $status;
	}
	public function doSearch(string $q = null, string $redirect = null) : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post       = $this->input->post->getArray();
		$return     = base64_decode($this->input->post->getBase64('return', ''));
		$searchWord = $this->input->post->getString('searchword') ?? $this->input->getString('searchword');

        //echo $searchWord;exit;
		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);
        //echo "<pre>";print_r($redirect->getPath());exit;
		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{

			$redirect = (!empty($return)) ? new Uri($return) : new Uri(static::getReferer());
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Execute search.
		if ($this->user->isGuest() || $this->user->isSupplier())	// TODO - after merging both search functions in the model get rid of this and grant usage on findPartsNEW/() to everybody
		{
			if ($this->user->isProgrammer()) :
                //echo "helpa";exit;
				die('Execute guest-/supplier-search');
			endif;

			$status = $this->model->findPartsOLD(htmlentities($q));

		}
		else
		{
			switch (true)
			{
				// 8cHGc6mVYkudhR2vYBGnaJqHXu4suzxBGPVvvTYBKXYYEWsgJt (lot number)
				case preg_match('/' . FTKREGEX_LOT_NUMBER . '/', $searchWord) :
					header('Location: index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=lot&lid=' . $searchWord);
					exit;

				// 000-9FF-9FF@SSS.1EJ.CB.00100.000 (used on process slips)
				case preg_match('/' . FTKREGEX_LOT_ITEM_NUMBER . '/', $searchWord) :
					$qPieces    = (array) explode('@', $searchWord);
					$searchWord = current($qPieces);
				break;

				// 0009FF9FF  or  000-9FF-9FF (single part code)
//				default :
			}
            /*echo $this->input->getInt('ptid', $this->input->getInt('partID'));
            echo $searchWord;
            echo $this->input->getString('filter', (string) \Nematrack\Model\Lizt::FILTER_ALL);exit;*/
			$searchParams = [
				'partID' => $this->input->getInt('ptid', $this->input->getInt('partID')),
				'search' => $searchWord,
				'filter' => $this->input->getString('filter', (string) \Nematrack\Model\Lizt::FILTER_ALL)
			];

			$status = $this->model->findParts($searchParams);
           // echo "<pre>";print_r($status);exit;
		}

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			$redirect = new Uri($this->getRoute());
			$redirect->setVar('at', $this->input->getInt('at'));

			header('Location: ' . $redirect->toString());
			exit;
		}
		else
		{
			$redirect->delVar('task');

			if (is_array($status) || is_object($status))
			{
				$status = (array) $status;	// force variable type

				// 1 item found.
				if (count($status) == 1)
				{
					// Init vars.
					$at = $this->input->getCmd('at');
					$at = (is_numeric($at) ? intval($at) : null);
					$af = $this->input->getCmd('af');
					$af = (is_numeric($af) ? intval($af) : null);
					$as = $this->input->getCmd('as');
					$as = (is_numeric($as) ? intval($as) : null);

					$interruptAT     = false;
					$partSearched    = $typeOfPartSearched = null;
					$partPrevious    = $typeOfPartPrevious = null;
					$searchResult    = (array) current($status);

					// Get the process this user has previously tracked to decide
					// to which process automatically scroll to.
					$lastUserProcess = UserHelper::getPreviouslyTrackedProcess($this->user);

					/* If the time when this tracking was created occurred more than 8 hours ago, then
					 * the user's shift has most likely ended and $lastUserProcessID becomes obsolete.
					 * The user's previous tracking is considered within its current shift only to
					 * prevent strange behaviour.
					 */
					$tracked   = date_create(ArrayHelper::getValue($lastUserProcess, 'timestamp', 'NOW', 'DATE'));
					$now       = date_create('NOW');
					$delta     = $tracked->diff($now);

					// Offset in hours the last tracking entry's process ID is valid.
					$maxOffset = ($at == '1' ? 1 : 4);

					// Calculate the preveously tracked process' ID.
					$lastUserProcessID = ($delta->h <= $maxOffset ? ArrayHelper::getValue($lastUserProcess, 'procID', 0, 'INT') : null);

					// Get search result.
					if ($at == '1')
					{
						$partSearched       = $this->model->getInstance('part', ['language' => $this->language])->getItem((int) ArrayHelper::getValue($searchResult, 'partID', 0, 'INT') );
						$typeOfPartSearched = $partSearched->get('artID');
					}

					/** Calculate AutoTrack-, AutoFill- and AutoSubmit-flags. **/

					/* // Interrupt AutoTrack when there is no previous tracking to be cloned.
					if (!$lastUserProcessID)
					{
						$interruptAT = true;
						$lastUserProcessID = null;	// Prevent URI hash generation

						// Message to render when in AutoTrack mode.
						if ($at == '1')
						{
							Messager::setMessage([
								'type' => 'info',
								'text' => sprintf('%s<br>%s<br>%s',
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT',       $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_DATA_EMPTY_TEXT',    $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_DATA_REQUIRED_TEXT', $this->language)
								)
							]);
						}
						// Message to render otherwise.
						else
						{
							Messager::setMessage([
								'type' => 'info',
								// TODO - translate
								'text' => Text::translate('Can\'t remember your last tracking.<br>It is way too long ago.', $this->language)
							]);
						}

						$at = '0';
						$af = '0';
						$as = '0';
					} */

					// Interrupt AutoTrack when search result is blocked.
					if ($at == '1' && !$interruptAT && $partSearched->get('blocked') == '1')
					{
						$interruptAT = true;
						$lastUserProcessID = null;	// Prevent URI hash generation

						Messager::setMessage([
							'type' => 'info',
							'text' => sprintf('%s<br>%s',
								Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT', $this->language),
								Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_IS_BLOCKED_TEXT',   $this->language)
							)
						]);

						$at = '0';
						$af = '0';
						$as = '0';
					}

					// Interrupt AutoTrack when search result is a bad part.
					if ($at == '1' && !$interruptAT && $partSearched->isBad())
					{
						$interruptAT = true;
						$lastUserProcessID = null;	// Prevent URI hash generation

						Messager::setMessage([
							'type' => 'info',
							'text' => sprintf('%s<br>%s',
								Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT', $this->language),
								Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_IS_BAD_TEXT',       $this->language)
							)
						]);

						$at = '0';
						$af = '0';
						$as = '0';
					}

					// Interrupt AutoTrack when type of search result does not equal
					// type of previously edited part.
					if ($at == '1' && !$interruptAT && $lastUserProcessID)
					{
						// Load part previously edited.
						$partPrevious       = $this->model->getInstance('part', ['language' => $this->language])->getItem((int) ArrayHelper::getValue($lastUserProcess, 'partID', 0, 'INT') );
						$typeOfPartPrevious = $partPrevious->get('artID');

						// Search result is a different article than the previously edited part.
						// Disable AutoTrack.
						if ($typeOfPartSearched !== $typeOfPartPrevious)
						{
							$interruptAT = true;
							$lastUserProcessID = null;	// Prevent URI hash generation

							Messager::setMessage([
								'type' => 'warning',
								'text' => sprintf('%s<br>%s',
									Text::translate('COM_FTK_SYSTEM_MESSAGE_ARTICLE_IS_DIFFERENT_TEXT', $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT',    $this->language)
								)
							]);

							$at = '0';
							$af = '0';
							$as = '0';
						}
					}

					// Interrupt AutoTrack when if there's an inconsistency in the search results
					// process status.
					if ($at == '1' && !$interruptAT && $lastUserProcessID)
					{
						// $at = '1';
						$af = '1';
						$as = '1';

						// Search result type is equal to previously edited part type.
						// Check for potential inconsistencies in processing state.
						if ($typeOfPartSearched === $typeOfPartPrevious)
						{
							// Filter params of part previously edited.
							$partPreviousProcesses  = (array) $partPrevious->get('processes');
							$partPreviousTechParams = (array) $partPrevious->get('trackingData');

							// When comparing each part's processes stack we must ignore the process going to be AutoTracked
							// because this cannot already be filled.
							foreach ($partPreviousProcesses as $pid => $process)
							{
								// Skip previously edited process.
								// It must not be part of the processes chains comparison.
								if ($pid == $lastUserProcessID)
								{
									unset($partPreviousProcesses[$pid]);
									continue;
								}

								$techParams = ArrayHelper::getValue($partPreviousTechParams, $pid);

								if (empty($techParams))
								{
									unset($partPreviousProcesses[$pid]);
									continue;
								}

								// Associate tech params to process id for next step (comparison);
								$partPreviousProcesses[$pid] = $techParams;
							}

							// Free memory.
							unset($partPreviousTechParams);

							ksort($partPreviousProcesses);

							// Filter params of part searched for.
							$partSearchedProcesses  = (array) $partSearched->get('processes');
							$partSearchedTechParams = (array) $partSearched->get('trackingData');

							// When comparing each part's processes stack we must ignore the process going to be AutoTracked
							// because this cannot already be filled.
							foreach ($partSearchedProcesses as $pid => $process)
							{
								// Skip previously edited process.
								// It must not be part of the processes chains comparison.
								if ($pid == $lastUserProcessID)
								{
									unset($partSearchedProcesses[$pid]);
									continue;
								}

								$techParams = ArrayHelper::getValue($partSearchedTechParams, $pid);

								if (empty($techParams))
								{
									unset($partSearchedProcesses[$pid]);
									continue;
								}

								// Associate tech params to process id for next step (comparison);
								$partSearchedProcesses[$pid] = $techParams;
							}

							// Free memory.
							unset($partSearchedTechParams);

							ksort($partSearchedProcesses);

							// Compare \array_keys (pids) of both arrays.
							// If they are not identical then AutoTrack must be stopped,
							// because there's something strange to be inspected and maybe fixed.
							$diff1 = array_diff_key($partPreviousProcesses, $partSearchedProcesses);
							$diff2 = array_diff_key($partSearchedProcesses, $partPreviousProcesses);

							// Free memory.
							unset($partPreviousProcesses);
							unset($partSearchedProcesses);
							unset($diff1);
							unset($diff2);
						}
					}

					if ($at == '1' && !$interruptAT && $lastUserProcessID)
					{
						// If either comparison result is not empty, something is strange and must be checked.
						if (!empty($diff1) || !empty($diff2))
						{
							$lastUserProcessID = null;	// Prevent URI hash generation

							Messager::setMessage([
								'type' => 'info',
								'text' => sprintf('%s<br>%s<br>%s',
									Text::translate('COM_FTK_SYSTEM_MESSAGE_AUTOTRACK_STOPPED_TEXT',                 $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_TRACKING_STATUS_IRREGULARITY_TEXT', $this->language),
									Text::translate('COM_FTK_SYSTEM_MESSAGE_PLEASE_CHECK_TEXT',                      $this->language)
								)
							]);

							// $at = '0';
							$af = '0';
							$as = '0';
						}
					}

					// Build redirect URI.
					$redirect = new Uri( 'index.php' );
					$redirect->setVar('hl',   $this->language);
					$redirect->setVar('view', 'part');
                    $redirect->setVar('artid', ArrayHelper::getValue($searchResult, 'artID', 0, 'INT'));

					if ($at != '1' || $af != '1' || empty($lastUserProcess))
					{
						$redirect->setVar('layout', 'item');
					}
					else
					{
						$redirect->setVar('layout', 'edit');
					}

					$redirect->setVar('ptid', ArrayHelper::getValue($searchResult, 'partID', 0, 'INT'));

					if ($lastUserProcessID)
					{
						$redirect->setVar('pid', $lastUserProcessID);
					}

					// Send "AutoTrack" flag.
					if ($at == '1')
					{
						$redirect->setVar('at', $at);

						// Send "AutoFill" flag + "AutoSubmit" flag.
						if ($af == '1')
						{
							$redirect->setVar('af', $af);
							$redirect->setVar('as', $as);
						}
					}

					if ($lastUserProcessID)
					{
						if ($at != '1' || empty($lastUserProcess))
						{
							$redirect->setFragment('p-' . hash('MD5',   $lastUserProcessID));
						}
						else
						{
							$redirect->setFragment('p-' . hash('CRC32', $lastUserProcessID));
						}
					}
                    //print_r($status);exit;
					header('Location: ' . $redirect->toString());
					exit;
				}
			}
		}

		return $status;
	}

    public function mikeSearch(int $aID = null,int $procID = null, string $mpID=null) : ?array
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        //echo "jeie";
        $post       = $this->input->post->getArray();
        $return     = base64_decode($this->input->post->getBase64('return', ''));
        $searchWord = $this->input->post->getString('searchword') ?? $this->input->getString('searchword');

        $aID = $this->input->post->getString('artid') ?? $this->input->getString('artid');
        $proID = $this->input->post->getString('procid') ?? $this->input->getString('procid');
        $mpID = $this->input->post->getString('mp') ?? $this->input->getString('mp');
        $partID = $this->input->post->getString('ptid') ?? $this->input->getString('ptid');
        $lotid = $this->input->post->getString('ptid') ?? $this->input->getString('lotid');

        /*  echo $aID;
          echo $proID;
          echo $mpID;*/



        //echo $this->input->getInt('ptid', $this->input->getInt('partID'));
        $searchParams = [
            'partID' => $this->input->getInt('ptid', $this->input->getInt('partID')),
            'search' => $searchWord
        ];
        $actualMPart   = $this->model->getInstance('part', ['language' => $this->language])->activeArticleMP($aID, $proID);
        //$mpID = 'CUT1DD003';  // Example value, replace this with your actual mpID
        $mpFrequencyScope = null;  // Initialize variable to store the result

        foreach ($actualMPart as $item) {
            if ($item['mp'] === $mpID) {
                $mpFrequencyScope = $item['mpFrequencyScope'];
                break;  // Exit the loop once the match is found
            }
        }


         //echo "<pre>";print_r($actualMPart);
        $status = $this->model->findPrevParts($aID, $proID, $mpID, $partID, $lotid, $mpFrequencyScope);
        //$finlast2 = $this->model->findPrevParts2($aID, $proID, $mpID);
        //$status = array_merge_recursive($finlast, $finlast2);
        // echo "<pre>";print_r($finlast);
        return $status;

    }

	/**
	 * Saves the deletion of an item.
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @todo - migrate to parent model's deletion function like in other list views
	 */
	private function saveDeletion__OFF_MOVED_TO_PARENT(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$post   = $this->input->post->getArray();

		$status = $this->model->getInstance('part', ['language' => $this->language])->deletePart($this->input->post->getInt('ptid', 0));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			// Message set by model
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_DELETED_TEXT', $this->language)
			]);

			$redirect = new Uri( 'index.php?hl=' . $this->language . '&view=part&layout=item&ptid=' . $this->input->post->getInt('ptid', 0) );
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			if ($status != '-1')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_WAS_DELETED_TEXT', $this->language)
				]);
			}

			$redirect = new Uri($this->getRoute());
		}

		$redirect->setVar('at', $this->input->getInt('at'));

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Saves the publishing state of an item.
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @todo - migrate to parent model's deletion function like in other list views
	 */
	private function saveState__OFF_MOVED_TO_PARENT(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$ids         = (array) $this->input->getInt('ptid');
		$state       = $this->input->getInt('state', 0);
		$status      = $this->model->setState($ids, $state);

		// Calculate redirect URL.
		$redirect    = new Uri( !is_null($redirect)
			? $redirect
			: 'index.php?hl=' . $this->language . '&view=part&layout=' . (count($ids) > 1 ? 'list' : 'item&ptid=' . $this->input->getInt('ptid'))
		);
		$redirect->setVar('at', $this->input->getInt('at'));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			// Message set by model
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ITEM_STATE_COULD_NOT_BE_CHANGED_TEXT', $this->language)
			]);

			header('Location: ' . $redirect->toString());
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			if ($status != '-1')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ITEM_STATE_CHANGED_TEXT', $this->language)
				]);
			}

			header('Location: ' . $redirect);
		}

		exit;
	}

	/**
	 * Add description...
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveUnbookedPartsListFilter(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post     = $this->input->post->getArray();
		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		$uid    = $this->input->post->getInt('user', 0);
		$config = ArrayHelper::getValue($post, 'config', [], 'ARRAY');

		// Save data.
		$status = $this->model->getInstance('user', ['language' => $this->language])->updateProfile(
			$uid,
			[
				'uid'     => $uid,
				'profile' => $config
			]
		);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.

			// Error messages are set in model

			// http_response_code('500');
		}
		else
		{
			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
			]);

			// http_response_code('200');

			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}
		}

		// Add the page to return to, that was sent via POST.
		// $redirect->setVar('return', $return);	// Don't set it, when it was loaded into new Uri instead of $redirect, as that would redirect to itself.

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		header('Location: ' . $redirect->toString());
		exit;
	}
}
