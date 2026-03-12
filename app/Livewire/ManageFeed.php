<?php

namespace App\Livewire;

use App\Models\Check;
use App\Models\ConnectionFailure;
use App\Models\Feed;
use Livewire\Attributes\Title;
use Livewire\Component;

class ManageFeed extends Component
{
    public Feed $feed;

    public bool $canCheck = false;

    public function refreshCheckAvailability(): void
    {
        if (! $this->feed->confirmed) {
            $this->canCheck = false;

            return;
        }

        if (! $this->feed->latestCheck()) {
            $this->canCheck = false;

            return;
        }

        $this->canCheck = $this->feed->latestCheck()
            ->updated_at
            ->diffInSeconds(now()) > 30;
    }

    public function check(): void
    {
        if (! $this->canCheck) {
            return;
        }

        $this->feed->check();
        $this->refreshCheckAvailability();
    }

    public function delete()
    {
        $this->feed->delete();

        return redirect('/')
            ->with('message', 'Your feed monitor has been deleted.');
    }

    /**
     * Get recent check history (up to 10 items) combining checks and connection failures.
     * Returns an array of items with 'type', 'timestamp', 'is_valid', and 'status' keys.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentCheckHistory(): array
    {
        // Get up to 15 most recent checks (to ensure we have enough after filtering)
        $checks = $this->feed->checks()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Check $check) => [
                'type' => 'check',
                'timestamp' => $check->created_at,
                'is_valid' => $check->is_valid,
                'status' => $check->status,
            ]);

        // Get up to 15 most recent connection failures
        $failures = $this->feed->connectionFailures()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (ConnectionFailure $failure) => [
                'type' => 'connection_failure',
                'timestamp' => $failure->created_at,
                'is_valid' => false,
                'status' => 0,
                'exceeded_threshold' => $failure->exceedsThreshold(),
            ]);

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $merged */
        $merged = collect($checks)
            ->concat($failures)
            ->sortByDesc('timestamp');

        // Remove duplicate connection failures that have a corresponding Check record
        // (when a connection failure exceeds threshold, both records are created)
        $deduplicated = $merged->filter(function (array $item) use ($merged) {
            if ($item['type'] !== 'connection_failure') {
                return true; // Keep all checks
            }

            // Check if there's a corresponding Check with status 0 within 5 seconds
            return ! $merged->contains(function (array $other) use ($item) {
                return $other['type'] === 'check'
                    && $other['status'] === 0
                    && abs($other['timestamp']->diffInSeconds($item['timestamp'])) <= 5;
            });
        });

        return $deduplicated
            ->take(10)
            ->values()
            ->all();
    }

    #[Title('Manage Feed')]
    public function render()
    {
        $this->refreshCheckAvailability();

        return view('livewire.manage-feed', [
            'recentHistory' => $this->getRecentCheckHistory(),
        ]);
    }
}
