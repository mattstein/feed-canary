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

    protected ?string $feedId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(string $feedId)
    {
        $this->feedId = $feedId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $feed = Feed::query()
            ->where(['id' => $this->feedId])
            ->first();

        if ($feed) {
            $feed->check();
        }
    }
}
