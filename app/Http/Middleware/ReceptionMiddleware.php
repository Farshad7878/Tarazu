<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReceptionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isReception()) {
            return response()->json(['error' => 'دسترسی غیر مجاز'], 403);
        }

        return $next($request);
    }
}