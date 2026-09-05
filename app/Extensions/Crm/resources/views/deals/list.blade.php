@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Deals List'))
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('All your deals in a table view.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.deals.index') }}"
		>
			<x-tabler-layout-kanban class="size-4" />
			{{ __('Pipeline View') }}
		</x-button>
		<x-modal title="{{ __('Add Deal') }}">
			<x-slot:trigger>
				<x-tabler-plus class="size-4" />
				{{ __('Add Deal') }}
			</x-slot:trigger>

			<x-slot:modal>
				<form
					class="flex flex-col gap-5"
					onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.deals.store') }}')"
				>
					@csrf
					<x-forms.input
						size="lg"
						label="{{ __('Deal Title') }}"
						name="title"
						required
						placeholder="{{ __('Enter deal title') }}"
					/>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Stage') }}"
						name="crm_deal_stage_id"
						required
					>
						@foreach ($stages as $stage)
							<option value="{{ $stage->id }}">{{ $stage->name }}</option>
						@endforeach
					</x-forms.input>
					<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<x-forms.input
							size="lg"
							type="number"
							label="{{ __('Value') }}"
							name="value"
							value="0"
							placeholder="0.00"
						/>
						<x-forms.input
							size="lg"
							label="{{ __('Currency') }}"
							name="currency"
							value="USD"
						/>
					</div>
					<x-forms.input
						size="lg"
						type="date"
						label="{{ __('Expected Close Date') }}"
						name="expected_close_date"
					/>
					<x-forms.input
						size="lg"
						type="select"
						label="{{ __('Contact') }}"
						name="crm_contact_id"
					>
						<option value="">{{ __('Select a contact') }}</option>
						@foreach ($contacts as $contact)
							<option value="{{ $contact->id }}">{{ $contact->full_name }}</option>
						@endforeach
					</x-forms.input>
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
						label="{{ __('Description') }}"
						name="description"
						type="textarea"
						rows="2"
						placeholder="{{ __('Deal details') }}"
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
		['label' => __('Date'),   'sort' => 'created_at'],
		['label' => __('Title'),  'sort' => 'title'],
		['label' => __('Value'),  'sort' => 'value'],
		['label' => __('Stage'),  'sort' => 'stage'],
		['label' => __('Close Date'), 'sort' => 'expected_close_date'],
	];

	$filter_buttons = [
		['label' => __('All'),       'filter' => 'all'],
		['label' => __('Favorites'), 'filter' => 'favorites'],
	];
	foreach ($stages as $stage) {
		$filter_buttons[] = ['label' => $stage->name, 'filter' => \Illuminate\Support\Str::slug($stage->name)];
	}
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
								href="{{ route('dashboard.user.crm.deals.list', array_merge(request()->query(), ['sort' => $button['sort'], 'sort_dir' => ($sort === $button['sort'] && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
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
						href="{{ route('dashboard.user.crm.deals.list', array_merge(request()->query(), ['filter' => $button['filter']])) }}"
						variant="ghost"
					>
						{{ $button['label'] }}
					</x-button>
				@endforeach
			</div>
		</div>

		<div x-data>
		@include('crm::partials.bulk-actions', ['bulkType' => 'deal'])

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
					<th>{{ __('Title') }}</th>
					<th>{{ __('Value') }}</th>
					<th>{{ __('Stage') }}</th>
					<th>{{ __('Contact') }}</th>
					<th>{{ __('Company') }}</th>
					<th>{{ __('Close Date') }}</th>
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
						<td class="font-medium">
							<a
								href="{{ route('dashboard.user.crm.deals.edit', $entry->id) }}"
								class="hover:underline"
							>
								{{ $entry->title }}
							</a>
						</td>
						<td class="font-semibold">${{ number_format($entry->value, 0) }}</td>
						<td>
							<span class="inline-flex items-center gap-1.5">
								<span
									class="inline-block size-2.5 rounded-full"
									style="background-color: {{ $entry->stage?->color }}"
								></span>
								{{ $entry->stage?->name }}
							</span>
						</td>
						<td>{{ $entry->contact?->full_name ?? '-' }}</td>
						<td>{{ $entry->company?->name ?? '-' }}</td>
						<td>{{ $entry->expected_close_date?->format('M d, Y') ?? '-' }}</td>
						<td class="whitespace-nowrap text-end">
							<x-button
								class="size-9"
								size="none"
								variant="ghost-shadow"
								title="{{ __('Toggle favorite') }}"
								onclick="crmToggleFav('deal', {{ $entry->id }}, this)"
							>
								@if ($entry->is_favorite)
									<x-tabler-star-filled class="size-4 text-amber-400" />
								@else
									<x-tabler-star class="size-4" />
								@endif
							</x-button>
							<x-modal
								class="inline-flex"
								title="{{ __('Edit Deal') }} - {{ $entry->title }}"
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
										onsubmit="return crmSubmitForm(event, '{{ route('dashboard.user.crm.deals.update', $entry->id) }}')"
									>
										@csrf
										@method('PUT')
										<x-forms.input
											size="lg"
											label="{{ __('Deal Title') }}"
											name="title"
											required
											value="{{ $entry->title }}"
										/>
										<x-forms.input
											size="lg"
											type="select"
											label="{{ __('Stage') }}"
											name="crm_deal_stage_id"
											required
										>
											@foreach ($stages as $stage)
												<option
													value="{{ $stage->id }}"
													@selected($entry->crm_deal_stage_id == $stage->id)
												>
													{{ $stage->name }}
												</option>
											@endforeach
										</x-forms.input>
										<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
											<x-forms.input
												size="lg"
												type="number"
												label="{{ __('Value') }}"
												name="value"
												value="{{ $entry->value }}"
											/>
											<x-forms.input
												size="lg"
												label="{{ __('Currency') }}"
												name="currency"
												value="{{ $entry->currency }}"
											/>
										</div>
										<x-forms.input
											size="lg"
											type="date"
											label="{{ __('Expected Close Date') }}"
											name="expected_close_date"
											value="{{ $entry->expected_close_date?->format('Y-m-d') }}"
										/>
										<x-forms.input
											size="lg"
											type="select"
											label="{{ __('Contact') }}"
											name="crm_contact_id"
										>
											<option value="">{{ __('Select a contact') }}</option>
											@foreach ($contacts as $contact)
												<option
													value="{{ $contact->id }}"
													@selected($entry->crm_contact_id == $contact->id)
												>
													{{ $contact->full_name }}
												</option>
											@endforeach
										</x-forms.input>
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
											label="{{ __('Description') }}"
											name="description"
											type="textarea"
											rows="2"
										>{{ $entry->description }}</x-forms.input>
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
								onclick="return confirm('{{ __('Are you sure you want to delete this deal?') }}')"
								href="{{ route('dashboard.user.crm.deals.delete', $entry->id) }}"
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
							{{ __('No deals yet. Create your first one!') }}
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
