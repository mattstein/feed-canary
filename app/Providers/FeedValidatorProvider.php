<?php

namespace App\Providers;

use App\Services\FeedValidator\FeedValidator;
use Illuminate\Support\ServiceProvider;

class FeedValidatorProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind('feedValidator', function ($app) {
            return $app->get(FeedValidator::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
