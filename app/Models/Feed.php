<?php

namespace App\Models;

use App\Mail\FeedFailed;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Fungku\MarkupValidator\FeedValidator;
use Illuminate\Support\Facades\Mail;

/**
 * @property string $id
 * @property string $url
 * @property string $email
 * @property string $type
 * @property string $status
 * @property string $last_checked
 * @property string $last_notified
 */
class Feed extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['url', 'email', 'type', 'status', 'last_checked', 'last_notified'];

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
        $isValid = false;

        if ($response->successful()) {
            $validator = new FeedValidator();
            $isValid = $validator->validate($this->url);
        }

        $check = Check::create([
            'feed_id' => $this->id,
            'status' => $response->status(),
            'headers' => json_encode($response->headers()),
            //'body' => $response->body(),
            'is_valid' => $isValid,
        ]);

        $this->update([
            'last_checked' => now(),
            'status' => $isValid ? 'healthy' : 'failing',
        ]);

        // TODO: only notify once on status change

        if (! $isValid) {
            Mail::send(new FeedFailed($this, $check));

            $this->update([
                'last_notified' => now(),
            ]);

            $this->save();

            return false;
        }

        $this->save();

        return true;
    }

    public function latestCheck(): Check|null
    {
        return Check::query()
            ->where('feed_id', $this->id)
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    public function previousCheck(): Check|null
    {
        return Check::query()
            ->where('feed_id', $this->id)
            ->orderBy('updated_at', 'DESC')
            ->skip(1)
            ->first();
    }
}
