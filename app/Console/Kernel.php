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
        $schedule->job(new CheckFeeds())->everyFiveMinutes();
        $schedule->job(new PruneUnconfirmedFeeds())->daily();

        $schedule->command('backup:clean')->daily()->at('17:00');
        $schedule->command('backup:run')->daily()->at('17:30');
        $schedule->command('backup:monitor')->weekly()->at('18:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
