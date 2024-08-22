<?php

use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Do the very basics seem to be working?
 */
it('has landing page', function () {
    $this->get('/')
        ->assertOk();
});

it('has status page', function () {
    // Don’t bother either site for this test
    Http::fake([
        'https://validator.w3.org/feed' => Http::response(),
        'https://feedvalidator.org' => Http::response(),
    ]);

    $this->get('/status')
        ->assertOk();
});

it('has feed manage page', function () {
    $feed = Feed::factory()->create();

    $this->get($feed->manageUrl())
        ->assertOk()
        ->assertDontSeeText('Last checked');

    $feed->delete();
});

it('returns 404 for invalid feed', function () {
    $this->get('/feed/nope-not-a-real-url')
        ->assertStatus(404);
});

it('rejects invalid feed URL', function () {
    // Don’t bother Google, but give back a “web page” that’s distinctly not a feed
    Http::fake([
        'https://google.com/' => Http::response(
            '<html><head><title>The Google</title></head><body><p>Hey it’s Google who dis?</p></body></html>',
        ),
    ]);

    $this->post('/add', [
        'url' => 'https://google.com/',
        'email' => 'hi@example.foo',
    ])
        ->assertRedirect('/');
});

it('rejects invalid email address', function () {
    $this->post('/add', [
        'url' => 'https://www.theverge.com/rss/frontpage',
        'email' => 'nope',
    ])
        ->assertRedirect('/');
});

it('accepts valid input', function () {
    Mail::fake();

    // Don’t really bother The Verge; give back some basic, valid RSS
    Http::fake([
        'https://www.theverge.com/rss/frontpage' => Http::response(
            file_get_contents(base_path('tests/Resources/valid-rss.xml')),
            200,
            ['Content-Type' => 'application/rss+xml'],
        ),
    ]);

    $this->post('/add', [
        'url' => 'https://www.theverge.com/rss/frontpage',
        'email' => 'test@example.foo',
    ])
        ->assertRedirectContains('/feed/');

    Mail::assertSent(ConfirmFeed::class);

    Feed::where([
        'url' => 'https://www.theverge.com/rss/frontpage',
        'email' => 'test@example.foo',
    ])->delete();
});

it('deletes feed on request', function () {
    $feed = Feed::factory()->create();
    $feedId = $feed->id;

    $this->post($feed->deleteAction())
        ->assertRedirect('/');

    $this->assertNull(Feed::find($feedId));
});
