@php
    $route = 'dashboard.user.chat-setting.chat-template.create';
    $customChat = \Illuminate\Support\Facades\Route::has($route) && setting('chat_setting_for_customer', 1) == 1;
@endphp

@push('site-header-left-col')
    @include('panel.user.openai_chat.components.chat_category_dropdown')

    <hr class="inline-block h-4 w-px shrink-0 bg-border" />

    @auth
        @if (!$isOtherCategories || $customChat)
            <div
                class="flex [&_.label-added_.select-model-label]:hidden [&_.lqd-modal>.lqd-btn>span]:w-full [&_.lqd-modal>.lqd-btn>span]:overflow-hidden [&_.lqd-modal>.lqd-btn>span]:text-ellipsis [&_.lqd-modal>.lqd-btn]:w-32 [&_.lqd-modal>.lqd-btn]:justify-start [&_.lqd-modal>.lqd-btn]:overflow-hidden [&_.lqd-modal>.lqd-btn]:text-ellipsis [&_.lqd-modal>.lqd-btn]:whitespace-nowrap [&_.lqd-modal>.lqd-btn]:bg-transparent [&_.lqd-modal>.lqd-btn]:p-0 [&_.lqd-modal>.lqd-btn]:text-heading-foreground [&_.lqd-modal>.lqd-btn]:shadow-none [&_.lqd-modal>.lqd-btn]:hover:translate-y-0 [&_.lqd-modal>.lqd-btn]:hover:text-heading-foreground [&_.lqd-modal>.lqd-btn_svg]:shrink-0">
                @includeWhen(!$isOtherCategories, 'components.select-ai-model-list')
            </div>

            <hr class="inline-block h-4 w-px bg-border" />
        @endif
    @else
        <div
            class="flex [&_.label-added_.select-model-label]:hidden [&_.lqd-modal>.lqd-btn>span]:w-full [&_.lqd-modal>.lqd-btn>span]:overflow-hidden [&_.lqd-modal>.lqd-btn>span]:text-ellipsis [&_.lqd-modal>.lqd-btn]:w-32 [&_.lqd-modal>.lqd-btn]:justify-start [&_.lqd-modal>.lqd-btn]:overflow-hidden [&_.lqd-modal>.lqd-btn]:text-ellipsis [&_.lqd-modal>.lqd-btn]:whitespace-nowrap [&_.lqd-modal>.lqd-btn]:bg-transparent [&_.lqd-modal>.lqd-btn]:p-0 [&_.lqd-modal>.lqd-btn]:text-heading-foreground [&_.lqd-modal>.lqd-btn]:shadow-none [&_.lqd-modal>.lqd-btn]:hover:translate-y-0 [&_.lqd-modal>.lqd-btn]:hover:text-heading-foreground [&_.lqd-modal>.lqd-btn_svg]:shrink-0">
            @includeWhen(!$isOtherCategories, 'components.select-ai-model-list-un-auth')
        </div>
    @endauth

    <x-dropdown.dropdown
        class:dropdown-dropdown="max-lg:end-auto max-lg:start-0"
        offsetY="20px"
        :teleport="false"
    >
        <x-slot:trigger>
            <x-tabler-dots class="size-6" />
        </x-slot:trigger>
        <x-slot:dropdown
            class="min-w-52 whitespace-nowrap"
        >
            <p
                class="m-0 translate-y-1 border-b border-heading-foreground/5 px-5 py-2 text-2xs font-medium text-heading-foreground/60 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[40ms]">
                {{ __('More Options') }}
            </p>
            <div class="p-2">
                <div
                    class="group relative flex translate-y-1 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[80ms]"
                    id="show_export_btns"
                >
                    <x-button
                        class="w-full cursor-default justify-start rounded-md px-3.5 py-2 text-2xs font-medium text-heading-foreground/60 hover:transform-none hover:bg-heading-foreground/[3%] hover:text-heading-foreground hover:shadow-none"
                        variant="none"
                    >
                        {{ __('Export') }}
                    </x-button>
                    <div
                        class="invisible absolute start-full top-0 flex min-w-44 translate-y-1 flex-col rounded-dropdown bg-dropdown-background p-2 opacity-0 shadow-lg shadow-black/5 transition-all group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100"
                        id="export_btns"
                    >
                        <button
                            class="chat-download flex items-center gap-2 rounded-md px-3.5 py-2 text-start text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                            data-doc-type="pdf"
                        >
                            <x-tabler-file-type-pdf class="size-[18px] text-heading-foreground" />
                            {{ __('PDF') }}
                        </button>
                        <button
                            class="chat-download flex items-center gap-2 rounded-md px-3.5 py-2 text-start text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                            data-doc-type="doc"
                        >
                            <x-tabler-brand-office class="size-[18px] text-heading-foreground" />
                            {{ __('Word') }}
                        </button>
                        <button
                            class="chat-download flex items-center gap-2 rounded-md px-3.5 py-2 text-start text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground"
                            data-doc-type="txt"
                        >
                            <x-tabler-file-text class="size-[18px] text-heading-foreground" />
                            {{ __('Txt') }}
                        </button>
                    </div>
                </div>

                @auth
                    <div
                        class="translate-y-1 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[120ms]">
                        <div
                            class="relative cursor-pointer rounded-md px-3.5 py-2 text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground [&_.lqd-chat-share-modal-trigger]:absolute [&_.lqd-chat-share-modal-trigger]:inset-0 [&_.lqd-chat-share-modal-trigger]:z-2 [&_.lqd-chat-share-modal-trigger]:opacity-0">
                            @includeFirst(['chat-share::share-button-include', 'panel.user.openai_chat.includes.share-button-include', 'vendor.empty'])
                            <div class="lqd-btn inline-flex items-center first:hidden">
                                {{ __('Share') }}
                            </div>
                        </div>
                    </div>
                @endauth
            </div>
        </x-slot:dropdown>
    </x-dropdown.dropdown>
@endpush
