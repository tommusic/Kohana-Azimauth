rpx_auth (identifier from provider)
native_register (new email address, password)
native_auth (existing email/password)
anon_create (no identifier, no email/password)
anon_merge (with rpx or native)
logout (all)

login($type, array $data) {
    // Build the class name path
    $mechanism = 'Azimauth_Mechanism_' . $type;

    // Register the class for this prefix
    return new $mechanism($data);

    /* Essentially...
    switch($type) {
        case "rpx":
            Azimauth_Drivers_RPX($data['token']);
        return;

        case "native":
            Azimauth_Drivers_Native($data['username'], $data['password']);
        return;

        case "anonymous":
            Azimauth_Drivers_Anonymous();
        return;
    }
    */
}


# Azimauth

## Introduction

Azimauth is an auth(-entication/-orization) module for the [Kohana PHP Framework](http://kohanaframework.org/). It was specifically built with v3.0.9, but may be compatible with other versions.

This module allows for three different kinds of authentication mechanisms: [RPX](https://rpxnow.com/), Native, and Anonymous.

*   The RPX mechanism uses [Janrain's RPX service](https://rpxnow.com/), which allows users to authenticate for your site using their account on another service. For example, someone may choose to have GMail or LiveJournal vouch that they are a real person. GMail or LiveJournal would then send back a response to your site identifying the user and validating that they have an account.

*   The Native mechanism is a traditional email and password registration and login system.

*   The Anonymous mechanism is for creating an account without requiring any authentication at all. This is good for systems where you want users to be able to create and manage objects without needing to register. On the downside, losing their session means losing their authorization to edit the stuff they've previously created.

    In addition, an account created under the Anonymous mechanism can be "upgraded" to the RPX or Native mechanisms

## Initial Setup

The first step is to select which mechanisms you want to use. You can do this by copying and modifying the sample configuration file in the "config" folder of the module.

First, set how long authentication sessions should last (in seconds). This one is 60 x 60 x 24 x 14 = two weeks.

            'lifetime' => 1209600,

Next, the key within the cookie that will correspond to the authentication token. This will help keep the login more persistent.

            'cookie_key' => 'azimauth_token',

And the key for the token within the session...

            'session_key' => 'azimauth_token',

What kind of session to use for storing this information.

            'session_type' => 'database',

Now we'll get into configuring the authentication mechanisms that will be available. Completely comment out any that you don't want to use.

            'mechanisms' => array(
                'RPX' => array(

The API key that RPX provides you in your account dashboard at [RPXNow.com](rpxnow.com).

                	'api_key' => '',

The subdomain at [RPXNow.com](rpxnow.com) that is set up to take your requests.

                	'rpx_subdomain' => '',

The URL corresponding to a controller that can handle RPX's callback POST with the user's login token.

                	'callback_url' => URL::base(FALSE, TRUE) . 'sessions/create',
                ),
                'Native' => array(
                ),
                'Anonymous' => array(
                )
            )

## Usage Patterns

### Get Current User

To load an instance of Azimauth and get the current user:

        $user = Azimauth::instance()->get_user();
        
If there is a user with a valid token, that will have returned an ORM object from the DB with their data loaded. If not, the object will be NULL.

        if ($user)
        {
            // User was logged in
        }
        else
        {
            // User was not logged in
        }

### Logout Current User

Regardless of what login mechanism was used, this will log out the current user. If there is no user, this won't do anything. The return value will always be TRUE.

        $result = Azimauth::instance()->logout();
        $user = Azimauth::instance()->get_user(); // This will now return NULL

### Getting More Specific

The previous two Azimauth behaviors (`get_user()` and `logout()`) operate the same whether or not an authorization mechanism is provided. Other user management behaviors need more specificity...

### RPX User Management

In the controller action that receives the callback POST from the RPX service, you will use the following method to log the user in:

        $user = Azimauth::instance('RPX')->login($rpx_token);
        
*Note that we've specified that we want an Azimauth instance that uses the RPX mechanism for authentication activity.*

The return value will be the same as for the `get_user()` method: an Azimauth_User ORM object if successful, or NULL on failure.

It is important to note that this login mechanism will create the user in the database if they don't already exist. Logging in via RPX is the same as registering.

### Native User Management

Native users require a little bit more handling. Call this function to create a new user under the Native mechanism:

        $user = Azimauth::instance('Native')->register($email, $password);

And to log in a Native user:

        $user = Azimauth::instance('Native')->login($email, $password);

To log in a user without a password (such as from an "I forgot my password" email):

        $user = Azimauth::instance('Native')->force_login($email);

To change a logged-in user's password:

        $user = Azimauth::instance()->get_user();
        $result = $user->set_password($password);

## Quick Start

Once enabled, the simplest usage of this module would happen in a controller like this one:

    class Controller_Sessions extends Controller_Azimauth {
    	public $template = 'templates/base';

		public function action_index()
		{
    		$this->template->content = View::factory('userinfo')->set('user', $this->user);
		}
	
		public function action_create()
		{
	        if ($_POST)
	        {
	            if(isset($_POST['token']))
	            {
	                if ($user = Azimauth::instance()->login($_POST['token']))
	                {
	                    Request::instance()->redirect('/sessions');
	                }
	            }
	            echo "<p>An error occurred when logging you in. Please try again.</p>";
	        }
	        echo View::factory('azimauth/link');
	        echo View::factory('azimauth/script');
		}

		public function action_destroy()
		{
	        Azimauth::instance()->logout();
	        Request::instance()->redirect('/');
		}
    }

The "userinfo" view would likely present differential information depending on whether isset($user), which is essentially a check for whether or not we have a logged-in user.

## Methods

The key methods of the module:

* login($token)

Uses the token that RPX sends back to retrieve the user's identifying information. Returns the user if successful, or FALSE otherwise.

* get_user()

If the session or cookie have user_tokens stored to auto-login a user, return them. Otherwise, return FALSE.

* login()

Clear out the session and cookie user_tokens to remove the user.

## Conventions

The Azimauth module has a few conventions hard-wired at the moment:

* You must have ORM enabled; Azimauth uses this for the models involved
* Sessions are forced to live in the DB (you might change this, but I prefer it)

## Database tables

Here's how you'll get your DB tables set up for this. Note that if you want to store some of the additional fields retrieved from authorization requests, you simply need to add the properly named field to the 'users' table!

    CREATE TABLE `roles` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(32) NOT NULL,
      `description` varchar(255) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_name` (`name`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

    INSERT INTO `roles` (`id`, `name`, `description`) VALUES(1, 'login', 'Login privileges, granted after account confirmation');
    INSERT INTO `roles` (`id`, `name`, `description`) VALUES(2, 'admin', 'Administrative user, has access to everything.');

    CREATE TABLE `roles_users` (
      `user_id` int(10) unsigned NOT NULL,
      `role_id` int(10) unsigned NOT NULL,
      PRIMARY KEY (`user_id`,`role_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

    CREATE TABLE `sessions` (
      `session_id` varchar(24) CHARACTER SET latin1 NOT NULL,
      `last_active` int(10) unsigned NOT NULL,
      `contents` text CHARACTER SET latin1 NOT NULL,
      PRIMARY KEY (`session_id`),
      KEY `last_active` (`last_active`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

    CREATE TABLE `users` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `identifier` varchar(255) NOT NULL,
      `displayname` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `login_count` int(10) unsigned NOT NULL,
      `last_login` int(10) unsigned NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

    CREATE TABLE `user_tokens` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) unsigned NOT NULL,
      `user_agent` varchar(40) NOT NULL,
      `token` varchar(32) NOT NULL,
      `created` int(10) unsigned NOT NULL,
      `expires` int(10) unsigned NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_token` (`token`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
