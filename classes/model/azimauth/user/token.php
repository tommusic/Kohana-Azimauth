<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Azimauth_User_Token extends ORM {

    protected $_primary_key = 'token_hash';

	// Relationships
    protected $_belongs_to = array('user' => array('model' => 'user', 'foreign_key' => 'identifier'));

    protected $_ignored_columns = array('token');
    protected $_created_column = array('column' => 'created', 'format' => TRUE);
    protected $_updated_column = array('column' => 'updated', 'format' => TRUE);
    	
	// Current timestamp
	protected $_now;

	/**
	 * Handles garbage collection and deleting of expired objects.
	 */
	public function __construct($id = NULL)
	{
		parent::__construct($id);

		// Set the now, we use this a lot
		$this->_now = time();

		if (mt_rand(1, 100) === 1)
		{
			// Do garbage collection
			$this->delete_expired();
		}

		if ($this->expires < $this->_now)
		{
			// This object has expired
			$this->delete();
		}
	}

	/**
	 * Overload saving to set the created time and to create a new token
	 * when the object is saved.
	 */
	public function save()
	{
		if ($this->loaded() === FALSE)
		{
			// Set the hash of the user agent
			$this->user_agent = sha1(Request::$user_agent);
		}

		return parent::save();
	}

	/**
	 * Deletes all expired tokens.
	 *
	 * @return  void
	 */
	public function delete_expired()
	{
		// Delete all expired tokens
		DB::delete($this->_table_name)
			->where('expires', '<', $this->_now)
			->execute($this->_db);

		return $this;
	}

} // End Azimauth User Token Model