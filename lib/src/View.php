<?php
/* define application namespace */
namespace  ;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use Exception;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Entity\User;
use  \Helper\FilesystemHelper;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Inflector\EnglishInflector as StringInflector;
use Throwable;
use function is_a;
use function is_bool;
use function is_file;
use function is_null;
use function is_readable;
use function property_exists;

/**
 * Class description
 */
abstract class View extends App
{
	/**
	 * @var    string|null  The view title used as page heading.
	 * @since  2.10.1
	 */
	protected ?string $viewTitle  = null;

	/**
	 * @var    string|null  The name of the layout to render.
	 * @since  2.10.1
	 */
	protected ?string $layoutName = null;

	/**
	 * @var    string|null  The name of the task to execute.
	 * @since  2.10.1
	 */
	protected ?string $taskName   = null;

	/**
	 * Object instances container.
	 *
	 * @var    array
	 * @since  1.1
	 */
	protected static array $instances = [];

	/**
	 * The instance identifier
	 *
	 * @var    string|null
	 * @since  1.1
	 */
	protected ?string $context = null;

	/**
	 * The view name.
	 *
	 * @var    string|null
	 * @since  0.1
	 */
	protected ?string $name = null;

	/**
	 * The language tag to use for translation (e.g. de, en, fr, etc.).
	 *
	 * @var    string|null
	 * @since  0.1
	 */
	// protected $lang = null;

	/**
	 * The layout name.
	 *
	 * @var    string|null
	 * @since  0.1
	 */
	protected ?string $layout = null;

	/**
	 * The main model of this object.
	 *
	 * @var    Model|null
	 * @since  0.1
	 */
	protected ?Model $model = null;

	/**
	 * The user who requests this object.
	 *
	 * @var    User|null
	 * @since  1.1
	 */
	protected ?User $user = null;

	/**
	 * An object that contains the super globals $_GET, $_POST, $_FILES, $_SERVER, etc.
	 *
	 * @var    Input|null
	 * @since  1.1
	 */
	protected $input = null;

	/**
	 * An object that contains the super globals $_GET, $_POST, $_FILES, $_SERVER, etc.
	 *
	 * @var    Request|null
	 * @since  1.1
	 */
	protected $request = null;

	/**
	 * An object that holds information about the user's navigator object.
	 *
	 * @var    \Joomla\Registry\Registry|null
	 * @since  1.1
	 */
	protected $navigator = null;

	/**
	 * The output of the template script.
	 *
	 * @var    string|null
	 * @since  1.1
	 */
	private ?string $_output = null;

	/**
	 * Class construct
	 *
	 * @param   string $name     A view name.
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @since   0.1
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $name . '", ' . json_encode($options) . ')', true) . '</pre>' : null;

		parent::__construct($options);

		// Get instance name.
		$this->context = basename(get_class($this));

		$this->name = $name;

		// Assign ref to current user object.
		$this->user = App::getAppUser();

		// Assign ref to the navigator object (web browser).
		$this->browser = new Registry(ArrayHelper::getValue($GLOBALS, 'session')->get('navigator', []));

		// Assign ref to HTTP Request object.
		$this->input = App::getInput();

		// Assign ref to HTTP Request object.
		$this->request = App::getRequest();

		// Get model and assign it to this object.
		$model = Model::getInstance($this->name, ['language' => $this->get('language', ArrayHelper::getValue($options, 'language'))]);

		// TODO - check $options parameter for model names and load+assign them if exist.
		if (is_a($model, ' \Model'))
		{
			$this->setModel($model, true);
		}

		// Get language object.
		/*$lang = $model->getInstance('language')->getLanguageByTag($this->get('language'));
		$lang = (is_array($lang)) ? new Registry($lang) : new Registry;
		// TODO - replace this object's 'language' property value (currently the lang tag) with the registry object providing access to lang id, tag and name
		$this->lang = $lang;*/

		// Assign ref to requested layout.
		$this->layout = ArrayHelper::getValue($options, 'layout');
	}

	/**
	 * Returns a view's default URI
	 *
	 * @return string
	 */
	abstract public function getRoute() : string;

	/**
	 * Method to get an instance of View class.
	 *
	 * @param   string $name     The name of the desired View class.
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  mixed    \View if instantiation was successful, or null if not.
	 *
	 * @throws  \RuntimeException if the view could not be loaded.
	 * @since   1.1
	 *
	 */
	public static function getInstance(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '("' . $name . '")', true) . '</pre>' : null;

		// Get the options signature for the database connector.
		$signature = md5(serialize(compact('name', 'options')));

		// Create instance.
		if (empty(static::$instances[$signature]))
		{
			$className    = basename(str_replace('\\', '/', __CLASS__));
			$instanceName = ' \\' . $className . '\\' . ucfirst(mb_strtolower($name));

			if (!class_exists($instanceName))
			{
				$filePath = FilesystemHelper::fixPath(__DIR__ . '/' . $className . '/' . ucfirst(mb_strtolower($name)) . '.php');

				// If the class still doesn't exist we have nothing left to do but throw an exception.  We did our best.
				if (!is_file($filePath) || !is_readable($filePath))
				{
					// TODO - implement FileNotFoundException
					throw new RuntimeException(sprintf('Unable to load %s file: %s', $className, ucfirst(mb_strtolower($name))));
				}

				require_once $filePath;
			}

			// If the class still doesn't exist we have nothing left to do but throw an exception.  We did our best.
			if (!class_exists($instanceName))
			{
				// TODO - translate
				throw new RuntimeException(sprintf('%s not available: %s', $className, $instanceName));
			}

			// Create new View object based on the options given.
			try
			{
				$instance = new $instanceName($name, $options);
			}
			catch (Throwable $e)
			{
				$trace  = debug_backtrace();
				$called = current($trace);
				$caller = next($trace);

				// TODO - translate
				throw new RuntimeException(
					sprintf('Unable to instantiate %s "%s": %s', $className, $instanceName, $e->getMessage())
				);
			}

			// Set the new View object to the global instances based on signature.
			static::$instances[$signature] = $instance;
		}

		return static::$instances[$signature];
	}

	/**
	 * Add description...
	 *
	 * @param   false $fullyQualified
	 *
	 * @return  string
	 */
	public static function getReferer(bool $fullyQualified = false) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$referer = ArrayHelper::getValue($_SERVER, 'HTTP_REFERER', ArrayHelper::getValue($_SERVER, 'PHP_SELF', 'STRING'), 'STRING');
		$referer = $fullyQualified ? $referer : mb_substr($referer, strrpos($referer, '/'));

		return UriHelper::osSafe($referer);
	}

	/**
	 * Add description...
	 *
	 * @param   false $fullyQualified
	 *
	 * @return  string
	 */
	public static function getURI(bool $fullyQualified = false) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$uri = ArrayHelper::getValue($_SERVER, 'REQUEST_URI', ArrayHelper::getValue($_SERVER, 'PHP_SELF', 'STRING'), 'STRING');
		$uri = $fullyQualified ? $uri : mb_substr($uri, strrpos($uri, '/'));

		return UriHelper::osSafe($uri);
	}

	/**
	 * Execute and display a template script.
	 *
	 * @param   string $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 *
	 * @see     \JViewLegacy::loadTemplate()
	 * @since   3.0
	 */
	/*public function display($tpl = null)
	{
		$result = $this->loadTemplate($tpl);

		if ($result instanceof Exception)
		{
			return $result;
		}

		echo $result;
	}*/

	/**
	 * Load a template file -- first look in the templates folder for an override
	 *
	 * @param   string $tpl  The name of the template source file; automatically searches the template paths and compiles as needed.
	 *
	 * @return  string  The output of the template script.
	 *
	 * @throws  \Exception
	 *
	 * @since   2.6
	 * @todo    Adapt implementation from Joomla\libraries\src\MVC\View\HtmlView.php
	 */
	/*public function loadTemplate(string $tpl = null) : void
	{
		// TODO - Adapt implementation from Joomla\libraries\src\MVC\View\HtmlView.php
	}*/

	/**
	 * Function to prepare a view object for output.
	 *
	 * @return \ \View object for chaining
	 */
	public function prepare() : self
	{
		/*// Block unauthorised access.
		if (!$this->canView($this->__get('user')))
		{
			// $redirect = $this->input->post->getString('return') ?? ($this->input->get->getString('return') ?? null);
			$redirect = (
				!is_null($redirect) && StringHelper::isBase64Encoded($redirect)
					? base64_decode($redirect)
					: basename($_SERVER['PHP_SELF'])
			);
		}*/

		$layoutId = 'forms.' . $this->get('name') . '.' . $this->get('layout');
		$output   = LayoutHelper::render($layoutId, (object) ['view' => $this], ['language' => $this->get('language')]);

		if (is_null($output))
		{
			$output = '' .
				'<p class="alert alert-warning" role="alert">' .
				sprintf(Text::translate('COM_FTK_SYSTEM_MESSAGE_VIEW_NOT_FOUND', $this->language) . ': <strong><em>%s</em></strong>', $this->get('name')) .
				'</p>';
		}

		$this->_output = trim($output);

		return $this;
	}

	/**
	 * Setter for object property <pre>$layout</pre>.
	 *
	 * @param   string  The name of the layout.
	 */
	public function setLayout(string $layout)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->layout = $layout;
	}

	/**
	 * Setter for object property <pre>$model</pre>.
	 *
	 * @param   Model $model  The model to be assigned.
	 * @param   bool  $primary
	 *
	 * @return \ \View object for chaining
	 */
	public function setModel(Model $model, bool $primary = true) : self
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->model = $model;

		return $this;
	}

	/**
	 * Add description...
	 *
	 * @todo - remove outta here! Use layout files and move rendering into class Layout or LayoutHelper or a separate class Renderer
	 */
	public function render()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		echo $this->_output;
	}

	/**
	 * Add description...
	 *
	 * @return string|null
	 */
	public function getReturnPage() : ?string
	{
		$return = $this->input->getBase64('return');
		$return = (!is_null($return) && StringHelper::isBase64Encoded($return)) ? base64_decode($return) : $return;

		return (!empty($return))
			? basename($return)
			: null;	// why not returning to HTTP_REFERER
	}


	/**
	 * Add description...
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @throws  \Exception
	 */
	public function saveArchivation(string $redirect = '')  // This will archive or restore an item depending on the selected state (1 = archive, 0 = restore)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// $post   = $this->input->post->getArray();
		$return = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		// $modelName     = null;
		$modelNames    = (new StringInflector)->singularize($this->get('name'));
		// $cntModelNames = (int) count($modelNames);

		$status = null;

		if (is_array($modelNames) && count($modelNames) == 1)
		{
			$modelName = current($modelNames);
//			$tmpView   = $this->getInstance($modelName);

			try
			{
				$model = $this->model->getInstance($modelName, ['language' => $this->language]);
			}
			catch (Exception $e)
			{
				$model = null;
			}

			$funcName   = sprintf('%s%s', mb_strtolower($this->input->post->getWord('task')), ucfirst(mb_strtolower($modelName)));
//			$isCallable = method_exists($model, $funcName);

			$status = $model->$funcName(
				$this->input->post->getInt($this->getInstance($modelName)->getIdentificationKey(), 0)
			);
		}
		elseif (is_array($modelNames) && count($modelNames) > 1)
		{
			foreach ($modelNames as $name)
			{
				try
				{
					$model = $this->model->getInstance($name, ['language' => $this->language]);
				}
				catch (Exception $e)
				{
					$model = null;
				}

				if (!$model)
				{
					continue;
				}

				$funcName   = sprintf('%s%s', mb_strtolower($this->input->post->getWord('task')), ucfirst(mb_strtolower($name)));
				$isCallable = method_exists($model, $funcName);

				if ($isCallable)
				{
					$status = $model->$funcName(
						$this->input->post->getInt($this->getInstance($name)->getIdentificationKey(), 0)
					);
				}
			}
		}
		else
		{
			// TODO - translate
			Messager::setMessage([
				'type' => 'error',
				'text' => 'Model not found.'
			]);

			$status = null;
		}

		if (is_null($status) || (is_bool($status) && false === $status))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF')));
			$redirect->setVar('hl', $this->language);
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}
		}

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Add description...
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @throws  \Exception
	 */
	public function saveDeletion(string $redirect = '')     // This will delete or recover an item depending on the selected state (1 = delete, 0 = recover)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// $post   = $this->input->post->getArray();
		$return = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		$modelName     = null;
		$modelNames    = (new StringInflector)->singularize($this->get('name'));
//		$cntModelNames = (int) count($modelNames);

		$status = null;

		if (is_array($modelNames) && count($modelNames) == 1)
		{
			$modelName = current($modelNames);
//			$tmpView   = $this->getInstance($modelName);

			try
			{
				$model = $this->model->getInstance($modelName, ['language' => $this->language]);
			}
			catch (Exception $e)
			{
				$model = null;
			}

			$funcName   = sprintf('%s%s', mb_strtolower($this->input->post->getWord('task')), ucfirst(mb_strtolower($modelName)));
//			$isCallable = method_exists($model, $funcName);

			$status = $model->$funcName(
				$this->input->post->getInt($this->getInstance($modelName)->getIdentificationKey(), 0)
			);
		}
		elseif (is_array($modelNames) && count($modelNames) > 1)
		{
			foreach ($modelNames as $name)
			{
				try
				{
					$model = $this->model->getInstance($name, ['language' => $this->language]);
				}
				catch (Exception $e)
				{
					$model = null;
				}

				if (!$model)
				{
					continue;
				}

//				$funcName   = sprintf('delete%s', ucfirst($name));
				$funcName   = sprintf('%s%s', mb_strtolower($this->input->post->getWord('task')), ucfirst(mb_strtolower($name)));
				$isCallable = method_exists($model, $funcName);

				if ($isCallable)
				{
					$modelName = $name;

					$status = $model->$funcName(
						$this->input->post->getInt($this->getInstance($name)->getIdentificationKey(), 0)
					);
				}
			}
		}
		else
		{
			// TODO - translate
			Messager::setMessage([
				'type' => 'error',
				'text' => 'Model not found.'
			]);

			$status = null;
		}

		if (is_null($status) || (is_bool($status) && false === $status))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF')));
			$redirect->setVar('hl', $this->language);

			header('Location: ' . $redirect->toString());
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}

			if ($this->input->post->getWord('task') == 'delete')
			{
				if ($status != '-1')
				{
					Messager::setMessage([
						'type' => 'success',
						'text' => Text::translate(mb_strtoupper(sprintf('COM_FTK_SYSTEM_MESSAGE_%s_WAS_DELETED_TEXT', $modelName)), $this->language)
					]);
				}

// Reload opening window, close current window, focus opening window. This will cause the opening window's content to be updated prior re-focusing. (e.g. refreshed list results).
				$script = <<<JS
	if (window.opener !== null) {
		window.opener.location.reload();
		window.opener.focus();
		window.close();
	} else {
		window.location.assign("{$redirect->toString()}");
	}
JS;
			}
			else
			{
// Reload opening window, then reload current window. This will cause updated window content.
				$script = <<<JS
	if (window.opener !== null) {
		window.opener.location.reload();
		window.location.assign("{$redirect->toString()}");
	}
JS;
			}

			if (strlen(trim($script)))
			{
				echo "<script>$script</script>";
			}
			else
			{
				header('Location: ' . $redirect->toString());
			}
		}

		exit;
	}

	/*public function saveState__OFF(string $redirect = '')
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$post   = $this->input->post->getArray();
		$ids    = (array) $this->input->getInt('aid');
		$state  = $this->input->getInt('state', 0);
		$return = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Calculate whether to redirect to list or item.
		// $redirect = (!is_null($redirect)
		// ? $redirect
		// : 'index.php?hl=' . $this->language . '&view=article&layout=' . (count($ids) > 1 ? 'list' : 'item&aid=' . $this->input->getInt('aid')));

		$status = $this->model->setState(
			$ids,
			$state
		);

		if (is_null($status) || (is_bool($status) && false === $status))
		{
			// Message set by model
			Messager::setMessage([
				'type' => 'error',
				'text' => Text::translate('COM_FTK_SYSTEM_MESSAGE_ITEM_STATE_COULD_NOT_BE_CHANGED_TEXT', $this->language)
			]);
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
		}

		header('Location: ' . $redirect->toString());
		exit;
	}*/

	// public function saveState__NEW_BUT_SUPPOSEDLY_NO_LONGER_NEEDED_BECAUSE_REPLACED_WITH_ARCHIVATION(string $redirect = '')
	public function saveState(string $redirect = '')        // This will block or release an item depending on the selected state (1 = block, 0 = release)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

//		$post   = $this->input->post->getArray();
		$return = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

//		$modelName     = null;
		$modelNames    = (new StringInflector)->singularize($this->get('name'));
//		$cntModelNames = (int) count($modelNames);

		$status = null;

		if (is_array($modelNames) && count($modelNames) == 1)
		{
			$modelName = current($modelNames);
//			$tmpView   = $this->getInstance($modelName);

			try
			{
				$model = $this->model->getInstance($modelName, ['language' => $this->language]);
			}
			catch (Exception $e)
			{
				$model = null;
			}

			$funcName   = sprintf('%s%s', mb_strtolower($this->input->post->getWord('task')), ucfirst(mb_strtolower($modelName)));
			$isCallable = method_exists($model, $funcName);

			$status     = $model->$funcName(
				$this->input->post->getInt($this->getInstance($modelName)->getIdentificationKey(), 0)
			);
		}
		elseif (is_array($modelNames) && count($modelNames) > 1)
		{
			foreach ($modelNames as $name)
			{
				try
				{
					$model = $this->model->getInstance($name, ['language' => $this->language]);
				}
				catch (Exception $e)
				{
					$model = null;
				}

				if (!$model)
				{
					continue;
				}

				$funcName   = sprintf('%s%s', mb_strtolower($this->input->post->getWord('task')), ucfirst(mb_strtolower($name)));
				$isCallable = method_exists($model, $funcName);

				if ($isCallable)
				{
					$status = $model->$funcName(
						$this->input->post->getInt($this->getInstance($name)->getIdentificationKey(), 0)
					);
				}
			}
		}
		else
		{
			// TODO - translate
			Messager::setMessage([
				'type' => 'error',
				'text' => 'Model not found.'
			]);

			$status = null;
		}

		if (is_null($status) || (is_bool($status) && false === $status))
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF')));
			$redirect->setVar('hl', $this->language);
		}
		else
		{
			// Delete POST data from user session as it is not required anymore
			if (property_exists($this->user, 'formData'))
			{
				$this->user->__unset('formData');
			}
		}

		header('Location: ' . $redirect->toString());
		exit;
	}

	/**
	 * Add description...
	 *
	 * @param   \ \Entity\User $user
	 *
	 * @return bool
	 *
	 * @todo - Validate if given user is allowed to view the resource
	 */
	protected function canView(User $user) : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return true;


		// FIXME - find a way to calc access rights on view basis because of different limitations to e.g. project data, organisation data, user data, etc.
		// return $user->get('userID') == '1';
	}

	/**
	 * Checks if a user has item access right.
	 *
	 * @return  void
	 */
	protected function checkAccess() : void
	{
		// Only registered and authenticated users can view content.
		if (!is_a($this->user, ' \Entity\User'))
		{
			$redirect = new Uri($this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#1)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}
	}

	/**
	 * Prepares the view for rendering.
	 *
	 * @return  bool  False on error(s), true otherwise
	 */
	protected function prepareDocument() : bool
	{
		// Calculate page heading
		if (property_exists($this, 'viewTitle'))
		{
			$this->viewTitle = Text::translate(mb_strtoupper(sprintf('COM_FTK_HEADING_%s_TEXT', $this->get('name'))), $this->language);
		}

		// Calculate layout, task and form names.
		$layoutPieces = explode('.', $this->input->getCmd('layout'));
		$layoutPieces = array_map(function($str) { return str_ireplace('-', '_', $str); }, $layoutPieces);
		$layoutPieces = array_map('mb_strtolower', $layoutPieces); reset($layoutPieces);

		if (property_exists($this, 'layoutName'))
		{
//		    $this->layoutName = (count($layoutPieces) == 1) ? null : current($layoutPieces); current($layoutPieces);
			$this->layoutName = current($layoutPieces); reset($layoutPieces);
		}

		if (property_exists($this, 'taskName'))
		{
			// URL-param always wins.
			$this->taskName = $this->input->getCmd('task');

			if (!$this->taskName)
			{
//		        $this->taskName = end($layoutPieces);
				$this->taskName = (count($layoutPieces) == 2) ? end($layoutPieces) : null;
			}
		}

		return true;
	}
}
