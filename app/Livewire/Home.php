<?php

namespace App\Livewire;

use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class Home extends Component
{
    public ?string $url = null;
    public ?string $email = null;
    public ?Collection $errors = null;

    public function create()
    {
        $this->validate([
            'url' => 'required|url',
            'email' => 'required|email',
        ]);

        $this->errors = collect([]);

        try {
            $response = Http::get($this->url);
        } catch (ConnectionException $connectionException) {
            $this->errors->push('Couldn’t connect to that URL.');
            return null;
        }

        $contentType = $response->header('content-type');

        if (! Feed::isValidResponseType($contentType)) {
            $this->errors->push('That URL doesn’t return a JSON or RSS feed.');
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
