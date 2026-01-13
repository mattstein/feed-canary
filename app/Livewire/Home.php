<?php

namespace App\Livewire;

use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class Home extends Component
{
    public string|array|null $url = null;

    public string|array|null $email = null;

    public array $feedErrors = [];

    public function hydrate()
    {
        if (is_array($this->url)) {
            $this->url = $this->url[0] ?? null;
        }

        if (is_array($this->email)) {
            $this->email = $this->email[0] ?? null;
        }
    }

    public function updatedUrl($value)
    {
        $this->url = is_array($value) ? ($value[0] ?? null) : $value;
    }

    public function updatedEmail($value)
    {
        $this->email = is_array($value) ? ($value[0] ?? null) : $value;
    }

    public function create()
    {
        $this->validate([
            'url' => 'required|url',
            'email' => 'required|email',
        ]);

        $this->feedErrors = [];

        try {
            $response = Http::get($this->url);
        } catch (\Exception) {
            $this->feedErrors[] = 'Couldn’t connect to that URL.';

            return null;
        }

        $contentType = $response->header('content-type');

        if (! Feed::isValidResponseType($contentType)) {
            $this->feedErrors[] = 'That URL doesn’t return a JSON or RSS feed.';

            return null;
        }

        $feed = Feed::create([
            'url' => $this->url,
            'email' => $this->email,
            'type' => $contentType,
            'confirmation_code' => Str::random(),
        ]);

        $feed->check();

        Mail::send(new ConfirmFeed($feed));

        return redirect($feed->manageUrl());
    }

    public function render()
    {
        return view('livewire.home');
    }
}
