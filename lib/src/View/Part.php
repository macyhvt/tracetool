<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Froetek\Coder\Coder;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use  \App;
use  \Helper\UriHelper;
use  \Messager;
use  \Text;
use  \View\Item as ItemView;
use RuntimeException;
use function class_exists;
use function is_a;
use function is_null;
use function property_exists;

/**
 * Class description
 */
class Part extends ItemView
{
	use \ \Traits\View\Part;

	/**
	 * {@inheritdoc}
	 * @see Item::__construct
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
		if (!is_a($this->user, ' \Entity\User'))
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

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \ \Access\User::ROLE_WORKER)
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

		// Get id of item to fetch from the database.
		$id   = $this->input->get->getInt('ptid');

		// Fetch item.
		$item = (isset($id) && $id) ? $this->model->getItem($id, $this->model->get('language')) : null;

		// Access control. Block the attempt to open a non-existing item.
		if (!is_null($id) && !is_object($item))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))), $this->language), $item->get('partID'))
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Prepare item for display.
		if (is_a($item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) && $item->get('trackingcode'))
		{
			/*// Disabled on 2021-10-27 - code moved to {@see \ \Entity\Part::getItem()}
			// This part may be a subcomponent of another part (e.g. a joining part).
			// Fetch all parts where it might have been combined to.
			if ($item->isComponent)
			{
				$item->__set('isComponentOf', []);
			}*/
		}

		// Assign ref to loaded item.
		$this->item = $item;

		// Works, but caches the auth credentials, which is not desired. The information is cached in the global $_SERVER and cannot be unset.
		// see: https://www.php.net/manual/de/features.http-auth.php
		/*if (0 && $this->user->isProgrammer())
		{
			if ($this->input->getCmd('task') === 'approve') :
				if (!$this->input->server->get('PHP_AUTH_USER')) :
					header('WWW-Authenticate: Basic realm="Authentication Test"');
					header('HTTP/1.0 401 Unauthorized');
					echo '<p>Bitte geben Sie Ihre Zugangsdaten ein, um den Prozess freizugeben!</p><a href="javascript:void(0)" onclick="window.history.back()">zur&uuml;ck</a>';
					exit;
				else :
					echo "<p>Hallo {$this->input->server->get('PHP_AUTH_USER')}.</p>";
					echo "<p>Sie gaben {$this->input->server->get('PHP_AUTH_PW')} als Passwort ein.</p>";
					function_exists("apc_clear_cache") ? apc_clear_cache('user')   : null;
					function_exists("apc_clear_cache") ? apc_clear_cache('opcode') : null;
					exit;
				endif;
			endif;
		}*/
	}

	/**
	 * {@inheritdoc}
	 * @see Item::getIdentificationKey
	 */
	public function getIdentificationKey(): string
	{
		return $this->identificationKey = 'ptid';
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = ''): void
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

		$status = $this->model->addPart(
			[
				'form' => $post
			]
		);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.
			$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=add');
			$redirect->setVar('at', $this->input->getInt('at'));

			// Add the page to return to, that was sent via POST.
			$redirect->setVar('return', base64_encode($return));

			// If a URI fragment was sent via POST, set it as URI var.
			$redirect->setFragment($this->input->getString('fragment'));

			// Error messages are set in model

			header('Location: ' . $redirect->toString());
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			// 3rd attempt: If $redirect is still empty, load hardcoded URI into a {@see Joomla\Uri\Uri} object otherwise redirect to $return.
			$redirectToList = (empty($redirect->getPath()))
				// ? new Uri($this->getInstance('parts')->getRoute())
				? new Uri('index.php?hl=' . $this->language . '&view=part&layout=add&return' . base64_encode( basename( ( new Uri($this->getInstance('parts')->getRoute()) )->toString() ) ))
				: $redirect;


			$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=part&layout=item&ptid=' . $status);
			$redirectToItem->setVar('return', base64_encode('return'));
			// $redirectToItem->setVar('return', base64_encode( basename( ( new Uri($this->getInstance('parts')->getRoute()) )->toString() ) ));
			// If a URI fragment was sent via POST, set it as URI var.
			$redirectToItem->setFragment($this->input->getString('fragment'));


// Provide the user with a choice whether to step over to edit the previously created article
$question = Text::translate('Das Teil wurde erstellt.\r\nMöchten Sie es jetzt bearbeiten?', $this->language);
// $question = Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_POST_CREATION_EDIT_TEXT', $this->language);
$script   = <<<JS
			if (confirm("$question") === true) {
				window.location.assign("{$redirectToItem->toString()}");
			} else {
				// A window.unload-event will be triggered and a handler is implemented to reload the list view.
				if (window.opener !== null) {
					window.opener.location.reload();
					window.opener.focus();
					window.close();
				} else {
					window.location.assign("{$redirectToList->toString()}");
				}
			}
JS;
			echo "<script>$script</script>";
		}

		exit;
	}

	/**
	 * Saves data for a new lot of parts.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function saveAddLot(string $redirect = ''): void
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

		$aid        = $this->input->post->getInt('type', 0);
		$copies     = $this->input->post->getInt('copies', 0);
		$procParams = ArrayHelper::getValue($post, 'procParams', [], 'ARRAY');

		$status     = $this->model->addLot(
			$aid,
			$copies,
			$procParams
		);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.
			$redirect = new Uri('index.php?hl=' . $this->language . '&view=part&layout=add_lot');

			// Add the page to return to, that was sent via POST.
			$redirect->setVar('return', $return);

			// If a URI fragment was sent via POST, set it as URI var.
			$redirect->setFragment($this->input->getString('fragment'));

			// Error messages are set in model

			header('Location: ' . $redirect->toString());
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			// 3rd attempt: If $redirect is still empty, load hardcoded URI into a {@see Joomla\Uri\Uri} object otherwise redirect to $return.
			$redirectToList = (empty($redirect->getPath()))
				? new Uri($this->getInstance('parts')->getRoute())
				: $redirect;


			$redirectToRepeat = new Uri('index.php?hl=' . $this->language . '&view=part&layout=add_lot');
			$redirectToRepeat->setVar('return', base64_encode($return));
			// $redirectToRepeat->setVar('return', base64_encode( basename( ( new Uri($this->getInstance('parts')->getRoute()) )->toString() ) ));


			$redirectToItem   = new Uri('index.php?hl=' . $this->language . '&view=parts&layout=lot&lid=' . $status);
			$redirectToItem->setVar('return', base64_encode($return));
			// $redirectToItem->setVar('return', base64_encode( basename( ( new Uri($this->getInstance('parts')->getRoute()) )->toString() ) ));
			// If a URI fragment was sent via POST, set it as URI var.
			$redirectToItem->setFragment($this->input->getString('fragment'));


// Provide the user with a choice whether to step over to edit the previously created article
$question1 = Text::translate('Das Los wurde erstellt.\r\nMöchten Sie es jetzt drucken?', $this->language);
$question2 = Text::translate('Möchten Sie ein weiteres Los erstellen?', $this->language);
// $question = Text::translate('COM_FTK_SYSTEM_MESSAGE_LOT_POST_CREATION_EDIT_TEXT', $this->language);
$script   = <<<JS
			if (confirm("$question1") === true) {
				window.location.assign("{$redirectToItem->toString()}");
			} else {
				if (confirm("$question2") === true) {
					window.location.assign("{$redirectToRepeat->toString()}");
				} else {
					// A window.unload-event will be triggered and a handler is implemented to reload the list view.
					if (window.opener !== null) {
						window.opener.location.reload();
						window.opener.focus();
						window.close();
					} else {
						window.location.assign("{$redirectToList->toString()}");
					}
				}
			}
JS;
			echo "<script>$script</script>";
		}

		exit;
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveEdit
	 */
	public function saveEdit(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post     = $this->input->post->getArray();

		$status   = $this->model->updatePart(['form' => $post]);

		// Prepare redirect URL.
		$redirect = new Uri('index.php');
		$redirect->setVar('hl', $this->language);

		if ($this->input->getInt('at') == '1')
		{
			$redirect->setVar('view',   'parts');
			$redirect->setVar('layout', 'list');
		}
		else
		{
			$redirect->setVar('view',   'part');
			$redirect->setVar('layout', 'edit');
		}

		$redirect->setVar('ptid', $this->input->getInt('ptid', 0));
		$redirect->setVar('at',   $this->input->getInt('at'));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_NOT_SAVED_TEXT', $this->language)
			]);
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language)
			]);
		}

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Saves tracking data for an existing part.
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @todo - implement function updatePart(), then call it and in there call storeTrackingData() like is done with processParameters()
	 * @todo - properly validate that a user is allowed to edit in general and to edit a part's process !
	 */
	public function saveEditProcess(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post     = $this->input->post->getArray();
		/*$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}*/
        $user = App::getAppUser();
        //echo "<pre>";print_r($user);

        $userID = $user->get('userID');
        $statusID = $this->model->getuserTrack($userID);

		$status = $this->model->storeTrackingData(
			$this->input->getInt('ptid', 0),
			$this->input->getInt('pid',  0),
			[
				'form' => $post
			]
		);

		// Prepare redirect URL.
		/* $redirect = new Uri( !empty($redirect)
			? $redirect
			: 'index.php?hl=' . $this->language . '&view=part&layout=item&ptid=' . $this->input->getInt('ptid', 0) .
			  (!empty($fragment = $this->input->getString('fragment', '')) ? '#' . $fragment : '')
		); */

		$redirect = new Uri('index.php');
		$redirect->setVar('hl', $this->language);

		if ($this->input->getInt('at') == '1')
		{
			$redirect->setVar('view',   'parts');
			$redirect->setVar('layout', 'list');
		}
		else
		{
            switch ($statusID[0]['trackjump']) {
                case 0:
                    $redirect->setVar('view', 'part');
                    $redirect->setVar('layout', 'item');

                    if (!empty($fragment = $this->input->getString('fragment', ''))) {
                        $redirect->setFragment($fragment);
                    }
                    break;

                default:
                    $redirect->setVar('view', 'parts');
                    $redirect->setVar('layout', 'list');
                    break;
            }
            /*if($statusID[0]['trackjump'] == 0) {
                $redirect->setVar('view',   'part');
                $redirect->setVar('layout', 'item');

                if (!empty($fragment = $this->input->getString('fragment', '')))
                {
                    $redirect->setFragment($fragment);
                }
            }else {
                $redirect->setVar('view', 'parts');
                $redirect->setVar('layout', 'list');
            }*/
		}

		$redirect->setVar('ptid', $this->input->getInt('ptid', 0));
		$redirect->setVar('at',   $this->input->getInt('at'));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_NOT_SAVED_TEXT', $this->language)
			]);
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			Messager::setMessage([
				'type' => 'success',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_INPUT_SAVED_TEXT', $this->language)
			]);
		}

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Generates a unique part tracking code and redirects to the previous view.
	 *
	 * @param   string $redirect  The URI to redirect to
	 */
	public function genCode(string $redirect = ''): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - translate
		if (!class_exists('Froetek\Coder\Coder'))
		{
			throw new RuntimeException(sprintf('Missing dependency: %s', 'Froetek Trackingcode Generator'));
		}

		$post     = $this->input->post->getArray();
		$type     = $this->input->post->getInt('type', 0);
		$lastCode = $this->model->getLastCode();	// Get last inserted part tracking code from database and pass it to the coder

		$status   = Coder::getNextCode($lastCode);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			$redirect = new Uri($this->getInstance('parts', ['language' => $this->language])->getRoute());
		}
		else
		{
			$redirect = new Uri( 'index.php?hl=' . $this->language . '&view=part&layout=add&aid=' . $type . '&code=' . $status );
		}

		$redirect->setVar('at', $this->input->getInt('at'));

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Generates a unique part tracking code without redirecting (AJAX)
	 */
	public function genCodeJSON(): void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// TODO - translate
		if (!class_exists('Froetek\Coder\Coder'))
		{
			throw new RuntimeException(sprintf('Missing dependency: %s', 'Froetek Trackingcode Generator'));
		}

//		$post     = $this->input->post->getArray();
//		$type     = $this->input->post->getInt('type', 0);
		$lastCode = $this->model->getLastCode();	// Get last inserted part tracking code from database and pass it to the coder

		$status   = Coder::getNextCode($lastCode);

		try
		{
			$code = json_encode($status, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $e)
		{
			$code = $e->getMessage();
		}

		echo $code;
		exit;
	}

	/**
	 * Searches for part codes in a given string and converts them into hyperlinks.
	 * If a part code is bad and the related part cannot be found a red coloured inline error is displayed instead.
	 *
	 * @param   string $techparam
	 *
	 * @return  string
	 */
	public function convertCodesToHyperlinks(string $techparam): string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$techparam = trim($techparam);

		// Check if user input contains part code(s).
		//   NOTE: as of 2022-01-20 we allow multiple codes (think cross-referencing between welded parts or freq. monitoring)
		$containsTrackingcode = preg_match_all('/' . FTKREGEX_TRACKINGCODE_INLINE . '/', $techparam, $codes);

		$codes = \ \Helper\ArrayHelper::filterRecursive($codes, null, true);
		$codes = array_unique($codes, SORT_REGULAR);	/* Hint: The SORT_REGULAR flag prevents array_unique from throwing a
																	 *       "Array to string conversion" notification in the error log
																	 * caused by this function comparing elements as strings by default. */

		// Convert every code into hyperlink and replace in $techparam
		if ($containsTrackingcode)
		{
			$codes = array_shift($codes);

			// Convert into links.
			foreach ($codes as $code)
			{
				$partID = $this->model->getItemByCode(mb_strtoupper(trim($code)))->get('partID');

				if ($partID)
				{
					$uri  = UriHelper::osSafe(UriHelper::fixURL(sprintf('index.php?hl=%s&view=part&layout=item&ptid=%d', $this->language, $partID)));
					$link = sprintf('<a href="%s" role="button" class="part-code-inline text-info text-underlined" data-toggle="tooltip" title="%s">%s</a>',
						$uri,
						Text::translate('COM_FTK_LINK_TITLE_PART_VIEW_THIS_TEXT', $this->language),
						mb_strtoupper(trim($code))
					);
				}
				else
				{
					$link = sprintf('<span class="alert alert-inline alert-danger text-danger px-2" data-toggle="tooltip" title="%s" style="cursor:not-allowed">%s</span>',
						sprintf('%s. %s',
							Text::translate('COM_FTK_SYSTEM_MESSAGE_BAD_CODE_TEXT', $this->language),
							Text::translate('COM_FTK_SYSTEM_MESSAGE_PART_COULD_NOT_BE_FOUND_TEXT', $this->language)
						),
						mb_strtoupper(trim($code))
					);
				}

				$techparam = str_ireplace($code, trim($link), $techparam);
			}
		}

		return $techparam;
	}
}
