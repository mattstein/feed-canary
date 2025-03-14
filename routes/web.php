<?php

use App\Http\Controllers\FeedController;
use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Livewire\Updates;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class);
Route::get('/status', Status::class);
Route::get('/feed/{feed}', ManageFeed::class);
Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::get('/updates', Updates::class);
