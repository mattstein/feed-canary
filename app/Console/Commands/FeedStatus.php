<?php

namespace App\Console\Commands;

use App\Models\Feed;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FeedStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:status
                            {--failing : Show only feeds with active connection failures}
                            {--all : Show all feeds including healthy ones}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show feed status and connection failure information';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Feed::query()->where('confirmed', true);

        if ($this->option('failing')) {
            // Show only feeds with active connection failures
            $feeds = $query->get()->filter(fn (Feed $feed) => $feed->hasFailingConnection());
            $title = 'Feeds with Active Connection Failures';
        } elseif ($this->option('all')) {
            // Show all confirmed feeds
            $feeds = $query->get();
            $title = 'All Confirmed Feeds';
        } else {
            // Default: show feeds that are either failing or have recent connection issues
            $feeds = $query->get()->filter(function (Feed $feed) {
                return $feed->status === Feed::STATUS_FAILING || $feed->hasFailingConnection();
            });
            $title = 'Feeds with Issues';
        }

        if ($feeds->isEmpty()) {
            $this->info('No feeds found matching the criteria.');

            return Command::SUCCESS;
        }

        $this->line('');
        $this->line("<fg=cyan;options=bold>$title</>");
        $this->line('');

        $threshold = config('app.connection_failure_threshold', 86400);
        $rows = [];

        foreach ($feeds as $feed) {
            $latestFailure = $feed->latestConnectionFailure();
            $latestCheck = $feed->latestCheck();

            $status = $feed->status;
            $statusColor = $status === Feed::STATUS_HEALTHY ? 'green' : 'red';

            // Calculate time information
            $timeInfo = '-';
            $failureCount = 0;

            if ($latestFailure) {
                $failureCount = $feed->connectionFailures()
                    ->where('created_at', '>=', Carbon::now()->subDay())
                    ->count();

                if ($latestCheck) {
                    $timeSinceLastSuccess = $latestFailure->created_at->diffInSeconds($latestCheck->created_at);

                    if ($timeSinceLastSuccess <= $threshold) {
                        $remaining = $threshold - $timeSinceLastSuccess;
                        $hours = floor($remaining / 3600);
                        $timeInfo = sprintf('%dh until alert', $hours);
                    } else {
                        $timeInfo = 'Alert threshold exceeded';
                    }
                } else {
                    // No successful check yet
                    $oldestFailure = $feed->connectionFailures()->oldest()->first();
                    if ($oldestFailure) {
                        $timeSinceFirst = Carbon::now()->diffInSeconds($oldestFailure->created_at);
                        if ($timeSinceFirst <= $threshold) {
                            $remaining = $threshold - $timeSinceFirst;
                            $hours = floor($remaining / 3600);
                            $timeInfo = sprintf('%dh until alert', $hours);
                        } else {
                            $timeInfo = 'Alert threshold exceeded';
                        }
                    }
                }
            }

            $lastChecked = $feed->last_checked
                ? $feed->last_checked->diffForHumans()
                : 'never';

            $rows[] = [
                $feed->id,
                strlen($feed->url) > 50 ? substr($feed->url, 0, 47).'...' : $feed->url,
                "<fg=$statusColor>$status</>",
                $failureCount > 0 ? $failureCount : '-',
                $timeInfo,
                $lastChecked,
            ];
        }

        $this->table(
            ['ID', 'URL', 'Status', 'Failures (24h)', 'Time to Alert', 'Last Checked'],
            $rows
        );

        $this->line('');
        $this->line('Threshold: '.($threshold / 3600).' hours');
        $this->line('');

        return Command::SUCCESS;
    }
}
