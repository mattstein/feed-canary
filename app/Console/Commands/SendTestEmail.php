<?php

namespace App\Console\Commands;

use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-test-email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test confirmation email';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $feed = new Feed([
            'url' => 'https://foo.bar/feed.json',
            'email' => $this->argument('email'),
            'type' => 'application/json',
            'confirmation_code' => Str::random(),
        ]);

        Mail::send(new ConfirmFeed($feed));
    }
}
