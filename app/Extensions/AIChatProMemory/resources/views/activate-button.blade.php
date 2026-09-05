<div
    class="translate-y-1 opacity-0 transition-all group-[&.lqd-is-active]/dropdown:translate-y-0 group-[&.lqd-is-active]/dropdown:opacity-100 group-[&.lqd-is-active]/dropdown:delay-[120ms]">
    <div
        class="relative cursor-pointer rounded-md px-3.5 py-2 text-2xs font-medium text-heading-foreground/60 transition-all hover:bg-heading-foreground/[3%] hover:text-heading-foreground [&_.lqd-chat-share-modal-trigger]:absolute [&_.lqd-chat-share-modal-trigger]:inset-0 [&_.lqd-chat-share-modal-trigger]:z-2 [&_.lqd-chat-share-modal-trigger]:opacity-0">
        <x-modal
            class:modal-backdrop="backdrop-blur-[2px] bg-foreground/30"
            class:modal-content="w-[min(690px,100%)]"
            class:modal-head="!hidden"
            class:modal-body="max-md:p-4"
            class="self-center"
            id="ai-memory-modal"
            anchor="end"
            title="{{ __('AI Memory') }}"
        >
            <x-slot:trigger
                class="lqd-chat-share-modal-trigger h-6 max-md:inline-flex max-md:size-8 max-md:items-center max-md:justify-center max-md:rounded-full max-md:bg-background max-md:p-0 max-md:text-foreground max-md:shadow-md max-md:hover:text-primary-foreground md:h-6 md:px-2 md:py-1 md:text-2xs"
                variant="primary"
                disable-modal="{{ $app_is_demo }}"
                disable-modal-message="{{ __('This feature is disabled in Demo version.') }}"
                title="{{ __('AI Memory') }}"
            >
            </x-slot:trigger>
            <x-slot:modal>
                <button
                    class="absolute end-2 top-2 z-50 inline-grid size-8 place-items-center rounded-full bg-card-background shadow-lg shadow-black/5 transition hover:bg-heading-foreground/10"
                    @click="modalOpen = false"
                    type="button"
                >
                    <x-tabler-x class="size-5" />
                </button>

                <div
                    x-data="instructionsManager({{ $chat->id ?? 'null' }})"
                    x-init="init()"
                >
                    <!-- Description -->
                    <h2 class="mb-2 text-xl font-semibold text-heading-foreground">
                        {{ __('AI Memory') }}
                    </h2>

                    <p class="mb-4 text-pretty text-sm text-heading-foreground/70">
                        {{ __('To enhance its responses, your chatbot can remember non-sensitive information about you, allowing it to replay faster, more accurately and in a more personalized manner.') }}
                    </p>

                    <!-- User Instructions (Editable) -->
                    <div class="mt-4">
                        <x-forms.input
                            class:label="text-heading-foreground font-medium"
                            label="{{ __('Your Personal Instructions') }}"
                            placeholder="{{ __('E.g., Remember that my favorite color is blue and I enjoy hiking on weekends.') }}"
                            name="instructions"
                            size="lg"
                            type="textarea"
                            rows="5"
                            x-model="userInstructions"
                            ::disabled="loading"
                        />
                        {{-- <p class="mt-1 text-2xs text-heading-foreground/50"> --}}
                        {{-- 	<span x-show="hasUserOverride" class="text-green-600">✓ {{ __('Using your personal instructions') }}</span> --}}
                        {{-- 	<span x-show="!hasUserOverride" class="text-heading-foreground/40">{{ __('Leave empty to use admin defaults') }}</span> --}}
                        {{-- </p> --}}
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 space-y-2">
                        <x-button
                            class="w-full py-2"
                            variant="secondary"
                            type="button"
                            @click="saveInstructions()"
                            ::disabled="loading"
                        >
                            <span x-text="!loading ? '{{ __('Save Memory') }}' : '{{ __('Saving') }}'">
                                {{ __('Save Memory') }}
                            </span>
                            <span class="inline-grid size-7 place-content-center rounded-full bg-background text-foreground dark:bg-heading-foreground dark:text-header-background">
                                <x-tabler-chevron-right
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4"
                                    x-show="!loading"
                                />
                                <x-tabler-loader-2
                                    class="col-start-1 col-end-1 row-start-1 row-end-1 size-4 animate-spin"
                                    x-cloak
                                    x-show="loading"
                                />
                            </span>
                        </x-button>
                    </div>

                    <!-- Message Display -->
                    <div
                        class="mt-4 rounded-md p-3 text-sm transition-all"
                        x-show="message"
                        :class="messageType === 'success' ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-400' :
                            'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-400'"
                        x-text="message"
                        x-transition
                    ></div>
                </div>

                <script>
                    function instructionsManager(chatId) {
                        return {
                            chatId: chatId,
                            userInstructions: '',
                            hasUserOverride: false,
                            loading: false,
                            message: '',
                            messageType: 'success',

                            async init() {
                                this.syncChatId();

                                // During refresh/AJAX swap, chat id can appear slightly later.
                                if (this.chatId) {
                                    await this.loadInstructions();
                                    return;
                                }

                                let attempts = 0;
                                const retry = setInterval(async () => {
                                    attempts++;
                                    this.syncChatId();

                                    if (this.chatId) {
                                        clearInterval(retry);
                                        await this.loadInstructions();
                                        return;
                                    }

                                    if (attempts >= 10) {
                                        clearInterval(retry);
                                    }
                                }, 250);
                            },

                            syncChatId(showError = false) {
                                const hiddenChatId = document.getElementById('chat_id')?.value;
                                const globalChatId = window.chatid;
                                const resolvedChatId = this.chatId || hiddenChatId || globalChatId || null;

                                this.chatId = resolvedChatId ? String(resolvedChatId) : null;

                                if (!this.chatId && showError) {
                                    this.showMessage('{{ __('Chat ID is missing.') }}', 'error');
                                }

                                return !!this.chatId;
                            },

                            async loadInstructions() {
                                if (!this.syncChatId()) {
                                    return;
                                }

                                this.loading = true;
                                this.message = '';

                                try {
                                    const response = await fetch(`/ai-chat-pro-memory/instructions?chat_id=${this.chatId}`, {
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        }
                                    });

                                    if (!response.ok) throw new Error('Network response was not ok');

                                    const data = await response.json();

                                    if (data.success) {
                                        this.userInstructions = data.user_instructions || '';
                                        this.hasUserOverride = data.has_user_override;
                                    } else {
                                        this.showMessage(data.message || '{{ __('Failed to load instructions.') }}', 'error');
                                    }
                                } catch (error) {
                                    console.error('Error loading instructions:', error);
                                    this.showMessage('{{ __('An error occurred while loading instructions.') }}', 'error');
                                } finally {
                                    this.loading = false;
                                }
                            },

                            async saveInstructions() {
                                if (!this.syncChatId(true)) {
                                    return;
                                }

                                this.loading = true;
                                this.message = '';

                                try {
                                    const response = await fetch('/ai-chat-pro-memory/instructions', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        },
                                        body: JSON.stringify({
                                            instructions: this.userInstructions,
                                            chat_id: this.chatId
                                        })
                                    });

                                    if (!response.ok) throw new Error('Network response was not ok');

                                    const data = await response.json();

                                    if (data.success) {
                                        this.hasUserOverride = true;
                                        this.showMessage(data.message, 'success');
                                    } else {
                                        this.showMessage(data.message || '{{ __('Failed to save instructions.') }}', 'error');
                                    }
                                } catch (error) {
                                    console.error('Error saving instructions:', error);
                                    this.showMessage('{{ __('An error occurred while saving.') }}', 'error');
                                } finally {
                                    this.loading = false;
                                }
                            },

                            async clearInstructions() {
                                if (!confirm('{{ __('Are you sure you want to reset to default instructions?') }}')) {
                                    return;
                                }

                                if (!this.syncChatId(true)) {
                                    return;
                                }

                                this.loading = true;
                                this.message = '';

                                try {
                                    const response = await fetch(`/ai-chat-pro-memory/instructions?chat_id=${this.chatId}`, {
                                        method: 'DELETE',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        }
                                    });

                                    if (!response.ok) throw new Error('Network response was not ok');

                                    const data = await response.json();

                                    if (data.success) {
                                        this.userInstructions = '';
                                        this.hasUserOverride = false;
                                        this.showMessage(data.message, 'success');
                                    } else {
                                        this.showMessage(data.message || '{{ __('Failed to clear instructions.') }}', 'error');
                                    }
                                } catch (error) {
                                    console.error('Error clearing instructions:', error);
                                    this.showMessage('{{ __('An error occurred while clearing.') }}', 'error');
                                } finally {
                                    this.loading = false;
                                }
                            },

                            showMessage(msg, type = 'success') {
                                toastr[type === 'success' ? 'success' : 'error'](msg);
                            }
                        }
                    }
                </script>
            </x-slot:modal>
        </x-modal>
        <div class="lqd-btn inline-flex items-center first:hidden">
            {{ __('AI Memory') }}
        </div>
    </div>
</div>
