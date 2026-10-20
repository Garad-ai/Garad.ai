<script>
    window._inProgressIds = @json($inProgress);
    window._checkStatusBaseUrl = '{{ route('dashboard.user.ai-video-pro.check') }}';
    window._videoStatusBusy = false;

    function checkVideoStatus() {
        if (window._videoStatusBusy) return;

        if (!window._inProgressIds || window._inProgressIds.length === 0) {
            if (window._videoStatusInterval) {
                clearInterval(window._videoStatusInterval);
                window._videoStatusInterval = null;
            }
            return;
        }

        window._videoStatusBusy = true;
        const params = new URLSearchParams();
        window._inProgressIds.forEach(id => params.append('ids[]', id));

        fetch(window._checkStatusBaseUrl + '?' + params.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(response => {
                if (!response.ok) throw new Error('Status request failed');
                return response.json();
            })
            .then(data => {
                if (!data.data || data.data.length === 0) return;

                const videoPro = document.querySelector('.lqd-ai-video-pro');
                const vmData = (videoPro && typeof Alpine !== 'undefined') ? Alpine.$data(videoPro) : null;
                const terminalIds = [];

                for (const item of Object.values(data.data)) {
                    const itemId = Number(item.videoData?.id || String(item.divId || '').replace('video-', ''));
                    const itemStatus = String(item.videoData?.status || '').toLowerCase();
                    const terminal = ['complete', 'completed', 'error', 'failed', 'cancelled', 'canceled'].includes(itemStatus);

                    if (terminal && itemId) terminalIds.push(itemId);

                    const isPending = Boolean(itemId && vmData?.pendingVideos?.includes(itemId));
                    const videoElement = itemId ? document.getElementById(`video-${itemId}`) : null;

                    if (isPending && terminal) {
                        // Clear the shimmer for both successful and failed jobs.
                        vmData.pendingVideos = vmData.pendingVideos.filter(id => id !== itemId);

                        const todayGrid = document.getElementById('today-videos-grid');
                        if (todayGrid && item.html) {
                            const temp = document.createElement('div');
                            temp.innerHTML = item.html.trim();
                            const newEl = temp.firstElementChild;
                            if (newEl) {
                                const firstServerItem = todayGrid.querySelector(':scope > .group\\/item');
                                if (firstServerItem) todayGrid.insertBefore(newEl, firstServerItem);
                                else todayGrid.appendChild(newEl);
                                if (typeof Alpine !== 'undefined') Alpine.initTree(newEl);
                            }
                        }
                    } else if (videoElement && item.html) {
                        videoElement.outerHTML = item.html;
                        const updated = itemId ? document.getElementById(`video-${itemId}`) : null;
                        if (updated && typeof Alpine !== 'undefined') Alpine.initTree(updated);
                    }

                    if (
                        itemId &&
                        itemStatus === 'complete' &&
                        item.videoData?.video_url &&
                        vmData?.videos &&
                        !vmData.videos.find(v => v.id === itemId)
                    ) {
                        vmData.videos.unshift(item.videoData);
                    }
                }

                if (terminalIds.length > 0) {
                    window._inProgressIds = window._inProgressIds.filter(id => !terminalIds.includes(Number(id)));
                }

                if (window._inProgressIds.length === 0 && window._videoStatusInterval) {
                    clearInterval(window._videoStatusInterval);
                    window._videoStatusInterval = null;
                }

                document.dispatchEvent(new CustomEvent('video-status-updated'));
            })
            .catch(error => console.warn('Video status check failed; retrying.', error))
            .finally(() => { window._videoStatusBusy = false; });
    }

    window.startVideoPolling = function(videoId) {
        if (!window._inProgressIds) window._inProgressIds = [];

        videoId = Number(videoId);
        if (videoId && !window._inProgressIds.includes(videoId)) {
            window._inProgressIds.push(videoId);
        }

        checkVideoStatus();

        if (!window._videoStatusInterval) {
            window._videoStatusInterval = setInterval(checkVideoStatus, 4000);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        if (window._inProgressIds && window._inProgressIds.length > 0) {
            checkVideoStatus();
            window._videoStatusInterval = setInterval(checkVideoStatus, 4000);
        }
    });
</script>
