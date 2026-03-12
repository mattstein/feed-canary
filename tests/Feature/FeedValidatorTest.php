<?php

use App\Models\Feed;
use App\Services\FeedValidator\FeedValidator;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| FeedValidator — JSON validation
|--------------------------------------------------------------------------
*/

it('validates valid JSON feed', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    $validJson = json_encode([
        'version' => 'https://jsonfeed.org/version/1.1',
        'title' => 'Test Feed',
        'items' => [],
    ]);

    $validator = new FeedValidator;
    $result = $validator->feedIsValid($feed, $validJson);

    expect($result)->toBeTrue();
});

it('rejects invalid JSON feed missing required fields', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    $invalidJson = json_encode([
        'not_a_feed' => true,
    ]);

    $validator = new FeedValidator;
    $result = $validator->feedIsValid($feed, $invalidJson);

    expect($result)->toBeFalse();
});

it('rejects malformed JSON', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    $validator = new FeedValidator;
    $result = $validator->feedIsValid($feed, 'not json at all');

    expect($result)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| FeedValidator — XML validation
|--------------------------------------------------------------------------
*/

it('rejects unreadable XML', function () {
    $feed = Feed::factory()->create(['type' => 'application/rss+xml']);

    $validator = new FeedValidator;
    $result = $validator->feedIsValid($feed, '<not><valid><rss>');

    expect($result)->toBeFalse();
});

it('rejects empty body as invalid XML', function () {
    $feed = Feed::factory()->create(['type' => 'text/xml']);

    $validator = new FeedValidator;
    $result = $validator->feedIsValid($feed, '');

    expect($result)->toBeFalse();
});

it('routes JSON feed to JSON validator', function () {
    $feed = Feed::factory()->create(['type' => 'application/json']);

    $validJson = json_encode([
        'version' => 'https://jsonfeed.org/version/1.1',
        'title' => 'Test Feed',
        'items' => [],
    ]);

    // Should not make any HTTP calls (JSON validation is local)
    Http::fake();

    $validator = new FeedValidator;
    $validator->feedIsValid($feed, $validJson);

    Http::assertNothingSent();
});

it('falls back to RSS Board validator when W3C validator fails', function () {
    $feed = Feed::factory()->create([
        'type' => 'application/rss+xml',
        'url' => 'https://example.com/feed.xml',
    ]);

    $validRss = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    // RSS Board validator returns success
    Http::fake([
        'https://www.rssboard.org/rss-validator/*' => Http::response('This is a valid RSS feed.'),
    ]);

    $validator = new FeedValidator;
    $validator->feed = $feed;
    $validator->body = $validRss;

    // Directly test the fallback method
    $reflection = new ReflectionClass($validator);
    $method = $reflection->getMethod('isValidXmlRssBoardValidator');
    $method->setAccessible(true);

    expect($method->invoke($validator))->toBeTrue();
});

it('returns false from RSS Board validator when response indicates invalid', function () {
    $feed = Feed::factory()->create([
        'type' => 'application/rss+xml',
        'url' => 'https://example.com/feed.xml',
    ]);

    Http::fake([
        'https://www.rssboard.org/rss-validator/*' => Http::response('Sorry, this feed does not validate.'),
    ]);

    $validator = new FeedValidator;
    $validator->feed = $feed;
    $validator->body = '';

    $reflection = new ReflectionClass($validator);
    $method = $reflection->getMethod('isValidXmlRssBoardValidator');
    $method->setAccessible(true);

    expect($method->invoke($validator))->toBeFalse();
});

it('returns false from RSS Board validator on failed HTTP response', function () {
    $feed = Feed::factory()->create([
        'type' => 'application/rss+xml',
        'url' => 'https://example.com/feed.xml',
    ]);

    Http::fake([
        'https://www.rssboard.org/rss-validator/*' => Http::response('Server Error', 500),
    ]);

    $validator = new FeedValidator;
    $validator->feed = $feed;
    $validator->body = '';

    $reflection = new ReflectionClass($validator);
    $method = $reflection->getMethod('isValidXmlRssBoardValidator');
    $method->setAccessible(true);

    expect($method->invoke($validator))->toBeFalse();
});
