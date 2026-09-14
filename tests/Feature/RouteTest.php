<?php

use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Livewire\Updates;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Full-page Livewire route resolution
|--------------------------------------------------------------------------
|
| These hit the routes over real HTTP (not Livewire::test) to prove the
| route definitions themselves resolve to the right component, independent
| of how those routes are declared (Route::get(Class::class) vs
| Route::livewire()).
|
*/

it('resolves the home route to the Home component', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeLivewire(Home::class);
});

it('resolves the status route to the Status component', function () {
    Http::fake([
        'https://validator.w3.org/feed' => Http::response(),
        'https://feedvalidator.org' => Http::response(),
    ]);

    $this->get('/status')
        ->assertOk()
        ->assertSeeLivewire(Status::class);
});

it('resolves the feed manage route to the ManageFeed component', function () {
    $feed = Feed::factory()->create();

    $this->get($feed->manageUrl())
        ->assertOk()
        ->assertSeeLivewire(ManageFeed::class);

    $feed->delete();
});

it('resolves the updates route to the Updates component', function () {
    $this->get('/updates')
        ->assertOk()
        ->assertSeeLivewire(Updates::class);
});

it('shows the updates page content', function () {
    $this->get('/updates')
        ->assertOk()
        ->assertSeeText('Updates')
        ->assertSeeText('Overhauled front end to use Livewire and Blade.');
});

it('resolves the feed confirmation route to the controller', function () {
    $feed = Feed::factory()->unconfirmed()->create();

    $this->get("/feed/{$feed->id}/confirm/{$feed->confirmation_code}")
        ->assertRedirect();

    $feed->delete();
});
