<?php

namespace App\Livewire;

use App\Models\Check;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Status extends Component
{
    public ?string $w3cStatus = null;
    public ?string $validatorDotOrgStatus = null;

    public function updateW3cStatus(): void
    {
        $this->w3cStatus = Cache::get('w3c-status');

        if ($this->w3cStatus === null) {
            try {
                $this->w3cStatus = Http::timeout(5)
                    ->head('https://validator.w3.org/feed')
                    ->successful() ? 'up' : 'down';
            } catch (ConnectionException $e) {
                $this->w3cStatus = 'down';
            }

            Cache::put('w3c-status', $this->w3cStatus, 30);
        }
    }

    public function updateValidatorDotOrgStatus(): void
    {
        $this->validatorDotOrgStatus = Cache::get('validator.org-status');

        if ($this->validatorDotOrgStatus === null) {
            try {
                $this->validatorDotOrgStatus = Http::timeout(5)
                    ->head('https://www.feedvalidator.org')
                    ->successful() ? 'up' : 'down';
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $this->validatorDotOrgStatus = 'down';
            }

            Cache::put('validator.org-status', $this->validatorDotOrgStatus, 30);
        }
    }

    public function getStatusEmoji($status): string
    {
        if ($status === null) {
            return '⏳';
        }

        if ($status === 'up') {
            return '✅';
        }

        if ($status === 'down') {
            return '‼️';
        }

        return '🤷';
    }

    public function getStatusDescription($status): string
    {
        if ($status === null) {
            return '…';
        }

        if ($status === 'up') {
            return 'seems fine.';
        }

        if ($status === 'down') {
            return 'may be down!';
        }

        return '';
    }

    #[Title('Status')]
    public function render()
    {
        return view('livewire.status', [
            'lastCheck' => Check::query()->latest()->first()
        ]);
    }
}
