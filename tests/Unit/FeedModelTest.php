<?php

use App\Models\Feed;

test('has attributes', function () {
    $feed = new Feed([
        'url' => 'https://example.foo/feed.rss',
        'email' => 'hi@example.foo',
    ]);

    expect($feed->url)->toBe('https://example.foo/feed.rss')
        ->and($feed->email)->toBe('hi@example.foo');
});
