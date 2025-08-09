<?php

use App\Models\ConnectionFailure;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Are notifications sent only and exactly when they’re expected?
 */
it('sends email on new failure', function () {
    Mail::fake();

    // Start with healthy feed
    $feed = Feed::factory()->create();
    $this->assertEquals(Feed::STATUS_HEALTHY, $feed->status);
    $this->assertTrue($feed->confirmed);

    // Return an empty body with `text/html`; not a feed!
    Http::fake([
        $feed->url => Http::response('', 200, ['Content-Type' => 'text/html']),
    ]);

    // Check should get a successful but invalid response
    $feed->check();
    $feed->refresh();
    $this->assertEquals(Feed::STATUS_FAILING, $feed->status);

    // Should send failure email
    Mail::assertSent(\App\Mail\FeedFailed::class);

    $feed->delete();
});

it('does not send email on repeat failure', function () {
    Mail::fake();

    // Start with invalid, unhealthy feed
    $feed = Feed::factory()->failing()->create();
    $this->assertEquals(Feed::STATUS_FAILING, $feed->status);
    $this->assertTrue($feed->confirmed);

    // Create a “past” Check since the last two are compared to look for a state change
    makePastCheck($feed, [
        'status' => 200,
        'is_valid' => false,
    ]);

    // Return an empty body with `text/html`; not a feed!
    Http::fake([
        $feed->url => Http::response('', 200, ['Content-Type' => 'text/html']),
    ]);

    // Check should get a successful but invalid response
    $feed->check();
    $feed->refresh();
    $this->assertEquals(Feed::STATUS_FAILING, $feed->status);

    // Make sure no email is sent
    Mail::assertNotSent(\App\Mail\FeedFailed::class);

    $feed->delete();
});

it('sends email on new fix', function () {
    Mail::fake();

    // Start with invalid, unhealthy feed
    $feed = Feed::factory()->failing()->create();
    $this->assertEquals(Feed::STATUS_FAILING, $feed->status);
    $this->assertTrue($feed->confirmed);

    $oneMinuteAgo = now()->subMinutes(1);

    // Create a “past” Check since the last two are compared to look for a state change
    makePastCheck($feed, [
        'status' => 200,
        'is_valid' => false,
        'created_at' => $oneMinuteAgo,
        'updated_at' => $oneMinuteAgo,
    ]);

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    Http::fake([
        $feed->url => Http::response(
            file_get_contents(base_path('tests/Resources/valid-rss.xml')),
            200,
            ['Content-Type' => 'application/rss+xml']
        ),
    ]);

    // Run another check to get a healthy, valid response
    $feed->check();
    $feed->refresh();

    // Confirm healthy feed
    $this->assertEquals(Feed::STATUS_HEALTHY, $feed->status);

    // Make sure one email is sent
    Mail::assertSent(\App\Mail\FeedFixed::class);

    $feed->delete();
});

it('does not send email for unchanged health', function () {
    Mail::fake();

    // Start with valid, unhealthy feed
    $feed = Feed::factory()->create();
    $this->assertEquals(Feed::STATUS_HEALTHY, $feed->status);
    $this->assertTrue($feed->confirmed);

    $oneMinuteAgo = now()->subMinutes(1);

    // Create a “past” Check since the last two are compared to look for a state change
    makePastCheck($feed, [
        'status' => 200,
        'is_valid' => true,
        'created_at' => $oneMinuteAgo,
        'updated_at' => $oneMinuteAgo,
    ]);

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    Http::fake([
        $feed->url => Http::response(
            file_get_contents(base_path('tests/Resources/valid-rss.xml')),
            200,
            ['Content-Type' => 'application/rss+xml']
        ),
    ]);

    // Run another check to get a healthy, valid response
    $feed->check();
    $feed->refresh();

    // Content changes between checks, but check status does not
    $this->assertEquals(2, $feed->checks()->count());
    $this->assertFalse($feed->statusHasChanged());
    $this->assertTrue($feed->contentHasChanged());

    // Confirm still-healthy feed
    $this->assertEquals(Feed::STATUS_HEALTHY, $feed->status);

    // Make sure no email is sent
    Mail::assertNothingSent();

    $feed->delete();
});

it('does not send email for connection failure', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    // Simulate connection failure
    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    $feed->check();

    $this->travel(12)->hours();
    $feed->check();

    // Make sure no email is sent
    Mail::assertNothingSent();

    $feed->delete();
});

it('sends email for repeated connection failures', function () {
    Mail::fake();

    $feed = Feed::factory()->create();

    // Simulate connection failure
    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    $feed->check();

    $this->assertTrue($feed->hasFailingConnection());

    $this->travel(12)->hours();
    $feed->check();

    $this->travel(16)->hours();
    $feed->check();

    $this->assertEquals(3, $feed->connectionFailures()->count());
    Mail::assertSent(\App\Mail\FeedConnectionFailed::class);

    $feed->delete();
});

it('sends email for restored connection', function () {
    Mail::fake();

    $feed = Feed::factory()
        ->has(ConnectionFailure::factory()->count(3))
        ->failing()
        ->create();

    // Create a “past” Check since the last two are compared to look for a state change
    makePastCheck($feed, [
        'status' => 200,
        'is_valid' => false,
    ]);

    $this->assertTrue($feed->hasFailingConnection());
    $this->assertEquals(Feed::STATUS_FAILING, $feed->status);

    $this->travel(5)->minutes();

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    // Simulate happy check after connection failures
    Http::fake([
        $feed->url => Http::response(
            file_get_contents(base_path('tests/Resources/valid-rss.xml')),
            200,
            ['Content-Type' => 'application/rss+xml']
        ),
    ]);
    $feed->check();
    $feed->refresh();

    // Confirm now-healthy feed
    $this->assertFalse($feed->hasFailingConnection());
    $this->assertTrue($feed->statusHasChanged());
    $this->assertEquals(Feed::STATUS_HEALTHY, $feed->status);

    // Make sure one email is sent
    Mail::assertSent(\App\Mail\FeedFixed::class);

    $feed->delete();
});
