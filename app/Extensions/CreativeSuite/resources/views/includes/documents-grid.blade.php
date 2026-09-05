<template x-for="item in newItems">
    <div
        class="lqd-cs-doc-item image-result group/item w-full"
        :data-id="item.id"
    >
        <div
            class="relative mb-4 grid aspect-[1/0.85] w-full place-items-center rounded-card bg-heading-foreground/5 transition-all group-hover/item:shadow-[0_38px_55px_rgba(0,0,0,0.1)]">
            <img
                class="mx-auto h-auto w-10/12"
                :src="item.preview"
            >
            <div class="absolute inset-0 flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover/item:opacity-100">
                <x-button
                    class="size-9 bg-background text-foreground"
                    hover-variant="primary"
                    size="none"
                    href="#"
                    @click.prevent="loadDocument(item.id)"
                >
                    <x-tabler-pencil-minus class="size-5" />
                </x-button>

                <div
                    class="relative"
                    x-data="{ open: false }"
                    @click.outside="open = false"
                >
                    <x-button
                        class="size-9 bg-background text-foreground"
                        hover-variant="primary"
                        size="none"
                        @click.prevent="open = !open"
                    >
                        <x-tabler-dots class="size-5" />
                    </x-button>

                    <div
                        class="absolute -start-1 top-full mt-1 flex min-w-32 flex-col items-start gap-2 rounded-md bg-background p-3 shadow-lg shadow-black/5"
                        x-cloak
                        x-show="open"
                    >
                        <p class="mb-0 w-full border-b pb-2.5 text-2xs/none font-medium text-foreground/65">
                            {{ __('Actions') }}
                        </p>

                        <x-button
                            class="w-full justify-start text-2xs font-medium"
                            variant="link"
                            @click.prevent="duplicateDocument(item.id)"
                        >
                            <x-tabler-copy class="size-4" />
                            {{ __('Duplicate') }}
                        </x-button>
                        <x-button
                            class="w-full justify-start text-2xs font-medium"
                            variant="link"
                            @click.prevent="deleteDocument(item.id)"
                        >
                            <x-tabler-circle-minus class="size-4 text-red-500" />
                            {{ __('Delete') }}
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
        <h5
            class="mb-0 w-full truncate hyphens-auto break-all text-2xs font-medium"
            x-text="item.name"
            :title="item.name"
        ></h5>
        <time
            class="text-2xs font-medium opacity-60"
            x-text="new Date().toLocaleTimeString()"
        ></time>
    </div>
</template>

@foreach ($documents as $doc)
    <div
        class="lqd-cs-doc-item image-result group/item w-full"
        data-id="{{ $doc->id }}"
    >
        <div
            class="relative mb-4 grid aspect-[1/0.85] w-full place-items-center rounded-card bg-heading-foreground/5 transition-all group-hover/item:shadow-[0_38px_55px_rgba(0,0,0,0.1)]">
            @if ($doc->preview_url)
                <img
                    class="mx-auto h-auto w-10/12"
                    src="{{ ThumbImage($doc->preview_url) }}"
                >
                <div class="absolute inset-0 flex items-center justify-center gap-1 opacity-0 transition-opacity group-hover/item:opacity-100">
                    <x-button
                        class="size-9 bg-background text-foreground"
                        hover-variant="primary"
                        size="none"
                        href="#"
                        @click.prevent="loadDocument({{ $doc->id }})"
                    >
                        <x-tabler-pencil-minus class="size-5" />
                    </x-button>

                    <div
                        class="relative"
                        x-data="{ open: false }"
                        @click.outside="open = false"
                    >
                        <x-button
                            class="size-9 bg-background text-foreground"
                            hover-variant="primary"
                            size="none"
                            @click.prevent="open = !open"
                        >
                            <x-tabler-dots class="size-5" />
                        </x-button>

                        <div
                            class="absolute -start-1 top-full mt-1 flex min-w-32 flex-col items-start gap-2 rounded-md bg-background p-3 shadow-lg shadow-black/5"
                            x-cloak
                            x-show="open"
                        >
                            <p class="mb-0 w-full border-b pb-2.5 text-2xs/none font-medium text-foreground/65">
                                {{ __('Actions') }}
                            </p>

                            <x-button
                                class="w-full justify-start text-2xs font-medium"
                                variant="link"
                                @click.prevent="duplicateDocument({{ $doc->id }})"
                            >
                                <x-tabler-copy class="size-4" />
                                {{ __('Duplicate') }}
                            </x-button>
                            <x-button
                                class="w-full justify-start text-2xs font-medium"
                                variant="link"
                                @click.prevent="deleteDocument({{ $doc->id }})"
                            >
                                <x-tabler-circle-minus class="size-4 text-red-500" />
                                {{ __('Delete') }}
                            </x-button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <h5
            class="mb-0 w-full truncate hyphens-auto break-all text-2xs font-medium"
            title="{{ $doc->name }}"
        >
            {{ $doc->name }}
        </h5>
        <time class="text-2xs font-medium opacity-60">
            {{ \Carbon\Carbon::parse($doc->created_at)->diffForHumans() }}
        </time>
    </div>
@endforeach
