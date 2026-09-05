@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Fashion Studio'))
@section('titlebar_pretitle', '')
@section('titlebar_actions')
    <x-button
        href="{{ route('dashboard.user.fashion-studio.photo_shoots.my') }}"
        variant="ghost-shadow"
    >
        {{ __('My Photoshoots') }}
    </x-button>

    <x-button
        href="{{ route('dashboard.user.fashion-studio.photo_shoots.index') }}"
        variant="primary"
    >
        <x-tabler-plus class="size-4" />
        {{ __('New Photoshoot') }}
    </x-button>
@endsection
@section('titlebar_subtitle', __('Produce breathtakingly lifelike fashion photos and videos in just moments.'))

@section('content')
    <div
        class="py-10"
        x-data="{
            toolsSectionShowing: localStorage.getItem('fashionStudioAdvancedSettings') === null ?
                true : localStorage.getItem('fashionStudioAdvancedSettings') === 'true',

            toggleSettings() {
                this.toolsSectionShowing = !this.toolsSectionShowing;
                localStorage.setItem('fashionStudioAdvancedSettings', this.toolsSectionShowing);
            }
        }"
    >
        <div class="space-y-12">
            @include('fashion-studio::components.banner')

            <div>
                <x-button
                    class="w-full gap-9"
                    variant="link"
                    @click.prevent="toggleSettings()"
                >
                    <span class="h-px grow bg-foreground/10"></span>
                    <span class="flex items-center gap-2">
                        <span x-text="toolsSectionShowing ? '{{ __('Hide AI Tools') }}' : '{{ __('Show AI Tools') }}'"></span>
                        <x-tabler-chevron-up
                            class="size-4"
                            x-show="toolsSectionShowing"
                        />
                        <x-tabler-chevron-down
                            class="size-4"
                            x-show="!toolsSectionShowing"
                        />
                    </span>
                    <span class="h-px grow bg-foreground/10"></span>
                </x-button>

                <div
                    class="flex flex-col gap-6"
                    x-show="toolsSectionShowing"
                    x-collapse
                >
                    @include('fashion-studio::components.ai-tools', ['tools' => $tools])
                </div>
            </div>

            @include('fashion-studio::components.latest-photoshoots')
        </div>
    </div>
@endsection

@push('script')
@endpush
