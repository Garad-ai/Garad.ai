<x-card
    class="flex flex-col overflow-hidden"
    class:body="flex flex-col grow p-0"
>
    <x-slot:head
        class="flex items-center justify-between px-5 py-4"
    >
        <h4 class="m-0 text-xs font-medium">
            {{ __('Top Contacts') }}
        </h4>
        <x-button
            class="text-[12px] font-medium"
            variant="link"
            href="{{ route('dashboard.user.crm.contacts.index') }}"
        >
            {{ __('View All') }}
            <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
        </x-button>
    </x-slot:head>

    @forelse ($topContacts as $contact)
        @php
            $initials = strtoupper(mb_substr($contact->first_name, 0, 1) . mb_substr($contact->last_name, 0, 1));
            $colors = ['bg-primary/10 text-primary', 'bg-emerald-500/10 text-emerald-600', 'bg-amber-500/10 text-amber-600', 'bg-blue-500/10 text-blue-600'];
            $colorClass = $colors[$loop->index % count($colors)];
        @endphp

        <div @class([
            'contact-item group/fav relative flex items-center gap-3 border-b bg-background transition-colors last:border-b-0 hover:bg-foreground/[.02]',
            'is-fav' => $contact->is_favorite,
        ])>
            <button
                class="invisible absolute start-2 top-2 z-10 inline-grid size-7 place-items-center rounded-full border opacity-0 backdrop-blur-md transition-all hover:scale-110 group-hover/fav:visible group-hover/fav:opacity-100 group-[&.is-fav]/fav:visible group-[&.is-fav]/fav:opacity-100"
                type="button"
                onclick="crmToggleFavorite('contact', {{ $contact->id }}, this)"
                title="{{ __('Toggle favorite') }}"
            >
                <x-tabler-star-filled
                    class="invisible col-start-1 col-end-1 row-start-1 row-end-1 size-4 text-amber-400 opacity-0 transition group-[&.is-fav]/fav:visible group-[&.is-fav]/fav:opacity-100"
                />
                <x-tabler-star
                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 text-foreground/20 transition group-[&.is-fav]/fav:invisible group-[&.is-fav]/fav:opacity-0"
                />
            </button>

            <a
                class="flex w-full items-center gap-3 px-5 py-4"
                href="{{ route('dashboard.user.crm.contacts.show', $contact->id) }}"
            >
                @if ($contact->avatar_url)
                    <img
                        class="size-10 shrink-0 rounded-full object-cover"
                        src="{{ $contact->avatar_url }}"
                        alt="{{ $contact->full_name }}"
                    />
                @else
                    <span class="{{ $colorClass }} inline-grid size-10 shrink-0 place-items-center rounded-full text-center text-sm font-bold">
                        {{ $initials }}
                    </span>
                @endif

                <span class="flex w-full min-w-0 flex-wrap items-center justify-between gap-1 overflow-hidden">
                    <span class="truncate font-medium">
                        {{ $contact->full_name }}
                    </span>
                    <span class="truncate text-xs opacity-65">
                        {{ $contact->company?->name ?? ($contact->job_title ?? __('Contact')) }}
                    </span>
                </span>
            </a>
        </div>
    @empty
        <div class="p-5">
            <x-empty-state
                icon="tabler-users"
                title="{{ __('No contacts yet') }}"
                description="{{ __('Start adding contacts to see them here.') }}"
            />
        </div>
    @endforelse
</x-card>

@push('script')
    <script>
        function crmToggleFavorite(type, id, btn) {
            const parent = btn.closest('.contact-item');
            const isFav = parent.classList.contains('is-fav');

            parent.classList.toggle('is-fav', !isFav);

            $.ajax({
                type: 'POST',
                url: '{{ route('dashboard.user.crm.toggleFavorite') }}',
                data: {
                    type: type,
                    id: id,
                    _token: '{{ csrf_token() }}'
                },
                success: function(data) {
                    parent.classList.toggle('is-fav', data.is_favorite);
                },
                error: function() {
                    parent.classList.toggle('is-fav', isFav);
                },
            });
        }
    </script>
@endpush
