<?php

namespace App\Console\Commands;

use App\Models\Check;
use App\Models\Feed;
use Illuminate\Console\Command;

class RevalidateFeeds extends Command
{
    protected $signature = 'feed:revalidate
                            {--stale : Required. Expire cached verdicts so the next scheduled check revalidates}';

    protected $description = 'Expire pinned verdicts on failing or unverified feeds so they are revalidated on the next check';

    public function handle(): int
    {
        if (! $this->option('stale')) {
            $this->error('Pass --stale to confirm expiring cached verdicts.');

            return Command::FAILURE;
        }

        $expired = 0;

        // Only the cached verdicts are expired. Feed status is left alone and
        // nothing is flipped to healthy; the next scheduled run settles it.
        Feed::query()
            ->where('confirmed', true)
            ->each(function (Feed $feed) use (&$expired) {
                $latest = $feed->latestCheck();

                if (! $latest || $latest->validated_at === null) {
                    return;
                }

                if ($feed->status === Feed::STATUS_FAILING || $latest->is_valid !== true) {
                    $expired += Check::whereKey($latest->getKey())->update(['validated_at' => null]);
                }
            });

        $this->line(number_format($expired).' cached verdicts expired.');

        return Command::SUCCESS;
    }
}
