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
