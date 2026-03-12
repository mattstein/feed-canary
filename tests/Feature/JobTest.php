<?php

use App\Jobs\CheckFeed;
use App\Jobs\CheckFeeds;
use App\Jobs\PruneChecks;
use App\Jobs\PruneUnconfirmedFeeds;
use App\Models\Check;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| PruneUnconfirmedFeeds
|--------------------------------------------------------------------------
*/

it('deletes unconfirmed feeds not checked in 3+ days', function () {
    $oldUnconfirmed = Feed::factory()->unconfirmed()->create([
        'last_checked' => now()->subDays(4),
    ]);

    $recentUnconfirmed = Feed::factory()->unconfirmed()->create([
        'last_checked' => now()->subDay(),
    ]);

    $confirmedFeed = Feed::factory()->create([
        'last_checked' => now()->subDays(4),
    ]);

    (new PruneUnconfirmedFeeds)->handle();

    expect(Feed::find($oldUnconfirmed->id))->toBeNull();
    expect(Feed::find($recentUnconfirmed->id))->not->toBeNull();
    expect(Feed::find($confirmedFeed->id))->not->toBeNull();
});

it('deletes unconfirmed feeds that were never checked', function () {
    $neverChecked = Feed::factory()->unconfirmed()->create([
        'last_checked' => null,
    ]);

    (new PruneUnconfirmedFeeds)->handle();

    expect(Feed::find($neverChecked->id))->toBeNull();
});

it('does not delete confirmed feeds regardless of age', function () {
    $oldConfirmed = Feed::factory()->create([
        'last_checked' => now()->subDays(30),
    ]);

    (new PruneUnconfirmedFeeds)->handle();

    expect(Feed::find($oldConfirmed->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| PruneChecks
|--------------------------------------------------------------------------
*/

it('deletes checks older than 30 days', function () {
    $feed = Feed::factory()->create();

    $oldCheck = makePastCheck($feed, [
        'created_at' => now()->subDays(31),
        'updated_at' => now()->subDays(31),
    ]);

    $recentCheck = makePastCheck($feed, [
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    (new PruneChecks)->handle();

    expect(Check::find($oldCheck->id))->toBeNull();
    expect(Check::find($recentCheck->id))->not->toBeNull();
});

it('does not delete checks within 30 days', function () {
    $feed = Feed::factory()->create();

    $check29 = makePastCheck($feed, [
        'created_at' => now()->subDays(29),
        'updated_at' => now()->subDays(29),
    ]);

    (new PruneChecks)->handle();

    expect(Check::find($check29->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| CheckFeeds
|--------------------------------------------------------------------------
*/

it('dispatches CheckFeed jobs for ready feeds', function () {
    Queue::fake();

    $readyFeed = Feed::factory()->create([
        'last_checked' => now()->subMinutes(10),
    ]);

    $recentFeed = Feed::factory()->create([
        'last_checked' => now(),
    ]);

    (new CheckFeeds)->handle();

    Queue::assertPushed(CheckFeed::class, 1);
});

it('dispatches no jobs when no feeds are ready', function () {
    Queue::fake();

    Feed::factory()->create(['last_checked' => now()]);

    (new CheckFeeds)->handle();

    Queue::assertNothingPushed();
});

/*
|--------------------------------------------------------------------------
| CheckFeed
|--------------------------------------------------------------------------
*/

it('calls check on the feed', function () {
    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::response('', 200, ['Content-Type' => 'text/html']),
    ]);

    (new CheckFeed($feed->id))->handle();

    $feed->refresh();
    expect($feed->last_checked)->not->toBeNull();
    expect($feed->checks()->count())->toBeGreaterThanOrEqual(1);
});

it('handles non-existent feed ID gracefully', function () {
    // Should not throw
    (new CheckFeed('nonexistent-uuid-value'))->handle();

    expect(true)->toBeTrue();
});
