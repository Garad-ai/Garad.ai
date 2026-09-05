<x-dropdown.dropdown
    class:dropdown-dropdown="backdrop-blur-lg"
    anchor="start"
    offsetY="20px"
>
    <x-slot:trigger
        class="text-2xs font-medium"
        variant="link"
    >
        <span x-text="currentModel ? currentModel.label : '{{ __('Select Model') }}'">
            {{ __('Select Model') }}
        </span>
        <x-tabler-chevron-down class="size-4 transition group-[&.lqd-is-active]/dropdown:rotate-180" />
    </x-slot:trigger>

    <x-slot:dropdown
        class="max-h-60 min-w-[min(270px,100vw)] space-y-1 overflow-y-auto rounded-lg border-none bg-background/50 p-2 shadow-xl shadow-black/10"
    >
        <span class="block px-4 py-1.5 text-2xs font-medium opacity-50">
            {{ __('Select AI Model') }}
        </span>
        @foreach ($activeImageModels ?? [] as $key => $model)
            <button
                class="flex w-full justify-between gap-2.5 rounded-lg px-4 py-2 text-start text-xs transition hover:bg-foreground/5 [&.selected]:bg-foreground/5"
                type="button"
                @click="selectedModel = '{{ $key }}'; Alpine.$data(document.querySelector('#submitForm') ?? document.querySelector('#chatImageProForm')).initializeFormValues(); toggle('collapse')"
                :class="{ 'selected': selectedModel === '{{ $key }}' }"
            >
                <x-tabler-check
                    class="size-5 shrink-0"
                    ::class="{ 'opacity-0': selectedModel !== '{{ $key }}' }"
                />
                <span class="block grow">
                    {{ $model['label'] }}
                    @if (isset($model['description']) && $model['description'])
                        <span class="block text-2xs opacity-70">
                            {{ $model['description'] }}
                        </span>
                    @endif
                </span>
            </button>
        @endforeach
    </x-slot:dropdown>
</x-dropdown.dropdown>
