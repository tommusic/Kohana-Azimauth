<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Azimauth is an authentication system to use with Janrain's RPX service.
 *
 * @package    Azimauth
 * @category   Base
 * @author     Tom Music <tommusic@tommusic.net>
 * @copyright  (c) 2011 Tom Music
 * @license    MIT
 */
class Azimauth_Auth {

	/**
	 * @param  Azimauth
	 */
	protected static $instance;

	/**
	 * Get the Azimauth instance. If the instance has not yet been created,
	 * a new instance will be created and returned.
	 *
	 * @return  Azimauth
	 */
	public static function instance()
	{
		if ( ! isset(Azimauth::$instance))
		{
			// Load the configuration for this type
			$configuration = Kohana::config('azimauth')->as_array();

			// Create a new Azimauth instance
			Azimauth::$instance = new Azimauth($configuration);
		}

		return Azimauth::$instance;
	}

	/**
	 * @param  array  configuration settings
	 */
	public $config = array();

	/**
	 * Apply configuration and load current user, if applicable.
	 *
	 * @param  array  configuration settings
	 */
	public function __construct(array $config = array())
	{
		$this->config = $config;
		$this->user = $this->_get_user();
        if ($this->user->is_enabled != 1) {
            $this->logout(TRUE);
        }
    }

	/**
	 * @param  User  the currently logged-in user
	 */
	public $user;

	/**
	 * Takes a string value for a login token and returns a hash of it.
	 * This is to make it so that a compromised DB doesn't allow access as any user.
	 *
	 * @param   string   login token to be stored in the cookie
	 * @param   string   user identifier as stored in the DB
	 * @param   string   ID of the user record in the DB
	 * @return  string  hashed token
	 */
    protected function _create_token_hash($token, $identifier, $user_id) {
        
        // Hash repeated up to 43 times, depending on user_id.
        // This increases the universe of differences, and the difficulty of cracking.
        $iterations = ($user_id % 42) + 1;

		do {
			$hashed_token = hash_hmac("sha256", $token . $identifier, $this->config['hmac_key']);
		} while(--$iterations > 0);

        return $hashed_token;
    }
    
	/**
	 * Checks the cookie for a valid token, and returns the user if applicable.
	 * Returns a blank user object if no user is currently logged-in.
	 *
	 * @return  User
	 */
	protected function _get_user()
	{
		$cookie_identifier = Cookie::get('login_identifier');
		$cookie_token = Cookie::get('login_token');

		if (($cookie_token) AND ($cookie_identifier)) {
            $cookie_user = ORM::factory('user', $cookie_identifier);
            if ($cookie_user->loaded()) {
                $cookie_token_hash = $this->_create_token_hash($cookie_token, $cookie_identifier, $cookie_user->id);
    			$token = ORM::factory('user_token', $cookie_token_hash);
    			if (($token->loaded()) AND ($token->user_agent == sha1(Request::$user_agent))) {
    				if ($token->user->loaded())	{
    					return $token->user;
    				}
    			}
            }
		}
        return ORM::factory('user');
	}

	/**
	 * Uses the token returned from an RPX call to retrieve identifiers for the user.
	 *
	 * @param   string   token POSTed to the site by RPX
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
			// There was a problem using our POSTback token to get user details from the server.
			// The user has not been logged-in successfully, and we need to display an appropriate error.
			throw new Azimauth_Exception("Error during cURL to RPX for identifiers");
		}

		$auth_info = json_decode($raw_json, TRUE);
    
		if ($auth_info['stat'] == 'ok')
		{
			$profile_data = $auth_info['profile'];
			$name_data = Arr::get($profile_data, 'name', array());
			$identifiers = array
			(
				'identifier' => $profile_data['identifier'], // Guaranteed to be present
				'provider' => Arr::get($profile_data, 'providerName', NULL), // Optional

				'displayname' => Arr::get($profile_data, 'displayName', NULL), // Optional
				'formattedname' => Arr::get($name_data, 'formatted', NULL), // Optional
				'familyname' => Arr::get($name_data, 'familyName', NULL), // Optional
				'givenname' => Arr::get($name_data, 'givenName', NULL), // Optional
				'preferredusername' => Arr::get($profile_data, 'preferredUsername', NULL), // Optional

				'email' => Arr::get($profile_data, 'email', NULL), // Optional
				'url' => Arr::get($profile_data, 'url', NULL), // Optional
				'photo' => Arr::get($profile_data, 'photo', NULL), // Optional
			);
			return $identifiers;
		} else {
			throw new Azimauth_Exception('RPX says "' . $auth_info['err']['msg'] . '"');
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
	    if ($token == '') throw new Azimauth_Exception('RPX token was empty or did not exist in POST data');

	    $user_details = $this->_get_identifiers($token);
		$user = ORM::factory('user', $user_details['identifier']);

        // If the user doesn't exist in the DB yet, create it.
		if (!$user->loaded())
		{
			$user = ORM::factory('user')->values($user_details);
			if ($user->check())
			{
				$user->save();
			} else {
    			throw new Azimauth_Exception('New user details failed to validate');
			}
    		$user = ORM::factory('user', $user_details['identifier']);
		}

        // Update the login count for this user, and then create/store a login token.
		if ($user->loaded())
		{
            if ($user->is_enabled == '1') {
    			$user->login_count++;
    			$user->last_login = time();
    			$user->save();
    			$this->user = $user;

    			$token = ORM::factory('user_token');
    			$token->user = $user;
    			$token->expires = time() + $this->config['lifetime'];
        		$token->token = text::random('alnum', 32);
        		$token->token_hash = $this->_create_token_hash($token->token, $this->user->identifier, $this->user->id);
			    $token->save();
    		
    			Cookie::set('login_identifier', $user->identifier, $this->config['lifetime']);
    			Cookie::set('login_token', $token->token, $this->config['lifetime']);

                return $this->user;
            } else {
                // User has been banned
                $this->user = ORM::factory('user');
                return $this->user;
            }
		}
		else
		{
			throw new Azimauth_Exception('The returned identifier was not found in the DB');
		}
	}

	/**
	 * If a valid login token exists in the cookie, delete it from the DB.
	 *
	 * If $logout_all is TRUE, and the current token is valid, remove all tokens
	 * for this user from the DB.
	 *
     * @param   boolean     remove all tokens for this user
	 * @return  User        this should always be a brand-new user object
	 */
	public function logout($logout_all = FALSE)
	{
        if ($this->user->loaded()) {
    		if ($cookie_token = Cookie::get('login_token')) {
                if ($logout_all) {
                    $result = ORM::factory('user_token')
                                ->where('identifier', '=', $this->user->identifier)
                                ->delete_all();
                } else {
                    $cookie_token_hash = $this->_create_token_hash($cookie_token, $this->user->identifier, $this->user->id);
        			$token = ORM::factory('user_token', $cookie_token_hash);
        			$token->delete();
                }
    			Cookie::delete('login_token');
    			Cookie::delete('login_identifier');
    		}
            $this->user = ORM::factory('user');
            return $this->user;
        }
	}
    
} // End Azimauth
