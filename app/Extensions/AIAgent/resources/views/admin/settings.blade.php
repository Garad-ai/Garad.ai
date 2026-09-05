@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('AI Agent Settings'))
@section('titlebar_actions', '')
@section('titlebar_subtitle', __('Configure AI Agent settings'))

@section('settings')
    <form
        method="post"
        action="{{ route('dashboard.admin.ai-agent.settings.update') }}"
        id="settings_form"
        enctype="multipart/form-data"
    >
        @csrf
        <h3 class="mb-[25px] text-[20px]">{{ __('Copilot Settings') }}</h3>
        <div class="row">
            <x-card
                class="mb-3 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Default Copilot Model') }}</label>
                        <select
                            class="form-select"
                            id="ai-agent-copilot-model"
                            name="ai-agent-copilot-model"
                        >
                            @foreach ($copilotModels as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected($currentCopilotModel === $value)
                                >{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-alert class="mt-2">
                            <p>
                                {{ __('Select the default AI model used by the Copilot assistant for all workflows.') }}
                            </p>
                        </x-alert>
                    </div>
                </div>
            </x-card>
        </div>
        <button
            class="btn btn-primary w-full"
            type="submit"
        >
            {{ __('Save') }}
        </button>
    </form>
@endsection

@push('script')
    <script src="{{ custom_theme_url('/assets/js/panel/settings.js') }}"></script>
@endpush
