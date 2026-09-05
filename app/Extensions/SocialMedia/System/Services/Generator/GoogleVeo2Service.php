<?php

namespace App\Extensions\SocialMedia\System\Services\Generator;

use App\Helpers\Classes\ApiHelper;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleVeo2Service
{
    public static function content(string $requestId): PromiseInterface|Response
    {
        return Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->get('https://queue.fal.run/fal-ai/veo2/requests/' . $requestId);
    }

    public static function status(string $requestId): PromiseInterface|Response
    {
        return Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->get('https://queue.fal.run/fal-ai/veo2/requests/' . $requestId . '/status');
    }

    public static function downloadAndSaveVideoFromUrl($url): ?string
    {
        // Resmin içeriğini çek
        $response = Http::get($url);

        if ($response->successful()) {
            // Dosya uzantısını tahmin et
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

            // Benzersiz bir dosya adı oluştur
            $fileName = Str::uuid() . '.' . $extension;

            // Storage path (örnek: storage/app/public/images)
            $path = 'social-meida/' . $fileName;

            // Dosyayı kaydet
            Storage::disk('public')->put($path, $response->body());

            // İsteğe bağlı: URL'yi döndür (örnek: http://.../storage/images/abc.jpg)
            return '/uploads/' . $path;
        }

        return null;
    }

    public static function generate(string $prompt, array $options = []): PromiseInterface|Response
    {
        return Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => 'Key ' . ApiHelper::setFalAIKey(),
        ])->post('https://queue.fal.run/fal-ai/veo2', [
            'prompt'       => $prompt,
            'aspect_ratio' => $options['aspect_ratio'] ?? '9:16',
            'duration'     => $options['duration'] ?? '8s',
        ]);
    }
}
