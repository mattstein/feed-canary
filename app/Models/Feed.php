<?php

namespace App\Models;

use App\Mail\FeedFailed;
use App\Mail\FeedFixed;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * @property string $id
 * @property string $url
 * @property string $email
 * @property string $type
 * @property string $status
 * @property bool   $confirmed
 * @property string $last_checked
 * @property string $last_notified
 * @property string $confirmation_code
 */
class Feed extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var @inheritDoc
     */
    protected $fillable = ['url', 'email', 'type', 'status', 'confirmed', 'last_checked', 'last_notified', 'confirmation_code'];

    /**
     * Feed is responding and valid
     */
    public const STATUS_HEALTHY = 'healthy';

    /**
     * Feed failed to respond or does not validate
     */
    public const STATUS_FAILING = 'failing';

    /**
     * Format is RSS or Atom
     */
    public const FORMAT_XML = 'xml';

    /**
     * Format is a JSON feed
     */
    public const FORMAT_JSON = 'json';

    /**
     * @return string Public, unique URL for seeing feed status and managing the check
     */
    public function manageUrl(): string
    {
        return url('/feed/' . $this->id);
    }

    /**
     * @return string Temporary URL used to confirm and activate the feed monitor
     */
    public function confirmUrl(): string
    {
        if (empty($this->confirmation_code)) {
            return $this->manageUrl();
        }

        return url('/feed/' . $this->id . '/confirm/' . $this->confirmation_code);
    }

    /**
     * @return string URL for a feed validation service that can be used for troubleshooting
     */
    public function validatorUrl(): string
    {
        $baseUrl = $this->getFormat() === self::FORMAT_JSON ?
            'https://validator.jsonfeed.org/?url=' :
            'https://validator.w3.org/feed/check.cgi?url=';

        return $baseUrl . urlencode($this->url);
    }

    /**
     * @return string URL for deleting the monitor
     */
    public function deleteAction(): string
    {
        return url('/feed/' . $this->id . '/delete');
    }

    /**
     * Attempts to download and validate the feed, sending a notification if its status changed.
     * @return bool True if the feed seems healthy and valid
     */
    public function check(): bool
    {
        Log::debug('Checking ' . $this->url);
        $response = Http::withUserAgent('Feed Canary')->get($this->url);
        $isValid = false;

        if ($response->successful()) {
            Log::debug('Check successful');

            $body = $response->getBody()->getContents();
            $hash = md5($body);
            $previousHash = $this->latestCheck()->hash ?? null;

            // TODO: check and honor last-modified header?

            if ($hash !== $previousHash) {
                Log::debug('Content changed, checking validity');
                $isValid = (new Validator())->feedIsValid($this, $body);
            } else {
                Log::debug('Content unchanged, using last check validity');
                $isValid = $this->latestCheck()->is_valid;
            }
        }

        Log::debug('Creating check record');
        $check = Check::create([
            'feed_id' => $this->id,
            'status' => $response->status(),
            'headers' => json_encode($response->headers()),
            'hash' => $hash ?? null,
            'is_valid' => $isValid,
        ]);

        $this->last_checked = now();
        $this->status = $isValid ? self::STATUS_HEALTHY : self::STATUS_FAILING;

        if ($this->confirmed && $this->statusHasChanged()) {
            if (!$isValid) {
                // If a confirmed feed just started failing, send a notification
                Log::debug('Sending failure notification');
                Mail::send(new FeedFailed($this, $check));
                $this->last_notified = now();
            } elseif ($this->previousCheck() !== null) {
                // If a confirmed feed just turned healthy, send a notification
                Log::debug('Sending fixed notification');
                Mail::send(new FeedFixed($this, $check));
                $this->last_notified = now();
            }
        }

        if (!$this->save()) {
            Log::error('Failed to save feed ' . $this->id);
        }

        return $isValid;
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    /**
     * Returns the most recent check for the feed, if it exists.
     *
     * @return Check|null
     */
    public function latestCheck(): ?Check
    {
        return $this->checks()
            ->latest()
            ->first();
    }

    /**
     * Returns the second-most-recent check for the feed, if it exists.
     *
     * @return Check|null
     */
    public function previousCheck(): ?Check
    {
        return $this->checks()
            ->latest()
            ->skip(1)
            ->first();
    }

    /**
     * @return string `json` or `xml`, depending on the MIME type from the first response
     */
    public function getFormat(): string
    {
        if (str_contains($this->type, 'application/json')) {
            return self::FORMAT_JSON;
        }

        return self::FORMAT_XML;
    }

    public static function isValidResponseType($type = ''): bool
    {
        $validTypes = [
            'application/xml',
            'application/rss+xml',
            'application/atom+xml',
            'application/json',
            'application/x-rss+xml',
            'text/xml',
        ];

        foreach ($validTypes as $validType) {
            if (str_contains($type, $validType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool True if the status of the previous two checks differ.
     */
    private function statusHasChanged(): bool
    {
        $currentStatus = $this->latestCheck()->is_valid ?? null;
        $previousStatus = $this->previousCheck()->is_valid ?? null;

        return $currentStatus !== $previousStatus;
    }

    /**
     * @return bool True if the latest check’s content differs from the previous check.
     */
    private function contentHasChanged(): bool
    {
        $currentHash = $this->latestCheck()->hash ?? null;
        $previousHash = $this->previousCheck()->hash ?? null;

        return $currentHash !== $previousHash;
    }

    /**
     * Not implemented
     * @return bool
     */
    private function shouldSendNotification(): bool
    {
        // Is this a new status?
        return true;
    }

}
