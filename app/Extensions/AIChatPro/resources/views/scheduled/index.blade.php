@extends('panel.layout.app', ['layout_wide' => true])

@section('title', __('Scheduled'))

@push('before-head-close')
<link rel="stylesheet" href="{{ asset('/vendor/garad-chat-pro-2/chat-pro-2.css') }}?v=2.0.0">
@endpush

@section('content')
<div class="garad-scheduled-page mx-auto w-full max-w-[1180px] px-4 py-6 lg:px-8 lg:py-10">
    @include('ai-chat-pro::includes.garad-primary-nav')
    <div class="mb-8 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-foreground/10 bg-foreground/[0.035] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-foreground/55">
                <x-tabler-clock-bolt class="size-4" /> {{ __('Garad Scheduled') }}
            </div>
            <h1 class="m-0 text-3xl font-semibold tracking-[-0.035em] text-heading-foreground lg:text-4xl">{{ __('Reminders and AI tasks') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-foreground/60">{{ __('Create one-time reminders, recurring AI work, or monitoring tasks. Garad runs them in the background and notifies you with the result.') }}</p>
        </div>
        <a href="{{ route('dashboard.user.openai.chat.pro.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-foreground/10 px-4 text-sm font-medium text-heading-foreground transition hover:bg-foreground/5">
            <x-tabler-message-circle-plus class="size-4" /> {{ __('Create from Chat Pro') }}
        </a>
    </div>

    @if(!empty($selectedRun))
        <section class="mb-6 rounded-[20px] border border-primary/20 bg-primary/[0.035] p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="mb-1 text-[11px] font-semibold uppercase tracking-[.12em] text-primary">{{ __('Latest task result') }}</div>
                    <h2 class="m-0 text-base font-semibold">{{ $selectedRun['title'] }}</h2>
                    <div class="mt-1 text-[11px] text-foreground/45">{{ ucfirst($selectedRun['status']) }} @if($selectedRun['completed_at']) • {{ $selectedRun['completed_at']->diffForHumans() }} @endif</div>
                </div>
                <a href="{{ route('dashboard.user.openai.chat.pro.scheduled.index') }}" class="grid size-8 shrink-0 place-items-center rounded-lg border border-foreground/10 hover:bg-foreground/5" aria-label="{{ __('Close result') }}"><x-tabler-x class="size-4" /></a>
            </div>
            @if($selectedRun['error'])
                <div class="mt-4 rounded-xl bg-rose-500/10 p-3 text-xs leading-5 text-rose-600">{{ __('This run failed. Review the agent/server logs if the issue repeats.') }}</div>
            @elseif($selectedRun['result'])
                <div class="mt-4 whitespace-pre-wrap rounded-xl border border-foreground/8 bg-background p-4 text-sm leading-6 text-foreground/75">{{ $selectedRun['result'] }}</div>
            @else
                <div class="mt-4 text-sm text-foreground/55">{{ __('The task completed without a text result.') }}</div>
            @endif
        </section>
    @endif

    <div class="grid gap-5 lg:grid-cols-[360px_1fr]">
        <section class="rounded-[22px] border border-foreground/10 bg-background p-5 shadow-[0_18px_55px_rgba(15,23,42,.06)] dark:shadow-none">
            <h2 class="mb-1 text-base font-semibold">{{ __('New scheduled task') }}</h2>
            <p class="mb-5 text-xs leading-5 text-foreground/55">{{ __('For natural-language scheduling, simply ask Garad in Chat Pro. This form is the manual option.') }}</p>
            <form method="POST" action="{{ route('dashboard.user.openai.chat.pro.scheduled.store') }}" class="space-y-4" x-data="{ scheduleType: 'one_time', taskType: 'reminder' }">
                @csrf
                <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Title') }}</span><input name="title" required maxlength="255" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary" placeholder="{{ __('Daily AI news briefing') }}"></label>
                <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Instructions') }}</span><textarea name="instructions" required rows="4" class="w-full resize-y rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-primary" placeholder="{{ __('Tell Garad what to remind you about or what work to perform.') }}"></textarea></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Type') }}</span><select name="task_type" x-model="taskType" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3 py-2.5 text-sm"><option value="reminder">{{ __('Reminder') }}</option><option value="agent_task">{{ __('AI task') }}</option><option value="monitor">{{ __('Monitor') }}</option></select></label>
                    <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Schedule') }}</span><select name="schedule_type" x-model="scheduleType" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3 py-2.5 text-sm"><option value="one_time">{{ __('One time') }}</option><option value="recurring">{{ __('Recurring') }}</option></select></label>
                </div>
                <label class="block" x-show="scheduleType === 'one_time'"><span class="mb-1.5 block text-xs font-medium">{{ __('Run at') }}</span><input name="run_at" type="datetime-local" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 text-sm"></label>
                <label class="block" x-show="scheduleType === 'recurring'"><span class="mb-1.5 block text-xs font-medium">{{ __('Cron schedule') }}</span><input name="cron" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 font-mono text-sm" placeholder="0 8 * * *"><span class="mt-1.5 block text-[11px] text-foreground/45">{{ __('Example: 0 8 * * * = every day at 08:00') }}</span></label>
                <input type="hidden" name="timezone" value="{{ config('app.timezone', 'UTC') }}">
                <label x-show="taskType !== 'reminder'" class="flex items-center gap-2 text-xs"><input type="checkbox" name="web_search" value="1" class="rounded border-foreground/20 text-primary focus:ring-primary"> {{ __('Allow web search when the task runs') }}</label>
                <button class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-heading-foreground px-4 text-sm font-semibold text-background transition hover:opacity-90"><x-tabler-calendar-plus class="size-4" /> {{ __('Schedule task') }}</button>
            </form>
        </section>

        <section class="min-w-0">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-semibold">{{ __('Your scheduled tasks') }}</h2><span class="rounded-full bg-foreground/5 px-2.5 py-1 text-[11px] text-foreground/50">{{ count($tasks) }} {{ __('total') }}</span></div>
            <div class="space-y-3">
                @forelse($tasks as $task)
                    @php
                        $typeLabel = match($task['task_type']) { 'reminder' => __('Reminder'), 'monitor' => __('Monitor'), default => __('AI task') };
                        $active = $task['status'] === 'active';
                    @endphp
                    <article class="rounded-[18px] border border-foreground/10 bg-background p-4 shadow-[0_10px_40px_rgba(15,23,42,.04)] dark:shadow-none">
                        <div class="flex gap-3.5">
                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-foreground/[0.055] text-heading-foreground">
                                @if($task['task_type'] === 'reminder') <x-tabler-bell class="size-5" /> @elseif($task['task_type'] === 'monitor') <x-tabler-radar class="size-5" /> @else <x-tabler-sparkles class="size-5" /> @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div><h3 class="m-0 truncate text-sm font-semibold">{{ $task['title'] }}</h3><div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-foreground/50"><span>{{ $typeLabel }}</span><span>•</span><span class="{{ $active ? 'text-emerald-600' : 'text-amber-600' }}">{{ ucfirst($task['status']) }}</span></div></div>
                                    <div class="flex items-center gap-1.5">
                                        @if($task['status'] !== 'archived')
                                            <form method="POST" action="{{ route('dashboard.user.openai.chat.pro.scheduled.status', $task['id']) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $active ? 'paused' : 'active' }}"><button title="{{ $active ? __('Pause') : __('Resume') }}" class="grid size-8 place-items-center rounded-lg border border-foreground/10 hover:bg-foreground/5">@if($active)<x-tabler-player-pause class="size-4" />@else<x-tabler-player-play class="size-4" />@endif</button></form>
                                        @else
                                            <span title="{{ __('Completed') }}" class="grid size-8 place-items-center rounded-lg border border-emerald-500/20 bg-emerald-500/5 text-emerald-600"><x-tabler-check class="size-4" /></span>
                                        @endif
                                        <form method="POST" action="{{ route('dashboard.user.openai.chat.pro.scheduled.destroy', $task['id']) }}" onsubmit="return confirm('{{ __('Delete this task?') }}')">@csrf @method('DELETE')<button title="{{ __('Delete') }}" class="grid size-8 place-items-center rounded-lg border border-foreground/10 text-rose-500 hover:bg-rose-500/10"><x-tabler-trash class="size-4" /></button></form>
                                    </div>
                                </div>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-foreground/60">{{ $task['instructions'] }}</p>
                                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-foreground/7 pt-3 text-[11px] text-foreground/45"><span><strong class="font-medium text-foreground/65">{{ __('Next') }}:</strong> {{ $task['next_run_at'] ? \Carbon\Carbon::parse($task['next_run_at'])->diffForHumans() : __('Not scheduled') }}</span>@if($task['last_run_at'])<span><strong class="font-medium text-foreground/65">{{ __('Last') }}:</strong> {{ \Carbon\Carbon::parse($task['last_run_at'])->diffForHumans() }}</span>@endif @if($task['last_run_id'])<a class="ms-auto font-medium text-primary hover:underline" href="{{ route('dashboard.user.openai.chat.pro.scheduled.index', ['run' => $task['last_run_id']]) }}">{{ __('View result') }}</a>@endif</div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-[20px] border border-dashed border-foreground/15 p-12 text-center"><div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-foreground/5"><x-tabler-calendar-bolt class="size-6" /></div><h3 class="text-sm font-semibold">{{ __('Nothing scheduled yet') }}</h3><p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-foreground/50">{{ __('Try: “Remind me tomorrow at 9 AM to review the ICT report” or “Every morning research important AI news and send me a summary.”') }}</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
