<?php

declare(strict_types=1);

namespace App\Extensions\AIImagePro\System\Http\Middleware;

use App\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

class RedirectToAIImageProLogin extends Authenticate
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login', ['redirect' => 'aiImagePro']);
    }
}
