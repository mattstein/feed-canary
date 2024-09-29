<?php

namespace App\Http\Controllers;

use App\Models\Feed;

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
