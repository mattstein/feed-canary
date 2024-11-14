<?php

namespace App\Services\FeedValidator;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin FeedValidator
 */
class FeedValidatorFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'feedValidator';
    }
}
