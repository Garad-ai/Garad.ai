@extends('panel.layout.app')
@section('title', __('Video Dubbing'))
@section('titlebar_subtitle', __('Translate your videos into multiple languages.'))
@section('titlebar_actions', '')

@section('content')
    <div
        class="py-10"
        x-data="{
            selectedToday: [],
            selectedPrevious: [],
            videosJson: {{ $videosJson }},

            get totalSelected() {
                return this.selectedToday.length + this.selectedPrevious.length;
            },

            cancelSelection() {
                this.selectedToday = [];
                this.selectedPrevious = [];
            },

            async bulkDelete() {
                if (!confirm('{{ __('Are you sure you want to delete the selected videos?') }}')) return;

                const ids = [...this.selectedToday, ...this.selectedPrevious];
                try {
                    const response = await fetch('{{ route('dashboard.user.video-dubbing.bulk-delete') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ ids }),
                    });
                    const data = await response.json();

                    if (response.ok) {
                        ids.forEach(id => {
                            const el = document.getElementById('dubbing-' + id);
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
        }"
    >
        <div class="flex flex-col gap-8 lg:flex-row">
            {{-- Left Panel: Source Video Form --}}
            <div class="w-full lg:w-[340px] lg:shrink-0">
                @include('video-dubbing::sections.input-section')
            </div>

            {{-- Right Panel: Videos Grid --}}
            <div class="min-w-0 flex-1">
                @include('video-dubbing::sections.videos-section')
            </div>
        </div>
    </div>
@endsection

@push('script')
    @include('video-dubbing::scripts.check-video-status')
@endpush
