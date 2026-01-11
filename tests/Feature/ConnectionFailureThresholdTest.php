<?php

use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Comprehensive tests for connection failure threshold logic.
 * Ensures temporary network hiccups don't trigger notifications,
 * but persistent issues are properly surfaced to users.
 */
it('does not send notification for connection failure within 23 hours', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    // Simulate connection failure
    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();
    expect($feed->connectionFailures()->count())->toBe(1);
    expect($feed->hasFailingConnection())->toBeTrue();

    // Travel 23 hours (just under threshold)
    $this->travel(23)->hours();

    // Second failure - still within threshold
    $feed->check();
    $feed->refresh();

    expect($feed->connectionFailures()->count())->toBe(2);
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertNothingSent();

    $feed->delete();
});

it('sends notification when exceeding 24-hour threshold', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    // Simulate connection failure
    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();

    // Travel past 24 hours (24 hours + 1 minute to exceed threshold)
    $this->travel(24)->hours();
    $this->travel(1)->minute();

    // Second failure - exceeds threshold
    $feed->check();
    $feed->refresh();

    expect($feed->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class, 1);

    $feed->delete();
});

it('clears connection failure when feed recovers within threshold', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    // Simulate two failed checks, then a successful recovery
    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First check fails
    $feed->check();
    expect($feed->hasFailingConnection())->toBeTrue();

    // Travel 12 hours (still within threshold)
    $this->travel(12)->hours();

    // Second check also fails
    $feed->check();

    // Still within threshold, so should be healthy
    $feed->refresh();
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    expect($feed->connectionFailures()->count())->toBeGreaterThan(0);

    // Travel 8 more hours (20 total, still under 24 hour threshold)
    $this->travel(8)->hours();

    // Now succeed - use actual validation instead of mocking
    $validBody = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    // Mock validator to return true
    FeedValidator::shouldReceive('feedIsValid')
        ->andReturn(true);

    Http::fake([
        $feed->url => Http::response($validBody, 200, ['Content-Type' => 'application/rss+xml']),
    ]);

    // Third check succeeds - recovery within threshold!
    $feed->check();
    $feed->refresh();

    // Feed should be healthy and no notification sent (recovered within threshold)
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertNothingSent();

    $feed->delete();
});

it('handles multiple connection failures across threshold boundary', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();
    $this->travel(6)->hours();

    // Second failure (6 hours)
    $feed->check();
    $this->travel(6)->hours();

    // Third failure (12 hours)
    $feed->check();
    $this->travel(6)->hours();

    // Fourth failure (18 hours) - still no notification
    $feed->check();
    $feed->refresh();
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertNothingSent();

    $this->travel(7)->hours();

    // Fifth failure (25 hours) - exceeds threshold
    $feed->check();
    $feed->refresh();

    expect($feed->connectionFailures()->count())->toBe(5);
    expect($feed->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class, 1);

    $feed->delete();
});

it('does not send duplicate notifications for persistent failures', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();

    // Exceed threshold
    $this->travel(25)->hours();
    $feed->check();
    $feed->refresh();

    expect($feed->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class, 1);

    // Continue failing for another day
    $this->travel(12)->hours();
    $feed->check();

    $this->travel(12)->hours();
    $feed->check();

    // Should still only have sent one notification
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class, 1);

    $feed->delete();
});

it('respects custom connection failure threshold configuration', function () {
    Mail::fake();

    // Set custom threshold to 2 hours (7200 seconds)
    config(['app.connection_failure_threshold' => 7200]);

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();

    // Travel 1.5 hours (under custom threshold)
    $this->travel(90)->minutes();
    $feed->check();
    $feed->refresh();

    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertNothingSent();

    // Travel another hour (2.5 hours total, over custom threshold)
    $this->travel(60)->minutes();
    $feed->check();
    $feed->refresh();

    expect($feed->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class, 1);

    $feed->delete();
});

it('handles recovery after exceeding threshold', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    $validBody = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    $sequence = Http::sequence();

    // Three checks: two failures, then success (6 failures + 1 success)
    for ($i = 0; $i < 6; $i++) {
        $sequence->pushFailedConnection();
    }

    $sequence->push($validBody, 200, ['Content-Type' => 'application/rss+xml']);

    Http::fake([
        "{$feed->url}*" => $sequence,
        '*' => Http::response(),
    ]);

    $feed->check();

    $this->travel(12)->hours();
    $feed->check();

    // Exceed threshold
    $this->travel(16)->hours();
    $feed->check();
    $feed->refresh();

    expect($feed->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class, 1);

    // Travel a bit more and recover
    $this->travel(5)->minutes();

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    $feed->check();
    $feed->refresh();

    // Feed should recover and send fixed notification
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertSent(\App\Mail\FeedFixed::class, 1);

    $feed->delete();
});

it('handles feed that fails, recovers briefly, then fails again within threshold', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    $validBody = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    $sequence = Http::sequence();

    // First check: fail
    for ($i = 0; $i < 3; $i++) {
        $sequence->pushFailedConnection();
    }

    // Second check at 10 hours: success
    $sequence->push($validBody, 200, ['Content-Type' => 'application/rss+xml']);

    // Third check at 15 hours: fail again
    for ($i = 0; $i < 3; $i++) {
        $sequence->pushFailedConnection();
    }

    Http::fake([
        "{$feed->url}*" => $sequence,
        '*' => Http::response(),
    ]);

    // First failure
    $feed->check();
    expect($feed->hasFailingConnection())->toBeTrue();

    // Travel 10 hours and recover
    $this->travel(10)->hours();

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    $feed->check();
    $feed->refresh();

    expect($feed->hasFailingConnection())->toBeFalse();
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);

    // No notification sent because it recovered within threshold
    Mail::assertNothingSent();

    // Travel 5 more hours and fail again (new failure window starts)
    $this->travel(5)->hours();
    $feed->check();
    $feed->refresh();

    // New failure, but within threshold of this new failure
    expect($feed->hasFailingConnection())->toBeTrue();
    expect($feed->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertNothingSent();

    $feed->delete();
});

it('respects notify_connection_failures config', function () {
    Mail::fake();

    // Disable connection failure notifications
    config(['app.notify_connection_failures' => false]);

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();

    // Exceed threshold
    $this->travel(25)->hours();
    $feed->check();
    $feed->refresh();

    // Should still mark as failing
    expect($feed->status)->toBe(Feed::STATUS_FAILING);

    // But should not send notification
    Mail::assertNothingSent();

    $feed->delete();
});

it('only notifies for confirmed feeds', function () {
    Mail::fake();

    // Create unconfirmed feed
    $feed = Feed::factory()->unconfirmed()->create();
    expect($feed->confirmed)->toBeFalse();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();

    // Exceed threshold
    $this->travel(25)->hours();
    $feed->check();
    $feed->refresh();

    // Should mark as failing
    expect($feed->status)->toBe(Feed::STATUS_FAILING);

    // But should not send notification (unconfirmed)
    Mail::assertNothingSent();

    $feed->delete();
});

it('creates connection failure check record when threshold exceeded', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    // First failure
    $feed->check();

    $checksBeforeThreshold = $feed->checks()->count();

    // Exceed threshold
    $this->travel(25)->hours();
    $feed->check();
    $feed->refresh();

    // Should have created a check record with CONNECTION_FAILURE status
    expect($feed->checks()->count())->toBe($checksBeforeThreshold + 1);

    $latestCheck = $feed->latestCheck();
    expect((int) $latestCheck->status)->toBe(\App\Models\Check::STATUS_CONNECTION_FAILURE);
    expect($latestCheck->is_valid)->toBeFalse();

    $feed->delete();
});

it('tracks connection failure timestamps accurately', function () {
    $feed = Feed::factory()->create();

    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    $startTime = now();

    // First failure
    $feed->check();

    $firstFailure = $feed->connectionFailures()->first();
    expect($firstFailure->created_at->timestamp)->toBeGreaterThanOrEqual($startTime->timestamp);

    // Travel and create second failure
    $this->travel(12)->hours();
    $feed->check();

    $secondFailure = $feed->connectionFailures()->latest()->first();
    expect($secondFailure->created_at->diffInHours($firstFailure->created_at))->toBeGreaterThanOrEqual(12);

    $feed->delete();
});
