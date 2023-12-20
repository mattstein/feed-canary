<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Http;

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
        ]);

        $feed->check();

        // TODO: make sure URL is unique
        // TODO: email opt in

        return redirect($feed->manageUrl());
//        return view('manage-feed', [
//            'feed' => $feed,
//        ]);
    }

    public function confirm()
    {

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
