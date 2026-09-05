@extends('panel.layout.app', [
	'disable_tblr' => true,
	'disable_header' => true,
	'disable_footer' => true,
	'disable_navbar' => true,
	'disable_default_sidebar' => true,
	'disable_titlebar' => true,
	'layout_wide' => true,
	'disable_mobile_bottom_menu' => true,
])

@section('title', __('Video Editor'))

@section('content')
	<div
		class="lqd-video-editor fixed inset-0 z-50 flex flex-col overflow-hidden bg-surface"
		x-data="videoEditorApp({
			projectId: {{ $project?->id ?? 'null' }},
			projectData: {{ json_encode($project?->timeline_data ?? null, JSON_HEX_TAG | JSON_HEX_APOS) }},
			projectMedia: {{ json_encode($project?->media ?? [], JSON_HEX_TAG | JSON_HEX_APOS) }},
			projectName: {{ json_encode($project?->name ?? __('Untitled Project'), JSON_HEX_TAG | JSON_HEX_APOS) }},
			projectWidth: {{ $project?->width ?? 1920 }},
			projectHeight: {{ $project?->height ?? 1080 }},
			projectFps: {{ $project?->fps ?? 30 }},
			projectAspectRatio: '{{ $project?->aspect_ratio ?? '16:9' }}',
			csrfToken: '{{ csrf_token() }}',
			ttsProvider: '{{ $ttsProvider ?? \App\Extensions\VideoEditor\System\Http\Controllers\VideoEditorAiController::resolveTtsProvider($settings_two) }}',
			ttsGoogleEnabled: {{ ($settings_two->feature_tts_google ?? false) ? 'true' : 'false' }},
			ttsElevenlabsEnabled: {{ ($settings_two->feature_tts_elevenlabs ?? false) ? 'true' : 'false' }},
			aiVideoProEnabled: {{ \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-video-pro') ? 'true' : 'false' }},
			aiMusicProEnabled: {{ \App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-music-pro') ? 'true' : 'false' }},
			musicProvider: '{{ setting('ai_music_pro_default_provider', 'elevenlabs') }}',
			ttsEnabled: {{ (($settings_two->feature_tts_openai ?? false) || ($settings_two->feature_tts_google ?? false) || ($settings_two->feature_tts_elevenlabs ?? false) || ($settings_two->feature_tts_azure ?? false) || ($settings_two->feature_tts_speechify ?? false)) ? 'true' : 'false' }},
			routes: {
				index: '{{ route('dashboard.user.video-editor.index') }}',
				projectStore: '{{ route('dashboard.user.video-editor.projects.store') }}',
				projectUpdate: '{{ $project ? route('dashboard.user.video-editor.projects.update', $project) : '' }}',
				projectShow: '{{ $project ? route('dashboard.user.video-editor.projects.show', $project) : '' }}',
				mediaUpload: '{{ route('dashboard.user.video-editor.media.upload') }}',
				mediaIndex: '{{ route('dashboard.user.video-editor.media.index') }}',
				aiModels: '{{ route('dashboard.user.video-editor.ai.models') }}',
				aiGenerate: '{{ route('dashboard.user.video-editor.ai.generate') }}',
				aiStatus: '{{ route('dashboard.user.video-editor.ai.status') }}',
				aiCredits: '{{ route('dashboard.user.video-editor.ai.credits') }}',
				aiImport: '{{ route('dashboard.user.video-editor.ai.import') }}',
				voiceModels: '{{ route('dashboard.user.video-editor.ai.voiceModels') }}',
				voiceGenerate: '{{ route('dashboard.user.video-editor.ai.generateVoice') }}',
				musicGenerate: '{{ route('dashboard.user.video-editor.ai.generateMusic') }}',
				importFromContentManager: '{{ route('dashboard.user.video-editor.media.importFromContentManager') }}',
				exportStart: '{{ route('dashboard.user.video-editor.export.start') }}',
			}
		})"
		@keydown.window="handleKeydown($event)"
		@beforeunload.window="handleBeforeUnload($event)"
	>
		{{-- Top Toolbar --}}
		@include('video-editor::partials.toolbar')

		{{-- Main Content Area --}}
		<div class="flex flex-1 overflow-hidden">
			{{-- Compact Icon Sidebar --}}
			@include('video-editor::partials.sidebar')

			{{-- Left Panel: Library / AI --}}
			@include('video-editor::partials.left-panel')

			{{-- Center: Canvas Preview --}}
			@include('video-editor::partials.canvas-preview')

			{{-- Right Panel: Properties --}}
			@include('video-editor::partials.properties-panel')
		</div>

		{{-- Bottom: Timeline --}}
		@include('video-editor::partials.timeline')

		{{-- Export Modal --}}
		@include('video-editor::partials.export-modal')
	</div>
@endsection

@push('css')
	<style>
		/* Range input styling for Video Editor — uses theme primary color */
		.lqd-video-editor input[type="range"] {
			-webkit-appearance: none;
			appearance: none;
			background: transparent;
			cursor: pointer;
		}

		.lqd-video-editor input[type="range"]::-webkit-slider-runnable-track {
			height: 4px;
			border-radius: 9999px;
			background: hsl(var(--foreground) / 0.15);
		}

		.lqd-video-editor input[type="range"]::-webkit-slider-thumb {
			-webkit-appearance: none;
			appearance: none;
			width: 14px;
			height: 14px;
			border-radius: 50%;
			background: hsl(var(--primary));
			margin-top: -5px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
		}

		.lqd-video-editor input[type="range"]::-moz-range-track {
			height: 4px;
			border-radius: 9999px;
			background: hsl(var(--foreground) / 0.15);
		}

		.lqd-video-editor input[type="range"]::-moz-range-thumb {
			width: 14px;
			height: 14px;
			border-radius: 50%;
			background: hsl(var(--primary));
			border: none;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
		}

		.lqd-video-editor input[type="range"]::-moz-range-progress {
			height: 4px;
			border-radius: 9999px;
			background: hsl(var(--primary));
		}
	</style>
@endpush

@push('script')
	<script src="{{ url('vendor/video-editor/js/video-editor-app.js') }}?v={{ file_exists(public_path('vendor/video-editor/js/video-editor-app.js')) ? filemtime(public_path('vendor/video-editor/js/video-editor-app.js')) : time() }}"></script>
@endpush
