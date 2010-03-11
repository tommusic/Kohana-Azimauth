<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Handles authorization of users using RPXNow
 * password hashing.
 *
 * @package    Azimauth
 * @author     Tom Music (based on Auth by Kohana Team)
 */
abstract class Kohana_Azimauth {

	// Azimauth instances
	protected static $instance;

	public static function instance()
	{
		if ( ! isset(Azimauth::$instance))
		{
			// Load the configuration for this type
			$config = Kohana::config('azimauth');

			// Create a new session instance
			Azimauth::$instance = new Azimauth($config);
		}

		return Azimauth::$instance;
	}

	/**
	 * Create an instance of Azimauth.
	 *
	 * @return  object
	 */
	public static function factory($config = array())
	{
		return new Azimauth($config);
	}

	protected $session;

	protected $config;

    protected $user = NULL;

	/**
	 * Loads Session and configuration options.
	 *
	 * @return  void
	 */
	public function __construct($config = array())
	{
		$this->config = $config;
        $this->session = Session::instance($config['session_type']);
	}

	/**
	 * Uses the token returned from an RPX call to retrieve identifiers for the user.
	 *
	 * @param   string   token POSTed to our script by RPX
	 * @return  array   identifiers returned by RPX
	 */
    protected function _get_identifiers($token)
    {
        $options = array
        (
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POST => TRUE,
            CURLOPT_POSTFIELDS => array
            (
                'token' => $token,
                'apiKey' => $this->config['rpx_api_key'],
                'format' => 'json'
            ),
            CURLOPT_HEADER => FALSE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
        );        

        try
        {
            $raw_json = Remote::get('https://rpxnow.com/api/v2/auth_info', $options);
        }
        catch (Kohana_Exception $e)
        {
            // The CURL call to rpxnow had a problem. Do we need more visible messaging for this?
            return FALSE;
        }

        $auth_info = json_decode($raw_json, TRUE);
        
        if ($auth_info['stat'] == 'ok') {
            $profile = $auth_info['profile'];
            $identifiers = array
            (
                'identifier' => $profile['identifier'], // Guaranteed to be present
                'displayname' => Arr::get($profile, 'displayName', NULL), // Optional
                'email' => Arr::get($profile, 'email', NULL), // Optional
            );
            return $identifiers;
//        If there needs to be more visible messaging for a failure to validate, put it here.
//        } else {
//			throw new Kohana_Azimauth_Exception($auth_info['err']['msg']);
        }
    }

	/**
	 * Checks the session and cookie for valid tokens with which to load and return a user.
	 *
	 * @return  User
	 */
    public function get_user()
    {
        if (!$this->user)
        {
            $session_token = $this->session->get($this->config['session_key']);
            $cookie_token = cookie::get($this->config['cookie_key']);

    	    $token = ORM::factory('user_token');

            if ($session_token)
            {
        		$token = ORM::factory('user_token')
        		            ->where('token', "=", $session_token)
        		            ->find();
            }

            if ((!$token->loaded()) AND ($cookie_token))
            {
        		$token = ORM::factory('user_token')
        		            ->where('token', "=", $cookie_token)
        		            ->find();
            }

            if ($token->loaded())
            {
                if ($token->user->loaded())
                {
                    $this->user = $token->user;
                    return $this->user;
                }
            }
        }
        else
        {
            return $this->user;
        }
    }

	/**
	 * Uses the RPX-supplied token to get identifying information to load or create a corresponding user.
	 *
	 * @param   array   the token RPX gave us for retrieving the user's identifier
	 * @return  User
	 */
    public function login($token)
    {
        $identifiers = $this->_get_identifiers($token);
        if (!$identifiers) return FALSE;
        
		$user = ORM::factory('user')
		            ->where('identifier', "=", $identifiers['identifier'])
		            ->find();
        if (!$user->loaded())
        {
    		$user = ORM::factory('user');
    		$user->identifier = Arr::get($identifiers, 'identifier', NULL);
    		$user->displayname = Arr::get($identifiers, 'displayname', NULL);
    		$user->email = Arr::get($identifiers, 'email', NULL);
            if ($user->check())
            {
        		$user->save();
        		$user->add('roles', ORM::factory('role', array('name' => 'login')));
            }
        }
        if ($user->loaded())
        {
    		if ($user->has('roles', ORM::factory('role', array('name' => 'login'))))
    		{
                $user->login_count++;
                $user->last_login = time();
        		$user->save();

                $token = ORM::factory('user_token');
                $token->user = $user;
        		$token->expires = time() + $this->config['lifetime'];
        		$token->save();
        		
    			cookie::set($this->config['cookie_key'], $token->token, $this->config['lifetime']);
        		$this->session->regenerate();
        		$_SESSION[$this->config['session_key']] = $token;

    		    return $user;
    		}
    		else
    		{
    			throw new Kohana_Azimauth_Exception('banned_identifier');
    		}
        }
		else
		{
			throw new Kohana_Azimauth_Exception('invalid_identifier');
		}
    }

	/**
	 * Clears the session and cookie of tokens, and deletes the tokens from the DB.
	 *
	 * @return  void
	 */
    public function logout()
    {
        $session_token = $this->session->get($this->config['session_key']);
        $cookie_token = cookie::get($this->config['cookie_key']);

		if ($session_token)
		{
    		$this->session->delete($this->config['session_key']);
    		$this->session->regenerate();
			$token = ORM::factory('user_token', array('token' => $session_token));
			if ($token->loaded()) $token->delete();
		}

		if ($cookie_token)
		{
			cookie::delete($this->config['cookie_key']);
			$token = ORM::factory('user_token', array('token' => $cookie_token));
			if ($token->loaded()) $token->delete();
		}
    }
    
} // End Azimauth
