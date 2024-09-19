<?php

use App\Http\Controllers\FeedController;
use App\Models\Check;
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
        try {
            $w3cUp = Http::timeout(5)
                ->head('https://validator.w3.org/feed')
                ->successful();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $w3cUp = false;
        }

        Cache::put('w3c-status', $w3cUp, 30);
    }

    if ($validatorDotOrgUp === null) {
        try {
            $validatorDotOrgUp = Http::timeout(5)
                ->head('https://www.feedvalidator.org')
                ->successful();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $validatorDotOrgUp = false;
        }
        
        Cache::put('validator.org-status', $validatorDotOrgUp, 30);
    }

    return view('status', [
        'lastCheck' => Check::query()->latest()->first(),
        'w3c' => $w3cUp,
        'validatorDotOrg' => $validatorDotOrgUp,
    ]);
});

Route::post('/add', [FeedController::class, 'create']);

Route::get('/feed/{id}', [FeedController::class, 'view']);
Route::get('/feed/{id}/confirm/{code}', [FeedController::class, 'confirm']);
Route::post('/feed/{id}/delete', [FeedController::class, 'delete']);

Route::view('/updates', 'updates');
