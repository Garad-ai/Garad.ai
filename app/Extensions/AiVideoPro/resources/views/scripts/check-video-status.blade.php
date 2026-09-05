<script>
    window._inProgressIds = @json($inProgress);
    window._checkStatusBaseUrl = '{{ route('dashboard.user.ai-video-pro.check') }}';

    function checkVideoStatus() {
        if (!window._inProgressIds || window._inProgressIds.length === 0) {
            if (window._videoStatusInterval) {
                clearInterval(window._videoStatusInterval);
                window._videoStatusInterval = null;
            }
            return;
        }

        const params = new URLSearchParams();
        window._inProgressIds.forEach(id => params.append('ids[]', id));

        fetch(window._checkStatusBaseUrl + '?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (!data.data || data.data.length === 0) return;

                const videoPro = document.querySelector('.lqd-ai-video-pro');
                const vmData = (videoPro && typeof Alpine !== 'undefined') ? Alpine.$data(videoPro) : null;
                const completedIds = [];

                for (const item of Object.values(data.data)) {
                    if (item.videoData) {
                        completedIds.push(item.videoData.id);
                    }

                    const isPending = vmData?.pendingVideos?.includes(item.videoData?.id);
                    const videoElement = document.getElementById(item.divId);

                    if (isPending) {
                        // AJAX-submitted video completed:
                        // 1. Remove from pendingVideos (Alpine removes shimmer)
                        vmData.pendingVideos = vmData.pendingVideos.filter(id => id !== item.videoData.id);

                        // 2. Insert server-rendered HTML as a standalone element in the grid
                        const todayGrid = document.getElementById('today-videos-grid');
                        if (todayGrid && item.html) {
                            const temp = document.createElement('div');
                            temp.innerHTML = item.html.trim();
                            const newEl = temp.firstElementChild;
                            // Insert after the x-for template items (first non-template child)
                            const firstServerItem = todayGrid.querySelector(':scope > .group\\/item');
                            if (firstServerItem) {
                                todayGrid.insertBefore(newEl, firstServerItem);
                            } else {
                                todayGrid.appendChild(newEl);
                            }
                            if (typeof Alpine !== 'undefined') {
                                Alpine.initTree(newEl);
                            }
                        }
                    } else if (videoElement) {
                        // Server-rendered in-progress video: replace innerHTML as before
                        videoElement.innerHTML = item.html;
                        if (typeof Alpine !== 'undefined') {
                            Alpine.initTree(videoElement);
                        }
                    }

                    // Push completed video data into the modal's videos array
                    if (item.videoData && vmData) {
                        if (vmData.videos && !vmData.videos.find(v => v.id === item.videoData.id)) {
                            vmData.videos.unshift(item.videoData);
                        }
                    }
                }

                // Remove completed IDs from polling tracking
                if (completedIds.length > 0) {
                    window._inProgressIds = window._inProgressIds.filter(id => !completedIds.includes(id));
                }

                if (window._inProgressIds.length === 0 && window._videoStatusInterval) {
                    clearInterval(window._videoStatusInterval);
                    window._videoStatusInterval = null;
                }

                document.dispatchEvent(new CustomEvent('video-status-updated'));
            })
            .catch(error => console.error('Error:', error));
    }

    window.startVideoPolling = function(videoId) {
        if (!window._inProgressIds) {
            window._inProgressIds = [];
        }

        if (!window._inProgressIds.includes(videoId)) {
            window._inProgressIds.push(videoId);
        }

        if (!window._videoStatusInterval) {
            window._videoStatusInterval = setInterval(checkVideoStatus, 5000);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        if (window._inProgressIds && window._inProgressIds.length > 0) {
            window._videoStatusInterval = setInterval(checkVideoStatus, 5000);
        }
    });
</script>
