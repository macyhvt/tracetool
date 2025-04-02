<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Uri\Uri;
use Nematrack\Entity;
use Nematrack\Helper\UriHelper;
use Nematrack\View;

/**
 * Class description
 */
abstract class Item extends View
{
	/**
	 * @var    string|null  The URI param to identify the entity type.
	 * @since  2.10.1
	 */
	protected string $identificationKey;

	/**
	 * @var    string|null  The item's form name.
	 * @since  2.10.1
	 */
	protected ?string $formName = null;

	/**
	 * @var    array|null  The item form's data.
	 * @since  2.10.1
	 */
	protected ?array $formData = [];

	/**
	 * @var    Entity|null  The item.
	 * @since  1.1
	 */
	protected ?Entity $item = null;


	/**
	 * {@inheritdoc}
	 * @see Item::__construct
	 */
	public function __construct(string $name, array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($name, $options);
	}

	/**
	 * Saves a new item
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	abstract public function saveAdd(string $redirect = '') : void;

	/**
	 * Saves changes to an existing item
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	abstract public function saveEdit(string $redirect = '') : void;

	/**
	 * Returns a short string identifier that maps semantically to the related database table's primary key,
	 * like e.g. artID => aid , orgID => oid
	 *
	 * @return  string
	 */
	abstract public function getIdentificationKey(): string;

	/**
	 * Returns the URL to a specific entity.
	 *
	 * @param   string $layoutName
	 *
	 * @return  string
	 */
	public function getRoute(string $layoutName = 'item') : string
	{
		$route = mb_strtolower( sprintf( 'index.php?hl=%s&view=%s&layout=item', $this->get('language'), $this->get('name'), $layoutName ) );

		return UriHelper::fixURL($route);
	}

	/**
	 * Closes an item's edit view.
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	public function closeEdit(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Delete POST data from user session as it is not required anymore
		if (property_exists($this->user, 'formData'))
		{
			$this->user->__unset('formData');
		}

		// Execute redirect.
		if ($redirect->getVar('layout') == 'list')
		{
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
		}
		else
		{
			header('Location: ' . $redirect->toString());
		}

		exit;
	}

	/**
	 * Saves changes to an existing item and closes the edit view.
	 *
	 * @param   string $redirect  The URI to redirect to
	 *
	 * @return  void
	 */
	public function saveAndCloseEdit(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->saveEdit($redirect);

		$return   = base64_decode($this->input->post->getBase64('return'));

		// 1st attempt: If $redirect is not empty, load it into a {@see Joomla\Uri\Uri} object.
		$redirect = new Uri($redirect);

		// 2nd attempt: If $redirect is empty, load a potentially sent via POST var named 'return'.
		if (empty($redirect->getPath()))
		{
			$redirect = new Uri($return);
		}

		// If a URI fragment was sent via POST, set it as URI var.
		$redirect->setFragment($this->input->getString('fragment'));

		// Delete POST data from user session as it is not required anymore
		if (property_exists($this->user, 'formData'))
		{
			$this->user->__unset('formData');
		}

		// Execute redirect.
		if ($redirect->getVar('layout') == 'list')
		{
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
		}
		else
		{
			header('Location: ' . $redirect->toString());
		}

		exit;
	}

	/**
	 * {@inheritdoc}
	 * @see View::prepareDocument
	 */
	protected function prepareDocument() : bool
	{
		parent::prepareDocument();

		if (property_exists($this, 'formName'))
		{
			$this->formName = sprintf('%s%sForm',
				(property_exists($this, 'layoutName') && isset($this->layoutName)) ? $this->layoutName : $this->get('name'),
				ucfirst(mb_strtolower($this->get('name')))
			);
		}

		// Ensure proper formData format.
		if (is_a($this->user, 'Nematrack\Entity\User'))
		{
			try
			{
				$this->formData = $this->user->__get('formData');
				$this->formData = (array) $this->formData;
			}
			catch (Exception $e)
			{
				// TODO - log error
			}
		}

		return true;
	}
}
