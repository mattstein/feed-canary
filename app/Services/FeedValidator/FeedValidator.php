<?php

namespace App\Services\FeedValidator;

use App\Models\Feed;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
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

        // Reset so a stale source from an earlier validation can’t leak through.
        Context::forget('feed_validator');

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

        return $this->decided('jsonschema', $validator->isValid() ? ValidationResult::Valid : ValidationResult::Invalid);
    }

    private function validateXml(): ValidationResult
    {
        Log::debug('Validating XML schema');

        if (! $this->isReadableXml()) {
            return $this->decided('laminas', ValidationResult::Invalid);
        }

        if (config('feed.local_validator_url')) {
            $verdict = $this->localVerdict();

            if ($verdict !== null) {
                return $this->decided('local', $verdict);
            }
        }

        if (config('feed.w3c_enabled')) {
            $verdict = $this->w3cVerdict();

            if ($verdict !== null) {
                return $this->decided('w3c', $verdict);
            }
        }

        $verdict = $this->rssBoardVerdict();

        if ($verdict !== ValidationResult::Unknown) {
            return $this->decided('rssboard', $verdict);
        }

        Log::warning('No validator produced a verdict', [
            'feed_id' => $this->feed->id,
            'feed_url' => $this->feed->url,
        ]);

        $this->reportFailure('all-validators', 'No validator produced a verdict');

        return $verdict;
    }

    /**
     * Notes which stage gave the verdict, for `Feed::check()` to store. Goes
     * through `Context` so callers (and test mocks of this class) needn’t change.
     */
    private function decided(string $source, ValidationResult $result): ValidationResult
    {
        Context::add('feed_validator', $source);

        return $result;
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
     * Asks the self-hosted validator by posting the exact body we fetched.
     * Any failure is reported, since the public validators are only a fallback.
     */
    private function localVerdict(): ?ValidationResult
    {
        try {
            $response = $this->client()->asForm()->post(config('feed.local_validator_url'), [
                'output' => 'soap12',
                'url' => $this->feed->url,
                'rawdata' => $this->body,
            ]);
        } catch (ConnectionException $exception) {
            $this->reportFailure('local-validator', 'Unreachable: '.$exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->reportFailure('local-validator', 'HTTP '.$response->status());

            return null;
        }

        $verdict = $this->soapVerdict($response);

        if ($verdict === null) {
            $this->reportFailure('local-validator', 'Unusable response');
        }

        return $verdict;
    }

    /**
     * Asks the public W3C SOAP service. Returns `null` for anything that isn’t
     * a definitive, well-formed answer (429, Cloudflare challenge, 5xx,
     * timeout, SOAP fault) so the caller can try another validator.
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

        return $this->soapVerdict($response);
    }

    private function soapVerdict(Response $response): ?ValidationResult
    {
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
     * Reports an infrastructure failure, at most once per throttle window per
     * stage, so a dead validator produces one alert instead of one per feed.
     */
    private function reportFailure(string $stage, string $detail): void
    {
        Log::warning("Feed validator problem ({$stage}): {$detail}");

        try {
            if (! Cache::add('feed-validator-alert:'.$stage, true, (int) config('feed.alert_throttle', 900))) {
                return;
            }
        } catch (\Throwable) {
            // If the cache itself is down, better to over-report than to stay quiet.
        }

        report(new ValidatorInfrastructureException("Feed validator problem ({$stage}): {$detail}"));
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
