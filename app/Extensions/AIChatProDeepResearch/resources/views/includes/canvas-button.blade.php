<button
    class="lqd-chat-canvas-btn group"
    id="{{ $category->slug == 'ai_vision' && $app_is_demo ? '' : 'create_canvas_button' }}"
    :class="{ 'active': hasToolSelected('canvas') }"
    type="button"
    title="{{ __('Canvas') }}"
    @if ($category->slug == 'ai_vision' && $app_is_demo) onclick="return toastr.info('{{ __('This feature is disabled in Demo version.') }}')" @endif
    @click="toggleToolSelection('canvas')"
>
    <x-tabler-pencil-minus class="size-4.5 shrink-0 opacity-75" />

    <span class="truncate">
        {{ __('Canvas') }}
    </span>

    <x-tabler-check class="invisible ms-auto size-4.5 shrink-0 group-[&.active]:visible" />
</button>
