<?php

namespace App\Console\Commands;

use App\Mail\FeedDeleted;
use App\Models\Feed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DeleteFeedAndNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-feed-and-notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a feed and notify the owner with a custom reason';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Step 1: Prompt for feed identifier (ID or URL)
        $identifier = $this->ask('Enter feed ID or URL');

        // Step 2: Find feed by ID or URL
        $feed = $this->findFeed($identifier);

        if (! $feed) {
            $this->error('Feed not found.');

            return;
        }

        // Step 3: Display feed information for confirmation
        $this->newLine();
        $this->line('Feed found:');
        $this->table(
            ['ID', 'URL', 'Email', 'Status', 'Confirmed'],
            [[$feed->id, $feed->url, $feed->email, $feed->status, $feed->confirmed ? 'Yes' : 'No']]
        );

        // Step 4: Prompt for deletion reason
        $reason = $this->ask('Enter deletion reason (will be sent to feed owner)');

        if (empty($reason)) {
            $this->error('Deletion reason is required.');

            return;
        }

        // Step 5: Final confirmation
        $this->newLine();
        if (! $this->confirm('Are you sure you want to delete this feed and notify the owner?', false)) {
            $this->info('Deletion cancelled.');

            return;
        }

        // Step 6: Send notification email
        $this->line('Sending notification email...');
        try {
            Mail::send(new FeedDeleted($feed, $reason));
            $this->info('Notification sent to '.$feed->email);
        } catch (\Exception $e) {
            $this->error('Failed to send notification: '.$e->getMessage());
            if (! $this->confirm('Continue with deletion anyway?', false)) {
                $this->info('Deletion cancelled.');

                return;
            }
        }

        // Step 7: Delete the feed (cascade will handle checks and connection_failures)
        $feedUrl = $feed->url;
        $feedEmail = $feed->email;

        $this->line('Deleting feed...');
        $feed->delete();

        $this->newLine();
        $this->info('Feed deleted successfully.');
        $this->line('URL: '.$feedUrl);
        $this->line('Owner: '.$feedEmail);
    }

    /**
     * Find feed by ID (UUID) or URL
     */
    private function findFeed(string $identifier): ?Feed
    {
        // Try to find by ID first (UUID format)
        if (strlen($identifier) === 36 && str_contains($identifier, '-')) {
            $feed = Feed::find($identifier);
            if ($feed) {
                return $feed;
            }
        }

        // Try to find by URL
        return Feed::where('url', $identifier)->first();
    }
}
