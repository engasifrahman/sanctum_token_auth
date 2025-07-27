<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to get from header or generate a new one
        $requestId = $request->header('X-Request-Id') ?? (string) Str::uuid();

        // Share globally for logs or other parts of the app
        $request->headers->set('X-Request-Id', $requestId); // ① For incoming request

        $response = $next($request);

        // Add it to response headers (optional, good for clients)
        $response->headers->set('X-Request-Id', $requestId); // ② For outgoing response

        return $response;
    }
}
