<?php
/* define application namespace */
namespace Nematrack\Connectivity;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

/**
 * Interface description
 *
 * Difference between interface and abstract class:
 *
 *   Interface cannot have properties, while abstract class can.
 *   All interface methods must be public, while abstract class methods are public or protected.
 *   All methods in an interface are abstract, so they cannot be implemented in code and the abstract keyword is not necessary.
 *   A class can implement an interface while inheriting from another class at the same time.
 */
interface MachineInterface
{
	// TODO - Define public methods shared to all child classes
}
