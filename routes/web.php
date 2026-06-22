<?php

use App\Http\Controllers\FeedController;
use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Livewire\Updates;
use Illuminate\Support\Facades\Route;

// TEMPORARY — confirms what client IP the app resolves behind the proxy. Remove
// once Horizon access is verified.
Route::get('/_ip', fn () => [
    'getClientIp' => request()->getClientIp(),
    'ip' => request()->ip(),
    'xff' => request()->header('X-Forwarded-For'),
    'remote_addr' => request()->server('REMOTE_ADDR'),
]);

Route::get('/', Home::class);
Route::get('/status', Status::class);
Route::get('/feed/{feed}', ManageFeed::class);
Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::get('/updates', Updates::class);
