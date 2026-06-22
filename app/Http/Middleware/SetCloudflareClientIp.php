<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCloudflareClientIp
{
    /**
     * Make request()->ip() / getClientIp() return the real client IP app-wide.
     *
     * When a request arrives via Cloudflare, the real client IP is in the
     * CF-Connecting-IP header — Coolify's Traefik overwrites X-Forwarded-For with
     * Cloudflare's edge IP, so the standard resolution sees Cloudflare, not the
     * client. Copying CF-Connecting-IP into X-Forwarded-For (with proxies trusted)
     * restores the real client everywhere. Requests that don't come through
     * Cloudflare (e.g. over the tailnet) have no CF header and are left untouched,
     * so their genuine source IP is preserved.
     *
     * SECURITY: this trusts a client-supplied header. It is only safe because the
     * origin is reachable solely through Cloudflare and the tailnet — the host
     * firewall MUST restrict ingress to Cloudflare's IP ranges (and Tailscale).
     * Without that, anyone hitting the origin directly can forge CF-Connecting-IP.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($cfIp = $request->headers->get('CF-Connecting-IP')) {
            $request->headers->set('X-Forwarded-For', $cfIp);
        }

        return $next($request);
    }
}
