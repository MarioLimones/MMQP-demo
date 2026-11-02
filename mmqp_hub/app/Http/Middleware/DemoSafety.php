<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoSafety
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('push/*')) {
            return response()->json(['message' => 'Los envíos push están desactivados en la demo.'], 403);
        }

        $response = $next($request);
        $response->headers->set('X-MMQP-Demo', 'synthetic-data');
        $response->headers->set('Referrer-Policy', 'same-origin');

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
