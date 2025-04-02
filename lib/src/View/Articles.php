<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View\Lizt as ListView;
use function is_array;
use function is_object;


/**
 * Class description
 */
class Articles extends ListView
{
	use \Nematrack\Traits\View\Articles;

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

		// Access control. If a user's flags don't satisfy the minimum requirement access is prohibited.
		// Role "worker" is the minimum requirement to access an entity, whereas the role(s) to access a view may be different.
		if ($this->user->getFlags() < \Nematrack\Access\User::ROLE_WORKER)
		{
			$redirect = new Uri($this->input->server->getUrl('HTTP_REFERER', $this->input->server->getUrl('PHP_SELF') . '?hl=' . $this->language));

			Messager::setMessage([
				'type' => 'notice',
				'text' => Text::translate('COM_FTK_ERROR_APPLICATION_VIEW_ACCESS_DENIED_TEXT', $this->language)
			]);

			http_response_code('401');

			header('Location: ' . $redirect->toString());
			exit;
		}

		// Define list filter to apply.
		/*$filter = ($this->user->isGuest() || $this->user->isCustomer() || $this->user->isSupplier())
			? $this->input->getString('filter', (string) ListModel::FILTER_ALL)		// initially loads all items
			: $this->input->getString('filter', (string) ListModel::FILTER_ACTIVE);	// initially loads only active items*/
		$filter = $this->input->getString('filter', (string) ListModel::FILTER_ACTIVE);

		// Load contents limited by access rights.
		switch (true)
		{
			// Company members may see all projects.
			case ($this->input->getWord('task') === 'search' &&
				 !$this->user->isGuest()    &&
				 !$this->user->isCustomer() &&
				 !$this->user->isSupplier() &&
				  $this->user->getFlags()   >= \Nematrack\Access\User::ROLE_PROGRAMMER
			) :
				// FIXME - in model replace function 'getList' with this new implementation and test all scripts
				$list = $this->model->getList([
					'artID'  => $this->input->getInt('aid', $this->input->getInt('artID')),
					'search' => $this->input->post->getString('searchword') ?? $this->input->get->getString('searchword'),
					'filter' => $filter
				]);
			break;

			default :
				$list = [];
		}

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
	 */
	public function doSearch(string $q = null, string $redirect = null) : ?array
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
		$status = $this->model->findArticles([
			'artID'  => $this->input->getInt('aid', $this->input->getInt('artID')),
			'search' => $this->input->post->getString('searchword') ?? $this->input->getString('searchword'),
			'filter' => $this->input->getString('filter', (string) ListModel::FILTER_ALL)
		]);

		// An error occurred. Dump user input into user session and redirect to the form.
//		if (is_null($status) || (is_bool($status) && false === $status))
		if (!$status)
		{
			$this->user->__set('formData', $post);

			header('Location: ' . $this->getRoute());
			exit;
		}
		// We have a search result.
		else
		{
			if (is_array($status) || is_object($status))
			{
				if (is_object($status))
				{
					$status = (array) $status;    // force type
				}

				// 1 item found.
				if (count($status) == 1)
				{
					$status = (array) current($status);

					// Extract data into accessible variables.
					extract($status);

					$isRestricted = ($archived == '1' || $blocked == '1' || $trashed == '1' || (int) $deleted_by > '0');
					$isDeleted    = ($trashed == '1' || (int) $deleted_by > '0');

					$goTo = new Uri('/index.php');
					$goTo->setVar('hl', $this->language);
					$goTo->setVar('view', 'article');
					$goTo->setVar('layout', 'item');
					$goTo->setVar('aid', ArrayHelper::getValue($status, 'artID', 0, 'INT'));
					$goTo->setVar('return', base64_encode($redirect->toString()));

					if (!$isRestricted || ($isRestricted && !$isDeleted) || $this->user->getFlags() >= \Nematrack\Access\User::ROLE_ADMINISTRATOR)
					{
						header('Location: ' . $goTo->toString());
						exit;
					}
				}
			}
		}

		return $status;
	}
}
