@extends('panel.layout.app')
@section('title', __('All Captioned Videos'))
@section('titlebar_subtitle', __('Browse every video you have captioned.'))

@section('titlebar_actions')
    <x-button
        variant="outline"
        size="md"
        href="{{ route('dashboard.user.ai-captions.index') }}"
    >
        <x-tabler-arrow-left class="size-4" />
        {{ __('Back to AI Captions') }}
    </x-button>
@endsection

@php
    $videosJson = $videos->getCollection()->map(fn ($v) => [
        'id'                 => $v->id,
        'title'              => $v->title,
        'video_url'          => $v->output_url,
        'template_id'        => $v->template_id,
        'template_name'      => $v->template_name,
        'duration_seconds'   => $v->duration_seconds,
        'formatted_duration' => $v->formatted_duration,
        'created_at'         => $v->created_at?->toIso8601String(),
    ])->values();
@endphp

@section('content')
    <div
        class="lqd-ai-captions py-7"
        x-data="aiCaptionsAllManager({ videos: {{ Illuminate\Support\Js::from($videosJson) }} })"
    >
        @if ($videos->isEmpty())
            <x-empty-state
                icon="tabler-quote-off"
                :title="__('No captioned videos yet')"
                :description="__('Upload a video and pick a template to get started.')"
            />
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($videos as $video)
                    @include('ai-captions::partials.video-item', [
                        'entry' => $video,
                        'selectionGroup' => 'selectedAll',
                    ])
                @endforeach
            </div>

            <div class="mt-8 flex justify-center">
                {{ $videos->links() }}
            </div>
        @endif

        @include('ai-captions::partials.video-detail-modal')

        <div
            class="pointer-events-none fixed bottom-8 end-0 start-0 z-20 transition-all max-lg:bottom-[calc(var(--bottom-menu-height)+1rem)] lg:start-[--navbar-width]"
            x-show="selectedAll.length > 0"
            x-cloak
            x-transition.scale-95
        >
            <div class="container">
                <form
                    class="pointer-events-auto flex flex-col items-center justify-between gap-1 rounded-full border border-foreground/5 bg-background px-6 py-4 shadow-xl shadow-black/5 md:flex-row md:py-1 md:pe-1"
                    @submit.prevent="bulkDelete"
                >
                    <span
                        class="text-2xs font-medium"
                        x-text="selectedAll.length + ' {{ __('selected') }}'"
                    ></span>

                    <div class="flex items-center gap-2">
                        <x-button type="submit">
                            {{ __('Move to Trash') }}
                        </x-button>

                        <x-button
                            variant="outline"
                            hover-variant="none"
                            type="button"
                            @click="selectedAll = []"
                        >
                            {{ __('Cancel') }}
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiCaptionsAllManager', (initial) => ({
                selectedAll: [],
                videos: (initial && initial.videos) || [],

                async bulkDelete() {
                    if (!this.selectedAll.length) return;
                    if (!confirm('{{ __('Are you sure you want to delete the selected videos?') }}')) {
                        return;
                    }

                    const ids = [...this.selectedAll];

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
                            this.selectedAll = [];
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
