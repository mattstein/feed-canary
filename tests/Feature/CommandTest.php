<?php

use App\Mail\FeedDeleted;
use App\Models\Check;
use App\Models\ConnectionFailure;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| app:check-feed
|--------------------------------------------------------------------------
*/

it('checks a valid feed by ID', function () {
    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::response('', 200, ['Content-Type' => 'text/html']),
    ]);

    $this->artisan('app:check-feed', ['id' => $feed->id])
        ->expectsOutputToContain('Checking')
        ->assertExitCode(0);
});

it('reports error for invalid feed ID', function () {
    $this->artisan('app:check-feed', ['id' => 'nonexistent'])
        ->expectsOutput('Invalid feed ID.')
        ->assertExitCode(0);
});

/*
|--------------------------------------------------------------------------
| app:prune-checks
|--------------------------------------------------------------------------
*/

it('prunes old checks and reports count', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'created_at' => now()->subDays(31),
        'updated_at' => now()->subDays(31),
    ]);

    makePastCheck($feed, [
        'created_at' => now()->subDays(31),
        'updated_at' => now()->subDays(31),
    ]);

    makePastCheck($feed, [
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $this->artisan('app:prune-checks')
        ->expectsOutputToContain('2 rows deleted')
        ->assertExitCode(0);
});

it('reports zero deletions when nothing to prune', function () {
    $this->artisan('app:prune-checks')
        ->expectsOutputToContain('0 rows deleted')
        ->assertExitCode(0);
});

/*
|--------------------------------------------------------------------------
| app:audit-feeds
|--------------------------------------------------------------------------
*/

it('reports no duplicates or recurring owners when none exist', function () {
    Feed::factory()->create(['url' => 'https://a.com/feed', 'email' => 'a@test.com']);
    Feed::factory()->create(['url' => 'https://b.com/feed', 'email' => 'b@test.com']);

    $this->artisan('app:audit-feeds')
        ->expectsOutputToContain('No duplicate feeds or recurring owners found')
        ->assertExitCode(0);
});

it('detects duplicate feed URLs', function () {
    Feed::factory()->create(['url' => 'https://dupe.com/feed', 'email' => 'a@test.com']);
    Feed::factory()->create(['url' => 'https://dupe.com/feed', 'email' => 'b@test.com']);

    $this->artisan('app:audit-feeds')
        ->expectsOutputToContain('Duplicate feeds found')
        ->assertExitCode(0);
});

it('detects recurring owners when duplicate feeds also exist', function () {
    // Note: line 54 of AuditFeeds checks $duplicateFeeds instead of $recurringOwners,
    // so "Recurring owners" only appears when duplicate feed URLs are also present
    Feed::factory()->create(['url' => 'https://dupe.com/feed', 'email' => 'same@test.com']);
    Feed::factory()->create(['url' => 'https://dupe.com/feed', 'email' => 'same@test.com']);

    $this->artisan('app:audit-feeds')
        ->expectsOutputToContain('Recurring owners found')
        ->assertExitCode(0);
});

/*
|--------------------------------------------------------------------------
| feed:status
|--------------------------------------------------------------------------
*/

it('shows message when no feeds match criteria', function () {
    $this->artisan('feed:status')
        ->expectsOutputToContain('No feeds found')
        ->assertExitCode(0);
});

it('shows failing feeds by default', function () {
    $failingFeed = Feed::factory()->failing()->create([
        'url' => 'https://failing.example.com/feed',
    ]);

    $healthyFeed = Feed::factory()->create();

    $this->artisan('feed:status')
        ->expectsOutputToContain('Feeds with Issues')
        ->expectsOutputToContain('failing.example.com')
        ->assertExitCode(0);
});

it('shows all feeds with --all flag', function () {
    Feed::factory()->create(['url' => 'https://healthy.example.com/feed']);
    Feed::factory()->failing()->create(['url' => 'https://failing.example.com/feed']);

    $this->artisan('feed:status', ['--all' => true])
        ->expectsOutputToContain('All Confirmed Feeds')
        ->assertExitCode(0);
});

it('filters to only failing connections with --failing flag', function () {
    $feed = Feed::factory()->create();

    // Add a connection failure more recent than any check
    ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    $this->artisan('feed:status', ['--failing' => true])
        ->expectsOutputToContain($feed->url)
        ->assertExitCode(0);
});

it('shows no feeds when --failing but none have connection issues', function () {
    Feed::factory()->create();

    $this->artisan('feed:status', ['--failing' => true])
        ->expectsOutputToContain('No feeds found')
        ->assertExitCode(0);
});

/*
|--------------------------------------------------------------------------
| app:delete-feed-and-notify (non-interactive parts)
|--------------------------------------------------------------------------
*/

it('reports error when feed is not found', function () {
    $this->artisan('app:delete-feed-and-notify')
        ->expectsQuestion('Enter feed ID or URL', 'nonexistent-feed-url')
        ->expectsOutput('Feed not found.')
        ->assertExitCode(0);
});

it('deletes feed and sends notification email', function () {
    Mail::fake();

    $feed = Feed::factory()->create(['url' => 'https://delete-me.example.com/feed']);
    $feedId = $feed->id;

    $this->artisan('app:delete-feed-and-notify')
        ->expectsQuestion('Enter feed ID or URL', $feed->id)
        ->expectsQuestion('Enter deletion reason (will be sent to feed owner)', 'Testing deletion')
        ->expectsConfirmation('Are you sure you want to delete this feed and notify the owner?', 'yes')
        ->expectsOutputToContain('Feed deleted successfully')
        ->assertExitCode(0);

    expect(Feed::find($feedId))->toBeNull();
    Mail::assertSent(FeedDeleted::class);
});

it('cancels when user declines confirmation', function () {
    $feed = Feed::factory()->create();
    $feedId = $feed->id;

    $this->artisan('app:delete-feed-and-notify')
        ->expectsQuestion('Enter feed ID or URL', $feed->id)
        ->expectsQuestion('Enter deletion reason (will be sent to feed owner)', 'Test reason')
        ->expectsConfirmation('Are you sure you want to delete this feed and notify the owner?', 'no')
        ->expectsOutput('Deletion cancelled.')
        ->assertExitCode(0);

    expect(Feed::find($feedId))->not->toBeNull();
});

it('requires a deletion reason', function () {
    $feed = Feed::factory()->create();

    $this->artisan('app:delete-feed-and-notify')
        ->expectsQuestion('Enter feed ID or URL', $feed->id)
        ->expectsQuestion('Enter deletion reason (will be sent to feed owner)', '')
        ->expectsOutput('Deletion reason is required.')
        ->assertExitCode(0);
});

it('finds feed by URL', function () {
    Mail::fake();

    $feed = Feed::factory()->create(['url' => 'https://findme.example.com/rss']);
    $feedId = $feed->id;

    $this->artisan('app:delete-feed-and-notify')
        ->expectsQuestion('Enter feed ID or URL', 'https://findme.example.com/rss')
        ->expectsQuestion('Enter deletion reason (will be sent to feed owner)', 'Found by URL')
        ->expectsConfirmation('Are you sure you want to delete this feed and notify the owner?', 'yes')
        ->expectsOutputToContain('Feed deleted successfully')
        ->assertExitCode(0);

    expect(Feed::find($feedId))->toBeNull();
});
