@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Video Editor'))
@section('titlebar_subtitle', __('Create and edit videos with a timeline-based editor'))

@section('titlebar_actions')
	<x-button
		onclick="document.getElementById('newProjectModal').classList.remove('hidden')"
		variant="primary"
	>
		<x-tabler-plus class="size-4" />
		{{ __('New Project') }}
	</x-button>
@endsection

@section('content')
	<div class="py-10">

		@if ($projects->isEmpty())
			{{-- Empty state --}}
			<div class="flex flex-col items-center justify-center gap-4 py-20 text-center">
				<div class="flex size-20 items-center justify-center rounded-full bg-primary/10">
					<x-tabler-movie class="size-10 text-primary" />
				</div>
				<h3 class="text-xl font-semibold text-heading-foreground">
					{{ __('No projects yet') }}
				</h3>
				<p class="max-w-md text-sm text-foreground/70">
					{{ __('Create your first video project to get started with the timeline editor.') }}
				</p>
				<x-button
					onclick="document.getElementById('newProjectModal').classList.remove('hidden')"
					variant="primary"
				>
					<x-tabler-plus class="size-4" />
					{{ __('Create First Project') }}
				</x-button>
			</div>
		@else
			{{-- Projects grid --}}
			<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
				@foreach ($projects as $project)
					<div class="group relative overflow-hidden rounded-2xl border border-card-border bg-card-background shadow-sm transition-all hover:shadow-md" data-project-id="{{ $project->id }}">
						{{-- Thumbnail --}}
						<a
							href="{{ route('dashboard.user.video-editor.editor', $project) }}"
							class="block"
						>
							<div class="relative aspect-video bg-background/50">
								@if ($project->thumbnail_url)
									<img
										src="{{ $project->thumbnail_url }}"
										alt="{{ $project->name }}"
										class="size-full object-cover"
										loading="lazy"
									/>
								@else
									<div class="flex size-full items-center justify-center">
										<x-tabler-video class="size-12 text-foreground/20" />
									</div>
								@endif
								{{-- Overlay on hover --}}
								<div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
									<div class="flex size-12 items-center justify-center rounded-full bg-white/90">
										<x-tabler-edit class="size-5 text-black" />
									</div>
								</div>
								{{-- Aspect ratio badge (visible on hover) --}}
								<span class="absolute start-2 top-2 rounded-md bg-black/60 px-2 py-0.5 text-xs font-medium text-white opacity-0 transition-opacity group-hover:opacity-100">
									{{ $project->aspect_ratio }}
								</span>
							</div>
						</a>
						{{-- Info --}}
						<div class="p-4">
							<h4 class="mb-1 truncate text-sm font-semibold text-heading-foreground">
								{{ $project->name }}
							</h4>
							<div class="text-xs text-foreground/50">
								{{ $project->updated_at->diffForHumans() }}
							</div>
						</div>
						{{-- Actions --}}
						<div class="absolute end-2 top-2 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
							<button
								onclick="duplicateProject({{ $project->id }})"
								class="flex size-8 items-center justify-center rounded-lg bg-black/60 text-white transition-colors hover:bg-black/80"
								title="{{ __('Duplicate') }}"
							>
								<x-tabler-copy class="size-4" />
							</button>
							<button
								onclick="deleteProject({{ $project->id }}, {{ json_encode($project->name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})"
								class="flex size-8 items-center justify-center rounded-lg bg-red-500/80 text-white transition-colors hover:bg-red-600"
								title="{{ __('Delete') }}"
							>
								<x-tabler-trash class="size-4" />
							</button>
						</div>
					</div>
				@endforeach
			</div>

			{{-- Pagination --}}
			<div class="mt-8">
				{{ $projects->links() }}
			</div>
		@endif
	</div>

	{{-- New Project Modal --}}
	<div
		id="newProjectModal"
		class="fixed inset-0 z-50 hidden"
	>
		<div
			class="absolute inset-0 bg-black/50"
			onclick="document.getElementById('newProjectModal').classList.add('hidden')"
		></div>
		<div class="absolute start-1/2 top-1/2 w-full max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-card-border bg-card-background p-6 shadow-xl">
			<h3 class="mb-4 text-lg font-semibold text-heading-foreground">
				{{ __('New Video Project') }}
			</h3>
			<form
				id="newProjectForm"
				onsubmit="createProject(event)"
			>
				<div class="mb-4">
					<label class="mb-1 block text-sm font-medium text-label">{{ __('Project Name') }}</label>
					<input
						type="text"
						name="name"
						required
						placeholder="{{ __('My Video Project') }}"
						class="w-full rounded-xl border border-input-border bg-input-background px-4 py-3 text-sm text-heading-foreground placeholder:text-foreground/40 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
					/>
				</div>
				<div class="mb-4">
					<label class="mb-1 block text-sm font-medium text-label">{{ __('Aspect Ratio') }}</label>
					<select
						name="aspect_ratio"
						class="w-full rounded-xl border border-input-border bg-input-background px-4 py-3 text-sm text-heading-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
					>
						<option value="16:9">16:9 ({{ __('Landscape') }})</option>
						<option value="9:16">9:16 ({{ __('Portrait') }})</option>
						<option value="1:1">1:1 ({{ __('Square') }})</option>
						<option value="4:3">4:3 ({{ __('Standard') }})</option>
					</select>
				</div>
				<div class="flex justify-end gap-3">
					<x-button
						type="button"
						variant="outline"
						onclick="document.getElementById('newProjectModal').classList.add('hidden')"
					>
						{{ __('Cancel') }}
					</x-button>
					<x-button
						type="submit"
						variant="primary"
					>
						{{ __('Create Project') }}
					</x-button>
				</div>
			</form>
		</div>
	</div>
@endsection

@push('script')
	<script>
		function createProject(e) {
			e.preventDefault();
			const form = document.getElementById('newProjectForm');
			const formData = new FormData(form);

			fetch('{{ route('dashboard.user.video-editor.projects.store') }}', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
						'Accept': 'application/json',
					},
					body: JSON.stringify(Object.fromEntries(formData)),
				})
				.then(res => res.json())
				.then(data => {
					if (data.status === 'success' && data.project) {
						window.location.href = '{{ url('dashboard/user/video-editor/editor') }}/' + data.project.id;
					} else {
						toastr.error(data.message || '{{ __('Failed to create project') }}');
					}
				})
				.catch(() => toastr.error('{{ __('Something went wrong') }}'));
		}

		function duplicateProject(projectId) {
			fetch('{{ url('dashboard/user/video-editor/projects') }}/' + projectId + '/duplicate', {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
						'Accept': 'application/json',
					},
				})
				.then(res => res.json())
				.then(data => {
					if (data.status === 'success') {
						toastr.success(data.message);
						window.location.reload();
					} else {
						toastr.error(data.message || '{{ __('Failed to duplicate project') }}');
					}
				});
		}

		function deleteProject(projectId, projectName) {
			if (!confirm('{{ __('Are you sure you want to delete') }} "' + projectName + '"?')) return;

			fetch('{{ url('dashboard/user/video-editor/projects') }}/' + projectId, {
					method: 'DELETE',
					headers: {
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
						'Accept': 'application/json',
						'Content-Type': 'application/json',
					},
				})
				.then(res => {
					if (!res.ok) throw new Error('Delete failed: ' + res.status);
					return res.json();
				})
				.then(data => {
					if (data.status === 'success') {
						// Remove card from DOM immediately
						const card = document.querySelector('[data-project-id="' + projectId + '"]');
						if (card) card.remove();
						if (window.toastr) toastr.success(data.message || '{{ __('Project deleted') }}');
					} else {
						if (window.toastr) toastr.error(data.message || '{{ __('Failed to delete project') }}');
					}
				})
				.catch(err => {
					console.error('Delete error:', err);
					if (window.toastr) toastr.error('{{ __('Failed to delete project') }}');
				});
		}
	</script>
@endpush
