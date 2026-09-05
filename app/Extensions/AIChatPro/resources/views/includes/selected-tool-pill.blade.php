<div
    class="flex max-h-[34px] max-w-60 items-center gap-1.5 whitespace-nowrap rounded-button bg-foreground/[7%] px-2 py-1 text-sm font-medium leading-tight md:px-3 md:py-2"
    :title="toolLabel"
    x-data="{
        toolName: $data.toolName ? $data.toolName : '{{ $tool_name ?? 'tool' }}',
        toolLabel: $data.toolLabel ? $data.toolLabel : '{{ $label ?? '' }}',
    }"
>
    <x-dynamic-component
        class="size-4.5 shrink-0 opacity-75"
        :component="$icon"
    />
    <span
        class="w-full truncate max-lg:hidden"
        x-text="toolLabel"
    ></span>
    <button
        class="inline-grid size-5.5 shrink-0 place-items-center rounded-full transition hover:scale-105 hover:bg-red-500 hover:text-white hover:shadow-xl hover:shadow-black/5"
        type="button"
        @click.prevent="removeToolSelection(toolName)"
    >
        <x-tabler-x class="size-3.5" />
    </button>
</div>
