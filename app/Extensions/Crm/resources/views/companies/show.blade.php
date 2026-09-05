@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $item->name)
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Company details and related records.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.companies.index') }}"
		>
			{{ __('Back') }}
		</x-button>
		<x-button href="{{ route('dashboard.user.crm.companies.index') }}?edit={{ $item->id }}">
			<x-tabler-pencil class="size-4" />
			{{ __('Edit') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div class="py-10">
		<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
			{{-- Company Info --}}
			<x-card class:body="flex flex-col gap-3 p-5">
				<h3 class="mb-2 font-semibold text-lg">{{ __('Company Info') }}</h3>

				@if ($item->industry)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Industry') }}</p>
						<p class="font-medium">{{ $item->industry }}</p>
					</div>
				@endif

				@if ($item->email)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Email') }}</p>
						<p class="font-medium">{{ $item->email }}</p>
					</div>
				@endif

				@if ($item->phone)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Phone') }}</p>
						<p class="font-medium">{{ $item->phone }}</p>
					</div>
				@endif

				@if ($item->website)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Website') }}</p>
						<p class="font-medium">{{ $item->website }}</p>
					</div>
				@endif

				@if ($item->address || $item->city || $item->state || $item->country)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Address') }}</p>
						<p class="font-medium">
							{{ collect([$item->address, $item->city, $item->state, $item->country])->filter()->join(', ') }}
						</p>
					</div>
				@endif

				@if ($item->notes)
					<div>
						<p class="text-xs text-foreground/60">{{ __('Notes') }}</p>
						<p class="text-sm">{{ $item->notes }}</p>
					</div>
				@endif
			</x-card>

			{{-- Contacts --}}
			<x-card class:body="p-0">
				<div class="flex items-center justify-between border-b px-5 py-4 dark:border-white/5">
					<h3 class="font-semibold">{{ __('Contacts') }} ({{ $item->contacts->count() }})</h3>
					<x-button
						variant="ghost-shadow"
						size="sm"
						href="{{ route('dashboard.user.crm.contacts.index') }}"
					>
						<x-tabler-plus class="size-3" />
						{{ __('Add') }}
					</x-button>
				</div>
				<div class="divide-y dark:divide-white/5">
					@forelse ($item->contacts as $contact)
						<a
							class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-foreground/5"
							href="{{ route('dashboard.user.crm.contacts.show', $contact->id) }}"
						>
							<x-tabler-user-circle class="size-6 shrink-0 text-foreground/40" />
							<div class="min-w-0">
								<p class="truncate text-sm font-medium">{{ $contact->full_name }}</p>
								<p class="truncate text-xs text-foreground/60">{{ $contact->job_title ?? $contact->email ?? '-' }}</p>
							</div>
						</a>
					@empty
						<p class="px-5 py-4 text-sm text-foreground/60">{{ __('No contacts linked.') }}</p>
					@endforelse
				</div>
			</x-card>

			{{-- Deals --}}
			<x-card class:body="p-0">
				<div class="flex items-center justify-between border-b px-5 py-4 dark:border-white/5">
					<h3 class="font-semibold">{{ __('Deals') }} ({{ $item->deals->count() }})</h3>
					<x-button
						variant="ghost-shadow"
						size="sm"
						href="{{ route('dashboard.user.crm.deals.create') }}"
					>
						<x-tabler-plus class="size-3" />
						{{ __('Add') }}
					</x-button>
				</div>
				<div class="divide-y dark:divide-white/5">
					@forelse ($item->deals as $deal)
						<a
							class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-foreground/5"
							href="{{ route('dashboard.user.crm.deals.edit', $deal->id) }}"
						>
							<div class="min-w-0">
								<p class="truncate text-sm font-medium">{{ $deal->title }}</p>
								<p class="text-xs text-foreground/60">
									<span
										class="inline-block size-2 rounded-full"
										style="background-color: {{ $deal->stage?->color }}"
									></span>
									{{ $deal->stage?->name }}
								</p>
							</div>
							<span class="shrink-0 text-sm font-semibold">${{ number_format($deal->value, 0) }}</span>
						</a>
					@empty
						<p class="px-5 py-4 text-sm text-foreground/60">{{ __('No deals linked.') }}</p>
					@endforelse
				</div>
			</x-card>
		</div>
	</div>
@endsection
