# Azimauth

An RPX-based authentication/authorization module for the [Kohana framework](http://kohanaphp.com/) (v3.0+). It borrows (very lightly) from the Kohana V3 Auth module.

[Janrain's RPX service](https://rpxnow.com/) allows users to authenticate for your site using their account on another service. For example, someone may choose to have GMail or LiveJournal vouch that they are a real person. GMail or LiveJournal would then send back a response to your site identifying the user and validating that they have an account.

The great part about this is that you can skip asking users to create an account on your site. Your site won't be the first one they ever use, and if they can just login using their (i.e.) Facebook account, they'll get started enjoying your site faster.

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
