<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLivewireUpdates
{
    /**
     * Rate-limit the Livewire update endpoint to deter automated
     * deserialization attacks without affecting real users.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isLivewireUpdate($request)) {
            $executed = RateLimiter::attempt(
                key: 'livewire-update:'.$request->ip(),
                maxAttempts: 120,         // generous: 2 req/s per IP
                decaySeconds: 60,
                callback: static fn () => null,
            );

            if (! $executed) {
                abort(429, 'Too many Livewire requests. Please slow down.');
            }
        }

        return $next($request);
    }

    private function isLivewireUpdate(Request $request): bool
    {
        return $request->is('livewire/update')
            && $request->isMethod('POST')
            && $request->hasHeader('X-Livewire');
    }
}
