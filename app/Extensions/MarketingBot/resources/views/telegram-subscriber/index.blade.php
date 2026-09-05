@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Telegram Group Subscribers'))

@section('titlebar_actions')
    <div
        class="flex gap-4 lg:justify-end"
        x-data="{
            open: false,
            step: 'upload',
            loading: false,
            groupId: '',
            headers: [],
            mapping: { name: '', username: '', phone: '' },
            valid: [],
            invalid: [],
            fileInput: null,
            mappingValid() {
                return this.mapping.name !== '' || this.mapping.username !== '';
            },
            autoDetect() {
                let known = {
                    name: ['name', 'full name', 'fullname', 'contact name', 'first name', 'firstname'],
                    username: ['username', 'user name', 'handle', 'telegram', 'user'],
                    phone: ['phone', 'mobile', 'tel', 'telephone', 'phone number', 'mobile number'],
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
                fetch('{{ route('dashboard.user.marketing-bot.telegram-subscriber.import.headers') }}', {
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
                if (this.mapping.name)     formData.append('mapping[name]', this.mapping.name);
                if (this.mapping.username) formData.append('mapping[username]', this.mapping.username);
                if (this.mapping.phone)    formData.append('mapping[phone]', this.mapping.phone);
            },
            previewCsv() {
                if (!this.mappingValid()) return;
                this.loading = true;
                let formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('file', this.fileInput.files[0]);
                this.buildMappingFormData(formData);
                fetch('{{ route('dashboard.user.marketing-bot.telegram-subscriber.import.preview') }}', {
                    method: 'POST',
                    body: formData,
                })
                .then(r => r.json())
                .then(data => {
                    this.loading = false;
                    if (data.status === 'error') { toastr.error(data.message); return; }
                    this.valid   = data.valid;
                    this.invalid = data.invalid;
                    this.step    = 'preview';
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
                if (this.groupId) formData.append('group_id', this.groupId);
                fetch('{{ route('dashboard.user.marketing-bot.telegram-subscriber.import') }}', {
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
                this.step = 'upload';
                this.groupId = '';
                this.valid = [];
                this.invalid = [];
                this.fileInput = null;
                this.headers = [];
                this.mapping = { name: '', username: '', phone: '' };
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

        @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('crm') && setting('crm_contact_sync_enabled', '0') && setting('crm_sync_marketing_telegram', '0'))
            @include('crm::partials.sync-button', ['source' => 'marketing_telegram'])
        @endif

        {{-- CSV Import Modal --}}
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @keydown.escape.window="open = false"
        >
            <div
                class="w-full max-w-2xl rounded-xl bg-white shadow-2xl dark:bg-zinc-900"
                @click.stop
            >
                {{-- Modal header --}}
                <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                        {{ __('Import Telegram Contacts') }}
                    </h3>
                    <button
                        type="button"
                        class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
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
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Upload any CSV file — you will map columns to fields in the next step.') }}
                    </p>

                    @if ($groups->isNotEmpty())
                        <div>
                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Add to Telegram Group') }}
                                <span class="text-xs font-normal text-zinc-400">({{ __('optional') }})</span>
                            </label>
                            <select
                                x-model="groupId"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                            >
                                <option value="">— {{ __('No group') }} —</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <input
                        type="file"
                        accept=".csv"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:cursor-pointer file:rounded file:border-0 file:bg-zinc-100 file:px-3 file:py-1 file:text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-200"
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
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Match your CSV columns to the required fields. Name or username is required.') }}
                    </p>

                    <div class="space-y-3">
                        {{-- Name (required OR username) --}}
                        <div class="flex items-center gap-4">
                            <label class="w-36 shrink-0 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Name') }}
                            </label>
                            <select
                                x-model="mapping.name"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                            >
                                <option value="">— {{ __('Not mapped') }} —</option>
                                <template x-for="h in headers" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Username (required OR name) --}}
                        <div class="flex items-center gap-4">
                            <label class="w-36 shrink-0 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Username') }}
                            </label>
                            <select
                                x-model="mapping.username"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                            >
                                <option value="">— {{ __('Not mapped') }} —</option>
                                <template x-for="h in headers" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Phone (optional) --}}
                        <div class="flex items-center gap-4">
                            <label class="w-36 shrink-0 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Phone') }}
                                <span class="text-xs">({{ __('optional') }})</span>
                            </label>
                            <select
                                x-model="mapping.phone"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200"
                            >
                                <option value="">— {{ __('Not mapped') }} —</option>
                                <template x-for="h in headers" :key="h">
                                    <option :value="h" x-text="h"></option>
                                </template>
                            </select>
                        </div>

                        <p
                            x-show="!mappingValid()"
                            class="text-xs text-amber-600 dark:text-amber-400"
                        >
                            {{ __('Map at least Name or Username to continue.') }}
                        </p>
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
                    <p class="mb-4 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        <span class="text-green-600 dark:text-green-400" x-text="valid.length"></span>
                        {{ __('contact(s) will be imported') }},
                        <span class="text-red-500" x-text="invalid.length"></span>
                        {{ __('row(s) skipped') }}.
                    </p>

                    {{-- Valid rows --}}
                    <template x-if="valid.length > 0">
                        <div class="mb-4">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-green-600 dark:text-green-400">
                                {{ __('Valid Contacts') }}
                            </p>
                            <div class="max-h-40 overflow-y-auto rounded-lg border border-green-200 dark:border-green-800">
                                <table class="w-full text-xs">
                                    <thead class="bg-green-50 dark:bg-green-900/30">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Name') }}</th>
                                            <th class="px-3 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Username') }}</th>
                                            <th class="px-3 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Phone') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, i) in valid" :key="i">
                                            <tr class="border-t border-green-100 dark:border-green-800/50">
                                                <td class="px-3 py-1.5 text-zinc-800 dark:text-zinc-200" x-text="row.name"></td>
                                                <td class="px-3 py-1.5 text-zinc-800 dark:text-zinc-200" x-text="row.username || '—'"></td>
                                                <td class="px-3 py-1.5 text-zinc-500 dark:text-zinc-400" x-text="row.phone || '—'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    {{-- Invalid rows --}}
                    <template x-if="invalid.length > 0">
                        <div class="mb-4">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-red-500">
                                {{ __('Skipped Rows') }}
                            </p>
                            <div class="max-h-40 overflow-y-auto rounded-lg border border-red-200 dark:border-red-800">
                                <table class="w-full text-xs">
                                    <thead class="bg-red-50 dark:bg-red-900/30">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Line') }}</th>
                                            <th class="px-3 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300">{{ __('Reason') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, i) in invalid" :key="i">
                                            <tr class="border-t border-red-100 dark:border-red-800/50">
                                                <td class="px-3 py-1.5 text-zinc-500 dark:text-zinc-400" x-text="row.line"></td>
                                                <td class="px-3 py-1.5 text-red-600 dark:text-red-400" x-text="row.reason"></td>
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
                            x-bind:disabled="loading || valid.length === 0"
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
                    {{ __('Username') }}
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
                            {{ $item->username }}
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
                                    data-delete="delete"
                                    data-delete-link="{{ route('dashboard.user.marketing-bot.telegram-group.destroy', $item->id) }}"
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
