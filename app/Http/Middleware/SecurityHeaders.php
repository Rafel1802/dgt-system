<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            
            if (app()->environment('production')) {
                $host = $request->getHost();
                if ($host !== 'localhost' && $host !== '127.0.0.1' && !str_starts_with($host, '192.168.')) {
                    $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
                }
            }
        }

        return $response;
    }
}
