<script>
    var chatid = @json($list)[0]?.id;
    $(`#chat_${chatid}`).addClass('active').siblings().removeClass('active');
    const category = @json($category);
    const openai_model = '{!! $setting->openai_default_model !!}';
    const prompt_prefix = document.getElementById("prompt_prefix")?.value;

    let messages = [];
    let training = [];

    @if ($chat_completions != null)
        training = @json($chat_completions);
    @endif

    messages.push({
        role: "assistant",
        content: prompt_prefix
    });

    @if ($lastThreeMessage != null)
        @foreach ($lastThreeMessage as $entry)
            message = {
                role: "user",
                content: @json($entry->input)
            };
            messages.push(message);
            message = {
                role: "assistant",
                content: @json(str_replace('/http', 'http', $entry->output))
            };
            messages.push(message);
        @endforeach
    @endif
</script>

<script src="{{ custom_theme_url('/assets/js/panel/openai_chat.js?v=' . time()) }}"></script>

<script>
    @if (count($list) === 0)
        window.addEventListener("load", (event) => {
            return startNewChat(
                {{ $category?->id }},
                '{{ LaravelLocalization::getCurrentLocale() }}',
                'chatpro-image'
            );
        });
    @endif

    // sendRequest function for chat_js.blade.php

    function sendRequest(type, images, responseObj, sharedMessageUUID = null) {
        const form = document.querySelector('#chatImageProForm');

        if (!form) {
            return toastr.error('{{ __('Could not find the form element') }}');
        };

        const formData = new FormData(form);

        // Save important hidden values before reset
        const chatId = form.querySelector('#chat_id')?.value;
        const chatType = form.querySelector('#chatType')?.value || form.querySelector('[name="chatType"]')?.value;

        if (form.elements.prompt) {
            form.elements.prompt.value = '';
        }
        if (form.elements.reimagine_image_url) {
            form.elements.reimagine_image_url.value = '';
        }
        if (form.elements.reimagine_prompt) {
            form.elements.reimagine_prompt.value = '';
        }
        if (form.elements.image_reference) {
            form.elements.image_reference.value = '';
        }
        if (form.elements['image_reference[]']?.length) {
            form.elements['image_reference[]'].forEach(element => {
                element.value = '';
            });
        }

        // Restore essential hidden values
        const chatIdInput = form.querySelector('#chat_id');
        if (chatIdInput && chatId) chatIdInput.value = chatId;
        const chatTypeInput = form.querySelector('#chatType') || form.querySelector('[name="chatType"]');
        if (chatTypeInput && chatType) chatTypeInput.value = chatType;

        // Clear the reimagine hidden fields (they should stay empty after reset, but be explicit)
        const reimagineImageInput = form.querySelector('#reimagine_image_url');
        if (reimagineImageInput) reimagineImageInput.value = '';
        const reimaginePromptInput = form.querySelector('#reimagine_prompt');
        if (reimaginePromptInput) reimaginePromptInput.value = '';

        // Clear any Alpine state for image input helpers
        const imageInputHelpers = form.querySelectorAll('[x-data*="ImageInputHelper"], [x-data*="imageInput"]');
        imageInputHelpers.forEach(helper => {
            const alpineData = Alpine.$data(helper);
            if (alpineData) {
                if (alpineData.uploadingImages) alpineData.uploadingImages = [];
                if (alpineData.storedFiles) alpineData.storedFiles = [];
                if (alpineData.files) alpineData.files = [];
                if (alpineData.previewUrl) alpineData.previewUrl = null;
                if (alpineData.selectedFile) alpineData.selectedFile = null;
            }
        });

        // Clear the main form component's formValues
        const formComponent = form.querySelector('[x-data*="aiImageProChatGeneratorForm"]') || form;
        if (formComponent) {
            const alpineData = Alpine.$data(formComponent);
            if (alpineData && alpineData.formValues) {
                // Only clear prompt, keep model settings
                if (alpineData.formValues.prompt !== undefined) {
                    alpineData.formValues.prompt = '';
                }
            }
        }

        // Dispatch event for any other listeners
        window.dispatchEvent(new CustomEvent('clear-image-inputs'));

        // Clear any file name displays
        const fileNameDisplays = form.querySelectorAll('.file-name');
        fileNameDisplays.forEach(display => {
            // Reset to original text if it had default content
            if (!display.querySelector('template')) {
                display.textContent = '';
            }
        });

        // Display user uploaded images in chat (check for image_reference or image_reference[])
        const imageFiles = formData.getAll('image_reference[]').concat(formData.getAll('image_reference'));

        if (imageFiles.length) {
            const chatUserImageBubbleTemplate = document.querySelector('#chat_user_image_bubble');
            const processedFiles = new Set();

            if (chatUserImageBubbleTemplate) {
                const bubbleEl = chatUserImageBubbleTemplate.content.cloneNode(true).firstElementChild;
                const linkTemplate = bubbleEl.querySelector('a');
                let isFirstImage = true;

                imageFiles.forEach(file => {
                    if (file instanceof File && file.type.startsWith('image/')) {
                        // Filter duplicates by file name + size
                        const fileKey = `${file.name}-${file.size}`;
                        if (processedFiles.has(fileKey)) return;
                        processedFiles.add(fileKey);

                        const imageUrl = URL.createObjectURL(file);

                        if (isFirstImage) {
                            // Use the existing link element for the first image
                            linkTemplate.href = imageUrl;
                            linkTemplate.querySelector('.img-content').src = imageUrl;
                            isFirstImage = false;
                        } else {
                            // Clone the link element for additional images
                            const newLink = linkTemplate.cloneNode(true);
                            newLink.href = imageUrl;
                            newLink.querySelector('.img-content').src = imageUrl;
                            bubbleEl.appendChild(newLink);
                        }
                    }
                });

                // Only insert if we have at least one image
                if (!isFirstImage) {
                    responseObj.bubbleEl?.insertAdjacentElement('beforebegin', bubbleEl);

                    if (typeof refreshFsLightbox === 'function') {
                        refreshFsLightbox();
                    }
                }

                setTimeout(() => {
                    if ('scrollConversationArea' in window) {
                        scrollConversationArea({
                            smooth: true
                        });
                    }
                }, 100);
            }
        }
        const throttledOnAiResponse = _.throttle(onAiResponse, 100);
        const abortController = new AbortController();

        // Track received events to prevent duplicates
        let receivedMessageId = false;
        let receivedFunctionCall = false;
        let receivedImageRecord = false;
        let processedRecordIds = new Set();

        if (category?.id) {
            formData.append('category_id', category?.id);
        };

        formData.append('template_type', type);

        responseObj.abortController = abortController;
        responseObj.request = {
            ...responseObj.request ?? {},
            formData
        };

        fetchEventSource('/dashboard/user/generator/generate-stream', {
            openWhenHidden: true,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            body: formData,
            signal: responseObj.abortController.signal,

            onmessage: async e => {
                const txt = e.data;

                // Determine event type from the event field
                let eventType = 'data'; // default
                if (e.event) {
                    if (e.event === 'message') eventType = 'message';
                    else if (e.event === 'title') eventType = 'title';
                    else if (e.event === 'function_call') eventType = 'function_call';
                    else if (e.event === 'image_record') eventType = 'image_record';
                }

                // Handle message ID event (only once)
                if (eventType === 'message' && !receivedMessageId) {
                    receivedMessageId = true;
                    const responseId = txt;
                    responseObj.responseId = responseId;
                    responseObj.bubbleEl?.setAttribute('data-message-id', responseId);
                    responseObj.acceptButtonEl?.setAttribute('data-message-id', responseId);
                    responseObj.regenerateButtonEl?.setAttribute('data-message-id', responseId);
                    return;
                }

                if (eventType === 'title') {
                    try {
                        const titleData = JSON.parse(txt);
                        if (titleData.title && titleData.chat_id) {
                            applyStreamedChatTitle(titleData.chat_id, titleData.title);
                            stripTitleFromBubble(responseObj);
                            throttledOnAiResponse(responseObj);
                        }
                    } catch (err) {}
                    return;
                }

                // Handle function_call event (only once)
                if (eventType === 'function_call' && !receivedFunctionCall) {
                    receivedFunctionCall = true;
                    const functionName = txt;

                    if (functionName === 'generate_edit_image') {
                        const chatsV2Store = Alpine.store('chatsV2');
                        if (chatsV2Store?.handleImageGenerationStarted) {
                            chatsV2Store.handleImageGenerationStarted(responseObj);
                        }
                    }
                    return; // CRITICAL: Don't add to response
                }

                // Handle image_record event (only once per record ID)
                if (eventType === 'image_record') {
                    const recordId = parseInt(txt);

                    if (!recordId || isNaN(recordId)) {
                        console.error('Invalid record ID received:', txt);
                        return;
                    }

                    // Check if this record ID has already been processed
                    if (processedRecordIds.has(recordId)) {
                        console.log(`Record ID ${recordId} already processed, ignoring duplicate`);
                        return;
                    }

                    // Mark as processed
                    processedRecordIds.add(recordId);
                    receivedImageRecord = true;

                    // Dispatch to Alpine component
                    const chatsV2Store = Alpine.store('chatsV2');
                    if (chatsV2Store?.handleImageRecordReceived) {
                        chatsV2Store.handleImageRecordReceived(recordId, responseObj);
                    }

                    return; // CRITICAL: Don't add to response
                }

                // Handle regular data events (actual content)
                if (txt == null) return;

                const responseIndex = aiResponses.findIndex(
                    response => response.responseId === responseObj.responseId
                );
                const isDoneSignal = txt === '[DONE]';

                if (isDoneSignal) {
                    messages.push({
                        role: 'assistant',
                        content: getAiResponseString({
                            responseObj
                        }),
                    });

                    // Keep message history manageable
                    if (messages.length >= 6) {
                        messages.splice(1, 2);
                    }

                    // Don't push [DONE] to visible response
                    responseObj.responseStreaming = false;
                    responseObj.abortController = null;

                    throttledOnAiResponse(responseObj);

                    if (responseIndex === aiResponses.length - 1) {
                        window.removeEventListener('beforeunload', onBeforePageUnload);
                    }

                    return;
                }

                // Only add to response if:
                // 1. We've received the message ID
                // 2. It's actual content (data event)
                // 3. It's not empty
                if (receivedMessageId && eventType === 'data' && txt.trim()) {
                    responseObj.response.push(txt);
                    throttledOnAiResponse(responseObj);
                }
            },

            onerror: err => {
                console.error('Stream error:', err);
                window.removeEventListener('beforeunload', onBeforePageUnload);

                switchGenerateButtonsStatus(false);

                responseObj.abortController = null;
                responseObj.responseStreaming = false;
                responseObj.response.push(`Error: ${err.message}`);

                throttledOnAiResponse(responseObj);

                throw err;
            },
        });
    }
</script>
