<?php

namespace App\Jobs;

use App\Models\Feed;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
class CheckFeeds implements ShouldQueue, ShouldBeUnique
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
        // Get confirmed feeds that were last checked more than five minutes ago (or haven’t been checked yet)
        $feeds = Feed::query()
            ->where(function ($query) {
                $cutoff = Carbon::now()->subMinutes(5);
                $query->where('last_checked', '<=', $cutoff)
                    ->orWhere('last_checked', null);
            })
            ->where('confirmed', '=', 1);

        Log::debug("Queuing " . $feeds->count() . " feeds.");

        foreach ($feeds->get() as $feed) {
            CheckFeed::dispatch($feed->id);
        }
    }
}
