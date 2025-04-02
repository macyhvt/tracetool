<?php
/* define application namespace */
namespace  \View;

/* no direct script access */
defined ('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use InvalidArgumentException;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \App;
use  \Factory;
use  \Helper\DatabaseHelper;
use  \Helper\UserHelper;
use  \Messager;
use  \Text;
use  \Utility\Math;
use  \View\Item as ItemView;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use function array_combine;
use function array_diff;
use function array_fill;
use function array_filter;
use function array_keys;
use function array_pop;
use function array_search;
use function in_array;
use function is_null;
use function property_exists;

/**
 * Class description
 */
class Article extends ItemView
{
	use \ \Traits\View\Article;

	/**
	 * {@inheritdoc}
	 * @throws  Exception
	 * @see     Item::__construct
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
		$id   = $this->input->get->getInt('aid');

		// Fetch item.
//		$item = (isset($id) && $id) ? $this->model->getItem($id, $this->model->get('language')) : null;
		$item = (isset($id) && $id)
				? $this->model->getItem($id, $this->model->get('language'))
				: $this->model->getItem(0, $this->model->get('language'));

		// Access control. Block the attempt to open a non-existing item.
		if (!is_null($id) && !is_object($item))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(Text::translate(mb_strtoupper(sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))), $this->language), $item->get('artID'))
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Access control. Customers/Suppliers may see only their item(s).
		$customerProjects = null;

		if (is_object($this->user) && $this->user->isCustomer())
		{
			$customerProjects = (array) $this->model->getInstance('organisation', ['language' => $this->model->get('language')])->getOrganisationProjectsNEW(['orgID' => $this->user->get('orgID')]);

			$customerProjects = array_column($customerProjects, 'number');
		}

		if (is_array($customerProjects) && !in_array($item->get('project'), $customerProjects))
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#3)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Prepare item for display.
		if (is_a($item, sprintf(' \Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) && $item->get('number'))
		{
			// Check for item metadata being completely translated.
			$model = $this->model->getInstance('languages');
			$langs = (array) $model->getList();
			// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
			$metas = array_combine(array_keys($langs), array_fill(0, count($langs), null));

			// Get list of metadata fields relevant for translation.
			$fields = DatabaseHelper::getTableColumns($this->get('name') . '_meta');
			$fields = array_diff($fields, ['artID', 'lngID', 'instructions', 'language']);  // drop these fields - they must not be handled (data "instructions" added on 2022-05-19, as of SM's decision)

			// Rename field 'name' to 'label' to pass hint translation later on.
			if ($idx = array_search('name', $fields))
			{
				$fields[$idx] = 'label';
			}

			// Rename field 'description' to 'annotation' to pass hint translation later on.
			if ($idx = array_search('description', $fields))
			{
				$fields[$idx] = 'annotation';
			}

			// Prepare object holding missing translation details.
			// TODO - convert fill process from array_fill() to array_fill_keys() {@link https://www.php.net/manual/de/function.array-fill-keys.php} - it preserves the counting
			$incomplete = new Registry([
				'translation' => array_combine(
					$fields, array_fill(0, count($fields), $metas)
				)
			]);

			// Find missing field translation(s).
			array_walk($langs, function($lang) use(&$model, &$id, &$langs, &$incomplete)
			{
				$lng  = ArrayHelper::getValue($lang, 'tag');
				$meta = $this->model->getItemMeta($id, $lng, true); // empty result means no metadata at all, whereas an array holds a single row of metadata

				if ($meta)
				{
					if (!empty($meta['name']))
					{
						$incomplete->remove('translation.label.' . $lng);
					}

					if (!empty($meta['description']))
					{
						$incomplete->remove('translation.annotation.' . $lng);
					}
				}

				// Skip collection if empty.
				$collection = (array) $incomplete->get('translation.label');
				if (!count($collection) || (count($collection) == count($langs)))
				{
					$incomplete->remove('translation.label');
				}

				$collection = (array) $incomplete->get('translation.annotation');
				if (!count($collection) || (count($collection) == count($langs)))
				{
					$incomplete->remove('translation.annotation');
				}
			});

			// Add list to item for rendering.
			if (count($incomplete))
			{
				$item->__set('incomplete', $incomplete);
			}

			// Free memory.
			unset($langs);
			unset($metas);
			unset($incomplete);
			unset($model);
		}

		// Assign ref to loaded item.
		$this->item = $item;

		// Prepare drawings for display.
		$this->prepareDrawings();
	}

	/**
	 * {@inheritdoc}
	 * @see Item::__construct
	 */
	public function getIdentificationKey(): string
	{
		return $this->identificationKey = 'aid';
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$action = $this->input->post->getWord('button');
		$post   = $this->input->post->getArray();
		$return = base64_decode($this->input->post->getBase64('return', ''));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			switch ($action)
			{
				// Return to List-view
				case 'submitAndClose' :
				case 'submit' :
				case 'cancel' :
					$redirect = new Uri($this->getInstance('articles', ['language' => $this->get('language')])->getRoute());
				break;

				// Return to add-Item-view
				case 'submitAndNew' :
					$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=add');
				break;

				default :
					$redirect = new Uri($return);
				break;
			}
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Save data.
		$status = $this->model->addArticle(array_filter([
			'form'  => $post,
			// Since 'input->files->getArray()' does not return the uploaded files in the same format as $_FILES
			// it is necessary to build the files array before passing it to the model. However, it still looks different.
			'files' => array_filter([
				// Article drawing(s9)
				'drawing-cust' => $this->input->files->get('drawing-cust'),	// Customer-drawing
				'drawing-ftk'  => $this->input->files->get('drawing-ftk'),	// FR�TEK-drawing of the same article
				// Process drawing(s)
				'drawings'     => $this->input->files->get('drawings')
			])
		]));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// On error return to previous page.
			$redirect = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=add');

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

			$message = Text::translate(mb_strtoupper(sprintf('COM_FTK_SYSTEM_MESSAGE_%s_WAS_CREATED_TEXT', $this->get('name'))), $this->language);

			if ($action == 'cancel')
			{
				header('Location: ' . $redirect->toString());
				exit;
			}

			if ($action == 'submitAndNew')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);

				header('Location: ' . $redirect->toString());
				exit;
			}

			if ($action == 'submitAndClose')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);

				$script = <<<JS
				// A window.unload-event will be triggered and a handler is implemented to reload the list view.
				if (window.opener !== null) {
					window.opener.location.reload();
					window.opener.focus();
					setTimeout(function() { window.close() }, 200);
				} else {
					window.location.assign("{$redirect->toString()}");
				}
JS;

				echo "<script>$script</script>";
				exit;
			}

			if ($action == 'submit')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);
			}

			/*// 3rd attempt: If $redirect is still empty, load hardcoded URI into a {@see Joomla\Uri\Uri} object otherwise redirect to $return.
			$redirectToList = (empty($redirect->getPath()))
				? new Uri($this->getInstance('articles')->getRoute())
				: $redirect;*/

			$redirectToItem = new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=edit&aid=' . $status);
			// $redirectToItem->setVar('return', $this->input->post->getBase64('return'));
			$redirectToItem->setVar('return', base64_encode(basename((new Uri('index.php?hl=' . $this->language . '&view=' . $this->get('name') . '&layout=item&aid=' . $status))->toString())));
			// If a URI fragment was sent via POST, set it as URI var.
			$redirectToItem->setFragment($this->input->getString('fragment'));

			$script = <<<JS
			// A window.unload-event will be triggered and a handler is implemented to reload the list view.
			window.location.assign("{$redirectToItem->toString()}");
JS;
			echo "<script>$script</script>";
		}

		exit;
	}

	/**
	 * {@inheritdoc}
	 * @see  Item::saveEdit
	 */
	public function saveEdit(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$action   = $this->input->post->getWord('button');
		$post     = $this->input->post->getArray();
		$return   = base64_decode($this->input->post->getBase64('return', ''));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			switch ($action)
			{
				// Return to List-view
				case 'submit' :
					$redirect = new Uri(parent::getReferer());
				break;

				// Return to add-Item-view
				case 'submitAndClose' :
				case 'cancel' :
				default :
					$redirect = new Uri($return);
				break;
			}
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Save data.
		$status = $this->model->updateArticle(array_filter([
			'form'  => $post,
			// Since 'input->files->getArray()' does not return the uploaded files in the same format as $_FILES
			// it is necessary to build the files array before passing it to the model. However, it still looks different.
			'files' => array_filter([
				// Article drawing(s9)
				'drawing-cust' => $this->input->files->get('drawing-cust'),	// Customer-drawing
				'drawing-ftk'  => $this->input->files->get('drawing-ftk'),	// FR�TEK-drawing of the same article
				// Process drawing(s)
				'drawings'     => $this->input->files->get('drawings')
			])
		]));

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			// Error messages are set in model
		}
		else
		{
			// Delete POST data from user session as it is not required anymore.
			// The item will be populated from current data after page reload.
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			$message = Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language);

			if ($action == 'submitAndClose')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);

				return;
			}

			if ($action == 'submit')
			{
				Messager::setMessage([
					'type' => 'success',
					'text' => $message
				]);
			}
		}

		// Add the page to return to, that was sent via POST.
		// $redirect->setVar('return', base64_encode($return));	// Don't set it, when it was loaded into new Uri instead of $redirect, as that would redirect to itself.
		// If a URI fragment was sent via POST, set it as URI var.
		// $redirect->setFragment($this->input->getString('fragment'));

		header('Location: ' . $redirect->toString());
		exit;
	}
    public function saveOrg(string $redirect = '') : void
    {

        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
        //echo "jeheikk";exit;
        $action   = $this->input->post->getWord('saveord');
        $post     = $this->input->post->getArray();

        $return   = base64_decode($this->input->post->getBase64('return', ''));
        //echo "<pre>hel";print_r($return);exit;
        // 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
        $redirect = new Uri($redirect);

        // 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.

        //print_r($_POST);exit;

        // If a URI fragment was sent via POST, set it as URI var.
        $redirect->setFragment($this->input->getString('fragment'));

        // Save data.
        $status = $this->model->updateArticleOrganisation($post['aid'], $post['pid'], $post['org_abbr']);
        //echo "<pre>";print_r($status);exit;
        // An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
        if (!$status){
            $this->user->__set('formData', $post);
        }else{
            // Delete POST data from user session as it is not required anymore.
            // The item will be populated from current data after page reload.
            if (property_exists($this->user, 'formData'))
            {
                $this->user->__unset('formData');
            }

            $message = Text::translate('COM_FTK_SYSTEM_MESSAGE_CHANGES_SAVED_TEXT', $this->language);

            if ($action == 'submitAndClose')
            {
                Messager::setMessage([
                    'type' => 'success',
                    'text' => $message
                ]);

                return;
            }

            if ($action == 'submit')
            {
                Messager::setMessage([
                    'type' => 'success',
                    'text' => $message
                ]);
            }
        }
        $backsend = new Uri('index.php');
        $backsend->setVar('hl',        $this->language);
        $backsend->setVar('view',      'article');
        $backsend->setVar('layout',    'edit');
        $backsend->setVar('aid', (int) $post['aid']);

        //echo $backsend.'#processes';exit;
        // Add the page to return to, that was sent via POST.
        // $redirect->setVar('return', base64_encode($return));	// Don't set it, when it was loaded into new Uri instead of $redirect, as that would redirect to itself.
        // If a URI fragment was sent via POST, set it as URI var.
        // $redirect->setFragment($this->input->getString('fragment'));
        $message = 'orgupdated='.$post['pid'];
        header('Location: ' .$backsend. '&message=' .$message.'#processes');
        //header('Location: ' . $redirect->toString());
        exit;
    }
    public function saveProstat($aid, $pid, $statcode) : void
    {
        echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

        $action   = $this->input->post->getWord('saveord');
        $post     = $this->input->post->getArray();
        $redirect = '';
        $return   = base64_decode($this->input->post->getBase64('return', ''));
        //echo "<pre>hel";print_r($return);exit;
        // 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
        $redirect = new Uri($redirect);

        // 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.

        //print_r($_POST);exit;
        //echo $aid;echo $pid;echo $statcode;exit;
        // If a URI fragment was sent via POST, set it as URI var.
        $redirect->setFragment($this->input->getString('fragment'));

        // Save data.
        $status = $this->model->updateProcessStatus($aid, $pid, $statcode);

        $status2 = $this->model->getArticleUpCount($aid, $pid);
        /*if ($status2 !== null) {
            // Records found

            //echo "Records found!";
        } else {
            echo "<style>.btnMikpro{
                    display: none;
                    }</style>";
            // No records found
            echo "No records found.";
        }*/
        //exit;

        //echo "<pre>";print_r($status);exit;
        // An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))

        $backsend = new Uri('index.php');
        $backsend->setVar('hl',        $this->language);
        $backsend->setVar('view',      'article');
        $backsend->setVar('layout',    'edit');
        $backsend->setVar('aid', (int) $aid);

        $message = 'orgupdated='.$pid;
        header('Location: ' .$backsend. '&message=' .$message.'#processes');
        //header('Location: ' . $redirect->toString());
        exit;
    }
	/**
	 * Method to prepare item drawing for display.
	 * Paths are validated and metadata is injected.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   2.7.0
	 */
	protected function prepareDrawings() : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/* Prepare item drawing(s) - 000 drawing(s) by FR�TEK and CUSTOMER (if available) for display. */

		// FRÖTEK-drawing.
		$itemDrawingFTK = $this->item->get('drawing', new Registry);
		// Process has drawing. Fix paths + add metadata.
		$itemDrawingFTK = $this->_evaluateDrawing($itemDrawingFTK);
		// Update item property.
		$this->item->set('drawing', $itemDrawingFTK);

		// CUSTOMER-drawing.
		$itemDrawingCUST = $this->item->get('customerDrawing', new Registry);
		// Process has drawing. Fix paths + add metadata.
		$itemDrawingCUST = $this->_evaluateDrawing($itemDrawingCUST);
		// Update item property.
		$this->item->set('customerDrawing', $itemDrawingCUST);

		// Prepare item process drawings for display.
		$processes = $this->item->get('processes', []);

		foreach ($processes as &$process)
		{
			$process = (is_a($process, 'Joomla\Registry\Registry')) ? $process : new Registry($process);

			// Prepare drawing file and thumbnail for display.
			$processDrawing = $process->extract('drawing');
			$processDrawing = $processDrawing ?? new Registry;
			// Process has drawing. Fix paths + add metadata.
			$processDrawing = $this->_evaluateDrawing($processDrawing);

			// Update process.
			$process->set('drawing', $processDrawing);
		}

		// Update item process drawings.
		$this->item->set('processes', $processes);
	}


	/**
	 * Add description ...
	 *
	 * @param   Registry $drawing
	 *
	 * @return  Registry|null
	 *
	 * @throws  Exception
	 *
	 * @uses    {@link Finder}
	 *
	 * @since   2.7.0
	 */
	private function _evaluateDrawing(Registry $drawing) : ?Registry
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Init vars.
		$usrProfile = $usrLocale = new Registry;

		try
		{
			$usrProfile->loadArray( UserHelper::getProfile($this->user) );
			$usrLocale = $usrProfile->extract('user.locale');
		}
		catch (InvalidArgumentException $e)
		{
			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('Failed to load your user profile data.', $this->language)
			]);
		}

		$placeholderURL = sprintf('%s/288x204/FFFFFF/FFFFFF.png%s', FTKRULE_DRAWING_THUMB_DUMMY_PROVIDER, /*?text=DUMMY*/'');    // white text on white background (Text is not configurable)

		// Register URL to online service for image placeholder.
		$drawing->set('placeholder', $placeholderURL);

		// The IntlDateFormatter will translate month names when they are part of the display pattern.
		$dateFormatter = new IntlDateFormatter(
			$usrLocale->get('language.ui', $usrLocale->get('language.native', $usrProfile->get('user.language', Factory::getConfig('language')))),

			IntlDateFormatter::SHORT,
			IntlDateFormatter::SHORT
		);

//		$dateFormatter->setPattern(sprintf('%s %s', 'dd. MMM y',  'HH:mm'));    // 01.01.2021 ==> 01. Jan. 2021 00:00
		$dateFormatter->setPattern(sprintf('%s %s', 'dd. MMMM y', 'HH:mm'));    // 01.01.2021 ==> 01. Januar 2021 00:00

		if ($drawing->get('file'))
		{
			// Define existence flags. They will be updated further below.
			$drawing->def('fileExists', true);
			$drawing->def('thumb', null);
			$drawing->def('thumbExists', true);

			// Validate this drawing object has a metadata attributes bag.
			if (!$drawing->def('metadata'))
			{
				$drawing->def('metadata', []);
			}

			// Get Symfony file handler.
			$finder   = new Finder;	// evaluate article drawing

			// Get path to PDF file.
			$pathFile = $drawing->get('file');
			$pathFile = explode('/', '' . $pathFile);
			$pathFile = array_filter($pathFile);
			// Skip file name. We are only interested in the path.
			$fileName = array_pop($pathFile);

			// Join fragments and concat with base name.
			$directory    = App::getRouter()->fixRoute(DIRECTORY_SEPARATOR . implode('/', $pathFile));
			$directoryABS = App::getRouter()->fixRoute(FTKPATH_BASE . DIRECTORY_SEPARATOR . $directory);

			// Configure lookup directory.
			try
			{
				$finder
				->ignoreUnreadableDirs()
				->in(App::getRouter()->fixRoute($directoryABS));
			}
			catch (DirectoryNotFoundException $e)
			{
				// The drawings directory doesn't exist at all or couldn't be found.
				// Reset data.
				$drawing->set('file',          null);
				$drawing->set('fileExists',    false);
				$drawing->set('fileAbsolute',  null);
				$drawing->set('images',        null);
				$drawing->set('thumb',         null);
				$drawing->set('thumbExists',   false);
				$drawing->set('thumbAbsolute', null);

				return $drawing;
			}

			// Find all PDF files related to this item in drawings directory.
			$finder
			->files()
			->followLinks()
			->name('/^' . $fileName . '$/');

			// Iterate over the found PDF(s) and fix path + inject medata data.
			if ($finder->hasResults())
			{
				$userProfile = $userProfile ?? new Registry(UserHelper::getProfile($this->user));
				$userLocale  = $userLocale ?? $userProfile->extract('user.locale');

				foreach ($finder as $PDF)
				{
					if ($PDF->getFilename() !== $fileName)
					{
						continue;
					}

					// Add absolute file path to drawing object data.
					$drawing->set('fileAbsolute', App::getRouter()->fixRoute(sprintf('%s?t=%d', $PDF->getRealPath(), mt_rand(0, 9999999))));
					$drawing->set('fileName', trim(preg_replace('/\.' . $PDF->getExtension() . '$/i', '', $PDF->getFilename())));

					$dateTimeObj = new DateTime;
					$timeZoneObj = new DateTimeZone($userLocale->get('timezone', FTKRULE_TIMEZONE));
					$dateFormat  = preg_replace('/\./', '. ', sprintf('%s %s', str_replace('m', 'F', $userLocale->get('date')), str_replace(':s', '', $userLocale->get('time'))));
					$dateTimeObj->setTimeZone($timeZoneObj);

					// Add file meta information to drawing object data.
					$drawing->set('metadata.access',   $dateTimeObj->setTimestamp($PDF->getATime())->format($dateFormat));
					$drawing->set('metadata.modified', $dateTimeObj->setTimestamp($PDF->getMTime())->format($dateFormat));
					$drawing->set('metadata.sizes.B',  $PDF->getSize());
					$drawing->set('metadata.sizes.KB', Math::bytesToKilobytes($PDF->getSize(), 2));
					$drawing->set('metadata.sizes.MB', Math::bytesToMegabytes($PDF->getSize(), 2));

					// Append timestamp to file path to prevent browser caching.
					$drawing->set('file', App::getRouter()->fixRoute(sprintf('%s?t=%d', $drawing->get('file'), mt_rand(0, 9999999))));

					// File found and prepared. Escape from loop now!
					break;
				}
			}
			else
			{
				$drawing->set('fileExists', false);
			}

			// Get path to PDF file.
			$pathThumb = current((array) $drawing->get('images'));
			$pathThumb = (array) explode('/', '' . $pathThumb);
			$pathThumb = array_filter($pathThumb);
			// Skip file name. We are only interested in the path.
			$thumbName = array_pop($pathThumb);

			// Join fragments and concat with base name.
			$directory    = App::getRouter()->fixRoute(DIRECTORY_SEPARATOR . implode('/', $pathThumb));
			$directoryABS = App::getRouter()->fixRoute(FTKPATH_BASE . DIRECTORY_SEPARATOR . $directory);

			// Configure lookup directory.
			try
			{
				$finder
				->ignoreUnreadableDirs()
				->in(App::getRouter()->fixRoute($directoryABS))
				->followLinks();
			}
			catch (DirectoryNotFoundException $e)
			{
				// The drawings directory doesn't exist at all or couldn't be found.
				// Reset data.
				$drawing->set('file',          null);
				$drawing->set('fileExists',    false);
				$drawing->set('fileAbsolute',  null);
				$drawing->set('images',        null);
				$drawing->set('thumb',         null);
				$drawing->set('thumbExists',   false);
				$drawing->set('thumbAbsolute', null);

				return $drawing;
			}

			// Find all PNG files related to this item in drawings directory.
			// $files     = $finder->path('/\.000\.[0-9A-Z](__thumb)?.p(df|ng)$/');
			// $files     = $finder->path('/\.000\.[0-9A-Z](__thumb).png$/');
			// $files     = $finder->path($thumbName);
			// $files     = $finder->name($thumbName);
			$finder->files()->name('/^' . $thumbName . '$/');

			// Iterate over the found PNG(s) and fix path + inject medata data.
			if ($finder->hasResults())
			{
				foreach ($finder as $IMG)
				{
					if ($IMG->getFilename() !== $thumbName)
					{
						continue;
					}

					// Add absolute file path to drawing object data.
					$drawing->set('thumbAbsolute', App::getRouter()->fixRoute(sprintf('%s?t=%d', $IMG->getRealPath(), mt_rand(0, 9999999))));

					// Split path to PDF.
					$thumb = explode('/', $drawing->get('file', ''));

					// Skip filename.
					array_pop($thumb);

					// Add filenmae of PNG.
					$thumb[] = $IMG->getRelativePathname();    // Gets in fact the file name incl. extension

					// Join pieces.
					$thumb = implode('/', $thumb);

					// Append timestamp to file path to prevent browser caching.
					$drawing->set('thumb', App::getRouter()->fixRoute(sprintf('%s?t=%d', $thumb, mt_rand(0, 9999999))));

					// File found and prepared. Escape from loop now!
					break;
				}
			}
			else
			{
				$drawing->set('thumbExists', false);
			}
		}

		// Free memory.
		unset($finder);
		unset($usrProfile);
		unset($usrLocale);
		unset($dateFormatter);

		return $drawing;
	}
}
