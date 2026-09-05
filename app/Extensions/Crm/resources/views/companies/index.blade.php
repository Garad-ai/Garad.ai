@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Companies'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Manage your companies and organizations.'))

@section('titlebar_actions')
	<x-modal title="{{ __('Add Company') }}">
		<x-slot:trigger>
			<x-tabler-plus class="size-4" />
			{{ __('Add Company') }}
		</x-slot:trigger>

		<x-slot:modal>
			<form
				class="flex flex-col gap-5"
				onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.companies.store') }}')"
			>
				@csrf
				<x-forms.input
					size="lg"
					label="{{ __('Company Name') }}"
					name="name"
					required
					placeholder="{{ __('Enter company name') }}"
				/>
				<x-forms.input
					size="lg"
					label="{{ __('Industry') }}"
					name="industry"
					placeholder="{{ __('e.g. Technology, Healthcare') }}"
				/>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
					<x-forms.input
						size="lg"
						label="{{ __('Phone') }}"
						name="phone"
						placeholder="{{ __('Phone number') }}"
					/>
					<x-forms.input
						size="lg"
						type="email"
						label="{{ __('Email') }}"
						name="email"
						placeholder="{{ __('Email address') }}"
					/>
				</div>
				<x-forms.input
					size="lg"
					label="{{ __('Website') }}"
					name="website"
					placeholder="{{ __('https://example.com') }}"
				/>
				<x-forms.input
					size="lg"
					label="{{ __('Address') }}"
					name="address"
					type="textarea"
					rows="2"
					placeholder="{{ __('Street address') }}"
				></x-forms.input>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
					<x-forms.input
						size="lg"
						label="{{ __('City') }}"
						name="city"
						placeholder="{{ __('City') }}"
					/>
					<x-forms.input
						size="lg"
						label="{{ __('State') }}"
						name="state"
						placeholder="{{ __('State') }}"
					/>
					<x-forms.input
						size="lg"
						label="{{ __('Country') }}"
						name="country"
						placeholder="{{ __('Country') }}"
					/>
				</div>
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
@endsection

@php
	$sort_buttons = [
		['label' => __('Name'),     'sort' => 'name'],
		['label' => __('Date'),     'sort' => 'created_at'],
		['label' => __('Industry'), 'sort' => 'industry'],
	];

	$filter_buttons = [
		['label' => __('All'),       'filter' => 'all'],
		['label' => __('Favorites'), 'filter' => 'favorites'],
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
								href="{{ route('dashboard.user.crm.companies.index', ['filter' => $filter, 'sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
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
						href="{{ route('dashboard.user.crm.companies.index', ['filter' => $button['filter'], 'sort' => $sort, 'sort_dir' => $sortDir]) }}"
						variant="ghost"
					>
						{{ $button['label'] }}
					</x-button>
				@endforeach
			</div>
		</div>

		<div x-data>
		@include('crm::partials.bulk-actions', ['bulkType' => 'company'])

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
					<th>{{ __('Industry') }}</th>
					<th>{{ __('Phone') }}</th>
					<th>{{ __('Email') }}</th>
					<th>{{ __('Contacts') }}</th>
					<th>{{ __('Deals') }}</th>
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
								class="font-medium text-primary hover:underline"
								href="{{ route('dashboard.user.crm.companies.show', $entry->id) }}"
							>
								{{ $entry->name }}
							</a>
						</td>
						<td>{{ $entry->industry ?? '-' }}</td>
						<td>{{ $entry->phone ?? '-' }}</td>
						<td>{{ $entry->email ?? '-' }}</td>
						<td>{{ $entry->contacts_count }}</td>
						<td>{{ $entry->deals_count }}</td>
						<td class="whitespace-nowrap text-end">
							<x-button
								class="size-9"
								size="none"
								variant="ghost-shadow"
								title="{{ __('Toggle favorite') }}"
								onclick="crmToggleFav('company', {{ $entry->id }}, this)"
							>
								@if ($entry->is_favorite)
									<x-tabler-star-filled class="size-4 text-amber-400" />
								@else
									<x-tabler-star class="size-4" />
								@endif
							</x-button>
							<x-modal
								class="inline-flex"
								title="{{ __('Edit Company') }} - {{ $entry->name }}"
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
										onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.companies.update', $entry->id) }}')"
									>
										@csrf
										@method('PUT')
										<x-forms.input
											size="lg"
											label="{{ __('Company Name') }}"
											name="name"
											required
											value="{{ $entry->name }}"
										/>
										<x-forms.input
											size="lg"
											label="{{ __('Industry') }}"
											name="industry"
											value="{{ $entry->industry }}"
										/>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input
												size="lg"
												label="{{ __('Phone') }}"
												name="phone"
												value="{{ $entry->phone }}"
											/>
											<x-forms.input
												size="lg"
												type="email"
												label="{{ __('Email') }}"
												name="email"
												value="{{ $entry->email }}"
											/>
										</div>
										<x-forms.input
											size="lg"
											label="{{ __('Website') }}"
											name="website"
											value="{{ $entry->website }}"
										/>
										<x-forms.input
											size="lg"
											label="{{ __('Address') }}"
											name="address"
											type="textarea"
											rows="2"
										>{{ $entry->address }}</x-forms.input>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
											<x-forms.input
												size="lg"
												label="{{ __('City') }}"
												name="city"
												value="{{ $entry->city }}"
											/>
											<x-forms.input
												size="lg"
												label="{{ __('State') }}"
												name="state"
												value="{{ $entry->state }}"
											/>
											<x-forms.input
												size="lg"
												label="{{ __('Country') }}"
												name="country"
												value="{{ $entry->country }}"
											/>
										</div>
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
								onclick="return confirm('{{ __('Are you sure you want to delete this company?') }}')"
								href="{{ route('dashboard.user.crm.companies.delete', $entry->id) }}"
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
							{{ __('No companies yet. Create your first one!') }}
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
