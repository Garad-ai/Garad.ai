<?php

namespace App\Extensions\AiPersona\System\Services\Traits;

use Psr\Http\Message\ResponseInterface;

trait AiPersona
{
    public function createVideo(
        array $body,
    ): array {
        return $this->post('/v2/video/generate', $body);
    }

    public function listVideos(int $limit = 50): array
    {
        $url = "/v1/video.list?limit={$limit}&offset={$this->secretKey}";

        return $this->get($url);
    }

    public function retrieveVideo(string $videoId): array
    {
        $url = "/v1/video_status.get?video_id={$videoId}";

        return $this->get($url);
    }

    public function deleteVideo(string $videoId): ResponseInterface
    {
        $url = "/v1/video.delete?video_id={$videoId}";

        return $this->delete($url);
    }

    public function listAvatars(): array
    {
        $url = '/v2/avatars';

        return $this->get($url);
    }

    public function listVoices(): array
    {
        $url = '/v2/voices';

        return $this->get($url);
    }

    public function listLocales(): array
    {
        return $this->get('/v2/voices/locales');
    }

    public function generatePhotoAvatar(array $body): array
    {
        return $this->post('/v2/photo_avatar/photo/generate', $body);
    }

    public function checkPhotoGeneration(string $generationId): array
    {
        return $this->get("/v2/photo_avatar/generation/{$generationId}");
    }

    public function createAvatarGroup(array $body): array
    {
        return $this->post('/v2/photo_avatar/avatar_group/create', $body);
    }

    public function trainAvatarGroup(string $groupId): array
    {
        return $this->post('/v2/photo_avatar/train', ['group_id' => $groupId]);
    }

    public function checkTrainingStatus(string $groupId): array
    {
        return $this->get("/v2/photo_avatar/train/status/{$groupId}");
    }

    public function listAvatarsInGroup(string $groupId): array
    {
        return $this->get("/v2/avatar_group/{$groupId}/avatars");
    }
}
