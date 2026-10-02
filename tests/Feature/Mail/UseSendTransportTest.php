<?php

use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use MattStein\UseSend\Exceptions\ApiRequestFailedException;
use MattStein\UseSend\Exceptions\MissingApiKeyException;
use MattStein\UseSend\UseSendTransport;

/*
 * Feed Canary sends through useSend using mattstein/usesend-laravel. These tests
 * pin the wiring: that the package registers the mailer, that a real
 * confirmation email becomes the request useSend expects, and that a rejected
 * send is loud.
 */

beforeEach(function () {
    config([
        'usesend.api_key' => 'us_test_key',
        'usesend.base_url' => 'https://app.usesend.com',
        'mail.from.address' => 'app@feedcanary.test',
        'mail.from.name' => 'Feed Canary',
    ]);
});

function confirmFeed(): ConfirmFeed
{
    return new ConfirmFeed(Feed::factory()->create(['email' => 'reader@example.com']));
}

/*
 * Laravel resolves a mailer by name through config("mail.mailers.$name"), so
 * the 'usesend' entry in config/mail.php is what makes MAIL_MAILER=usesend
 * work. Delete it and every send dies with "Mailer [usesend] is not defined".
 */
it('sends through a default mailer named only in config/mail.php', function () {
    Http::fake();
    config(['mail.default' => 'usesend']);

    Mail::send(confirmFeed());

    Http::assertSent(fn ($request) => $request->url() === 'https://app.usesend.com/api/v1/emails');
});

it('registers the useSend mailer from the package', function () {
    $transport = Mail::mailer('usesend')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(UseSendTransport::class)
        ->and((string) $transport)->toBe('usesend');
});

it('sends a feed confirmation to the useSend api', function () {
    Http::fake();

    Mail::mailer('usesend')->send(confirmFeed());

    Http::assertSent(fn ($request) => $request->url() === 'https://app.usesend.com/api/v1/emails'
        && $request->hasHeader('Authorization', 'Bearer us_test_key')
        && $request->hasHeader('Content-Type', 'application/json')
        && $request['to'] === ['reader@example.com']
        && $request['from'] === '"Feed Canary" <app@feedcanary.test>'
        && $request['replyTo'] === ['matts@omg.lol']
        && $request['subject'] === 'Confirm Feed'
        && str_contains((string) $request['text'], 'confirm your feed'));
});

it('sends to a self-hosted instance when the base url changes', function () {
    Http::fake();
    config(['usesend.base_url' => 'https://mail.example.com']);

    Mail::mailer('usesend')->send(confirmFeed());

    Http::assertSent(fn ($request) => $request->url() === 'https://mail.example.com/api/v1/emails');
});

it('turns a rejection from useSend into an exception', function () {
    Http::fake(['*' => Http::response(['message' => 'Domain not verified'], 422)]);

    expect(fn () => Mail::mailer('usesend')->send(confirmFeed()))
        ->toThrow(ApiRequestFailedException::class, 'Domain not verified');
});

it('refuses to send without an api key', function () {
    config(['usesend.api_key' => null]);

    Mail::mailer('usesend')->send(confirmFeed());
})->throws(MissingApiKeyException::class);
