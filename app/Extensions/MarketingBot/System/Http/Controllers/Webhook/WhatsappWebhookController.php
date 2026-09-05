<?php

namespace App\Extensions\MarketingBot\System\Http\Controllers\Webhook;

use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use App\Extensions\MarketingBot\System\Services\Whatsapp\WhatsappWebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        public WhatsappWebhookService $whatsappWebhookService,
    ) {}

    public function __invoke(Request $request, WhatsappChannel $whatsappChannel): Response|string
    {
        if ($request->isMethod('GET')) {
            return $this->verify($request, $whatsappChannel);
        }

        Log::channel('stack')->info('[Meta Webhook] Incoming payload', [
            'channel_id' => $whatsappChannel->getKey(),
            'payload'    => $request->all(),
        ]);

        if ($request->has('entry')) {
            $this->whatsappWebhookService->handleMeta($request->all(), $whatsappChannel);
        } else {
            $this->whatsappWebhookService->handle($request, $whatsappChannel);
        }

        return response('OK');
    }

    private function verify(Request $request, WhatsappChannel $whatsappChannel): Response|string
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        Log::channel('stack')->info('[Meta Webhook] Verification request', [
            'channel_id' => $whatsappChannel->getKey(),
            'mode'       => $mode,
            'token'      => $token,
        ]);

        if ($mode === 'subscribe' && $token === $whatsappChannel->meta_verify_token) {
            Log::channel('stack')->info('[Meta Webhook] Verification successful', ['channel_id' => $whatsappChannel->getKey()]);

            return response($challenge, 200);
        }

        Log::channel('stack')->warning('[Meta Webhook] Verification failed - token mismatch', [
            'channel_id'     => $whatsappChannel->getKey(),
            'received_token' => $token,
        ]);

        return response('Forbidden', 403);
    }
}
