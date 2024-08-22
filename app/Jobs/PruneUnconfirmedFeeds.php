<?php

namespace App\Jobs;

use App\Models\Feed;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PruneUnconfirmedFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $abandonedFeeds = Feed::query()
            ->where(function ($query) {
                $cutoff = Carbon::now()->subDays(3);
                $query->where('last_checked', '<=', $cutoff)
                    ->orWhere('last_checked', null);
            })
            ->where('confirmed', '!=', 1);

        foreach ($abandonedFeeds->get() as $abandonedFeed) {
            Log::info('Deleting feed '.$abandonedFeed->id);
            $abandonedFeed->delete();
        }
    }
}
