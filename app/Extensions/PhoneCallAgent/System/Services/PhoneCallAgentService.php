<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Services;

use App\Extensions\PhoneCallAgent\System\Booking\BookingToolDefinitions;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentTrain;
use App\Extensions\PhoneCallAgent\System\Services\Providers\ElevenLabsPhoneService;
use App\Extensions\PhoneCallAgent\System\Services\Providers\TwilioPhoneService;
use App\Services\Ai\ElevenLabsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PhoneCallAgentService
{
    public function __construct(
        protected ElevenLabsService $elevenLabs,
        protected ElevenLabsPhoneService $phoneService,
        protected TwilioPhoneService $twilioPhone,
        protected BookingToolDefinitions $bookingTools
    ) {}

    public function query(): Builder
    {
        return ExtPhoneCallAgent::query();
    }

    public function createAgent(array $args): JsonResponse
    {
        $conversationConfig = [
            'agent' => [
                'first_message' => $args['welcome_message'] ?? '',
                'prompt'        => [
                    'prompt' => $args['instructions'] ?? '',
                ],
            ],
            'tts' => [
                'voice_id' => $args['voice_id'] ?? ElevenLabsService::DEFAULT_ELEVENLABS_VOICE_ID,
                'model_id' => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
            ],
        ];

        if (isset($args['language']) && $args['language'] !== null && $args['language'] !== 'auto') {
            $conversationConfig['agent']['language'] = $args['language'];
            if ($args['language'] !== 'en') {
                $conversationConfig['tts']['model_id'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;
            }
        }

        return $this->elevenLabs->createAgent(conversation_config: $conversationConfig, name: $args['title']);
    }

    public function updateAgent(ExtPhoneCallAgent $agent): void
    {
        if ($agent->provider->value === 'twilio') {
            return;
        }

        if (! $agent->agent_id) {
            return;
        }

        // Fetch current config to preserve fields we don't manage (tools, timezone, etc.)
        $current = $this->elevenLabs->getAgent($agent->agent_id);
        $currentConfig = $current->getData(true)['resData']['conversation_config'] ?? [];

        $promptConfig = array_merge(
            $currentConfig['agent']['prompt'] ?? [],
            ['prompt' => $agent->instructions ?? '']
        );

        // Create/delete the standalone booking tools. Capture the prior ids first so we can drop
        // them from the agent config regardless of whether booking was just toggled off.
        $previousToolIds = $agent->booking_tool_ids ?? [];
        $toolIds = $this->syncBookingTools($agent);

        // Tools are managed as standalone entities referenced by tool_ids. Never echo inline `tools`
        // back — ElevenLabs rejects its own serialized inline-tool payload (dynamic_variable
        // placeholders, etc.). This also migrates legacy agents off inline booking tools.
        unset($promptConfig['tools']);

        // Rebuild tool_ids: drop our previously-managed ids, then re-add current ones if booking is on.
        $promptConfig['tool_ids'] = array_values(array_filter(
            $promptConfig['tool_ids'] ?? [],
            fn ($id) => ! in_array($id, $previousToolIds, true)
        ));

        if ($agent->booking_enabled && ! empty($toolIds)) {
            $promptConfig['tool_ids'] = array_values(array_unique(array_merge($promptConfig['tool_ids'], $toolIds)));
        }

        if (empty($promptConfig['tool_ids'])) {
            unset($promptConfig['tool_ids']);
        }

        $agentConfig = [
            'first_message' => $agent->welcome_message ?? '',
            'prompt'        => $promptConfig,
        ];

        if ($agent->language && $agent->language !== 'auto') {
            $agentConfig['language'] = $agent->language;
        }

        $ttsConfig = [
            'voice_id' => $agent->voice_id,
            'model_id' => ($agent->language && $agent->language !== 'auto' && $agent->language !== 'en')
                ? ElevenLabsService::DEFAULT_ELEVENLABS_MODEL
                : ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
        ];

        $conversationConfig = [
            'agent' => $agentConfig,
            'tts'   => $ttsConfig,
        ];

        $this->updateAgentWithKnowledgebase($agent, $conversationConfig);
    }

    public function deleteAgent(ExtPhoneCallAgent $agent): void
    {
        if ($agent->provider->value === 'twilio') {
            return;
        }

        $this->deleteBookingTools($agent->booking_tool_ids ?? []);

        if ($agent->agent_id) {
            $this->elevenLabs->deleteAgent($agent->agent_id);
        }
    }

    /**
     * Reconcile the agent's standalone ElevenLabs booking tools with its booking_enabled state.
     * Creates the tools (storing their ids) when booking is on, force-deletes them when off.
     *
     * @return array<int, string> the agent's current booking tool ids
     */
    public function syncBookingTools(ExtPhoneCallAgent $agent): array
    {
        if ($agent->provider->value === 'twilio') {
            return [];
        }

        $existing = $agent->booking_tool_ids ?? [];

        if ($agent->booking_enabled) {
            if (! empty($existing)) {
                return $existing;
            }

            $toolIds = [];
            foreach ($this->bookingTools->toolConfigsForElevenLabs($agent) as $config) {
                $res = $this->elevenLabs->createTool($config);
                $data = $res->getData();

                if (($data->status ?? '') === 'success' && isset($data->resData->id)) {
                    $toolIds[] = $data->resData->id;
                }
            }

            $agent->forceFill(['booking_tool_ids' => $toolIds ?: null])->save();

            return $toolIds;
        }

        if (! empty($existing)) {
            $this->deleteBookingTools($existing);
            $agent->forceFill(['booking_tool_ids' => null])->save();
        }

        return [];
    }

    /**
     * @param  array<int, string>  $toolIds
     */
    private function deleteBookingTools(array $toolIds): void
    {
        foreach ($toolIds as $toolId) {
            $this->elevenLabs->deleteTool($toolId, force: true);
        }
    }

    public function getConversationSignedUrl(string $agentId): JsonResponse
    {
        return $this->elevenLabs->getSignedUrl($agentId);
    }

    /**
     * Phone numbers in the shared ElevenLabs workspace that belong to one of this user's agents.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAgentPhoneNumbers(int $userId): array
    {
        $ownAgentIds = ExtPhoneCallAgent::query()
            ->where('user_id', $userId)
            ->whereNotNull('agent_id')
            ->pluck('agent_id')
            ->all();

        if (empty($ownAgentIds)) {
            return [];
        }

        return array_values(array_filter(
            $this->phoneService->listPhoneNumbers(),
            fn (array $number): bool => in_array($number['assigned_agent']['agent_id'] ?? null, $ownAgentIds, true)
        ));
    }

    /**
     * Import a number into ElevenLabs (from Twilio, a SIP trunk, or Exotel), assign it to the
     * agent, and persist it locally. The payload's `provider` discriminator selects the source.
     *
     * @param  array<string, mixed>  $payload
     *
     * @return array<string, mixed>
     */
    public function importAndAttachPhoneNumber(ExtPhoneCallAgent $agent, array $payload): array
    {
        $res = $this->phoneService->importPhoneNumber($payload);

        $phoneNumberId = $res['phone_number_id'] ?? null;

        if (! $phoneNumberId) {
            throw new RuntimeException($res['detail']['message'] ?? __('phone-call-agent::phone-call-agent.phone_number_import_failed'));
        }

        $this->phoneService->assignAgent($phoneNumberId, (string) $agent->agent_id);

        $attributes = [
            'phone_number'    => $payload['phone_number'],
            'phone_number_id' => $phoneNumberId,
        ];

        // Twilio credentials are reusable, so keep them on the agent; SIP/Exotel secrets live in ElevenLabs.
        if (($payload['provider'] ?? null) === 'twilio') {
            $attributes['twilio_account_sid'] = $payload['sid'] ?? null;
            $attributes['twilio_auth_token'] = $payload['token'] ?? null;
        }

        $agent->forceFill($attributes)->save();

        return $res;
    }

    /**
     * Reassign an already-imported number to this agent, moving it off any of the user's other agents.
     */
    public function assignExistingPhoneNumber(ExtPhoneCallAgent $agent, string $phoneNumberId, string $phoneNumber): void
    {
        $this->phoneService->assignAgent($phoneNumberId, (string) $agent->agent_id);

        ExtPhoneCallAgent::query()
            ->where('user_id', $agent->user_id)
            ->where('phone_number_id', $phoneNumberId)
            ->whereKeyNot($agent->getKey())
            ->update(['phone_number' => null, 'phone_number_id' => null]);

        $agent->forceFill([
            'phone_number'    => $phoneNumber,
            'phone_number_id' => $phoneNumberId,
        ])->save();
    }

    public function releasePhoneNumber(ExtPhoneCallAgent $agent): void
    {
        if ($agent->phone_number_id) {
            $this->phoneService->removePhoneNumber($agent->phone_number_id);
        }

        $agent->forceFill([
            'phone_number'    => null,
            'phone_number_id' => null,
        ])->save();
    }

    /**
     * Point a user-owned Twilio number at our webhooks and persist the agent's Twilio credentials.
     */
    public function configureTwilioNumber(ExtPhoneCallAgent $agent, string $phoneNumber, string $sid, string $token): void
    {
        $numberSid = $this->twilioPhone->configureInboundNumber(
            $sid,
            $token,
            $phoneNumber,
            route('api.phone-call-agent.webhook.twilio.inbound'),
            route('api.phone-call-agent.webhook.twilio.status'),
        );

        $agent->forceFill([
            'phone_number'       => $phoneNumber,
            'phone_number_id'    => $numberSid,
            'twilio_account_sid' => $sid,
            'twilio_auth_token'  => $token,
        ])->save();
    }

    public function releaseTwilioNumber(ExtPhoneCallAgent $agent): void
    {
        if ($agent->twilio_account_sid && $agent->twilio_auth_token) {
            $this->twilioPhone->clearInboundNumber($agent->twilio_account_sid, $agent->twilio_auth_token, $agent->phone_number_id);
        }

        $agent->forceFill([
            'phone_number'    => null,
            'phone_number_id' => null,
        ])->save();
    }

    public function getVoices(string $provider = 'elevenlabs'): array
    {
        if ($provider === 'twilio') {
            return $this->getTwilioVoices();
        }

        $res = $this->elevenLabs->getListOfVoices(page_size: 100);
        if ($res->getData()->status === 'success') {
            return $res->getData()->resData->voices;
        }

        return [];
    }

    private function getTwilioVoices(): array
    {
        return [
            ['voice_id' => 'Polly.Joanna-Neural', 'name' => 'Joanna (US English, Female)'],
            ['voice_id' => 'Polly.Matthew-Neural', 'name' => 'Matthew (US English, Male)'],
            ['voice_id' => 'Polly.Salli-Neural', 'name' => 'Salli (US English, Female)'],
            ['voice_id' => 'Polly.Amy-Neural', 'name' => 'Amy (British English, Female)'],
            ['voice_id' => 'Polly.Brian-Neural', 'name' => 'Brian (British English, Male)'],
            ['voice_id' => 'Polly.Emma-Neural', 'name' => 'Emma (British English, Female)'],
            ['voice_id' => 'Polly.Lucia-Neural', 'name' => 'Lucia (Spanish, Female)'],
            ['voice_id' => 'Polly.Lupe-Neural', 'name' => 'Lupe (Spanish US, Female)'],
            ['voice_id' => 'Polly.Lea-Neural', 'name' => 'Léa (French, Female)'],
            ['voice_id' => 'Polly.Vicki-Neural', 'name' => 'Vicki (German, Female)'],
            ['voice_id' => 'Polly.Bianca-Neural', 'name' => 'Bianca (Italian, Female)'],
            ['voice_id' => 'Google.en-US-Standard-B', 'name' => 'Google English US (Male)'],
            ['voice_id' => 'Google.en-US-Standard-C', 'name' => 'Google English US (Female)'],
            ['voice_id' => 'Google.en-GB-Standard-A', 'name' => 'Google English GB (Female)'],
            ['voice_id' => 'Google.fr-FR-Standard-A', 'name' => 'Google French (Female)'],
            ['voice_id' => 'Google.de-DE-Standard-A', 'name' => 'Google German (Female)'],
            ['voice_id' => 'Google.es-ES-Standard-A', 'name' => 'Google Spanish (Female)'],
        ];
    }

    public function addKnowledgebase(string $type, mixed $content, ?string $name = null): JsonResponse
    {
        return match ($type) {
            'text' => $this->elevenLabs->createKnowledgebaseDocFromText(text: $content, name: $name),
            'url'  => $this->elevenLabs->createKnowledgebaseDocFromUrl(url: $content, name: $name),
            'file' => $this->elevenLabs->createKnowledgebaseDocFromFile(file: $content, name: $name),
        };
    }

    public function deleteKnowledgebase(string $docId): void
    {
        $this->elevenLabs->deleteKnowledgebaseDocument($docId);
    }

    public function updateAgentWithKnowledgebase(ExtPhoneCallAgent $agent, array $conversationConfig = []): void
    {
        $trains = ExtPhoneCallAgentTrain::query()
            ->whereNotNull('trained_at')
            ->whereNotNull('doc_id')
            ->where('agent_id', $agent->getKey())
            ->get();

        $trainedKnowledges = $trains->map(fn ($train) => [
            'id'   => $train->doc_id,
            'name' => $train->name,
            'type' => $train->type,
        ])->all();

        if (! empty($trainedKnowledges)) {
            $conversationConfig['agent']['prompt']['knowledge_base'] = $trainedKnowledges;
        }

        if ($agent->agent_id && ! empty($conversationConfig)) {
            $this->elevenLabs->updateAgent(agent_id: $agent->agent_id, conversation_config: $conversationConfig, name: $agent->title);
        }
    }
}
