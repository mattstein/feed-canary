<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use App\Models\Feed;

class CheckFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $cutoff = Carbon::now()->subMinutes(10);

        $feeds = Feed::query()
            ->where('last_checked', '<=', $cutoff)
            ->orWhere('last_checked', null);

        echo "Queuing " . $feeds->count() . " feeds.\n";

        foreach ($feeds->get() as $feed) {
            CheckFeed::dispatch($feed);
        }
    }
}
