<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Feed;

class CheckFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public Feed $feed;

    /**
     * Create a new job instance.
     */
    public function __construct(Feed $feed)
    {
        $this->feed = $feed;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->feed->check();
    }
}
