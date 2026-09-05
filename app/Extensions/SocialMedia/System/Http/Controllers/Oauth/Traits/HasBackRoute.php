<?php

namespace App\Extensions\SocialMedia\System\Http\Controllers\Oauth\Traits;

trait HasBackRoute
{
    public function setBackCacheRoute(): void
    {
        if (request()->has('extension') && request('extension') === 'social-media-pro') {
            cache()->remember('redirect_back_route', 60, function () {
                return 'dashboard.user.social-media.agent.accounts';
            });
        }
    }

    public function getBackCacheRoute(): string
    {
        if (cache()->has('redirect_back_route')) {
            cache()->forget('redirect_back_route');

            return 'dashboard.user.social-media.agent.accounts';
        }

        return 'dashboard.user.social-media.platforms';
    }
}
