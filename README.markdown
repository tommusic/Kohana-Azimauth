# Azimauth


## What is it?

Azimauth is an RPX-based authentication/authorization module for the [Kohana PHP framework](http://kohanaframework.org/). I've developed and implemented it using v3.09 of Kohana, and haven't tried it on v3.1+ yet.

[Janrain's RPX service](https://rpxnow.com/) allows users to authenticate for your site using their account on another service. For example, someone may choose to have GMail or LiveJournal handle their authorization credentials. GMail or LiveJournal would then send back a response to your site identifying the user and validating that they have an account.

The great part about this is that you can skip asking users to create an account on your site. If they already have an account with any of the providers that you make available, they can login immediately. There is never any need for your site to handle changing or resetting passwords.

## A quick overview

The user will click a button or link on your site (i.e. "Login") that will present them with the RPX login interface. This interface will have one or more services (e.g. Facebook, Yahoo, Gmail, OpenID) through which they can authenticate.

Once the user has authenticated with their choice of provider, RPX will send an identifier back to your site. This gets stored in the database, and is essentially a username (though the user will never see it or be asked to type it). You'll receive this same identifier each time the user authenticates with a third-party via RPX, and they should be unique.

When the user is authenticated, a token is stored in the cookie for your site that allows them to stay logged-in until they choose to log out. There are no "Remember Me" checkboxes. You may provide the user the option to log out of only this computer, or from all computers in which they are logged in.

Let's get to setting this up...

## Configuration

Configuration settings for this module live in `config/azimauth.php`.

    return array
    (

The length of time that the authentication token should stay valid before the user needs to re-authenticate, 60 (seconds) * 60 (minutes) * 24 (hours) * 120 (days) = 10368000 seconds:

    	'lifetime' => 10368000,
    	
The key name under which to store the authentication token in the cookie:

    	'cookie_key' => 'login_token',

The API key that RPX provides for you, which can be found on your control panel at RPXNow.com:    	
    	
    	'rpx_api_key' => 'API_KEY_GOES_HERE',
    );

Now, let's see some code.

## Common Usage

Getting the instance of Azimauth automatically checks if the user is logged-in.

    $user = Azimauth::instance()->user;
    
The result of this will be a User model object. You can check if there is a logged-in user by looking at the state of `$user->loaded()`. If it's TRUE, there is a logged-in user. If FALSE, no logged-in user. Simple.

Logging in a user tends to be done in a controller action that is designated to receive POST data from the RPX service. That controller might have some code that looks like this:

    if ($_POST)	{
        $rpx_token = Arr::get($_POST, 'token', '');
        if ($rpx_token != '')
            $user = Azimauth::instance()->login($rpx_token);
    }

    try {
        $user = Azimauth::instance()->login($rpx_token);
    } catch (Azimauth_Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }

If the login executes successfully, `$user` will be set to the currently active user. So too will `Azimauth::instance()->user`.

And finally, to log out a user:

    $user = Azimauth::instance()->logout();

The result of this should be that `$user` gets set to an empty User model object.

Similarly, to log-out the current user on *all* computers:

    $user = Azimauth::instance()->logout(TRUE);

## Additional Requirements

All of the references to returning something to be stored in `$user` in the last section refer to the Azimauth_User model that is included with the module. *This model inherits from ORM, and expects a table named `users` in your database.*

You're welcome to put as many extra columns into that table as you'd like, and build your own `User` model that uses that table and handles everything other than authentication. User profiles, user-to-object relationships, the sky is the limit. When you need to do anything specific to authentication, `Azimauth_User` will be there waiting for you.

## Interface Additions

A few interface elements will be needed to make Azimauth functional on your site. You can get both of these from the "Sign-In for Websites" link on the RPXNow control panel for your application. Go through the steps with the "Generate Code" button and you'll get the pieces you need.

If you choose to have the RPX widget embedded, you'll have some IFrame code to paste where you want it to appear. It'll look something like this:

    <iframe src="http://example-website.rpxnow.com/openid/embed?token_url=http%3A%2F%2Fexamplewebsite.com%2Flogin" scrolling="no" frameBorder="no" allowtransparency="true" style="width:400px;height:240px"></iframe>
    
If you choose to have a modal overlay dialog appear, you'll get two chunks of code. First, a Javascript include that looks like this:

    <script type="text/javascript">
      var rpxJsHost = (("https:" == document.location.protocol) ? "https://" : "http://static.");
      document.write(unescape("%3Cscript src='" + rpxJsHost +
    "rpxnow.com/js/lib/rpx.js' type='text/javascript'%3E%3C/script%3E"));
    </script>
    <script type="text/javascript">
      RPXNOW.overlay = true;
      RPXNOW.language_preference = 'en';
    </script>

And a sign-in link that will look like this:

    <a class="rpxnow" onclick="return false;" href="https://example-website.rpxnow.com/openid/v2/signin?token_url=http%3A%2F%2Fexamplewebsite.com%2Flogin"> Sign In </a>

Hopefully it's obvious where your code will look differently than these examples. And you'll probably only want to display the sign-in link when there isn't a logged-in user.

## Database tables (working on this)

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
