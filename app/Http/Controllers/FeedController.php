<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmFeed;
use App\Models\Feed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'email' => 'required|email',
        ]);

        $feedUrl = $request->input('url');

        try {
            $response = Http::get($feedUrl);
        } catch (ConnectionException $connectionException) {
            $request->flash();

            return Redirect::back()
                ->withErrors([
                    'url' => 'Couldn’t connect to that URL.',
                ]);
        }

        $contentType = $response->header('content-type');

        if (! Feed::isValidResponseType($contentType)) {
            $request->flash();

            return Redirect::back()
                ->withErrors([
                    'url' => 'That URL doesn’t return a JSON or RSS feed.',
                ]);
        }

        $feed = Feed::create([
            'url' => $feedUrl,
            'email' => $request->input('email'),
            'type' => $contentType,
            'confirmation_code' => Str::random(),
        ]);

        $feed->check();

        Mail::send(new ConfirmFeed($feed));

        return redirect($feed->manageUrl());
    }

    public function confirm(string $id, string $code)
    {
        $feed = Feed::where([
            'id' => $id,
            'confirmation_code' => $code,
        ])->first();

        if (! $feed) {
            abort(404);
        }

        $feed->update([
            'confirmed' => true,
            'confirmation_code' => null,
        ]);

        return redirect($feed->manageUrl());
    }
}
