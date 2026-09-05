@php
    $primary_tool_keys = ['uncrop', 'reimagine', 'remove_background', 'cleanup', 'upscale'];

    $prompt_filters = [
        'all' => __('All'),
        'favorite' => __('Favorite'),
    ];
@endphp

@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_navbar' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'layout_wide' => true,
    'disable_mobile_bottom_menu' => true,
])
@section('title', __('Advanced Image Editor'))
@section('titlebar_actions', '')

@section('content')
    <div
        class="pointer-events-none absolute inset-x-0 top-0 z-0 overflow-hidden opacity-30 dark:hidden"
        aria-hidden="true"
    >
        <img
            class="w-full"
            src="{{ custom_theme_url('assets/img/advanced-image/image-editor-bg.jpg') }}"
            alt="Background image"
        >
    </div>
    <div
        class="lqd-adv-img-editor relative z-1 pt-[--header-h] [--header-h:60px] [--sidebar-w:100vw] md:[--sidebar-w:370px]"
        x-data='advancedImageEditor({
				tools: @json($tools),
				primaryToolKeys: @json($primary_tool_keys),
			})'
        @keyup.escape.window="!modalShow && switchView('<')"
    >
        @include('advanced-image::shared-components.top-navbar')

        @include('advanced-image::home.home', ['images' => $userOpenai->take(5), 'tools' => $tools])

        @include('advanced-image::editor.editor', ['tools' => $tools, 'primary_tool_keys' => $primary_tool_keys])

        @include('advanced-image::gallery.gallery', ['images' => $userOpenai])

        @include('advanced-image::shared-components.image-modal')
    </div>
@endsection

@push('script')
    <script>
        const AIModelsforTool = @json(\Illuminate\Support\Arr::pluck($tools, 'model', 'action'));

        // Handle pending tool action from modal
        document.addEventListener('alpine:initialized', () => {
            const pendingAction = sessionStorage.getItem('pendingToolAction');

            if (pendingAction) {
                try {
                    const toolData = JSON.parse(pendingAction);

                    // Clear the stored action
                    sessionStorage.removeItem('pendingToolAction');

                    // Get Alpine component instance
                    const editorComponent = Alpine.$data(document.querySelector('[x-data*="advancedImageEditor"]'));

                    if (editorComponent) {
                        // Execute the tool action
                        editorComponent.editingImage = {};
                        editorComponent.selectedTool = toolData.action;
                        editorComponent.switchToolsCat({
                            toolKey: toolData.action
                        });
                        editorComponent.switchView('editor');
                    }
                } catch (error) {
                    console.error('Error executing pending tool action:', error);
                    sessionStorage.removeItem('pendingToolAction');
                }
            }

            // Handle pending image from AI Image Pro "Edit with Editor"
            const pendingImage = sessionStorage.getItem('pendingImageForAdvancedEditor');

            if (pendingImage) {
                try {
                    const imageData = JSON.parse(pendingImage);

                    // Clear the stored image
                    sessionStorage.removeItem('pendingImageForAdvancedEditor');

                    // Get Alpine component instance
                    const editorComponent = Alpine.$data(document.querySelector('[x-data*="advancedImageEditor"]'));

                    if (editorComponent && imageData.url) {
                        // Show loading state
                        editorComponent.busy = true;
                        editorComponent.switchView('editor');

                        // Pre-load the image before setting it
                        const img = new Image();
                        img.onload = () => {
                            // Image loaded successfully, now set it
                            editorComponent.editingImage = {
                                output: imageData.url,
                                title: imageData.title || 'image'
                            };

                            // Auto-select reimagine tool if available
                            const reimagineTool = editorComponent.tools.find(t => t.action === 'reimagine');
                            if (reimagineTool) {
                                editorComponent.selectedTool = 'reimagine';
                                editorComponent.switchToolsCat({
                                    toolKey: 'reimagine'
                                });
                            }

                            editorComponent.busy = false;

                            if (window.toastr) {
                                toastr.success('{{ __('Image loaded! Ready to edit.') }}');
                            }
                        };
                        img.onerror = () => {
                            editorComponent.busy = false;
                            if (window.toastr) {
                                toastr.error('{{ __('Failed to load image') }}');
                            }
                            editorComponent.switchView('home');
                        };
                        img.src = imageData.url;
                    }
                } catch (error) {
                    console.error('Error loading pending image:', error);
                    sessionStorage.removeItem('pendingImageForAdvancedEditor');
                }
            }
        });

        async function fetchImageStatus() {
            try {
                const response = await fetch('/dashboard/user/openai/generator/check/status');
                const data = await response.json();
                if (data.data) {
                    data.data.forEach(item => updateImage(item));
                }
            } catch (error) {
                console.error('Error fetching image status:', error);
            }
        }

        function updateImage(item) {
            const wrapperEl = document.querySelector(`[data-id="${item.id}"]`);
            if (!wrapperEl) return;

            const imgElement = wrapperEl.querySelector(`#img-${item.response}-${item.id}`);
            const imgElementPayloadId = wrapperEl.querySelector(`#img-${item.response}-${item.id}-payload`);
            const imgElementDownload = wrapperEl.querySelector(`#img-${item.response}-${item.id}-download`);

            if (imgElement) {
                imgElement.src = item.img;
                imgElement.alt = item.title;
            }

            if (imgElementDownload) {
                imgElementDownload.href = item.img;
                imgElementDownload.target = '_blank';
            }

            wrapperEl.setAttribute('data-payload', JSON.stringify(item));
            if ('refreshFsLightbox' in window) {
                refreshFsLightbox();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setInterval(fetchImageStatus, 5000);
        });
    </script>
@endpush
