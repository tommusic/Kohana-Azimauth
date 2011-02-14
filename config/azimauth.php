<?php defined('SYSPATH') OR die('No direct access allowed.');

return array
(
	'lifetime' => 1209600,
	'cookie_key' => 'azimauth_token',
	'session_key' => 'azimauth_token',
	'session_type' => 'database',
    'mechanisms' => array(
/*        'RPX' => array(
            // your RPX api key
        	'api_key' => '',

            // the URL that will handle RPX tokens
        	'callback_url' => URL::base(FALSE, TRUE) . 'sessions/create',

            // the domain RPX uses for your site
        	'rpx_subdomain' => '',
        ),
        'Native' => array(
            
        ), */
        'Anonymous' => array(
        )
    )
);
