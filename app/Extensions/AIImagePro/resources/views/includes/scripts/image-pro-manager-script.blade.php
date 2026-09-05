@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('imageProManager', () => ({
                pollingIntervals: {},
                prevViews: [],
                currentView: 'home',
                replicationData: null,

                // Modal state
                modalShow: false,
                activeModal: null,
                activeModalId: null,
                activeModalIdPrefix: null,
                modalImageSrc: '',
                modalImageLoading: false,
                modalImageRequestId: 0,

                // User preferences
                favorites: [],
                likes: [],
                isAuthenticated: {{ auth()->check() ? 'true' : 'false' }},

                init() {
                    // Initialize user preferences
                    this.initializeUserPreferences();

                    // Check URL parameters on load
                    this.checkUrlParams();

                    document.querySelectorAll('section.site-section').forEach(function(el) {
                        el.classList.add('lqd-is-in-view');
                    });

                    // Watch for replication data changes
                    this.$watch('replicationData', (data) => {
                        if (data) {
                            this.$nextTick(() => {
                                this.applyReplicationData();
                            });
                        }
                    });
                },

                // Initialize favorites and likes from localStorage
                initializeUserPreferences() {
                    try {
                        const savedFavorites = localStorage.getItem('galleryFavorites');
                        if (savedFavorites) {
                            this.favorites = JSON.parse(savedFavorites);
                        }

                        const savedLikes = localStorage.getItem('galleryLikes');
                        if (savedLikes) {
                            this.likes = JSON.parse(savedLikes);
                        }
                    } catch (error) {
                        console.error('Failed to load user preferences:', error);
                        this.favorites = [];
                        this.likes = [];
                    }
                },

                viewUserGallery(userId) {
                    if (!userId) {
                        this.showNotification('{{ __('Cannot view gallery for anonymous users') }}', 'info');
                        return;
                    }

                    // Get user data from active modal
                    const userData = this.activeModal?.user;

                    // Close the modal
                    this.modalShow = false;

                    // Dispatch filter event FIRST - this sets the filter before view switch
                    window.dispatchEvent(new CustomEvent('filter-user-gallery', {
                        detail: {
                            userId: userId,
                            userData: userData
                        }
                    }));

                    // Then switch to community view
                    this.switchView('community');
                },

                checkUrlParams() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const view = urlParams.get('view');
                    if (view === 'community') {
                        this.currentView = 'community';
                    }
                },

                switchView(view) {
                    if (view === '<') {
                        this.currentView = this.prevViews.pop() || 'home';
                        return;
                    }
                    this.prevViews.push(this.currentView);
                    this.currentView = view || 'home';
                },

                // Modal functions
                setActiveModal(data, idPrefix = 'modal') {
                    // For top navbar, use the full unique ID from data-id attribute
                    // For others, use just the image id
                    if (idPrefix.startsWith('topnav-')) {
                        this.activeModalId = idPrefix;
                        this.activeModalIdPrefix = 'topnav';
                    } else {
                        this.activeModalId = data.id;
                        this.activeModalIdPrefix = idPrefix;
                    }
                    this.updateModalImage(data);
                    this.modalShow = true;
                },

                resolveModalImageSrc(data) {
                    const imagePath = data?.url || data?.output || '';

                    if (!imagePath) {
                        return '';
                    }

                    return imagePath.startsWith('upload') ? `/${imagePath}` : imagePath;
                },

                updateModalImage(data) {
                    this.activeModal = data;

                    const nextSrc = this.resolveModalImageSrc(data);
                    const requestId = ++this.modalImageRequestId;

                    if (!nextSrc) {
                        this.modalImageSrc = '';
                        this.modalImageLoading = false;
                        return;
                    }

                    this.modalImageSrc = '';
                    this.modalImageLoading = true;

                    const preloader = new Image();
                    preloader.onload = () => {
                        if (requestId !== this.modalImageRequestId) return;
                        this.modalImageSrc = nextSrc;
                        this.modalImageLoading = false;
                    };
                    preloader.onerror = () => {
                        if (requestId !== this.modalImageRequestId) return;
                        // Fallback to direct src even when preload fails.
                        this.modalImageSrc = nextSrc;
                        this.modalImageLoading = false;
                    };
                    preloader.src = nextSrc;
                },

                prevImageModal() {
                    // Get all images with the same prefix
                    const allImages = document.querySelectorAll(`.image-result[data-id-prefix='${this.activeModalIdPrefix}']`);
                    const currentIndex = Array.from(allImages).findIndex(el =>
                        String(el.getAttribute('data-id')) === String(this.activeModalId)
                    );

                    if (currentIndex > 0) {
                        const prevEl = allImages[currentIndex - 1];
                        const data = JSON.parse(prevEl.getAttribute('data-payload') || '{}');
                        const newId = prevEl.getAttribute('data-id');

                        this.updateModalImage(data);
                        this.activeModalId = newId;
                    }
                },

                nextImageModal() {
                    // Get all images with the same prefix
                    const allImages = document.querySelectorAll(`.image-result[data-id-prefix='${this.activeModalIdPrefix}']`);
                    const currentIndex = Array.from(allImages).findIndex(el =>
                        String(el.getAttribute('data-id')) === String(this.activeModalId)
                    );

                    if (currentIndex >= 0 && currentIndex < allImages.length - 1) {
                        const nextEl = allImages[currentIndex + 1];
                        const data = JSON.parse(nextEl.getAttribute('data-payload') || '{}');
                        const newId = nextEl.getAttribute('data-id');

                        this.updateModalImage(data);
                        this.activeModalId = newId;
                    }
                },

                // Favorite/Bookmark functions
                toggleFavorite(imageId) {
                    @if (!auth()->check())
                        this.showNotification("{{ __('Please log in to add bookmarks.') }}", 'info');
                    @else
                        const index = this.favorites.indexOf(imageId);
                        if (index > -1) {
                            this.favorites.splice(index, 1);
                            this.showNotification("{{ __('Removed from bookmarks') }}", 'info');
                        } else {
                            this.favorites.push(imageId);
                            this.showNotification("{{ __('Added to bookmarks') }}", 'success');
                        }
                        this.saveFavorites();
                    @endif
                },

                isFavorite(imageId) {
                    return this.favorites.includes(imageId);
                },

                // Like functions
                toggleLike(imageId) {
                    const index = this.likes.indexOf(imageId);
                    if (index > -1) {
                        this.likes.splice(index, 1);
                    } else {
                        this.likes.push(imageId);
                    }
                    this.saveLikes();
                    this.sendLikeToServer(imageId, index === -1);
                },

                isLiked(imageId) {
                    return this.likes.includes(imageId);
                },

                async sendLikeToServer(imageId, isLike) {
                    try {
                        await fetch(`{{ route('ai-image-pro.community.images.like') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                image_id: imageId,
                                action: isLike ? 'like' : 'unlike'
                            })
                        });
                    } catch (error) {
                        console.error('Failed to sync like:', error);
                    }
                },

                // Save to localStorage
                saveFavorites() {
                    try {
                        localStorage.setItem('galleryFavorites', JSON.stringify(this.favorites));
                    } catch (error) {
                        console.error('Failed to save favorites:', error);
                    }
                },

                saveLikes() {
                    try {
                        localStorage.setItem('galleryLikes', JSON.stringify(this.likes));
                    } catch (error) {
                        console.error('Failed to save likes:', error);
                    }
                },

                // Download image
                async downloadImage(image) {
                    const imageUrl = image?.url || image?.output;

                    if (!imageUrl) {
                        return;
                    }

                    const extension = imageUrl.split('.').pop()?.split('?')[0] || 'png';
                    const filename = `ai-image-${image.id || Date.now()}.${extension}`;

                    try {
                        const response = await fetch(imageUrl);
                        const blob = await response.blob();
                        const blobUrl = URL.createObjectURL(blob);

                        const link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        URL.revokeObjectURL(blobUrl);
                        this.showNotification('{{ __('Download started') }}', 'success');
                    } catch (error) {
                        console.error('Download failed:', error);

                        const link = document.createElement('a');
                        link.href = imageUrl;
                        link.download = filename;
                        link.target = '_blank';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        this.showNotification('{{ __('Download Failed') }}', 'error');
                    }
                },

                // Share image
                async shareImage(imageId, image = null) {
                    let shareUrl;
                    if (image && image.published) {
                        shareUrl = `{{ route('ai-image-pro.index') }}?view=community&image=${imageId}`;
                    } else {
                        try {
                            const formData = new FormData();
                            formData.append('_token', document.querySelector('input[name=_token]')?.value);
                            formData.append('image_id', imageId);

                            const response = await fetch(`{{ route('ai-image-pro.share.generate') }}`, {
                                method: 'POST',
                                body: formData
                            });

                            const data = await response.json();
                            if (data.success) {
                                shareUrl = data.share_url;
                            } else {
                                this.showNotification(data.message || '{{ __('Failed to generate share link') }}', 'error');
                                return;
                            }
                        } catch (error) {
                            console.error('Failed to generate share link:', error);
                            this.showNotification('{{ __('Failed to generate share link') }}', 'error');
                            return;
                        }
                    }

                    if (navigator.share) {
                        navigator.share({
                            title: '{{ __('Check out this AI generated image') }}',
                            url: shareUrl
                        }).catch(err => {
                            if (err.name !== 'AbortError') {
                                this.copyToClipboard(shareUrl);
                            }
                        });
                    } else {
                        this.copyToClipboard(shareUrl);
                    }
                },

                copyToClipboard(text) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(() => {
                            this.showNotification('{{ __('Link copied to clipboard!') }}', 'success');
                        }).catch(err => {
                            console.error('Failed to copy:', err);
                            this.fallbackCopy(text);
                        });
                    } else {
                        this.fallbackCopy(text);
                    }
                },

                fallbackCopy(text) {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        this.showNotification('{{ __('Link copied to clipboard!') }}', 'success');
                    } catch (err) {
                        prompt('{{ __('Copy this link:') }}', text);
                    }
                    document.body.removeChild(textarea);
                },

                // Replicate image
                replicateImage(image) {
                    this.modalShow = false;
                    this.setReplicationData(image);
                    this.switchView('home');
                },

                // Advanced Image Editor availability
                isAdvancedImageInstalled: {{ $isAdvancedImageInstalled ? 'true' : 'false' }},

                // Edit image - redirects to the image editor
                editImage(image) {
                    this.editWithEditor(image);
                },

                // Edit with Editor - redirects to AI Image Pro Editor
                editWithEditor(image) {
                    if (!image || (!image.url && !image.output)) {
                        this.showNotification('{{ __('No image to edit') }}', 'error');
                        return;
                    }

                    const imageUrl = image.url || image.output;
                    const imageData = {
                        url: imageUrl,
                        title: image.title || image.prompt || image.input || 'image',
                    };

                    sessionStorage.setItem('pendingImageForEditor', JSON.stringify(imageData));
                    window.location.href = '{{ route(auth()->check() ? 'dashboard.user.ai-image-pro.edit' : 'ai-image-pro.edit') }}';
                },

                // Creative Suite availability
                isCreativeSuiteInstalled: {{ $isCreativeSuiteInstalled ? 'true' : 'false' }},

                // Open with Creative Suite
                openWithCreativeSuite(image) {
                    if (!this.isCreativeSuiteInstalled) {
                        this.showNotification('{{ __('Creative Suite extension is not installed') }}', 'error');
                        return;
                    }

                    if (!image || (!image.url && !image.output)) {
                        this.showNotification('{{ __('No image to open') }}', 'error');
                        return;
                    }

                    const imageUrl = image.url || image.output;
                    const imageData = {
                        url: imageUrl,
                        prompt: image.title || image.prompt || image.input || '',
                        width: image.width || null,
                        height: image.height || null,
                    };

                    sessionStorage.setItem('pendingImageForCreativeSuite', JSON.stringify(imageData));
                    @if ($isCreativeSuiteInstalled)
                        window.location.href = '{{ route('dashboard.user.creative-suite.index') }}';
                    @endif
                },

                // Edit with Assistant - redirects to AI Chat Image page with image and prompt
                editWithAssistant(image) {
                    if (!image || (!image.url && !image.output)) {
                        this.showNotification('{{ __('No image to edit') }}', 'error');
                        return;
                    }

                    const imageUrl = image.url || image.output;
                    const prompt = image.title || image.prompt || image.input || '';

                    // Store image data in sessionStorage for the chat page to pick up
                    const imageData = {
                        url: imageUrl,
                        prompt: prompt,
                        name: `image-${image.id || Date.now()}.png`,
                        width: image.width || null,
                        height: image.height || null,
                    };

                    sessionStorage.setItem('pendingImageForChatAssistant', JSON.stringify(imageData));

                    // Redirect to AI Chat Image page
                    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-image-chat'))
                        window.location.href = '{{ route('ai-chat-image.index') }}';
                    @endif
                },

                // Publish image
                async publishImage(imageId) {
                    try {
                        const formData = new FormData();
                        formData.append('_token', document.querySelector('input[name=_token]')?.value);
                        formData.append('image_id', imageId);

                        const response = await fetch(`{{ route('ai-image-pro.community.images.publish') }}`, {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.showNotification(data.message || "{{ __('Publish request submitted successfully') }}", 'success');
                            this.modalShow = false;
                        } else {
                            this.showNotification(data.message || '{{ __('Failed to publish image') }}', 'error');
                        }
                    } catch (error) {
                        console.error('Failed to publish:', error);
                        this.showNotification('{{ __('Failed to publish image') }}', 'error');
                    }
                },

                // Open image menu
                openImageMenu(imageId) {
                    this.showNotification('{{ __('More options coming soon') }}', 'info');
                },

                // Show notification
                showNotification(message, type = 'info') {
                    if (window.toastr) {
                        window.toastr[type](message);
                    } else {
                        console.log(`[${type.toUpperCase()}] ${message}`);
                    }
                },

                setReplicationData(imageData) {
                    this.replicationData = imageData;
                },

                applyReplicationData() {
                    if (!this.replicationData) return;
                    const data = this.replicationData;
                    setTimeout(() => {
                        const formElement = document.querySelector('#submitForm');
                        if (!formElement) return;

                        let formComponent = null;
                        if (Alpine && Alpine.$data) {
                            formComponent = Alpine.$data(formElement);
                        }
                        if (!formComponent && formElement.__x) {
                            formComponent = formElement.__x.$data;
                        }
                        if (!formComponent && formElement._x_dataStack) {
                            formComponent = formElement._x_dataStack[0];
                        }
                        if (!formComponent) return;

                        if (data.model && formComponent.models && formComponent.models[data.model]) {
                            formComponent.selectedModel = data.model;
                            formComponent.initializeFormValues();
                        }

                        setTimeout(() => {
                            if (data.title || data.prompt) {
                                const promptInput = formComponent.getPromptInput ? formComponent.getPromptInput() : null;
                                if (promptInput) {
                                    formComponent.formValues[promptInput.name] = data.title || data.prompt;
                                } else {
                                    if (formComponent.formValues && formComponent.formValues.hasOwnProperty('prompt')) {
                                        formComponent.formValues['prompt'] = data.title || data.prompt;
                                    }
                                }
                            }

                            if (formComponent.formValues) {
                                const fieldMappings = {
                                    'style': 'style',
                                    'ratio': 'aspect_ratio',
                                    'aspect_ratio': 'aspect_ratio',
                                    'negative_prompt': 'negative_prompt',
                                };

                                Object.entries(fieldMappings).forEach(([dataKey, formKey]) => {
                                    if (data[dataKey] !== undefined && formComponent.formValues.hasOwnProperty(formKey)) {
                                        formComponent.formValues[formKey] = data[dataKey];
                                    }
                                });

                                Object.keys(data).forEach(key => {
                                    if (formComponent.formValues.hasOwnProperty(key) &&
                                        key !== 'id' &&
                                        key !== 'url' &&
                                        key !== 'user' &&
                                        key !== 'date' &&
                                        key !== 'tags' &&
                                        key !== 'can_publish') {
                                        formComponent.formValues[key] = data[key];
                                    }
                                });
                            }

                            this.replicationData = null;
                            if (window.toastr) {
                                toastr.success('{{ __('Image parameters loaded! You can now generate a similar image.') }}');
                            }
                            formElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }, 100);
                    }, 300);
                }
            }));
        });
    </script>
@endPush
