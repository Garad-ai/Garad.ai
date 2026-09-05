<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Whatsapp;

use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use App\Extensions\MarketingBot\System\Services\Whatsapp\MetaWhatsappTemplateService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaWhatsappTemplateController extends Controller
{
    public function __construct(
        public MetaWhatsappTemplateService $templateService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $channel = WhatsappChannel::query()
            ->where('user_id', Auth::id())
            ->where('whatsapp_provider', 'meta')
            ->first();

        if (! $channel || ! $channel->meta_waba_id) {
            return response()->json(['templates' => []]);
        }

        $templates = $this->templateService->getTemplates($channel);

        $formatted = array_map(function (array $template) {
            return [
                'name'           => $template['name'],
                'language'       => $template['language'] ?? 'en_US',
                'body_text'      => $this->templateService->bodyText($template),
                'variable_count' => $this->templateService->bodyVariableCount($template),
            ];
        }, $templates);

        return response()->json(['templates' => array_values($formatted)]);
    }
}
