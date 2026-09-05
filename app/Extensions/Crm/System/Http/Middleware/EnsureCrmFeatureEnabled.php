<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmFeatureEnabled
{
    /**
     * Abort with 404 when the admin has disabled the requested CRM feature.
     */
    public function handle(Request $request, Closure $next, string $feature = 'crm'): Response
    {
        $settingKey = match ($feature) {
            'assistant'     => 'crm_assistant_enabled',
            'presentations' => 'crm_presentations_enabled',
            default         => 'crm_enabled',
        };

        if (! (bool) setting($settingKey, '1')) {
            abort(404);
        }

        return $next($request);
    }
}
