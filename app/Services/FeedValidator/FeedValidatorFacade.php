<?php

namespace App\Services\FeedValidator;

use App\Models\Feed;
use Illuminate\Support\Facades\Facade;

/**
 * @method static feedIsValid(Feed $feed, string $body)
 *
 * @mixin FeedValidator
 */
class FeedValidatorFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'feedValidator';
    }
}
