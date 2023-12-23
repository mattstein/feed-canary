<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use App\Mail\ConfirmFeed;

class FeedController extends Controller
{
    public function create(Request $request)
    {
        $feedUrl = $request->input('url');
        $response = Http::get($feedUrl);
        $contentType = $response->header('content-type');

        if ( ! Feed::isValidResponseType($contentType)) {
            $request->flash();
            return Redirect::back()
                ->withErrors([
                    'url' => 'That URL doesn’t return a JSON or RSS feed.'
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
        $feed = Feed::query()
            ->find($id);

        if ($feed && $feed->confirmation_code === $code) {
            $feed->update([
                'confirmed' => true,
                'confirmation_code' => null,
            ]);
        }

        return redirect($feed->manageUrl());

        // TODO: handle failure
    }

    public function delete(string $id)
    {
        $feed = Feed::query()
            ->find($id);

        $feed->delete();

        return redirect('/')
            ->with([
                'message' => 'Feed deleted.'
            ]);
    }
}
