<?php

use App\Providers\HorizonServiceProvider;
use Laravel\Horizon\Horizon;

it('routes Horizon mail notifications to the configured address', function () {
    config(['horizon.notification_email' => 'ops@example.test']);

    (new HorizonServiceProvider(app()))->boot();

    expect(Horizon::$email)->toBe('ops@example.test');
});

it('does not hardcode a notification address in the provider', function () {
    expect(file_get_contents(app_path('Providers/HorizonServiceProvider.php')))
        ->not->toMatch('/[\w.+-]+@[\w-]+\.[\w.-]+/');
});
