<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Feed Canary has no user accounts. This file is kept minimal because
    | some framework internals expect config('auth.*') to be present.
    |
    */

    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => null,
        ],
    ],

    'providers' => [],

];
