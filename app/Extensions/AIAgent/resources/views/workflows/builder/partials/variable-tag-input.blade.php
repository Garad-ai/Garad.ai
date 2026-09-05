@php($multiline = $multiline ?? false)
@php($required = $required ?? false)
@php($placeholder = $placeholder ?? '')
<div>
    <label class="lqd-input-label mb-3 flex cursor-pointer items-center gap-2 text-2xs font-medium leading-none text-label">
        {{ $label }}
        @if ($required)
            <span class="ms-1 text-[10px] font-normal text-red-500">{{ __('required') }}</span>
        @endif
    </label>
    <div
        class="relative rounded-input border border-input-border bg-input-background focus-within:ring-1 focus-within:ring-primary/30"
        x-data="variableTagInput({ configKey: '{{ $configKey }}', stepRef: selectedStep, multiline: {{ $multiline ? 'true' : 'false' }} })"
    >
        <div
            class="px-3 py-2 pe-8 text-sm outline-none"
            :class="multiline ? 'min-h-[4.5rem] whitespace-pre-wrap break-words' : 'min-h-[2.25rem] overflow-x-auto whitespace-nowrap'"
            data-placeholder="{{ $placeholder }}"
            x-ref="editor"
            contenteditable="true"
            @input.debounce.50ms="syncToModel()"
            @keydown="handleKeydown($event)"
            @paste="handlePaste($event)"
            @click="handleClick($event)"
        ></div>
        <button
            class="absolute end-2 top-2 rounded p-0.5 text-foreground/40 hover:bg-foreground/5 hover:text-foreground/70"
            type="button"
            @click.stop="menuOpen = !menuOpen"
        ><x-tabler-braces class="size-3.5" /></button>
        <div
            class="absolute end-0 top-full z-50 mt-1 min-w-[180px] rounded-lg border border-border bg-background p-1.5 shadow-lg"
            x-show="menuOpen"
            x-cloak
            @click.outside="menuOpen = false"
        >
            <template x-for="v in availableVariables" :key="v.name">
                <button
                    class="block w-full rounded px-2 py-1 text-start hover:bg-foreground/5"
                    type="button"
                    @click="insertVariable(v)"
                >
                    <span class="block font-mono text-[10px] text-heading-foreground" x-text="v.name"></span>
                    <span class="block text-[9px] text-foreground/40" x-text="v.description"></span>
                </button>
            </template>
        </div>
    </div>
</div>
