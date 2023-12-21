<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Mail\ConfirmFeed;

class FeedController extends Controller
{
    public function create(Request $request)
    {
        $feedUrl = $request->input('url');
        $response = Http::get($feedUrl);

        $feed = Feed::create([
            'url' => $feedUrl,
            'email' => $request->input('email'),
            'type' => $response->header('content-type'),
            'confirmation_code' => Str::random(),
        ]);

        $feed->check();

        // TODO: stop if the feed check fails or it wasn’t unique to start with

        Mail::send(new ConfirmFeed($feed));

        return redirect($feed->manageUrl());
//        return view('manage-feed', [
//            'feed' => $feed,
//        ]);
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
