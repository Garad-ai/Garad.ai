@extends('ai-chat-pro::workspace.shell')
@section('title', __('Agents'))
@section('content')
<div class="gpage-head">
    <div>
        <h1>{{ __('Agents') }}</h1>
        <p>{{ __('Your existing Garad AI agents and workflows. Run them from Chat Pro without leaving the Garad workspace.') }}</p>
    </div>
    <div class="gactions">
        <button class="gbtn primary" onclick="garadOpenChatPrompt('Create a new AI agent that can ')" type="button">
            <x-tabler-plus style="width:16px;height:16px"/> {{ __('New agent') }}
        </button>
    </div>
</div>

@if(!($agentsAvailable ?? false))
    <div class="gempty">{{ __('AI Agents are not available on this installation yet.') }}</div>
@else
    <div class="gcards garad-agent-grid">
        @forelse($agents as $agent)
            <article class="gcard garad-agent-card">
                <div class="garad-agent-card-top">
                    <div class="garad-agent-avatar">
                        @if(!empty($agent['avatar_url']))
                            <img src="{{ $agent['avatar_url'] }}" alt="{{ $agent['name'] }}" onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                            <span class="garad-agent-avatar-fallback" style="display:none"><x-tabler-sparkles style="width:24px;height:24px"/></span>
                        @else
                            <span class="garad-agent-avatar-fallback"><x-tabler-sparkles style="width:24px;height:24px"/></span>
                        @endif
                    </div>
                    <span class="gstatus {{ $agent['status']==='active'?'active':'' }}">{{ ucfirst($agent['status']) }}</span>
                </div>

                <h3>{{ $agent['name'] }}</h3>
                <p>{{ $agent['description'] !== '' ? $agent['description'] : __('No description yet.') }}</p>

                <div class="garad-agent-meta">
                    <span><x-tabler-list-details style="width:14px;height:14px"/> {{ $agent['steps_count'] }} {{ __('steps') }}</span>
                    <span><x-tabler-bolt style="width:14px;height:14px"/> {{ ucfirst($agent['trigger_type']) }}</span>
                    @if($agent['runs_count'] !== null)
                        <span><x-tabler-history style="width:14px;height:14px"/> {{ $agent['runs_count'] }} {{ __('runs') }}</span>
                    @endif
                </div>

                <div class="gcard-actions">
                    <button
                        class="gbtn primary"
                        type="button"
                        onclick="garadOpenChatPrompt({{ json_encode('Run AI agent workflow #'.$agent['id'].' - '.$agent['name'].' for this task: ') }})"
                    >
                        <x-tabler-player-play style="width:15px;height:15px"/> {{ __('Run in chat') }}
                    </button>
                </div>
            </article>
        @empty
            <div class="gempty" style="grid-column:1/-1">
                {{ __('No AI agents yet. Create one in Chat Pro and it will appear here automatically.') }}
            </div>
        @endforelse
    </div>
@endif
@endsection

@push('styles')
<style>
.garad-agent-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
.garad-agent-card{display:flex;min-height:260px;flex-direction:column}
.garad-agent-card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:13px}
.garad-agent-avatar{width:48px;height:48px;border-radius:13px;overflow:hidden;background:var(--g-soft);display:grid;place-items:center;color:var(--g-muted)}
.garad-agent-avatar img{width:100%;height:100%;object-fit:cover}.garad-agent-avatar-fallback{width:100%;height:100%;place-items:center}
.garad-agent-meta{display:flex;flex-wrap:wrap;gap:8px 12px;margin-top:13px;color:var(--g-muted);font-size:10.5px}
.garad-agent-meta span{display:inline-flex;align-items:center;gap:4px}.garad-agent-card .gcard-actions{margin-top:auto;padding-top:16px}
@media(max-width:900px){.garad-agent-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.garad-agent-grid{grid-template-columns:1fr}}
</style>
@endpush
