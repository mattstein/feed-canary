<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $feed_id
 * @property int $status HTTP response status code
 * @property array $headers
 * @property string $hash A hash of the content for comparison with other checks
 * @property bool|null $is_valid Whether the response body is valid XML or JSON; `null` if no validator produced a verdict
 * @property Carbon|null $validated_at When a validator last produced the verdict (carried forward when reused)
 */
class Check extends Model
{
    use HasFactory;

    /**
     * Pseudo-status stored when a connection failure prevents receiving a response.
     */
    public const int STATUS_CONNECTION_FAILURE = 0;

    protected $fillable = ['feed_id', 'status', 'headers', 'hash', 'is_valid', 'validated_at'];

    protected $casts = [
        'headers' => 'array',
        'is_valid' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }
}
