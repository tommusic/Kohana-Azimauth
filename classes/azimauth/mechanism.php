<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Azimauth handles authentication and authorization of users
 *
 * @package    Azimauth
 * @category   Base
 * @author     Tom Music
 * @copyright  (c) 2011 Tom Music
 * @license    MIT
 */
abstract class Azimauth_Mechanism {

	/**
	 * Applies configuration variables to the current mechanism.
	 *
	 * @param  array  configuration
	 */
	public function __construct(array $config = NULL)
	{
		if ($config)
		{
			foreach ($config as $name => $value)
			{
				if (property_exists($this, $name))
				{
					$this->$name = $value;
				}
			}
		}
	}

} // End Azimauth_Mechanism
