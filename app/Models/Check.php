<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $feed_id
 * @property string $status
 * @property string $headers
 * @property string $hash
 * @property bool   $is_valid
 */
class Check extends Model
{
    use HasFactory;

    protected $fillable = ['feed_id', 'status', 'headers', 'hash', 'is_valid'];

}
