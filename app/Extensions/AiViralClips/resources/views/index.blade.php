@extends('panel.layout.app', [
    'disable_tblr' => true,
])

@section('title', __('AI Viral Clips'))
@section('titlebar_subtitle', __('Generate viral clips from long video content.'))
@section('titlebar_actions', '')

@section('content')
    <div class="py-10">
        <div
            class="lqd-external-chatbot-edit"
            x-data="aiInflucencerData"
        >
            @include('ai-viral-clips::create-clips.create-clips-window', ['overlay' => false])
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiInflucencerData', () => ({
                aiClipsWindowKey: 0,
                init() {
                    Alpine.store('aiInflucencerData', this);
                    this.toggleWindow();
                },
                // when open or close the window, change some neccessary css.
                toggleWindow(open = true) {
                    Alpine.nextTick(() => {
                        this.aiClipsWindowKey = 1;
                    })
                }
            }))
        });
    </script>
@endpush
