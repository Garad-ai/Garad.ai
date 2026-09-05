{{-- Sync-to-CRM button + progress modal. Requires $source slug. --}}
<div
    x-data="{
        open: false,
        running: false,
        error: false,
        done: false,
        total: 0,
        processed: 0,
        synced: 0,
        url: '{{ route('dashboard.user.crm.contacts.sync', $source) }}',
        token: '{{ csrf_token() }}',
        percent() {
            return this.total > 0 ? Math.round((this.processed / this.total) * 100) : (this.done ? 100 : 0);
        },
        async start() {
            this.open = true;
            this.running = true;
            this.error = false;
            this.done = false;
            this.total = 0;
            this.processed = 0;
            this.synced = 0;

            let offset = 0;

            try {
                while (true) {
                    let formData = new FormData();
                    formData.append('_token', this.token);
                    formData.append('offset', offset);

                    let res = await fetch(this.url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: formData,
                    });
                    let data = await res.json();

                    if (data.status !== 'success') {
                        this.error = true;
                        this.running = false;
                        toastr.error(data.message || '{{ __('Something went wrong.') }}');
                        return;
                    }

                    this.total = data.total;
                    this.processed = data.processed;
                    this.synced += data.batchSynced;

                    if (data.done) {
                        this.done = true;
                        this.running = false;
                        return;
                    }

                    offset = data.processed;
                }
            } catch (e) {
                this.error = true;
                this.running = false;
                toastr.error('{{ __('Something went wrong.') }}');
            }
        }
    }"
>
    <x-button
        type="button"
        variant="ghost-shadow"
        @click="start()"
    >
        <x-tabler-refresh class="size-4" />
        {{ __('Sync to CRM') }}
    </x-button>

    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        @keydown.escape.window="if (!running) open = false"
    >
        <div
            class="w-full max-w-md rounded-card bg-card-background shadow-2xl"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-card-border px-6 py-4">
                <h3 class="text-base font-semibold text-foreground">
                    {{ __('Syncing to CRM') }}
                </h3>
                <button
                    type="button"
                    class="text-label transition-colors hover:text-foreground disabled:opacity-40"
                    x-bind:disabled="running"
                    @click="open = false"
                >
                    <x-tabler-x class="size-5" />
                </button>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div class="flex items-center justify-between text-sm">
                    <span
                        class="text-label"
                        x-show="running"
                    >{{ __('Syncing your contacts…') }}</span>
                    <span
                        class="font-medium text-foreground"
                        x-show="done"
                        x-cloak
                    >{{ __('Sync complete') }}</span>
                    <span
                        class="font-medium text-red-500"
                        x-show="error"
                        x-cloak
                    >{{ __('Sync failed') }}</span>
                    <span
                        class="text-label"
                        x-text="percent() + '%'"
                    ></span>
                </div>

                <div class="h-2 w-full overflow-hidden rounded-full bg-surface">
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-300"
                        x-bind:style="`width: ${percent()}%`"
                    ></div>
                </div>

                <p class="text-xs text-label">
                    <span x-text="processed"></span>
                    {{ __('of') }}
                    <span x-text="total"></span>
                    {{ __('processed') }} ·
                    <span
                        class="font-medium text-foreground"
                        x-text="synced"
                    ></span>
                    {{ __('added or updated in CRM') }}
                </p>

                <div
                    class="flex justify-end gap-3 pt-2"
                    x-show="!running"
                    x-cloak
                >
                    <x-button
                        type="button"
                        @click="open = false"
                    >
                        {{ __('Done') }}
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</div>
