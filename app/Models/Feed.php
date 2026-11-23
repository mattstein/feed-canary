<?php

namespace App\Models;

use App\Mail\FeedConnectionFailed;
use App\Mail\FeedFailed;
use App\Mail\FeedFixed;
use App\Services\PublicHttp;
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
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Sentry\State\Scope;

/**
 * @property string $id
 * @property string $url
 * @property string $email
 * @property string $type
 * @property string $status
 * @property bool $confirmed
 * @property \Illuminate\Support\Carbon|null $last_checked
 * @property \Illuminate\Support\Carbon|null $last_notified
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
        // Attach Sentry context so any exceptions include feed identifiers
        if (app()->bound('sentry')) {
            app('sentry')->configureScope(function (Scope $scope) {
                $scope->setTag('feed_id', (string) $this->id);
                $scope->setTag('feed_url', (string) $this->url);
            });
        }
        $now = now();
        $this->last_checked = $now;

        try {
            $response = PublicHttp::client()
                ->withUserAgent(config('app.user_agent'))
                ->retry(2, 250, fn ($e) => ! $e instanceof ConnectionException, throw: false)
                ->get($this->url);
        } catch (ConnectionException|RequestException|GuzzleRequestException $e) {
            $failure = new ConnectionFailure;

            $failure->feed_id = $this->id;
            $failure->url = $this->url;
            $failure->message = $e->getMessage();

            $failure->save();

            if ($failure->exceedsThreshold()) {
                // Only log persistent failures (exceeded threshold)
                Log::warning('Connection failed (threshold exceeded): '.$e->getMessage(), [
                    'feed_id' => $this->id,
                    'feed_url' => $this->url,
                ]);

                $this->status = self::STATUS_FAILING;

                if ($this->latestCheck()?->status !== Check::STATUS_CONNECTION_FAILURE) {
                    $this->checks()->create([
                        'status' => Check::STATUS_CONNECTION_FAILURE,
                        'headers' => [],
                        'hash' => null,
                        'is_valid' => false,
                    ]);
                }

                $latestCheck = $this->latestCheck();

                $shouldNotify = $this->confirmed
                    && config('app.notify_connection_failures')
                    && ($this->last_notified === null || ($latestCheck && $this->last_notified->lt($latestCheck->created_at)));

                if ($shouldNotify) {
                    Log::debug('Sending connection failure notification');

                    Mail::send(new FeedConnectionFailed($this, $failure));
                    $this->last_notified = $now;
                }
            } else {
                // Temporary failure within threshold - log at debug level only
                Log::debug('Connection failed (within threshold): '.$e->getMessage(), [
                    'feed_id' => $this->id,
                    'feed_url' => $this->url,
                ]);
            }

            if (! $this->save()) {
                Log::error('Failed to save feed '.$this->id);
            }

            return false;
        }

        // `null` means no validator produced a verdict
        $isValid = false;
        $hash = null;
        $validatedAt = $now;
        $validator = null;

        if ($response->successful()) {
            Log::debug('Check successful');

            $body = $response->body();
            $hash = md5($body);
            $previous = $this->latestCheck();

            // TODO: check and honor last-modified header?

            if ($this->canReuseVerdict($previous, $hash)) {
                Log::debug('Content unchanged, using last check validity');
                $isValid = $previous->is_valid;
                $validatedAt = $previous->validated_at;
                $validator = $previous->validator;
            } else {
                Log::debug('Checking validity');
                Context::forget('feed_validator');
                $isValid = FeedValidator::validate($this, $body)->toBool();
                $validatedAt = $isValid === null ? null : $now;
                $validator = $isValid === null ? null : Context::get('feed_validator');
            }
        }

        Log::debug('Creating check record');

        $check = new Check;

        $check->feed_id = $this->id;
        $check->status = $response->status();
        $check->headers = $response->headers();
        $check->hash = $hash;
        $check->is_valid = $isValid;
        $check->validated_at = $validatedAt;
        $check->validator = $validator;

        $check->save();

        if ($isValid === null) {
            // No verdict: don’t touch status or send anything.
            Log::warning('Feed could not be verified; no validator produced a verdict', [
                'feed_id' => $this->id,
                'feed_url' => $this->url,
            ]);

            if (! $this->save()) {
                Log::error('Failed to save feed '.$this->id);
            }

            return $this->status === self::STATUS_HEALTHY;
        }

        $this->status = $isValid ? self::STATUS_HEALTHY : self::STATUS_FAILING;

        if ($this->confirmed && $this->statusHasChanged()) {
            if (! $isValid) {
                // If a confirmed feed just started failing, send a notification
                Log::debug('Sending failure notification');
                Mail::send(new FeedFailed($this, $check));
                $this->last_notified = now();
            } elseif ($this->previousVerdictCheck() !== null) {
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
     * A previous verdict may stand in for a fresh validation only if the
     * content is identical, it was an actual verdict, and it isn’t stale.
     */
    private function canReuseVerdict(?Check $previous, string $hash): bool
    {
        return $previous !== null
            && $previous->hash === $hash
            && $previous->is_valid !== null
            && $previous->validated_at !== null
            && $previous->validated_at->gt(now()->subSeconds((int) config('feed.revalidate_after', 86400)));
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
     *
     * @return HasMany<Check, $this>
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
        /** @var Check|null */
        return $this->checks()
            ->latest()
            ->first();
    }

    /**
     * Returns connection failures encountered when attempting to check the feed.
     *
     * @return HasMany<ConnectionFailure, $this>
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
        /** @var ConnectionFailure|null */
        return $this->connectionFailures()
            ->latest()
            ->first();
    }

    /**
     * Returns the second-most-recent check for the feed, if it exists.
     */
    public function previousCheck(): ?Check
    {
        /** @var Check|null */
        return $this->checks()
            ->latest()
            ->skip(1)
            ->first();
    }

    /**
     * Returns the most recent check before the latest that reached a verdict,
     * skipping checks where no validator could give one.
     */
    public function previousVerdictCheck(): ?Check
    {
        /** @var Check|null */
        return $this->checks()
            ->whereNotNull('is_valid')
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
     * @return bool True if the status of the latest check differs from the previous check that had a verdict.
     */
    public function statusHasChanged(): bool
    {
        $latest = $this->latestCheck();
        $previous = $this->previousVerdictCheck();

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
