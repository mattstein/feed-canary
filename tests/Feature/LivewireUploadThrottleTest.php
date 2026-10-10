<?php

use Livewire\Mechanisms\HandleRequests\EndpointResolver;

/*
|--------------------------------------------------------------------------
| Livewire upload endpoint throttling
|--------------------------------------------------------------------------
|
| Livewire applies `throttle:60,1` to its upload route. ThrottleRequests
| resolves `$request->user()`, so the default auth guard must be able to
| answer "nobody" even though the app has no user accounts.
|
*/

it('rejects unsigned upload requests without erroring', function () {
    $this->post(EndpointResolver::uploadPath())
        ->assertUnauthorized();
});

it('throttles the upload endpoint after 60 requests per minute', function () {
    $path = EndpointResolver::uploadPath();

    for ($i = 0; $i < 60; $i++) {
        $this->post($path)->assertUnauthorized();
    }

    $this->post($path)->assertTooManyRequests();
});
