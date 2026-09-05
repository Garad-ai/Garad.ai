<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\WebSocket;

use App\Domains\Entity\EntityManager;
use App\Extensions\PhoneCallAgent\System\Booking\BookingProviderResolver;
use App\Extensions\PhoneCallAgent\System\Booking\BookingToolDefinitions;
use App\Extensions\PhoneCallAgent\System\Booking\BookingToolHandler;
use App\Extensions\PhoneCallAgent\System\Enums\CallStatusEnum;
use App\Extensions\PhoneCallAgent\System\Enums\RoleEnum;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentCall;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentTrain;
use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgentTranscript;
use App\Extensions\PhoneCallAgent\System\Services\TwilioLlmService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class ConversationRelayHandler implements MessageComponentInterface
{
    protected SplObjectStorage $clients;

    /** @var array<int, array{agent: ExtPhoneCallAgent, call: ExtPhoneCallAgentCall|null, systemPrompt: string, history: array}> */
    protected array $state = [];

    public function __construct()
    {
        $this->clients = new SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $path = $conn->httpRequest->getUri()->getPath();
        $uuid = basename($path);

        Log::info('[Twilio WS] Connection opened', ['path' => $path, 'uuid' => $uuid]);

        $agent = ExtPhoneCallAgent::query()
            ->where('uuid', $uuid)
            ->where('provider', 'twilio')
            ->where('active', true)
            ->first();

        if (! $agent) {
            Log::warning('[Twilio WS] No active agent for uuid', ['uuid' => $uuid]);
            $conn->close();

            return;
        }

        Log::info('[Twilio WS] Agent loaded', ['agent_id' => $agent->id, 'model' => $agent->ai_model]);

        $this->clients->attach($conn);
        $this->state[spl_object_id($conn)] = [
            'agent'        => $agent,
            'call'         => null,
            'systemPrompt' => $this->buildSystemPrompt($agent),
            'history'      => [],
        ];
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $payload = json_decode((string) $msg, true);

        if (! is_array($payload)) {
            return;
        }

        // ConversationRelay frames carry their discriminator in `type`; keep `event` as a fallback.
        $event = $payload['type'] ?? $payload['event'] ?? null;
        $id = spl_object_id($from);

        Log::info('[Twilio WS] Message', ['type' => $event]);

        if (! isset($this->state[$id])) {
            return;
        }

        match ($event) {
            'setup', 'connected'         => $this->handleConnected($from, $payload, $id),
            'prompt'                     => $this->handlePrompt($from, $payload, $id),
            'interrupt', 'dtmf', 'error' => null,
            default                      => null,
        };
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        Log::info('[Twilio WS] Connection closed');
        $this->handleDisconnect($id);
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, Exception $e): void
    {
        Log::error('[Twilio WS] Error', ['message' => $e->getMessage()]);
        $conn->close();
    }

    private function handleConnected(ConnectionInterface $conn, array $payload, int $id): void
    {
        $callSid = $payload['callSid'] ?? null;

        if (! $callSid) {
            return;
        }

        $agent = $this->state[$id]['agent'];

        $call = ExtPhoneCallAgentCall::query()
            ->where('call_sid', $callSid)
            ->first();

        if (! $call) {
            // Outbound/test calls have no inbound webhook to pre-create the record; create it here
            // so the conversation still lands in call history.
            $call = ExtPhoneCallAgentCall::create([
                'uuid'          => Str::uuid()->toString(),
                'agent_id'      => $agent->id,
                'user_id'       => $agent->user_id,
                'call_sid'      => $callSid,
                'caller_number' => $payload['from'] ?? null,
                'called_number' => $payload['to'] ?? null,
                'status'        => CallStatusEnum::Answered,
                'started_at'    => now(),
            ]);
            Log::info('[Twilio WS] Call record created', ['callSid' => $callSid, 'call_id' => $call->id]);
        } else {
            Log::info('[Twilio WS] Call record linked', ['callSid' => $callSid, 'call_id' => $call->id]);
        }

        $this->state[$id]['call'] = $call;
    }

    private function handlePrompt(ConnectionInterface $conn, array $payload, int $id): void
    {
        $voicePrompt = $payload['voicePrompt'] ?? '';

        if ($voicePrompt === '') {
            return;
        }

        $ctx = &$this->state[$id];
        $agent = $ctx['agent'];
        $call = $ctx['call'];

        Log::info('[Twilio WS] User prompt', ['prompt' => $voicePrompt]);

        $ctx['history'][] = ['role' => 'user', 'content' => $voicePrompt];

        if ($call) {
            ExtPhoneCallAgentTranscript::create([
                'call_id' => $call->id,
                'role'    => RoleEnum::user->value,
                'message' => $voicePrompt,
            ]);
        }

        $result = $this->callLlm($ctx);
        $spoken = $this->stripToolNarration($result['spoken']);

        Log::info('[Twilio WS] LLM response', ['response' => $spoken]);

        // History keeps the richer `memory` (includes internal tool results) so the model
        // recalls slots/UIDs on later turns; the caller only hears `spoken`.
        $ctx['history'][] = ['role' => 'assistant', 'content' => $result['memory']];

        if ($call) {
            ExtPhoneCallAgentTranscript::create([
                'call_id' => $call->id,
                'role'    => RoleEnum::agent->value,
                'message' => $spoken,
            ]);
        }

        $conn->send(json_encode([
            'type'  => 'text',
            'token' => $spoken,
            'last'  => true,
        ]));
    }

    private function handleDisconnect(int $id): void
    {
        if (! isset($this->state[$id])) {
            return;
        }

        $call = $this->state[$id]['call'];

        if ($call && $call->status !== CallStatusEnum::Completed) {
            $call->update([
                'status'   => CallStatusEnum::Completed,
                'ended_at' => now(),
                'duration' => $call->started_at ? max(0, now()->diffInSeconds($call->started_at)) : ($call->duration ?? 0),
            ]);
        }

        unset($this->state[$id]);
    }

    private function buildSystemPrompt(ExtPhoneCallAgent $agent): string
    {
        $instructions = $agent->instructions ?? '';

        $trains = ExtPhoneCallAgentTrain::query()
            ->where('agent_id', $agent->getKey())
            ->whereNotNull('trained_at')
            ->get();

        if (! $trains->isEmpty()) {
            $kb = $trains
                ->map(fn ($t) => trim($t->content ?? $t->text ?? ''))
                ->filter()
                ->map(fn ($text, $i) => isset($trains[$i]->name) && $trains[$i]->name
                    ? "## {$trains[$i]->name}\n{$text}"
                    : $text
                )
                ->implode("\n\n---\n\n");

            $instructions .= "\n\n# Knowledge Base\n\n" . $kb;
        }

        if ($this->bookingReady($agent)) {
            $today = now()->format('l, Y-m-d');

            $instructions .= "\n\n# Booking Capability\n"
                . "You can check availability and book appointments for the caller. Today is {$today}; use it to resolve relative or spoken dates and to pick the correct year.\n\n"
                . "This is a spoken phone conversation. The caller will say dates and times in everyday language (for example \"next Tuesday\", \"January 24th\", \"tomorrow afternoon\"). NEVER ask the caller to say or spell a date in YYYY-MM-DD or any digit-by-digit format, and never read date or time formats aloud. Silently interpret what they say, convert it to the YYYY-MM-DD format yourself, and pass that to the check_availability and create_booking tools. If a spoken date is genuinely ambiguous, ask one short, natural clarifying question (e.g. \"Do you mean this coming Tuesday?\") — never mention formats.\n\n"
                . "The ONLY information required to make a booking is: a specific available time slot, the caller's full name, their email address, and their timezone. Do NOT ask for anything else — no service type, no duration, no meeting type, no reason for the appointment, and no phone number unless the caller volunteers it. Never invent extra required fields.\n\n"
                . "Booking flow — follow these steps and actually call the tools; never just say you will book without calling create_booking:\n"
                . "1. Work out the caller's preferred date and call check_availability.\n"
                . '2. Read the returned slots back in plain spoken time (e.g. "nine in the morning", not an ISO timestamp) and let the caller pick one.' . "\n"
                . "3. Collect the caller's full name, email address, and timezone — only the ones you don't already have.\n"
                . "4. Confirm the chosen time, name, and email back in plain English ONCE, then immediately call create_booking. Do not keep re-confirming or asking for more details.\n"
                . 'After create_booking succeeds, read the booked date and time back in plain English. For cancellations or rescheduling, ask for the booking reference or the email address used when the booking was made.';
        }

        return $instructions;
    }

    /**
     * Run one LLM turn.
     *
     * @return array{spoken: string, memory: string} `spoken` is sent to the caller; `memory` is
     *                                               stored in history (may embed internal tool results)
     */
    private function callLlm(array $ctx): array
    {
        $agent = $ctx['agent'];
        $user = $agent->user;

        try {
            // Use the platform's default chat model and bill the call against the owner's plan/credits.
            $driver = app(EntityManager::class)->driverForUser($user);
            $model = $driver->enum()->value;

            if (! $driver->isUnlimitedCredit() && ! $driver->hasCreditBalance()) {
                Log::warning('[Twilio WS] No credit balance', ['user_id' => $agent->user_id]);

                return $this->reply(__('phone-call-agent::phone-call-agent.no_credits_voice'));
            }

            $llm = app(TwilioLlmService::class);

            if ($this->bookingReady($agent)) {
                $provider = app(BookingProviderResolver::class)->resolve($agent);
                $handler = new BookingToolHandler($provider);
                $definitions = new BookingToolDefinitions;
                $tools = $this->toolsForEngine($model, $definitions);

                $result = $llm->completeWithTools(
                    model: $model,
                    systemPrompt: $ctx['systemPrompt'],
                    messages: $ctx['history'],
                    tools: $tools,
                    toolHandler: fn (string $name, array $input) => $handler->handle($name, $input),
                );

                $spoken = $result['text'];
                $memory = $result['toolSummary'] !== ''
                    ? $result['toolSummary'] . "\n\n" . $spoken
                    : $spoken;
            } else {
                $spoken = $llm->complete(
                    model: $model,
                    systemPrompt: $ctx['systemPrompt'],
                    messages: $ctx['history'],
                );
                $memory = $spoken;
            }

            $this->deductCredit($user, $spoken);

            return ['spoken' => $spoken, 'memory' => $memory];
        } catch (Exception $e) {
            Log::error('[Twilio WS] LLM error', ['error' => $e->getMessage()]);

            return $this->reply("I'm sorry, I encountered an error. Please try again.");
        }
    }

    /** @return array{spoken: string, memory: string} */
    private function reply(string $text): array
    {
        return ['spoken' => $text, 'memory' => $text];
    }

    /**
     * Strip raw JSON tool-call narration from the spoken text.
     *
     * Weaker models sometimes write the tool arguments (and even a fabricated result) as a raw
     * JSON object inside their reply instead of emitting a structured tool call. Left untouched it
     * would be read aloud by TTS and stored in the transcript. We remove flat JSON object blobs and
     * tidy the surrounding whitespace, falling back to the original text if nothing readable remains.
     */
    private function stripToolNarration(string $text): string
    {
        if (! str_contains($text, '{')) {
            return $text;
        }

        $cleaned = preg_replace('/\{[^{}]*\}/', '', $text) ?? $text;
        $cleaned = preg_replace('/[ \t]{2,}/', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim(preg_replace('/\n{3,}/', "\n\n", $cleaned) ?? $cleaned);

        return $cleaned !== '' ? $cleaned : $text;
    }

    /**
     * Booking is usable only when enabled AND a provider + event type are configured. Both the
     * system-prompt capability blurb and the tool wiring must agree, or the model would be told it
     * can book while having no tools — producing fabricated confirmations.
     */
    private function bookingReady(ExtPhoneCallAgent $agent): bool
    {
        return $agent->booking_enabled && $agent->booking_provider && $agent->booking_event_type_id;
    }

    /**
     * Bill the generated response against the owner's plan/shared credits via the Entity system.
     */
    private function deductCredit(User $user, string $responseText): void
    {
        if (trim($responseText) === '') {
            return;
        }

        try {
            app(EntityManager::class)->driverForUser($user)
                ->input($responseText)
                ->calculateCredit()
                ->decreaseCredit();
        } catch (Exception $e) {
            Log::error('[Twilio WS] Credit deduction failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function toolsForEngine(string $model, BookingToolDefinitions $definitions): array
    {
        return match (true) {
            str_starts_with($model, 'claude')  => $definitions->forAnthropic(),
            str_starts_with($model, 'gemini')  => $definitions->forGemini(),
            default                            => $definitions->forOpenAi(),
        };
    }
}
