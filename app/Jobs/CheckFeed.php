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

    protected ?Feed $feed = null;

    /**
     * Create a new job instance.
     */
    public function __construct(string $feedId)
    {
        $this->feed = Feed::query()
            ->where(['id' => $feedId])
            ->first();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->feed) {
            $this->feed->check();
        }
    }
}
