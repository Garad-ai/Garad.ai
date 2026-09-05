<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Http\Controllers;

use App\Extensions\PhoneCallAgent\System\Http\Requests\PhoneCallAgentPhoneNumberAssignRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\PhoneCallAgentPhoneNumberImportRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\PhoneCallAgentStoreRequest;
use App\Extensions\PhoneCallAgent\System\Http\Requests\PhoneCallAgentUpdateRequest;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use App\Extensions\PhoneCallAgent\System\Services\PhoneCallAgentService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Ai\ElevenLabsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PhoneCallAgentController extends Controller
{
    private const DEMO_PHONE_NUMBER = '+16184237161';

    public function __construct(public PhoneCallAgentService $service) {}

    public function index(Request $request): View
    {
        $autoOpen = match (true) {
            $request->routeIs('dashboard.phone-call-agent.history') => 'history',
            $request->routeIs('dashboard.phone-call-agent.new')     => 'new',
            default                                                 => null,
        };

        $agents = $this->service->query()
            ->where('user_id', Auth::id())
            ->withCount(['calls as today_calls_count' => fn ($q) => $q->whereDate('started_at', today())])
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: 100);

        if (Helper::appIsDemo()) {
            $agents->getCollection()->each(function (ExtPhoneCallAgent $agent): void {
                $agent->phone_number ??= self::DEMO_PHONE_NUMBER;

                if ($agent->provider?->value === 'elevenlabs' && ! $agent->agent_id) {
                    $agent->agent_id = 'demo_agent';
                }
            });
        }

        return view('phone-call-agent::index', [
            'agents'   => $agents,
            'voices'   => $this->service->getVoices(setting('phone_call_agent_provider', 'elevenlabs')),
            'autoOpen' => $autoOpen,
        ]);
    }

    public function store(PhoneCallAgentStoreRequest $request): JsonResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $reqData = $request->validated();

        if (isset($reqData['language']) && $reqData['language'] === 'auto') {
            unset($reqData['language']);
        }

        $provider = $reqData['provider'] ?? setting('phone_call_agent_provider', 'elevenlabs');

        if ($provider === 'twilio') {
            $reqData['agent_id'] = null;
            $reqData['voice_id'] = $reqData['voice_id'] ?? 'Polly.Joanna-Neural';
            $reqData['ai_model'] = $reqData['ai_model'] ?? 'gpt-4o-mini';
            $agent = ExtPhoneCallAgent::create($reqData);

            return JsonResource::make($agent);
        }

        $res = $this->service->createAgent($reqData);

        $resData = $res->getData();

        if (isset($resData->status) && $resData->status === 'success') {
            $reqData['agent_id'] = $resData->resData->agent_id;
            $reqData['voice_id'] = $reqData['voice_id'] ?? ElevenLabsService::DEFAULT_ELEVENLABS_VOICE_ID;
            $reqData['ai_model'] = $reqData['ai_model'] ?? ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;

            if (isset($reqData['language']) && $reqData['language'] === 'en') {
                $reqData['ai_model'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH;
            }

            $agent = ExtPhoneCallAgent::create($reqData);

            if ($agent->booking_enabled) {
                $this->service->updateAgent($agent);
            }

            return JsonResource::make($agent);
        }

        $errorMessage = $resData->message
            ?? $resData->error
            ?? __('Failed to create agent. Please check your ElevenLabs API key in settings.');

        return response()->json(['message' => $errorMessage], 422);
    }

    public function update(PhoneCallAgentUpdateRequest $request): JsonResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $reqData = $request->validated();

        if (isset($reqData['language']) && $reqData['language'] === 'auto') {
            unset($reqData['language']);
        }

        $agent = ExtPhoneCallAgent::findOrFail($reqData['id']);
        $agent->update($reqData);
        $agent->refresh();

        $this->service->updateAgent($agent);

        return JsonResource::make($agent);
    }

    public function simulate(ExtPhoneCallAgent $agent): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        if ($agent->user_id !== Auth::id()) {
            abort(403);
        }

        if ($agent->provider->value !== 'elevenlabs' || ! $agent->agent_id) {
            return response()->json([
                'message' => __('Web simulation is only available for ElevenLabs agents.'),
            ], 422);
        }

        $res = $this->service->getConversationSignedUrl($agent->agent_id);
        $data = json_decode($res->getContent(), true);

        if (($data['status'] ?? '') !== 'success') {
            return response()->json([
                'message' => $data['message'] ?? __('Failed to generate simulation URL.'),
            ], 500);
        }

        $token = $data['resData']['token'] ?? null;

        if (! $token) {
            return response()->json(['message' => __('Failed to generate simulation URL.')], 500);
        }

        // Extract signed_url from the JWT payload metadata
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(str_pad(strtr($parts[1] ?? '', '-_', '+/'), strlen($parts[1] ?? '') % 4 === 0 ? 0 : 4 - strlen($parts[1] ?? '') % 4, '=')), true);
        $metadata = json_decode($payload['metadata'] ?? '{}', true);
        $signedUrl = $metadata['signed_url'] ?? null;

        if (! $signedUrl) {
            return response()->json(['message' => __('Failed to extract simulation URL.')], 500);
        }

        return response()->json(['signed_url' => $signedUrl]);
    }

    public function phoneNumbers(ExtPhoneCallAgent $agent): JsonResponse
    {
        if ($error = $this->guardPhoneAgent($agent)) {
            return $error;
        }

        // Only ElevenLabs exposes a workspace number list to pick from.
        $numbers = $agent->provider->value === 'elevenlabs'
            ? $this->service->listAgentPhoneNumbers(Auth::id())
            : [];

        return response()->json(['phone_numbers' => $numbers]);
    }

    public function importPhoneNumber(PhoneCallAgentPhoneNumberImportRequest $request): JsonResource|JsonResponse
    {
        $data = $request->validated();
        $agent = $this->service->query()->where('uuid', $data['agent_id'])->firstOrFail();

        if ($error = $this->guardPhoneAgent($agent)) {
            return $error;
        }

        try {
            if ($agent->provider->value === 'twilio') {
                $this->service->configureTwilioNumber(
                    $agent,
                    $data['phone_number'],
                    $data['twilio_account_sid'],
                    $data['twilio_auth_token'],
                );
            } else {
                if (! $agent->agent_id) {
                    return response()->json([
                        'message' => __('phone-call-agent::phone-call-agent.phone_numbers_provider_unsupported'),
                    ], 422);
                }

                $this->service->importAndAttachPhoneNumber($agent, $this->buildImportPayload($data));
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return JsonResource::make($agent->refresh());
    }

    public function assignPhoneNumber(PhoneCallAgentPhoneNumberAssignRequest $request): JsonResource|JsonResponse
    {
        $data = $request->validated();
        $agent = $this->service->query()->where('uuid', $data['agent_id'])->firstOrFail();

        if ($error = $this->guardPhoneAgent($agent)) {
            return $error;
        }

        if ($agent->provider->value !== 'elevenlabs' || ! $agent->agent_id) {
            return response()->json([
                'message' => __('phone-call-agent::phone-call-agent.phone_numbers_provider_unsupported'),
            ], 422);
        }

        $this->service->assignExistingPhoneNumber($agent, $data['phone_number_id'], $data['phone_number']);

        return JsonResource::make($agent->refresh());
    }

    public function releasePhoneNumber(ExtPhoneCallAgent $agent): JsonResource|JsonResponse
    {
        if ($error = $this->guardPhoneAgent($agent)) {
            return $error;
        }

        if ($agent->provider->value === 'twilio') {
            $this->service->releaseTwilioNumber($agent);
        } else {
            $this->service->releasePhoneNumber($agent);
        }

        return JsonResource::make($agent->refresh());
    }

    /**
     * Build the ElevenLabs import body for the chosen source (twilio | sip_trunk | exotel).
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function buildImportPayload(array $data): array
    {
        $phoneNumber = $data['phone_number'];
        $label = $data['label'] ?? $phoneNumber;

        return match ($data['import_type']) {
            'sip_trunk' => array_filter([
                'provider'              => 'sip_trunk',
                'phone_number'          => $phoneNumber,
                'label'                 => $label,
                'inbound_trunk_config'  => array_filter([
                    'credentials' => array_filter([
                        'username' => $data['sip_username'] ?? null,
                        'password' => $data['sip_password'] ?? null,
                    ]),
                ]),
                'outbound_trunk_config' => array_filter([
                    'address'     => $data['sip_address'] ?? null,
                    'transport'   => $data['sip_transport'] ?? 'auto',
                    'credentials' => array_filter([
                        'username' => $data['sip_username'] ?? null,
                        'password' => $data['sip_password'] ?? null,
                    ]),
                ]),
            ]),
            'exotel' => array_filter([
                'provider'      => 'exotel',
                'phone_number'  => $phoneNumber,
                'label'         => $label,
                'account_sid'   => $data['exotel_account_sid'] ?? null,
                'api_key'       => $data['exotel_api_key'] ?? null,
                'api_token'     => $data['exotel_api_token'] ?? null,
                'api_subdomain' => $data['exotel_subdomain'] ?? null,
                'app_id'        => $data['exotel_app_id'] ?? null,
                'applet_url'    => $data['exotel_applet_url'] ?? null,
            ]),
            default => [
                'provider'     => 'twilio',
                'phone_number' => $phoneNumber,
                'label'        => $label,
                'sid'          => $data['twilio_account_sid'],
                'token'        => $data['twilio_auth_token'],
            ],
        };
    }

    private function guardPhoneAgent(ExtPhoneCallAgent $agent): ?JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        if ($agent->user_id !== Auth::id()) {
            abort(403);
        }

        return null;
    }

    public function delete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => __('phone-call-agent::phone-call-agent.demo_mode_disabled'),
            ], 403);
        }

        $request->validate(['id' => 'required']);

        $agent = $this->service->query()->findOrFail($request->get('id'));

        if ($agent->getAttribute('user_id') === Auth::id()) {
            $this->service->deleteAgent($agent);
            $agent->delete();
        } else {
            abort(403);
        }

        return response()->json([
            'message' => __('Phone Call Agent deleted successfully'),
            'type'    => 'success',
            'status'  => 200,
        ]);
    }

    public function fetchCalendlyEventTypes(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key', '');

        if (empty($apiKey)) {
            return response()->json(['message' => __('API key is required.')], 422);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ];

        $meRes = Http::withHeaders($headers)
            ->timeout(10)
            ->get('https://api.calendly.com/users/me');

        if ($meRes->failed()) {
            return response()->json(['message' => __('Invalid Calendly API key.')], 422);
        }

        $userUri = $meRes->json('resource.uri');

        $typesRes = Http::withHeaders($headers)
            ->timeout(10)
            ->get('https://api.calendly.com/event_types', ['user' => $userUri]);

        if ($typesRes->failed()) {
            return response()->json(['message' => __('Failed to fetch event types.')], 422);
        }

        $types = collect($typesRes->json('collection') ?? [])
            ->filter(fn ($et) => $et['active'] ?? false)
            ->map(fn ($et) => [
                'name' => $et['name'],
                'uri'  => $et['uri'],
                'slug' => $et['slug'],
            ])
            ->values();

        return response()->json(['event_types' => $types]);
    }
}
