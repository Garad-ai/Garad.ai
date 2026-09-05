@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Content Manager Settings'))
@section('titlebar_actions', '')

@section('additional_css')
@endsection

@section('settings')
	<form method="POST" action="{{ route('content-manager::settings.update') }}">
		@csrf

		<label class="form-check form-switch mb-5">
			<input
				class="form-check-input"
				id="content_manager_enabled"
				name="content_manager_enabled"
				type="checkbox"
				{{ setting('content_manager_enabled', '1') === '1' ? 'checked' : '' }}
			>
			<span class="form-check-label">{{ __('Enable Content Manager') }}</span>
		</label>

		<x-forms.input
			class="mb-5"
			id="media_max_files"
			size="lg"
			type="text"
			name="media_max_files"
			label="{{ __('Max File Count') }}"
			placeholder="{{ __('Enter the maximum number of files allowed') }}"
			tooltip="{{ __('This setting controls the maximum number of files that can be uploaded at once.') }}"
			value="{{ setting('media_max_files', 5) }}"
			required
		/>

		<x-forms.input
			class="mb-5"
			id="media_max_size"
			size="lg"
			type="text"
			name="media_max_size"
			label="{{ __('Max File Size (MB)') }}"
			placeholder="{{ __('Enter the maximum file size in MB') }}"
			tooltip="{{ __('This setting controls the maximum file size allowed for uploads.') }}"
			value="{{ setting('media_max_size', 25) }}"
			required
		/>

		<x-forms.input
			class="mb-5"
			id="media_allowed_types"
			size="lg"
			type="text"
			name="media_allowed_types"
			label="{{ __('Allowed File Types (comma-separated) for Non-Admin Users')}}"
			placeholder="{{ __('Enter allowed file types (e.g., jpg,png,gif,webp,svg,mp4,avi,mov,wmv,flv,webm)') }}"
			tooltip="{{ __('This setting controls which file types are allowed for uploads. Use comma to separate multiple types.') }}"
			value="{{ setting('media_allowed_types', 'jpg,png,gif,webp,svg,mp4,avi,mov,wmv,flv,webm,mp3,wav,m4a,pdf,doc,docx,xls,xlsx') }}"
			required
		/>

		<button type="submit" class="btn btn-primary">{{ __('Save Configuration') }}</button>
	</form>
@endsection

@push('script')
@endpush
