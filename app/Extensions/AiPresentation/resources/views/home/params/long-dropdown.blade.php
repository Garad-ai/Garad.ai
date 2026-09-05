<!-- TEXT LENGTH DROPDOWN (numeric input) -->
<x-dropdown.dropdown
    anchor="start"
    offsetY="15px"
>
    <x-slot:trigger
        class="outline-foreground/[7%]"
        variant="outline"
    >
        <x-tabler-pencil class="size-4" />
        <span x-text="textLengthLabel">
            {{ $default_text_length }}
        </span>
    </x-slot:trigger>
    <x-slot:dropdown
        class="max-h-60 overflow-y-auto rounded-lg bg-background p-2 shadow-lg"
    >
        <div class="flex flex-col">
            <div class="text-muted-foreground px-1 text-2xs text-blue-600">
                <x-tabler-info-circle class="mr-1 inline size-4" />
                {{ __('How much text each card contains') }}
            </div>

            <template
                x-for="preset in ['Brief', 'Medium', 'Detailed', 'Extensive']"
                :key="preset"
            >
                <a
                    class="flex items-center gap-2 border-b p-2 text-2xs font-medium text-heading-foreground transition-all last:border-0 hover:bg-foreground/5"
                    href="#"
                    @click.prevent="setTextLength(preset)"
                >
                    <span x-text="preset"></span>
                </a>
            </template>
        </div>
    </x-slot:dropdown>
</x-dropdown.dropdown>

<input
    type="hidden"
    name="text_length"
    x-model="textLengthLabel"
>
