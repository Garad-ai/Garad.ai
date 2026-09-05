<script>
    window._aiCaptionsInProgressIds = @json($inProgress);
    window._aiCaptionsCheckUrl = '{{ route('dashboard.user.ai-captions.check') }}';

    function checkAiCaptionsStatus() {
        if (!window._aiCaptionsInProgressIds || window._aiCaptionsInProgressIds.length === 0) {
            if (window._aiCaptionsStatusInterval) {
                clearInterval(window._aiCaptionsStatusInterval);
                window._aiCaptionsStatusInterval = null;
            }
            return;
        }

        const params = new URLSearchParams();
        window._aiCaptionsInProgressIds.forEach((id) => params.append('ids[]', id));

        fetch(window._aiCaptionsCheckUrl + '?' + params.toString())
            .then((response) => response.json())
            .then((data) => {
                if (!data.data) return;

                const root = document.querySelector('.lqd-ai-captions');
                const vmData = (root && typeof Alpine !== 'undefined') ? Alpine.$data(root) : null;
                const completedIds = [];

                for (const item of data.data) {
                    const videoId = item.videoData?.id || Number(String(item.divId).replace('caption-video-', ''));
                    const isPending = vmData?.pendingVideos?.includes(videoId);
                    const existingEl = document.getElementById(item.divId);

                    if (isPending && item.html) {
                        vmData.pendingVideos = vmData.pendingVideos.filter((id) => id !== videoId);

                        const todayGrid = document.getElementById('today-captions-grid');
                        if (todayGrid) {
                            const temp = document.createElement('div');
                            temp.innerHTML = item.html.trim();
                            const newEl = temp.firstElementChild;
                            if (newEl) {
                                const firstItem = todayGrid.querySelector(':scope > .group\\/item');
                                if (firstItem) {
                                    todayGrid.insertBefore(newEl, firstItem);
                                } else {
                                    todayGrid.appendChild(newEl);
                                }
                                if (typeof Alpine !== 'undefined') {
                                    Alpine.initTree(newEl);
                                }
                            }
                        }
                    } else if (existingEl) {
                        existingEl.innerHTML = item.html;
                        if (typeof Alpine !== 'undefined') {
                            Alpine.initTree(existingEl);
                        }
                    }

                    if (item.videoData && vmData) {
                        if (!vmData.videos.find((v) => v.id === item.videoData.id)) {
                            vmData.videos.unshift(item.videoData);
                        }
                        completedIds.push(item.videoData.id);
                    }
                }

                if (completedIds.length > 0) {
                    window._aiCaptionsInProgressIds = window._aiCaptionsInProgressIds.filter(
                        (id) => !completedIds.includes(id)
                    );
                }

                if (window._aiCaptionsInProgressIds.length === 0 && window._aiCaptionsStatusInterval) {
                    clearInterval(window._aiCaptionsStatusInterval);
                    window._aiCaptionsStatusInterval = null;
                }
            })
            .catch((err) => console.error('AI Captions polling error:', err));
    }

    window.startCaptionsPolling = function (videoId) {
        if (!window._aiCaptionsInProgressIds) {
            window._aiCaptionsInProgressIds = [];
        }
        if (!window._aiCaptionsInProgressIds.includes(videoId)) {
            window._aiCaptionsInProgressIds.push(videoId);
        }
        if (!window._aiCaptionsStatusInterval) {
            window._aiCaptionsStatusInterval = setInterval(checkAiCaptionsStatus, 5000);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (window._aiCaptionsInProgressIds && window._aiCaptionsInProgressIds.length > 0) {
            window._aiCaptionsStatusInterval = setInterval(checkAiCaptionsStatus, 5000);
        }
    });
</script>
