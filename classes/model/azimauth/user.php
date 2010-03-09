<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Azimauth_User extends ORM {

	// Relationships
	protected $_has_many = array
		(
			'user_tokens' => array('model' => 'user_token'),
			'roles'       => array('model' => 'role', 'through' => 'roles_users'),
		);

    // Filters
    protected $_filters = array
    (
        'identifier'    => array
        (
            'trim'      => NULL,
            'htmlspecialchars' => array(ENT_QUOTES),
        ),
        'displayname'    => array
        (
            'trim'      => NULL,
            'htmlspecialchars' => array(ENT_QUOTES),
        ),
        'email'    => array
        (
            'trim'      => NULL,
        ),
    );

	// Rules
	protected $_rules = array
	(
    	'identifier'		=> array
		(
			'not_empty'		=> NULL,
			'min_length'		=> array(4),
			'max_length'		=> array(256),
		),
    	'displayname'		=> array
		(
			'max_length'		=> array(256),
		),
    	'email'		=> array
		(
			'validate::email'		=> NULL,
			'max_length'		=> array(256),
		),
	);

	/**
	 * Convenience function to check this user for permissions.
	 *
	 * @param  string   role to check for
	 * @return boolean
	 */
	public function has_role($role)
	{
		if ($this->loaded())
		{
            if ($this->has('roles', ORM::factory('role', array('name' => $role))))
            {
			    return TRUE;
			}
        }
        return FALSE;
    }

} // End Azimauth User Model