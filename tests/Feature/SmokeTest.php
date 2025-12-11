<?php

use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Mail\ConfirmFeed;
use App\Models\Feed;
use FeedValidator as FeedValidatorFacade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * Do the very basics seem to be working?
 */
it('has landing page', function () {
    Livewire::test(Home::class)
        ->assertOk();
});

it('has status page', function () {
    // Don’t bother either site for this test
    Http::fake([
        'https://validator.w3.org/feed' => Http::response(),
        'https://feedvalidator.org' => Http::response(),
    ]);

    Livewire::test(Status::class)
        ->assertSeeText('Status')
        ->assertSeeText('Last check was')
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

    Livewire::test(Home::class)
        ->set('url', 'https://google.com/')
        ->set('email', 'hi@example.foo')
        ->runAction('create')
        ->assertOk()
        ->assertNoRedirect()
        ->assertSeeText('That URL doesn’t return a JSON or RSS feed.');
});

it('rejects invalid email address', function () {
    Livewire::test(Home::class)
        ->set('url', 'https://www.theverge.com/rss/frontpage')
        ->set('email', 'nope')
        ->call('create')
        ->assertOk()
        ->assertNoRedirect()
        ->assertSeeText('The email field must be a valid email address.');
});

it('accepts valid input', function () {
    Mail::fake();
    FeedValidatorFacade::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    // Don’t really bother The Verge; give back some basic, valid RSS
    Http::fake([
        'https://www.theverge.com/rss/frontpage*' => Http::response(
            file_get_contents(base_path('tests/Resources/valid-rss.xml')),
            200,
            ['Content-Type' => 'application/rss+xml'],
        ),
        '*' => Http::response(),
    ]);

    Livewire::test(Home::class)
        ->set('url', 'https://www.theverge.com/rss/frontpage')
        ->set('email', 'test@example.foo')
        ->call('create')
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

    Livewire::test(ManageFeed::class, ['feed' => $feed])
        ->call('delete')
        ->assertRedirect('/');

    Livewire::test(Home::class)
        ->assertSee('Your feed monitor has been deleted.');

    expect(Feed::find($feedId))
        ->toBeNull();
});
