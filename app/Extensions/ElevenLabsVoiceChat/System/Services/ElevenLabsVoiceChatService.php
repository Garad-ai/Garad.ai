<?php

declare(strict_types=1);

namespace App\Extensions\ElevenLabsVoiceChat\System\Services;

use App\Extensions\ElevenLabsVoiceChat\System\Models\VoiceChatBot;
use App\Extensions\ElevenLabsVoiceChat\System\Models\VoiceChatBotTrain;
use App\Services\Ai\ElevenLabsService;
use Illuminate\Http\JsonResponse;

class ElevenLabsVoiceChatService
{
    public const ELEVENLABS_VOICE_CHATBOT_TITLE = 'MagicAI Voice Chatbot';

    public function __construct(protected ElevenLabsService $service) {}

    /**
     * fetch the voice chat agent or create new agent
     */
    public function fetchVoiceChatbot(): VoiceChatBot|array
    {
        $chatbot = VoiceChatBot::where('title', ElevenLabsVoiceChatService::ELEVENLABS_VOICE_CHATBOT_TITLE)->first();
        if ($chatbot) {
            return $chatbot;
        }

        $defaultParams = $this->defaultParams();
        $res = $this->createAgent($defaultParams);
        if ($res?->getData()->status == 'success') {
            $defaultParams['agent_id'] = $res->getData()->resData->agent_id;
            $defaultParams['user_id'] = auth()->id();

            return VoiceChatBot::create($defaultParams);
        }

        return [];
    }

    /**
     * get voices from elevenlabs
     */
    public function getVoices(): array
    {
        $res = $this->service->getListOfVoices(page_size: 100);
        if ($res->getData()->status == 'success') {
            return $res->getData()->resData->voices;
        }

        return [];
    }

    /**
     * create agent
     *
     * @param  mixed  $args
     */
    public function createAgent($args): JsonResponse
    {
        $conversation_config = [
            'agent' => [
                'first_message' => $args['welcome_message'],
                'prompt'        => [
                    'prompt' => $args['instruction'],
                ],
            ],
            'tts' => [
                'model_id' => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
            ],
            'conversation' => [
                'client_events' => ['audio', 'interruption', 'user_transcript', 'agent_response'],
            ],
        ];

        if (isset($args['language']) && $args['language'] != null && $args['language'] != 'auto') {
            $conversation_config['agent']['language'] = $args['language'];
            if ($args['language'] != 'en') {
                $conversation_config['tts']['model_id'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;
            }
        }

        return $this->service->createAgent(conversation_config: $conversation_config, name: $args['title']);
    }

    /**
     * update agent
     *
     * @return JsonResponse|void
     */
    public function updateAgent(string|int $agent_id)
    {
        $agent = VoiceChatBot::find($agent_id);
        if (empty($agent)) {
            return;
        }

        $conversation_config = [
            'agent' => [
                'first_message' => $agent->welcome_message,
                'prompt'        => [
                    'prompt' => $agent->instructions,
                ],
            ],
            'tts' => [
                'voice_id' => $agent->voice_id,
                'model_id' => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
            ],
        ];

        if (isset($agent->language) && $agent->language != null && $agent->language != 'auto') {
            $conversation_config['agent']['language'] = $agent->language;
            if ($agent->language != 'en') {
                $conversation_config['tts']['model_id'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;
            }
        }

        $this->updateAgentWithKnowledgebase($agent_id);

        return $this->service->updateAgent(agent_id: $agent->agent_id, conversation_config: $conversation_config, name: $agent->name);
    }

    /**
     * update the agent with knowledgebase by trianing the content
     */
    public function updateAgentWithKnowledgebase(string|int $chatbot_id): void
    {
        $trains = VoiceChatBotTrain::query()
            ->whereNotNull('trained_at')
            ->whereNotNull('doc_id')
            ->where('chatbot_id', $chatbot_id)
            ->get();

        $trainedKnowledges = $trains->map(fn ($train) => [
            'id' 	  => $train->doc_id,
            'name' 	=> $train->name,
            'type' 	=> $train->type,
        ])->all();

        if (empty($trainedKnowledges)) {
            return;
        }

        $config = [
            'agent' => [
                'prompt' => [
                    'knowledge_base' => $trainedKnowledges,
                ],
            ],
        ];

        $agent_id = VoiceChatBot::findOrFail($chatbot_id)?->agent_id;

        if ($agent_id) {
            $this->service->updateAgent(agent_id: $agent_id, conversation_config: $config);
        }
    }

    /**
     * delete knowledgebase from elevenlabes
     */
    public function deleteKnowledgebase(string $doc_id): JsonResponse
    {
        return $this->service->deleteKnowledgebaseDocument($doc_id);
    }

    /**
     * add knowledgebase to elevenlabs
     *
     * @param  mixed  $content
     */
    public function addKnowledgebase(
        string $type,
        $content,
        ?string $name = null
    ): JsonResponse {
        return match ($type) {
            'text' => $this->service->createKnowledgebaseDocFromText(text: $content, name: $name),
            'url'  => $this->service->createKnowledgebaseDocFromUrl(url: $content, name: $name),
            'file' => $this->service->createKnowledgebaseDocFromFile(file: $content, name: $name),
        };
    }

    /**
     * default voice chat params
     */
    private function defaultParams(): array
    {
        return [
            'welcome_message' 	=> 'Hey there, How can I help you?',
            'instruction' 		   => 'You are good customer assistant',
            'language'			      => 'en',
            'title'				        => ElevenLabsVoiceChatService::ELEVENLABS_VOICE_CHATBOT_TITLE,
            'voice_id'			      => ElevenLabsService::DEFAULT_ELEVENLABS_VOICE_ID,
            'ai_model'			      => ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH,
        ];
    }
}
