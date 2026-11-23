<?php

use App\Models\Feed;
use App\Services\FeedValidator\FeedValidator;
use App\Services\FeedValidator\ValidationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

const W3C_URL = 'https://validator.w3.org/*';
const RSSBOARD_URL = 'https://www.rssboard.org/rss-validator/*';

function soap(string $validity): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        .'<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope"><env:Body>'
        .'<m:feedvalidationresponse xmlns:m="http://www.w3.org/2005/10/feed-validator">'
        .'<m:uri>https://example.com/feed.xml</m:uri>'
        .'<m:validity>'.$validity.'</m:validity>'
        .'</m:feedvalidationresponse></env:Body></env:Envelope>';
}

function xmlFeed(): Feed
{
    return Feed::factory()->create([
        'type' => 'application/rss+xml',
        'url' => 'https://example.com/feed.xml',
    ]);
}

function validRss(): string
{
    return file_get_contents(base_path('tests/Resources/valid-rss.xml'));
}

/*
|--------------------------------------------------------------------------
| JSON validation
|--------------------------------------------------------------------------
*/

it('validates valid JSON feed', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    $validJson = json_encode([
        'version' => 'https://jsonfeed.org/version/1.1',
        'title' => 'Test Feed',
        'items' => [],
    ]);

    expect((new FeedValidator)->validate($feed, $validJson))->toBe(ValidationResult::Valid);
});

it('rejects invalid JSON feed missing required fields', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    $result = (new FeedValidator)->validate($feed, json_encode(['not_a_feed' => true]));

    expect($result)->toBe(ValidationResult::Invalid);
});

it('rejects malformed JSON', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    expect((new FeedValidator)->validate($feed, 'not json at all'))->toBe(ValidationResult::Invalid);
});

it('routes JSON feed to JSON validator without HTTP calls', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    Http::fake();

    (new FeedValidator)->validate($feed, json_encode([
        'version' => 'https://jsonfeed.org/version/1.1',
        'title' => 'Test Feed',
        'items' => [],
    ]));

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| XML validation
|--------------------------------------------------------------------------
*/

it('rejects unreadable XML without any network call', function () {
    Http::fake();

    $result = (new FeedValidator)->validate(xmlFeed(), '<not><valid><rss>');

    expect($result)->toBe(ValidationResult::Invalid);
    Http::assertNothingSent();
});

it('rejects empty body as invalid XML', function () {
    $feed = Feed::factory()->create(['type' => 'text/xml']);

    expect((new FeedValidator)->validate($feed, ''))->toBe(ValidationResult::Invalid);
});

it('trusts a W3C validity=true response', function () {
    Http::fake([W3C_URL => Http::response(soap('true'))]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Valid);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rssboard'));
});

it('trusts a well-formed W3C validity=false response', function () {
    Http::fake([W3C_URL => Http::response(soap('false'))]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Invalid);
});

it('falls back to RSS Board when W3C is rate limited, and accepts its success page', function () {
    Http::fake([
        W3C_URL => Http::response('<html>Just a moment...</html>', 429, ['cf-mitigated' => 'challenge']),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Valid);
});

it('returns Unknown when W3C is blocked and RSS Board serves its error page with a 200', function () {
    Http::fake([
        W3C_URL => Http::response('<html>Just a moment...</html>', 429),
        RSSBOARD_URL => Http::response('Sorry An error occurred while trying to validate this feed.', 200),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Unknown);
});

it('returns Unknown on a W3C SOAP fault and an RSS Board timeout', function () {
    Http::fake([
        W3C_URL => Http::response('<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope"><env:Body><env:Fault/></env:Body></env:Envelope>', 200),
        RSSBOARD_URL => fn () => throw new ConnectionException('timed out'),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Unknown);
});

it('returns Unknown when W3C returns 200 with a Cloudflare challenge and RSS Board is unparseable', function () {
    Http::fake([
        W3C_URL => Http::response('<html>Just a moment...</html>', 200),
        RSSBOARD_URL => Http::response('<html>something unexpected</html>', 200),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Unknown);
});

it('returns Unknown when RSS Board answers with a 5xx', function () {
    Http::fake([
        W3C_URL => Http::response('', 502),
        RSSBOARD_URL => Http::response('Server Error', 500),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Unknown);
});

it('returns Invalid when RSS Board explicitly says the feed does not validate', function () {
    Http::fake([
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('Sorry This feed does not validate.', 200),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Invalid);
});

it('skips the W3C stage when it is disabled in config', function () {
    config(['feed.w3c_enabled' => false]);

    Http::fake([RSSBOARD_URL => Http::response('This is a valid RSS feed.')]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Valid);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'w3.org'));
});
