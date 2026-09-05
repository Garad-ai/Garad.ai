@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('mediaLibrary', () => ({
                // State
                images: [],
                loading: true,
                loadingMore: false,
                hasMore: false,
                page: 1,
                total: 0,

                // Filters & Sort
                filter: 'assets',
                sort: 'date',
                sortDirection: 'desc',
                searchQuery: '',
                searchTimeout: null,

                // Grid
                gridSize: 4,

                // Selection
                selectedItems: [],
                bulkAction: 'delete',
                bulkLoading: false,

                // User preferences
                favorites: [],

                // Extension availability
                isAdvancedImageInstalled: {{ $isAdvancedImageInstalled ? 'true' : 'false' }},
                isCreativeSuiteInstalled: {{ $isCreativeSuiteInstalled ? 'true' : 'false' }},
                isDemo: {{ $app_is_demo ? 'true' : 'false' }},

                // Labels
                sortLabels: {
                    date: '{{ __('Sort by Date') }}',
                    popularity: '{{ __('Sort by Popularity') }}',
                    variations: '{{ __('Sort by Variations') }}',
                    edits: '{{ __('Sort by Edits') }}'
                },

                init() {
                    this.loadPreferences();
                    this.fetchImages();
                },

                loadPreferences() {
                    try {
                        const savedFavorites = localStorage.getItem('galleryFavorites');
                        if (savedFavorites) {
                            this.favorites = JSON.parse(savedFavorites);
                        }

                        const savedGridSize = localStorage.getItem('mediaLibraryGridSize');
                        if (savedGridSize) {
                            this.gridSize = parseInt(savedGridSize, 10);
                        }
                    } catch (e) {
                        console.error('Error loading preferences:', e);
                    }
                },

                saveGridSize() {
                    localStorage.setItem('mediaLibraryGridSize', this.gridSize.toString());
                },

                async fetchImages(append = false) {
                    if (!append) {
                        this.loading = true;
                        this.page = 1;
                    } else {
                        this.loadingMore = true;
                    }

                    try {
                        const params = new URLSearchParams({
                            filter: this.filter,
                            sort: this.sort,
                            direction: this.sortDirection,
                            page: this.page,
                            per_page: 24
                        });

                        if (this.searchQuery) {
                            params.append('search', this.searchQuery);
                        }

                        if (this.filter === 'bookmarks') {
                            this.favorites.forEach(fav => params.append('favorites[]', fav));
                        }

                        const response = await fetch(`{{ route('ai-image-pro.media-library.images') }}?${params}`);
                        const data = await response.json();

                        if (append) {
                            this.images = [...this.images, ...data.images];
                        } else {
                            this.images = data.images;
                        }

                        this.hasMore = data.hasMore;
                        this.total = data.total;
                    } catch (error) {
                        console.error('Error fetching images:', error);
                        toastr.error('{{ __('Failed to load images') }}');
                    } finally {
                        this.loading = false;
                        this.loadingMore = false;
                    }
                },

                get groupedImages() {
                    const groups = {};
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);

                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);

                    const monthAgo = new Date(today);
                    monthAgo.setMonth(monthAgo.getMonth() - 1);

                    this.images.forEach(image => {
                        let label;
                        const imageDate = this.parseImageDate(image);

                        if (imageDate >= today) {
                            label = '{{ __('Today') }}';
                        } else if (imageDate >= yesterday) {
                            label = '{{ __('Yesterday') }}';
                        } else if (imageDate >= weekAgo) {
                            label = '{{ __('This Week') }}';
                        } else if (imageDate >= monthAgo) {
                            label = '{{ __('This Month') }}';
                        } else {
                            label = '{{ __('Older') }}';
                        }

                        if (!groups[label]) {
                            groups[label] = [];
                        }
                        groups[label].push(image);
                    });

                    let order = ['{{ __('Today') }}', '{{ __('Yesterday') }}', '{{ __('This Week') }}', '{{ __('This Month') }}',
                        '{{ __('Older') }}'
                    ];

                    if (this.sortDirection === 'asc') {
                        order = order.slice().reverse();
                    }

                    return order
                        .filter(label => groups[label])
                        .map(label => ({
                            label,
                            images: groups[label]
                        }));
                },

                parseImageDate(image) {
                    if (image?.date_iso) {
                        const parsedDate = new Date(image.date_iso);
                        if (!Number.isNaN(parsedDate.getTime())) {
                            return parsedDate;
                        }
                    }

                    return this.parseRelativeDate(image?.date || '');
                },

                parseRelativeDate(dateStr) {
                    const now = new Date();
                    if (typeof dateStr !== 'string' || !dateStr.length) {
                        return now;
                    }

                    const normalizedDate = dateStr.toLowerCase();

                    if (normalizedDate === 'today' || normalizedDate === 'just now') {
                        return now;
                    }

                    if (normalizedDate === 'yesterday') {
                        const date = new Date(now);
                        date.setDate(date.getDate() - 1);

                        return date;
                    }

                    if (normalizedDate.includes('second') || normalizedDate.includes('minute') || normalizedDate.includes('hour')) {
                        return now;
                    }

                    const match = normalizedDate.match(/(\d+)\s+(day|week|month|year)/);
                    if (match) {
                        const num = parseInt(match[1], 10);
                        const unit = match[2].toLowerCase();
                        const date = new Date(now);

                        switch (unit) {
                            case 'day':
                                date.setDate(date.getDate() - num);
                                break;
                            case 'week':
                                date.setDate(date.getDate() - (num * 7));
                                break;
                            case 'month':
                                date.setMonth(date.getMonth() - num);
                                break;
                            case 'year':
                                date.setFullYear(date.getFullYear() - num);
                                break;
                        }
                        return date;
                    }

                    return now;
                },

                setFilter(newFilter) {
                    if (this.filter !== newFilter) {
                        this.filter = newFilter;
                        this.clearSelection();
                        this.fetchImages();
                    }
                },

                setSort(newSort) {
                    if (this.sort === newSort) {
                        // Toggle direction when clicking the same sort
                        this.sortDirection = this.sortDirection === 'desc' ? 'asc' : 'desc';
                    } else {
                        // Reset to desc when clicking a new sort
                        this.sort = newSort;
                        this.sortDirection = 'desc';
                    }
                    this.fetchImages();
                },

                doSearch() {
                    this.fetchImages();
                },

                loadMore() {
                    if (!this.loading && !this.loadingMore && this.hasMore) {
                        this.page++;
                        this.fetchImages(true);
                    }
                },

                // Selection
                isSelected(id) {
                    return this.selectedItems.includes(id);
                },

                toggleSelect(id) {
                    const index = this.selectedItems.indexOf(id);
                    if (index === -1) {
                        this.selectedItems.push(id);
                    } else {
                        this.selectedItems.splice(index, 1);
                    }
                },

                toggleSelectAll() {
                    if (this.selectedItems.length === this.images.length) {
                        this.selectedItems = [];
                    } else {
                        this.selectedItems = this.images.map(img => img.id);
                    }
                },

                clearSelection() {
                    this.selectedItems = [];
                },

                handleImageClick(event, image) {
                    // If clicking on interactive elements, don't select
                    if (event.target.closest('button, a, input, .dropdown')) {
                        return;
                    }

                    // Toggle selection on click
                    this.toggleSelect(image.id);
                },

                // Favorites
                isFavorite(id) {
                    return this.favorites.includes(id);
                },

                toggleFavorite(id) {
                    const index = this.favorites.indexOf(id);
                    if (index === -1) {
                        this.favorites.push(id);
                    } else {
                        this.favorites.splice(index, 1);
                    }
                    localStorage.setItem('galleryFavorites', JSON.stringify(this.favorites));

                    // If in bookmarks view, refresh to remove unbookmarked items
                    if (this.filter === 'bookmarks') {
                        this.fetchImages();
                    }
                },

                // Bulk Actions
                async applyBulkAction() {
                    if (this.selectedItems.length === 0) return;
                    if (this.isDemo) {
                        toastr.info('{{ __('This feature is disabled in demo mode.') }}');
                        return;
                    }

                    if (this.bulkAction === 'delete') {
                        if (!confirm('{{ __('Are you sure you want to delete the selected images?') }}')) {
                            return;
                        }

                        this.bulkLoading = true;
                        try {
                            const response = await fetch('{{ route('ai-image-pro.media-library.delete') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    image_ids: this.selectedItems
                                })
                            });

                            const data = await response.json();

                            if (data.success) {
                                toastr.success(data.message);
                                this.clearSelection();
                                this.fetchImages();
                            } else {
                                toastr.error(data.message || '{{ __('Failed to delete images') }}');
                            }
                        } catch (error) {
                            console.error('Error deleting images:', error);
                            toastr.error('{{ __('Failed to delete images') }}');
                        } finally {
                            this.bulkLoading = false;
                        }
                    }
                },

                // Single delete
                async deleteImage(id) {
                    if (this.isDemo) {
                        toastr.info('{{ __('This feature is disabled in demo mode.') }}');
                        return;
                    }

                    if (!confirm('{{ __('Are you sure you want to delete this image?') }}')) {
                        return;
                    }

                    try {
                        const response = await fetch('{{ route('ai-image-pro.media-library.delete') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                image_ids: [id]
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            toastr.success('{{ __('Image deleted successfully') }}');
                            // Remove from local state
                            this.images = this.images.filter(img => img.id !== id);
                            this.selectedItems = this.selectedItems.filter(item => item !== id);
                        } else {
                            toastr.error(data.message || '{{ __('Failed to delete image') }}');
                        }
                    } catch (error) {
                        console.error('Error deleting image:', error);
                        toastr.error('{{ __('Failed to delete image') }}');
                    }
                },

                // Edit actions
                editWithAssistant(image) {
                    if (!image || !image.url) {
                        this.showNotification('{{ __('No image to edit') }}', 'error');
                        return;
                    }

                    const imageData = {
                        url: image.url,
                        prompt: image.prompt || '',
                        name: `image-${image.id || Date.now()}.png`,
                        width: image.width || null,
                        height: image.height || null,
                    };

                    sessionStorage.setItem('pendingImageForChatAssistant', JSON.stringify(imageData));
					@if(\App\Helpers\Classes\MarketplaceHelper::isRegistered('ai-chat-pro-image-chat'))
                    	window.location.href = '{{ route('ai-chat-image.index') }}';
					@endif
                },

                editWithEditor(image) {
                    if (!image || !image.url) {
                        this.showNotification('{{ __('No image to edit') }}', 'error');
                        return;
                    }

                    const imageData = {
                        url: image.url,
                        title: image.prompt || 'image',
                    };

                    sessionStorage.setItem('pendingImageForEditor', JSON.stringify(imageData));
                    window.location.href = '{{ route(auth()->check() ? 'dashboard.user.ai-image-pro.edit' : 'ai-image-pro.edit') }}';
                },

                openWithCreativeSuite(image) {
                    if (!this.isCreativeSuiteInstalled) {
                        this.showNotification('{{ __('Creative Suite extension is not installed') }}', 'error');
                        return;
                    }

                    if (!image || !image.url) {
                        this.showNotification('{{ __('No image to open') }}', 'error');
                        return;
                    }

                    const imageData = {
                        url: image.url,
                        prompt: image.prompt || '',
                        width: image.width || null,
                        height: image.height || null,
                    };

                    sessionStorage.setItem('pendingImageForCreativeSuite', JSON.stringify(imageData));
                    @if ($isCreativeSuiteInstalled)
                        window.location.href = '{{ route('dashboard.user.creative-suite.index') }}';
                    @endif
                },

                // Show notification helper
                showNotification(message, type = 'info') {
                    if (window.toastr) {
                        window.toastr[type](message);
                    } else {
                        console.log(`[${type.toUpperCase()}] ${message}`);
                    }
                }
            }));
        });
    </script>
@endpush
