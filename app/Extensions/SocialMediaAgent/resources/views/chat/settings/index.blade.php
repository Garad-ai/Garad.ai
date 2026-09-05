@php
    $example_prompts = collect([
        ['name' => '📅 7-day Instagram calendar', 'prompt' => 'Plan a 7-day Instagram content calendar for a fashion brand.'],
        ['name' => '📹 Viral Reels script', 'prompt' => 'Write a 30-second Reels script announcing a new collection.'],
        ['name' => '🎯 Audience personas', 'prompt' => 'Describe two personas for a sustainable beauty brand.'],
        ['name' => '📈 Campaign recap', 'prompt' => 'Summarize our latest social campaign performance in 5 bullet points.'],
        ['name' => '🧠 Social tone guide', 'prompt' => 'Create quick guidelines for a playful yet professional social voice.'],
        ['name' => '💬 Engagement questions', 'prompt' => 'Suggest 5 questions that encourage followers to comment.'],
        ['name' => '🏷️ Hashtag set', 'prompt' => 'Recommend 15 trending hashtags for a wellness post.'],
        ['name' => '🤝 Influencer DM template', 'prompt' => 'Draft a friendly DM to invite an influencer to collaborate.'],
    ])
        ->map(fn ($item) => (object) $item)
        ->toArray();

    $example_prompts_json = json_encode($example_prompts, JSON_THROW_ON_ERROR);
@endphp

@extends('panel.layout.settings')
@section('title', __('Social Media Agent Chat Settings'))
@section('titlebar_actions', '')

@section('additional_css')
@endsection

@section('settings')
	<form
		method="post"
		enctype="multipart/form-data"
		action="{{ route('dashboard.admin.social-media.agent.chat.settings.update') }}"
	>
		@csrf

		<x-form-step
			step="1"
			label="{{ __('Example Prompts') }}"
		>
			<x-button
				class="add-more ms-auto inline-flex size-8 items-center justify-center rounded-full bg-background text-foreground transition-all"
				size="none"
				type="button"
				variant="ghost-shadow"
			>
				<x-tabler-plus class="size-5" />
			</x-button>
		</x-form-step>

		<x-card class:body="flex flex-col gap-5">
			@forelse(json_decode(setting('social_media_agent_example_prompts', $example_prompts_json), false, 512, JSON_THROW_ON_ERROR) as $suggestion)
				<x-card
					class="user-input-group relative"
					class:body="flex flex-col gap-5"
					data-input-name="{{ $suggestion?->name }}"
					data-inputs-id="{{ $loop->index + 1 }}"
				>
					<x-forms.input
						class="input_name"
						size="lg"
						label="{{ __('Name') }}"
						name="input_name[]"
						tooltip="{{ __('The primary text will shown in the chat box.') }}"
						value="{{ $suggestion?->name }}"
					/>

					<x-forms.input
						class="input_prompt"
						type="textarea"
						size="lg"
						rows="1"
						name="input_prompt[]"
						label="{{ __('Prompt') }}"
						tooltip="{{ __('The prompt will be shown in the input box.') }}"
					>{{ $suggestion?->prompt }}</x-forms.input>

					<x-button
						class="remove-inputs-group absolute -end-3 -top-3 size-6"
						size="none"
						variant="danger"
						type="button"
					>
						<x-tabler-minus class="size-4" />
					</x-button>
				</x-card>
			@empty
				<x-card
					class="user-input-group relative"
					class:body="flex flex-col gap-5"
					data-inputs-id="1"
				>
					<x-forms.input
						class="input_name"
						size="lg"
						name="input_name[]"
						label="{{ __('Name') }}"
						tooltip="{{ __('The primary text will shown in the chat box.') }}"
					/>

					<x-forms.input
						class="input_prompt"
						type="textarea"
						size="lg"
						rows="3"
						name="input_prompt[]"
						label="{{ __('Prompt') }}"
						tooltip="{{ __('The prompt will be shown in the input box.') }}"
					></x-forms.input>

					<x-button
						class="remove-inputs-group absolute -end-3 -top-3 size-6"
						size="none"
						variant="danger"
						type="button"
					>
						<x-tabler-minus class="size-4" />
					</x-button>
				</x-card>
			@endforelse
			<div class="add-more-placeholder"></div>
		</x-card>

		<x-alert class="mt-2">
			<p>{{ __('These prompts power the scrolling suggestions displayed in the Social Media Agent chat experience.') }}</p>
		</x-alert>

		<button class="btn btn-primary mt-5 w-full">
			{{ __('Save') }}
		</button>
	</form>

	<template id="user-input-company">
		<x-card
			class="user-input-group relative"
			class:body="flex flex-col gap-5"
			data-inputs-id="1"
		>
			<x-forms.input
				class="input_name"
				size="lg"
				name="input_name[]"
				label="{{ __('Name') }}"
				tooltip="{{ __('The primary text will shown in the chat box.') }}"
			/>

			<x-forms.input
				class="input_prompt"
				type="textarea"
				size="lg"
				rows="3"
				name="input_prompt[]"
				label="{{ __('Prompt') }}"
				tooltip="{{ __('The prompt will be shown in the input box.') }}"
			></x-forms.input>

			<x-button
				class="remove-inputs-group absolute -end-3 -top-3 size-6"
				size="none"
				variant="danger"
				type="button"
			>
				<x-tabler-minus class="size-4" />
			</x-button>
		</x-card>
	</template>
@endsection

@push('script')
	<script>
		$(function () {
			"use strict";

			const template = document.getElementById('user-input-company');
			const placeholder = document.querySelector('.add-more-placeholder');
			let lastInputsId = document.querySelectorAll('.user-input-group').length;

			const toggleRemoveButtons = () => {
				const groups = document.querySelectorAll('.user-input-group');
				groups.forEach((group) => {
					const button = group.querySelector('.remove-inputs-group');
					if (groups.length <= 1) {
						button?.setAttribute('disabled', true);
					} else {
						button?.removeAttribute('disabled');
					}
				});
			};

			$('.add-more').on('click', function () {
				const clone = template.content.cloneNode(true);
				const wrapper = clone.querySelector('.user-input-group');
				wrapper.dataset.inputsId = `${++lastInputsId}`;
				placeholder.before(clone);
				toggleRemoveButtons();
			});

			$('body').on('click', '.remove-inputs-group', function () {
				$(this).closest('.user-input-group').remove();
				lastInputsId = document.querySelectorAll('.user-input-group').length;
				toggleRemoveButtons();
			});

			toggleRemoveButtons();
		});
	</script>
@endpush
