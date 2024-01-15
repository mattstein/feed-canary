<?php

use App\Http\Controllers\FeedController;
use App\Models\Check;
use App\Models\Feed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/status', function () {
    $w3cUp = Cache::get('w3c-status');
    $validatorDotOrgUp = Cache::get('validator.org-status');

    if ($w3cUp === null) {
        $w3cUp = Http::head('https://validator.w3.org/feed')->successful();
        Cache::put('w3c-status', $w3cUp, 30);
    }

    if ($validatorDotOrgUp === null) {
        $validatorDotOrgUp = Http::head('https://feedvalidator.org')->successful();
        Cache::put('validator.org-status', $validatorDotOrgUp, 30);
    }

    return view('status', [
        'lastCheck' => Check::query()->orderBy('created_at', 'DESC')->first(),
        'w3c' => $w3cUp,
        'validatorDotOrg' => $validatorDotOrgUp,
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

Route::view('/updates', 'updates');
