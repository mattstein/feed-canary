<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $feed_id
 * @property string $url
 * @property string $message
 */
class ConnectionFailure extends Model
{
    use HasFactory;

    public function exceedsThreshold(): bool
    {
        $maxSeconds = config('app.connection_failure_threshold');
        $latestCheck = $this->feed->latestCheck();

        if ($latestCheck) {
            return $this->created_at->diffInSeconds($latestCheck->created_at) > $maxSeconds;
        }

        // If we don’t have a check, use the oldest connection failure
        $oldestConnectionFailure = self::whereFeedId($this->feed_id)
            ->oldest()
            ->first();

        if ($oldestConnectionFailure) {
            return $this->created_at->diffInSeconds($oldestConnectionFailure->created_at) > $maxSeconds;
        }

        return false;
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }
}
