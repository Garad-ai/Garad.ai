<?php

namespace App\Extensions\SocialMedia\System\Services\Publisher;

use App\Extensions\SocialMedia\System\Helpers\Linkedin;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;
use Illuminate\Support\Facades\Http;

class LinkedinService extends BasePublisherService
{
    public function handle()
    {
        $images = $this->post->images ?? [];
        $media = $this->post->image;

        $message = $this->post->content;

        $linkedin = new Linkedin;
        $linkedin->setToken($this->accessToken);
        $author = data_get($this->credentials, 'author_urn', $this->platformId);
        $accountType = data_get($this->credentials, 'account_type')
            ?? (str_starts_with((string) $author, 'urn:li:organization:') ? 'organization' : 'member');
        $scopes = (array) data_get($this->credentials, 'scopes', []);
        if ($scopes === []) {
            $scopes = preg_split('/\s+/', (string) data_get($this->credentials, 'scope', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $requiredScope = $accountType === 'organization'
            ? 'w_organization_social'
            : 'w_member_social';
        $legacyMemberWithUnknownScopes = $accountType === 'member' && $scopes === [];
        if ((! in_array($requiredScope, $scopes, true) && ! $legacyMemberWithUnknownScopes)
            || ($accountType === 'organization' && ! data_get($this->credentials, 'can_publish', false))) {
            $message = $accountType === 'organization'
                ? 'Page connected, but this LinkedIn account/app does not have permission to publish to this organization. Confirm the member role and LinkedIn approval for w_organization_social.'
                : 'This LinkedIn connection does not have the w_member_social permission required to publish from a personal profile. Reconnect after confirming the LinkedIn app permission.';

            return Http::response(['message' => $message], 403);
        }

        $mediaFiles = count($images) > 0
            ? array_map(fn ($img) => public_path($img), $images)
            : ($media ? [public_path($media)] : []);

        return match (count($mediaFiles) > 0) {
            true    => $linkedin->publishImage($author, $mediaFiles, $message),
            default => $linkedin->publishText($author, $message),
        };
    }
}
