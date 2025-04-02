<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use Nematrack\Messager;
use Nematrack\Text;
use Nematrack\View\Item as ItemView;
use function is_a;
use function is_null;

/**
 * Class description
 */
class Fmea extends ItemView
{
	use \Nematrack\Traits\View\Fmea;

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

		// Prepare view for rendering.
		$this->prepareDocument();

		// Get id of item to fetch from the database.
		$id = $this->input->getInt('id', $this->input->getInt($this->getIdentificationKey()));

		// Fetch item.
		$this->item = (isset($id) && $id)
			? $this->model->getItem($id, $this->model->get('language'))
			: $this->model->getItem(0,   $this->model->get('language'));

		// Access control.
		$this->checkAccess();

		// Prepare item for display.
		if (is_a($this->item, sprintf('Nematrack\Entity\%s', ucfirst(mb_strtolower($this->get('name'))))) &&
			$this->item->get($this->item->getPrimaryKeyName()) &&
			$this->item->get('name')
		) {
			// Do some stuff.
		}
	}

	/**
	 * {@inheritdoc}
	 * @see Item::__construct
	 */
	public function getIdentificationKey(): string
	{
		return $this->identificationKey = 'fid';
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveAdd
	 */
	public function saveAdd(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
		// TODO: Implement saveAdd() method.
	}

	/**
	 * {@inheritdoc}
	 * @see Item::saveEdit
	 */
	public function saveEdit(string $redirect = '') : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
		// TODO: Implement saveEdit() method.
	}

	/**
	 * {@inheritdoc}
	 * @see Item::checkAccess
	 */
	protected function checkAccess() : void
	{
		parent::checkAccess();

		// If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \Nematrack\Access\User::ROLE_WORKER)
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language) . ($this->user->isProgrammer() ? ' (#13)' : '')
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// An (empty) item may be requested by another resource and must not be rendered.
		// Hence, we return right here.
		if (!isset($this->layoutName)  ||							// item requested without layout
			$this->layoutName == 'add' ||							// new item to be created
			!$this->item->get( $this->item->getPrimaryKeyName() )   // empty item instance requested to use any of its methods
		) return;

		// Block the attempt to access a non-existing item.
		// If no item id has been passed then chances are that any view method shall be executed. Abort further loading.
		// Important:  We're checking for layout 'add' because whenever this layout is requested there is no item id.
		//             Hence, method <pre>prepareDocument()</pre> would not execute.
		if (isset($this->layoutName) && isset($this->item) && is_null( $this->item->get( $this->item->getPrimaryKeyName() ) ))
		{
			$redirect = new Uri(
				$this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language)
			);

			Messager::setMessage([
				'type' => 'notice',
				'text' => sprintf(
					Text::translate(
						mb_strtoupper(
							sprintf('COM_FTK_HINT_%s_HAVING_ID_X_NOT_FOUND_TEXT', $this->get('name'))
						),
						$this->language
					),
					$this->item->get(
						$this->item->getPrimaryKeyName()
					)
				)
			]);

			http_response_code('404');

			header('Location: ' . $redirect->toString());
			exit;
		}
	}
}
