{{--
    Activity Timeline — reusable partial for contact / deal detail pages.

    Required variables:
        $notes        — Collection of CrmNote (with 'user' loaded)
        $events       — Array of system events [{icon, color, title, desc?, time}]
        $notableType  — 'contact' or 'deal'
        $notableId    — int
--}}
@php
	$typeConfig = [
		'note'    => ['icon' => 'tabler-note',   'color' => 'primary',     'label' => __('Note')],
		'call'    => ['icon' => 'tabler-phone',  'color' => 'emerald-500', 'label' => __('Call')],
		'meeting' => ['icon' => 'tabler-users',  'color' => 'blue-500',    'label' => __('Meeting')],
		'email'   => ['icon' => 'tabler-mail',   'color' => 'amber-500',   'label' => __('Email')],
	];

	$colorMap = [
		'primary'     => ['bg' => 'bg-primary/10',     'text' => 'text-primary'],
		'emerald-500' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-500'],
		'blue-500'    => ['bg' => 'bg-blue-500/10',    'text' => 'text-blue-500'],
		'amber-500'   => ['bg' => 'bg-amber-500/10',   'text' => 'text-amber-500'],
		'red-500'     => ['bg' => 'bg-red-500/10',     'text' => 'text-red-500'],
		'secondary'   => ['bg' => 'bg-secondary/10',   'text' => 'text-secondary'],
		'[#3C82F6]'   => ['bg' => 'bg-[#3C82F6]/10',   'text' => 'text-[#3C82F6]'],
		'[#20C69F]'   => ['bg' => 'bg-[#20C69F]/10',   'text' => 'text-[#20C69F]'],
	];
	$fallbackBg   = 'bg-foreground/5';
	$fallbackText = 'text-foreground/40';

	// Merge notes + events into one sorted timeline
	$timeline = collect();

	foreach ($notes as $note) {
		$cfg = $typeConfig[$note->type] ?? $typeConfig['note'];
		$timeline->push([
			'is_note'    => true,
			'id'         => $note->id,
			'icon'       => $cfg['icon'],
			'color'      => $cfg['color'],
			'badge'      => $cfg['label'],
			'title'      => $cfg['label'],
			'content'    => $note->content,
			'user'       => $note->source_label ?: $note->user?->name,
			'is_agent'   => filled($note->source_label),
			'time'       => $note->created_at,
			'scheduled'  => $note->scheduled_at,
		]);
	}

	foreach ($events as $evt) {
		$timeline->push([
			'is_note' => false,
			'icon'    => $evt['icon'],
			'color'   => $evt['color'] ?? 'foreground/40',
			'title'   => $evt['title'],
			'content' => $evt['desc'] ?? null,
			'time'    => $evt['time'],
		]);
	}

	$timeline = $timeline->sortByDesc('time')->values();
@endphp

<x-card
	class="flex flex-col"
	class:body="flex flex-col grow p-0"
>
	<x-slot:head class="flex items-center justify-between px-5 py-3.5">
		<div class="flex items-center gap-4">
			<x-lqd-icon class="bg-background text-heading-foreground dark:bg-foreground/5">
				<x-tabler-history class="size-6" stroke-width="1.5" />
			</x-lqd-icon>
			<h4 class="m-0 text-base font-medium">{{ __('Activity') }}</h4>
		</div>
	</x-slot:head>

	{{-- ── Add-Note Form ──────────────────────────────────── --}}
	<div
		class="border-b px-5 py-4 dark:border-white/5"
		x-data="{
			type: 'note',
			content: '',
			scheduledAt: '',
			saving: false,
			async submit() {
				if (!this.content.trim() || this.saving) return;
				this.saving = true;

				try {
					const res = await $.ajax({
						type: 'POST',
						url: '{{ route('dashboard.user.crm.notes.store') }}',
						data: {
							_token: '{{ csrf_token() }}',
							notable_type: '{{ $notableType }}',
							notable_id: {{ $notableId }},
							type: this.type,
							content: this.content,
							scheduled_at: this.scheduledAt,
						},
					});

					if (res.success) {
						this.content = '';
						this.scheduledAt = '';
						location.reload();
					}
				} catch (e) {
					toastr.error('{{ __('Failed to save note.') }}');
				} finally {
					this.saving = false;
				}
			},
		}"
	>
		<textarea
			x-model="content"
			class="w-full resize-none rounded-lg border border-card-border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-foreground/40 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-white/10 dark:bg-foreground/[.02]"
			rows="2"
			placeholder="{{ __('Log a note, call, meeting, or email...') }}"
			@keydown.ctrl.enter="submit()"
			@keydown.meta.enter="submit()"
		></textarea>

		<div
			x-show="type === 'call' || type === 'meeting'"
			x-cloak
			class="mt-3"
		>
			<label class="mb-1.5 block text-xs font-medium text-foreground/60">{{ __('Schedule (adds to calendar)') }}</label>
			<input
				type="datetime-local"
				x-model="scheduledAt"
				class="w-full rounded-lg border border-card-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-white/10 dark:bg-foreground/[.02]"
			/>
		</div>

		<div class="mt-3 flex items-center justify-between">
			<div class="flex gap-1.5">
				@foreach ($typeConfig as $key => $cfg)
					<button
						type="button"
						class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition"
						:class="type === '{{ $key }}'
							? 'border-{{ $cfg['color'] }}/30 bg-{{ $cfg['color'] }}/10 text-{{ $cfg['color'] }}'
							: 'border-border text-foreground/50 hover:text-foreground'"
						@click="type = '{{ $key }}'"
					>
						<x-dynamic-component :component="$cfg['icon']" class="size-3.5" />
						{{ $cfg['label'] }}
					</button>
				@endforeach
			</div>

			<x-button
				size="sm"
				x-bind:disabled="!content.trim() || saving"
				@click="submit()"
			>
				<span x-show="!saving">{{ __('Save') }}</span>
				<span x-show="saving" x-cloak>{{ __('Saving...') }}</span>
			</x-button>
		</div>
	</div>

	{{-- ── Timeline Feed ──────────────────────────────────── --}}
	<div class="divide-y dark:divide-white/5">
		@forelse ($timeline as $entry)
			@php
				$cm = $colorMap[$entry['color']] ?? null;
				$bgClass   = $cm['bg']   ?? $fallbackBg;
				$textClass = $cm['text'] ?? $fallbackText;
			@endphp
			<div class="group/tl flex gap-3 px-5 py-3.5 transition-colors hover:bg-foreground/[.02]">
				{{-- Icon --}}
				<div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full {{ $bgClass }}">
					<x-dynamic-component
						:component="$entry['icon']"
						class="size-3.5 {{ $textClass }}"
					/>
				</div>

				{{-- Body --}}
				<div class="min-w-0 grow">
					<div class="flex items-start justify-between gap-2">
						<div class="min-w-0">
							<p class="text-sm font-medium text-heading-foreground">
								{{ $entry['title'] }}
								@if (!empty($entry['badge']))
									<span class="ml-1 inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $bgClass }} {{ $textClass }}">
										{{ $entry['badge'] }}
									</span>
								@endif
								@if (!empty($entry['user']))
									<span class="font-normal text-foreground/50">
										&mdash;
										@if (!empty($entry['is_agent']))
											<x-tabler-robot class="inline size-3.5 -mt-0.5 text-primary" />
										@endif
										{{ $entry['user'] }}
									</span>
								@endif
							</p>
						</div>
						<div class="flex shrink-0 items-center gap-2">
							<span class="text-xs text-foreground/40">{{ $entry['time']->diffForHumans(null, true, true) }}</span>
							@if ($entry['is_note'])
								<a
									href="{{ route('dashboard.user.crm.notes.delete', $entry['id']) }}"
									class="opacity-0 group-hover/tl:opacity-100 text-foreground/30 transition-opacity hover:text-red-500"
									title="{{ __('Delete') }}"
									onclick="return confirm('{{ __('Delete this note?') }}')"
								>
									<x-tabler-x class="size-3.5" />
								</a>
							@endif
						</div>
					</div>
					@if ($entry['content'])
						<p class="mt-0.5 text-sm text-foreground/70 whitespace-pre-line">{{ $entry['content'] }}</p>
					@endif
					@if (!empty($entry['scheduled']))
						<p class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary">
							<x-tabler-calendar-event class="size-3.5" />
							{{ __('Scheduled for') }} {{ $entry['scheduled']->isoFormat('MMM D, YYYY · HH:mm') }}
						</p>
					@endif
				</div>
			</div>
		@empty
			<div class="px-5 py-8 text-center text-sm text-foreground/50">
				<x-tabler-notes-off class="mx-auto mb-2 size-8 text-foreground/20" />
				<p>{{ __('No activity yet. Add a note to get started.') }}</p>
			</div>
		@endforelse
	</div>
</x-card>
