<?php

namespace App\Extensions\AdvancedImage\System\Services\Traits;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait HasNanoBanana
{
    private static string $baseApiUrl = 'https://queue.fal.run/fal-ai/';

    private static string $uploadPath = 'uploads/';

    public static function generateImage(
        string $prompt,
        EntityEnum $entity = EntityEnum::NANO_BANANA_EDIT,
        array $images = []
    ): Response {
        $request = self::buildRequest($prompt, $entity, $images);
        $response = self::makeApiRequest($entity, $request);

        return self::handleResponse($response);
    }

    private static function buildRequest(string $prompt, EntityEnum $entity, array $images): array
    {
        $request = ['prompt' => $prompt];

        if (empty($images)) {
            return $request;
        }

        $request['image_urls'] = $images;
        //        $request['image_urls'] = [
        //            'https://storage.googleapis.com/falserverless/example_inputs/nano-banana-edit-input.png',
        //            'https://cdn.mos.cms.futurecdn.net/ntFmJUZ8tw3ULD3tkBaAtf-650-80.jpg.webp',
        //        ];

        $request['num_images'] = 1;

        $request['output_format'] = 'png';

        return $request;
    }

    private static function makeApiRequest(EntityEnum $entity, array $request): Response
    {
        $url = self::$baseApiUrl . $entity->value;

        return Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post($url, $request);
    }

    private static function handleResponse(Response $response): Response
    {
        if ($response->successful() && $requestId = $response->json('request_id')) {
            return $response;
        }

        $errorMessage = $response->json('detail') ?: 'Check your FAL API key.';

        throw new RuntimeException(__($errorMessage));
    }

    public static function createImageUrls(array $images): array
    {
        if (empty($images)) {
            return [];
        }

        $baseUrl = url(self::$uploadPath);

        return array_map(
            fn ($image) => $baseUrl . '/' . $image->store('falai', 'public'),
            $images
        );
    }
}
