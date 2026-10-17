<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Home;
use App\Livewire\ManageFeed;
use App\Livewire\Status;
use App\Livewire\Updates;
use Illuminate\Support\Facades\Route;

Route::view('/about', 'about');
Route::get('/sitemap.xml', SitemapController::class);
Route::livewire('/', Home::class);
Route::livewire('/status', Status::class);
Route::livewire('/feed/{feed}', ManageFeed::class);
Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::livewire('/updates', Updates::class);
