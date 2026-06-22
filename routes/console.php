<?php

use App\Jobs\CheckFeeds;
use App\Jobs\PruneUnconfirmedFeeds;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckFeeds)->everyFifteenMinutes();
Schedule::job(new PruneUnconfirmedFeeds)->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
