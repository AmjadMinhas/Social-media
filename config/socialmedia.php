<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Facebook Configuration
    |--------------------------------------------------------------------------
    */
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect_uri' => env('APP_URL') . '/auth/facebook/callback',
        'graph_api_version' => env('FACEBOOK_API_VERSION', 'v18.0'),
        'permissions' => [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_manage_engagement',
            'pages_messaging', // Required for Facebook Messenger (sending and reading messages)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LinkedIn Configuration
    |--------------------------------------------------------------------------
    */
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri' => env('APP_URL') . '/auth/linkedin/callback',
        'scopes' => [
            'openid',
            'profile',
            'email',
            'w_member_social',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram Configuration
    |--------------------------------------------------------------------------
    | Note: Instagram uses Facebook's Graph API
    | You need a Facebook App with Instagram permissions
    */
    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID') ?: env('FACEBOOK_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET') ?: env('FACEBOOK_APP_SECRET'),
        'redirect_uri' => env('APP_URL') . '/auth/instagram/callback',
        'graph_api_version' => env('INSTAGRAM_API_VERSION', 'v18.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Twitter/X Configuration
    |--------------------------------------------------------------------------
    */
    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect_uri' => env('APP_URL') . '/auth/twitter/callback',
        'bearer_token' => env('TWITTER_BEARER_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TikTok Configuration
    |--------------------------------------------------------------------------
    */
    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri' => env('APP_URL') . '/auth/tiktok/callback',
    ],

];

