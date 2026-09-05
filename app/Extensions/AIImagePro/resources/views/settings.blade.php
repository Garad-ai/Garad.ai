@extends('panel.layout.settings')
@section('title', __('AI Image Pro Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        method="POST"
        action="{{ route('dashboard.admin.ai-image-pro.settings.update') }}"
    >
        @csrf
        @method('PUT')

        <div class="mb-6">
            <x-form-step
                step="1"
                label="{{ __('Select Active AI Models') }}"
            >
            </x-form-step>
            <x-card class="mb-2 max-md:text-center">
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
                            >@lang('No Sodels Selected')</span>
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

            <x-form-step
                step="2"
                label="{{ __('Show/Hide Sections') }}"
            >
            </x-form-step>
            <x-card
                class="mb-2"
                size="lg"
            >
                <div class="flex flex-row gap-4">
                    <div class="w-1/2">
                        <x-form.checkbox
                            class="border-input rounded-input border !px-2.5 !py-3"
                            name="ai-image-pro:show-tools-section"
                            label="{{ __('Tools Section') }}"
                            checked="{{ (bool) setting('ai-image-pro:show-tools-section', 1) }}"
                            tooltip="{{ __('Show or hide the Tools section on the AI Image Pro home page.') }}"
                        />
                    </div>

                    <div class="w-1/2">
                        <x-form.checkbox
                            class="border-input rounded-input border !px-2.5 !py-3"
                            name="ai-image-pro:show-community-section"
                            label="{{ __('Gallery Section') }}"
                            checked="{{ (bool) setting('ai-image-pro:show-community-section', 1) }}"
                            tooltip="{{ __('Show or hide the Community Gallery section on the AI Image Pro home page.') }}"
                        />
                    </div>
                </div>
            </x-card>

            <x-form-step
                step="3"
                label="{{ __('AI Image Pro Display Type') }}"
            >
            </x-form-step>
            <x-card class="mb-2 max-md:text-center">
                <div class="col-md-12">
                    <x-alert class="mb-2">
                        <p>
                            @lang('If you want "AI Image Pro" to appear as a separate menu option, select the "Menu" option. If you prefer "AI Image" to be included in the Pro edition, choose the "AI Image" option. To have both, select the "Both" option.')
                        </p>
                    </x-alert>
                    <select
                        class="form-select"
                        id="ai_image_pro_display_type"
                        name="ai_image_pro_display_type"
                    >
                        <option
                            value="menu"
                            {{ setting('ai_image_pro_display_type', 'both_fm') === 'menu' ? 'selected' : '' }}
                        >
                            {{ __('Dashboard Side Menu') }}
                        </option>
                        <option
                            value="ai_image"
                            {{ setting('ai_image_pro_display_type', 'both_fm') === 'ai_image' ? 'selected' : '' }}
                        >
                            {{ __('AI Image') }}
                        </option>
                        <option
                            value="frontend"
                            {{ setting('ai_image_pro_display_type', 'both_fm') === 'frontend' ? 'selected' : '' }}
                        >
                            {{ __('Site Front End') }}
                        </option>
                        <option
                            value="both_fm"
                            {{ setting('ai_image_pro_display_type', 'both_fm') === 'both_fm' ? 'selected' : '' }}
                        >
                            {{ __('Site Front End & Dashboard Side Menu') }}
                        </option>
                    </select>
                </div>
            </x-card>

            {{-- daily limit for guest users --}}
            <x-form-step
                step="4"
                label="{{ __('Daily Limit for Guest Users') }}"
            >
            </x-form-step>
            <x-card class="mb-2 max-md:text-center">
                <div class="col-md-12">
                    <x-alert class="mb-3">
                        <p>
                            {{ __('Set the maximum number of images that guest users can generate per day. Enter -1 for unlimited.') }}
                        </p>
                    </x-alert>
                    <x-forms.input
                        class="w-full"
                        type="number"
                        name="ai_image_pro:guest_daily_limit"
                        label="{{ __('Daily Limit') }}"
                        value="{{ setting('ai_image_pro:guest_daily_limit', 2) }}"
                        min="-1"
                        step="1"
                    />
                </div>
            </x-card>

            {{-- Footer Settings --}}
            <x-form-step
                step="5"
                label="{{ __('Footer Settings') }}"
            >
            </x-form-step>
            <x-card
                class="mb-2"
                size="lg"
            >
                <div class="mb-6">
                    <x-form.checkbox
                        class="border-input rounded-input border !px-2.5 !py-3"
                        name="ai-image-pro:show-footer"
                        label="{{ __('Show Footer') }}"
                        checked="{{ (bool) setting('ai-image-pro:show-footer', 1) }}"
                        tooltip="{{ __('Show or hide the footer section on the AI Image Pro page.') }}"
                    />
                </div>

                <div class="mb-6">
                    <x-forms.input
                        type="text"
                        name="ai-image-pro:footer-copyright"
                        label="{{ __('Copyright Text') }}"
                        value="{{ setting('ai-image-pro:footer-copyright', '') }}"
                        placeholder="{{ __('Leave empty to use default from site settings') }}"
                    />
                </div>

                <div class="mb-6">
                    <x-form.checkbox
                        class="border-input rounded-input border !px-2.5 !py-3"
                        name="ai-image-pro:footer-show-social"
                        label="{{ __('Show Social Icons') }}"
                        checked="{{ (bool) setting('ai-image-pro:footer-show-social', 1) }}"
                        tooltip="{{ __('Show social media icons in the footer (uses site social accounts).') }}"
                    />
                </div>
            </x-card>

            {{-- Footer Columns --}}
            @php
                $footerColumns = json_decode(setting('ai-image-pro:footer-columns', '[]'), true) ?: [];
                $defaultColumns = [
                    ['title' => 'Models', 'enabled' => true, 'source' => 'models', 'links' => []],
                    ['title' => 'Editor', 'enabled' => true, 'source' => 'editor', 'links' => []],
                    ['title' => 'Tools', 'enabled' => true, 'source' => 'tools', 'links' => []],
                    ['title' => 'Company', 'enabled' => true, 'source' => 'pages', 'links' => []],
                ];
                if (empty($footerColumns)) {
                    $footerColumns = $defaultColumns;
                }
            @endphp

            <div x-data="{
                columns: {{ json_encode($footerColumns) }},
                sources: [
                    { value: 'models', label: '{{ __('AI Models (Auto)') }}' },
                    { value: 'editor', label: '{{ __('Editor Features (Auto)') }}' },
                    { value: 'tools', label: '{{ __('Tools (Auto)') }}' },
                    { value: 'pages', label: '{{ __('Footer Pages (Auto)') }}' },
                    { value: 'custom', label: '{{ __('Custom Links') }}' },
                ],
                addColumn() {
                    this.columns.push({
                        title: '{{ __('New Column') }}',
                        enabled: true,
                        source: 'custom',
                        links: []
                    });
                },
                removeColumn(index) {
                    this.columns.splice(index, 1);
                },
                addLink(columnIndex) {
                    this.columns[columnIndex].links.push({ label: '', url: '' });
                },
                removeLink(columnIndex, linkIndex) {
                    this.columns[columnIndex].links.splice(linkIndex, 1);
                }
            }">
                <input
                    type="hidden"
                    name="ai-image-pro:footer-columns"
                    :value="JSON.stringify(columns)"
                >

                <template
                    x-for="(column, colIndex) in columns"
                    :key="colIndex"
                >
                    <x-card
                        class="mb-4"
                        size="lg"
                    >
                        <div class="mb-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <span
                                    class="text-sm font-semibold text-foreground/70"
                                    x-text="'{{ __('Column') }} ' + (colIndex + 1)"
                                ></span>
                                <label class="flex cursor-pointer items-center gap-2 text-xs">
                                    <input
                                        class="size-4 rounded border-border"
                                        type="checkbox"
                                        x-model="column.enabled"
                                    >
                                    <span>{{ __('Enabled') }}</span>
                                </label>
                            </div>
                            <button
                                class="text-xs text-red-500 hover:text-red-700"
                                type="button"
                                @click="removeColumn(colIndex)"
                                x-show="columns.length > 1"
                            >
                                <x-tabler-trash class="size-4" />
                            </button>
                        </div>

                        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-medium">{{ __('Column Title') }}</label>
                                <input
                                    class="form-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                    type="text"
                                    x-model="column.title"
                                    placeholder="{{ __('Column Title') }}"
                                >
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-medium">{{ __('Content Source') }}</label>
                                <select
                                    class="form-select w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                    x-model="column.source"
                                >
                                    <template
                                        x-for="source in sources"
                                        :key="source.value"
                                    >
                                        <option
                                            :value="source.value"
                                            x-text="source.label"
                                        ></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div
                            x-show="column.source === 'custom'"
                            x-transition
                        >
                            <label class="mb-2 block text-xs font-medium">{{ __('Custom Links') }}</label>

                            <div class="space-y-2">
                                <template
                                    x-for="(link, linkIndex) in column.links"
                                    :key="linkIndex"
                                >
                                    <div class="flex items-center gap-2">
                                        <input
                                            class="form-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                            type="text"
                                            x-model="link.label"
                                            placeholder="{{ __('Link Label') }}"
                                        >
                                        <input
                                            class="form-input w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                                            type="text"
                                            x-model="link.url"
                                            placeholder="{{ __('URL (e.g., /page/about or https://...)') }}"
                                        >
                                        <button
                                            class="shrink-0 text-red-500 hover:text-red-700"
                                            type="button"
                                            @click="removeLink(colIndex, linkIndex)"
                                        >
                                            <x-tabler-x class="size-4" />
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <button
                                class="mt-3 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                type="button"
                                @click="addLink(colIndex)"
                            >
                                <x-tabler-plus class="size-3" />
                                {{ __('Add Link') }}
                            </button>
                        </div>

                        <p
                            class="text-xs text-foreground/50"
                            x-show="column.source !== 'custom'"
                        >
                            <span x-show="column.source === 'models'">{{ __('Will automatically display active AI Image models.') }}</span>
                            <span x-show="column.source === 'editor'">{{ __('Will automatically display editor features from Advanced Image extension.') }}</span>
                            <span x-show="column.source === 'tools'">{{ __('Will automatically display available tools (Image Editor, Marketing, Assistant, etc.).') }}</span>
                            <span x-show="column.source === 'pages'">{{ __('Will automatically display pages marked as "Show on Footer" or Footer Menu items.') }}</span>
                        </p>
                    </x-card>
                </template>

                <button
                    class="inline-flex items-center gap-2 rounded-lg border border-dashed border-border px-4 py-2 text-xs font-medium transition-colors hover:border-primary hover:text-primary"
                    type="button"
                    @click="addColumn()"
                    x-show="columns.length < 6"
                >
                    <x-tabler-plus class="size-4" />
                    {{ __('Add Column') }}
                </button>
            </div>
        </div>

        <x-form-step
            step="6"
            label="{{ __('AI Image Pro Edit Modal') }}"
        >
        </x-form-step>
        <x-card class="mb-2 max-md:text-center">
            <div class="col-md-12">
                <x-alert class="mb-2">
                    <p>
                        @lang('Choose which model you want to use for editing images, then pick the option that best matches your workflow.')
                    </p>
                </x-alert>
                <select
                    class="form-select"
                    id="ai_image_pro_edit_model"
                    name="ai_image_pro_edit_model"
                >
                    <option
                        value="nano-banana"
                        {{ setting('ai_image_pro_edit_model', 'gpt-image-1.5') === 'nano-banana' ? 'selected' : '' }}
                    >
                        {{ __('Nano Banana') }}
                    </option>
                    <option
                        value="nano-banana-pro"
                        {{ setting('ai_image_pro_edit_model', 'gpt-image-1.5') === 'nano-banana-pro' ? 'selected' : '' }}
                    >
                        {{ __('Nano Banana Pro') }}
                    </option>
                    <option
                        value="nano-banana-2"
                        {{ setting('ai_image_pro_edit_model', 'gpt-image-1.5') === 'nano-banana-2' ? 'selected' : '' }}
                    >
                        {{ __('Nano Banana 2') }}
                    </option>
                    <option
                        value="gpt-image-1.5"
                        {{ setting('ai_image_pro_edit_model', 'gpt-image-1.5') === 'gpt-image-1.5' ? 'selected' : '' }}
                    >
                        {{ __('GPT-IMAGE-1.5') }}
                    </option>
                    <option
                        value="gpt-image-2"
                        {{ setting('ai_image_pro_edit_model', 'gpt-image-1.5') === 'gpt-image-2' ? 'selected' : '' }}
                    >
                        {{ __('GPT-IMAGE-2') }}
                    </option>
                    <option
                        value="xai/grok-imagine-image"
                        {{ setting('ai_image_pro_edit_model', 'gpt-image-1.5') === 'xai/grok-imagine-image' ? 'selected' : '' }}
                    >
                        {{ __('Grok Imagine Image') }}
                    </option>
                </select>
            </div>
        </x-card>

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
