<?php

use App\Livewire\Home;
use App\Models\Feed;
use App\Services\PublicHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| PublicHttp — outbound requests to user-supplied URLs
|--------------------------------------------------------------------------
|
| These make real connections (no Http::fake), since the guard lives in
| curl's connection phase and only runs once a TCP connection succeeds.
| Each test opens its own listening socket on an ephemeral loopback port
| so that holds in any environment, and asserts on curl's "aborted by
| pre-request callback" error: a plain "connection refused" would be a
| ConnectionException too, and would pass with the guard removed.
|
*/

const BLOCKED_BY_GUARD = 'aborted by pre-request callback';

/**
 * Listen on an ephemeral port. The kernel completes the TCP handshake
 * from the backlog, so nothing needs to accept() for curl to connect.
 *
 * @return array{0: resource, 1: int}|null
 */
function loopbackListener(string $host = '127.0.0.1'): ?array
{
    $server = @stream_socket_server("tcp://{$host}:0");

    if ($server === false) {
        return null;
    }

    $name = (string) stream_socket_get_name($server, false);

    return [$server, (int) substr($name, strrpos($name, ':') + 1)];
}

beforeEach(function () {
    $listener = loopbackListener();

    expect($listener)->not->toBeNull();

    [$this->listener, $this->port] = $listener;
});

afterEach(function () {
    fclose($this->listener);
});

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

it('refuses to send to a loopback listener', function (string $host) {
    PublicHttp::client()->get("http://{$host}:{$this->port}/");
})->with([
    '127.0.0.1',
    'localhost',
    '2130706433', // 127.0.0.1 as a decimal integer
])->throws(ConnectionException::class, BLOCKED_BY_GUARD);

it('refuses to send to an IPv6 loopback listener', function () {
    $listener = loopbackListener('[::1]');

    if ($listener === null) {
        $this->markTestSkipped('IPv6 loopback is unavailable.');
    }

    [$server, $port] = $listener;

    try {
        PublicHttp::client()->get("http://[::1]:{$port}/");
    } finally {
        fclose($server);
    }
})->throws(ConnectionException::class, BLOCKED_BY_GUARD);

it('refuses non-HTTP schemes', function () {
    PublicHttp::client()->get('file:///etc/passwd');
})->throws(ConnectionException::class, "scheme 'file' is not supported");

it('rejects internal URLs when creating a feed', function () {
    Event::fake([ConnectionFailed::class]);

    Livewire::test(Home::class)
        ->set('url', "http://127.0.0.1:{$this->port}/")
        ->set('email', 'test@example.com')
        ->call('create')
        ->assertNoRedirect()
        ->assertSet('feedErrors', ['Couldn’t connect to that URL.']);

    Event::assertDispatched(
        ConnectionFailed::class,
        fn (ConnectionFailed $event) => str_contains($event->exception->getMessage(), BLOCKED_BY_GUARD),
    );

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
    $feed = Feed::factory()->create(['url' => "http://127.0.0.1:{$this->port}/"]);

    expect($feed->check())->toBeFalse()
        ->and($feed->checks()->count())->toBe(0)
        ->and($feed->connectionFailures()->sole()->message)->toContain(BLOCKED_BY_GUARD);
});
