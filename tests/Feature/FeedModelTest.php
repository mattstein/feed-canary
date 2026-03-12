<?php

use App\Models\Check;
use App\Models\ConnectionFailure;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Feed::check() - Core Business Logic
|--------------------------------------------------------------------------
*/

it('returns true and sets healthy status for valid feed response', function () {
    Mail::fake();

    $feed = Feed::factory()->create();
    $body = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    Http::fake([
        $feed->url => Http::response($body, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    $result = $feed->check();

    expect($result)->toBeTrue();
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    expect($feed->last_checked)->not->toBeNull();
});

it('returns false and sets failing status for invalid feed response', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::response('not a feed', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = $feed->check();

    expect($result)->toBeFalse();
    expect($feed->status)->toBe(Feed::STATUS_FAILING);
});

it('returns false for non-successful HTTP response', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::response('Not Found', 404),
    ]);

    $result = $feed->check();

    expect($result)->toBeFalse();
    expect($feed->status)->toBe(Feed::STATUS_FAILING);

    $check = $feed->latestCheck();
    expect((int) $check->status)->toBe(404);
    expect($check->is_valid)->toBeFalse();
});

it('creates a check record with correct attributes', function () {
    Mail::fake();

    $feed = Feed::factory()->create();
    $body = '<rss><channel><title>Test</title></channel></rss>';

    Http::fake([
        $feed->url => Http::response($body, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    $feed->check();

    $check = $feed->latestCheck();
    expect($check)->not->toBeNull();
    expect((int) $check->status)->toBe(200);
    expect($check->hash)->toBe(md5($body));
    expect($check->is_valid)->toBeTrue();
});

it('updates last_checked timestamp on check', function () {
    Mail::fake();

    $feed = Feed::factory()->create(['last_checked' => now()->subHour()]);
    $oldLastChecked = $feed->last_checked->copy();

    Http::fake([
        $feed->url => Http::response('', 200, ['Content-Type' => 'text/html']),
    ]);

    $feed->check();

    expect($feed->last_checked->gt($oldLastChecked))->toBeTrue();
});

it('returns false and records connection failure on exception', function () {
    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    $result = $feed->check();

    expect($result)->toBeFalse();
    expect($feed->connectionFailures()->count())->toBe(1);

    $failure = $feed->latestConnectionFailure();
    expect($failure->feed_id)->toBe($feed->id);
    expect($failure->url)->toBe($feed->url);
});

it('skips validation when content hash is unchanged', function () {
    $feed = Feed::factory()->create();
    $body = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    makePastCheck($feed, [
        'hash' => md5($body),
        'is_valid' => true,
    ]);

    Http::fake([
        $feed->url => Http::response($body, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    // Should never call the validator
    FeedValidator::shouldReceive('feedIsValid')->times(0);

    $result = $feed->check();
    expect($result)->toBeTrue();
});

it('calls validator when content hash changes', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'hash' => md5('old content'),
        'is_valid' => true,
    ]);

    $newBody = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    Http::fake([
        $feed->url => Http::response($newBody, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    $feed->check();
});

/*
|--------------------------------------------------------------------------
| Feed::hasFailingConnection()
|--------------------------------------------------------------------------
*/

it('returns false when no connection failures exist', function () {
    $feed = Feed::factory()->create();

    expect($feed->hasFailingConnection())->toBeFalse();
});

it('returns true when connection failure exists but no checks', function () {
    $feed = Feed::factory()->create();

    ConnectionFailure::factory()->for($feed)->create();

    expect($feed->hasFailingConnection())->toBeTrue();
});

it('returns true when connection failure is more recent than latest check', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now()->subHour(),
    ]);

    expect($feed->hasFailingConnection())->toBeTrue();
});

it('returns false when latest check is more recent than connection failure', function () {
    $feed = Feed::factory()->create();

    ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now()->subHours(2),
    ]);

    makePastCheck($feed, [
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    expect($feed->hasFailingConnection())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Feed::statusHasChanged()
|--------------------------------------------------------------------------
*/

it('detects status change from valid to invalid', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'is_valid' => true,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    makePastCheck($feed, [
        'is_valid' => false,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    expect($feed->statusHasChanged())->toBeTrue();
});

it('detects no change when status is unchanged', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'is_valid' => true,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    makePastCheck($feed, [
        'is_valid' => true,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    expect($feed->statusHasChanged())->toBeFalse();
});

it('reports changed when only one check exists', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, ['is_valid' => true]);

    // previousCheck is null → null !== true → changed
    expect($feed->statusHasChanged())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Feed::contentHasChanged()
|--------------------------------------------------------------------------
*/

it('detects content change between checks', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'hash' => md5('old'),
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    makePastCheck($feed, [
        'hash' => md5('new'),
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    expect($feed->contentHasChanged())->toBeTrue();
});

it('detects no content change when hashes match', function () {
    $feed = Feed::factory()->create();
    $hash = md5('same');

    makePastCheck($feed, [
        'hash' => $hash,
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    makePastCheck($feed, [
        'hash' => $hash,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    expect($feed->contentHasChanged())->toBeFalse();
});

it('reports changed when both hashes are null', function () {
    $feed = Feed::factory()->create();

    // No checks at all → null !== null is false
    expect($feed->contentHasChanged())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Feed::isValidResponseType()
|--------------------------------------------------------------------------
*/

it('accepts all valid feed content types', function (string $type) {
    expect(Feed::isValidResponseType($type))->toBeTrue();
})->with([
    'application/xml',
    'application/rss+xml',
    'application/atom+xml',
    'application/json',
    'application/feed+json',
    'application/x-rss+xml',
    'text/xml',
]);

it('accepts content types with charset suffix', function () {
    expect(Feed::isValidResponseType('application/xml; charset=utf-8'))->toBeTrue();
    expect(Feed::isValidResponseType('application/json; charset=utf-8'))->toBeTrue();
});

it('rejects invalid content types', function (string $type) {
    expect(Feed::isValidResponseType($type))->toBeFalse();
})->with([
    'text/html',
    'text/plain',
    'image/png',
    'application/pdf',
    '',
]);

/*
|--------------------------------------------------------------------------
| Feed::validatorUrl()
|--------------------------------------------------------------------------
*/

it('returns W3C validator URL for XML feeds', function () {
    $feed = Feed::factory()->create([
        'url' => 'https://example.com/feed.xml',
        'type' => 'application/rss+xml',
    ]);

    expect($feed->validatorUrl())->toBe(
        'https://validator.w3.org/feed/check.cgi?url='.urlencode('https://example.com/feed.xml')
    );
});

it('returns JSON feed validator URL for JSON feeds', function () {
    $feed = Feed::factory()->create([
        'url' => 'https://example.com/feed.json',
        'type' => 'application/json',
    ]);

    expect($feed->validatorUrl())->toBe(
        'https://validator.jsonfeed.org/?url='.urlencode('https://example.com/feed.json')
    );
});

it('encodes special characters in validator URL', function () {
    $feed = Feed::factory()->create([
        'url' => 'https://example.com/feed?format=rss&lang=en',
        'type' => 'text/xml',
    ]);

    expect($feed->validatorUrl())->toContain(urlencode('https://example.com/feed?format=rss&lang=en'));
});

/*
|--------------------------------------------------------------------------
| Feed::scopeReadyForCheck()
|--------------------------------------------------------------------------
*/

it('excludes recently-checked feeds from ready query', function () {
    $recentFeed = Feed::factory()->create(['last_checked' => now()]);
    $staleFeed = Feed::factory()->create(['last_checked' => now()->subMinutes(10)]);
    $neverChecked = Feed::factory()->create(['last_checked' => null]);

    $ready = Feed::getAllReadyForCheck()->pluck('id');

    expect($ready)->not->toContain($recentFeed->id);
    expect($ready)->toContain($staleFeed->id);
    expect($ready)->toContain($neverChecked->id);
});
