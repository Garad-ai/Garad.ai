<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Console\Commands;

use App\Extensions\PhoneCallAgent\System\Models\ExtPhoneCallAgent;
use App\Extensions\PhoneCallAgent\System\Services\Providers\TwilioPhoneService;
use Illuminate\Console\Command;
use Throwable;
use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;

class TwilioRelayTestCall extends Command
{
    protected $signature = 'phone-call-agent:relay-test-call
        {to : Recipient phone number in E.164 (e.g. +15551234567)}
        {--agent= : Agent uuid or phone number; defaults to the latest Twilio agent}
        {--host= : Public host for the wss relay (e.g. xxx.ngrok-free.app); defaults to APP_URL host}';

    protected $description = 'Place an outbound Twilio test call that connects ConversationRelay to a Phone Call Agent';

    public function handle(): int
    {
        $agent = $this->resolveAgent();

        if (! $agent) {
            $this->error('No matching Twilio agent found.');

            return self::FAILURE;
        }

        if (! $agent->twilio_account_sid || ! $agent->twilio_auth_token || ! $agent->phone_number) {
            $this->error("Agent \"{$agent->title}\" is missing Twilio credentials or a phone number.");

            return self::FAILURE;
        }

        $host = $this->resolveHost();
        $to = (string) $this->argument('to');

        $wss = "wss://{$host}/api/phone-call-agent/ws/twilio/{$agent->uuid}";

        $attrs = array_merge(
            ['url' => $wss],
            TwilioPhoneService::conversationRelayVoice($agent->voice_id ?: 'Polly.Joanna-Neural'),
        );

        if ($agent->welcome_message) {
            $attrs['welcomeGreeting'] = $agent->welcome_message;
        }

        if ($agent->language && $agent->language !== 'auto') {
            $attrs['language'] = $agent->language;
        }

        $response = new VoiceResponse;
        $response->connect()->conversationRelay($attrs);

        $this->info("Agent:  {$agent->title} ({$agent->phone_number})");
        $this->info("Relay:  {$wss}");
        $this->info("Calling {$to} ...");

        try {
            $call = (new Client($agent->twilio_account_sid, $agent->twilio_auth_token))
                ->calls
                ->create($to, $agent->phone_number, ['twiml' => (string) $response]);
        } catch (Throwable $e) {
            $this->error('Twilio call failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Call placed. SID: {$call->sid}");
        $this->line('Watch storage/logs/laravel.log and ensure the relay server + ngrok wss proxy are up.');

        return self::SUCCESS;
    }

    private function resolveAgent(): ?ExtPhoneCallAgent
    {
        $query = ExtPhoneCallAgent::query()->where('provider', 'twilio');

        if ($identifier = $this->option('agent')) {
            return $query
                ->where(fn ($q) => $q->where('uuid', $identifier)->orWhere('phone_number', $identifier))
                ->first();
        }

        return $query->latest()->first();
    }

    private function resolveHost(): string
    {
        $host = (string) ($this->option('host') ?: parse_url((string) config('app.url'), PHP_URL_HOST));

        return preg_replace('#^https?://#', '', $host);
    }
}
