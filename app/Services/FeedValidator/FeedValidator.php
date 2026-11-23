<?php

namespace App\Services\FeedValidator;

use App\Models\Feed;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonSchema\Validator;
use Laminas\Feed\Exception\RuntimeException;
use Laminas\Feed\Reader\Reader;
use Sentry\State\Scope;

class FeedValidator
{
    public Feed $feed;

    public ?string $body;

    public array $errors;

    /**
     * Establishes whether a feed is valid. Only returns `Invalid` when a
     * validator actually parsed the feed and rejected it; every failure of the
     * validators themselves is `Unknown`.
     */
    public function validate(Feed $feed, string $body = ''): ValidationResult
    {
        $this->feed = $feed;
        $this->body = $body;

        // Ensure Sentry context includes feed identifiers during validation
        if (app()->bound('sentry')) {
            app('sentry')->configureScope(function (Scope $scope) {
                $scope->setTag('feed_id', (string) $this->feed->id);
                $scope->setTag('feed_url', (string) $this->feed->url);
            });
        }

        if ($this->feed->getFormat() === 'json') {
            return $this->validateJson();
        }

        return $this->validateXml();
    }

    private function validateJson(): ValidationResult
    {
        Log::debug('Validating JSON schema');
        $schemaDefinition = resource_path('schema-v1.1.json');
        $jsonSchemaObject = json_decode(file_get_contents($schemaDefinition));
        $data = json_decode($this->body);

        $validator = new Validator;
        $validator->validate($data, $jsonSchemaObject);

        return $validator->isValid() ? ValidationResult::Valid : ValidationResult::Invalid;
    }

    private function validateXml(): ValidationResult
    {
        Log::debug('Validating XML schema');

        if (! $this->isReadableXml()) {
            return ValidationResult::Invalid;
        }

        if (config('feed.w3c_enabled')) {
            $verdict = $this->w3cVerdict();

            if ($verdict !== null) {
                return $verdict;
            }
        }

        $verdict = $this->rssBoardVerdict();

        if ($verdict === ValidationResult::Unknown) {
            Log::warning('No validator produced a verdict', [
                'feed_id' => $this->feed->id,
                'feed_url' => $this->feed->url,
            ]);
        }

        return $verdict;
    }

    private function isReadableXml(): bool
    {
        try {
            Reader::importString($this->body);

            return true;
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            Log::debug('Could not parse feed');
        }

        return false;
    }

    /**
     * Asks the W3C SOAP service. Returns `null` for anything that isn’t a
     * definitive, well-formed answer (429, Cloudflare challenge, 5xx, timeout,
     * SOAP fault) so the caller can try another validator.
     */
    private function w3cVerdict(): ?ValidationResult
    {
        try {
            $response = $this->client()->get(config('feed.w3c_url'), [
                'output' => 'soap12',
                'url' => $this->feed->url,
            ]);
        } catch (ConnectionException $exception) {
            Log::debug('W3C validator unreachable: '.$exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::debug('W3C validator gave no verdict', ['status' => $response->status()]);

            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;

            if (! $document->loadXML($response->body(), LIBXML_NONET)) {
                return null;
            }

            $xpath = new DOMXPath($document);

            if ($xpath->query('//*[local-name()="Fault"]')->length > 0
                || $xpath->query('//*[local-name()="feedvalidationresponse"]')->length === 0) {
                return null;
            }

            $validity = $xpath->query('//*[local-name()="feedvalidationresponse"]//*[local-name()="validity"]')->item(0);

            $text = $validity === null ? '' : $validity->textContent;

            return match (strtolower(trim($text))) {
                'true' => ValidationResult::Valid,
                'false' => ValidationResult::Invalid,
                default => null,
            };
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * RSS Board reports its own failures with a 200, so the status code says
     * nothing; only recognised result markup counts as a verdict.
     */
    private function rssBoardVerdict(): ValidationResult
    {
        try {
            $response = $this->client()->get(config('feed.rssboard_url'), ['url' => $this->feed->url]);
        } catch (ConnectionException $exception) {
            Log::debug('RSS Board validator unreachable: '.$exception->getMessage());

            return ValidationResult::Unknown;
        }

        if (! $response->successful()) {
            return ValidationResult::Unknown;
        }

        $text = $response->body();

        if (str_contains($text, 'This is a valid RSS feed.')) {
            return ValidationResult::Valid;
        }

        if (str_contains($text, 'An error occurred while trying to validate this feed')) {
            return ValidationResult::Unknown;
        }

        if (stripos($text, 'feed does not validate') !== false) {
            return ValidationResult::Invalid;
        }

        return ValidationResult::Unknown;
    }

    private function client(): PendingRequest
    {
        return Http::withUserAgent(config('app.user_agent'))
            ->timeout(config('feed.validator_timeout'));
    }
}
