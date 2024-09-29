<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

class Updates extends Component
{
    #[Title('Updates')]
    public function render()
    {
        return view('livewire.updates');
    }
}
