@extends('panel.layout.app')
@section('title', __('AI Video Pro'))
@section('titlebar_subtitle', __('Create studio-quality videos and cinematics in seconds.'))
@section('titlebar_actions', __(''))

@section('content')
    <div
        class="lqd-ai-video-pro py-7"
        x-data="videoManager"
        @video-generated.window="addPendingVideo($event.detail.video_id)"
    >
        <div class="flex flex-col gap-8 lg:flex-row">
            {{-- Left Panel: Form --}}
            <div class="w-full lg:w-[445px] lg:shrink-0">
                @include('ai-video-pro::sections.input-section')
            </div>

            {{-- Right Panel: Videos --}}
            <div class="flex min-w-0 flex-1 flex-col">
                @include('ai-video-pro::sections.videos-section')
            </div>
        </div>

        @include('ai-video-pro::partials.video-detail-modal')

    </div>
@endsection

@push('script')
    @include('ai-video-pro::scripts.check-video-status')
    @include('ai-video-pro::scripts.image-handler')

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('videoManager', () => ({
                selectedToday: [],
                selectedPrevious: [],
                videos: @json($videosJson),
                pendingVideos: [],

                get totalSelected() {
                    return this.selectedToday.length + this.selectedPrevious.length;
                },

                addPendingVideo(videoId) {
                    this.pendingVideos.unshift(videoId);
                    window.startVideoPolling(videoId);
                },

                removePendingVideo(videoId) {
                    this.pendingVideos = this.pendingVideos.filter(id => id !== videoId);
                },

                cancelSelection() {
                    this.selectedToday = [];
                    this.selectedPrevious = [];
                },

                async bulkDelete() {
                    if (!confirm('{{ __('Are you sure you want to delete the selected videos?') }}')) return;

                    const ids = [...this.selectedToday, ...this.selectedPrevious];
                    try {
                        const response = await fetch('{{ route('dashboard.user.ai-video-pro.bulk-delete') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                ids
                            }),
                        });
                        const data = await response.json();

                        if (response.ok) {
                            ids.forEach(id => {
                                const el = document.getElementById('video-' + id);
                                if (el) el.remove();
                            });
                            this.cancelSelection();
                            toastr.success(data.message);
                        } else {
                            toastr.error(data.message || '{{ __('Delete failed.') }}');
                        }
                    } catch (e) {
                        toastr.error('{{ __('An error occurred.') }}');
                    }
                },
            }));
        });
    </script>
@endpush
