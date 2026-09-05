@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', trans('Contact'))

@section('titlebar_actions')
    <div
        class="flex gap-4 lg:justify-end"
        x-data="{
            open: false,
            step: 'upload',
            loading: false,
            segmentId: '',
            contactId: '',
            headers: [],
            mapping: { name: '', phone: '', country_code: '' },
            valid: [],
            invalid: [],
            selectedIndices: [],
            fileInput: null,
            mappingValid() {
                return this.mapping.name !== '' && this.mapping.phone !== '';
            },
            selectedCount() {
                return this.selectedIndices.filter(v => v).length;
            },
            autoDetect() {
                let known = {
                    name: ['name', 'full name', 'fullname', 'contact name', 'first name', 'firstname'],
                    phone: ['phone', 'mobile', 'tel', 'telephone', 'phone number', 'mobile number'],
                    country_code: ['country_code', 'country code', 'cc', 'countrycode', 'dial code'],
                };
                for (let field in known) {
                    let match = this.headers.find(h => known[field].includes(h.toLowerCase()));
                    this.mapping[field] = match !== undefined ? match : '';
                }
            },
            fetchHeaders() {
                if (!this.fileInput || !this.fileInput.files.length) {
                    toastr.error('{{ __('Please select a CSV file.') }}');
                    return;
                }
                this.loading = true;
                let formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('file', this.fileInput.files[0]);
                fetch('{{ route('dashboard.user.marketing-bot.contact-list.import.headers') }}', {
                    method: 'POST',
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.status === 'error') { toastr.error(data.message); return; }
                    this.headers = data.headers;
                    this.autoDetect();
                    this.step = 'map';
                })
                .catch(() => { this.loading = false; toastr.error('{{ __('Something went wrong.') }}'); });
            },
            buildMappingFormData(formData) {
                if (this.mapping.name)         formData.append('mapping[name]', this.mapping.name);
                if (this.mapping.phone)        formData.append('mapping[phone]', this.mapping.phone);
                if (this.mapping.country_code) formData.append('mapping[country_code]', this.mapping.country_code);
            },
            previewCsv() {
                if (!this.mappingValid()) return;
                this.loading = true;
                let formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('file', this.fileInput.files[0]);
                this.buildMappingFormData(formData);
                fetch('{{ route('dashboard.user.marketing-bot.contact-list.import.preview') }}', {
                    method: 'POST',
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.status === 'error') { toastr.error(data.message); return; }
                    this.valid           = data.valid;
                    this.invalid         = data.invalid;
                    this.selectedIndices = data.valid.map(() => true);
                    this.step            = 'preview';
                })
                .catch(() => { this.loading = false; toastr.error('{{ __('Something went wrong.') }}'); });
            },
            confirmImport() {
                if (!this.fileInput || !this.fileInput.files.length) return;
                this.loading = true;
                let formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('file', this.fileInput.files[0]);
                this.buildMappingFormData(formData);
                if (this.segmentId) formData.append('segment_id', this.segmentId);
                if (this.contactId) formData.append('contact_id', this.contactId);
                this.valid
                    .filter((_, i) => this.selectedIndices[i])
                    .forEach(r => formData.append('include_phones[]', r.phone));
                fetch('{{ route('dashboard.user.marketing-bot.contact-list.import') }}', {
                    method: 'POST',
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.status === 'error') { toastr.error(data.message); return; }
                    toastr.success(data.message);
                    this.open = false;
                    this.step = 'upload';
                    setTimeout(() => window.location.reload(), 600);
                })
                .catch(() => { this.loading = false; toastr.error('{{ __('Something went wrong.') }}'); });
            },
            reset() {
                this.step            = 'upload';
                this.segmentId       = '';
                this.contactId       = '';
                this.valid           = [];
                this.invalid         = [];
                this.selectedIndices = [];
                this.fileInput       = null;
                this.headers         = [];
                this.mapping         = { name: '', phone: '', country_code: '' };
            }
        }"
    >
        @if ($app_is_demo)
            <x-button
				type="button"
                variant="ghost-shadow"
                onclick="return toastr.info('{{ __('CSV import is disabled in demo mode.') }}')"
            >
                <x-tabler-upload class="size-4" />
                {{ __('Import CSV') }}
            </x-button>
        @else
            <x-button
				type="button"
                variant="ghost-shadow"
                @click="open = true; reset()"
            >
                <x-tabler-upload class="size-4" />
                {{ __('Import CSV') }}
            </x-button>
        @endif

        @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('crm') && setting('crm_contact_sync_enabled', '0') && setting('crm_sync_marketing_whatsapp', '0'))
            @include('crm::partials.sync-button', ['source' => 'marketing_whatsapp'])
        @endif

        <x-button href="{{ route('dashboard.user.marketing-bot.contact-list.create') }}">
            <x-tabler-plus class="size-4" />
            {{ __('Add New Contact') }}
        </x-button>

        {{-- CSV Import Modal --}}
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @keydown.escape.window="open = false"
        >
            <div
                class="w-full max-w-2xl rounded-card bg-card-background shadow-2xl"
                @click.stop
            >
                {{-- Modal header --}}
                <div class="flex items-center justify-between border-b border-card-border px-6 py-4">
                    <h3 class="text-base font-semibold text-foreground">
                        {{ __('Import WhatsApp Contacts') }}
                    </h3>
                    <button
                        type="button"
                        class="text-label transition-colors hover:text-foreground"
                        @click="open = false"
                    >
                        <x-tabler-x class="size-5" />
                    </button>
                </div>

                {{-- Step 1: Upload --}}
                <div
                    x-show="step === 'upload'"
                    class="space-y-4 px-6 py-5"
                >
                    <p class="text-sm text-label">
                        {{ __('Upload any CSV file — you will map columns to fields in the next step.') }}
                    </p>

                    @if ($segments->isNotEmpty())
                        <div>
                            <label class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('Add to Segment') }}
                                <span class="text-xs font-normal text-label">({{ __('optional') }})</span>
                            </label>
                            <select
                                x-model="segmentId"
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            >
                                <option value="">— {{ __('No segment') }} —</option>
                                @foreach ($segments as $segment)
                                    <option value="{{ $segment->id }}">{{ $segment->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($contacts->isNotEmpty())
                        <div>
                            <label class="mb-1 block text-sm font-medium text-foreground">
                                {{ __('Add to Contact List') }}
                                <span class="text-xs font-normal text-label">({{ __('optional') }})</span>
                            </label>
                            <select
                                x-model="contactId"
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            >
                                <option value="">— {{ __('No contact list') }} —</option>
                                @foreach ($contacts as $contact)
                                    <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <input
                        type="file"
                        accept=".csv"
                        class="block w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground file:me-3 file:cursor-pointer file:rounded file:border-0 file:bg-surface file:px-3 file:py-1 file:text-sm file:text-foreground"
                        x-ref="csvFile"
                        @change="fileInput = $refs.csvFile"
                    />
                    <div class="flex justify-end gap-3 pt-2">
                        <x-button
                            type="button"
                            variant="outline"
                            @click="open = false"
                        >
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button
                            type="button"
                            x-bind:disabled="loading"
                            @click="fetchHeaders()"
                        >
                            <span x-show="!loading">{{ __('Next') }}</span>
                            <span x-show="loading">{{ __('Loading…') }}</span>
                        </x-button>
                    </div>
                </div>

                {{-- Step 2: Map columns --}}
                <div
                    x-show="step === 'map'"
                    class="space-y-5 px-6 py-5"
                >
                    <p class="text-sm text-label">
                        {{ __('Match your CSV columns to the required fields.') }}
                    </p>

                    <div class="space-y-3">
                        {{-- Name (required) --}}
                        <div class="flex items-center gap-4">
                            <label class="w-36 shrink-0 text-sm font-medium text-foreground">
                                {{ __('Name') }}
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                x-model="mapping.name"
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            >
                                <option value="">— {{ __('Not mapped') }} —</option>
                                <template x-for="h in headers" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Phone (required) --}}
                        <div class="flex items-center gap-4">
                            <label class="w-36 shrink-0 text-sm font-medium text-foreground">
                                {{ __('Phone') }}
                                <span class="text-red-500">*</span>
                            </label>
                            <select
                                x-model="mapping.phone"
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            >
                                <option value="">— {{ __('Not mapped') }} —</option>
                                <template x-for="h in headers" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Country Code (optional) --}}
                        <div class="flex items-center gap-4">
                            <label class="w-36 shrink-0 text-sm text-label">
                                {{ __('Country Code') }}
                                <span class="text-xs">({{ __('optional') }})</span>
                            </label>
                            <select
                                x-model="mapping.country_code"
                                class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
                            >
                                <option value="">— {{ __('Not mapped') }} —</option>
                                <template x-for="h in headers" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button
                            type="button"
                            variant="outline"
                            @click="step = 'upload'"
                        >
                            {{ __('Back') }}
                        </x-button>
                        <x-button
                            type="button"
                            x-bind:disabled="loading || !mappingValid()"
                            @click="previewCsv()"
                        >
                            <span x-show="!loading">{{ __('Preview') }}</span>
                            <span x-show="loading">{{ __('Loading…') }}</span>
                        </x-button>
                    </div>
                </div>

                {{-- Step 3: Preview --}}
                <div
                    x-show="step === 'preview'"
                    class="px-6 py-5"
                >
                    {{-- Summary bar --}}
                    <div class="mb-3 flex items-center gap-2 text-sm">
                        <span class="font-semibold text-foreground" x-text="selectedCount()"></span>
                        <span class="text-label">
                            {{ __('of') }}
                            <span x-text="valid.length"></span>
                            {{ __('contacts selected') }}
                        </span>
                        <template x-if="invalid.length > 0">
                            <span class="ms-auto text-xs text-red-500">
                                <span x-text="invalid.length"></span>
                                {{ __('row(s) skipped') }}
                            </span>
                        </template>
                    </div>

                    {{-- Selectable rows --}}
                    <template x-if="valid.length > 0">
                        <div class="mb-4 max-h-60 overflow-y-auto rounded-card border border-card-border">
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 bg-surface">
                                    <tr>
                                        <th class="w-8 px-3 py-2">
                                            <input
                                                type="checkbox"
                                                class="rounded"
                                                :checked="selectedIndices.length > 0 && selectedIndices.every(v => v)"
                                                :indeterminate.prop="selectedIndices.some(v => v) && !selectedIndices.every(v => v)"
                                                @change="selectedIndices = selectedIndices.map(() => $event.target.checked)"
                                            />
                                        </th>
                                        <th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Name') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Phone') }}</th>
                                        <th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Country Code') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, i) in valid" :key="i">
                                        <tr
                                            class="cursor-pointer border-t border-card-border transition-opacity"
                                            :class="selectedIndices[i] ? 'opacity-100' : 'opacity-40'"
                                            @click="selectedIndices[i] = !selectedIndices[i]; selectedIndices = [...selectedIndices]"
                                        >
                                            <td class="px-3 py-1.5" @click.stop>
                                                <input
                                                    type="checkbox"
                                                    class="rounded"
                                                    :checked="selectedIndices[i]"
                                                    @change="selectedIndices[i] = $event.target.checked; selectedIndices = [...selectedIndices]"
                                                />
                                            </td>
                                            <td class="px-3 py-1.5 text-foreground" x-text="row.name"></td>
                                            <td class="px-3 py-1.5 text-foreground" x-text="row.phone"></td>
                                            <td class="px-3 py-1.5 text-label" x-text="row.country_code || '—'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    {{-- Invalid / skipped rows --}}
                    <template x-if="invalid.length > 0">
                        <div class="mb-4">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-red-500">
                                {{ __('Skipped Rows') }}
                            </p>
                            <div class="max-h-32 overflow-y-auto rounded-card border border-card-border">
                                <table class="w-full text-xs">
                                    <thead class="bg-surface">
                                        <tr>
                                            <th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Line') }}</th>
                                            <th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Reason') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, i) in invalid" :key="i">
                                            <tr class="border-t border-card-border">
                                                <td class="px-3 py-1.5 text-label" x-text="row.line"></td>
                                                <td class="px-3 py-1.5 text-red-500" x-text="row.reason"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-button
                            type="button"
                            variant="outline"
                            @click="step = 'map'"
                        >
                            {{ __('Back') }}
                        </x-button>
                        <x-button
                            type="button"
                            x-bind:disabled="loading || selectedCount() === 0"
                            @click="confirmImport()"
                        >
                            <span x-show="!loading">{{ __('Confirm Import') }}</span>
                            <span x-show="loading">{{ __('Importing…') }}</span>
                        </x-button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="py-10">
        <x-table>
            <x-slot:head>
                <th>
                    {{ __('ID') }}
                </th>
                <th>
                    {{ __('Name') }}
                </th>
                <th>
                    {{ __('phone') }}
                </th>

                <th class="text-end">
                    {{ __('Action') }}
                </th>
            </x-slot:head>

            <x-slot:body>
                @foreach ($items as $item)
                    <tr>
                        <td>
                            {{ $item->id }}
                        </td>
                        <td>
                            {{ $item->name }}
                        </td>
                        <td>
                            {{ $item->phone }}
                        </td>
                        <td class="whitespace-nowrap text-end">
                            @if ($app_is_demo)
                                <x-button
                                    class="size-9"
                                    variant="ghost-shadow"
                                    hover-variant="danger"
                                    size="none"
                                    onclick="return toastr.info('This feature is disabled in Demo version.')"
                                    title="{{ __('Delete') }}"
                                >
                                    <x-tabler-x class="size-4" />
                                </x-button>
                            @else
                                <x-button
                                    class="size-9"
                                    href="{{ route('dashboard.user.marketing-bot.contact-list.edit', $item->id) }}"
                                    variant="ghost-shadow"
                                    size="none"
                                    title="{{ __('Delete') }}"
                                >
                                    <x-tabler-edit class="size-4" />
                                </x-button>
                                <x-button
                                    class="size-9"
                                    data-delete="delete"
                                    data-delete-link="{{ route('dashboard.user.marketing-bot.contact-list.destroy', $item->id) }}"
                                    variant="ghost-shadow"
                                    hover-variant="danger"
                                    size="none"
                                    title="{{ __('Delete') }}"
                                >
                                    <x-tabler-x class="size-4" />
                                </x-button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-slot:body>
        </x-table>

        <div class="mt-3 flex justify-end">
            {{ $items->links() }}
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            "use strict";
            $('#submit').on('submit', function(e) {
                e.preventDefault();
                let form = $(this);
                let data = $(this).serialize();

                $.post(form.attr('action'), data, function(data) {}).done(function(data) {
                    if (data.status === 'success') {
                        toastr.success(data.message);

                        setTimeout(function() {
                            window.location.reload();
                        }, 600);

                        return;
                    }

                    if (data.message) {
                        toastr.error(data.message);
                        return;
                    }

                    toastr.error('Something went wrong!');
                }).fail(function(e) {
                    if (e?.responseJSON?.message) {
                        toastr.error(e.responseJSON.message);
                    } else {
                        toastr.error('Something went wrong!');
                    }
                });
            });

            $('[data-delete="delete"]').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this contact?')) {
                    return;
                }

                let deleteLink = $(this).data('delete-link');

                $.ajax({
                    url: deleteLink,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(data) {
                        if (data.status === 'success') {
                            toastr.success(data.message);

                            setTimeout(function() {
                                window.location.reload();
                            }, 600);

                            return;
                        }

                        if (data.message) {
                            toastr.error(data.message);
                            return;
                        }

                        toastr.error('Something went wrong!');
                    },
                    error: function(e) {
                        if (e?.responseJSON?.message) {
                            toastr.error(e.responseJSON.message);
                        } else {
                            toastr.error('Something went wrong!');
                        }
                    }
                });
            });
        });
    </script>
@endpush
