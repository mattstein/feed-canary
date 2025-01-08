<?php

namespace App\Livewire;

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

    #[Title('Manage Feed')]
    public function render()
    {
        $this->refreshCheckAvailability();

        return view('livewire.manage-feed');
    }
}
