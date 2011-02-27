<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Azimauth_User extends ORM {

    protected $_primary_key = 'identifier';

    protected $_created_column = array('column' => 'created', 'format' => TRUE);
    protected $_updated_column = array('column' => 'updated', 'format' => TRUE);

	// Relationships
    protected $_has_many = array(
        'tokens' => array(
            'model' => 'user_token',
            'foreign_key' => 'identifier'
        )
    );

} // End Azimauth User Model