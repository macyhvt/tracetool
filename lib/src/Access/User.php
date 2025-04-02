<?php
/* define application namespace */
namespace  \Access;

/* no direct script access */

use  \Access;
use function array_pop;
use function array_walk;

defined('_FTK_APP_') or die('403 FORBIDDEN');

/**
 * The class above is abstract and cannot be instantiated, so an extension is required.
 * Below is a simple extension named {@link Access} -- which is severely truncated for clarity.
 *
 * Notice I am defining constants, variables AND methods to use them.
 *
 * This seems like a lot of work, but we have addressed many issues, for example, using and
 * maintaining the code is easy, and the getting and setting of role values make sense.
 * With the User class, you can now see how easy and intuitive bitwise role operations become.
 */
class User extends Access
{
	//
	// These constants reflect the user groups defined in database !
	//
	// Whenever a new role is added this object must be updated too, meaning:
	// 	the role must be added as constant,
	// 	the role must be added to {@see Text} to support multi-lang environment,
	//	getter/setter must be added,
	//	the bit mask must be updated,
	//	the {@see User::__toString()} method must be updated.
	//

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_REGISTERED        =   1;	// BIT  #1 of $groups has the value   1

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_GUEST             =   2;	// BIT  #2 of $groups has the value   2 (1 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_WORKER            =   4;	// BIT  #3 of $groups has the value   4 (1+2 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_CUSTOMER          =   8;	// BIT  #4 of $groups has the value   8 (1+2+4 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_SUPPLIER          =  16;	// BIT  #5 of $groups has the value  16 (1+2+4+8 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  2.6
	 */
	public const ROLE_QUALITY_ASSURANCE =  32;	// BIT  #6 of $groups has the value  32 (1+2+4+8+16 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_DRAWER            =  64;	// BIT  #7 of $groups has the value  64 (1+2+4+8+16+32 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_MANAGER           =  128;	// BIT  #8 of $groups has the value 128 (1+2+4+8+16+32+64 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_QUALITY_MANAGER   =  256;	// BIT  #9 of $groups has the value 256 (1+2+4+8+16+32+64+128 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_ADMINISTRATOR     =  512;	// BIT #10 of $groups has the value 512 (1+2+4+8+16+32+64+128+256 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_PROGRAMMER        = 1024;	// BIT #11 of $groups has the value 1024 (1+2+4+8+16+32+64+128+256+512 + 1)

	/**
	 * The user group identifier.
	 *
	 * @var    integer
	 * @since  1.1
	 */
	public const ROLE_SUPERUSER         = 2048;	// BIT #12 of $groups has the value 2048 (1+2+4+8+16+32+64+128+256+512+1024 + 1)


	/**
	 * Returns a user's flags property.
	 *
	 * @return int|null
	 */
	public function getFlags() : ?int
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->flags;
	}


	/**
	 * Flags a user to be registered in the system.
	 *
	 * @param $value
	 */
	public function makeRegistered($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_REGISTERED, $value);
	}

	/**
	 * Returns whether a user is registered in the system.
	 *
	 * @return bool
	 */
	public function isRegistered() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_REGISTERED);
	}


	/**
	 * Flags a user as guest in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeGuest($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_GUEST, $value);
	}

	/**
	 * Returns whether a user is a guest in the system.
	 *
	 * @return bool
	 */
	public function isGuest() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_GUEST);
	}


	/**
	 * Flags a user as worker in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeWorker($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_WORKER, $value);
	}

	/**
	 * Returns whether a user is a worker in the system.
	 *
	 * @return bool
	 */
	public function isWorker() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_WORKER);
	}


	/**
	 * Flags a user as customer in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeCustomer($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_CUSTOMER, $value);
	}

	/**
	 * Returns whether a user is a customer in the system.
	 *
	 * @return bool
	 */
	public function isCustomer() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_CUSTOMER);
	}


	/**
	 * Flags a user as supplier in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeSupplier($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_SUPPLIER, $value);
	}

	/**
	 * Returns whether a user is a supplier in the system.
	 *
	 * @return bool
	 */
	public function isSupplier() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_SUPPLIER);
	}


	/**
	 * Flags a user as quality controller in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeQualityAssurance($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_QUALITY_ASSURANCE, $value);
	}

	/**
	 * Returns whether a user is a quality controller in the system.
	 *
	 * @return bool
	 */
	public function isQualityAssurance() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_QUALITY_ASSURANCE);
	}


	/**
	 * Flags a user as drawer in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeDrawer($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_DRAWER, $value);
	}

	/**
	 * Returns whether a user is a drawer in the system.
	 *
	 * @return bool
	 */
	public function isDrawer() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_DRAWER);
	}


	/**
	 * Flags a user as manager in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeManager($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_MANAGER, $value);
	}

	/**
	 * Returns whether a user is a manager in the system.
	 *
	 * @return bool
	 */
	public function isManager() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_MANAGER);
	}


	/**
	 * Flags a user as quality assurer in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeQualityManager($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_QUALITY_MANAGER, $value);
	}

	/**
	 * Returns whether a user is a quality assurer in the system.
	 *
	 * @return bool
	 */
	public function isQualityManager() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_QUALITY_MANAGER);
	}


	/**
	 * Flags a user as being an administrator in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeAdministrator($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_ADMINISTRATOR, $value);
	}

	/**
	 * Returns whether a user is an administrator in the system.
	 *
	 * @return bool
	 */
	public function isAdministrator() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_ADMINISTRATOR);
	}


	/**
	 * Flags a user as being the superuser (there can be <u>only one</u>) in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeSuperuser($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_SUPERUSER, $value);
	}

	/**
	 * Returns whether a user is the superuser in the system.
	 *
	 * @return bool
	 */
	public function isSuperuser() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_SUPERUSER);
	}


	/**
	 * Flags a user as programmer in the system.
	 *
	 * @param  $value
	 *
	 * @return void
	 */
	public function makeProgrammer($value) : void
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$this->setFlag(static::ROLE_PROGRAMMER, $value);
	}

	/**
	 * Returns whether a user is a programmer in the system.
	 *
	 * @return bool
	 */
	public function isProgrammer() : bool
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		return $this->isFlagSet(static::ROLE_PROGRAMMER);
	}


	/**
	 * Returns all available user roles as an array.
	 *
	 * @return array
	 */
	public function toArray() : array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		preg_match('#\[([^\]]*)\]#', $this->__toString(), $groups);

		$groups = array_pop($groups);
		$groups = str_ireplace(' ', ',', $groups);
		$groups = (array) explode(',', $groups);

		// Prepend 'ROLE_' to match defined roles.
		array_walk($groups, function(&$group)
		{
			$group = 'ROLE_' . $group;
		});

		return $groups;
	}

	/**
	 * Dumps the user roles array to string.
	 *
	 * @return string
	 */
	public function __toString() : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$className = trim(str_ireplace(__NAMESPACE__, '', get_class($this)), '\\/');

		return $className . ' [' .
			($this->isRegistered()       ? 'REGISTERED'         : '') .
			($this->isGuest()            ? ' GUEST'             : '') .
			($this->isWorker()           ? ' WORKER'            : '') .
			($this->isCustomer()         ? ' CUSTOMER'          : '') .
			($this->isSupplier()         ? ' SUPPLIER'          : '') .
			($this->isQualityAssurance() ? ' QUALITY_ASSURANCE' : '') .
			($this->isDrawer()           ? ' DRAWER'            : '') .
			($this->isManager()          ? ' MANAGER'           : '') .
			($this->isQualityManager()   ? ' QUALITY_MANAGER'   : '') .
			($this->isAdministrator()    ? ' ADMINISTRATOR'     : '') .
			($this->isSuperuser()        ? ' SUPERUSER'         : '') .
			($this->isProgrammer()       ? ' PROGRAMMER'        : '') .
		']';
	}
}
