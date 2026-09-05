<script>
    function findPendingDubbingIds() {
        const ids = [];
        document.querySelectorAll('[id^="dubbing-"]').forEach(el => {
            if (el.querySelector('.lqd-shimmer-text')) {
                const id = el.id.replace('dubbing-', '');
                if (id) ids.push(id);
            }
        });
        return ids;
    }

    function checkDubbingStatus() {
        const ids = findPendingDubbingIds();

        if (!ids.length) return;

        const params = new URLSearchParams();
        ids.forEach(id => params.append('ids[]', id));

        fetch('{{ route('dashboard.user.video-dubbing.check') }}?' + params.toString())
            .then(response => response.json())
            .then(data => {
                for (const [id, item] of Object.entries(data.data)) {
                    const el = document.getElementById(item.divId);
                    if (el) {
                        el.outerHTML = item.html;
                    }
                }

                if (Array.isArray(data.videos)) {
                    window.dispatchEvent(new CustomEvent('dubbing-videos-updated', { detail: data.videos }));
                }
            })
            .catch(error => console.error('Error checking dubbing status:', error));
    }

    document.addEventListener('DOMContentLoaded', function() {
        setInterval(checkDubbingStatus, 5000);
    });
</script>
