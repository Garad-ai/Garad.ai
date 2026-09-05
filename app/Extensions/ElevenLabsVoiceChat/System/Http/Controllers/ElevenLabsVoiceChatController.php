<?php

namespace App\Extensions\ElevenLabsVoiceChat\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity as EntityFacade;
use App\Extensions\ElevenLabsVoiceChat\System\Http\Requests\VoiceChatbotUpdateRequest;
use App\Extensions\ElevenLabsVoiceChat\System\Services\ElevenLabsVoiceChatService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ElevenLabsVoiceChatController extends Controller
{
    public function __construct(public ElevenLabsVoiceChatService $service) {}

    /**
     * setting page for elevenlabs voice chat
     */
    public function index()
    {
        $item = $this->service->fetchVoiceChatbot();
        $data = empty($item) ? null : $item->trainData();
        $voices = $this->service->getVoices();

        return view('elevenlabs-voice-chat::index', compact('item', 'data', 'voices'));
    }

    /**
     * update the voice chatbot configure
     */
    public function update(VoiceChatbotUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $chatbot = $this->service->fetchVoiceChatbot();
            $chatbot->update($data);

            return response()->json(['status' => 'success']);
        } catch (Throwable $th) {
            return response()->json([
                'status' 		    => 'error',
                'message' 		   => 'Something went wrong!',
                'erroMessage' 	=> $th->getMessage(),
            ]);
        }
    }

    public function checkVoiceBalance(Request $request): ?JsonResponse
    {
        if (Helper::appIsDemo()) {
            $clientIp = Helper::getRequestIp();

            $cacheKey = "voice-chat-attempt-:{$clientIp}:" . now()->format('Y-m-d');

            $requestCount = Cache::get($cacheKey, 0);

            Cache::put($cacheKey, $requestCount + 1, now()->addDay()->startOfDay());

            if ($requestCount >= 25) {
                return response()->json(['status' => 'error', 'message' => 'Exceeded messages limit on demo'], 200);
            }

            return response()->json(['status' => 'success', 'message' => 'Demo mode'], 200);
        }

        $chatbot = $this->service->fetchVoiceChatbot();
        if (! empty($chatbot)) {
            $user = $chatbot->user;
            $driver = EntityFacade::driver(EntityEnum::ELEVENLABS_VOICE_CHATBOT)->forUser($user);

            try {
                $driver->redirectIfNoCreditBalance();
            } catch (Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'status'  => 'error',
                ], 200);
            }
        }

        return response()->json(['status' => 'success', 'message' => ''], 200);
    }
}
