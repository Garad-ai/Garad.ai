@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Agents'))
@section('titlebar_subtitle', __('Automate tasks across channels with triggers and actions.'))
@section('titlebar_actions')
    <x-button
        variant="primary"
        x-data="{}"
        @click.prevent="Alpine.$data(document.querySelector('#ai-agent-templates-modal')).modalOpen = true"
    >
        <x-tabler-plus class="size-4" />
        @lang('New Agent')
    </x-button>
@endsection

@section('content')
    @if ($workflows->isEmpty())
        <x-empty-state
            class="py-10"
            icon="tabler-circuit-diode"
            :title="__('No agents yet')"
            :description="__('Create your first agent to start automating.')"
        />
    @else
        <div class="py-10">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($workflows as $workflow)
                    @include('ai-agent::includes.agent-card', ['workflow' => $workflow])
                @endforeach
            </div>

            <div class="mt-6">
                {{ $workflows->links() }}
            </div>
        </div>
    @endif

    @include('ai-agent::includes.templates-modal')
    @include('ai-agent::includes.logs-modal')
@endsection
