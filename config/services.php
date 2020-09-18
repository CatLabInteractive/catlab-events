<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => \App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'catlab' => [
        'url' => env('CATLAB_API', 'https://accounts.catlab.eu/'),
        'client_id' => env('CATLAB_CLIENT_ID'),
        'client_secret' => env('CATLAB_CLIENT_SECRET'),
        'redirect'=> env('APP_URL') . '/login/callback',
        'authorizePath'=> '/oauth2/authorize?reset=1&language=nl&',
        'model' => \App\Models\User::class
    ],

    'quizwitz' => [
        'reportClient' => env('QUIZWITZ_REPORT_CLIENT_KEY'),
    ],

    'pay' => [
        'tokenCode' => env('PAY_TOKEN'),
        'apiToken' => env('PAY_APITOKEN'),
        'serviceId' => env('PAY_SERVICEID')

    ],

    'uitdb' => [
        'env' => env('UITDB_ENV'),

        'oauth_consumer' => env('UITDB_OAUTH_CONSUMER'),
        'oauth_secret' => env('UITDB_OAUTH_SECRET'),

        'entry_api_key' => env('UITDB_ENTRY_API_KEY')
    ]


];
