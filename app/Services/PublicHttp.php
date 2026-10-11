<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;

/**
 * HTTP client for user-supplied URLs that refuses to talk to anything but
 * the public internet.
 *
 * The check runs inside curl after it connects and before it sends the
 * request, against the IP it actually connected to. That covers every
 * redirect hop (Guzzle follows redirects with a fresh curl request) and
 * DNS rebinding, neither of which validating the URL up front can catch.
 * A blocked request surfaces as a ConnectionException.
 */
class PublicHttp
{
    public static function client(): PendingRequest
    {
        // Fail closed rather than silently sending unguarded requests.
        if (! defined('CURLOPT_PREREQFUNCTION')) {
            throw new LogicException('PublicHttp requires PHP 8.4+ built against libcurl 7.80+.');
        }

        return Http::withOptions([
            // Guzzle's default, stated explicitly: no file://, gopher://, etc.
            'protocols' => ['http', 'https'],
            'curl' => [
                CURLOPT_PREREQFUNCTION => static fn ($handle, string $primaryIp): int => self::isPublicIp($primaryIp)
                    ? CURL_PREREQFUNC_OK
                    : CURL_PREREQFUNC_ABORT,
            ],
        ]);
    }

    /**
     * True only for globally routable addresses: rejects loopback, private,
     * link-local (incl. cloud metadata), CGNAT/Tailscale, reserved, and
     * IPv4-mapped forms of those.
     */
    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        // FILTER_FLAG_GLOBAL_RANGE treats the NAT64 prefixes (64:ff9b::/96 and
        // 64:ff9b:1::/48) as global, but they embed an IPv4 address that may
        // be internal.
        $hex = bin2hex((string) inet_pton($ip));

        return ! str_starts_with($hex, '0064ff9b0000000000000000')
            && ! str_starts_with($hex, '0064ff9b0001');
    }
}
