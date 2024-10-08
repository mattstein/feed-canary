<?php

namespace App\Console;

use App\Jobs\CheckFeeds;
use App\Jobs\PruneUnconfirmedFeeds;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new CheckFeeds)->everyFifteenMinutes();
        $schedule->job(new PruneUnconfirmedFeeds)->daily();

        $schedule->command('horizon:snapshot')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
