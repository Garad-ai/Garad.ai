@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_navbar' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'layout_wide' => true,
])
@section('title', __('AI Presentation'))
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
        class="lqd-adv-img-editor relative z-1 pt-[--header-h] [--header-h:60px] [--sidebar-w:370px]"
        x-data="presentationManager"
    >
        @include('ai-presentation::home.top-navbar')

        <div class="lqd-adv-img-editor-home transition-all">
            <div class="container">
                @include('ai-presentation::home.generator-form')
                @include('ai-presentation::home.recent-presentation-grid', ['presentations' => $presentations])
            </div>
        </div>

        @include('ai-presentation::gallery.gallery')
        @include('ai-presentation::home.pdf-view-modal')
    </div>
@endsection

@pushOnce('script')
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/5.4.149/pdf.min.mjs"
        type="module"
    ></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('presentationManager', () => ({
                pollingIntervals: {},
                prevViews: [],
                currentView: 'home',

                init() {
                    this.pollProcessingPresentations();
                },
                switchView(view) {
                    if (view === '<') {
                        this.currentView = this.prevViews.pop() || 'home';
                        return;
                    }

                    this.prevViews.push(this.currentView);
                    this.currentView = view || 'home';
                },

                // Start polling all processing/pending presentations
                pollProcessingPresentations() {
                    const processingItems = document.querySelectorAll('[data-payload]');
                    processingItems.forEach(item => {
                        try {
                            const payload = JSON.parse(item.getAttribute('data-payload'));
                            if (payload.status === 'processing' || payload.status === 'pending') {
                                this.startPolling(payload.generation_id);
                            }
                        } catch (e) {
                            console.error('Error parsing payload:', e);
                        }
                    });
                },

                // Poll backend for a single generation
                startPolling(generationId) {
                    if (this.pollingIntervals[generationId]) return;

                    this.pollingIntervals[generationId] = setInterval(async () => {
                        const item = document.querySelector(`[data-generation-id="${generationId}"]`);
                        if (!item) {
                            clearInterval(this.pollingIntervals[generationId]);
                            delete this.pollingIntervals[generationId];
                            return;
                        }

                        try {
                            const response = await fetch(`/dashboard/user/ai-presentation/status/${generationId}`);
                            const result = await response.json();

                            if (result.type === 'success' && result.data) {
                                const status = result.data.status;
                                this.updatePresentationItem(generationId, result.data);

                                if (status === 'completed' || status === 'failed') {
                                    clearInterval(this.pollingIntervals[generationId]);
                                    delete this.pollingIntervals[generationId];
                                }
                            }
                        } catch (error) {
                            console.error('Error polling presentation status:', error);
                        }
                    }, 5000);
                },

                // Update a single presentation card
                updatePresentationItem(generationId, data) {
                    const item = document.querySelector(`[data-generation-id="${generationId}"]`);
                    if (!item) return;

                    item.setAttribute('data-payload', JSON.stringify(data));

                    const container = item.querySelector('[class*="aspect-[4/3]"]');
                    if (!container) return;

                    // Remove old overlays
                    container.querySelectorAll('.absolute.inset-0').forEach(el => el.remove());

                    // Loading state
                    if (data.status === 'processing' || data.status === 'pending') {
                        const loadingDiv = document.createElement('div');
                        loadingDiv.className = 'absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white/80 backdrop-blur-sm dark:bg-zinc-800/80';
                        loadingDiv.innerHTML = `
                    <div class="size-12 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></div>
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('Generating') }}...</span>
                `;
                        container.appendChild(loadingDiv);

                    } else if (data.status === 'failed') {
                        // Failed state
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'absolute inset-0 flex flex-col items-center justify-center gap-3 bg-red-50/80 backdrop-blur-sm dark:bg-red-900/20';
                        errorDiv.innerHTML = `
                    <x-tabler-alert-circle class="size-12 text-red-500"></x-tabler-alert-circle>
                    <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ __('Generation Failed') }}</span>
                `;
                        container.appendChild(errorDiv);

                    } else if (data.status === 'completed' && data.pdf_url) {
                        // Completed state: clone Blade template
                        const template = document.getElementById('presentation-action-template');
                        if (!template) return;

                        const actionsDiv = template.firstElementChild.cloneNode(true);

                        // Buttons inside the overlay
                        const [viewBtn, downloadBtn, deleteBtn] = actionsDiv.querySelectorAll('button, a');

                        // View button
                        if (viewBtn) {
                            viewBtn.addEventListener('click', e => {
                                e.preventDefault();
                                window.dispatchEvent(new CustomEvent('open-pdf', {
                                    detail: {
                                        url: data.pdf_url,
                                        title: data.input_text || 'Presentation',
                                        pages: data.total_pages || 1,
                                    }
                                }));
                            });
                        }

                        // Download button
                        if (downloadBtn) {
                            downloadBtn.href = data.pdf_url;
                            downloadBtn.download = `presentation-${data.id}.pdf`;
                        }

                        // Delete button
                        if (deleteBtn) {
                            deleteBtn.addEventListener('click', e => {
                                e.preventDefault();
                                this.deletePresentation(data.id);
                            });
                        }

                        container.appendChild(actionsDiv);
                    }

                    // Update timestamp
                    const timeElement = item.querySelector('time');
                    if (timeElement && data.created_at) {
                        timeElement.textContent = new Date(data.created_at).toLocaleString();
                    }
                },

                // Delete a presentation
                deletePresentation(id) {
                    if (!confirm('Are you sure you want to delete this presentation?')) return;

                    fetch(`/dashboard/user/ai-presentation/delete/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            }
                        })
                        .then(res => res.json())
                        .then(result => {
                            if (result.type === 'success') {
                                const item = document.querySelector(`[data-id="${id}"]`);
                                if (item) {
                                    item.style.transition = 'opacity 0.3s ease-out';
                                    item.style.opacity = '0';
                                    setTimeout(() => item.remove(), 300);
                                }
                                if (window.toastr) toastr.success(result.message);
                            }
                        })
                        .catch(err => {
                            console.error('Error deleting presentation:', err);
                            alert('Failed to delete presentation. Please try again.');
                        });
                }
            }));
        });
    </script>
@endpushOnce
