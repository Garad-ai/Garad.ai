<?php

namespace App\Extensions\MarketingBot\System\Services\Whatsapp;

use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsappTemplateService
{
    private const GRAPH_API_VERSION = 'v19.0';

    private const GRAPH_API_BASE = 'https://graph.facebook.com';

    /**
     * Fetch approved WhatsApp message templates for the given channel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemplates(WhatsappChannel $channel): array
    {
        try {
            $url = sprintf(
                '%s/%s/%s/message_templates',
                self::GRAPH_API_BASE,
                self::GRAPH_API_VERSION,
                $channel->meta_waba_id,
            );

            $response = Http::withToken($channel->meta_access_token)
                ->acceptJson()
                ->get($url, ['limit' => 100, 'status' => 'APPROVED']);

            Log::channel('stack')->info('[Meta Templates] Response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if ($response->failed()) {
                return [];
            }

            return $response->json('data', []);
        } catch (Exception $exception) {
            Log::channel('stack')->error('[Meta Templates] Exception', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract the number of body variables ({{1}}, {{2}}, ...) from a template.
     *
     * @param  array<string, mixed>  $template
     */
    public function bodyVariableCount(array $template): int
    {
        $bodyText = $this->bodyText($template);

        preg_match_all('/\{\{\d+\}\}/', $bodyText, $matches);

        return count(array_unique($matches[0]));
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function bodyText(array $template): string
    {
        foreach ($template['components'] ?? [] as $component) {
            if (($component['type'] ?? '') === 'BODY') {
                return $component['text'] ?? '';
            }
        }

        return '';
    }
}
