<?php

use App\Http\Controllers\FeedController;
use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Livewire\Updates;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class);
Route::livewire('/status', Status::class);
Route::livewire('/feed/{feed}', ManageFeed::class);
Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::livewire('/updates', Updates::class);
