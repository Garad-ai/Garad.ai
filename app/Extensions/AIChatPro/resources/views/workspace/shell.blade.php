<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Garad Chat Pro') · Garad AI</title>
    <script>
        (function(){
            try {
                var dark = localStorage.getItem('lqdDarkMode') === 'true' || localStorage.getItem('tablerTheme') === 'dark';
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            } catch(e) {}
        })();
    </script>
    <style>
        :root{--g-bg:#fff;--g-sidebar:#f7f7f8;--g-surface:#fff;--g-soft:#f1f1f2;--g-soft2:#e8e8ea;--g-text:#171717;--g-muted:#6f6f73;--g-border:#e5e5e7;--g-accent:#171717;--g-accentText:#fff;--g-danger:#d92d20;--g-green:#0f9f6e;--sidebar:282px}
        html.dark{--g-bg:#111;--g-sidebar:#171717;--g-surface:#1d1d1d;--g-soft:#242424;--g-soft2:#2d2d2d;--g-text:#f5f5f5;--g-muted:#a5a5a8;--g-border:#303033;--g-accent:#f5f5f5;--g-accentText:#111;--g-danger:#ff6b6b;--g-green:#43c995}
        *{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--g-bg);color:var(--g-text);font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}body{font-size:14px}.gshell{min-height:100vh}.gside{position:fixed;inset:0 auto 0 0;width:var(--sidebar);background:var(--g-sidebar);display:flex;flex-direction:column;padding:10px 8px 8px;z-index:40;overflow:hidden}.gbrand{display:flex;align-items:center;justify-content:space-between;height:38px;padding:0 8px 0 10px;font-weight:700;font-size:15px}.gbrand a{color:var(--g-text);text-decoration:none}.giconbtn{width:32px;height:32px;border:0;background:transparent;color:var(--g-muted);border-radius:8px;display:grid;place-items:center;cursor:pointer}.giconbtn:hover{background:var(--g-soft);color:var(--g-text)}.giconbtn svg,.gnav svg{transition:transform .18s ease}.giconbtn:hover svg,.gnav a:hover svg{transform:translateX(1px) scale(1.07)}.giconbtn svg,.gnav svg{width:18px;height:18px}.gnav{display:flex;flex-direction:column;gap:2px;margin-top:7px}.gnav a{min-height:38px;padding:0 10px;border-radius:9px;display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--g-text);font-size:13px;font-weight:520}.gnav a:hover,.gnav a.active{background:var(--g-soft2)}.gside-search{position:relative;margin:10px 8px 0}.gside-search input{width:100%;height:34px;border:0;background:var(--g-soft);color:var(--g-text);border-radius:9px;padding:0 10px 0 32px;font:inherit;font-size:12px;outline:none}.gside-search svg{position:absolute;left:9px;top:9px;width:16px;height:16px;color:var(--g-muted)}.gsection{margin:20px 10px 7px;color:var(--g-muted);font-size:11px;font-weight:650}.ghistory{min-height:0;overflow:auto;padding:0 2px 12px;scrollbar-width:thin}.ghistory a{display:block;min-height:34px;padding:8px 10px;border-radius:9px;color:var(--g-text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12.5px}.ghistory a:hover{background:var(--g-soft)}.gprojectlink{display:flex!important;align-items:center;gap:8px}.gprojectlink svg{width:15px;height:15px;flex:none}.gprofile{margin-top:auto;padding-top:6px}.gprofile-row{display:flex;align-items:center;gap:9px;min-height:46px;padding:6px 9px;border-radius:10px}.gprofile-row:hover{background:var(--g-soft)}.gavatar{width:29px;height:29px;border-radius:50%;object-fit:cover;background:var(--g-soft2)}.gprofile-copy{min-width:0;flex:1}.gprofile-name{font-size:12px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gprofile-meta{font-size:10px;color:var(--g-muted);margin-top:1px}.gprofile-actions{display:flex;gap:2px}.gmain{margin-left:var(--sidebar);min-height:100vh;background:var(--g-bg)}.gmain-inner{width:min(1080px,calc(100vw - var(--sidebar) - 48px));margin:0 auto;padding:48px 0 72px}.gpage-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:30px}.gpage-head h1{margin:0;font-size:30px;line-height:1.15;letter-spacing:-.035em;font-weight:680}.gpage-head p{margin:8px 0 0;color:var(--g-muted);font-size:13.5px;line-height:1.55}.gactions{display:flex;align-items:center;gap:8px}.gbtn{height:38px;border:1px solid var(--g-border);background:var(--g-surface);color:var(--g-text);padding:0 14px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;font-size:12.5px;font-weight:620;cursor:pointer}.gbtn:hover{background:var(--g-soft)}.gbtn.primary{background:var(--g-accent);color:var(--g-accentText);border-color:var(--g-accent)}.gbtn.danger{color:var(--g-danger)}.ginput,.gtextarea,.gselect{width:100%;border:1px solid var(--g-border);background:var(--g-surface);color:var(--g-text);border-radius:12px;padding:10px 12px;font:inherit;outline:none}.ginput:focus,.gtextarea:focus,.gselect:focus{border-color:#85858a}.gtextarea{min-height:92px;resize:vertical}.gsearch{position:relative}.gsearch input{height:42px;padding-left:38px}.gsearch svg{position:absolute;left:13px;top:12px;width:18px;height:18px;color:var(--g-muted)}.gtabs{display:flex;gap:18px;margin:26px 0 18px}.gtabs button{border:0;background:transparent;color:var(--g-muted);font:inherit;font-weight:620;padding:6px 0;cursor:pointer}.gtabs button.active{color:var(--g-text)}.gcards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.gcard{border:1px solid var(--g-border);background:var(--g-surface);border-radius:18px;padding:16px;min-width:0}.gcard:hover{background:color-mix(in srgb,var(--g-soft) 50%,var(--g-surface))}.gcard-icon{height:144px;border-radius:13px;background:var(--g-soft);display:grid;place-items:center;color:var(--g-muted);overflow:hidden}.gcard-icon img{width:100%;height:100%;object-fit:cover}.gcard h3{font-size:13px;margin:13px 0 5px;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gcard p{margin:0;color:var(--g-muted);font-size:11.5px;line-height:1.45}.gcard-actions{display:flex;gap:7px;margin-top:13px}.gcard-actions .gbtn{height:32px;padding:0 10px;font-size:11px}.glist{display:flex;flex-direction:column}.glist-row{display:grid;grid-template-columns:42px minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--g-border)}.glist-icon{width:38px;height:38px;border-radius:10px;background:var(--g-soft);display:grid;place-items:center;color:var(--g-muted)}.glist-title{font-weight:640;font-size:13px}.glist-sub{font-size:11.5px;color:var(--g-muted);margin-top:3px}.gempty{padding:56px 20px;text-align:center;border:1px dashed var(--g-border);border-radius:18px;color:var(--g-muted)}.gcomposer{display:flex;align-items:center;gap:10px;border:1px solid var(--g-border);background:var(--g-surface);border-radius:18px;padding:9px 10px 9px 14px}.gcomposer input{flex:1;border:0;background:transparent;color:var(--g-text);font:inherit;outline:none;min-width:0}.gcomposer button{width:36px;height:36px;border:0;border-radius:50%;background:var(--g-accent);color:var(--g-accentText);display:grid;place-items:center;cursor:pointer}.gcomposer button svg{width:17px;height:17px}.grecommended{margin-top:28px}.grecommended h2{font-size:13px;margin:0 0 6px}.grec-row{display:grid;grid-template-columns:minmax(0,1fr) 34px;gap:12px;align-items:center;padding:13px 0;border-bottom:1px solid var(--g-border)}.grec-title{font-weight:640;font-size:13px}.grec-sub{font-size:11.5px;color:var(--g-muted);margin-top:4px}.grec-add{width:32px;height:32px;border:0;border-radius:8px;background:transparent;color:var(--g-text);font-size:22px;cursor:pointer}.grec-add:hover{background:var(--g-soft)}.gform-panel{border:1px solid var(--g-border);border-radius:18px;padding:18px;background:var(--g-surface)}.gfield{display:block;margin-bottom:12px}.gfield>span{display:block;margin-bottom:6px;font-size:11.5px;font-weight:620}.gsplit{display:grid;grid-template-columns:330px minmax(0,1fr);gap:20px}.gstatus{display:inline-flex;padding:4px 8px;border-radius:999px;background:var(--g-soft);font-size:10.5px;color:var(--g-muted)}.gstatus.active{color:var(--g-green)}.gcode-grid{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:520px;border:1px solid var(--g-border);border-radius:18px;overflow:hidden}.gcode-side{border-right:1px solid var(--g-border);background:var(--g-sidebar);padding:14px}.gcode-main{padding:28px;display:flex;flex-direction:column;justify-content:center}.gcode-main h2{font-size:24px;margin:0 0 8px;letter-spacing:-.025em}.gcode-main p{color:var(--g-muted);line-height:1.6;margin:0 0 22px}.gcode-links{display:grid;gap:8px}.gcode-link{padding:10px 11px;border-radius:10px;color:var(--g-text);text-decoration:none;font-size:12px}.gcode-link:hover{background:var(--g-soft)}.mobile-top{display:none}.hide{display:none!important}
        @media(max-width:920px){:root{--sidebar:0px}.gside{transform:translateX(-100%);width:min(86vw,310px);transition:.18s}.gside.open{transform:none}.gmain{margin-left:0}.gmain-inner{width:min(100% - 28px,1080px);padding-top:72px}.mobile-top{position:fixed;top:0;left:0;right:0;height:54px;background:var(--g-bg);display:flex;align-items:center;justify-content:space-between;padding:0 12px;z-index:30;border-bottom:1px solid var(--g-border)}.gcards{grid-template-columns:repeat(2,minmax(0,1fr))}.gsplit,.gcode-grid{grid-template-columns:1fr}.gcode-side{border-right:0;border-bottom:1px solid var(--g-border)}.gpage-head{flex-direction:column}.gactions{width:100%;flex-wrap:wrap}}
        @media(max-width:580px){.gcards{grid-template-columns:1fr}.gmain-inner{width:calc(100% - 22px);padding-bottom:48px}.gpage-head h1{font-size:25px}.glist-row{grid-template-columns:38px minmax(0,1fr)}.glist-row>.gactions{grid-column:2}.gcard-icon{height:128px}}
    </style>
    @stack('styles')
</head>
<body>
@php
    $shellChats = collect();
    $shellProjects = collect();
    try {
        $chatColumns = ['id', 'title', 'slug', 'updated_at'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('user_openai_chats', 'is_pinned')) {
            $chatColumns[] = 'is_pinned';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('user_openai_chats', 'folder_id')) {
            $chatColumns[] = 'folder_id';
        }
        $shellChats = \App\Models\UserOpenaiChat::query()
            ->where('user_id', auth()->id())
            ->where('is_chatbot', 0)
            ->latest('updated_at')
            ->limit(80)
            ->get($chatColumns);
    } catch (\Throwable $e) {}
    try {
        if (class_exists(\App\Extensions\AIChatProFolders\System\Models\AiChatProFolder::class)) {
            $shellProjects = \App\Extensions\AIChatProFolders\System\Models\AiChatProFolder::query()
                ->where('created_by', auth()->id())
                ->latest('updated_at')
                ->limit(24)
                ->get(['id','name']);
        }
    } catch (\Throwable $e) {}
    $shellPinned = $shellChats->filter(fn ($chat) => (bool) ($chat->is_pinned ?? false));
    $shellRegular = $shellChats->filter(fn ($chat) => ! (bool) ($chat->is_pinned ?? false) && empty($chat->folder_id));
    $u = auth()->user();
    $avatar = $u?->avatar ? custom_theme_url($u->avatar, true) : custom_theme_url('/assets/img/auth/default-avatar.png');
@endphp
<div class="gshell">
    <aside class="gside" id="garadShellSidebar">
        <div class="gbrand"><a href="{{ route('dashboard.user.openai.chat.pro.index') }}">Garad AI</a><button class="giconbtn" id="shellClose" aria-label="Close sidebar"><x-tabler-layout-sidebar-left-collapse /></button></div>
        <nav class="gnav">
            <a href="{{ route('dashboard.user.openai.chat.pro.index') }}"><x-tabler-edit /><span>{{ __('New chat') }}</span></a>
            <a href="{{ route('dashboard.user.openai.chat.pro.workspace.library') }}" class="{{ request()->routeIs('dashboard.user.openai.chat.pro.workspace.library') ? 'active' : '' }}"><x-tabler-library /><span>{{ __('Library') }}</span></a>
            <a href="{{ route('dashboard.user.openai.chat.pro.scheduled.index') }}" class="{{ request()->routeIs('dashboard.user.openai.chat.pro.scheduled.*') ? 'active' : '' }}"><x-tabler-clock /><span>{{ __('Scheduled') }}</span></a>
            <a href="{{ route('dashboard.user.openai.chat.pro.workspace.agents') }}" class="{{ request()->routeIs('dashboard.user.openai.chat.pro.workspace.agents') ? 'active' : '' }}"><x-tabler-sparkles /><span>{{ __('Agents') }}</span></a>
            <a href="{{ route('dashboard.user.openai.chat.pro.workspace.code') }}" class="{{ request()->routeIs('dashboard.user.openai.chat.pro.workspace.code') ? 'active' : '' }}"><x-tabler-code /><span>{{ __('Garad Code') }}</span></a>
        </nav>
        <div class="gside-search"><x-tabler-search /><input id="shellChatSearch" placeholder="{{ __('Search chats') }}"></div>
        <div class="ghistory" id="shellHistory">
            @if($shellPinned->isNotEmpty())
                <div class="gsection">{{ __('Pinned') }}</div>
                @foreach($shellPinned as $c)
                    <a data-shell-chat href="{{ route('dashboard.user.openai.chat.pro.index', ['slug' => $c->slug ?: $c->id]) }}">{{ $c->title ?: __('Untitled chat') }}</a>
                @endforeach
            @endif

            @if($shellProjects->isNotEmpty())
                <div class="gsection">{{ __('Projects') }}</div>
                @foreach($shellProjects as $p)
                    <a class="gprojectlink" href="{{ route('dashboard.user.openai.chat.pro.projects.index') }}#project-{{ $p->id }}"><x-tabler-folder /> <span>{{ $p->name }}</span></a>
                @endforeach
            @endif

            @if($shellRegular->isNotEmpty())
                <div class="gsection">{{ __('Chats') }}</div>
                @foreach($shellRegular as $c)
                    <a data-shell-chat href="{{ route('dashboard.user.openai.chat.pro.index', ['slug' => $c->slug ?: $c->id]) }}">{{ $c->title ?: __('Untitled chat') }}</a>
                @endforeach
            @endif
        </div>
        <div class="gprofile">
            <div class="gprofile-row">
                <img class="gavatar" src="{{ $avatar }}" alt="">
                <div class="gprofile-copy"><div class="gprofile-name">{{ $u?->name ?: __('Account') }}</div><div class="gprofile-meta">Garad AI</div></div>
                <div class="gprofile-actions">
                    <button class="giconbtn" id="shellTheme" title="{{ __('Toggle theme') }}"><x-tabler-moon /></button>
                    <a class="giconbtn" href="{{ route('dashboard.user.index') }}" title="{{ __('Dashboard') }}"><x-tabler-layout-dashboard /></a>
                </div>
            </div>
        </div>
    </aside>
    <div class="mobile-top"><button class="giconbtn" id="shellOpen"><x-tabler-menu-2 /></button><strong>Garad AI</strong><a class="giconbtn" href="{{ route('dashboard.user.openai.chat.pro.index') }}"><x-tabler-edit /></a></div>
    <main class="gmain"><div class="gmain-inner">@yield('content')</div></main>
</div>
<script>
(function(){
    var sidebar=document.getElementById('garadShellSidebar');
    document.getElementById('shellOpen')?.addEventListener('click',()=>sidebar?.classList.add('open'));
    document.getElementById('shellClose')?.addEventListener('click',()=>sidebar?.classList.remove('open'));
    document.getElementById('shellChatSearch')?.addEventListener('input',function(){var q=this.value.toLowerCase().trim();document.querySelectorAll('[data-shell-chat]').forEach(function(a){a.style.display=!q||a.textContent.toLowerCase().includes(q)?'block':'none'});});
    document.getElementById('shellTheme')?.addEventListener('click',function(){
        var dark=!document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark',dark);
        document.documentElement.style.colorScheme=dark?'dark':'light';
        try{localStorage.setItem('lqdDarkMode',dark?'true':'false');localStorage.setItem('tablerTheme',dark?'dark':'light')}catch(e){}
    });
    window.garadOpenChatPrompt=function(prompt){
        var u=new URL(@json(route('dashboard.user.openai.chat.pro.index')),window.location.origin);
        if(prompt)u.searchParams.set('prompt',prompt);
        window.location.href=u.toString();
    };
})();
</script>
@stack('scripts')
</body>
</html>
