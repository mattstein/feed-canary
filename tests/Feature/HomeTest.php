<?php

use App\Livewire\Home;
use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Home::create() — edge cases
|--------------------------------------------------------------------------
*/

it('handles connection exception when checking feed URL', function () {
    Http::fake([
        '*' => Http::failedConnection(),
    ]);

    Livewire::test(Home::class)
        ->set('url', 'https://unreachable.example.com/feed')
        ->set('email', 'test@example.com')
        ->call('create')
        ->assertOk()
        ->assertNoRedirect()
        ->assertSet('feedErrors', fn ($errors) => count($errors) > 0);
});

it('rejects URL missing scheme', function () {
    Livewire::test(Home::class)
        ->set('url', 'not-a-url')
        ->set('email', 'test@example.com')
        ->call('create')
        ->assertHasErrors(['url']);
});

it('rejects empty URL', function () {
    Livewire::test(Home::class)
        ->set('url', '')
        ->set('email', 'test@example.com')
        ->call('create')
        ->assertHasErrors(['url']);
});

it('rejects empty email', function () {
    Livewire::test(Home::class)
        ->set('url', 'https://example.com/feed')
        ->set('email', '')
        ->call('create')
        ->assertHasErrors(['email']);
});

it('coerces array URL to string', function () {
    $component = Livewire::test(Home::class)
        ->set('url', ['https://example.com/feed', 'extra']);

    expect($component->get('url'))->toBe('https://example.com/feed');
});

it('coerces array email to string', function () {
    $component = Livewire::test(Home::class)
        ->set('email', ['test@example.com', 'extra']);

    expect($component->get('email'))->toBe('test@example.com');
});

it('sends confirmation email on successful feed creation', function () {
    Mail::fake();

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    Http::fake([
        'https://valid.example.com/feed*' => Http::response(
            file_get_contents(base_path('tests/Resources/valid-rss.xml')),
            200,
            ['Content-Type' => 'application/rss+xml'],
        ),
        '*' => Http::response(),
    ]);

    Livewire::test(Home::class)
        ->set('url', 'https://valid.example.com/feed')
        ->set('email', 'confirm@example.com')
        ->call('create')
        ->assertRedirectContains('/feed/');

    Mail::assertSent(ConfirmFeed::class);

    // Verify feed was created
    $feed = Feed::where('url', 'https://valid.example.com/feed')->first();
    expect($feed)->not->toBeNull();
    expect($feed->email)->toBe('confirm@example.com');
    expect($feed->confirmation_code)->not->toBeNull();

    $feed->delete();
});

it('stores content type from response', function () {
    Mail::fake();

    FeedValidator::shouldReceive('feedIsValid')
        ->once()
        ->andReturn(true);

    Http::fake([
        'https://json.example.com/feed*' => Http::response(
            json_encode(['version' => 'https://jsonfeed.org/version/1.1', 'title' => 'Test', 'items' => []]),
            200,
            ['Content-Type' => 'application/feed+json'],
        ),
        '*' => Http::response(),
    ]);

    Livewire::test(Home::class)
        ->set('url', 'https://json.example.com/feed')
        ->set('email', 'json@example.com')
        ->call('create')
        ->assertRedirectContains('/feed/');

    $feed = Feed::where('url', 'https://json.example.com/feed')->first();
    expect($feed->type)->toBe('application/feed+json');

    $feed->delete();
});
