@extends('migration::partials.layouts.master')

@section('template_title')
	@lang('MagicAI Migration')
@endsection

@section('container')
	<form
		class="migration-form group/form flex flex-col gap-6"
		action="{{ route('migration::migrate') }}"
		method="POST"
		enctype="multipart/form-data"
	>
		@csrf
		<h4 class="mb-6 mt-0 font-body text-[26px]">{{ __('Start Migration') }}</h4>
		<p class="mb-9 text-[75px] leading-none">🪄</p>
		<div>
			<x-forms.input
				class="flex items-center justify-center gap-2 rounded-lg p-2 font-medium border border-transparent h-11 shadow-sm transition-all duration-300"
				id="provider"
				label="{{ __('Select the source script you want to migrate from') }}"
				name="provider"
				type="select"
				size="md"
			>
				@foreach($providers as $provider)
					<option value="{{$provider->enum()->value}}">
						{{ $provider->getName() }}
					</option>
				@endforeach
			</x-forms.input>

			<!-- Capabilities box -->
			<div
				id="capabilitiesBox"
				class="hidden"
			>
				<div class="my-6 rounded-lg border p-4 shadow-sm">
					<h5 class="mb-3 text-lg font-semibold">
						@lang('What can be migrated?')
					</h5>
					<ul id="capabilitiesList" class="list-disc list-inside space-y-1 text-sm text-left m-auto text-gray-700"></ul>
				</div>
				<!-- Upload Database -->
				<x-forms.input
					class="mb-5 flex items-center justify-center gap-2 rounded-lg p-2 font-medium border border-transparent shadow-sm transition-all duration-300"
					id="sql_file"
					size="lg"
					label="{{ __('Upload the database dump (.sql) file') }}"
					name="sql_file"
					type="file"
					accept=".sql"
				/>

				<!-- Upload .Env -->
				<x-forms.input
					class="mb-5 flex items-center justify-center gap-2 rounded-lg p-2 font-medium border border-transparent shadow-sm transition-all duration-300"
					id="env_file"
					size="lg"
					label="{{ __('Upload the Environment file (.env) file') }}"
					name="env_file"
					type="file"
				/>

				<!-- migrate data button -->
				<x-button
					class="rounded-lg shadow-sm"
					size="lg"
					onclick="{{ $app_is_demo ? 'return toastr.info(\'This feature is disabled in Demo version.\')' : '' }}"
					type="{{ $app_is_demo ? 'button' : 'submit' }}"
					variant="primary"
				>
					{{ __('Migrate Data') }}
				</x-button>
			</div>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', () => {
				const capabilitiesMap = @json($capabilities);
				const providerSelect = document.getElementById('provider');
				const capabilitiesBox = document.getElementById('capabilitiesBox');
				const capabilitiesList = document.getElementById('capabilitiesList');

				// Set the initial state based on the selected provider
				const initialValue = providerSelect.value;
				if (initialValue) {
					const capabilities = capabilitiesMap[initialValue] || [];
					capabilities.forEach(cap => {
						const li = document.createElement('li');
						li.textContent = cap;
						capabilitiesList.appendChild(li);
					});
					capabilitiesBox.classList.remove('hidden');
				}

				providerSelect.addEventListener('change', () => {
					const selectedValue = providerSelect.value;

					if (!selectedValue) {
						capabilitiesBox.classList.add('hidden');
						capabilitiesList.innerHTML = '';
						return;
					}

					const capabilities = capabilitiesMap[selectedValue] || [];

					capabilitiesList.innerHTML = '';
					capabilities.forEach(cap => {
						const li = document.createElement('li');
						li.textContent = cap;
						capabilitiesList.appendChild(li);
					});

					capabilitiesBox.classList.remove('hidden');
				});
			});
		</script>





	</form>
@endsection
