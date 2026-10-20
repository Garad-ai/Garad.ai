@extends('panel.layout.app', ['layout_wide' => true])
@section('title', __('Projects'))

@push('before-head-close')
<link rel="stylesheet" href="{{ asset('/vendor/garad-chat-pro-2/chat-pro-2.css') }}?v=2.0.0">
@endpush
@section('content')
<div class="garad-projects-page mx-auto w-full max-w-[1180px] px-4 py-6 lg:px-8 lg:py-10">
    @include('ai-chat-pro::includes.garad-primary-nav')
    <div class="mb-8 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
        <div>
            <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-foreground/10 bg-foreground/[0.035] px-3 py-1 text-[11px] font-semibold uppercase tracking-[.14em] text-foreground/55"><x-tabler-folders class="size-4" /> {{ __('Garad Projects') }}</div>
            <h1 class="m-0 text-3xl font-semibold tracking-[-.035em] lg:text-4xl">{{ __('Keep long-running work together') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-foreground/60">{{ __('Projects group related chats and give Garad a stable project description and instructions. Existing Chat Pro folders remain compatible.') }}</p>
        </div>
        <a href="{{ route('dashboard.user.openai.chat.pro.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-foreground/10 px-4 text-sm font-medium hover:bg-foreground/5"><x-tabler-message-circle-plus class="size-4" /> {{ __('New chat') }}</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-[340px_1fr]">
        <section class="rounded-[22px] border border-foreground/10 bg-background p-5 shadow-[0_18px_55px_rgba(15,23,42,.06)] dark:shadow-none">
            <h2 class="mb-4 text-base font-semibold">{{ __('Create project') }}</h2>
            <form method="POST" action="{{ route('dashboard.user.openai.chat.pro.projects.store') }}" class="space-y-4">
                @csrf
                <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Project name') }}</span><input name="name" required class="w-full rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 text-sm" placeholder="{{ __('Arkaan ICT') }}"></label>
                <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Description') }}</span><textarea name="description" rows="3" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 text-sm" placeholder="{{ __('What this project is about') }}"></textarea></label>
                <label class="block"><span class="mb-1.5 block text-xs font-medium">{{ __('Project instructions') }}</span><textarea name="instructions" rows="4" class="w-full rounded-xl border border-foreground/10 bg-transparent px-3.5 py-2.5 text-sm" placeholder="{{ __('How Garad should work in this project') }}"></textarea></label>
                <button class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-heading-foreground px-4 text-sm font-semibold text-background"><x-tabler-folder-plus class="size-4" /> {{ __('Create project') }}</button>
            </form>
        </section>

        <section class="min-w-0">
            <div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-semibold">{{ __('Your projects') }}</h2><span class="rounded-full bg-foreground/5 px-2.5 py-1 text-[11px] text-foreground/50">{{ $projects->count() }}</span></div>
            <div class="grid gap-3 md:grid-cols-2">
                @forelse($projects as $project)
                <article class="group rounded-[20px] border border-foreground/10 bg-background p-5 transition hover:-translate-y-0.5 hover:shadow-[0_18px_45px_rgba(15,23,42,.07)] dark:hover:shadow-none">
                    <div class="flex items-start justify-between gap-3">
                        <div class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"><x-tabler-folder class="size-5" /></div>
                        <form method="POST" action="{{ route('dashboard.user.openai.chat.pro.projects.destroy', $project['id']) }}" onsubmit="return confirm('{{ __('Delete this project? Chats will not be deleted.') }}')">@csrf @method('DELETE')<button class="grid size-8 place-items-center rounded-lg text-foreground/35 opacity-0 transition hover:bg-rose-500/10 hover:text-rose-500 group-hover:opacity-100"><x-tabler-trash class="size-4" /></button></form>
                    </div>
                    <h3 class="mt-4 text-base font-semibold tracking-[-.02em]">{{ $project['name'] }}</h3>
                    <p class="mt-1 line-clamp-2 min-h-[40px] text-xs leading-5 text-foreground/55">{{ $project['description'] ?: __('No project description yet.') }}</p>
                    <div class="mt-4 flex items-center gap-3 text-[11px] text-foreground/45"><span>{{ $project['chat_count'] }} {{ __('chats') }}</span><span>•</span><span>{{ optional($project['updated_at'])->diffForHumans() }}</span></div>
                    @if($project['chats']->isNotEmpty())
                    <div class="mt-4 space-y-1.5 border-t border-foreground/7 pt-3">
                        @foreach($project['chats']->take(3) as $chat)
                            <a href="{{ route('dashboard.user.openai.chat.pro.index', ['slug' => $chat->slug ?? $chat->id]) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-foreground/60 hover:bg-foreground/5 hover:text-heading-foreground"><x-tabler-message class="size-3.5 shrink-0" /><span class="truncate">{{ $chat->title ?: __('Untitled chat') }}</span></a>
                        @endforeach
                    </div>
                    @endif
                </article>
                @empty
                <div class="md:col-span-2 rounded-[20px] border border-dashed border-foreground/15 p-12 text-center"><div class="mx-auto mb-3 grid size-12 place-items-center rounded-2xl bg-foreground/5"><x-tabler-folders class="size-6" /></div><h3 class="text-sm font-semibold">{{ __('No projects yet') }}</h3><p class="mt-1 text-xs text-foreground/50">{{ __('Create a project for work that you return to often.') }}</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
