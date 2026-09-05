<script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
<script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>

<script>
    (() => {
        document.addEventListener('alpine:init', () => {
            Alpine.data('socialMediaAgentPosts', () => ({
                platforms: @json($platforms_with_image),
                totalPostsCount: {{ $total_posts_count ?? 0 }},
                scheduledPostsCount: {{ $scheduled_posts_count ?? 0 }},
                pendingPostsCount: {{ $pending_posts_count ?? 0 }},
                defaultAgentId: {{ $default_agent_id ?? 'null' }},
                generationStatus: @json($generation_status ?? ['status' => 'idle']),
                generatedPostsCount: {{ $generation_status['generated_posts_count'] ?? $generation_status['generated_count'] ?? 0 }},
                generationStatusPollId: null,
                generationStatusEndpoint: '{{ route('dashboard.user.social-media.agent.api.generation-status') }}',
                allPostsLoaded: false,
                loadingMore: false,
                editingPost: null,
                editingPostPagination: {
                    currentPage: null,
                    prevPageUrl: null,
                    nextPageUrl: null,
                },
                filters: {
                    platform: '',
                    platform_id: '',
                    agent_id: [],
                    account: '',
                },
                sort: {
                    'sortBy': 'created_at',
                    'sortDirection': 'desc'
                },
                editingPostInitialStatus: null,
                currentTasks: new Set(),
                editingPostVideoPollingTimer: null,
                editingPostVideoPollingDelay: 6000,
                editingPostVideoPollingAttempts: 0,
                editingPostVideoPollingMaxAttempts: 60,
                editingPostImagePollingTimer: null,
                editingPostImagePollingDelay: 6000,
                editingPostImagePollingAttempts: 0,
                editingPostImagePollingMaxAttempts: 60,
                pendingCounterEl: document.querySelector('#social-media-agent-pending-posts-counter .lqd-number-counter-value'),
                scheduledCounterEl: document.querySelector('#social-media-agent-scheduled-posts-counter .lqd-number-counter-value'),
                flickityData: null,
                cols: {
                    '(max-width: 767px)': 1,
                    '(min-width: 768px) and (max-width: 991px)': 2,
                    '(min-width: 992px)': 4,
                },
                autoNavigateOnPostUpdates: true,
                readyTextTemplate: "{{ ':generated of :total posts are ready for review.' }}",
                videoStatusEndpoint: "{{ route('dashboard.user.social-media.agent.video.status') }}",
                imageStatusEndpoint: "{{ route('dashboard.user.social-media.agent.image.status') }}",
                videoExtensions: ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'],

                get isGenerationBusy() {
                    return ['queued', 'generating'].includes(this.generationStatus?.status);
                },

                get generationStatusLabel() {
                    if (this.isGenerationBusy) {
                        return '{{ __('We are generating fresh posts for you...') }}';
                    }

                    return '{{ __('Your new post are ready for review.') }}';
                },

                get showReadyProgressText() {
                    return this.isGenerationBusy;
                },

                get readyProgressText() {
                    const generatedFromStatus = Number(this.generationStatus?.generated_posts_count ?? this.generatedPostsCount ?? 0);
                    const plannedFromStatus = Number(this.generationStatus?.planned_posts_count ?? this.generationStatus?.total_requested ?? 0);
                    const pendingCount = Math.max(this.pendingPostsCount ?? 0, 0);

                    const readyCount = Math.max(0, pendingCount + generatedFromStatus);
                    const totalCount = Math.max(0, pendingCount + plannedFromStatus);

                    return this.readyTextTemplate
                        .replace(':generated', Math.max(0, readyCount))
                        .replace(':total', Math.max(0, totalCount));
                },

                get sortLabel() {
                    const {
                        sortBy
                    } = this.sort;
                    let label = sortBy;

                    switch (sortBy) {
                        case 'created_at':
                            label = '{{ __('Date') }}';
                            break;
                        case 'platform_id':
                            label = '{{ __('Platform') }}';
                            break;
                    }

                    return label;
                },

                init() {
                    if ('Flickity' in window && this.$refs.postsCarousel) {
                        Flickity.prototype._createResizeClass = function() {
                            this.element.classList.add('flickity-resize');
                        };

                        Flickity.createMethods.push('_createResizeClass');

                        var resize = Flickity.prototype.resize;
                        Flickity.prototype.resize = function() {
                            this.element.classList.remove('flickity-resize');
                            resize.call(this);
                            this.element.classList.add('flickity-resize');
                        };

                        this.flickityData = new Flickity(this.$refs.postsCarousel, {
                            cellSelector: '.social-media-agent-post-item',
                            prevNextButtons: false,
                            pageDots: false,
                            cellAlign: 'left',
                            contain: true
                        });

                        this.flickityData.on('dragStart', () => {
                            this.flickityData.slider.style.willChange = 'transform';
                        });
                        this.flickityData.on('settle', () => {
                            this.flickityData.slider.style.willChange = 'auto';
                        });
                    }

                    this.externalRefreshHandler = () => {
                        this.filterPosts();
                    };

                    window.addEventListener('social-media-agent:post-created', this.externalRefreshHandler);
                    window.addEventListener('social-media-agent:duplicate-post', event => {
                        if (!event.detail?.id) {
                            return;
                        }

                        this.duplicatePost(event.detail.id);
                    });

                    this.startGenerationStatusPolling();

                    this.$watch('editingPost', post => {
                        console.log('[Modal Image Polling] editingPost changed:', post ? {
                            id: post.id,
                            image_request_id: post.image_request_id,
                            image_status: post.image_status,
                            media_urls: post.media_urls,
                        } : 'null');

                        if (!post) {
                            this.stopEditingPostVideoPolling();
                            this.stopEditingPostImagePolling();

                            return;
                        }

                        const shouldPollVid = this.shouldPollVideo(post);
                        const shouldPollImg = this.shouldPollImage(post);

                        console.log('[Modal Image Polling] Should poll?', {
                            video: shouldPollVid,
                            image: shouldPollImg
                        });

                        if (shouldPollVid) {
                            this.startEditingPostVideoPolling();
                        } else {
                            this.stopEditingPostVideoPolling();
                        }

                        if (shouldPollImg) {
                            console.log('[Modal Image Polling] ✅ Starting image polling for modal');
                            this.startEditingPostImagePolling();
                        } else {
                            this.stopEditingPostImagePolling();
                        }
                    });
                },

                isVideoUrl(url) {
                    if (!url || typeof url !== 'string') {
                        return false;
                    }

                    const cleanUrl = url.split('?')[0].split('#')[0].toLowerCase();

                    return this.videoExtensions.some(ext => cleanUrl.endsWith('.' + ext));
                },

                getVideoPreviews(post) {
                    if (!post) {
                        return [];
                    }

                    const directVideos = Array.isArray(post.video_urls) ? post.video_urls.filter(Boolean) : [];
                    if (directVideos.length) {
                        return directVideos;
                    }

                    const mediaVideos = Array.isArray(post.media_urls)
                        ? post.media_urls.filter(url => this.isVideoUrl(url))
                        : [];

                    return mediaVideos;
                },

                getImagePreviews(post) {
                    if (!post) {
                        return [];
                    }

                    const media = Array.isArray(post.media_urls) ? post.media_urls.filter(Boolean) : [];
                    const hasVideos = this.getVideoPreviews(post).length > 0;

                    if (!hasVideos) {
                        return media;
                    }

                    return media.filter(url => !this.isVideoUrl(url));
                },

                getVideoStatus(post) {
                    return post?.video_status ?? 'none';
                },

                isVideoPost(post) {
                    if (!post) {
                        return false;
                    }

                    return this.getVideoPreviews(post).length > 0
                        || ['pending', 'generating'].includes(this.getVideoStatus(post))
                        || post.post_type === 'video';
                },

                getVideoPlatformSlug(post) {
                    if (!post) {
                        return null;
                    }

                    return post.platform?.platform ?? this.getPlatformById(post.platform_id)?.platform ?? null;
                },

                canUsePlatform(post, slug) {
                    if (!post || !slug) {
                        return false;
                    }

                    if (!this.isVideoPost(post)) {
                        return true;
                    }

                    const lockedSlug = this.getVideoPlatformSlug(post);
                    if (lockedSlug) {
                        return slug === lockedSlug;
                    }

                    return ['youtube', 'youtube-shorts'].includes(slug);
                },

                shouldPollVideo(post) {
                    if (!post) {
                        return false;
                    }

                    const status = this.getVideoStatus(post);
                    const hasVideo = this.getVideoPreviews(post).length > 0;
                    const requestId = post.video_request_id ?? null;

                    return ['pending', 'generating', 'in_queue'].includes(status ?? 'none')
                        && !hasVideo
                        && !!requestId
                        && !!this.videoStatusEndpoint;
                },

                startEditingPostVideoPolling() {
                    this.stopEditingPostVideoPolling();

                    if (!this.shouldPollVideo(this.editingPost)) {
                        return;
                    }

                    this.editingPostVideoPollingAttempts = 0;
                    this.scheduleEditingPostVideoPolling();
                },

                scheduleEditingPostVideoPolling() {
                    this.stopEditingPostVideoPolling();

                    this.editingPostVideoPollingTimer = setTimeout(() => {
                        this.pollEditingPostVideoStatus();
                    }, this.editingPostVideoPollingDelay);
                },

                stopEditingPostVideoPolling() {
                    if (!this.editingPostVideoPollingTimer) {
                        return;
                    }

                    clearTimeout(this.editingPostVideoPollingTimer);
                    this.editingPostVideoPollingTimer = null;
                },

                // Image polling methods
                shouldPollImage(post) {
                    if (!post) {
                        return false;
                    }

                    const status = post.image_status ?? 'none';
                    const hasNoImages = !Array.isArray(post.media_urls) || post.media_urls.length === 0;
                    const requestId = post.image_request_id ?? null;

                    return ['pending', 'generating', 'in_queue'].includes(status)
                        && hasNoImages
                        && !!requestId
                        && !!this.imageStatusEndpoint;
                },

                startEditingPostImagePolling() {
                    this.stopEditingPostImagePolling();

                    if (!this.shouldPollImage(this.editingPost)) {
                        console.log('[Modal Image Polling] ❌ Cannot start - shouldPollImage returned false');
                        return;
                    }

                    console.log('[Modal Image Polling] ✅ Starting polling for editingPost ID:', this.editingPost?.id);
                    this.editingPostImagePollingAttempts = 0;
                    this.scheduleEditingPostImagePolling();
                },

                scheduleEditingPostImagePolling() {
                    this.stopEditingPostImagePolling();

                    console.log('[Modal Image Polling] ⏰ Scheduled next poll in', this.editingPostImagePollingDelay, 'ms');

                    this.editingPostImagePollingTimer = setTimeout(() => {
                        this.pollEditingPostImageStatus();
                    }, this.editingPostImagePollingDelay);
                },

                stopEditingPostImagePolling() {
                    if (!this.editingPostImagePollingTimer) {
                        return;
                    }

                    clearTimeout(this.editingPostImagePollingTimer);
                    this.editingPostImagePollingTimer = null;
                },

                async pollEditingPostImageStatus() {
                    if (!this.editingPost?.id) {
                        this.stopEditingPostImagePolling();

                        return;
                    }

                    this.editingPostImagePollingAttempts += 1;

                    console.log('[Modal Image Polling] 🔄 Polling attempt', this.editingPostImagePollingAttempts, 'for editingPost ID:', this.editingPost.id);

                    const requestId = this.editingPost?.image_request_id;
                    let post = null;

                    if (requestId && this.imageStatusEndpoint) {
                        console.log('[Modal Image Polling] 🌐 Fetching status for request:', requestId);
                        const statusResponse = await this.fetchImageStatus(requestId);

                        if (statusResponse?.success) {
                            post = statusResponse.post ?? null;

                            if (!post && statusResponse.status) {
                                this.editingPost.image_status = statusResponse.status;
                            }
                        } else if (statusResponse?.status === 'failed') {
                            this.editingPost.image_status = 'failed';
                        }
                    }

                    if (!post) {
                        const data = await this.fetchPost({
                            query: `id=${this.editingPost.id}`,
                            suppressErrors: true,
                            trackTasks: false,
                        });

                        post = data?.posts?.data?.[0] ?? null;
                    }

                    if (post) {
                        this.editingPost = post;
                        this.$dispatch('social-media-agent-post-updated', {
                            post
                        });
                    }

                    if (this.shouldPollImage(this.editingPost) && this.editingPostImagePollingAttempts < this.editingPostImagePollingMaxAttempts) {
                        this.scheduleEditingPostImagePolling();
                    } else {
                        this.stopEditingPostImagePolling();
                    }
                },

                async pollEditingPostVideoStatus() {
                    if (!this.editingPost?.id) {
                        this.stopEditingPostVideoPolling();

                        return;
                    }

                    this.editingPostVideoPollingAttempts += 1;

                    const requestId = this.editingPost?.video_request_id;
                    let post = null;

                    if (requestId && this.videoStatusEndpoint) {
                        const statusResponse = await this.fetchVideoStatus(requestId);

                        if (statusResponse?.success) {
                            post = statusResponse.post ?? null;

                            if (!post && statusResponse.status) {
                                this.editingPost.video_status = statusResponse.status;
                            }
                        } else if (statusResponse?.status === 'failed') {
                            this.editingPost.video_status = 'failed';
                        }
                    }

                    if (!post) {
                        const data = await this.fetchPost({
                            query: `id=${this.editingPost.id}`,
                            suppressErrors: true,
                            trackTasks: false,
                        });

                        post = data?.posts?.data?.[0] ?? null;
                    }

                    if (post) {
                        this.editingPost = post;
                        this.$dispatch('social-media-agent-post-updated', {
                            post
                        });
                    }

                    if (this.shouldPollVideo(this.editingPost) && this.editingPostVideoPollingAttempts < this.editingPostVideoPollingMaxAttempts) {
                        this.scheduleEditingPostVideoPolling();
                    } else {
                        this.stopEditingPostVideoPolling();
                    }
                },

                getPlatformById(id) {
                    const platform = this.platforms.find(p => p.id === id);

                    return platform;
                },

                getSidedrawer() {
                    const editSidedrawerEl = document.querySelector('#social-media-agent-sidedrawer');
                    const editSidedrawerData = Alpine.$data(editSidedrawerEl);

                    return editSidedrawerData;
                },

                async fetchPost({
                    query,
                    url,
                    taskKey = null,
                    suppressErrors = false,
                    trackTasks = true,
                }) {
                    if (!url && !query) {
                        if (!suppressErrors) {
                            toastr.error('@lang('Please provide a valid url or query.')');
                        }
                        return null;
                    }

                    const shouldTrackTasks = trackTasks;
                    const taskKeys = shouldTrackTasks ? ['fetchingPost'] : [];

                    if (shouldTrackTasks && taskKey) {
                        taskKeys.push(taskKey);
                    }

                    taskKeys.forEach(key => this.currentTasks.add(key));

                    url = url ?? `/dashboard/user/social-media/agent/api/posts?per_page=1&${query}`;

                    try {
                        const res = await fetch(url, {
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();

                        if (!data.success) {
                            const message = data.message || '@lang('Failed fetching post')'
                            if (!suppressErrors) {
                                toastr.error(message);
                            }

                            return null;
                        }

                        const post = data.posts.data[0];

                        if (!post) {
                            if (!suppressErrors) {
                                toastr.warning('@lang('No Posts Found.')')
                            }

                            return null;
                        }

                        return data;
                    } catch (err) {
                        const message = err.message || '@lang('Failed fetching post')'
                        if (!suppressErrors) {
                            toastr.error(message);
                        }

                        return null;
                    } finally {
                        if (shouldTrackTasks) {
                            taskKeys.forEach(key => this.currentTasks.delete(key));
                        }
                    }
                },

                async fetchVideoStatus(requestId, suppressErrors = true) {
                    if (!requestId || !this.videoStatusEndpoint) {
                        return null;
                    }

                    try {
                        const url = `${this.videoStatusEndpoint}?request_id=${encodeURIComponent(requestId)}`;
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (!data.success && !suppressErrors) {
                            toastr.error(data.message ?? '{{ __('Failed fetching video status') }}');
                        }

                        return data;
                    } catch (error) {
                        if (!suppressErrors) {
                            toastr.error(error.message ?? '{{ __('Failed fetching video status') }}');
                        }

                        return null;
                    }
                },

                async fetchImageStatus(requestId, suppressErrors = true) {
                    if (!requestId || !this.imageStatusEndpoint) {
                        return null;
                    }

                    try {
                        const url = `${this.imageStatusEndpoint}?request_id=${encodeURIComponent(requestId)}`;
                        console.log('[Modal Image Polling] 🌐 Fetching:', url);

                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();
                        console.log('[Modal Image Polling] 📦 Response:', data);

                        if (!data.success && !suppressErrors) {
                            toastr.error(data.message ?? '{{ __('Failed fetching image status') }}');
                        }

                        return data;
                    } catch (error) {
                        if (!suppressErrors) {
                            toastr.error(error.message ?? '{{ __('Failed fetching image status') }}');
                        }

                        return null;
                    }
                },

                async fetchGenerationStatus() {
                    if (!this.generationStatusEndpoint) {
                        return;
                    }

                    try {
                        const response = await fetch(this.generationStatusEndpoint, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();

                        if (data?.success) {
                            this.generationStatus = data.status ?? { status: 'idle' };
                            if (typeof data.ready_text_template === 'string' && data.ready_text_template.length) {
                                this.readyTextTemplate = data.ready_text_template;
                            }

                            if (typeof data.pending_posts_count === 'number' || typeof data.scheduled_posts_count === 'number') {
                                const pending = typeof data.pending_posts_count === 'number'
                                    ? data.pending_posts_count
                                    : this.pendingPostsCount;

                                const scheduled = typeof data.scheduled_posts_count === 'number'
                                    ? data.scheduled_posts_count
                                    : this.scheduledPostsCount;

                                this.updateCounters(pending, scheduled, data.total_posts_count);
                            }
                            if (typeof data.generated_posts_count === 'number') {
                                this.generatedPostsCount = data.generated_posts_count;
                            }
                        }
                    } catch (error) {
                        console.error('Failed to fetch generation status', error);
                    }
                },

                startGenerationStatusPolling() {
                    if (!this.defaultAgentId && (!this.generationStatus || this.generationStatus.status === 'idle')) {
                        return;
                    }

                    this.fetchGenerationStatus();
                    this.stopGenerationStatusPolling();

                    this.generationStatusPollId = setInterval(() => {
                        this.fetchGenerationStatus();
                    }, 10000);
                },

                stopGenerationStatusPolling() {
                    if (this.generationStatusPollId) {
                        clearInterval(this.generationStatusPollId);
                        this.generationStatusPollId = null;
                    }
                },

                async openEditSidedrawer({
                    query,
                    url,
                    taskKey = null,
                    autoNavigateOnPostUpdates = true
                }) {
                    const sidedrawer = this.getSidedrawer();
                    console.log('[Modal Image Polling] 🔄 Opening sidedrawer, stopping old polling...');
                    this.stopEditingPostVideoPolling();
                    this.stopEditingPostImagePolling();
                    const data = await this.fetchPost({
                        query,
                        url,
                        taskKey
                    });
                    const post = data.posts.data[0];

                    if (!post) {
                        sidedrawer.sidedrawerOpen = false;
                        return;
                    }

                    sidedrawer.sidedrawerOpen = true;

                    this.editingPost = post;
                    this.editingPostPagination.currentPage = data.posts.current_page;
                    this.editingPostPagination.prevPageUrl = data.posts.prev_page_url;
                    this.editingPostPagination.nextPageUrl = data.posts.next_page_url;
                    this.editingPostInitialStatus = post.status;

                    this.autoNavigateOnPostUpdates = autoNavigateOnPostUpdates;
                    this.startEditingPostVideoPolling();
                    this.startEditingPostImagePolling();
                },

                async updatePost(postId) {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    this.currentTasks.add('updatePost');

                    const {
                        media_urls,
                        platform_id,
                        platform,
                        post_type,
                        scheduled_at,
                        content
                    } = this.editingPost;

                    const res = await fetch(`/dashboard/user/social-media/agent/api/posts/${postId}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            media_urls,
                            platform_id,
                            platform,
                            post_type,
                            scheduled_at: new Date(scheduled_at).toISOString(),
                            content,
                        })
                    });
                    const data = await res.json();

                    this.currentTasks.delete('updatePost');

                    if (!data.success) {
                        const message = data.message ?? '@lang('An error occurred')';
                        return toastr.error(message);
                    }

                    toastr.success(data.message ?? '@lang('Post updated successfully.')');

                    this.editingPost = data.post;
                    this.startEditingPostVideoPolling();
                    this.startEditingPostImagePolling();

                    const postEl = document.querySelector(`.social-media-agent-post-item[data-post-id="${postId}"]`);
                    const postElAlpineData = postEl && Alpine.$data(postEl);
                    const dashboardCalendarEl = document.querySelector('#social-media-agent-calendar');

                    if (postElAlpineData) {
                        ['media_urls', 'video_urls', 'video_status', 'video_request_id', 'image_status', 'image_request_id', 'platform_id', 'platform', 'post_type', 'scheduled_at', 'published_at', 'content', 'status'].forEach(prop => {
                            postElAlpineData[prop] = data.post[prop];
                        })
                    }

                    if (dashboardCalendarEl) {
                        const calendarData = Alpine.$data(dashboardCalendarEl);

                        if (calendarData.calendar) {
                            calendarData.calendar.refetchEvents();
                        }
                    }

                    if (this.editingPostInitialStatus === 'draft' && data.post.status === 'scheduled') {
                        this.updateCounters(
                            Math.max(0, this.pendingPostsCount - 1),
                            Math.min(this.totalPostsCount, this.scheduledPostsCount + 1)
                        );
                    }

                    this.editingPostInitialStatus = data.post.status;

                    this.$dispatch('social-media-agent-post-updated', {
                        post: data.post
                    });
                },

                async approvePost(postId) {

					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    this.currentTasks.add('approvePost')

                    const res = await fetch(`/dashboard/user/social-media/agent/posts/${postId}/approve`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    this.currentTasks.delete('approvePost');

                    if (!data.success) {
                        const message = data.message ?? '@lang('An error occurred')';
                        return toastr.error(message)
                    }

                    const message = data.message ?? '@lang('Post approved and scheduled!')';

                    toastr.success(message);

                    const postEl = document.querySelector(`.social-media-agent-post-item[data-post-id="${postId}"]`);
                    const dashboardCalendarEl = document.querySelector('#social-media-agent-calendar');

                    if (postEl && Alpine.$data(postEl).status) {
                        Alpine.$data(postEl).status = 'scheduled';
                    }

                    if (this.editingPostInitialStatus === 'draft') {
                        this.updateCounters(
                            Math.max(0, this.pendingPostsCount - 1),
                            Math.min(this.totalPostsCount, this.scheduledPostsCount + 1)
                        );
                    }

                    if (dashboardCalendarEl) {
                        const calendarData = Alpine.$data(dashboardCalendarEl);

                        if (calendarData.calendar) {
                            calendarData.calendar.refetchEvents();
                        }
                    }

                    this.editingPostInitialStatus = 'scheduled';

                    this.$dispatch('social-media-agent-post-approved', {
                        postId: postId
                    });
                },

                async rejectPost(postId) {

					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    if (!confirm("{{ __('Are you sure you want to reject and delete the post?') }}")) {
                        return
                    }

                    this.currentTasks.add('rejectPost')

                    const res = await fetch(`/dashboard/user/social-media/agent/posts/${postId}/reject`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await res.json();

                    this.currentTasks.delete('rejectPost')

                    if (!data.success) {
                        const message = data.message ?? '@lang('An error occurred')';
                        return toastr.error(message)
                    }

                    const message = data.message ?? '@lang('Post rejected and deleted.')';

                    toastr.success(message);

                    const postEl = document.querySelector(`.social-media-agent-post-item[data-post-id="${postId}"]`);
                    const dashboardCalendarEl = document.querySelector('#social-media-agent-calendar');

                    if (postEl) {
                        const closestCarousel = postEl.closest('.flickity-enabled');

                        postEl.remove();

                        if (closestCarousel && 'Flickity' in window) {
                            Flickity.data(closestCarousel)?.reloadCells();
                            Flickity.data(closestCarousel)?.reposition();
                        }
                    }

                    if (dashboardCalendarEl) {
                        const calendarData = Alpine.$data(dashboardCalendarEl);

                        if (calendarData.calendar) {
                            calendarData.calendar.refetchEvents();
                        }
                    }

                    const sidedrawer = this.getSidedrawer();
                    sidedrawer.sidedrawerOpen = false;

                    this.updateCounters(
                        Math.max(0, this.pendingPostsCount - 1),
                        Math.max(0, this.scheduledPostsCount)
                    );

                    this.editingPostInitialStatus = null;

                    this.$dispatch('social-media-agent-post-rejected', {
                        postId: postId
                    });
                },

                async duplicatePost(postId) {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif
                    if (this.currentTasks.has('duplicatePost')) {
                        return;
                    }

                    this.currentTasks.add('duplicatePost');

                    try {
                        const res = await fetch(`/dashboard/user/social-media/agent/posts/${postId}/duplicate`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            throw new Error(data.message || '@lang('Failed to duplicate post')');
                        }

                        toastr.success(data.message || '@lang('Post duplicated successfully.')');
                        this.filterPosts();
                    } catch (error) {
                        toastr.error(error.message || '@lang('Failed to duplicate post')');
                    } finally {
                        this.currentTasks.delete('duplicatePost');
                    }
                },

                async regeneratePostContent(postId) {
					@if(\App\Helpers\Classes\Helper::appIsDemo())
						toastr.error('{{ __('This action is disabled in the demo.') }}');
						return;
					@endif

                    const taskKey = `regeneratePost-${postId}`;

                    if (this.currentTasks.has(taskKey)) {
                        return;
                    }

                    this.currentTasks.add(taskKey);

                    try {
                        const res = await fetch(`/dashboard/user/social-media/agent/api/posts/${postId}/regenerate`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (!res.ok || !data.success) {
                            throw new Error(data.message || '@lang('Failed to regenerate post content.')');
                        }

                        toastr.success(data.message || '@lang('Post content regenerated successfully.')');

                        const postEl = document.querySelector(`.social-media-agent-post-item[data-post-id="${postId}"]`);

                        if (postEl) {
                            const postElData = Alpine.$data(postEl);

                            if (postElData) {
                                postElData.content = data.post.content;
                                postElData.post_type = data.post.post_type;
                            }
                        }

                        if (this.editingPost?.id === postId) {
                            this.editingPost = {
                                ...this.editingPost,
                                content: data.post.content,
                                post_type: data.post.post_type,
                                hashtags: data.post.hashtags,
                            };
                        }
                    } catch (error) {
                        toastr.error(error.message || '@lang('Failed to regenerate post content.')');
                    } finally {
                        this.currentTasks.delete(taskKey);
                    }
                },

                onAjaxSend() {
                    this.loadingMore = true;
                },

                onAjaxSuccess() {
                    const {
                        html
                    } = this.$event.detail;
                    const nextPageUrl = html.querySelector('[data-next-page-url]')?.getAttribute('data-next-page-url');
                    const newPosts = [...html.children || []].filter(el => el.classList.contains('social-media-agent-post-item'));

                    if (newPosts.length) {
                        if (this.$refs.postsCarousel && this.flickityData) {
                            this.appendNewCarouselPosts(newPosts);
                        }
                        if (this.$refs.postsList) {
                            this.appendNewListPosts(newPosts);
                        }
                    }

                    if (nextPageUrl) {
                        this.$refs.loadMoreTrigger?.setAttribute('href', nextPageUrl);
                    } else {
                        this.allPostsLoaded = true;
                    }
                },

                appendNewCarouselPosts(newPosts) {
                    const {
                        cells
                    } = this.flickityData;
                    const lastPostItem = cells.at(-2);
                    const lastPostItemIndex = cells.indexOf(lastPostItem);
                    let cols = 1;

                    Object.keys(this.cols).forEach(mq => {
                        if (window.matchMedia(mq).matches) {
                            cols = this.cols[mq];
                        }
                    });

                    const updateDraggable = enabled => {
                        this.flickityData.options.draggable = enabled;
                        this.flickityData.slider.classList.toggle('select-none', !enabled);
                        this.flickityData.updateDraggable();
                    }

                    const onSettle = () => {
                        updateDraggable(true);

                        this.flickityData.insert(newPosts, cells.length - 1);
                        this.flickityData.selectCell(lastPostItemIndex - Math.max(0, cols - 2), false, true);

                        this.loadingMore = false;

                        this.flickityData.off('settle', onSettle);
                    }


                    if (this.flickityData.isAnimating) {
                        updateDraggable(false);
                        this.flickityData.on('settle', onSettle);
                    } else {
                        onSettle();
                    }
                },

                appendNewListPosts(newPosts) {
                    const tableBodyEl = this.$refs.postsList.querySelector('tbody');
                    const appendNewPostsTo = tableBodyEl ? tableBodyEl : this.$refs.postsList;

                    appendNewPostsTo.append(...newPosts);

                    this.loadingMore = false;
                },

                onAjaxError() {
                    this.loadingMore = false;
                },

                async filterPosts({
                    platform = null,
                    platform_id = null,
                    agent_id = null,
                    query = ''
                } = {}) {
                    const postStyle = this.$refs.postsCarousel ? 'carousel' : 'list';
                    this.currentTasks.add('fetchingPosts');

                    this.allPostsLoaded = false;
                    this.loadingMore = true;

                    const params = new URLSearchParams({
                        sort_by: this.sort.sortBy,
                        sort_direction: this.sort.sortDirection,
                        post_style: postStyle
                    });

                    this.filters.platform = platform ?? this.filters.platform;

                    this.filters.platform_id = platform_id ?? this.filters.platform_id;

                    if (agent_id) {
                        if (this.filters.agent_id.includes(agent_id)) {
                            this.filters.agent_id = this.filters.agent_id.filter(id => id !== agent_id);
                        } else {
                            this.filters.agent_id.push(agent_id);
                        }
                    }

                    Object.entries(this.filters).forEach(([key, value]) => {
                        const hasValue = Array.isArray(value) ? value.length : !!value;

                        if (hasValue) {
                            params.append(key, value);
                        }
                    })

                    let url = `/dashboard/user/social-media/agent/post-items?${params}${query ? `&${query}` : ''}`;

                    const res = await fetch(url, {
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                    this.loadingMore = false;
                    this.currentTasks.delete('fetchingPosts');

                    if (!res.ok) {
                        return toastr.error('@lang('Failed fetching post')');
                    }

                    const data = await res.text();

                    const tempEl = document.createElement('div');
                    tempEl.innerHTML = data;

                    if (this.$refs.postsCarousel && this.flickityData) {
                        this.flickityData.remove(
                            this.flickityData.cells
                            .map(cell => cell.element)
                            .filter(element => !element.classList.contains('social-media-agent-posts-carousel-load-more-wrap'))
                        );
                        this.flickityData.insert(tempEl.children, 0);
                        this.flickityData.selectCell(0, false, true);
                    }

                    if (this.$refs.postsList) {
                        const tableBodyEl = this.$refs.postsList.querySelector('tbody');
                        const appendNewPostsTo = tableBodyEl ? tableBodyEl : this.$refs.postsList;

                        appendNewPostsTo.innerHTML = data;
                    }

                    const nextPageUrl = tempEl.querySelector('[data-next-page-url]')?.getAttribute('data-next-page-url');

                    if (nextPageUrl) {
                        this.$refs.loadMoreTrigger?.setAttribute('href', nextPageUrl);
                    } else {
                        this.allPostsLoaded = true;
                    }
                },

                async sortPosts(sortBy, sortDirection = 'toggle') {
                    if (!sortBy || !sortDirection) {
                        return toastr.error('{{ __('Please provide all sort options.') }}')
                    }

                    this.sort.sortBy = sortBy;

                    if (sortDirection === 'toggle') {
                        this.sort.sortDirection =
                            (this.sort.sortBy !== sortBy || this.sort.sortDirection === 'asc') ?
                            'desc' :
                            'asc';
                    } else if (sortDirection === 'desc' || sortDirection === 'asc') {
                        this.sort.sortDirection = sortDirection;
                    }

                    await this.filterPosts()
                },

                updateCounters(pendingPostsCount, scheduledPostsCount, totalPostsCount = null) {
                    this.pendingPostsCount = pendingPostsCount;
                    this.scheduledPostsCount = scheduledPostsCount;
                    if (typeof totalPostsCount === 'number') {
                        this.totalPostsCount = totalPostsCount;
                    }

                    if (this.pendingCounterEl) {
                        Alpine.$data(this.pendingCounterEl).updateValue({
                            value: this.pendingPostsCount
                        });
                    }

                    if (this.scheduledCounterEl) {
                        Alpine.$data(this.scheduledCounterEl).updateValue({
                            value: this.scheduledPostsCount
                        });
                    }
                },
            }))
        })
    })();
</script>
