<?php

return [
    /*
    | How long (in seconds) an unchanged feed body may reuse its previous
    | validation verdict before it is validated again.
    */
    'revalidate_after' => (int) env('FEED_REVALIDATE_AFTER', 86400),

    /*
    | Self-hosted W3C feed validator (check.cgi), e.g. http://validator/check.cgi
    | in the compose network. The fetched body is posted to it. `null` disables
    | it. When it fails, the public validators below are used and the failure
    | is reported (to Sentry/Bugsink), at most once per `alert_throttle` seconds.
    */
    'local_validator_url' => env('FEED_LOCAL_VALIDATOR_URL'),
    'alert_throttle' => (int) env('FEED_ALERT_THROTTLE', 900),

    /*
    | The public W3C validator sits behind a Cloudflare challenge at times. When
    | disabled, or when it gives no verdict, we fall through to RSS Board.
    */
    'w3c_enabled' => (bool) env('FEED_W3C_ENABLED', true),
    'w3c_url' => env('FEED_W3C_URL', 'https://validator.w3.org/feed/check.cgi'),
    'rssboard_url' => env('FEED_RSSBOARD_URL', 'https://www.rssboard.org/rss-validator/check.cgi'),
    'validator_timeout' => (int) env('FEED_VALIDATOR_TIMEOUT', 10),
];
