@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Contacts'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Manage your contacts and relationships.'))

@section('titlebar_actions')
	<div
		class="flex flex-wrap items-center gap-3 lg:justify-end"
		x-data="{
			open: false,
			step: 'upload',
			loading: false,
			headers: [],
			mapping: { first_name: '', last_name: '', email: '', phone: '', job_title: '', company: '', status: '' },
			defaultStatus: 'active',
			valid: [],
			invalid: [],
			selectedIndices: [],
			fileInput: null,
			mappingValid() {
				return this.mapping.first_name !== '';
			},
			selectedCount() {
				return this.selectedIndices.filter(v => v).length;
			},
			autoDetect() {
				let known = {
					first_name: ['first name', 'firstname', 'first_name', 'name', 'full name', 'fullname'],
					last_name: ['last name', 'lastname', 'last_name', 'surname'],
					email: ['email', 'e-mail', 'mail', 'email address'],
					phone: ['phone', 'mobile', 'tel', 'telephone', 'phone number'],
					job_title: ['job title', 'job_title', 'title', 'position', 'role'],
					company: ['company', 'company name', 'organization', 'organisation'],
					status: ['status', 'state'],
				};
				for (let field in known) {
					let match = this.headers.find(h => known[field].includes(h.toLowerCase()));
					this.mapping[field] = match !== undefined ? match : '';
				}
			},
			buildMappingFormData(formData) {
				if (this.mapping.first_name) formData.append('mapping[first_name]', this.mapping.first_name);
				if (this.mapping.last_name)  formData.append('mapping[last_name]', this.mapping.last_name);
				if (this.mapping.email)      formData.append('mapping[email]', this.mapping.email);
				if (this.mapping.phone)      formData.append('mapping[phone]', this.mapping.phone);
				if (this.mapping.job_title)  formData.append('mapping[job_title]', this.mapping.job_title);
				if (this.mapping.company)    formData.append('mapping[company]', this.mapping.company);
				if (this.mapping.status)     formData.append('mapping[status]', this.mapping.status);
				formData.append('default_status', this.defaultStatus);
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
				fetch('{{ route('dashboard.user.crm.contacts.import.headers') }}', { method: 'POST', body: formData })
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
			previewCsv() {
				if (!this.mappingValid()) return;
				this.loading = true;
				let formData = new FormData();
				formData.append('_token', '{{ csrf_token() }}');
				formData.append('file', this.fileInput.files[0]);
				this.buildMappingFormData(formData);
				fetch('{{ route('dashboard.user.crm.contacts.import.preview') }}', { method: 'POST', body: formData })
					.then(r => r.json())
					.then(data => {
						this.loading = false;
						if (data.status === 'error') { toastr.error(data.message); return; }
						this.valid = data.valid;
						this.invalid = data.invalid;
						this.selectedIndices = data.valid.map(() => true);
						this.step = 'preview';
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
				this.valid.forEach((_, i) => {
					if (this.selectedIndices[i]) formData.append('include_indices[]', i);
				});
				fetch('{{ route('dashboard.user.crm.contacts.import') }}', { method: 'POST', body: formData })
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
				this.valid = [];
				this.invalid = [];
				this.selectedIndices = [];
				this.fileInput = null;
				this.headers = [];
				this.mapping = { first_name: '', last_name: '', email: '', phone: '', job_title: '', company: '', status: '' };
				this.defaultStatus = 'active';
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
						{{ __('Import Contacts') }}
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
						{{ __('Match your CSV columns to the contact fields.') }}
					</p>

					<div class="space-y-3">
						@php
							$import_fields = [
								['key' => 'first_name', 'label' => __('First Name'), 'required' => true],
								['key' => 'last_name',  'label' => __('Last Name'),  'required' => false],
								['key' => 'email',      'label' => __('Email'),      'required' => false],
								['key' => 'phone',      'label' => __('Phone'),      'required' => false],
								['key' => 'job_title',  'label' => __('Job Title'),  'required' => false],
								['key' => 'company',    'label' => __('Company'),    'required' => false],
								['key' => 'status',     'label' => __('Status'),     'required' => false],
							];
						@endphp
						@foreach ($import_fields as $field)
							<div class="flex items-center gap-4">
								<label class="w-36 shrink-0 text-sm font-medium text-foreground">
									{{ $field['label'] }}
									@if ($field['required'])
										<span class="text-red-500">*</span>
									@else
										<span class="text-xs font-normal text-label">({{ __('optional') }})</span>
									@endif
								</label>
								<select
									x-model="mapping.{{ $field['key'] }}"
									class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
								>
									<option value="">— {{ __('Not mapped') }} —</option>
									<template x-for="h in headers" :key="h">
										<option :value="h" x-text="h"></option>
									</template>
								</select>
							</div>
						@endforeach
					</div>

					<div class="flex items-center gap-4 border-t border-card-border pt-4">
						<label class="w-36 shrink-0 text-sm font-medium text-foreground">
							{{ __('Default Status') }}
						</label>
						<div class="w-full">
							<select
								x-model="defaultStatus"
								class="w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground"
							>
								<option value="active">{{ __('Active') }}</option>
								<option value="inactive">{{ __('Inactive') }}</option>
							</select>
							<p class="mt-1 text-xs text-label">
								{{ __('Used when the Status column is not mapped or a row value is not "active"/"inactive".') }}
							</p>
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
										<th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Email') }}</th>
										<th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Phone') }}</th>
										<th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Job Title') }}</th>
										<th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Company') }}</th>
										<th class="px-3 py-2 text-start font-medium text-foreground">{{ __('Status') }}</th>
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
											<td class="px-3 py-1.5 text-foreground" x-text="(row.first_name + ' ' + row.last_name).trim()"></td>
											<td class="px-3 py-1.5 text-foreground" x-text="row.email || '—'"></td>
											<td class="px-3 py-1.5 text-foreground" x-text="row.phone || '—'"></td>
											<td class="px-3 py-1.5 text-foreground" x-text="row.job_title || '—'"></td>
											<td class="px-3 py-1.5 text-label" x-text="row.company || '—'"></td>
											<td class="px-3 py-1.5">
												<span
													class="inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-medium"
													:class="row.status === 'inactive' ? 'bg-red-500/10 text-red-500' : 'bg-green-500/10 text-green-600'"
													x-text="row.status === 'inactive' ? '{{ __('Inactive') }}' : '{{ __('Active') }}'"
												></span>
											</td>
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

	<x-modal title="{{ __('Add Contact') }}">
		<x-slot:trigger>
			<x-tabler-plus class="size-4" />
			{{ __('Add Contact') }}
		</x-slot:trigger>

		<x-slot:modal>
			<form
				class="flex flex-col gap-5"
				onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.contacts.store') }}')"
			>
				@csrf
				<div
					class="flex items-center gap-4"
					x-data="{ preview: null }"
				>
					<span class="relative inline-grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 text-sm font-bold text-primary">
						<template x-if="preview">
							<img
								class="absolute inset-0 size-full object-cover"
								:src="preview"
								alt="{{ __('Avatar preview') }}"
							/>
						</template>
						<x-tabler-user class="size-6" x-show="!preview" />
					</span>
					<div class="grow">
						<label class="mb-1 block text-2xs font-medium text-foreground">
							{{ __('Avatar') }}
							<span class="font-normal text-label">({{ __('optional') }})</span>
						</label>
						<input
							class="block w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground file:me-3 file:cursor-pointer file:rounded file:border-0 file:bg-surface file:px-3 file:py-1 file:text-sm file:text-foreground"
							type="file"
							name="avatar"
							accept="image/png,image/jpeg,image/webp"
							@change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
						/>
					</div>
				</div>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						label="{{ __('First Name') }}"
						name="first_name"
						required
						placeholder="{{ __('First name') }}"
					/>
					<x-forms.input
						size="lg"
						label="{{ __('Last Name') }}"
						name="last_name"
						required
						placeholder="{{ __('Last name') }}"
					/>
				</div>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						type="email"
						label="{{ __('Email') }}"
						name="email"
						placeholder="{{ __('Email address') }}"
					/>
					<x-forms.input
						size="lg"
						label="{{ __('Phone') }}"
						name="phone"
						placeholder="{{ __('Phone number') }}"
					/>
				</div>
				<x-forms.input
					size="lg"
					label="{{ __('Job Title') }}"
					name="job_title"
					placeholder="{{ __('e.g. CEO, Marketing Manager') }}"
				/>
				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Company') }}"
					name="crm_company_id"
				>
					<option value="">{{ __('Select a company') }}</option>
					@foreach ($companies as $company)
						<option value="{{ $company->id }}">{{ $company->name }}</option>
					@endforeach
				</x-forms.input>
				<x-forms.input
					size="lg"
					type="select"
					label="{{ __('Status') }}"
					name="status"
				>
					<option value="active">{{ __('Active') }}</option>
					<option value="inactive">{{ __('Inactive') }}</option>
				</x-forms.input>
				<x-forms.input
					size="lg"
					label="{{ __('Notes') }}"
					name="notes"
					type="textarea"
					rows="2"
					placeholder="{{ __('Additional notes') }}"
				></x-forms.input>
				<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
					<x-button
						@click.prevent="modalOpen = false"
						variant="outline"
						type="button"
					>
						{{ __('Cancel') }}
					</x-button>
					<x-button type="submit">
						{{ __('Save') }}
					</x-button>
				</div>
			</form>
		</x-slot:modal>
	</x-modal>
	</div>
@endsection

@php
	$sort_buttons = [
		['label' => __('Name'),    'sort' => 'first_name'],
		['label' => __('Date'),    'sort' => 'created_at'],
		['label' => __('Company'), 'sort' => 'company'],
		['label' => __('Status'),  'sort' => 'status'],
	];

	$filter_buttons = [
		['label' => __('All'),       'filter' => 'all'],
		['label' => __('Favorites'), 'filter' => 'favorites'],
		['label' => __('Active'),    'filter' => 'active'],
		['label' => __('Inactive'),  'filter' => 'inactive'],
	];
@endphp

@section('content')
	<div class="py-10">
		<div class="mb-6">
			@include('crm::partials.stats-bar', ['stats' => $stats])
		</div>

		<div class="mb-6 flex flex-wrap items-center gap-3">
			<x-dropdown.dropdown class="pe-3" offsetY="1rem">
				<x-slot:trigger class="whitespace-nowrap py-1.5" variant="link" size="xs">
					{{ __('Sort by:') }}
					<x-tabler-arrows-sort class="size-4" />
				</x-slot:trigger>

				<x-slot:dropdown class="overflow-hidden text-2xs font-medium">
					<div class="flex flex-col">
						@foreach ($sort_buttons as $button)
							<a
								@class([
									'flex w-full items-center gap-1 px-3 py-2 hover:bg-foreground/5',
									'bg-foreground/5' => $sort === $button['sort'],
								])
								href="{{ route('dashboard.user.crm.contacts.index', ['filter' => $filter, 'sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
							>
								{{ $button['label'] }}
								@if ($sort === $button['sort'])
									<x-tabler-caret-down-filled @class([
										'size-3 opacity-80 transition-all',
										'rotate-180' => $sortDir === 'asc',
									]) />
								@endif
							</a>
						@endforeach
					</div>
				</x-slot:dropdown>
			</x-dropdown.dropdown>

			<div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-heading-foreground max-sm:gap-3">
				@foreach ($filter_buttons as $button)
					<x-button
						@class([
							'inline-flex px-2.5 py-0.5 transition-colors hover:bg-foreground/5 hover:translate-y-0 text-2xs leading-tight',
							'bg-foreground/5' => $filter === $button['filter'],
						])
						tag="a"
						href="{{ route('dashboard.user.crm.contacts.index', ['filter' => $button['filter'], 'sort' => $sort, 'sort_dir' => $sortDir]) }}"
						variant="ghost"
					>
						{{ $button['label'] }}
					</x-button>
				@endforeach
			</div>
		</div>

		<div x-data>
		@include('crm::partials.bulk-actions', ['bulkType' => 'contact'])

		<x-table>
			<x-slot:head>
				<tr>
					<th class="w-8">
						<label
							class="relative z-10 inline-grid size-[18px] cursor-pointer select-none place-items-center rounded bg-foreground/5 text-primary before:absolute before:left-1/2 before:top-1/2 before:size-8 before:-translate-x-1/2 before:-translate-y-1/2"
							@click.prevent="$store.crmBulk.toggleAll()"
						>
							<input class="crm-bulk-cb-all peer invisible absolute z-10 size-0" type="checkbox" />
							<span class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block size-full rounded bg-primary/5 opacity-0 transition peer-checked:opacity-100"></span>
							<x-tabler-check class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-75 opacity-0 transition peer-checked:scale-100 peer-checked:opacity-100" stroke-width="2.5" />
							<x-tabler-minus class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-75 opacity-0 transition peer-[&.partial]:opacity-100" stroke-width="3" />
						</label>
					</th>
					<th>{{ __('Name') }}</th>
					<th>{{ __('Email') }}</th>
					<th>{{ __('Phone') }}</th>
					<th>{{ __('Company') }}</th>
					<th>{{ __('Job Title') }}</th>
					<th>{{ __('Status') }}</th>
					<th class="text-end">{{ __('Actions') }}</th>
				</tr>
			</x-slot:head>

			<x-slot:body>
				@forelse ($list as $entry)
					<tr>
						<td>
							<label class="relative z-10 inline-grid size-[18px] cursor-pointer select-none place-items-center rounded bg-foreground/5 text-primary before:absolute before:left-1/2 before:top-1/2 before:size-8 before:-translate-x-1/2 before:-translate-y-1/2">
								<input
									class="crm-bulk-cb peer invisible absolute z-10 size-0"
									type="checkbox"
									data-id="{{ $entry->id }}"
									@change="$store.crmBulk.toggle({{ $entry->id }})"
								/>
								<span class="col-start-1 col-end-1 row-start-1 row-end-1 inline-block size-full rounded bg-primary/5 opacity-0 transition peer-checked:opacity-100"></span>
								<x-tabler-check class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 scale-75 opacity-0 transition peer-checked:scale-100 peer-checked:opacity-100" stroke-width="2.5" />
							</label>
						</td>
						<td>
							<a
								class="flex items-center gap-2.5 font-medium text-primary hover:underline"
								href="{{ route('dashboard.user.crm.contacts.show', $entry->id) }}"
							>
								@if ($entry->avatar_url)
									<img
										class="size-8 shrink-0 rounded-full object-cover"
										src="{{ $entry->avatar_url }}"
										alt="{{ $entry->full_name }}"
									/>
								@else
									<span class="inline-grid size-8 shrink-0 place-items-center rounded-full bg-primary/10 text-2xs font-bold text-primary">
										{{ $entry->initials }}
									</span>
								@endif
								{{ $entry->full_name }}
							</a>
						</td>
						<td>{{ $entry->email ?? '-' }}</td>
						<td>{{ $entry->phone ?? '-' }}</td>
						<td>
							@if ($entry->company)
								<a
									class="text-primary hover:underline"
									href="{{ route('dashboard.user.crm.companies.show', $entry->company->id) }}"
								>
									{{ $entry->company->name }}
								</a>
							@else
								-
							@endif
						</td>
						<td>{{ $entry->job_title ?? '-' }}</td>
						<td>
							@if ($entry->status === 'active')
								<x-badge class="bg-green-500/10 text-green-600">{{ __('Active') }}</x-badge>
							@else
								<x-badge class="bg-red-500/10 text-red-500">{{ __('Inactive') }}</x-badge>
							@endif
						</td>
						<td class="whitespace-nowrap text-end">
							<x-button
								class="size-9"
								size="none"
								variant="ghost-shadow"
								title="{{ __('Toggle favorite') }}"
								onclick="crmToggleFav('contact', {{ $entry->id }}, this)"
							>
								@if ($entry->is_favorite)
									<x-tabler-star-filled class="size-4 text-amber-400" />
								@else
									<x-tabler-star class="size-4" />
								@endif
							</x-button>
							<x-modal
								class="inline-flex"
								title="{{ __('Edit Contact') }} - {{ $entry->full_name }}"
							>
								<x-slot:trigger
									class="size-9"
									size="none"
									variant="ghost-shadow"
									title="{{ __('Edit') }}"
								>
									<x-tabler-pencil class="size-4" />
								</x-slot:trigger>

								<x-slot:modal>
									<form
										class="flex flex-col gap-5"
										onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.contacts.update', $entry->id) }}')"
									>
										@csrf
										@method('PUT')
										<div
											class="flex items-center gap-4"
											x-data="{ preview: null }"
										>
											<span class="relative inline-grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 text-sm font-bold text-primary">
												<template x-if="preview">
													<img
														class="absolute inset-0 size-full object-cover"
														:src="preview"
														alt="{{ __('Avatar preview') }}"
													/>
												</template>
												<template x-if="!preview">
													<span class="absolute inset-0 grid place-items-center">
														@if ($entry->avatar_url)
															<img
																class="size-full object-cover"
																src="{{ $entry->avatar_url }}"
																alt="{{ $entry->full_name }}"
															/>
														@else
															{{ $entry->initials }}
														@endif
													</span>
												</template>
											</span>
											<div class="grow">
												<label class="mb-1 block text-2xs font-medium text-foreground">
													{{ __('Avatar') }}
													<span class="font-normal text-label">({{ __('optional') }})</span>
												</label>
												<input
													class="block w-full rounded-input border border-input-border bg-input-background px-3 py-2 text-sm text-input-foreground file:me-3 file:cursor-pointer file:rounded file:border-0 file:bg-surface file:px-3 file:py-1 file:text-sm file:text-foreground"
													type="file"
													name="avatar"
													accept="image/png,image/jpeg,image/webp"
													@change="preview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
												/>
											</div>
										</div>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input
												size="lg"
												label="{{ __('First Name') }}"
												name="first_name"
												required
												value="{{ $entry->first_name }}"
											/>
											<x-forms.input
												size="lg"
												label="{{ __('Last Name') }}"
												name="last_name"
												required
												value="{{ $entry->last_name }}"
											/>
										</div>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input
												size="lg"
												type="email"
												label="{{ __('Email') }}"
												name="email"
												value="{{ $entry->email }}"
											/>
											<x-forms.input
												size="lg"
												label="{{ __('Phone') }}"
												name="phone"
												value="{{ $entry->phone }}"
											/>
										</div>
										<x-forms.input
											size="lg"
											label="{{ __('Job Title') }}"
											name="job_title"
											value="{{ $entry->job_title }}"
										/>
										<x-forms.input
											size="lg"
											type="select"
											label="{{ __('Company') }}"
											name="crm_company_id"
										>
											<option value="">{{ __('Select a company') }}</option>
											@foreach ($companies as $company)
												<option
													value="{{ $company->id }}"
													@selected($entry->crm_company_id == $company->id)
												>
													{{ $company->name }}
												</option>
											@endforeach
										</x-forms.input>
										<x-forms.input
											size="lg"
											type="select"
											label="{{ __('Status') }}"
											name="status"
										>
											<option
												value="active"
												@selected($entry->status === 'active')
											>{{ __('Active') }}</option>
											<option
												value="inactive"
												@selected($entry->status === 'inactive')
											>{{ __('Inactive') }}</option>
										</x-forms.input>
										<x-forms.input
											size="lg"
											label="{{ __('Notes') }}"
											name="notes"
											type="textarea"
											rows="2"
										>{{ $entry->notes }}</x-forms.input>
										<div class="flex justify-end gap-3 border-t pt-4 dark:border-white/5">
											<x-button
												@click.prevent="modalOpen = false"
												variant="outline"
												type="button"
											>
												{{ __('Cancel') }}
											</x-button>
											<x-button type="submit">
												{{ __('Save') }}
											</x-button>
										</div>
									</form>
								</x-slot:modal>
							</x-modal>
							<x-button
								class="size-9"
								variant="ghost-shadow"
								hover-variant="danger"
								size="none"
								onclick="return confirm('{{ __('Are you sure you want to delete this contact?') }}')"
								href="{{ route('dashboard.user.crm.contacts.delete', $entry->id) }}"
								title="{{ __('Delete') }}"
							>
								<x-tabler-x class="size-4" />
							</x-button>
						</td>
					</tr>
				@empty
					<tr>
						<td
							class="text-center text-foreground/60"
							colspan="8"
						>
							{{ __('No contacts yet. Create your first one!') }}
						</td>
					</tr>
				@endforelse
			</x-slot:body>
		</x-table>
		</div>
	</div>
@endsection

@push('script')
	<script>
		function crmToggleFav(type, id, btn) {
			$.ajax({
				type: 'POST',
				url: '{{ route("dashboard.user.crm.toggleFavorite") }}',
				data: { type: type, id: id, _token: '{{ csrf_token() }}' },
				success: function (data) {
					btn.innerHTML = data.is_favorite
						? '<svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M8.243 7.34l-6.38 .925l-.113 .023a1 1 0 0 0 -.44 1.684l4.622 4.499l-1.09 6.355l-.013 .11a1 1 0 0 0 1.464 .944l5.706 -3l5.693 3l.1 .046a1 1 0 0 0 1.352 -1.1l-1.091 -6.355l4.624 -4.5l.078 -.085a1 1 0 0 0 -.633 -1.62l-6.38 -.926l-2.852 -5.78a1 1 0 0 0 -1.794 0l-2.853 5.78z"/></svg>'
						: '<svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"/></svg>';
				},
			});
		}

		function crmSubmitForm(event, url) {
			event.preventDefault();
			const form = event.target;
			const btn = form.querySelector('button[type="submit"]');
			btn.disabled = true;
			btn.innerHTML = magicai_localize?.please_wait || 'Please wait...';

			$.ajax({
				type: 'POST',
				url: url,
				data: new FormData(form),
				contentType: false,
				processData: false,
				success: function(data) {
					toastr.success(data.message || '{{ __('Saved successfully.') }}');
					location.reload();
				},
				error: function(data) {
					var err = data.responseJSON?.errors;
					if (err) {
						$.each(err, function(index, value) {
							toastr.error(value);
						});
					} else {
						toastr.error(data.responseJSON?.message || '{{ __('An error occurred.') }}');
					}
					btn.disabled = false;
					btn.innerHTML = '{{ __('Save') }}';
				}
			});
			return false;
		}
	</script>
@endpush
