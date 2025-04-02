<?php
/* define application namespace */
namespace Nematrack\Traits\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * Traits description
 */
trait User
{
	/**
	 * Returns whether a user's account is active.
	 *
	 * @return bool
	 */
	public function isActive() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return (false === (bool) $this->get('blocked', true));
	}


	/**
	 * Returns whether a user is allowed to create content.
	 *
	 * @return bool
	 */
	public function canCreate() : bool
	{
		return ($this->isRegistered() && $this->isActive());
	}

	/**
	 * Returns whether a user is allowed to create a new article.
	 *
	 * @return bool
	 */
	public function canCreateArticle() : bool
	{
		return (!$this->isCustomer() && $this->canCreate() && $this->isManager());
	}

	/**
	 * Returns whether a user is allowed to create a new organisation.
	 *
	 * @return bool
	 */
	public function canCreateOrganisation() : bool
	{
		return (!$this->isCustomer() && $this->canCreate() && $this->isAdministrator());
	}

	/**
	 * Returns whether a user is allowed to create a new process.
	 *
	 * @return bool
	 */
	public function canCreateProcess() : bool
	{
		return (!$this->isCustomer() && $this->canCreate() && $this->isManager());
	}

	/**
	 * Returns whether a user is allowed to create a new project.
	 *
	 * @return bool
	 */
	public function canCreateProject() : bool
	{
		return (!$this->isCustomer() && $this->canCreate() && $this->isAdministrator());
	}

	/**
	 * Returns whether a user is allowed to create a new part.
	 *
	 * @return bool
	 */
	public function canCreatePart() : bool
	{
		return (!$this->isCustomer() && $this->canCreate() && $this->isWorker());
	}

	/**
	 * Returns whether a user is allowed to create a new user.
	 *
	 * @return bool
	 */
	public function canCreateUser() : bool
	{
		return ($this->canCreate() && $this->isAdministrator());
	}


	/**
	 * Returns whether a user is allowed to edit content.
	 *
	 * @return bool
	 */
	public function canEdit() : bool
	{
		return ($this->isRegistered() && $this->isActive());
	}

	/**
	 * Returns whether a user is allowed to edit an article.
	 *
	 * @return bool
	 */
	public function canEditArticle() : bool
	{
		return (!$this->isCustomer() && $this->canEdit() && $this->isManager());
	}

	/**
	 * Returns whether a user is allowed to edit an organisation.
	 *
	 * @return bool
	 */
	public function canEditOrganisation() : bool
	{
		return ($this->canEdit() && $this->isAdministrator());
	}

	/**
	 * Returns whether a user is allowed to edit a process.
	 *
	 * @return bool
	 */
	public function canEditProcess() : bool
	{
		return (!$this->isCustomer() && $this->canEdit() && $this->isManager());
	}

	/**
	 * Returns whether a user is allowed to edit a project.
	 *
	 * @return bool
	 */
	public function canEditProject() : bool
	{
		return (!$this->isCustomer() && $this->canEdit() && $this->isAdministrator());
	}

	/**
	 * Returns whether a user is allowed to edit a part.
	 *
	 * @return bool
	 */
	public function canEditPart() : bool
	{
		return (!$this->isCustomer() && $this->canEdit() && $this->isWorker());
	}

	/**
	 * Returns whether a user is allowed to edit a user.
	 *
	 * @return bool
	 */
	public function canEditUser() : bool
	{
//		return ($this->isActive() && ($this->isProgrammer()|| $this->isSuperuser()|| $this->isAdministrator()));
		return ($this->canEdit() && $this->isAdministrator());
	}


	/*public function canDelete() : bool
	{
		return ($this->canDeleteUser() && $this->isManager());
	}

	public function canDeleteArticle() : bool
	{
		return $this->canDelete();
	}

	public function canDeleteOrganisation() : bool
	{
		return $this->canDelete();
	}

	public function canDeleteProcess() : bool
	{
		return $this->canDelete();
	}

	public function canDeleteProject() : bool
	{
		return $this->canDelete();
	}

	public function canDeletePart() : bool
	{
		return $this->canDelete();
	}

	public function canDeleteUser() : bool
	{
		return ($this->isActive() && ($this->isProgrammer()|| $this->isSuperuser()|| $this->isAdministrator()));
	}*/
}
