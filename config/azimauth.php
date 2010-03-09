<?php defined('SYSPATH') OR die('No direct access allowed.');

return array
(
	'lifetime' => 1209600,
	'cookie_key' => 'azimauth_token',
	'session_key' => 'azimauth_token',
	'session_type' => 'database',
	'rpx_api_key' => '', // your RPX api key
	'rpx_token_url' => URL::base(FALSE, TRUE) . 'sessions/create', // the URL that will handle RPX tokens
	'rpx_domain' => '', // the domain RPX uses for your site
);
