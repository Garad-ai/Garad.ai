@extends('ai-chat-pro::workspace.shell')
@section('title', __('Scheduled'))
@section('content')
<div class="gpage-head">
    <div><h1>{{ __('Scheduled') }}</h1><p>{{ __('Ask Garad to schedule tasks, set reminders, or monitor for updates.') }}</p></div>
    <div class="gactions"><span class="gstatus active">{{ __('Active') }}</span></div>
</div>
<form class="gcomposer" id="scheduleQuickForm">
    <x-tabler-plus style="width:19px;height:19px;color:var(--g-muted)" />
    <input id="scheduleQuick" placeholder="{{ __('Schedule a task') }}" autocomplete="off">
    <button aria-label="{{ __('Continue in Chat Pro') }}"><x-tabler-arrow-up /></button>
</form>

@if(!empty($selectedRun))
<div class="gform-panel" style="margin-top:22px"><div style="display:flex;justify-content:space-between;gap:12px"><div><strong>{{ $selectedRun['title'] }}</strong><div class="glist-sub">{{ ucfirst($selectedRun['status']) }} @if($selectedRun['completed_at']) · {{ $selectedRun['completed_at']->diffForHumans() }} @endif</div></div><a class="gbtn" href="{{ route('dashboard.user.openai.chat.pro.scheduled.index') }}">{{ __('Close') }}</a></div><div style="white-space:pre-wrap;margin-top:14px;line-height:1.6;color:var(--g-muted)">{{ $selectedRun['error'] ? __('This run failed. Review the server log if it repeats.') : ($selectedRun['result'] ?: __('The task completed without a text result.')) }}</div></div>
@endif

<section style="margin-top:30px">
    @forelse($tasks as $task)
        @php($active=$task['status']==='active')
        <div class="glist-row">
            <div class="glist-icon">@if($task['task_type']==='reminder')<x-tabler-bell />@elseif($task['task_type']==='monitor')<x-tabler-radar />@else<x-tabler-sparkles />@endif</div>
            <div><div class="glist-title">{{ $task['title'] }}</div><div class="glist-sub">{{ ucfirst(str_replace('_',' ',$task['task_type'])) }} · {{ $task['next_run_at'] ? __('Next').' '.\Carbon\Carbon::parse($task['next_run_at'])->diffForHumans() : __('Not scheduled') }}</div><div class="glist-sub" style="margin-top:6px">{{ \Illuminate\Support\Str::limit($task['instructions'],150) }}</div></div>
            <div class="gactions">
                @if($task['last_run_id'])<a class="gbtn" href="{{ route('dashboard.user.openai.chat.pro.scheduled.index',['run'=>$task['last_run_id']]) }}">{{ __('Result') }}</a>@endif
                @if($task['status']!=='archived')<form method="POST" action="{{ route('dashboard.user.openai.chat.pro.scheduled.status',$task['id']) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $active?'paused':'active' }}"><button class="gbtn">{{ $active?__('Pause'):__('Resume') }}</button></form>@endif
                <form method="POST" action="{{ route('dashboard.user.openai.chat.pro.scheduled.destroy',$task['id']) }}" onsubmit="return confirm('{{ __('Delete this task?') }}')">@csrf @method('DELETE')<button class="gbtn danger">{{ __('Delete') }}</button></form>
            </div>
        </div>
    @empty
        <div class="gempty">{{ __('No scheduled tasks yet. Use the box above and tell Garad what you want it to do and when.') }}</div>
    @endforelse
</section>

<div class="grecommended">
    <h2>{{ __('Recommended') }}</h2>
    @foreach([
        [__('Daily brief'),__('Get a personalized daily briefing with the topics you care about.'),'Every morning, send me a brief with important updates about my work and AI.'],
        [__('AI news monitor'),__('Monitor important AI releases and tell me only when something significant changes.'),'Monitor major AI and technology updates and notify me when there is an important development.'],
        [__('Weekly ICT review'),__('Review my ICT priorities every week before tasks become overdue.'),'Every week, remind me to review my ICT priorities and pending work.'],
        [__('Garad Code watch'),__('Track major Cursor, Claude Code, and coding-agent changes that can improve Garad Code.'),'Monitor important AI coding assistant updates that should influence Garad Code.']
    ] as $rec)
    <div class="grec-row"><div><div class="grec-title">{{ $rec[0] }}</div><div class="grec-sub">{{ $rec[1] }}</div></div><button class="grec-add" data-rec="{{ $rec[2] }}">+</button></div>
    @endforeach
</div>

<details style="margin-top:30px"><summary style="cursor:pointer;font-weight:650;font-size:13px">{{ __('Manual schedule options') }}</summary>
<div class="gform-panel" style="margin-top:12px;max-width:540px">
<form method="POST" action="{{ route('dashboard.user.openai.chat.pro.scheduled.store') }}" id="manualSchedule">@csrf
<label class="gfield"><span>{{ __('Title') }}</span><input class="ginput" name="title" required placeholder="Daily AI news briefing"></label>
<label class="gfield"><span>{{ __('Instructions') }}</span><textarea class="gtextarea" name="instructions" required placeholder="{{ __('What should Garad remind you about or do?') }}"></textarea></label>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px"><label class="gfield"><span>{{ __('Type') }}</span><select class="gselect" name="task_type"><option value="reminder">{{ __('Reminder') }}</option><option value="agent_task">{{ __('AI task') }}</option><option value="monitor">{{ __('Monitor') }}</option></select></label><label class="gfield"><span>{{ __('Schedule') }}</span><select class="gselect" id="manualScheduleType" name="schedule_type"><option value="one_time">{{ __('One time') }}</option><option value="recurring">{{ __('Recurring') }}</option></select></label></div>
<label class="gfield" id="manualRunAt"><span>{{ __('Run at') }}</span><input class="ginput" name="run_at" type="datetime-local"></label>
<label class="gfield hide" id="manualCron"><span>{{ __('Cron') }}</span><input class="ginput" name="cron" placeholder="0 8 * * *"></label>
<input type="hidden" name="timezone" value="{{ config('app.timezone','UTC') }}"><button class="gbtn primary" style="width:100%">{{ __('Schedule task') }}</button>
</form></div></details>
@endsection
@push('scripts')
<script>
(function(){
 const f=document.getElementById('scheduleQuickForm'), q=document.getElementById('scheduleQuick');
 f?.addEventListener('submit',e=>{e.preventDefault();if(q.value.trim())garadOpenChatPrompt('Schedule this task: '+q.value.trim())});
 document.querySelectorAll('[data-rec]').forEach(b=>b.addEventListener('click',()=>garadOpenChatPrompt('Schedule this task: '+b.dataset.rec)));
 const s=document.getElementById('manualScheduleType'),r=document.getElementById('manualRunAt'),c=document.getElementById('manualCron');function sync(){if(!s)return;r.classList.toggle('hide',s.value!=='one_time');c.classList.toggle('hide',s.value!=='recurring')}s?.addEventListener('change',sync);sync();
})();
</script>
@endpush
