<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Fungku\MarkupValidator\FeedValidator;

/**
 * @property string $id
 * @property string $url
 */
class Feed extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['url', 'email', 'type', 'last_checked'];

    public function manageUrl(): string
    {
        return url('/feed/' . $this->id);
    }

    public function deleteAction(): string
    {
        return url('/feed/' . $this->id . '/delete');
    }

    public function check(): bool
    {
        $response = Http::get($this->url);
        $validator = new FeedValidator();
        $isValid = $validator->validate($this->url);

        Check::create([
            'feed_id' => $this->id,
            'status' => $response->status(),
            'headers' => json_encode($response->headers()),
            //'body' => $response->body(),
            'is_valid' => $isValid,
        ]);

        $this->update([
            'last_checked' => now()
        ]);

        if (! $response->successful() || ! $isValid) {
            // TODO: send yikes email with details
            return false;
        }

        return true;
    }
}
