<?php

namespace App\Console\Commands;

use App\Models\Check;
use App\Models\Feed;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PruneChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-checks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune checks older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoff = Carbon::now()->subDays(30);
        $rowsDeleted = Check::query()
            ->where('created_at', '<=', $cutoff)
            ->delete();

        $this->line($rowsDeleted . ' rows deleted.');
    }
}
