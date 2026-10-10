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
    | The `none` driver is a closure request guard registered in
    | AppServiceProvider that always resolves to no user. It lets code
    | calling `$request->user()` (e.g. the `throttle` middleware) work
    | without a user provider.
    |
    */

    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'none',
        ],
    ],

    'providers' => [],

];
