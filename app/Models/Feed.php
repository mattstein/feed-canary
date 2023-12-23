<?php

namespace App\Models;

use App\Mail\FeedFailed;
use App\Mail\FeedFixed;
use Fungku\MarkupValidator\FeedValidator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use JsonSchema\Validator;

/**
 * @property string $id
 * @property string $url
 * @property string $email
 * @property string $type
 * @property string $status
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

            if ($this->getFormat() === self::FORMAT_JSON) {
                Log::debug('Validating JSON schema');
                $schemaDefinition = resource_path('schema-v1.1.json');
                $jsonSchemaObject = json_decode(file_get_contents($schemaDefinition));
                $data = json_decode($response->getBody()->getContents());

                $validator = new Validator;
                $validator->validate($data, $jsonSchemaObject);
                $isValid = $validator->isValid();
            } else {
                Log::debug('Validating XML schema');
                $validator = new FeedValidator();
                $isValid = $validator->validate($this->url);
            }
        }

        Log::debug('Creating check record');
        $check = Check::create([
            'feed_id' => $this->id,
            'status' => $response->status(),
            'headers' => json_encode($response->headers()),
            'is_valid' => $isValid,
        ]);

        $this->last_checked = now();
        $this->status = $isValid ? self::STATUS_HEALTHY : self::STATUS_FAILING;

        if ($this->statusHasChanged()) {
            if (! $isValid) {
                Log::debug('Sending failure notification');
                Mail::send(new FeedFailed($this, $check));
                $this->last_notified = now();
            } elseif ( ! empty($this->previousCheck())) {
                Log::debug('Sending fixed notification');
                Mail::send(new FeedFixed($this, $check));
                $this->last_notified = now();
            }
        }

        if ( ! $this->save()) {
            Log::error('Failed to save feed ' . $this->id);
        }

        return $isValid;
    }

    /**
     * Returns the most recent check for the feed, if it exists.
     * @return Check|null
     */
    public function latestCheck(): Check|null
    {
        return Check::query()
            ->where('feed_id', $this->id)
            ->orderBy('updated_at', 'DESC')
            ->first();
    }

    /**
     * Returns the second-most-recent check for the feed, if it exists.
     * @return Check|null
     */
    public function previousCheck(): Check|null
    {
        return Check::query()
            ->where('feed_id', $this->id)
            ->orderBy('updated_at', 'DESC')
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
     * @return bool True if the status of the previous two checks differ.
     */
    private function statusHasChanged(): bool
    {
        $currentStatus = $this->latestCheck()->is_valid ?? null;
        $previousStatus = $this->previousCheck()->is_valid ?? null;

        return $currentStatus !== $previousStatus;
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
