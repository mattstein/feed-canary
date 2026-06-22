<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FeedValidatorProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    FeedValidatorProvider::class,
    HorizonServiceProvider::class,
];
