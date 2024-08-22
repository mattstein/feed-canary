<?php

namespace App\Console\Commands;

use App\Models\Feed;
use Illuminate\Console\Command;

class AuditFeeds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:audit-feeds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Conduct feed housekeeping';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $allFeeds = Feed::all();

        $duplicateFeeds = $allFeeds
            ->filter(function ($feed) {
                return Feed::whereUrl($feed->url)->count() > 1;
            })->map(function ($item) {
                return $item->only('id', 'url', 'email');
            });

        $recurringOwners = $allFeeds
            ->filter(function ($feed) {
                return Feed::whereEmail($feed->email)->count() > 1;
            })->map(function ($item) {
                return $item->only('email', 'url');
            });

        if ($duplicateFeeds->isNotEmpty()) {
            $this->newLine();
            $this->line('Duplicate feeds found:');
            $this->table(
                ['ID', 'URL', 'Email'],
                $duplicateFeeds->sortBy('url')->toArray()
            );
        }

        if ($duplicateFeeds->isNotEmpty()) {
            $this->newLine();
            $this->line('Recurring owners found:');
            $this->table(
                ['Email', 'URL'],
                $recurringOwners->sortBy('email')->toArray()
            );
        }

        if ($duplicateFeeds->isEmpty() && $recurringOwners->isEmpty()) {
            $this->newLine();
            $this->info('No duplicate feeds or recurring owners found.');
        }
    }
}
