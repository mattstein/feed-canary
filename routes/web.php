<?php

use App\Http\Controllers\FeedController;
use App\Models\Check;
use App\Models\Feed;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/status', function () {
    $w3c = \Illuminate\Support\Facades\Http::get('https://validator.w3.org/feed');
    $validatorDotOrg = \Illuminate\Support\Facades\Http::get('https://feedvalidator.org');

    return view('status', [
        'lastCheck' => Check::query()->orderBy('created_at', 'DESC')->first(),
        'w3c' => $w3c->successful(),
        'validatorDotOrg' => $validatorDotOrg->successful(),
    ]);
});

Route::post('/add', [FeedController::class, 'create']);

Route::get('/feed/{id}', function (string $id) {
    if (! $feed = Feed::query()->find($id)) {
        abort(404);
    }

    return view('manage-feed', ['feed' => $feed]);
});

Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::post('/feed/{id}/delete', [FeedController::class, 'delete']);
