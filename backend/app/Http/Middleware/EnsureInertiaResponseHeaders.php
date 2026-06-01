<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInertiaResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $isInertiaRequest = $request->headers->get('X-Inertia') === 'true';
        $isInertiaResponse = $response->headers->has('X-Inertia');

        if ($isInertiaRequest || $isInertiaResponse) {
            $this->appendVaryHeader($response, 'X-Inertia');

            // Prevent shared caches/CDNs from serving Inertia JSON as full HTML pages.
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function appendVaryHeader(Response $response, string $value): void
    {
        $current = (string) $response->headers->get('Vary', '');
        $parts = array_filter(array_map('trim', explode(',', $current)));

        foreach ($parts as $part) {
            if (strcasecmp($part, $value) === 0) {
                return;
            }
        }

        $parts[] = $value;
        $response->headers->set('Vary', implode(', ', $parts));
    }
}

