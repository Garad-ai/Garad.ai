@extends('panel.layout.settings')
@section('title', __('AI Chat Pro Image Chat Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        method="POST"
        action="{{ route('dashboard.admin.ai-chat-pro-image-chat.settings.update') }}"
    >
        @csrf
        @method('PUT')

        <div class="mb-6">
            <x-form-step
                step="1"
                label="{{ __('Select Active AI Models') }}"
            >
            </x-form-step>
            <x-card
                class="mb-2 max-md:text-center"
                size="lg"
            >
                <!-- Selected tags display -->
                <x-card
                    class="mb-4 p-4"
                    size="none"
                >
                    <div
                        class="flex min-h-[32px] flex-wrap gap-2"
                        id="selected_tags"
                    >
                        @php
                            // Create a lookup array for models by value
                            $modelsLookup = collect($models)->keyBy('value');
                        @endphp

                        @foreach ($selectedSlugs as $slug)
                            @if (isset($modelsLookup[$slug]))
                                <span
                                    class="tag-item inline-flex items-center gap-1.5 rounded-md bg-foreground/5 px-2.5 py-1 text-2xs font-medium transition-colors"
                                    data-value="{{ $slug }}"
                                >
                                    {{ $modelsLookup[$slug]['label'] }}
                                    <button
                                        class="text-sm leading-none text-foreground/40 transition-colors hover:text-foreground"
                                        type="button"
                                        onclick="removeTag('{{ $slug }}')"
                                    >&times;</button>
                                </span>
                            @endif
                        @endforeach

                        @if (empty($selectedSlugs) || count($selectedSlugs) === 0)
                            <span
                                class="text-2xs text-foreground/40"
                                id="empty-message"
                            >@lang('No Models Selected')</span>
                        @endif
                    </div>
                </x-card>
                <!-- Hidden inputs for selected values - REMOVED SERVER-SIDE LOOP -->
                <div id="hidden_inputs"></div>
                <!-- Available models dropdown -->
                <div class="relative inline-block">
                    <x-dropdown.dropdown
                        anchor="start"
                        offsetY="8px"
                    >
                        <x-slot:trigger
                            class="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-xs font-medium transition-colors hover:bg-background/50"
                            variant="ghost"
                        >
                            <x-tabler-plus class="size-4" />
                            <span>@lang('Add Model')</span>
                        </x-slot:trigger>

                        <x-slot:dropdown
                            class="max-h-60 min-w-[250px] overflow-y-auto rounded-lg bg-background shadow-lg"
                        >
                            @foreach ($models as $model)
                                <button
                                    class="model-option block w-full px-4 py-2 text-left text-xs transition-colors hover:bg-background/50"
                                    data-value="{{ $model['value'] }}"
                                    data-label="{{ $model['label'] }}"
                                    type="button"
                                    onclick="selectModel('{{ $model['value'] }}', `{{ addslashes($model['label']) }}`)"
                                >
                                    {{ $model['label'] }}
                                </button>
                            @endforeach
                        </x-slot:dropdown>
                    </x-dropdown.dropdown>
                </div>
            </x-card>

            {{-- daily limit for guest users --}}
            <x-form-step
                step="4"
                label="{{ __('Daily Limit for Guest Users') }}"
            >
            </x-form-step>
            <x-card
                class="mb-2 max-md:text-center"
                size="lg"
            >
                <div class="col-md-12">
                    <x-alert class="mb-3">
                        <p>
                            {{ __('Set the maximum number of images that guest users can generate per day. Enter -1 for unlimited.') }}
                        </p>
                    </x-alert>
                    <x-forms.input
                        class="w-full"
                        type="number"
                        name="ai_chat_pro_image_chat:guest_daily_limit"
                        label="{{ __('Daily Limit') }}"
                        value="{{ setting('ai_chat_pro_image_chat:guest_daily_limit', 2) }}"
                        min="-1"
                        step="1"
                    />
                </div>
            </x-card>
        </div>

        <x-button
            class="w-full"
            type="submit"
            size="lg"
        >
            {{ __('Save') }}
        </x-button>
    </form>

    <script>
        // Ensure selectedModels is always an array
        let selectedModels = {!! json_encode(array_values($selectedSlugs)) !!};

        // Fallback: if it's not an array, make it one
        if (!Array.isArray(selectedModels)) {
            selectedModels = Object.values(selectedModels);
        }

        const emptyMessageText = "{{ __('No Models Selected') }}";

        // Initialize hidden inputs on page load
        function initializeHiddenInputs() {
            const hiddenInputsContainer = document.getElementById('hidden_inputs');
            hiddenInputsContainer.innerHTML = ''; // Clear any existing inputs

            selectedModels.forEach(value => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'active_models[]';
                input.value = value;
                input.className = 'hidden-model-input';
                input.dataset.value = value;
                hiddenInputsContainer.appendChild(input);
            });
        }

        function updateDropdownOptions() {
            const options = document.querySelectorAll('.model-option');

            options.forEach(option => {
                const value = option.dataset.value;
                if (selectedModels.includes(value)) {
                    option.classList.add('bg-foreground/5', 'text-foreground/40', 'cursor-not-allowed');
                    option.classList.remove('hover:bg-background/50', 'cursor-pointer');
                    option.style.pointerEvents = 'none';
                } else {
                    option.classList.remove('bg-foreground/5', 'text-foreground/40', 'cursor-not-allowed');
                    option.classList.add('hover:bg-background/50', 'cursor-pointer');
                    option.style.pointerEvents = 'auto';
                }
            });
        }

        function selectModel(value, label) {
            if (selectedModels.includes(value)) {
                return;
            }

            selectedModels.push(value);

            // Remove "no models" message if exists
            const emptyMessage = document.getElementById('empty-message');
            if (emptyMessage) {
                emptyMessage.remove();
            }

            // Add tag to display
            const tag = document.createElement('span');
            tag.className = 'inline-flex items-center gap-1.5 rounded-md bg-foreground/5 px-2.5 py-1 text-2xs font-medium transition-colors tag-item';
            tag.dataset.value = value;

            // Create the label span
            const labelSpan = document.createElement('span');
            labelSpan.textContent = label;
            tag.appendChild(labelSpan);

            // Create the remove button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-foreground/40 hover:text-foreground text-sm leading-none transition-colors ml-1';
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', function() {
                removeTag(value);
            });

            tag.appendChild(removeBtn);
            document.getElementById('selected_tags').appendChild(tag);

            // Add hidden input
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'active_models[]';
            input.value = value;
            input.className = 'hidden-model-input';
            input.dataset.value = value;
            document.getElementById('hidden_inputs').appendChild(input);

            updateDropdownOptions();
        }

        function removeTag(value) {
            // Remove from array
            const index = selectedModels.indexOf(value);
            if (index > -1) {
                selectedModels.splice(index, 1);
            }

            // Remove tag from display
            const tags = document.querySelectorAll('.tag-item');
            tags.forEach(tag => {
                if (tag.dataset.value === value) {
                    tag.remove();
                }
            });

            // Remove hidden input by data-value attribute
            const inputs = document.querySelectorAll('.hidden-model-input');
            inputs.forEach(input => {
                if (input.dataset.value === value) {
                    input.remove();
                }
            });

            // Show "no models" message if empty
            const tagsContainer = document.getElementById('selected_tags');
            if (tagsContainer.querySelectorAll('.tag-item').length === 0) {
                const emptyMessage = document.createElement('span');
                emptyMessage.className = 'text-2xs text-foreground/40';
                emptyMessage.id = 'empty-message';
                emptyMessage.textContent = emptyMessageText;
                tagsContainer.appendChild(emptyMessage);
            }

            updateDropdownOptions();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeHiddenInputs(); // Create hidden inputs from selectedModels array
            updateDropdownOptions();
        });
    </script>
@endsection
