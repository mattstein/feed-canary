<?php

use App\Models\ConnectionFailure;
use App\Models\Feed;

/*
|--------------------------------------------------------------------------
| ConnectionFailure::exceedsThreshold() — all code paths
|--------------------------------------------------------------------------
*/

it('exceeds threshold when time since latest check exceeds max seconds', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    $failure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    expect($failure->exceedsThreshold())->toBeTrue();
});

it('does not exceed threshold when time since latest check is within limit', function () {
    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'created_at' => now()->subHours(12),
        'updated_at' => now()->subHours(12),
    ]);

    $failure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    expect($failure->exceedsThreshold())->toBeFalse();
});

it('uses oldest connection failure when no checks exist', function () {
    $feed = Feed::factory()->create();

    // Create oldest failure 25+ hours ago
    ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now()->subHours(26),
    ]);

    // Create the "current" failure now
    $latestFailure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    expect($latestFailure->exceedsThreshold())->toBeTrue();
});

it('does not exceed threshold using oldest failure when within limit', function () {
    $feed = Feed::factory()->create();

    // Oldest failure 12 hours ago
    ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now()->subHours(12),
    ]);

    // Current failure now
    $latestFailure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    expect($latestFailure->exceedsThreshold())->toBeFalse();
});

it('returns false when only one failure exists and no checks', function () {
    $feed = Feed::factory()->create();

    // Single failure — oldest and current are the same, diff is 0
    $failure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    expect($failure->exceedsThreshold())->toBeFalse();
});

it('respects custom threshold config', function () {
    config(['app.connection_failure_threshold' => 3600]); // 1 hour

    $feed = Feed::factory()->create();

    makePastCheck($feed, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $failure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now(),
    ]);

    expect($failure->exceedsThreshold())->toBeTrue();
});

it('uses absolute time difference for threshold comparison', function () {
    $feed = Feed::factory()->create();

    // Check created AFTER the failure (edge case)
    $failure = ConnectionFailure::factory()->for($feed)->create([
        'created_at' => now()->subHours(26),
    ]);

    makePastCheck($feed, [
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($failure->exceedsThreshold())->toBeTrue();
});
