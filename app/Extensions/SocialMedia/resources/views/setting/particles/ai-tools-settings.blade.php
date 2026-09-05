<div class="mb-3 p-0">
	@php
		$falAiImageModels = [
			'nano-banana-pro' => 'Nano Banana Pro',
			'nano-banana-2' => 'Nano Banana 2',
			'nano-banana' => 'Nano Banana',
			'flux/schnell' => 'Flux Schnell',
			'seedream/v4/text-to-image' => 'SeeDream v4',
			'flux-pro/v1.1' => 'Flux Pro 1.1',
			'flux-realism' => 'Flux Realism',
			'imagen4' => 'Imagen 4',
			'flux-pro' => 'Flux Pro',
			'flux-2-flex' => 'Flux 2 Flex',
			'flux-pro/kontext/text-to-image' => 'Flux Pro Kontext',
			'ideogram-v2' => 'Ideogram V2',
		];
	@endphp
	<div class="mb-3">
		<x-card
			class="w-full"
			size="sm"
		>
			<label class="form-label">{{ __('Social Media Image Model') }}<x-info-tooltip text="{{ __('Default image generation model for Social Media. All models use FalAI.') }}" /></label>
			<select
				class="form-select"
				id="social_media_image_model"
				name="social_media_image_model"
			>
				@foreach($falAiImageModels as $key => $label)
					<option
						value="{{ $key }}"
						{{ setting('social_media_image_model', 'nano-banana-pro') === $key ? 'selected' : null }}
					>
						{{ $label }}
					</option>
				@endforeach
			</select>
		</x-card>
	</div>
	<div class="mb-3">
		<x-card
			class="w-full"
			size="sm"
		>
			<label class="form-label">{{ __('Social Media Agent Image Model') }}  <x-info-tooltip text="{{ __('Default image generation model for AI Social Media Agent. All models use FalAI.') }}" /></label>
			<select
				class="form-select"
				id="social_media_agent_image_model"
				name="social_media_agent_image_model"
			>
				@foreach($falAiImageModels as $key => $label)
					<option
						value="{{ $key }}"
						{{ setting('social_media_agent_image_model', 'nano-banana-pro') === $key ? 'selected' : null }}
					>
						{{ $label }}
					</option>
				@endforeach
			</select>
		</x-card>
	</div>
</div>
