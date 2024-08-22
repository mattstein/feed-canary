<?php

namespace App\Services\FeedValidator;

use App\Models\Feed;
use Fungku\MarkupValidator\FeedValidator as FeedMarkupValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laminas\Feed\Exception\RuntimeException;
use Laminas\Feed\Reader\Reader;

class FeedValidator
{
    public Feed $feed;

    public ?string $body;

    public array $errors;

    public function feedIsValid(Feed $feed, $body = ''): bool
    {
        $this->feed = $feed;
        $this->body = $body;

        if ($this->feed->getFormat() === 'json') {
            return $this->isValidJson();
        }

        return $this->isValidXml();
    }

    private function isValidJson(): bool
    {
        Log::debug('Validating JSON schema');
        $schemaDefinition = resource_path('schema-v1.1.json');
        $jsonSchemaObject = json_decode(file_get_contents($schemaDefinition));
        $data = json_decode($this->body);

        $validator = new \JsonSchema\Validator;
        $validator->validate($data, $jsonSchemaObject);

        return $validator->isValid();
    }

    private function isValidXml(): bool
    {
        Log::debug('Validating XML schema');

        if (! $this->isReadableXml()) {
            return false;
        }

        try {
            return (new FeedMarkupValidator)->validate($this->feed->url);
        } catch (\ErrorException $exception) {
            Log::debug('W3C validator had a problem');

            // Last-ditch effort if the W3C validator returned a 500 error
            return $this->isValidXmlWithValidatorDotOrg();
        }
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

    private function isValidXmlWithValidatorDotOrg(): bool
    {
        $response = Http::withUserAgent('Feed Canary')
            ->get('https://www.feedvalidator.org/check.cgi?url='.urlencode($this->feed->url));

        if ($response->successful()) {
            $responseText = $response->body();

            return str_contains($responseText, 'Congratulations!');
        }

        return false;
    }
}
