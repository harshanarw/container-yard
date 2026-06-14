<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcast Driver
    |--------------------------------------------------------------------------
    | Supported: "reverb" (self-hosted VPS), "pusher" (hosted fallback), "null"
    |
    | Switch with a single .env change:
    |   BROADCAST_DRIVER=reverb   → uses Laravel Reverb (recommended on VPS)
    |   BROADCAST_DRIVER=pusher   → uses Pusher hosted service (fallback)
    |   BROADCAST_DRIVER=null     → disables WebSocket; 5-second polling only
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    'connections' => [

        /*
        |----------------------------------------------------------------------
        | Laravel Reverb — self-hosted WebSocket server
        | Setup: composer require laravel/reverb && php artisan reverb:install
        | Run:   php artisan reverb:start --host=0.0.0.0 --port=8080
        | Prod:  manage via Supervisor (see deployment docs)
        |----------------------------------------------------------------------
        */
        'reverb' => [
            'driver' => 'reverb',
            'key'    => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host'        => env('REVERB_HOST', '0.0.0.0'),           // server bind address
                'client_host' => env('REVERB_CLIENT_HOST', '127.0.0.1'), // browser connects here
                'port'        => env('REVERB_PORT', 8080),
                'scheme'      => env('REVERB_SCHEME', 'http'),
                'useTLS'      => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [],
        ],

        /*
        |----------------------------------------------------------------------
        | Pusher — hosted WebSocket service (free tier: 100 conn, 200k msg/day)
        | Useful as a fallback when Reverb is being maintained/restarted.
        | Signup: https://pusher.com — create an app, copy credentials to .env
        |----------------------------------------------------------------------
        */
        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'useTLS'  => true,
            ],
            'client_options' => [],
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
