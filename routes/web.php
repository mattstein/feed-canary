<?php

use App\Http\Controllers\FeedController;
use App\Models\Feed;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::post('/add', [FeedController::class, 'create']);

Route::get('/feed/{id}', function (string $id) {
    $feed = Feed::query()
        ->find($id);
    return view('manage-feed', ['feed' => $feed]);
    // TODO: or 404
});

Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::post('/feed/{id}/delete', [FeedController::class, 'delete']);
