<?php

namespace App\Extensions\SocialMedia\System\Services\Publisher\Contracts;

use App\Extensions\SocialMedia\System\Enums\LogStatusEnum;
use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPlatform;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Models\SocialMediaSharedLog;
use Illuminate\Http\Client\Response;
use JetBrains\PhpStorm\NoReturn;

class BasePublisherService
{
    public SocialMediaPost $post;

    public SocialMediaPlatform $platform;

    public array $credentials;

    public ?string $accessToken = null;

    public ?string $platformId = null;

    private array|string|null|Response $publishResponse = null;

    #[NoReturn]
    public function publish(): void
    {
        $this->credentials = $this->platform->credentials;

        $accessToken = data_get($this->credentials, 'access_token');

        $platformId = data_get($this->credentials, 'platform_id');

        $this->setAccessToken($accessToken);

        $this->setPlatformId($platformId);

        if ($this->check()) {
            return;
        }

        if ($response = $this->handle()) {
            $this->setPublishResponse($response);

            $this->finish();
        }
    }

    public function hande()
    {
        return false;
    }

    public function finish(): void
    {
        $isPublished = false;
        $publishId = null;
        $error = null;

        $response = $this->publishResponse;

        if ($response instanceof Response) {
            if ($response->successful()) {
                $publishId = match ($this->platform->platform) {
                    'linkedin' => $response->header('x-restli-id'),
                    'tiktok'   => $response->json('data.id'),
                    'x'        => $response->json('data.id'),
                    default    => $response->json('id'),
                };

                $this->post->update([
                    'post_id'   => $publishId,
                    'status'    => StatusEnum::published->value,
                    'posted_at' => now(),
                ]);

                $this->post->agentPostPublished($publishId);

                SocialMediaSharedLog::query()->create([
                    'social_media_post_id' => $this->post->id,
                    'response'             => [
                        'post_id'          => $publishId,
                        'published_at'     => now(),
                        'scheduled_at'     => null,
                        'status'           => 'shared',
                        'message'          => 'published successfully',
                    ],
                    'status'              => LogStatusEnum::success,
                    'created_at'          => now(),
                ]);

                $isPublished = true;
            } else {
                $error = match ($this->platform->platform) {
                    'facebook'  => $response->json('error.message'),
                    'instagram' => $response->json('error.error_user_msg'),
                    'x'         => $response->json('title'),
                    'tiktok'    => $response->json('error'),
                    default     => $response->json(),
                };
            }
        } elseif (is_array($response)) {
            $publishId = data_get($response, 'data.id');
            if ($publishId) {
                $this->post->update([
                    'post_id'   => $publishId,
                    'status'    => StatusEnum::published->value,
                    'posted_at' => now(),
                ]);

                $this->post->agentPostPublished($publishId);

                SocialMediaSharedLog::query()->create([
                    'social_media_post_id' => $this->post->id,
                    'response'             => [
                        'post_id'          => $publishId,
                        'published_at'     => now(),
                        'scheduled_at'     => null,
                        'status'           => 'shared',
                        'message'          => 'published successfully',
                    ],
                    'status'              => LogStatusEnum::success,
                    'created_at'          => now(),
                ]);

                $isPublished = true;
            } else {
                $error = $response['title'] ?? 'Something went wrong, please try again.';
            }
        } elseif (is_string($response)) {
            $error = $response;
        }

        if ($error || ! $isPublished) {
            $this->post->update([
                'status'       => StatusEnum::failed->value,
            ]);

            // Convert error to string if it's an array
            $errorMessage = $error ?: 'Unknown error occurred.';
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }

            $this->post->agentPostFailed($errorMessage);

            SocialMediaSharedLog::query()->create([
                'social_media_post_id' => $this->post->id,
                'response'             => [
                    'message' => $errorMessage,
                ],
                'status'     => LogStatusEnum::failed,
                'created_at' => now(),
            ]);
        }

        $this->replicate();
    }

    public function replicate(): void
    {
        if ($this->post->is_repeated && ! $this->post->has_replicate) {

            $replicate = $this->post->replicate();

            $replicate->post_id = null;

            $replicate->posted_at = null;

            $replicate->scheduled_at = match ($replicate->repeat_period) {
                'day'   => now()->addDay(),
                'week'  => now()->addWeek(),
                'month' => now()->addMonth(),
            };

            $replicate->status = StatusEnum::scheduled;

            $replicate->save();
        }
    }

    public function setPublishResponse($response): self
    {
        $this->publishResponse = $response;

        return $this;
    }

    public function check(): bool
    {
        if ($this->post->status !== StatusEnum::scheduled) {

            SocialMediaSharedLog::query()->create([
                'social_media_post_id' => $this->post->id,
                'response'             => [
                    'message' => 'Post dont scheduled.',
                ],
                'status'     => LogStatusEnum::failed,
                'created_at' => now(),
            ]);

            return true;
        }

        if (! $this->platform->isConnected()) {

            $this->post->update([
                'status' => StatusEnum::failed,
            ]);

            SocialMediaSharedLog::query()->create([
                'social_media_post_id' => $this->post->id,
                'response'             => [
                    'message' => 'Platform is not connected.',
                ],
                'status'     => LogStatusEnum::failed,
                'created_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    public function setPost(SocialMediaPost $post): self
    {
        $this->post = $post;

        return $this;
    }

    public function setPlatform(SocialMediaPlatform $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function setAccessToken(?string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function setPlatformId(?string $platformId): self
    {
        $this->platformId = $platformId;

        return $this;
    }
}
