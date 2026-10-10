<?php

use Livewire\Mechanisms\HandleRequests\EndpointResolver;

/*
|--------------------------------------------------------------------------
| ThrottleLivewireUpdates
|--------------------------------------------------------------------------
|
| Paths are derived from EndpointResolver rather than hardcoded, since
| Livewire 4 prefixes its endpoints with a hash of APP_KEY. A hardcoded
| `livewire/update` silently stopped matching after the Livewire 4 upgrade.
|
*/

function livewireUpdate(string $ip = '203.0.113.10', array $headers = ['X-Livewire' => 'true'])
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(EndpointResolver::updatePath(), ['components' => []], $headers);
}

it('throttles Livewire update requests past the limit', function () {
    for ($i = 0; $i < 120; $i++) {
        expect(livewireUpdate()->status())->not->toBe(429);
    }

    livewireUpdate()->assertStatus(429);
});

it('throttles Livewire update requests per IP', function () {
    for ($i = 0; $i < 121; $i++) {
        livewireUpdate('203.0.113.10');
    }

    livewireUpdate('203.0.113.10')->assertStatus(429);
    expect(livewireUpdate('203.0.113.20')->status())->not->toBe(429);
});

it('does not throttle POSTs without the X-Livewire header', function () {
    for ($i = 0; $i < 125; $i++) {
        expect(livewireUpdate(headers: [])->status())->not->toBe(429);
    }
});
