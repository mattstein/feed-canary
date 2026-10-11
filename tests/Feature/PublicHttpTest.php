<?php

use App\Livewire\Home;
use App\Models\Feed;
use App\Services\PublicHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| PublicHttp — outbound requests to user-supplied URLs
|--------------------------------------------------------------------------
|
| These make real connections (no Http::fake), since the guard lives in
| curl's connection phase. Loopback serves a 200 inside the test
| environment, so a blocked request proves the guard, not a dead port.
|
*/

it('classifies public and non-public addresses', function (string $ip, bool $public) {
    expect(PublicHttp::isPublicIp($ip))->toBe($public);
})->with([
    ['8.8.8.8', true],
    ['2606:4700:4700::1111', true],
    ['127.0.0.1', false],
    ['10.0.0.1', false],
    ['172.17.0.1', false],
    ['192.168.1.1', false],
    ['169.254.169.254', false],
    ['100.100.100.100', false], // Tailscale (CGNAT)
    ['0.0.0.0', false],
    ['::1', false],
    ['fe80::1', false],
    ['fd00::1', false],
    ['::ffff:127.0.0.1', false],
    ['64:ff9b::7f00:1', false], // NAT64-mapped 127.0.0.1
    ['64:ff9b:1::a00:1', false], // local-use NAT64-mapped 10.0.0.1
    ['not-an-ip', false],
]);

it('can reach loopback without the guard', function () {
    expect(Http::get('http://127.0.0.1/')->status())->toBe(200);
});

it('refuses to connect to loopback', function (string $url) {
    PublicHttp::client()->get($url);
})->with([
    'http://127.0.0.1/',
    'http://localhost/',
    'http://[::1]/',
    'http://2130706433/', // 127.0.0.1 as a decimal integer
])->throws(ConnectionException::class);

it('refuses non-HTTP schemes', function () {
    PublicHttp::client()->get('file:///etc/passwd');
})->throws(ConnectionException::class);

it('rejects internal URLs when creating a feed', function () {
    Livewire::test(Home::class)
        ->set('url', 'http://127.0.0.1/')
        ->set('email', 'test@example.com')
        ->call('create')
        ->assertNoRedirect()
        ->assertSet('feedErrors', ['Couldn’t connect to that URL.']);

    expect(Feed::count())->toBe(0);
});

it('rejects non-HTTP URLs when creating a feed', function () {
    Livewire::test(Home::class)
        ->set('url', 'ftp://example.com/feed.xml')
        ->set('email', 'test@example.com')
        ->call('create')
        ->assertHasErrors(['url']);
});

it('records a connection failure when a stored feed points at an internal address', function () {
    $feed = Feed::factory()->create(['url' => 'http://127.0.0.1/']);

    expect($feed->check())->toBeFalse()
        ->and($feed->connectionFailures()->count())->toBe(1)
        ->and($feed->checks()->count())->toBe(0);
});
