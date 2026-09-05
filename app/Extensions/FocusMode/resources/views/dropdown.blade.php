<x-dropdown.dropdown
    class="ai-tools-dropdown"
    class:dropdown="rounded-3xl"
    anchor="start"
    offsetY="20px"
    :teleport="false"
>
    <x-slot:trigger
        class="before:-star[12.5%] relative z-10 size-9 p-0 text-heading-foreground before:absolute before:inset-0 before:-top-[12.5%] before:h-[150%] before:w-[150%]"
        variant="link"
        title="{{ __('AI Tools') }}"
    >
        <x-tabler-grid-dots class="size-6" />
    </x-slot:trigger>

    <x-slot:dropdown
        class="max-h-[450px] min-w-80 overflow-y-auto"
    >
        @php
            $items = app(\App\Services\Common\MenuService::class)->generate();
            $isAdmin = \Auth::user()?->isAdmin();


            $ai_tools_list = [
                'dashboard',
                'documents',
                'ai_writer',
                'ai_editor',
                'ai_detector_extension',
                'scheduled_posts_extension',
                'ai_plagiarism_extension',
                'ai_image_generator',
                'ai_social_media_extension',
                'photo_studio_extension',
                'ai_article_wizard',
                'ai_web_chat_extension',
                'seo_tool_extension',
                'ai_pdf',
                // 'chat_settings_extension',
                'ai_vision',
                'ai_rewriter',
                'ai_chat_image',
                'ai_chat_all',
                'ai_code_generator',
                'ai_youtube',
                'ai_rss',
                'ai_speech_to_text',
                'ai_voiceover',
                'ai_voiceover_clone',
                'ai_social_media_extension',
            ];
            $ai_tools = [];

            foreach ($items as $item) {
                if (data_get($item, 'type') === 'item' && in_array(data_get($item, 'key'), $ai_tools_list)) {
                    $ai_tools[] = $item;
                }
            }
        @endphp



        <h3 class="m-0 border-b border-border py-5 text-center">
            {{ __('AI Tools') }}
        </h3>

        <ul class="ai-tools-list grid grid-cols-3 gap-2 p-3">
            @foreach ($ai_tools as $ai_tool)
                @includeIf('default.components.navbar.partials.types.' . $ai_tool['type'], ['item' => $ai_tool])
            @endforeach
        </ul>
    </x-slot:dropdown>
</x-dropdown.dropdown>
