<div x-data="InfluencerAvatarData">
    {{-- Add new product ad card --}}
    <x-card
        class:body="lg:p-16 p-9"
        class="max-w-[353px] text-center"
    >
        <figure class="mx-auto mb-6 inline-grid size-40 place-items-center rounded-full bg-heading-foreground/[3%]">
            <img
                class="h-[127px] w-[127px]"
                src="{{ asset('images/ai-influencer/social-video-create.png') }}"
                alt="Generate new video"
            >
        </figure>
        <p class="mx-auto mb-6 max-w-[370px] font-heading text-xl font-semibold leading-[1.3em] text-heading-foreground">
            @lang('Generate captivating video content with influencers.')
        </p>
        <x-button
            variant="ghost-shadow"
            href="#"
            @click.prevent="toggleInfluencerAvatarWindow()"
        >
            <x-tabler-plus class="size-4" />
            @lang('Generate New')
        </x-button>
    </x-card>
    @include('influencer-avatar::social-video-window.social-video-window', ['overlay' => true])
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('InfluencerAvatarData', () => ({
                // use for refresh the data in influencer avatar window
                influencerAvatarWindowKey: 1,
                // status of influencer avatar window
                openInfluencerAvatarWindow: false,
                init() {
                    Alpine.store('InfluencerAvatarData', this);
                },
                // toggle influencer avatar window, open or close
                toggleInfluencerAvatarWindow(open = true) {
                    this.openInfluencerAvatarWindow = open;
                    if (open) {
                        this.influencerAvatarWindowKey++;
                    }
                    Alpine.store('aiInflucencerData').toggleWindow(open);
                }
            }));
        });
    </script>
@endpush
