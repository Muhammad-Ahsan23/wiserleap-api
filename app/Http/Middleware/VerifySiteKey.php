<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server endpoints (the website's contact form).
 *
 * Only the Next.js server knows CONTACT_SITE_KEY, so browsers and bots can't
 * call the endpoint directly. Because the request comes from the website's
 * server, the visitor's IP arrives in X-Visitor-Ip; it is trusted only
 * together with a valid key and is what the rate limits use.
 */
class VerifySiteKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strlen((string) config('contact.site_key')) < 32) {
            return response()->json(['message' => 'The contact form is not available right now.'], 503);
        }

        if (! self::validKey($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }

    public static function validKey(Request $request): bool
    {
        $key = (string) config('contact.site_key');

        return strlen($key) >= 32 && hash_equals($key, (string) $request->header('X-Site-Key'));
    }

    /**
     * The visitor's IP: X-Visitor-Ip only with a valid key, otherwise the
     * connecting IP. Used by the rate limiter too (it can run before this middleware).
     */
    public static function visitorIp(Request $request): string
    {
        $forwarded = (string) $request->header('X-Visitor-Ip');

        return self::validKey($request) && filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : (string) $request->ip();
    }
}
