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
			$configuration = Kohana::config('azimauth');

			// Create a new session instance
			Azimauth::$instance = new Azimauth($configuration);
		}

		return Azimauth::$instance;
	}

	/**
	 * @param  array  configuration settings
	 */
	public $config = array();

	/**
	 * Apply configuration.
	 *
	 * @param  array  configuration settings
	 */
	public function __construct(array $config = array())
	{
		$this->config = $config;
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
// If there needs to be more visible messaging for a failure to validate, put it here.
//		} else {
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
			$user = ORM::factory('user')
				->values($identifiers);
//			$user->identifier = Arr::get($identifiers, 'identifier', NULL);
//			$user->displayname = Arr::get($identifiers, 'displayname', NULL);
//			$user->email = Arr::get($identifiers, 'email', NULL);
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
	 * If a valid login token exists in the cookie, delete it from the DB.
	 *
	 * If $logout_all is TRUE, and the current token is valid, remove all tokens
	 * for this user from the DB.
	 *
     * @param   boolean     remove all tokens for this user
	 * @return  void
	 */
	public function logout($logout_all = FALSE)
	{
		if ($cookie_token_value = cookie::get($this->config['cookie_key'])) {
			$token = ORM::factory('user_token', array('token' => $cookie_token_value));
		}

        if ($token->loaded()) {
            if ($logout_all) {
                $result = ORM::factory('user', $token->user_id)->tokens->delete_all();
            } else {
                $result = $token->delete();
            }
        }
	}
    
} // End Azimauth
