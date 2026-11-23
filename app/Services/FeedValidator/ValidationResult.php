<?php

namespace App\Services\FeedValidator;

enum ValidationResult
{
    /** A validator parsed the feed and passed it. */
    case Valid;

    /** A validator parsed the feed and rejected it. */
    case Invalid;

    /** No validator produced a verdict (outage, 429, timeout, error page). */
    case Unknown;

    /**
     * Maps to the nullable `checks.is_valid` column: `null` means no verdict.
     */
    public function toBool(): ?bool
    {
        return match ($this) {
            self::Valid => true,
            self::Invalid => false,
            self::Unknown => null,
        };
    }
}
