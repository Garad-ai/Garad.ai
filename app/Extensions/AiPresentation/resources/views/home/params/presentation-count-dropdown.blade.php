<!-- PRESENTATION COUNT DROPDOWN -->
<x-dropdown.dropdown
    anchor="start"
    offsetY="15px"
>
    <x-slot:trigger
        class="outline-foreground/[7%]"
        variant="outline"
    >
        <x-tabler-presentation class="size-4" />
        <span x-text="presentationCount">
            {{ $default_presentation_count }}
        </span>
    </x-slot:trigger>
    <x-slot:dropdown
        class="max-h-60 overflow-y-auto rounded-lg bg-background p-2 shadow-lg"
    >
        <template
            x-for="number in 60"
            :key="number"
        >
            <a
                class="flex items-center gap-2 border-b p-2 text-2xs font-medium text-heading-foreground transition-all last:border-0 hover:bg-foreground/5"
                href="#"
                @click.prevent="presentationCount = number"
            >
                <span x-text="number"></span><span>{{ __('Cards') }}</span>
            </a>
        </template>
    </x-slot:dropdown>
</x-dropdown.dropdown>

<input
    type="hidden"
    name="presentation_count"
    x-model="presentationCount"
>
