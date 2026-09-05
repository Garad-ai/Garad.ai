@php
    use App\Domains\Entity\Enums\EntityEnum;

    $theme = setting('dash_theme', 'default');
    $isOtherCategories = isset($category) && in_array($category?->slug, ['ai_vision', 'ai_pdf', 'ai_chat_image']);
    $disable_actions = $app_is_demo && $isOtherCategories;
    $show_mobile_nav_header = (bool) setting('ai_chat_pro_show_mobile_nav_header', '1');
    $show_mobile_bottom_bar = (bool) setting('ai_chat_pro_show_mobile_bottom_bar', '0');

    // Chat Pro sidebar profile image only. Keep the account/profile logic untouched.
    // Use the exact avatar path saved on the user profile so changing the profile image
    // is reflected here after refresh. New users keep the existing default avatar.
    $chatSidebarUser = auth()->user();
    $chatSidebarAvatar = trim((string) ($chatSidebarUser?->avatar ?? ''));

    if ($chatSidebarAvatar === '') {
        $chatSidebarAvatarUrl = custom_theme_url('/assets/img/auth/default-avatar.png');
    } elseif (filter_var($chatSidebarAvatar, FILTER_VALIDATE_URL)) {
        $chatSidebarAvatarUrl = $chatSidebarAvatar;
    } else {
        $chatSidebarAvatarPath = $chatSidebarAvatar;

        if (str_starts_with($chatSidebarAvatarPath, 'upload') || str_starts_with($chatSidebarAvatarPath, 'assets')) {
            $chatSidebarAvatarPath = '/' . ltrim($chatSidebarAvatarPath, '/');
        }

        $chatSidebarAvatarUrl = url(custom_theme_url($chatSidebarAvatarPath));
    }

    $user_is_premium = false;
    $plan = $chatSidebarUser?->relationPlan;

    // Show the active relation plan name; users without a plan are shown as Free.
    $chatSidebarPlanName = trim((string) ($plan?->name ?? ''));
    if ($chatSidebarPlanName === '') {
        $chatSidebarPlanName = __('Free');
    }

    $chatSidebarPlanKey = strtolower($chatSidebarPlanName);
    $chatSidebarPlanColor = '#64748b';
    $chatSidebarPlanBg = 'rgba(100, 116, 139, 0.12)';
    $chatSidebarPlanBorder = 'rgba(100, 116, 139, 0.26)';

    if (str_contains($chatSidebarPlanKey, 'enterprise')) {
        $chatSidebarPlanColor = '#d97706';
        $chatSidebarPlanBg = 'rgba(245, 158, 11, 0.12)';
        $chatSidebarPlanBorder = 'rgba(245, 158, 11, 0.28)';
    } elseif (str_contains($chatSidebarPlanKey, 'business')) {
        $chatSidebarPlanColor = '#059669';
        $chatSidebarPlanBg = 'rgba(16, 185, 129, 0.12)';
        $chatSidebarPlanBorder = 'rgba(16, 185, 129, 0.28)';
    } elseif (str_contains($chatSidebarPlanKey, 'professional') || str_contains($chatSidebarPlanKey, 'pro')) {
        $chatSidebarPlanColor = '#7c3aed';
        $chatSidebarPlanBg = 'rgba(124, 58, 237, 0.12)';
        $chatSidebarPlanBorder = 'rgba(124, 58, 237, 0.28)';
    } elseif (str_contains($chatSidebarPlanKey, 'standard') || str_contains($chatSidebarPlanKey, 'plus')) {
        $chatSidebarPlanColor = '#2563eb';
        $chatSidebarPlanBg = 'rgba(37, 99, 235, 0.12)';
        $chatSidebarPlanBorder = 'rgba(37, 99, 235, 0.28)';
    } elseif (str_contains($chatSidebarPlanKey, 'basic') || str_contains($chatSidebarPlanKey, 'free')) {
        $chatSidebarPlanColor = '#64748b';
        $chatSidebarPlanBg = 'rgba(100, 116, 139, 0.12)';
        $chatSidebarPlanBorder = 'rgba(100, 116, 139, 0.26)';
    }

    if ($plan) {
        $planType = strtolower($plan->plan_type ?? 'all');
        if ($plan->plan_type === 'all' || $plan->plan_type === 'premium') {
            $user_is_premium = true;
        }
    }

    // $premium_features = \App\Models\OpenAIGenerator::query()->where('active', 1)->where('premium', 1)->get()->pluck('title')->toArray();
    $premium_features = [
        ['title' => 'Chat History', 'is_pro' => false],
        ['title' => 'Chat with Document', 'is_pro' => false],
        ['title' => 'Unlimited Credits', 'is_pro' => true],
        ['title' => 'Chatbot Training', 'is_pro' => true],
        ['title' => 'Voice Chat', 'is_pro' => true],
        ['title' => 'Access to All AI Tools', 'is_pro' => true],
    ];
    $ad_enabled = adsense('chat-pro-top-header-section-728x90');

    $suggestion_style = setting('ai_chat_pro_suggestion_style', 'pill');

    $typographyDefaults = [
        'body' => [
    'fontSize' => ['value' => 16, 'unit' => 'px'],
    'lineHeight' => ['value' => 1.85, 'unit' => 'em']
],
        'h1' => [
    'fontSize' => ['value' => 34, 'unit' => 'px'],
    'lineHeight' => ['value' => 1.2, 'unit' => 'em']
],

'h2' => [
    'fontSize' => ['value' => 28, 'unit' => 'px'],
    'lineHeight' => ['value' => 1.3, 'unit' => 'em']
],

'h3' => [
    'fontSize' => ['value' => 22, 'unit' => 'px'],
    'lineHeight' => ['value' => 1.4, 'unit' => 'em']
],
        'h4' => ['fontSize' => ['value' => 15, 'unit' => 'px'], 'lineHeight' => ['value' => 1.53, 'unit' => 'em']],
        'h5' => ['fontSize' => ['value' => 14, 'unit' => 'px'], 'lineHeight' => ['value' => 1.43, 'unit' => 'em']],
        'h6' => ['fontSize' => ['value' => 12, 'unit' => 'px'], 'lineHeight' => ['value' => 1.67, 'unit' => 'em']],
    ];
    $savedTypography = json_decode(setting('ai_chat_pro_typography', '{}'), true);
    $typography = array_replace_recursive($typographyDefaults, is_array($savedTypography) ? $savedTypography : []);

    $typoVal = fn($prop) => is_numeric($prop['value'] ?? null) ? $prop['value'] . ($prop['unit'] ?? 'px') : null;

    $typoCss = '';
    foreach (['body' => '', 'h1' => ' h1', 'h2' => ' h2', 'h3' => ' h3', 'h4' => ' h4', 'h5' => ' h5', 'h6' => ' h6'] as $el => $sel) {
        $fs = $typoVal($typography[$el]['fontSize'] ?? []);
        $lh = $typoVal($typography[$el]['lineHeight'] ?? []);
        $rules = ($fs ? "font-size:{$fs};" : '') . ($lh ? "line-height:{$lh};" : '');
        if ($rules) {
            $typoCss .= '.lqd-chat-v2 .chat-content' . $sel . '{' . $rules . '}';
        }
    }
@endphp

@extends('panel.layout.app', [
    'disable_tblr' => true,
    'layout_wide' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
    'body_class' => 'lqd-chat-v2 suggestion-style-' . $suggestion_style,
    'disable_mobile_bottom_menu' => !$show_mobile_bottom_bar,
])

@section('title')
    @switch($category?->slug)
        @case('ai_vision')
            {{ __('Vision AI') }}
        @break

        @case('ai_pdf')
            {{ __('AI File Chat') }}
        @break

        @case('ai_chat_image')
            {{ __('Chat Image') }}
        @break

        @default
            {{ __('AI Chat') }}
    @endswitch
@endsection

@section('titlebar_subtitle')
    @if ($category?->slug == 'ai_vision')
        {{ __('Seamlessly upload any image you want to explore and get insightful conversations.') }}
    @elseif ($category?->slug == 'ai_pdf')
        {{ __('Simply upload a PDF, find specific information. extract key insights or summarize the entire document.') }}
    @elseif ($category?->slug == 'ai_chat_image')
        {{ __('Seamlessly generate and craft a diverse array of images without ever leaving your chat environment.') }}
    @endif
@endsection

@if (filled($typoCss))
    @push('before-head-close')
        <style>
        .chat-content{
    font-size:16px !important;
    line-height:1.85 !important;
    font-weight:400;
    max-width:850px;
}

.chat-content p{
    margin:0 0 24px;
    line-height:inherit;
}

.chat-content h1,
.chat-content h2,
.chat-content h3{
    margin:36px 0 18px;
    line-height:1.3;
    font-weight:700;
}

.chat-content ul,
.chat-content ol{
    margin:20px 0;
    padding-left:28px;
}

.chat-content li{
    margin-bottom:10px;
    line-height:1.8;
}

.chat-content pre{
    margin:24px 0;
    padding:18px;
    border-radius:12px;
}

.chat-content blockquote{
    margin:20px 0;
    padding-left:18px;
}

.chat-content table{
    margin:24px 0;
}

.chat-content td,
.chat-content th{
    padding:12px 16px;
}
            {!! $typoCss !!}
            .lqd-chat-v2 .chat-content.prose,
.lqd-chat-v2 .chat-content.prose p,
.lqd-chat-v2 .chat-content.prose li{
    font-size:16px !important;
    line-height:1.85 !important;
}

.conversation-area-wrap,
.conversation-area-wrap > .card,
.conversation-area-wrap > .card-body,
#load_chat_area_container{
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
}

.ai-header{
    display:none;
}

.lqd-chat-ai-bubble{
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

.lqd-chat-ai-bubble .chat-content-container{
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    max-width: 100% !important;
}

.conversation-area-wrap,
#load_chat_area_container,
#load_chat_area_container > .card,
#load_chat_area_container > .card-body,
.conversation-area {
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
}

.typing-wrapper{
    display:flex;
    align-items:center;
    gap:12px;
}

.lqd-typing{
    width:28px;
    height:28px;
}

.lqd-typing-dots{
    gap:4px;
}

.lqd-typing-dot{
    width:6px;
    height:6px;
    border-radius:9999px;
    animation:typing 1.4s infinite ease-in-out;
}

.lqd-typing-dot:nth-child(2){
    animation-delay:.2s;
}

.lqd-typing-dot:nth-child(3){
    animation-delay:.4s;
}

@keyframes typing{
    0%,80%,100%{
        opacity:.2;
        transform:scale(.8);
    }
    40%{
        opacity:1;
        transform:scale(1);
    }
}
          
          /* ===== GARAD CHAT - CHATGPT-LIKE UI =====
             UI only. Existing chat actions, routes, Alpine state, search,
             folders, rename/pin/delete, voice, attachments and message logic stay intact. */

            .lqd-chat-v2-container {
                --garad-sidebar-open-width: 264px;
                --garad-rail-width: 52px;
                --sidebar-w: var(--garad-rail-width);
                min-height: 100vh;
                background: hsl(var(--background));
            }

            /* Desktop state: when history is open the old left rail collapses,
               so the conversations panel can start flush from the left edge. */
            @media (min-width: 992px) {
                .lqd-chat-v2-container.garad-sidebar-open {
                    --sidebar-w: 0px;
                }

                .lqd-chat-v2-container.garad-sidebar-closed {
                    --sidebar-w: var(--garad-rail-width);
                }

                .garad-sidebar-open .lqd-chat-v2-sidebar {
                    width: 0 !important;
                    min-width: 0 !important;
                    max-width: 0 !important;
                    padding: 0 !important;
                    border: 0 !important;
                    overflow: hidden !important;
                    opacity: 0;
                    pointer-events: none;
                }

                .garad-sidebar-closed .lqd-chat-v2-sidebar {
                    width: var(--garad-rail-width) !important;
                    min-width: var(--garad-rail-width) !important;
                    max-width: var(--garad-rail-width) !important;
                    opacity: 1;
                    pointer-events: auto;
                }

                /* The model/category controls move out of the opened sidebar area,
                   while the centered Garad.ai logo keeps its own column. */
                .garad-sidebar-open .lqd-chat-pro-header > div:first-child {
                    width: calc(41.666667% - var(--garad-sidebar-open-width)) !important;
                    margin-inline-start: var(--garad-sidebar-open-width) !important;
                    transition: width .24s ease, margin .24s ease;
                }

                .garad-sidebar-closed .lqd-chat-pro-header > div:first-child {
                    width: 41.666667% !important;
                    margin-inline-start: 0 !important;
                    transition: width .24s ease, margin .24s ease;
                }
            }

            /* Closed-state rail: only the history/show button remains here. */
            .lqd-chat-v2-sidebar {
                width: var(--garad-rail-width) !important;
                min-width: var(--garad-rail-width) !important;
                max-width: var(--garad-rail-width) !important;
                padding: 12px 6px !important;
                border-color: hsl(var(--border)) !important;
                background: hsl(var(--background));
                transition: width .24s ease, min-width .24s ease, max-width .24s ease, opacity .18s ease, padding .24s ease !important;
            }

            .lqd-chat-v2-sidebar > div {
                gap: 8px !important;
            }

            .lqd-chat-v2-sidebar #show-recent-btn {
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
                border-radius: 11px !important;
            }


            .lqd-chat-v2-sidebar .garad-rail-new-chat-btn {
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
                max-width: 38px !important;
                padding: 0 !important;
                border-radius: 11px !important;
                background: transparent !important;
                color: hsl(var(--heading-foreground)) !important;
                box-shadow: none !important;
            }

            .lqd-chat-v2-sidebar .garad-rail-new-chat-btn:hover {
                background: hsl(var(--heading-foreground) / 0.055) !important;
                transform: none !important;
            }

            .lqd-chat-v2-sidebar .garad-rail-new-chat-btn svg {
                width: 19px !important;
                height: 19px !important;
            }

            /* Open conversation sidebar */
            .chats-sidebar-wrap {
                width: var(--garad-sidebar-open-width) !important;
                max-width: var(--garad-sidebar-open-width) !important;
                padding-inline-start: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                flex-wrap: nowrap !important;
                border-inline-end: 1px solid hsl(var(--border));
                background: hsl(var(--background)) !important;
                box-shadow: none !important;
                transition: width .24s ease, transform .24s ease !important;
            }

            .garad-sidebar-topbar {
                display: flex;
                align-items: center;
                gap: 8px;
                min-height: 58px;
                padding: 10px 10px 6px;
                border-bottom: 0;
                background: hsl(var(--background));
                flex: 0 0 auto;
            }

            .garad-sidebar-new-chat {
                min-width: 0;
                flex: 1 1 auto;
                height: 38px !important;
                justify-content: flex-start !important;
                gap: 9px !important;
                padding: 0 11px !important;
                border: 0 !important;
                border-radius: 10px !important;
                background: transparent !important;
                color: hsl(var(--heading-foreground)) !important;
                box-shadow: none !important;
                font-size: 13px !important;
                font-weight: 600 !important;
            }

            .garad-sidebar-new-chat:hover {
                background: hsl(var(--heading-foreground) / 0.055) !important;
                transform: none !important;
            }

            .garad-sidebar-new-chat svg {
                width: 18px !important;
                height: 18px !important;
            }

            .garad-sidebar-collapse-btn {
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
                padding: 0 !important;
                border: 0 !important;
                border-radius: 10px !important;
                background: transparent !important;
                color: hsl(var(--heading-foreground)) !important;
                box-shadow: none !important;
            }

            .garad-sidebar-collapse-btn:hover {
                background: hsl(var(--heading-foreground) / 0.055) !important;
                transform: none !important;
            }

            .chats-list-container {
                flex: 1 1 auto !important;
                height: auto !important;
                min-height: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                background: hsl(var(--background)) !important;
                box-shadow: none !important;
            }

            .chats-list-container > .card-body {
                min-height: 0 !important;
                padding: 0 !important;
            }

            /* Search and delete stay directly under New chat. */
            .chats-search {
                order: 1;
                height: auto !important;
                gap: 7px !important;
                padding: 5px 10px 8px !important;
                border-bottom: 0 !important;
            }

            .chats-search-form .navbar-search-input {
                min-height: 36px !important;
                height: 36px !important;
                padding-inline-start: 35px !important;
                padding-inline-end: 10px !important;
                border: 0 !important;
                border-radius: 10px !important;
                background: hsl(var(--heading-foreground) / 0.045) !important;
                box-shadow: none !important;
                font-size: 11.5px !important;
            }

            .chats-search-form svg {
                inset-inline-start: 11px !important;
                width: 15px !important;
                height: 15px !important;
                opacity: .55 !important;
            }

            .chats-search .lqd-chat-clear-all {
                width: 36px !important;
                height: 36px !important;
                min-width: 36px !important;
                padding: 0 !important;
                border: 0 !important;
                border-radius: 10px !important;
                background: hsl(var(--heading-foreground) / 0.045) !important;
                box-shadow: none !important;
            }

            .chats-search .lqd-chat-clear-all svg {
                width: 16px !important;
                height: 16px !important;
            }

            /* The old bottom New Conversation block is intentionally hidden;
               the exact same action now lives in the topbar above search. */
            .chats-new {
                display: none !important;
            }

            .chats-list {
                order: 2;
                flex: 1 1 auto !important;
                min-height: 0 !important;
                overflow: hidden !important;
            }

            #chat_sidebar_container,
            .chat-list-wrapper,
            .chat-list-ul {
                min-height: 0 !important;
            }

            .chat-list-ul {
                padding: 0 5px 12px !important;
                scrollbar-width: thin;
            }

            .chat-list-item {
                margin: 1px 4px !important;
                border: 0 !important;
                border-radius: 9px !important;
                transition: background-color .14s ease !important;
            }

            .chat-list-item::before {
                display: none !important;
            }

            .chat-list-item:hover,
            .chat-list-item.active {
                background: hsl(var(--heading-foreground) / 0.055) !important;
            }

            .chat-list-item.active {
                background: hsl(var(--heading-foreground) / 0.085) !important;
            }

            .chat-list-item-trigger {
                min-height: 35px !important;
                padding: 7px 9px !important;
                gap: 7px !important;
                border-radius: 9px !important;
                align-items: center !important;
            }

            .chat-folder-item .chat-list-item-trigger,
            .chat-add-folder-item .chat-list-item-trigger {
                min-height: 35px !important;
                padding: 7px 9px !important;
                font-size: 12px !important;
            }

            .lqd-chat-item-trigger-icons {
                display: none !important;
            }

            .lqd-chat-item-trigger-info {
                min-width: 0 !important;
            }

            .chat-item-title {
                display: block;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 12px !important;
                line-height: 1.3 !important;
                font-weight: 500 !important;
            }

            .chat-item-date {
                display: none !important;
            }

            .chats-sidebar-links {
                gap: 2px !important;
                padding: 8px 10px 12px !important;
                border-top: 1px solid hsl(var(--border));
                background: hsl(var(--background));
            }

            .chats-sidebar-links a,
            .chats-sidebar-links button {
                width: 100%;
                justify-content: flex-start !important;
                padding: 6px 7px !important;
                border-radius: 8px !important;
                font-size: 9.5px !important;
                letter-spacing: .01em !important;
                text-transform: none !important;
            }

            .chats-sidebar-links a:hover,
            .chats-sidebar-links button:hover {
                background: hsl(var(--heading-foreground) / 0.05) !important;
            }

            /* Profile now lives at the bottom of the open sidebar.
               The original header profile trigger is hidden only on Chat Pro. */
            @media (min-width: 992px) {
                .lqd-chat-pro-header .header-user-dropdown {
                    display: none !important;
                }
            }

            .garad-sidebar-bottom {
                flex: 0 0 auto;
                margin-top: auto;
                background: hsl(var(--background));
            }

            .garad-sidebar-profile-wrap {
                padding: 7px 10px 5px;
                border-top: 1px solid hsl(var(--border));
                background: hsl(var(--background));
            }

            .garad-sidebar-profile-dropdown {
                width: 100% !important;
            }

            .garad-sidebar-profile-dropdown .lqd-dropdown-trigger {
                width: 100% !important;
                height: auto !important;
                min-height: 58px !important;
                justify-content: flex-start !important;
                align-items: center !important;
                gap: 9px !important;
                padding: 7px 8px !important;
                border: 0 !important;
                border-radius: 9px !important;
                background: transparent !important;
                color: hsl(var(--heading-foreground)) !important;
                box-shadow: none !important;
                font-size: 11.5px !important;
                font-weight: 600 !important;
            }

            .garad-sidebar-profile-dropdown .lqd-dropdown-trigger:hover {
                background: hsl(var(--heading-foreground) / 0.055) !important;
                transform: none !important;
            }

            /* Keep the sidebar profile visually clean: no underline on hover/focus. */
            .garad-sidebar-profile-dropdown .lqd-dropdown-trigger,
            .garad-sidebar-profile-dropdown .lqd-dropdown-trigger:hover,
            .garad-sidebar-profile-dropdown .lqd-dropdown-trigger:focus,
            .garad-sidebar-profile-dropdown .lqd-dropdown-trigger *,
            .garad-sidebar-profile-name,
            .garad-sidebar-profile-plan {
                text-decoration: none !important;
                text-decoration-line: none !important;
            }

            .garad-sidebar-profile-avatar {
                display: inline-grid;
                width: 28px;
                height: 28px;
                min-width: 28px;
                place-items: center;
                overflow: hidden;
                border: 1px solid hsl(var(--border));
                border-radius: 999px;
                background: hsl(var(--heading-foreground) / 0.035);
            }

            .garad-sidebar-profile-avatar img {
                display: block;
                width: 100%;
                height: 100%;
                border-radius: inherit;
                object-fit: cover;
                object-position: center;
            }

            .garad-sidebar-profile-copy {
                min-width: 0;
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 1px;
                line-height: 1.15;
            }

            .garad-sidebar-profile-name {
                display: block;
                width: 100%;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 11.5px;
                font-weight: 700;
                color: hsl(var(--heading-foreground));
            }

            .garad-sidebar-profile-plan {
                display: inline-flex;
                align-items: center;
                width: max-content;
                max-width: 100%;
                margin-top: 5px;
                padding: 3px 8px;
                overflow: hidden;
                border: 1px solid;
                border-radius: 999px;
                font-size: 9.5px;
                font-weight: 800;
                line-height: 1.15;
                letter-spacing: 0.01em;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* Force the existing user dropdown to open upward from the sidebar. */
            .garad-sidebar-profile-dropdown .lqd-dropdown-dropdown {
                top: auto !important;
                bottom: calc(100% + 7px) !important;
                inset-inline-start: 0 !important;
                inset-inline-end: auto !important;
                width: 100% !important;
            }

            .garad-sidebar-profile-dropdown .lqd-dropdown-dropdown-content {
                width: 100% !important;
                min-width: 228px !important;
                max-width: 244px !important;
            }

            /* ChatGPT-like hover behaviour in addition to the existing click behaviour. */
            .garad-sidebar-profile-dropdown:hover .lqd-dropdown-dropdown {
                opacity: 1 !important;
                visibility: visible !important;
                pointer-events: auto !important;
                transform: translateY(0) !important;
            }

            /* Dashboard reminder: uses the EXISTING grid Dashboard button in the header.
               No extra icon is created. */
            .garad-dashboard-reminder-target {
                position: relative !important;
                overflow: visible !important;
            }

            .garad-dashboard-reminder {
                position: absolute;
                top: calc(100% + 8px);
                inset-inline-end: 0;
                z-index: 180;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: max-content;
                padding: 6px 9px;
                border-radius: 7px;
                background: hsl(var(--heading-foreground));
                color: hsl(var(--background));
                box-shadow: 0 8px 24px hsl(var(--heading-foreground) / 0.16);
                font-size: 10px;
                font-weight: 600;
                line-height: 1;
                white-space: nowrap;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(-4px) scale(.97);
                transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
            }

            .garad-dashboard-reminder::before {
                content: '';
                position: absolute;
                inset-inline-end: 13px;
                bottom: 100%;
                width: 0;
                height: 0;
                border-inline-start: 5px solid transparent;
                border-inline-end: 5px solid transparent;
                border-bottom: 5px solid hsl(var(--heading-foreground));
            }

            .garad-dashboard-reminder.is-visible {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            /* Main chat remains functionally untouched. */
            .conversation-area-wrap,
            #load_chat_area_container,
            #load_chat_area_container > .card,
            #load_chat_area_container > .card-body,
            .conversation-area {
                background: transparent !important;
                border: 0 !important;
                box-shadow: none !important;
            }

            .lqd-chat-ai-bubble .chat-content-container {
                width: 100% !important;
                max-width: 760px !important;
                margin-inline: auto !important;
            }

            .lqd-chat-ai-bubble .chat-content {
                width: 100% !important;
                max-width: 720px !important;
                margin-inline: auto !important;
                padding: 16px 18px !important;
            }

            .lqd-chat-user-bubble {
                width: min(720px, calc(100% - 28px)) !important;
                margin-inline: auto !important;
                justify-content: flex-start !important;
            }

            .lqd-chat-user-bubble .chat-content-container {
                margin-inline-start: auto !important;
                max-width: min(78%, 600px) !important;
                border-radius: 17px !important;
            }

            /* ChatGPT-like composer: a little wider, taller and fully balanced. */
            .lqd-chat-form-wrap {
                background: hsl(var(--background)) !important;
            }

            #chat_form {
                width: calc(100% - 32px) !important;
                max-width: 760px !important;
                margin-inline: auto !important;
                padding: 0 0 14px !important;
                gap: 0 !important;
                transition: max-width .24s ease, width .24s ease !important;
            }

            @media (min-width: 992px) {
                .garad-sidebar-open #chat_form {
                    max-width: 700px !important;
                }

                .garad-sidebar-closed #chat_form {
                    max-width: 760px !important;
                }
            }

            #chat_form .lqd-chat-form-inputs-container {
                min-height: 52px !important;
                border: 1px solid hsl(var(--border)) !important;
                border-radius: 26px !important;
                background: hsl(var(--surface-background, var(--background))) !important;
                box-shadow: 0 4px 20px rgba(0, 0, 0, .045) !important;
                overflow: hidden !important;
            }

            #chat_form .lqd-chat-form-inputs-container > .relative {
                min-height: 52px !important;
                align-items: center !important;
            }

            #chat_form #prompt {
                min-height: 52px !important;
                max-height: 150px !important;
                padding-top: 14px !important;
                padding-bottom: 14px !important;
                padding-inline-start: 52px !important;
                padding-inline-end: 118px !important;
                font-size: 14px !important;
                line-height: 1.4 !important;
            }

            /* Keep +, microphone and voice controls on the same center line. */
            #chat_form .lqd-chat-form-inputs-container > .relative > .pointer-events-none {
                top: 50% !important;
                bottom: auto !important;
                inset-inline-start: 9px !important;
                inset-inline-end: 9px !important;
                transform: translateY(-50%) !important;
                align-items: center !important;
                gap: 5px !important;
            }

            #chat_form #chat-options {
                height: 32px !important;
                min-height: 32px !important;
                align-items: center !important;
                gap: 5px !important;
            }

            #chat_form #chat-options > * {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
                align-self: center !important;
            }

            #chat_form .lqd-dropdown-trigger,
            #chat_form #chat-options button,
            #chat_form .lqd-chat-record-trigger,
            #chat_form .lqd-chat-record-stop-trigger,
            #chat_form .lqd-chat-send-btn,
            #chat_form .lqd-chat-stop-btn {
                width: 32px !important;
                height: 32px !important;
                min-width: 32px !important;
                min-height: 32px !important;
                max-width: 32px !important;
                max-height: 32px !important;
                padding: 0 !important;
                border-radius: 999px !important;
                align-items: center !important;
                justify-content: center !important;
                line-height: 1 !important;
            }

            #chat_form .lqd-dropdown-trigger svg,
            #chat_form #chat-options button svg,
            #chat_form .lqd-chat-record-trigger svg,
            #chat_form .lqd-chat-record-stop-trigger svg,
            #chat_form .lqd-chat-send-btn svg,
            #chat_form .lqd-chat-stop-btn svg {
                width: 16px !important;
                height: 16px !important;
                min-width: 16px !important;
                min-height: 16px !important;
            }

            .lqd-chat-bubble-suggestions button {
                border-radius: 9px !important;
                font-size: 10.5px !important;
            }

            @media (max-width: 991px) {
                .lqd-chat-v2-container {
                    --sidebar-w: 0px;
                }

                .lqd-chat-v2-sidebar {
                    display: none !important;
                }

                .garad-sidebar-topbar {
                    display: none !important;
                }

                .chats-sidebar-wrap {
                    width: min(300px, 88vw) !important;
                    max-width: min(300px, 88vw) !important;
                    padding-inline-start: 0 !important;
                    border-inline-end: 1px solid hsl(var(--border));
                    box-shadow: 16px 0 35px rgba(0, 0, 0, .14) !important;
                }

                #chat_form {
                    width: calc(100% - 18px) !important;
                    max-width: none !important;
                    padding-bottom: 9px !important;
                }

                #chat_form .lqd-chat-form-inputs-container,
                #chat_form .lqd-chat-form-inputs-container > .relative,
                #chat_form #prompt {
                    min-height: 52px !important;
                }

                .lqd-chat-ai-bubble .chat-content {
                    padding: 13px 10px !important;
                }

                .lqd-chat-user-bubble {
                    width: calc(100% - 14px) !important;
                }
            }

            @media (max-width: 767px) {
                .chats-search {
                    padding-top: 8px !important;
                }

                .chat-list-item-trigger,
                .chat-folder-item .chat-list-item-trigger,
                .chat-add-folder-item .chat-list-item-trigger {
                    min-height: 38px !important;
                }

                #chat_form #prompt {
                    font-size: 16px !important;
                }
            }
        </style>
    @endpush
@endif

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.remove("focus-mode");
        })();
    </script>
@endpush

@if ($ad_enabled)
    <style>
        body {
            --ad-h: calc(90px + 0.5rem);
        }
    </style>
@endif

@if (!$show_mobile_nav_header)
    @push('css')
        <style>
            @media(max-width: 992px) {
                :root {
                    --header-height: 0px !important;
                }

                .lqd-chat-v2 {
                    --header-h: 0px !important;
                }

                .lqd-header {
                    display: none !important;
                }
            }
        </style>
    @endpush
@endif

@section('content')
    @if ($category?->slug == 'ai_webchat' && count($list) === 0)
        <input
            id="createChatUrl"
            type="hidden"
            name="createChatUrl"
            value="/dashboard/user/openai/webchat/start-new-chat"
        />
    @endif
    <input
        id="openChatAreaContainerUrl"
        type="hidden"
        name="openChatAreaContainerUrl"
        value="@yield('openChatAreaContainerUrl', '/dashboard/user/openai/chat/open-chat-area-container')"
    />

    {!! adsense_chat_pro_top_header_728x90() !!}

    <div
        class="lqd-chat-v2-container relative lg:flex"
        :class="{ 'garad-sidebar-open': !sidebarHidden, 'garad-sidebar-closed': sidebarHidden }"
        x-data="chatsV2"
        @keydown.escape.window="if (window.innerWidth < 992) sidebarHidden = true"
        @chat-created.window="onNewChatCreated"
    >
        <div
            class="pointer-events-none absolute -top-10 start-20 z-20 hidden h-52 w-[500px] rotate-12 rounded-full bg-gradient-to-r from-blue-500 to-green-400 opacity-[0.04] blur-3xl transition-all lg:block">
        </div>
        <div class="pointer-events-none absolute top-52 hidden size-52 -translate-x-2/3 bg-rose-500 opacity-10 blur-3xl transition-all dark:opacity-5 lg:block">
        </div>

        <div class="lqd-chat-v2-sidebar sticky top-0 z-20 hidden w-[--sidebar-w] shrink-0 flex-col border-e py-5 transition-all lg:flex">
            <div class="flex flex-col items-center gap-2">
                <x-button
                    class="relative before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-10 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100"
                    id="show-recent-btn"
                    variant="outline"
                    size="none"
                    hover-variant="primary"
                    @click.prevent="$nextTick(() => toggleSidebarHidden())"
                    title="{{ __('Show Recent') }}"
                >
                    <x-tabler-circle-chevron-right
                        class="size-5"
                        stroke-width="1.5"
                    />
                </x-button>

                @if (!view()->hasSection('chat_sidebar_actions'))
                    @if (isset($category) && $category?->slug == 'ai_pdf')
                        <x-button
                            class="garad-rail-new-chat-btn lqd-upload-doc-trigger relative before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-10 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100"
                            variant="none"
                            size="none"
                            href="javascript:void(0);"
                            title="{{ __('Upload Document') }}"
                            onclick="return $('#selectDocInput').click();"
                        >
                            <x-tabler-plus class="size-5" />
                        </x-button>
                    @else
                        <x-button
                            class="garad-rail-new-chat-btn lqd-new-chat-trigger relative before:pointer-events-none before:invisible before:absolute before:start-full before:top-1/2 before:z-10 before:ms-2 before:-translate-y-1/2 before:translate-x-1 before:whitespace-nowrap before:rounded before:bg-background before:px-3 before:py-1.5 before:text-2xs before:font-medium before:text-heading-foreground before:opacity-0 before:shadow-md before:shadow-black/5 before:transition-all before:content-[attr(title)] hover:before:visible hover:before:translate-x-0 hover:before:opacity-100"
                            variant="none"
                            size="none"
                            href="javascript:void(0);"
                            title="{{ __('New Chat') }}"
                            onclick="{!! $disable_actions
                                ? 'return toastr.info(\'{{ __('This feature is disabled in Demo version.') }}\')'
                                : (auth()->check()
                                    ? 'return startNewChat(\'{{ $category?->id }}\', \'{{ LaravelLocalization::getCurrentLocale() }}\', \'chatpro\')'
                                    : 'return window.location.reload();') !!}"
                        >
                            <x-tabler-plus class="size-5" />
                        </x-button>
                    @endif
                @endif
            </div>
        </div>

        <div class="grow px-2 md:px-5 lg:px-0">
            @include('ai-chat-pro::includes.header.header')

            <div class="max-lg:py-5">
                <div
                    id="user_chat_area"
                    @class([
                        'chats-wrap group/chats-wrap relative lg:h-[calc(100vh-var(--ad-h,0px))]',
                        'max-lg:h-[calc(100vh-var(--header-height,var(--header-h,0px))-var(--bottom-menu-height,0px)-var(--ad-h,0px)-2.5rem)] md:grid md:max-lg:grid-flow-col md:max-lg:[grid-template-columns:30%_70%]' => Auth::check(),
                        'max-lg:h-[calc(100vh-2.5rem-var(--header-h))]' => !Auth::check(),
                        'conversation-started' => count($chat->messages ?? []) > 1,
                        'conversation-not-started' => count($chat->messages ?? []) <= 1,
                    ])
                    :class="{ 'chats-sidebar-hidden': (($store.focusMode.active && sidebarHidden) || sidebarForceHidden) }"
                >
                    <div
                        @class([
                            'chats-sidebar-wrap relative flex h-[inherit] w-full transition-all max-md:absolute max-md:start-0 max-md:top-20 max-md:z-10 max-md:h-0 max-md:overflow-hidden max-md:bg-background lg:fixed lg:start-0 lg:z-10 lg:grid lg:grid-cols-1 lg:h-screen lg:w-[405px] lg:grid-rows-5 lg:flex-wrap lg:bg-background lg:ps-[--sidebar-w] lg:duration-300 max-md:[&.active]:h-[calc(100%-80px)]',
                            'md:max-lg:hidden' => !Auth::check(),
                        ])
                        :class="{
                            'active': mobileSidebarShow || !sidebarHidden,
                            'lg:hidden': (($store.focusMode.active && sidebarHidden) || sidebarForceHidden)
                        }"
                        @click.outside="(e) => { if (window.innerWidth < 992) { return !sidebarLocked && !sidebarHidden && !isShowRecent(e?.target) && (sidebarHidden = true); } }"
                    >
                        @auth
                            <div class="garad-sidebar-topbar hidden lg:flex">
                                @if (!view()->hasSection('chat_sidebar_actions'))
                                    @if (isset($category) && $category?->slug == 'ai_pdf')
                                        <x-button
                                            class="garad-sidebar-new-chat lqd-upload-doc-trigger"
                                            variant="none"
                                            size="none"
                                            href="javascript:void(0);"
                                            onclick="return $('#selectDocInput').click();"
                                        >
                                            <x-tabler-plus class="size-4" />
                                            <span>{{ __('Upload Document') }}</span>
                                        </x-button>
                                    @else
                                        <x-button
                                            class="garad-sidebar-new-chat lqd-new-chat-trigger"
                                            variant="none"
                                            size="none"
                                            href="javascript:void(0);"
                                            title="{{ __('New Chat') }}"
                                            onclick="{!! $disable_actions
                                                ? 'return toastr.info(\'{{ __('This feature is disabled in Demo version.') }}\')'
                                                : (auth()->check()
                                                    ? 'return startNewChat(\'{{ $category?->id }}\', \'{{ LaravelLocalization::getCurrentLocale() }}\', \'chatpro\')'
                                                    : 'return window.location.reload();') !!}"
                                        >
                                            <x-tabler-plus class="size-4" />
                                            <span>{{ __('New Chat') }}</span>
                                        </x-button>
                                    @endif
                                @else
                                    <div class="min-w-0 grow"></div>
                                @endif

                                <x-button
                                    class="garad-sidebar-collapse-btn"
                                    variant="none"
                                    size="none"
                                    href="#"
                                    @click.prevent="toggleSidebarHidden()"
                                    title="{{ __('Hide Recent') }}"
                                >
                                    <svg
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                        <path d="M9 4v16"></path>
                                    </svg>
                                </x-button>
                            </div>
                        @endauth

                        @if (view()->hasSection('chat_sidebar'))
                            @yield('chat_sidebar')
                        @else
                            @include('panel.user.openai_chat.components.chat_sidebar', [
                                'website_url' => 'chatpro',
                            ])
                        @endif

                        <div class="garad-sidebar-bottom hidden w-full flex-col lg:flex">
                            @auth
                                <div class="garad-sidebar-profile-wrap">
                                    <x-user-dropdown
                                        class="garad-sidebar-profile-dropdown"
                                        class:trigger="garad-sidebar-profile-trigger"
                                        class:dropdown-dropdown="garad-sidebar-profile-popover"
                                        class:dropdown="garad-sidebar-profile-menu"
                                        dropdown-offset-y="8px"
                                    >
                                        <x-slot:trigger>
                                            <span class="garad-sidebar-profile-avatar">
                                                <img
                                                    src="{{ $chatSidebarAvatarUrl }}"
                                                    alt="{{ auth()->user()?->fullName() ?? __('Profile') }}"
                                                    loading="lazy"
                                                    onerror="this.onerror=null;this.src='{{ custom_theme_url('/assets/img/auth/default-avatar.png') }}';"
                                                >
                                            </span>
                                            <span class="garad-sidebar-profile-copy">
                                                <span class="garad-sidebar-profile-name">
                                                    {{ $chatSidebarUser?->fullName() }}
                                                </span>
                                                <span
                                                    class="garad-sidebar-profile-plan"
                                                    style="color: {{ $chatSidebarPlanColor }}; background: {{ $chatSidebarPlanBg }}; border-color: {{ $chatSidebarPlanBorder }};"
                                                >
                                                    {{ $chatSidebarPlanName }}
                                                </span>
                                            </span>
                                        </x-slot:trigger>
                                    </x-user-dropdown>
                                </div>
                            @endauth

                            <div class="chats-sidebar-links flex w-full flex-col items-start gap-y-5 px-7 pb-12">
                                <x-button
                                    class="text-4xs uppercase tracking-widest text-heading-foreground/50 hover:text-heading-foreground"
                                    href="{{ url('/privacy-policy') }}"
                                    variant="link"
                                >
                                    <x-tabler-chevron-right class="size-3.5 transition-all group-hover:translate-x-0.5" />
                                    {{ __('Privacy Policy') }}
                                </x-button>

                                <x-button
                                    class="text-4xs uppercase tracking-widest text-heading-foreground/50 hover:text-heading-foreground"
                                    href="{{ url('/terms') }}"
                                    variant="link"
                                >
                                    <x-tabler-chevron-right class="size-3.5 transition-all group-hover:translate-x-0.5" />
                                    {{ __('Need Help?') }}
                                </x-button>
                            </div>
                        </div>
                    </div>

                    <x-card
    class="conversation-area-wrap relative flex h-[inherit] grow flex-col md:rounded-s-none lg:w-full border-0 shadow-none bg-transparent"
    class:body="h-full rounded-none border-0 shadow-none bg-transparent p-0"
    id="load_chat_area_container"
    size="none"
>
                        <x-slot:head
                            class="!border-none !p-0"
                        >
                            <x-button
                                class="chats-sidebar-expander absolute start-0 top-5 z-[99] hidden size-10 -translate-x-1/2 place-content-center rounded-full bg-background text-heading-foreground shadow-md shadow-heading-foreground/10 hover:translate-y-0 hover:scale-105 dark:bg-background lg:group-[&.focus-mode]/body:inline-grid"
                                variant="ghost-shadow"
                                size="none"
                                href="#"
                                @click.prevent="if ( $store.focusMode.active ) { toggleSidebarHidden() }"
                            >
                                <span
                                    class="inline-block transition-transform"
                                    :class="{ 'rotate-180': sidebarHidden }"
                                >
                                    <x-tabler-chevron-left class="rtl:-scale-x-1 size-4" />
                                </span>
                            </x-button>
                        </x-slot:head>
                        @if ($chat != null)
                            @if (view()->hasSection('chat_area_container'))
                                @yield('chat_area_container')
                            @elseif (\App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1))
                                @include('canvas::includes.chat_area_container')
                            @elseif (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-deep-research') && (bool) setting('deep_research_auto_canvas', 1))
                                @include('ai-chat-pro-deep-research::includes.chat_area_container')
                            @else
                                @include('ai-chat-pro::includes.chat_area_container')
                            @endif
                        @else
                            <div class="conversation-area flex h-[inherit] grow flex-col justify-between overflow-y-auto rounded-b-[inherit] rounded-t-[inherit] max-md:max-h-full">
                            </div>
                        @endif

                    </x-card>

                    @if ((bool) setting('ai_chat_pro_prompt_navigator', '1'))
                        @include('ai-chat-pro::includes.prompt-navigator')
                    @endif
                </div>
            </div>
        </div>

        @includeIf('ai-chat-pro-entity-highlight::entity-hightlight-drawer')
        @includeIf('ai-chat-pro-highlight-to-ask::highlight-to-ask-popover')
    </div>

    <template id="chat_user_image_bubble">
        <div class="lqd-chat-image-bubble mb-2 flex !w-auto max-w-[50%] flex-row-reverse content-end gap-2 !px-3 !py-2.5 last:mb-0 lg:ms-auto lg:justify-self-end">
            <a
                class="flex items-center gap-1.5 underline underline-offset-2"
                data-fslightbox="gallery"
                data-type="image"
                href="#"
                target="_blank"
            >
                <svg
                    class="shrink-0 opacity-50"
                    width="15"
                    height="19"
                    viewBox="0 0 15 19"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M1.66667 18.5C1.20833 18.5 0.815972 18.3042 0.489583 17.9125C0.163194 17.5208 0 17.05 0 16.5V2.5C0 1.95 0.163194 1.47917 0.489583 1.0875C0.815972 0.695833 1.20833 0.5 1.66667 0.5H10L15 6.5V16.5C15 17.05 14.8368 17.5208 14.5104 17.9125C14.184 18.3042 13.7917 18.5 13.3333 18.5H1.66667ZM3.33333 14.5H11.6667V12.5H3.33333V14.5ZM3.33333 10.5H11.6667V8.5H3.33333V10.5ZM3.33333 6.5H9.16667V4.5H3.33333V6.5Z"
                    />
                </svg>
                <img
                    class="img-content rounded-3xl"
                    loading="lazy"
                />
            </a>
        </div>
    </template>

    <template id="chat_bot_image_bubble">
        <div class="lqd-chat-image-bubble mb-2 flex content-end gap-2 lg:ms-auto">
            <div class="mb-2 flex w-4/5 justify-start rounded-3xl text-heading-foreground dark:text-heading-foreground md:w-1/2">
                <a
                    data-fslightbox="gallery"
                    data-type="image"
                    href="#"
                    target="_blank"
                >
                    <img
                        class="img-content rounded-3xl"
                        loading="lazy"
                    />
                </a>
            </div>
        </div>
    </template>

    <template id="chat_user_bubble">
        <div class="lqd-chat-user-bubble mb-2 flex flex-row-reverse content-end gap-2 lg:ms-auto">
            <div class="lqd-chat-sender flex items-center gap-2.5">
                <span
                    class="lqd-chat-avatar inline-block size-6 shrink-0 rounded-full bg-cover bg-center"
                    style="background-image: url({{ url(Auth::user()?->avatar ?? '', true) }})"
                ></span>
                <span class="lqd-chat-sender-name">
                    @lang('You')
                </span>
            </div>
            <div
                class="chat-content-container group relative max-w-[calc(100%-64px)] rounded-[2em] bg-secondary text-secondary-foreground dark:bg-zinc-700 dark:text-primary-foreground">
                <div class="chat-content px-5 py-3.5 max-md:break-words"></div>
                <div
                    class="lqd-chat-actions-wrap pointer-events-auto invisible absolute -start-5 bottom-0 flex flex-col gap-2 leading-5 opacity-0 transition-all group-hover:!visible group-hover:!opacity-100">
                    <div class="lqd-clipboard-copy-wrap group/copy-wrap flex flex-col gap-2 transition-all">
                        <button
                            class="lqd-clipboard-copy group/btn relative inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110"
                            data-copy-options='{ "content": ".chat-content", "contentIn": "<.chat-content-container" }'
                            title="{{ __('Copy to clipboard') }}"
                        >
                            <span
                                class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                            >
                                {{ __('Copy to clipboard') }}
                            </span>
                            <x-tabler-copy class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template id="chat_ai_bubble">
        <div
            class="lqd-chat-ai-bubble group mb-2 flex content-start items-start gap-2"
            data-message-id=""
            data-title=""
        >
           <div class="lqd-chat-sender ai-header flex items-center gap-2.5">
                <span
                    class="lqd-chat-avatar inline-block size-12 shrink-0 rounded-full bg-cover bg-center"
                    style="background-image: url('{{ !empty($chat->category->image) ? custom_theme_url($chat->category->image, true) : url(custom_theme_url('/assets/img/auth/default-avatar.png')) }}')"
                ></span>
                <span class="lqd-chat-sender-name">
                    {{ __($chat?->category?->name ?? 'AI Assistant') }}
                </span>
            </div>
            <div class="chat-content-container relative min-h-12 min-w-12 w-full max-w-full text-heading-foreground dark:text-heading-foreground">
                <div id="lqd-typing" class="typing-wrapper flex items-center gap-3">

    <div class="lqd-typing relative inline-flex">
        <span class="dot"></span>
        <span class="dot"></span>
        <span class="dot"></span>
    </div>

    <span id="thinking-text" class="text-sm text-gray-400">
        Understanding your request...
    </span>

</div>
                    <div
class="chat-content prose relative w-full max-w-[760px] px-8 py-6 indent-0 font-[inherit] text-[16px] leading-[1.85] font-normal text-current [word-break:break-word] empty:hidden">
</div>

                    <div
                        class="lqd-chat-actions-wrap pointer-events-auto invisible absolute -end-5 bottom-0 flex flex-col gap-2 opacity-0 transition-all group-hover:!visible group-hover:!opacity-100">
                        <div class="lqd-clipboard-copy-wrap group/copy-wrap flex flex-col gap-2 transition-all">
                            <button
                                class="lqd-clipboard-copy group/btn relative inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110"
                                data-copy-options='{ "content": ".chat-content", "contentIn": "<.chat-content-container" }'
                                title="{{ __('Copy to clipboard') }}"
                            >
                                <span
                                    class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                                >
                                    {{ __('Copy to clipboard') }}
                                </span>
                                <x-tabler-copy class="size-4" />
                            </button>

                            @if (
                                (\App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1)) ||
                                    (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-deep-research') && (bool) setting('deep_research_auto_canvas', 1)))
                                <button
                                    class="lqd-chat-bubble-canvas-trigger group/btn inline-flex size-10 items-center justify-center rounded-full border-none bg-white p-0 text-[12px] text-black shadow-lg transition-all hover:-translate-y-[2px] hover:scale-110 group-[&.loading]:pointer-events-none group-[&.streaming-on]:pointer-events-none group-[&.loading]:opacity-50 group-[&.streaming-on]:opacity-50"
                                    type="button"
                                    @click.prevent="setCanvasActive(true);"
                                >
                                    <span
                                        class="pointer-events-none absolute end-full top-1/2 me-1 inline-block -translate-y-1/2 translate-x-1 whitespace-nowrap rounded-full bg-white px-3 py-1 font-medium leading-5 opacity-0 shadow-lg transition-all group-hover/btn:translate-x-0 group-hover/btn:opacity-100"
                                    >
                                        {{ __('Open in Canvas') }}
                                    </span>
                                    <x-tabler-edit class="size-4" />
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
                <div
                    @class([
                        'lqd-chat-bubble-suggestions mt-5',
                        'flex flex-wrap gap-2' => $suggestion_style === 'pill',
                        'flex flex-col gap-1 items-start' => $suggestion_style === 'inline',
                    ])
                    x-data="{ suggestions: [] }"
                >
                    <template
                        x-for="(suggestion, index) in suggestions"
                        x-key="index"
                    >
                        <x-button
                            @class([
                                'text-3xs border bg-transparent font-semibold text-primary hover:bg-primary hover:border-primary hover:text-primary-foreground' =>
                                    $suggestion_style === 'pill',
                                'dark:text-secondary dark:hover:text-primary-foreground' =>
                                    $theme === 'social-media-dashboard' && $suggestion_style === 'pill',
                                'px-0 py-1.5 text-start justify-start [font-weight:inherit] hover:text-primary' =>
                                    $suggestion_style === 'inline',
                            ])
                            variant="none"
                            type="button"
                            ::data-prompt="suggestion"
                            @click.prevent="
                                const promptInput = document.getElementById('prompt');
                                if (promptInput) {
                                    promptInput.value = $el.getAttribute('data-prompt');
                                    promptInput.dispatchEvent(new Event('input'));
                                    const submitBtn = document.getElementById('send_message_button');
                                    if (submitBtn) { submitBtn.click(); }
									promptInput.value = '';
                                    promptInput.dispatchEvent(new Event('input'));
                                }
                            "
                        >
                            @if ($suggestion_style === 'inline')
                                <span class="-scale-x-100">↵</span>
                            @endif
                            <span x-text="suggestion"></span>
                        </x-button>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <template id="chat_ai_council_bubble">
        <div
            class="lqd-chat-ai-bubble model-council-bubble group mb-2 flex max-w-full content-start items-start gap-2"
            data-message-id=""
            data-title=""
        >
            <div class="lqd-chat-sender flex items-center gap-2.5">
                <span
                    class="lqd-chat-avatar inline-block size-12 shrink-0 rounded-full bg-cover bg-center"
                    style="background-image: url('{{ !empty($chat->category->image) ? custom_theme_url($chat->category->image, true) : url(custom_theme_url('/assets/img/auth/default-avatar.png')) }}')"
                ></span>
                <span class="lqd-chat-sender-name">
                    {{ __($chat?->category?->name ?? 'AI Assistant') }}
                </span>
            </div>
            <div class="chat-content-container relative min-h-12 w-full min-w-12 max-w-[calc(100%-64px)] rounded-3xl text-heading-foreground dark:text-heading-foreground">
                <div class="model-council-summary-thinking mb-4 hidden">
                    <x-shimmar>{{ __('Thinking...') }}</x-shimmar>
                </div>

                <div class="model-council-final-sections hidden">
                    <p class="model-council-meta-line mb-4 hidden text-2xs text-heading-foreground/60">
                        <span class="model-council-responded-models"></span>
                        <span
                            class="model-council-meta-sep mx-1 hidden"
                            aria-hidden="true"
                        >•</span>
                        <span class="model-council-confidence"></span>
                    </p>

                    <h4 class="model-council-title-final-answer mb-2 text-sm font-semibold empty:hidden"></h4>
                    <div class="model-council-final-answer chat-content prose mb-4 w-full max-w-[760px] text-[16px] leading-[1.85] text-current [word-break:break-word] [&_*]:text-current">
                    </div>

                    <div class="mb-4 overflow-hidden rounded-xl border border-heading-foreground/10">
                        <div class="grid grid-cols-2 border-b border-heading-foreground/10 bg-heading-foreground/[0.03] px-4 py-2 text-2xs font-semibold">
                            <span class="model-council-title-agreement-level"></span>
                            <span class="model-council-title-confidence-impact"></span>
                        </div>
                        <div class="model-council-agreement-rows"></div>
                    </div>

                    <div class="mb-4 space-y-4 text-xs">
                        <div>
                            <h5 class="model-council-title-agreement-analysis mb-1 text-xs font-semibold empty:hidden"></h5>
                            <ul class="model-council-agreement-analysis m-0 space-y-1 text-heading-foreground/80"></ul>
                        </div>
                        <div>
                            <h5 class="model-council-title-disagreements mb-1 text-xs font-semibold empty:hidden"></h5>
                            <ul class="model-council-disagreements m-0 space-y-1 text-heading-foreground/80"></ul>
                        </div>
                        <div>
                            <h5 class="model-council-title-discoveries mb-1 text-xs font-semibold empty:hidden"></h5>
                            <ul class="model-council-discoveries m-0 space-y-1 text-heading-foreground/80"></ul>
                        </div>
                    </div>
                </div>

                <div class="mt-5 w-full">
                    <h5 class="model-council-title-model-replies mb-4 text-sm font-semibold empty:hidden"></h5>
                    <div class="model-council-replies w-full space-y-4"></div>
                </div>
            </div>
        </div>
    </template>

    @if (
        (\App\Helpers\Classes\MarketplaceHelper::isRegistered('canvas') && (bool) setting('ai_chat_pro_canvas', 1)) ||
            (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-deep-research') && (bool) setting('deep_research_auto_canvas', 1)))
        <template id="canvas_edit_btn_block">
            <div class="mb-3 w-full">
                <button
                    class="lqd-chat-bubble-canvas-trigger group/btn flex items-center gap-2 rounded-md border px-3 py-2 text-2xs font-medium transition-all hover:border-foreground hover:bg-foreground hover:text-background group-[&.loading]:pointer-events-none group-[&.streaming-on]:pointer-events-none group-[&.loading]:opacity-50 group-[&.streaming-on]:opacity-50"
                    type="button"
                    @click.prevent="setCanvasActive(true);"
                >
                    <span
                        class="inline-grid size-9 place-items-center rounded-full border-none bg-surface-background p-0 text-foreground shadow-lg shadow-black/5 transition-all group-hover/btn:-translate-y-[2px] group-hover/btn:scale-110 group-[&.loading]:scale-90 group-[&.streaming-on]:scale-90"
                    >
                        <x-tabler-pencil class="size-4" />
                    </span>
                    {{ __('Open in Canvas') }}
                </button>
            </div>
        </template>
    @endif

    <template id="prompt_image">
        <div class="relative">
            <button
                class="prompt_image_close absolute -end-2 -top-2 flex size-5 items-center justify-center rounded-full bg-red-600 text-white"
                onclick="if ( document.getElementById('mainupscale_src') ) { document.getElementById('mainupscale_src').style.display = 'block'; }"
            >
                <x-tabler-x class="size-4" />
            </button>
            <img
                class="m-0 aspect-square w-20 rounded-xl object-cover object-center"
                src=""
            />
        </div>
    </template>

    <template id="prompt_pdf">
        <div class="relative ms-2 flex items-center gap-3 self-center rounded-full bg-foreground/5 px-3 py-2.5 text-sm/tight text-heading-foreground">
            <a
                class="flex items-center gap-1.5"
                href="#"
                target="_blank"
            >
                <svg
                    class="shrink-0 opacity-50"
                    width="15"
                    height="19"
                    viewBox="0 0 15 19"
                    fill="currentColor"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M1.66667 18.5C1.20833 18.5 0.815972 18.3042 0.489583 17.9125C0.163194 17.5208 0 17.05 0 16.5V2.5C0 1.95 0.163194 1.47917 0.489583 1.0875C0.815972 0.695833 1.20833 0.5 1.66667 0.5H10L15 6.5V16.5C15 17.05 14.8368 17.5208 14.5104 17.9125C14.184 18.3042 13.7917 18.5 13.3333 18.5H1.66667ZM3.33333 14.5H11.6667V12.5H3.33333V14.5ZM3.33333 10.5H11.6667V8.5H3.33333V10.5ZM3.33333 6.5H9.16667V4.5H3.33333V6.5Z"
                    />
                </svg>
                <span class="inline-block max-w-52 truncate"></span>
            </a>
            <button
                class="prompt_image_close shrink-0"
                onclick="if ( document.getElementById('mainupscale_src') ) { document.getElementById('mainupscale_src').style.display = 'block'; }"
            >
                <x-tabler-x class="size-4" />
            </button>
        </div>
    </template>

    <template id="prompt_image_add_btn">
        <div class="promt_image_btn">
            <button class="aspect-square w-20 rounded-xl bg-foreground/10 text-2xl font-light transition-all hover:bg-emerald-500 hover:text-white">+
            </button>
        </div>
    </template>

    <template id="chat_pdf">
        <div class="mb-2 mr-[30px] flex flex-row-reverse content-end gap-[8px] lg:ms-auto">
            <svg
                width="36"
                height="36"
                viewBox="0 0 36 36"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M23.7762 0H5.11921C4.59978 0 4.17871 0.421071 4.17871 1.23814V35.3571C4.17871 35.5789 4.59978 36 5.11921 36H30.8811C31.4005 36 31.8216 35.5789 31.8216 35.3571V8.343C31.8216 7.89557 31.7618 7.75157 31.6564 7.6455L24.1761 0.165214C24.07 0.0597857 23.926 0 23.7762 0Z"
                    fill="#E9E9E0"
                />
                <path
                    d="M24.1074 0.0970459V7.71426H31.7246L24.1074 0.0970459Z"
                    fill="#D9D7CA"
                />
                <path
                    d="M12.5445 21.4226C12.3208 21.4226 12.1061 21.35 11.9229 21.2131C11.2537 20.711 11.1637 20.1523 11.2061 19.7718C11.3231 18.7252 12.6172 17.6298 15.0536 16.5138C16.0205 14.3949 16.9404 11.7843 17.4888 9.60306C16.8472 8.20677 16.2236 6.3952 16.6781 5.33256C16.8375 4.96035 17.0362 4.67492 17.4071 4.55149C17.5537 4.50263 17.924 4.44092 18.0603 4.44092C18.3843 4.44092 18.669 4.85813 18.8709 5.11527C19.0605 5.35699 19.4906 5.86935 18.6311 9.48799C19.4977 11.2777 20.7255 13.1008 21.902 14.3493C22.7448 14.1969 23.4699 14.1191 24.0607 14.1191C25.0674 14.1191 25.6775 14.3538 25.9263 14.8372C26.132 15.2371 26.0478 15.7044 25.6755 16.2258C25.3175 16.7266 24.8238 16.9914 24.2484 16.9914C23.4667 16.9914 22.5564 16.4977 21.5413 15.5225C19.7175 15.9037 17.5878 16.5838 15.8662 17.3366C15.3288 18.4771 14.8138 19.3957 14.3343 20.0694C13.6753 20.9919 13.107 21.4226 12.5445 21.4226ZM14.2558 18.1273C12.882 18.8994 12.3221 19.5339 12.2816 19.8913C12.2752 19.9505 12.2578 20.1061 12.5587 20.3362C12.6545 20.306 13.2138 20.0508 14.2558 18.1273ZM23.0225 15.2718C23.5464 15.6748 23.6743 15.8786 24.017 15.8786C24.1674 15.8786 24.5962 15.8722 24.7948 15.5951C24.8906 15.4608 24.9279 15.3746 24.9427 15.3283C24.8636 15.2866 24.7588 15.2017 24.1873 15.2017C23.8627 15.2023 23.4545 15.2165 23.0225 15.2718ZM18.2203 11.0405C17.7607 12.6309 17.1538 14.348 16.5013 15.9031C17.8449 15.3817 19.3055 14.9266 20.6773 14.6045C19.8095 13.5965 18.9423 12.3378 18.2203 11.0405ZM17.8301 5.60063C17.7671 5.62185 16.9751 6.73013 17.8918 7.66806C18.5019 6.30842 17.8578 5.59163 17.8301 5.60063Z"
                    fill="#CC4B4C"
                />
                <path
                    d="M30.8811 36H5.11921C4.59978 36 4.17871 35.5789 4.17871 35.0595V25.0714H31.8216V35.0595C31.8216 35.5789 31.4005 36 30.8811 36Z"
                    fill="#CC4B4C"
                />
                <path
                    d="M11.176 34.0714H10.1211V27.594H11.9841C12.2592 27.594 12.5318 27.6377 12.8012 27.7258C13.0705 27.8139 13.3122 27.9456 13.5263 28.1211C13.7404 28.2966 13.9133 28.5094 14.0451 28.7582C14.1769 29.007 14.2431 29.2866 14.2431 29.5978C14.2431 29.9263 14.1872 30.2233 14.076 30.4901C13.9647 30.7569 13.8092 30.9812 13.6099 31.1625C13.4106 31.3438 13.1702 31.4846 12.8892 31.5842C12.6083 31.6839 12.2972 31.7334 11.9577 31.7334H11.1754L11.176 34.0714ZM11.176 28.3937V30.96H12.1429C12.2715 30.96 12.3987 30.9381 12.5254 30.8938C12.6514 30.8501 12.7671 30.7781 12.8725 30.6784C12.978 30.5788 13.0628 30.4399 13.1271 30.2612C13.1914 30.0825 13.2235 29.8614 13.2235 29.5978C13.2235 29.4924 13.2087 29.3702 13.1798 29.2333C13.1502 29.0957 13.0905 28.9639 12.9998 28.8379C12.9085 28.7119 12.7812 28.6065 12.6173 28.5216C12.4534 28.4368 12.2361 28.3944 11.9667 28.3944L11.176 28.3937Z"
                    fill="white"
                />
                <path
                    d="M20.7121 30.6527C20.7121 31.1856 20.6549 31.6414 20.5404 32.0194C20.426 32.3974 20.2814 32.7137 20.1052 32.9689C19.9291 33.2241 19.7317 33.4247 19.5119 33.5713C19.292 33.7179 19.0799 33.8271 18.8748 33.9011C18.6697 33.9744 18.482 34.0213 18.3123 34.0419C18.1426 34.0611 18.0166 34.0714 17.9343 34.0714H15.4824V27.594H17.4335C17.9786 27.594 18.4576 27.6808 18.8703 27.8531C19.283 28.0254 19.6263 28.2561 19.8989 28.5429C20.1714 28.8296 20.3746 29.1568 20.5096 29.5226C20.6446 29.889 20.7121 30.2657 20.7121 30.6527ZM17.5833 33.2981C18.2981 33.2981 18.8137 33.0699 19.13 32.6128C19.4463 32.1557 19.6044 31.4936 19.6044 30.6264C19.6044 30.357 19.5723 30.0902 19.508 29.8266C19.4431 29.5631 19.319 29.3246 19.1345 29.1105C18.95 28.8964 18.6993 28.7235 18.383 28.5917C18.0667 28.4599 17.6566 28.3937 17.1526 28.3937H16.5374V33.2981H17.5833Z"
                    fill="white"
                />
                <path
                    d="M23.3135 28.3937V30.4329H26.0206V31.1535H23.3135V34.0714H22.2412V27.594H26.2925V28.3937H23.3135Z"
                    fill="white"
                />
            </svg>
        </div>
        <div class="mb-2 mr-[30px] flex flex-row-reverse content-end gap-[8px] lg:ms-auto">
            <a
                class="pdfpath flex"
                href=""
                target="_blank"
            >
                <label class="pdfname"></label>
                <svg
                    width="17"
                    height="18"
                    viewBox="0 0 17 18"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <mask
                        id="mask0_3243_893"
                        style="mask-type:alpha"
                        maskUnits="userSpaceOnUse"
                        x="0"
                        y="0"
                        width="17"
                        height="18"
                    >
                        <rect
                            y="0.43103"
                            width="17"
                            height="17"
                            fill="#D9D9D9"
                        />
                    </mask>
                    <g mask="url(#mask0_3243_893)">
                        <path
                            d="M4.45937 12.9289L3.71973 12.1892L10.69 5.21212H4.35314V4.14966H12.4989V12.2955H11.4365V5.95858L4.45937 12.9289Z"
                            fill="#1C1B1F"
                        />
                    </g>
                </svg>
            </a>
        </div>
    </template>

    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('multi-model') || \App\Helpers\Classes\MarketplaceHelper::isRegistered('model-council'))
        <template id="multi-model-response-head">
            <div class="multi-model-response-head mb-3 hidden w-full items-center gap-4">
                {{-- blade-formatter-disable --}}
                <svg class="shrink-0" width="15" height="14" viewBox="0 0 15 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg" > <path d="M4.76586 11.495L5.08728 11.4297C5.1773 11.4117 5.25828 11.363 5.31647 11.292C5.37466 11.221 5.40645 11.132 5.40645 11.0402C5.40645 10.9484 5.37466 10.8594 5.31647 10.7884C5.25828 10.7174 5.1773 10.6688 5.08728 10.6507L4.76586 10.5854C4.36954 10.505 4.00569 10.3097 3.71974 10.0237C3.43379 9.7378 3.23842 9.37397 3.15801 8.97767L3.09275 8.65626C3.07471 8.56625 3.02605 8.48525 2.95503 8.42706C2.88402 8.36888 2.79504 8.3371 2.70323 8.3371C2.61142 8.3371 2.52245 8.36888 2.45143 8.42706C2.38042 8.48525 2.33175 8.56625 2.3137 8.65626L2.24844 8.97767C2.16804 9.37397 1.97266 9.7378 1.68671 10.0237C1.40076 10.3097 1.03692 10.505 0.640595 10.5854L0.319189 10.6507C0.229171 10.6688 0.148173 10.7174 0.0899825 10.7884C0.0317923 10.8594 0 10.9484 0 11.0402C0 11.132 0.0317923 11.221 0.0899825 11.292C0.148173 11.363 0.229171 11.4117 0.319189 11.4297L0.640595 11.495C1.03692 11.5754 1.40076 11.7708 1.68671 12.0567C1.97266 12.3426 2.16804 12.7065 2.24844 13.1028L2.3137 13.4242C2.33175 13.5142 2.38042 13.5952 2.45143 13.6534C2.52245 13.7116 2.61142 13.7433 2.70323 13.7433C2.79504 13.7433 2.88402 13.7116 2.95503 13.6534C3.02605 13.5952 3.07471 13.5142 3.09275 13.4242L3.15801 13.1028C3.23842 12.7065 3.43379 12.3426 3.71974 12.0567C4.00569 11.7708 4.36954 11.5754 4.76586 11.495Z" /> <path d="M12.5567 5.67479L13.7396 5.43497C13.8576 5.41083 13.9637 5.34666 14.0399 5.25332C14.1161 5.15998 14.1577 5.04318 14.1577 4.92269C14.1577 4.80221 14.1161 4.68542 14.0399 4.59208C13.9637 4.49873 13.8576 4.43457 13.7396 4.41042L12.5567 4.1706C11.9869 4.05496 11.4637 3.77405 11.0526 3.36291C10.6414 2.95178 10.3605 2.42865 10.2449 1.85884L10.005 0.67604C9.98131 0.557759 9.91735 0.451342 9.82403 0.374886C9.73071 0.29843 9.61379 0.256653 9.49315 0.256653C9.37251 0.256653 9.25559 0.29843 9.16228 0.374886C9.06896 0.451342 9.00499 0.557759 8.98126 0.67604L8.74143 1.85884C8.62589 2.4287 8.345 2.95188 7.93384 3.36303C7.52267 3.77418 6.99947 4.05506 6.42959 4.1706L5.24674 4.41042C5.12869 4.43457 5.02259 4.49873 4.9464 4.59208C4.87022 4.68542 4.8286 4.80221 4.8286 4.92269C4.8286 5.04318 4.87022 5.15998 4.9464 5.25332C5.02259 5.34666 5.12869 5.41083 5.24674 5.43497L6.42959 5.67479C6.99947 5.79032 7.52267 6.07121 7.93384 6.48236C8.345 6.89351 8.62589 7.4167 8.74143 7.98656L8.98126 9.16936C9.00499 9.28764 9.06896 9.39404 9.16228 9.4705C9.25559 9.54695 9.37251 9.58874 9.49315 9.58874C9.61379 9.58874 9.73071 9.54695 9.82403 9.4705C9.91735 9.39404 9.98131 9.28764 10.005 9.16936L10.2449 7.98656C10.3605 7.41674 10.6414 6.89361 11.0526 6.48248C11.4637 6.07135 11.9869 5.79042 12.5567 5.67479Z" /> </svg>
				{{-- blade-formatter-enable --}}

                <span class="multi-model-response-name inline-block max-w-full truncate text-[12px] font-medium underline underline-offset-4"></span>

                {{-- <div class="multi-model-response-actions contents shrink-0">
                    <x-button
                        class="multi-model-response-regenerate size-8 shrink-0 rounded-full p-0"
                        size="none"
                        variant="outline"
                    >
                        <x-tabler-rotate class="size-4" />
                    </x-button>
            </div>
        </template>

        <template id="multi-model-response-foot">
            <div class="multi-model-response-foot hidden">
                <x-button
                    class="multi-model-response-accept mt-3 p-0 text-[12px] underline underline-offset-4"
                    size="none"
                    variant="none"
                >
                    <x-tabler-thumb-up class="size-4" />
                    {{ __('I prefer this response') }}
                </x-button>
            </div>
        </template>
    @endif

    <input
        id="assistant"
        type="hidden"
        value="{{ $category->assistant }}"
    />
    <input
        id="guest_id"
        type="hidden"
        value="{{ $apiUrl }}"
    >
    <input
        id="guest_search"
        type="hidden"
        value="{{ $apiSearch }}"
    >
    <input
        id="guest_search_id"
        type="hidden"
        value="{{ $apiSearchId }}"
    >
    <input
        id="guest_event_id"
        type="hidden"
        value="{{ $apikeyPart1 }}"
    >
    <input
        id="guest_look_id"
        type="hidden"
        value="{{ $apikeyPart2 }}"
    >
    <input
        id="guest_product_id"
        type="hidden"
        value="{{ $apikeyPart3 }}"
    >
    @if ($category->prompt_prefix != null)
        <input
            id="prompt_prefix"
            type="hidden"
            value="{{ $category->prompt_prefix }} you will now play a character and respond as that character (You will never break character). Your name is {{ $category->human_name }} but do not introduce by yourself as well as greetings."
        >
    @else
        <input
            id="prompt_prefix"
            type="hidden"
            value=""
        >
    @endif
@endsection

@push('script')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/prism/prism.css') }}"
    >
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/katex/katex.min.css') }}"
    >

    <script src="{{ custom_theme_url('/assets/libs/prism/prism.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it-container.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it-attrs.browser.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/html2pdf/html2pdf.bundle.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/turndown.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/katex/katex.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/vscode-markdown-it-katex/index.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/fslightbox/fslightbox.js') }}"></script>

    @include('panel.user.openai_chat.components.chat_js')

    @if ($category?->slug !== 'ai_pdf')
        <script>
            (() => {
                const promptEl = document.getElementById('prompt');
                if (!promptEl) return;

                const hasImageInClipboard = (items) => {
                    if (!items?.length) return false;
                    for (const item of items) {
                        if (item.kind === 'file' && item.type.startsWith('image/')) return true;
                    }
                    return false;
                };

                @guest
                    promptEl.addEventListener('paste', (e) => {
                        if (!hasImageInClipboard(e.clipboardData?.items)) return;
                        e.preventDefault();
                        toastr.warning('{{ __('Login to upload a document or image.') }}');
                    });
                @else
                    @if ($app_is_demo)
                        promptEl.addEventListener('paste', (e) => {
                            if (!hasImageInClipboard(e.clipboardData?.items)) return;
                            e.preventDefault();
                            toastr.info('{{ __('This feature is disabled in Demo version.') }}');
                        });
                    @else
                        promptEl.addEventListener('paste', (e) => {
                            const items = e.clipboardData?.items;
                            if (!items?.length) return;

                            const imageFiles = [];
                            for (const item of items) {
                                if (item.kind === 'file' && item.type.startsWith('image/')) {
                                    const file = item.getAsFile();
                                    if (file) imageFiles.push(file);
                                }
                            }
                            if (!imageFiles.length) return;

                            e.preventDefault();

                            imageFiles.forEach((file, idx) => {
                                const reader = new FileReader();
                                reader.onload = (ev) => {
                                    const name = file.name && file.name !== 'image.png'
                                        ? file.name
                                        : `pasted-image-${Date.now()}-${idx}.${(file.type.split('/')[1] || 'png')}`;
                                    if (typeof addFileToChat === 'function') {
                                        addFileToChat({ data: ev.target.result, name });
                                    }
                                };
                                reader.readAsDataURL(file);
                            });
                        });
                    @endif
                @endguest
            })();
        </script>
    @endif

    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('chatsV2', () => ({
                    mobileOptionsShow: false,
                    mobileSidebarShow: false,
                    mobilePromptNavShow: false,
                    mobileHeaderMoreOptionsShow: false,
                    promptNavVisible: false,
                    sidebarLocked: false,
                    sidebarHidden: true,
                    sidebarForceHidden: false,
                    realtimeStatus: 'idle',
                    promptLibraryShow: false,
                    promptFilter: 'all',
                    searchPromptStr: '',
                    prompt: '',
                    selectedTools: [],

                    init() {
                        const isDesktop = window.matchMedia('(min-width: 992px)').matches;

                        if (isDesktop) {
                            try {
                                const savedSidebarState = localStorage.getItem('garad_chat_sidebar_hidden');
                                this.sidebarHidden = savedSidebarState === '1';
                            } catch (e) {
                                this.sidebarHidden = false;
                            }
                        } else {
                            this.sidebarHidden = true;
                        }

                        Alpine.store('chatsV2', this);

                        document.addEventListener('chat:response-complete', async (e) => {
                            const {
                                messageId,
                                bubbleEl,
                                suggestionsHandled
                            } = e.detail;
                            if (!messageId || !bubbleEl || suggestionsHandled) return;

                            try {
                                const response = await fetch(`/dashboard/user/openai/chat/pro/message-suggestions/${messageId}`);
                                const data = await response.json();

                                if (data?.suggestions?.length) {
                                    const container = bubbleEl.querySelector('.lqd-chat-bubble-suggestions');
                                    if (container) {
                                        const alpineData = Alpine.$data(container);
                                        if (alpineData) {
                                            alpineData.suggestions = data.suggestions;
                                        }
                                    }
                                }
                            } catch (err) {
                                console.error('Failed to fetch suggestions', err);
                            }
                        });

                        if (localStorage.getItem('chatCouncilMode') == 1) {
                            this.addToolSelection('council');
                        }

                        this.$watch('$store.modelList?.councilMode', value => {
                            if (value) {
                                this.addToolSelection('council');
                            } else {
                                this.removeToolSelection('council');
                            }
                        })

                        this.$watch('selectedTools', selectedTools => {
                            if (Alpine.store('modelList') && Alpine.store('modelList').onCouncilModeChange) {
                                Alpine.store('modelList').onCouncilModeChange(selectedTools.includes('council'));
                            }
                        });
                    },
                    togglePromptLibraryShow() {
                        this.promptLibraryShow = !this.promptLibraryShow
                    },
                    changePromptFilter(filter) {
                        filter !== this.promptFilter && (this.promptFilter = filter)
                    },
                    setSearchPromptStr(str) {
                        this.searchPromptStr = str.trim().toLowerCase()
                    },
                    setPrompt(prompt) {
                        this.prompt = prompt
                    },
                    focusOnPrompt() {
                        this.$nextTick(() => this.$refs.prompt?.focus())
                    },
                    toggleMobileOptions() {
                        this.mobileOptionsShow = !this.mobileOptionsShow;
                        if (this.mobileOptionsShow) {
                            this.mobileSidebarShow = false;
                            this.mobilePromptNavShow = false;
                            this.mobileHeaderMoreOptionsShow = false;
                        }
                    },
                    toggleMobileSidebar() {
                        this.mobileSidebarShow = !this.mobileSidebarShow;
                        if (this.mobileSidebarShow) {
                            this.mobileOptionsShow = false;
                            this.mobilePromptNavShow = false;
                            this.mobileHeaderMoreOptionsShow = false;
                        }
                    },
                    toggleMobileHeaderMoreOptions() {
                        this.mobileHeaderMoreOptionsShow = !this.mobileHeaderMoreOptionsShow;
                        if (this.mobileHeaderMoreOptionsShow) {
                            this.mobileOptionsShow = false;
                            this.mobileSidebarShow = false;
                            this.mobilePromptNavShow = false;
                        }
                    },
                    toggleMobilePromptNav() {
                        this.mobilePromptNavShow = !this.mobilePromptNavShow;
                        if (this.mobilePromptNavShow) {
                            this.mobileOptionsShow = false;
                            this.mobileSidebarShow = false;
                            this.mobileHeaderMoreOptionsShow = false;
                        }
                    },
                    toggleSidebarHidden() {
                        this.sidebarHidden = !this.sidebarHidden;

                        if (window.matchMedia('(min-width: 992px)').matches) {
                            try {
                                localStorage.setItem(
                                    'garad_chat_sidebar_hidden',
                                    this.sidebarHidden ? '1' : '0'
                                );
                            } catch (e) {
                                // UI state persistence is optional; chat functionality must continue.
                            }
                        }
                    },
                    setRealtimeStatus(status) {
                        this.realtimeStatus = status
                    },
                    isShowRecent(element) {
                        let el = element;

                        do {
                            if (el && el.id && el.id == 'show-recent-btn') {
                                return true;
                            }
                        } while (el = el.parentElement);

                        return false;
                    },

                    onSuggestionClick(event) {
                        const button = event.currentTarget;
                        const suggestion = button.getAttribute('data-prompt');

                        if (!suggestion) {
                            return console.error('{{ __('No suggestion found') }}');
                        }

                        this.setPrompt(suggestion);

                        this.focusOnPrompt();

                        this.$nextTick(() => {
                            const submitBtn = document.querySelector('#send_message_button');
                            if (submitBtn) {
                                submitBtn.click();
                            }

                            this.setPrompt('');
                        });
                    },

                    toggleToolSelection(tool) {
                        const index = this.selectedTools.indexOf(tool);

                        if (index === -1) {
                            this.selectedTools.push(tool);
                        } else {
                            this.selectedTools.splice(index, 1);
                        }
                    },

                    addToolSelection(tool) {
                        if (!this.selectedTools.includes(tool)) {
                            this.selectedTools.push(tool);
                        }
                    },

                    removeToolSelection(tool) {
                        const index = this.selectedTools.indexOf(tool);

                        if (index !== -1) {
                            this.selectedTools.splice(index, 1);
                        }
                    },

                    hasToolSelected(tool) {
                        return this.selectedTools.includes(tool);
                    },

                    onNewChatCreated(event) {
                        this.selectedTools = [];
                    }
                }));

                Alpine.data('realtimeToggle', () => ({
                    init() {},
                    handleRealtimeChange(event) {
                        if (event.target.checked) {
                            toastr.success('{{ __('Real-Time data activated') }}');
                        } else {
                            toastr.warning('{{ __('Real-Time data deactivated') }}');
                        }
                    }
                }));
            });
        })();
    </script>

    @auth
        <script>
            (() => {
                const initDashboardReminder = () => {
                    // This is the ORIGINAL Dashboard grid button rendered by header-right-col.blade.php.
                    const dashboardButton = document.querySelector('.lqd-chat-pro-header a[title="Dashboard"]');

                    if (!dashboardButton || dashboardButton.dataset.garadReminderReady === '1') {
                        return;
                    }

                    dashboardButton.dataset.garadReminderReady = '1';
                    dashboardButton.classList.add('garad-dashboard-reminder-target');

                    const reminder = document.createElement('span');
                    reminder.className = 'garad-dashboard-reminder';
                    reminder.textContent = '{{ __('Dashboard') }}';
                    reminder.setAttribute('aria-hidden', 'true');
                    dashboardButton.appendChild(reminder);

                    let hideTimer = null;

                    const showReminder = () => {
                        window.clearTimeout(hideTimer);
                        reminder.classList.add('is-visible');

                        hideTimer = window.setTimeout(() => {
                            reminder.classList.remove('is-visible');
                        }, 2200);
                    };

                    // Normal tooltip feel when the user points at the existing icon.
                    dashboardButton.addEventListener('mouseenter', showReminder);
                    dashboardButton.addEventListener('focus', showReminder);

                    // Small automatic reminder every 10 seconds; no new button/icon is created.
                    window.setTimeout(showReminder, 1800);
                    window.setInterval(showReminder, 10000);
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initDashboardReminder, { once: true });
                } else {
                    initDashboardReminder();
                }
            })();
        </script>
    @endauth
@endpush