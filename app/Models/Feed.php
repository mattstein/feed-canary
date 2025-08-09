<?php

namespace App\Models;

use App\Mail\FeedConnectionFailed;
use App\Mail\FeedFailed;
use App\Mail\FeedFixed;
use Carbon\Carbon;
use FeedValidator;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * @property string $id
 * @property string $url
 * @property string $email
 * @property string $type
 * @property string $status
 * @property bool $confirmed
 * @property string $last_checked
 * @property string $last_notified
 * @property string $confirmation_code
 */
class Feed extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * {@inheritDoc}
     */
    protected $fillable = ['url', 'email', 'type', 'status', 'confirmed', 'last_checked', 'last_notified', 'confirmation_code'];

    /**
     * Attribute casting for convenience and correctness.
     */
    protected $casts = [
        'confirmed' => 'boolean',
        'last_checked' => 'datetime',
        'last_notified' => 'datetime',
    ];

    /**
     * Feed is responding and valid
     */
    public const string STATUS_HEALTHY = 'healthy';

    /**
     * Feed failed to respond or does not validate
     */
    public const string STATUS_FAILING = 'failing';

    /**
     * Format is RSS or Atom
     */
    public const string FORMAT_XML = 'xml';

    /**
     * Format is a JSON feed
     */
    public const string FORMAT_JSON = 'json';

    /**
     * @return string Public, unique URL for seeing feed status and managing the check
     */
    public function manageUrl(): string
    {
        return url('/feed/'.$this->id);
    }

    /**
     * @return string Temporary URL used to confirm and activate the feed monitor
     */
    public function confirmUrl(): string
    {
        if (empty($this->confirmation_code)) {
            return $this->manageUrl();
        }

        return url('/feed/'.$this->id.'/confirm/'.$this->confirmation_code);
    }

    /**
     * @return string URL for a feed validation service that can be used for troubleshooting
     */
    public function validatorUrl(): string
    {
        $base = match ($this->getFormat()) {
            self::FORMAT_JSON => 'https://validator.jsonfeed.org/?url=',
            default => 'https://validator.w3.org/feed/check.cgi?url=',
        };

        return $base.urlencode($this->url);
    }

    /**
     * Attempts to download and validate the feed, sending a notification if its status changed.
     *
     * @return bool True if the feed seems healthy and valid
     *
     * @throws ConnectionException
     */
    public function check(): bool
    {
        Log::debug('Checking '.$this->url);
        $this->last_checked = now();

        try {
            $response = Http::withUserAgent(config('app.user_agent'))
                ->retry(2, 250)
                ->get($this->url);
        } catch (ConnectionException|RequestException|GuzzleRequestException $e) {
            Log::debug('Connection failed: '.$e->getMessage());

            $failure = new ConnectionFailure;

            $failure->feed_id = $this->id;
            $failure->url = $this->url;
            $failure->message = $e->getMessage();

            $failure->save();

            if ($failure->exceedsThreshold()) {
                $this->status = self::STATUS_FAILING;

                if ($this->confirmed && config('app.notify_connection_failures')) {
                    Log::debug('Sending connection failure notification');

                    Mail::send(new FeedConnectionFailed($this, $failure));
                    $this->last_notified = now();
                }

                if (! $this->save()) {
                    Log::error('Failed to save feed '.$this->id);
                }
            }

            return false;
        }

        $isValid = false;

        if ($response->successful()) {
            Log::debug('Check successful');

            $body = $response->body();
            $hash = md5($body);
            $previousHash = $this->latestCheck()->hash ?? null;

            // TODO: check and honor last-modified header?

            if ($hash !== $previousHash) {
                Log::debug('Content changed, checking validity');
                $isValid = FeedValidator::feedIsValid($this, $body);
            } else {
                Log::debug('Content unchanged, using last check validity');
                $isValid = $this->latestCheck()->is_valid;
            }
        }

        Log::debug('Creating check record');

        $check = new Check;

        $check->feed_id = $this->id;
        $check->status = $response->status();
        $check->headers = $response->headers();
        $check->hash = $hash ?? null;
        $check->is_valid = $isValid;

        $check->save();

        $this->status = $isValid ? self::STATUS_HEALTHY : self::STATUS_FAILING;

        if ($this->confirmed && $this->statusHasChanged()) {
            if (! $isValid) {
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

        if (! $this->save()) {
            Log::error('Failed to save feed '.$this->id);
        }

        return $isValid;
    }

    /**
     * Returns true if Feed Canary has not been able to connect to the feed.
     */
    public function hasFailingConnection(): bool
    {
        if (! $latestConnectionFailure = $this->latestConnectionFailure()) {
            // If we don’t have any connection failures, we’re all good
            return false;
        }

        if (! $latestCheck = $this->latestCheck()) {
            // If we have a connection failure without any checks, that’s a problem
            return true;
        }

        if ($latestConnectionFailure->created_at->gte($latestCheck->created_at)) {
            // If the connection failure is more recent than the check, that’s a problem
            return true;
        }

        return false;
    }

    /**
     * Returns checks against the feed.
     */
    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    /**
     * Returns the most recent check for the feed, if it exists.
     */
    public function latestCheck(): ?Check
    {
        return $this->checks()
            ->latest()
            ->first();
    }

    /**
     * Returns connection failures encountered when attempting to check the feed.
     */
    public function connectionFailures(): HasMany
    {
        return $this->hasMany(ConnectionFailure::class);
    }

    /**
     * Returns the most recent ConnectionFailure, or `null` if there isn’t one.
     */
    public function latestConnectionFailure(): ?ConnectionFailure
    {
        return $this->connectionFailures()
            ->latest()
            ->first();
    }

    /**
     * Returns the second-most-recent check for the feed, if it exists.
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

    /**
     * Returns `true` if the provided MIME type represents a feed we can check.
     */
    public static function isValidResponseType(string $type = ''): bool
    {
        $validTypes = [
            'application/xml',
            'application/rss+xml',
            'application/atom+xml',
            'application/json',
            'application/feed+json',
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
    public function statusHasChanged(): bool
    {
        $latest = $this->latestCheck();
        $previous = $this->previousCheck();

        $currentStatus = $latest->is_valid ?? null;
        $previousStatus = $previous->is_valid ?? null;

        return $currentStatus !== $previousStatus;
    }

    /**
     * @return bool True if the latest check’s content differs from the previous check.
     */
    public function contentHasChanged(): bool
    {
        $latest = $this->latestCheck();
        $previous = $this->previousCheck();

        $currentHash = $latest->hash ?? null;
        $previousHash = $previous->hash ?? null;

        return $currentHash !== $previousHash;
    }

    /**
     * Returns confirmed feeds that are ready to be checked.
     */
    public static function getAllReadyForCheck(): Builder
    {
        return self::query()->readyForCheck();
    }

    /**
     * Scope: confirmed feeds ready to be checked (not checked in last 5 minutes).
     */
    public function scopeReadyForCheck(Builder $query): Builder
    {
        $cutoff = Carbon::now()->subMinutes(5);

        return $query
            ->where('confirmed', 1)
            ->where(function (Builder $q) use ($cutoff) {
                $q->where('last_checked', '<=', $cutoff)
                    ->orWhereNull('last_checked');
            });
    }
}
