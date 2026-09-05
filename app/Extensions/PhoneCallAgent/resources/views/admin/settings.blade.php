@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Phone Call Agent Settings'))
@section('titlebar_subtitle', __('Configure global phone call agent settings.'))

@section('settings')
    <form
        method="post"
        enctype="multipart/form-data"
        action="{{ route('dashboard.phone-call-agent.admin.settings.update') }}"
    >
        @csrf
        <x-card class="mb-2 max-md:text-center">
            <div
                class="flex flex-col gap-5"
                x-data="{ provider: '{{ setting('phone_call_agent_provider', '') }}' }"
            >
                <div class="form-control border-none !p-0">
                    <label class="form-label">{{ __('Phone Call Provider') }}</label>
                    <select
                        class="form-control"
                        name="phone_call_agent_provider"
                        x-model="provider"
                    >
                        <option value="">@lang('Select Provider')</option>
                        <option value="elevenlabs">@lang('ElevenLabs')</option>
                        <option value="twilio">@lang('Twilio')</option>
                    </select>

                    <div
                        class="mt-3"
                        x-show="provider === 'twilio'"
                        x-cloak
                        x-transition
                    >
                        <p class="text-xs opacity-60">
                            @lang('Twilio numbers and credentials are configured per agent in the agent\'s Phone Numbers step — each user connects their own Twilio number. No global credentials needed.')
                        </p>
                    </div>

                    <div
                        class="mt-3"
                        x-show="provider === 'elevenlabs'"
                        x-cloak
                        x-transition
                    >
                        <p class="mb-2 text-xs opacity-60">
                            @lang('ElevenLabs API key is configured in the main AI settings. Phone numbers are managed per-agent.')
                        </p>
                    </div>
                </div>

                <div
                    x-show="provider === 'elevenlabs'"
                    x-cloak
                    x-transition
                >
                    <label class="form-label">{{ __('ElevenLabs Inbound Webhook URL') }}</label>
                    <div class="flex items-center gap-2">
                        <code class="grow truncate rounded bg-heading-foreground/5 px-3 py-2 text-xs">
                            {{ url('/api/phone-call-agent/webhook/elevenlabs/inbound') }}
                        </code>
                        <x-button
                            class="shrink-0 text-xs"
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="navigator.clipboard.writeText('{{ url('/api/phone-call-agent/webhook/elevenlabs/inbound') }}'); toastr.success('{{ __('Copied!') }}')"
                        >
                            <x-tabler-copy class="size-3.5" />
                        </x-button>
                    </div>
                    <p class="mb-4 mt-1 text-xs opacity-60">
                        {{ __('Set this once in ElevenLabs → Developers → Webhooks as the post-call webhook. Shared across all agents.') }}
                    </p>

                    <label class="form-label">{{ __('ElevenLabs Webhook Secret') }}</label>
                    <input
                        class="form-control"
                        type="password"
                        name="elevenlabs_webhook_secret"
                        autocomplete="off"
                        placeholder="{{ __('Paste webhook signing secret') }}"
                        value="{{ setting('elevenlabs_webhook_secret', '') }}"
                    >
                    <p class="mt-1 text-xs opacity-60">
                        {{ __('Found in ElevenLabs → Developers → Webhooks → your endpoint → Signing Secret. Leave blank to skip signature verification.') }}
                    </p>
                </div>
            </div>
        </x-card>
        <button class="btn btn-primary w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection
