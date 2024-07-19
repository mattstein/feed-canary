<?php

namespace App\Console\Commands;

use App\Models\Feed;
use Illuminate\Console\Command;

class CheckFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-feed {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check a single feed';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $feed = Feed::find($this->argument('id'));

        if ($feed) {
            $this->line('Checking ' . $feed->url . '...');
            $result = $feed->check();
            $this->line($result ? '✓ Feed is valid' : '✗ Feed is not valid');
        } else {
            $this->error('Invalid feed ID.');
        }
    }
}
