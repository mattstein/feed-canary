<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'Feed Canary'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'notify_connection_failures' => env('NOTIFY_CONNECTION_FAILURES', true),
    'connection_failure_threshold' => env('CONNECTION_FAILURE_THRESHOLD', 86400),
    'user_agent' => env('APP_USER_AGENT', 'Feed Canary'),
    'plausible_domain' => env('PLAUSIBLE_DOMAIN', null),
    'plausible_script' => env('PLAUSIBLE_SCRIPT', 'https://plausible.io/js/script.js'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
        // 'store' => 'redis',
    ],
    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        // App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        \MarcAndreAppel\BackblazeB2\BackblazeB2ServiceProvider::class,
        \App\Providers\FeedValidatorProvider::class,
    ])->toArray(),
    'aliases' => Facade::defaultAliases()->merge([
        'FeedValidator' => \App\Services\FeedValidator\FeedValidatorFacade::class,
    ])->toArray(),
];
