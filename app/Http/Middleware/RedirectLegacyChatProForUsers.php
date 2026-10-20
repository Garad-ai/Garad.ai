<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyChatProForUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $next($request);
        }

        $slug = $request->route('slug');

        $parameters = [];
        if (is_string($slug) && $slug !== '') {
            $parameters['slug'] = $slug;
        }

        return redirect()->route('chat.pro', $parameters);
    }
}
