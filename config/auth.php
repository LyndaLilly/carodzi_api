<?php

return [


    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'seller' => [
            'driver' => 'sanctum',
            'provider' => 'sellers',
        ],

          'buyer' => [
            'driver' => 'sanctum',
            'provider' => 'buyers',
        ],

        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
    ],

    // ...

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        'sellers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Seller::class,
        ],

          'buyers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Buyer::class,
        ],


        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

];

