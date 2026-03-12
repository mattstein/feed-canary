<?php

use App\Livewire\Status;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Status component — validator availability checks
|--------------------------------------------------------------------------
*/

it('detects W3C validator as up', function () {
    Cache::flush();

    Http::fake([
        'https://validator.w3.org/feed' => Http::response('', 200),
        'https://www.feedvalidator.org' => Http::response('', 200),
    ]);

    $component = new Status;
    $component->updateW3cStatus();

    expect($component->w3cStatus)->toBe('up');
});

it('detects W3C validator as down on failure', function () {
    Cache::flush();

    Http::fake([
        'https://validator.w3.org/feed' => Http::response('', 500),
    ]);

    $component = new Status;
    $component->updateW3cStatus();

    expect($component->w3cStatus)->toBe('down');
});

it('detects W3C validator as down on connection exception', function () {
    Cache::flush();

    Http::fake([
        'https://validator.w3.org/feed' => Http::failedConnection(),
    ]);

    $component = new Status;
    $component->updateW3cStatus();

    expect($component->w3cStatus)->toBe('down');
});

it('caches W3C status and uses cache on next call', function () {
    Cache::flush();

    Http::fake([
        'https://validator.w3.org/feed' => Http::response('', 200),
    ]);

    $component = new Status;
    $component->updateW3cStatus();

    expect(Cache::get('w3c-status'))->toBe('up');

    // Second call should use cache, not HTTP
    Http::fake([
        'https://validator.w3.org/feed' => Http::response('', 500),
    ]);

    $component2 = new Status;
    $component2->updateW3cStatus();

    expect($component2->w3cStatus)->toBe('up'); // Still cached as up
});

it('detects FeedValidator.org as up', function () {
    Cache::flush();

    Http::fake([
        'https://www.feedvalidator.org' => Http::response('', 200),
    ]);

    $component = new Status;
    $component->updateValidatorDotOrgStatus();

    expect($component->validatorDotOrgStatus)->toBe('up');
});

it('detects FeedValidator.org as down on failure', function () {
    Cache::flush();

    Http::fake([
        'https://www.feedvalidator.org' => Http::response('', 500),
    ]);

    $component = new Status;
    $component->updateValidatorDotOrgStatus();

    expect($component->validatorDotOrgStatus)->toBe('down');
});

it('detects FeedValidator.org as down on connection exception', function () {
    Cache::flush();

    Http::fake([
        'https://www.feedvalidator.org' => Http::failedConnection(),
    ]);

    $component = new Status;
    $component->updateValidatorDotOrgStatus();

    expect($component->validatorDotOrgStatus)->toBe('down');
});

/*
|--------------------------------------------------------------------------
| Status component — emoji and description helpers
|--------------------------------------------------------------------------
*/

it('returns correct status emojis', function () {
    $component = new Status;

    expect($component->getStatusEmoji(null))->toBe('⏳');
    expect($component->getStatusEmoji('up'))->toBe('✅');
    expect($component->getStatusEmoji('down'))->toBe('‼️');
    expect($component->getStatusEmoji('unknown'))->toBe('🤷');
});

it('returns correct status descriptions', function () {
    $component = new Status;

    expect($component->getStatusDescription(null))->toBe('…');
    expect($component->getStatusDescription('up'))->toBe('seems fine.');
    expect($component->getStatusDescription('down'))->toBe('may be down!');
    expect($component->getStatusDescription('unknown'))->toBe('');
});
