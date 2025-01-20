<?php

use App\Models\Check;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;

it('ignores unconfirmed feeds from check cycles', function () {
    $testFeed = Feed::factory()->unconfirmed()->create();

    $this->assertNotContains(
        $testFeed,
        Feed::getAllReadyForCheck()->get()
    );

    $testFeed->delete();
});

it('does not re-validate unchanged content', function () {
    $feed = Feed::factory()->create();
    $fakeResponseBody = file_get_contents(base_path('tests/Resources/valid-rss.xml'));

    // First call to the feed URL should return valid RSS, second should be empty text/html
    Http::fake([
        $feed->url => Http::sequence()
            ->push($fakeResponseBody, 200, ['Content-Type' => 'application/rss+xml'])
            ->push('', 200, ['Content-Type' => 'text/html']),

    ]);

    $oneMinuteAgo = now()->subMinutes(1);

    // Create a pretend previous check that was successful, with the fake response hash
    $check = new Check;

    $check->feed_id = $feed->id;
    $check->status = 200;
    $check->headers = json_encode([]);
    $check->hash = md5($fakeResponseBody);
    $check->created_at = $oneMinuteAgo;
    $check->updated_at = $oneMinuteAgo;
    $check->is_valid = true;

    $check->save();

    // After re-receiving the same feed content, external validation should be skipped
    FeedValidator::shouldReceive('feedIsValid')->times(0);

    $feed->check();

    $this->assertEquals(Feed::STATUS_HEALTHY, $feed->status);

    // Changed content *should* trigger a new validation check though
    FeedValidator::shouldReceive('feedIsValid')->once();

    $feed->check();

    $this->assertEquals(Feed::STATUS_FAILING, $feed->status);

    $feed->delete();
});

it('handles connection failure', function () {
    $feed = Feed::factory()->create();
    $originalStatus = $feed->status;

    // Simulate connection failure
    Http::fake([
        $feed->url => Http::failedConnection(),
    ]);

    $feed->check();

    $this->assertTrue($feed->hasFailingConnection());
    $this->assertEquals($originalStatus, $feed->status); // No change
    $this->assertTrue(now()->diffInSeconds($feed->last_checked) < 15);
});
