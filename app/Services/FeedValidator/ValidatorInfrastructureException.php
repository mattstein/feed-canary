<?php

namespace App\Services\FeedValidator;

use RuntimeException;

/**
 * A validator couldn’t be reached or gave an unusable answer. This is about
 * our infrastructure, not about a feed, and is reported (throttled) so a dead
 * validator container can’t fail quietly behind the public fallbacks.
 */
class ValidatorInfrastructureException extends RuntimeException {}
