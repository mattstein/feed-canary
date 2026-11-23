<?php

use App\Mail\FeedFailed;
use App\Models\Check;
use App\Models\Feed;
use App\Services\FeedValidator\ValidationResult;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

const FEED_BODY = '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title></channel></rss>';

function fakeFeedResponse(Feed $feed): void
{
    Http::fake([$feed->url => Http::response(FEED_BODY, 200, ['Content-Type' => 'application/rss+xml'])]);
}

it('leaves status alone and sends no mail when the verdict is Unknown', function () {
    Mail::fake();
    $feed = Feed::factory()->create();
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->once()->andReturn(ValidationResult::Unknown);

    $feed->check();
    $feed->refresh();

    expect($feed->status)->toBe(Feed::STATUS_HEALTHY)
        ->and($feed->last_notified)->toBeNull()
        ->and($feed->latestCheck()->is_valid)->toBeNull();
    Mail::assertNothingSent();
});

it('keeps a failing feed failing and silent when the verdict is Unknown', function () {
    Mail::fake();
    $feed = Feed::factory()->failing()->create();
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->once()->andReturn(ValidationResult::Unknown);

    $feed->check();

    expect($feed->fresh()->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertNothingSent();
});

it('notifies when the verdict is Invalid', function () {
    Mail::fake();
    $feed = Feed::factory()->create();
    makePastCheck($feed, ['is_valid' => true, 'validated_at' => now()->subMinute()]);
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->once()->andReturn(ValidationResult::Invalid);

    $feed->check();

    expect($feed->fresh()->status)->toBe(Feed::STATUS_FAILING);
    Mail::assertSent(FeedFailed::class);
});

it('revalidates an unchanged body when the last verdict is older than the reuse window', function () {
    Mail::fake();
    $feed = Feed::factory()->failing()->create();
    $old = now()->subSeconds(config('feed.revalidate_after') + 60);
    makePastCheck($feed, [
        'is_valid' => false,
        'hash' => md5(FEED_BODY),
        'created_at' => now()->subMinute(),
        'validated_at' => $old,
    ]);
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->once()->andReturn(ValidationResult::Valid);

    $feed->check();

    $latest = $feed->latestCheck();
    expect($latest->is_valid)->toBeTrue()
        ->and($latest->validated_at->isAfter($old))->toBeTrue()
        ->and($feed->fresh()->status)->toBe(Feed::STATUS_HEALTHY);
});

it('reuses a recent verdict for an unchanged body and carries its validation time forward', function () {
    $feed = Feed::factory()->create();
    $validatedAt = now()->subHour()->startOfSecond();
    makePastCheck($feed, ['is_valid' => true, 'hash' => md5(FEED_BODY), 'validated_at' => $validatedAt]);
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->never();

    $feed->check();

    $latest = $feed->latestCheck();
    expect($latest->is_valid)->toBeTrue()
        ->and($latest->validated_at->equalTo($validatedAt))->toBeTrue();
});

it('eventually revalidates a body that never changes, even though every check is fresh', function () {
    $feed = Feed::factory()->create();
    makePastCheck($feed, [
        'is_valid' => true,
        'hash' => md5(FEED_BODY),
        'created_at' => now()->subMinute(),
        'validated_at' => now()->subSeconds(config('feed.revalidate_after') + 1),
    ]);
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->once()->andReturn(ValidationResult::Valid);

    $feed->check();
});

it('never inherits a null verdict for an unchanged body', function () {
    $feed = Feed::factory()->create();
    makePastCheck($feed, ['is_valid' => null, 'hash' => md5(FEED_BODY), 'validated_at' => null]);
    fakeFeedResponse($feed);

    FeedValidator::shouldReceive('validate')->once()->andReturn(ValidationResult::Valid);

    $feed->check();

    expect($feed->latestCheck()->is_valid)->toBeTrue();
});

it('sends zero notifications when a feed goes Valid, Unknown, Valid', function () {
    Mail::fake();
    $feed = Feed::factory()->create();
    makePastCheck($feed, ['is_valid' => true, 'created_at' => now()->subMinutes(3), 'validated_at' => now()->subMinutes(3)]);

    foreach ([ValidationResult::Valid, ValidationResult::Unknown, ValidationResult::Valid] as $i => $verdict) {
        // Distinct bodies force a fresh validation every time.
        Http::swap(new Factory);
        Http::fake([$feed->url => Http::response(FEED_BODY.str_repeat(' ', $i + 1), 200, ['Content-Type' => 'application/rss+xml'])]);
        FeedValidator::shouldReceive('validate')->once()->andReturn($verdict);

        $this->travel(1)->minutes();
        $feed->refresh()->check();
    }

    expect($feed->fresh()->status)->toBe(Feed::STATUS_HEALTHY);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    expect(Check::where('feed_id', $feed->id)->whereNull('is_valid')->count())->toBe(1);
});

it('feed:revalidate --stale expires pinned verdicts on failing feeds only, and is idempotent', function () {
    $failing = Feed::factory()->failing()->create();
    $healthy = Feed::factory()->create();
    $recent = now()->subMinute();

    $pinned = makePastCheck($failing, ['is_valid' => false, 'validated_at' => $recent]);
    $fine = makePastCheck($healthy, ['is_valid' => true, 'validated_at' => $recent]);

    $this->artisan('feed:revalidate', ['--stale' => true])->assertSuccessful();
    $this->artisan('feed:revalidate', ['--stale' => true])->assertSuccessful();

    expect($pinned->fresh()->validated_at)->toBeNull()
        ->and($pinned->fresh()->is_valid)->toBeFalse() // never bulk-flipped to healthy
        ->and($failing->fresh()->status)->toBe(Feed::STATUS_FAILING)
        ->and($fine->fresh()->validated_at)->not->toBeNull();
});
