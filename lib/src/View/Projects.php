<?php
/* define application namespace */
namespace Nematrack\View;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Joomla\Uri\Uri;
use Nematrack\Messager;
use Nematrack\Model\Lizt as ListModel;
use Nematrack\Text;
use Nematrack\View\Lizt as ListView;

/**
 * Class description
 */
class Projects extends ListView
{
	use \Nematrack\Traits\View\Projects;

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

		// Define main group filter to apply.
		$mainGroups         = $this->model->getInstance('articles', ['language' => $this->language])->getMainGroups();
		$mainGroupsFilter   = (array) $this->input->getWord('mgrp');
		$mainGroupsFiltered = [];
		$list               = [];

		// Load contents limited by access rights.
		switch (true)
		{
			// Customers/Suppliers require a special treatment. They can only see what belongs to their organisation.
			case ( $this->user->isGuest() ||  $this->user->isCustomer() ||  $this->user->isSupplier()) :
				$list = $this->model->getInstance('organisation', ['language' => $this->language])->getOrganisationProjectsNEW(
					[
						'proID'  => $this->input->getInt('proid', $this->input->getInt('proID')),
						'orgID'  => $this->user->get('orgID'),
						'filter' => $filter,
						'params' => true
					]
				);
			break;

			// Company members may see all items.
			case (!$this->user->isGuest() && !$this->user->isCustomer() && !$this->user->isSupplier() && $this->user->getFlags() >= \Nematrack\Access\User::ROLE_WORKER) :
				// FIXME - in model replace function 'getList' with this new implementation and test all js
				if ($this->input->get('mgrp'))
				{
					$list = $this->model->getList(
						[
							'proID'  => $this->input->getInt('proid', $this->input->getInt('proID')),
							'orgID'  => $this->input->getInt('oid', $this->input->getInt('orgID')),
							'filter' => $filter,
							'params' => true
						]
					);
				}
			break;

			default :
				$list = [];
		}

		// Filter out selected main group(s) and their associated projects.
		if (is_countable($mainGroupsFilter) && count($mainGroupsFilter))
		{
			$mainGroupsFiltered = array_intersect_key($mainGroups, array_flip($mainGroupsFilter));

			if (count($mainGroupsFiltered))
			{
				$projectNumbers = [];

				array_walk($mainGroupsFiltered, function ($projects) use (&$projectNumbers)
				{
					$projectNumbers = array_merge($projectNumbers, array_keys($projects));
				});

				sort($projectNumbers);

				$list = array_filter($list, function ($project) use (&$projectNumbers)
				{
					return in_array($project['number'], $projectNumbers);
				});
			}
		}

		// Assign ref to loaded list data.
		$this->list = $list;

		// Assign ref to main group(s) filter.
		$this->mainGroups         = $mainGroups;
		$this->mainGroupsFilter   = $mainGroupsFilter;
		$this->mainGroupsFiltered = $mainGroupsFiltered;
	}
}
