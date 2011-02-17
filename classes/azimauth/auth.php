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
    }

	/**
	 * @param  User  the currently logged-in user
	 */
	public $user;

	/**
	 * Checks the cookie for a valid token, and returns the user if applicable.
	 * Returns a blank user object if no user is currently logged-in.
	 *
	 * @return  User
	 */
	protected function _get_user()
	{
		$cookie_token = Cookie::get($this->config['cookie_key']);

		if ($cookie_token) {
			$token = ORM::factory('user_token', $cookie_token);
			if ($token->loaded()) {
				if ($token->user->loaded())	{
					return $token->user;
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
			// This means our server couldn't connect to their server. Log this and warn user.
			throw new Azimauth_Exception("Something is technically wrong. We're looking into it!");
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
			throw new Azimauth_Exception($auth_info['err']['msg']);
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
        try
        {
		    $user_details = $this->_get_identifiers($token);
        }
        catch (Azimauth_Exception $e)
        {
            // Should this exception just ripple upward too?
            die($e->getMessage());
        }
        
		$user = ORM::factory('user', $user_details['identifier']);

        // If the user doesn't exist in the DB yet, create it.
		if (!$user->loaded())
		{
			$user = ORM::factory('user')->values($user_details);
			if ($user->check())
			{
				$user->save();
			} else {
    			throw new Azimauth_Exception('invalid_identifier');
			}
		}

        // Update the login count for this user, and then create/store a login token.
		if ($user->loaded())
		{
			$user->login_count++;
			$user->last_login = time();
			$user->save();
			$this->user = $user;

			$token = ORM::factory('user_token');
			$token->user = $user;
			$token->expires = time() + $this->config['lifetime'];
			$token->save();
    		
			Cookie::set($this->config['cookie_key'], $token->token, $this->config['lifetime']);

            return $this->user;
		}
		else
		{
			throw new Azimauth_Exception('invalid_identifier');
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
        if ($this->user) {
    		if ($cookie_token = Cookie::get($this->config['cookie_key'])) {
    			Cookie::delete($this->config['cookie_key']);
    			$token = ORM::factory('user_token', $cookie_token);
                if ($token->loaded()) {
                    if ($logout_all) {
                        $result = ORM::factory('user_token')
                                    ->where('identifier', '=', $token->identifier)
                                    ->delete_all();
                    } else {
                        $result = $token->delete();
                    }
                }
    		}
            $this->user = ORM::factory('user');
            return $this->user;
        }
	}
    
} // End Azimauth
