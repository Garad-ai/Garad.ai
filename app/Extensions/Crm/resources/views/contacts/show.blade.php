@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', $item->full_name)
@section('titlebar_pretitle', '')
@section('titlebar_subtitle', __('Contact details and related records.'))

@section('titlebar_actions')
	<div class="flex gap-2">
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.contacts.index') }}"
		>
			{{ __('Back') }}
		</x-button>
		<x-button
			variant="ghost-shadow"
			href="{{ route('dashboard.user.crm.contacts.index') }}?edit={{ $item->id }}"
		>
			<x-tabler-pencil class="size-4" />
			{{ __('Edit') }}
		</x-button>
	</div>
@endsection

@section('content')
	<div class="py-10">
		<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
			{{-- ── Left Sidebar (1/3) ────────────────────────── --}}
			<div class="flex flex-col gap-6">
				{{-- Contact Info --}}
				<x-card class:body="flex flex-col gap-3 p-5">
					<div class="mb-2 flex items-center gap-3">
						@if ($item->avatar_url)
							<img
								class="size-12 shrink-0 rounded-full object-cover"
								src="{{ $item->avatar_url }}"
								alt="{{ $item->full_name }}"
							/>
						@else
							<span class="inline-grid size-12 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-bold text-primary">
								{{ $item->initials }}
							</span>
						@endif
						<h3 class="font-semibold text-lg">{{ __('Contact Info') }}</h3>
					</div>

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

					@if ($item->job_title)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Job Title') }}</p>
							<p class="font-medium">{{ $item->job_title }}</p>
						</div>
					@endif

					@if ($item->company)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Company') }}</p>
							<a
								class="font-medium text-primary hover:underline"
								href="{{ route('dashboard.user.crm.companies.show', $item->company->id) }}"
							>
								{{ $item->company->name }}
							</a>
						</div>
					@endif

					<div>
						<p class="text-xs text-foreground/60">{{ __('Status') }}</p>
						@if ($item->status === 'active')
							<x-badge class="bg-green-500/10 text-green-600">{{ __('Active') }}</x-badge>
						@else
							<x-badge class="bg-red-500/10 text-red-500">{{ __('Inactive') }}</x-badge>
						@endif
					</div>

					@if ($item->notes)
						<div>
							<p class="text-xs text-foreground/60">{{ __('Notes') }}</p>
							<p class="text-sm">{{ $item->notes }}</p>
						</div>
					@endif
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

				{{-- Tasks --}}
				<x-card class:body="p-0">
					<div class="flex items-center justify-between border-b px-5 py-4 dark:border-white/5">
						<h3 class="font-semibold">{{ __('Tasks') }} ({{ $item->tasks->count() }})</h3>
					</div>
					<div class="divide-y dark:divide-white/5">
						@forelse ($item->tasks as $task)
							<div class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-foreground/5">
								<div class="min-w-0 flex items-center gap-2">
									@if ($task->status === 'completed')
										<x-tabler-circle-check class="size-4 shrink-0 text-green-500" />
									@else
										<x-tabler-circle class="size-4 shrink-0 text-foreground/30" />
									@endif
									<p class="truncate text-sm font-medium {{ $task->status === 'completed' ? 'line-through text-foreground/50' : '' }}">
										{{ $task->title }}
									</p>
								</div>
								@if ($task->due_date)
									<span class="shrink-0 text-xs {{ $task->due_date->isPast() && $task->status !== 'completed' ? 'text-red-500' : 'text-foreground/60' }}">
										{{ $task->due_date->format('M d') }}
									</span>
								@endif
							</div>
						@empty
							<p class="px-5 py-4 text-sm text-foreground/60">{{ __('No tasks linked.') }}</p>
						@endforelse
					</div>
				</x-card>
			</div>

			{{-- ── Right Main Area (2/3) — Activity Timeline ─── --}}
			<div class="lg:col-span-2">
				@include('crm::partials.activity-timeline', [
					'notes'       => $notes,
					'events'      => $events,
					'notableType' => 'contact',
					'notableId'   => $item->id,
				])
			</div>
		</div>
	</div>
@endsection
