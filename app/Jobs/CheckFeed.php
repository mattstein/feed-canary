<?php

namespace App\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

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
