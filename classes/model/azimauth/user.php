<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Azimauth_User extends ORM {

    protected $_primary_key = 'identifier';

	// Relationships
    protected $_has_many = array(
        'tokens' => array(
            'model' => 'user_token',
            'foreign_key' => 'identifier'
        )
    );

	// Filters
	protected $_filters = array
	(
		'identifier'    => array
		(
			'trim'    		 => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
/*		'provider'    => array
		(
			'trim'    		 => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'displayname'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'formattedname'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'familyname'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'givenname'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'preferredusername'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'url'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'photo'    => array
		(
			'trim'      => NULL,
			'htmlspecialchars' => array(ENT_QUOTES),
		),
		'email'    => array
		(
			'trim'      => NULL,
		), */
	);

	// Validation rules
	protected $_rules = array
	(
		'identifier'		=> array
		(
			'not_empty'		=> NULL,
			'min_length'		=> array(4),
			'max_length'		=> array(256),
		),
/*		'displayname'		=> array
		(
			'max_length'		=> array(256),
		),
		'email'		=> array
		(
			'validate::email'		=> NULL,
			'max_length'		=> array(256),
		), */
	);

} // End Azimauth User Model