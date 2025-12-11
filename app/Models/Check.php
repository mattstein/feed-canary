<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $feed_id
 * @property int $status HTTP response status code
 * @property array $headers
 * @property string $hash A hash of the content for comparison with other checks
 * @property bool $is_valid Whether the response body is valid XML or JSON
 */
class Check extends Model
{
    use HasFactory;

    /**
     * Pseudo-status stored when a connection failure prevents receiving a response.
     */
    public const int STATUS_CONNECTION_FAILURE = 0;

    protected $fillable = ['feed_id', 'status', 'headers', 'hash', 'is_valid'];

    protected $casts = [
        'headers' => 'array',
        'is_valid' => 'boolean',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }
}
