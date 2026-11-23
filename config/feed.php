<?php

return [
    /*
    | How long (in seconds) an unchanged feed body may reuse its previous
    | validation verdict before it is validated again.
    */
    'revalidate_after' => (int) env('FEED_REVALIDATE_AFTER', 86400),

    /*
    | The W3C validator sits behind a Cloudflare challenge at times. When
    | disabled, or when it gives no verdict, we fall through to RSS Board.
    */
    'w3c_enabled' => (bool) env('FEED_W3C_ENABLED', true),
    'w3c_url' => env('FEED_W3C_URL', 'https://validator.w3.org/feed/check.cgi'),
    'rssboard_url' => env('FEED_RSSBOARD_URL', 'https://www.rssboard.org/rss-validator/check.cgi'),
    'validator_timeout' => (int) env('FEED_VALIDATOR_TIMEOUT', 10),
];
