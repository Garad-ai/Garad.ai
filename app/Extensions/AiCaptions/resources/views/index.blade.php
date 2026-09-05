@extends('panel.layout.app')
@section('title', __('AI Captions'))
@section('titlebar_subtitle', __('Create studio-quality videos and cinematics in seconds.'))

@section('titlebar_actions')
    <x-button
        variant="outline"
        size="md"
        href="{{ route('dashboard.user.ai-captions.all') }}"
    >
        {{ __('View All Videos') }}
    </x-button>

    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('video-editor'))
        <x-button
            href="{{ route('dashboard.user.video-editor.index') }}"
            size="md"
        >
            <x-tabler-plus class="size-4" />
            {{ __('Open AI Video Editor') }}
        </x-button>
    @endif
@endsection

@section('content')
    <div
        class="lqd-ai-captions py-7"
        x-data="aiCaptionsManager"
        @caption-generated.window="addPendingVideo($event.detail.video_id)"
    >
        <div class="flex flex-col gap-8 lg:flex-row">
            {{-- Left Panel: Form --}}
            <div class="w-full lg:w-[445px] lg:shrink-0">
                @include('ai-captions::sections.input-section')
            </div>

            {{-- Right Panel: Videos --}}
            <div class="flex min-w-0 flex-1 flex-col">
                @include('ai-captions::sections.videos-section')
            </div>
        </div>

        @include('ai-captions::partials.template-picker-modal')
        @include('ai-captions::partials.video-detail-modal')
    </div>
@endsection

@push('script')
    @include('ai-captions::scripts.check-video-status')

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiCaptionsManager', () => ({
                selectedToday: [],
                selectedPrevious: [],
                videos: @json($videosJson),
                pendingVideos: [],

                get totalSelected() {
                    return this.selectedToday.length + this.selectedPrevious.length;
                },

                addPendingVideo(videoId) {
                    if (!this.pendingVideos.includes(videoId)) {
                        this.pendingVideos.unshift(videoId);
                    }
                    if (typeof window.startCaptionsPolling === 'function') {
                        window.startCaptionsPolling(videoId);
                    }
                },

                cancelSelection() {
                    this.selectedToday = [];
                    this.selectedPrevious = [];
                },

                async bulkDelete() {
                    if (!confirm('{{ __('Are you sure you want to delete the selected videos?') }}')) {
                        return;
                    }

                    const ids = [...this.selectedToday, ...this.selectedPrevious];

                    try {
                        const response = await fetch('{{ route('dashboard.user.ai-captions.bulk-delete') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ ids }),
                        });

                        const data = await response.json();

                        if (response.ok) {
                            ids.forEach((id) => {
                                const el = document.getElementById('caption-video-' + id);
                                if (el) el.remove();
                            });
                            this.videos = this.videos.filter((v) => !ids.includes(v.id));
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
