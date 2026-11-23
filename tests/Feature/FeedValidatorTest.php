<?php

use App\Models\Feed;
use App\Services\FeedValidator\FeedValidator;
use App\Services\FeedValidator\ValidationResult;
use App\Services\FeedValidator\ValidatorInfrastructureException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
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

/*
|--------------------------------------------------------------------------
| Self-hosted validator container
|--------------------------------------------------------------------------
*/

const LOCAL_URL = 'http://validator/*';

function useLocalValidator(): void
{
    config(['feed.local_validator_url' => 'http://validator/check.cgi']);
}

it('posts the fetched body to the local validator and trusts a valid verdict', function () {
    useLocalValidator();
    Http::fake([LOCAL_URL => Http::response(soap('true'))]);

    $result = (new FeedValidator)->validate(xmlFeed(), validRss());

    expect($result)->toBe(ValidationResult::Valid);
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'http://validator/check.cgi'
        && $request['rawdata'] === validRss()
        && $request['output'] === 'soap12');
    Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://validator/'));
});

it('trusts an invalid verdict from the local validator without asking anyone else', function () {
    useLocalValidator();
    Http::fake([LOCAL_URL => Http::response(soap('false'))]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Invalid);
    Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'http://validator/'));
});

it('falls back to the public validators and reports when the local validator is unreachable', function () {
    Exceptions::fake();
    useLocalValidator();
    Http::fake([
        LOCAL_URL => fn () => throw new ConnectionException('connection refused'),
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Valid);
    Exceptions::assertReported(ValidatorInfrastructureException::class);
});

it('falls back and reports when the local validator answers with a 5xx', function () {
    Exceptions::fake();
    useLocalValidator();
    Http::fake([
        LOCAL_URL => Http::response('Internal Server Error', 500),
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Valid);
    Exceptions::assertReported(ValidatorInfrastructureException::class);
});

it('falls back and reports when the local validator returns garbage with a 200', function () {
    Exceptions::fake();
    useLocalValidator();
    Http::fake([
        LOCAL_URL => Http::response('<html>Apache default page</html>'),
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Valid);
    Exceptions::assertReported(ValidatorInfrastructureException::class);
});

it('reports a local validator outage once per throttle window, not once per feed', function () {
    Exceptions::fake();
    useLocalValidator();
    Http::fake([
        LOCAL_URL => Http::response('', 503),
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    foreach (range(1, 5) as $_) {
        (new FeedValidator)->validate(xmlFeed(), validRss());
    }

    Exceptions::assertReportedCount(1);
});

it('reports when every validator fails to give a verdict, even without a local validator', function () {
    Exceptions::fake();
    Http::fake([
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('Sorry An error occurred while trying to validate this feed.'),
    ]);

    expect((new FeedValidator)->validate(xmlFeed(), validRss()))->toBe(ValidationResult::Unknown);
    Exceptions::assertReported(ValidatorInfrastructureException::class);
});

it('does not report when the public W3C stage fails but a verdict is still reached', function () {
    Exceptions::fake();
    Http::fake([
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    Exceptions::assertNothingReported();
});

it('does not touch the network for a local validator that is not configured', function () {
    Http::fake([
        W3C_URL => Http::response(soap('true')),
    ]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'http://validator/'));
});

/*
|--------------------------------------------------------------------------
| Which validator gave the verdict
|--------------------------------------------------------------------------
*/

it('records the local validator as the source of its verdict', function () {
    useLocalValidator();
    Http::fake([LOCAL_URL => Http::response(soap('true'))]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    expect(Context::get('feed_validator'))->toBe('local');
});

it('records the public W3C validator as the source of its verdict', function () {
    Http::fake([W3C_URL => Http::response(soap('false'))]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    expect(Context::get('feed_validator'))->toBe('w3c');
});

it('records RSS Board as the source when it gives the verdict', function () {
    Http::fake([
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    expect(Context::get('feed_validator'))->toBe('rssboard');
});

it('records the fallback, not the failed local validator, when the container is down', function () {
    Exceptions::fake();
    useLocalValidator();
    Http::fake([
        LOCAL_URL => Http::response('', 503),
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('This is a valid RSS feed.'),
    ]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    expect(Context::get('feed_validator'))->toBe('rssboard');
});

it('records no source when nobody gave a verdict, and clears a stale one', function () {
    Exceptions::fake();
    Context::add('feed_validator', 'local');
    Http::fake([
        W3C_URL => Http::response('', 429),
        RSSBOARD_URL => Http::response('Sorry An error occurred while trying to validate this feed.'),
    ]);

    (new FeedValidator)->validate(xmlFeed(), validRss());

    expect(Context::get('feed_validator'))->toBeNull();
});

it('records the structural parser as the source of an unparseable-XML verdict', function () {
    (new FeedValidator)->validate(xmlFeed(), '<not><valid><rss>');

    expect(Context::get('feed_validator'))->toBe('laminas');
});

it('records the JSON schema check as the source of a JSON verdict', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    (new FeedValidator)->validate($feed, json_encode(['version' => 'https://jsonfeed.org/version/1.1', 'title' => 'T', 'items' => []]));

    expect(Context::get('feed_validator'))->toBe('jsonschema');
});
