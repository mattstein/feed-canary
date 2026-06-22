<?php

use App\Http\Controllers\FeedController;
use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Livewire\Updates;
use Illuminate\Support\Facades\Route;

// Health endpoint for Coolify's rolling-deploy check (GET /up on the octane port).
// A plain 200 confirms the container booted and is serving, which is what gates
// the Start-before-Stop cutover.
Route::get('/up', fn () => response('OK', 200));

Route::get('/', Home::class);
Route::get('/status', Status::class);
Route::get('/feed/{feed}', ManageFeed::class);
Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::get('/updates', Updates::class);
