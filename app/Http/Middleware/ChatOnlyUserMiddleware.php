<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatOnlyUserMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $response;
        }

        $pathSegments = explode('/', trim($request->path(), '/'));
        $isDashboardRoute = $request->routeIs('dashboard.*')
            || in_array('dashboard', $pathSegments, true);

        if (! $request->user()?->isUser() || ! $isDashboardRoute) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        if (str_contains($contentType, 'text/html')) {
            return redirect()->route('chat.pro');
        }

        return $response;
    }
}
