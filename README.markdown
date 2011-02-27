# Azimauth


## What is it?

Azimauth is an authentication module for the [Kohana PHP framework](http://kohanaframework.org/) that uses [Janrain's RPX service](https://rpxnow.com/) for user credentials instead of requiring you to handle account creation, etc. I've developed and implemented it using v3.09 of Kohana, and haven't tried it on v3.1+ yet.

Janrain is now changing the name of RPX to "Janrain Engage", but I'm going to save time and keep calling it RPX in this document. [RPX](https://rpxnow.com/) lets you configure which of a number of services a user can use to authenticate for your site. For example, users could login to your site using their account on AOL, Yahoo, Facebook, Twitter, LinkedIn, Google, MySpace, Windows Live, PayPal, OpenID, LiveJournal, or Wordpress. You would never have to store their password on your server, handle password reset emails, or any of that stuff.

## Requirements

Before we move on: Azimauth requires the `Database` and `ORM` modules. You can use other modules to access the database, too, but Azimauth uses ORM.

## A quick overview of how this works when it's set up

The user clicks a "Login" link on your site and is presented with buttons for each service that they can use to login. You can pick what services are in this list.

Once the user has authenticated with their choice of provider, RPX will send an identifier back to your site. This gets stored in the database, and is essentially a username (though it is ugly, so the user should never see it or be asked to type it). You'll receive this same identifier each time the user authenticates with that third-party via RPX. This gets stored in the DB, along with a `displayname`, `email` address (when available), and the `provider` that was used for authentication. These go in the corresponding database fields.

When the user is authenticated, a token is stored in the cookie for your site that allows them to stay logged-in until they choose to log out. There is no "Remember Me" checkbox; remembering is the default.

At logout time you can provide the user the option to log out of only this computer, or from all computers in which they are logged in. Or not. It's up to you.

Let's get to setting this up...

## The Set-Up

First, you'll need to set up an account at [RPXNow.com](https://rpxnow.com/). They've got a free plan option that is limited to 2500 unique users. Lame, I know. I hope that by the time I get to that point I would know if I want to start paying for it or not.

Once you've got your RPX account set up, you can start configuring this module (`config/azimauth.php`):

The length of time that the authentication token should stay valid before the user needs to re-authenticate, 60 (seconds) * 60 (minutes) * 24 (hours) * 120 (days) = 10368000 seconds:

        'lifetime' => 10368000,

The API key that RPX provides for you, which can be found on your control panel at RPXNow.com:    	

        'rpx_api_key' => 'API_KEY_GOES_HERE',
    	    	
The key to use in building the hashed version of login tokens. Make this something quite unguessable:

        'hmac_key' => 'HMAC_KEY_GOES_HERE',

Now, let's see some code...

## Common Usage

Getting the instance of Azimauth automatically checks if the user is logged-in.

    $user = Azimauth::instance()->user;
    
The result of this will be an Azimauth_User model object. You can check if there is a logged-in user by looking at the state of `$user->loaded()`. If it's TRUE, there is a logged-in user. If FALSE, no logged-in user. Simple. If you want to have this available in a bunch of controllers by default, you might create and extend a base controller that has `$user` as a variable that gets populated in the `before()` function. That's what I've done.

Logging in a user tends to be done in a controller action that is designated to receive POST data from the RPX service. That controller might have some code that looks like this:

    if ($_POST)	{
        try
        {
            $user = Azimauth::instance()->login(Arr::get($_POST, 'token', ''));
        }
        catch (Azimauth_Exception $e)
        {
            Kohana::$log->add('LOGIN ERROR', $e->getMessage());
        }
    }

If the login executes successfully, `$user` will be set to the currently active user. So too will `Azimauth::instance()->user`. If it fails, an exception will be raised. The code above logs this exception, but it is up to you to determine what sort of error you want to report to the user.

To log out a user:

    $user = Azimauth::instance()->logout();

The result of this should be that `$user` gets set to an empty Azimauth_User model object.

Similarly, to log-out the current user on *all* computers:

    $user = Azimauth::instance()->logout(TRUE);

Should it ever be necessary to completely ban a user: use your favorite DB administration tool to find the row in the `user` table and change the `is_enabled` value from 1 to 0. This will make the user unable to login, and will automatically log them out on any other computers they use to access their account. To reinstate them simply change `is_enabled` back to 1.

Similarly, you can use the `is_admin` field to mark users that should have administrative privileges. The module doesn't use this in any way; how to act on this property is up to you!

## A Note on User Models

The `Azimauth_User` model mentioned in the last section expects a table named `users` in the DB, and stores all of its information there. You're welcome to extend `Azimauth_User` or to create your own User model that uses that table, and you can add all sorts of extra columns to the table for your purposes. Things should still work fine for most changes.

## Interface Elements

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

## Database Table Definitions

Here's how you'll get your DB tables set up for this.

    CREATE TABLE `users` (
        `id` int(11) unsigned not null auto_increment,
        `identifier` varchar(255) not null,
        `is_enabled` tinyint(1) unsigned default '1',
        `is_admin` tinyint(1) unsigned default '0',
        `provider` varchar(25),
        `displayname` varchar(255),
        `email` varchar(255),
        `login_count` int(10) unsigned not null,
        `last_login` int(10) unsigned not null,
        `created` int(10) unsigned,
        `updated` int(10) unsigned,
        PRIMARY KEY (`id`),
        UNIQUE KEY (`identifier`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

    CREATE TABLE `user_tokens` (
        `id` int(11) unsigned not null auto_increment,
        `identifier` varchar(255) not null,
        `token_hash` varchar(100),
        `user_agent` varchar(40) not null,
        `created` int(10) unsigned not null,
        `expires` int(10) unsigned not null,
        PRIMARY KEY (`id`),
        KEY `token_hash` (`token_hash`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
