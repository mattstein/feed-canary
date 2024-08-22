<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $feed_id
 * @property int $status HTTP response status code
 * @property string $headers
 * @property string $hash A hash of the content for comparison with other checks
 * @property bool $is_valid Whether the response body is valid XML or JSON
 */
class Check extends Model
{
    protected $fillable = ['feed_id', 'status', 'headers', 'hash', 'is_valid'];
}
