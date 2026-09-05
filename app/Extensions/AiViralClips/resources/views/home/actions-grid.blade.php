<div x-data="aiViralClipsData">
    {{-- Add new product ad card --}}
    <x-card
        class:body="lg:p-16 p-9"
        class="max-w-[353px] text-center"
    >
        <figure class="mx-auto mb-6 inline-grid size-40 place-items-center rounded-full bg-heading-foreground/[3%]">
            <img
                class="h-[127px] w-[127px]"
                src="{{ asset('images/ai-influencer/ai-video-clip.png') }}"
                alt="Generate new video"
            >
        </figure>
        <p class="mx-auto mb-6 max-w-[370px] font-heading text-xl font-semibold leading-[1.3em] text-heading-foreground">
            @lang('Generate viral clips from long video content.')
        </p>
        <x-button
            variant="ghost-shadow"
            href="#"
            @click.prevent="toggleAiClipsWindow()"
        >
            <x-tabler-plus class="size-4" />
            @lang('Generate New')
        </x-button>
    </x-card>
    @include('ai-viral-clips::create-clips.create-clips-window', ['overlay' => true])
</div>

@push('script')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('aiViralClipsData', () => ({
                // use for refresh the data in ai viral clips window
                aiClipsWindowKey: 1,
                // status of ai clips window
                openAiClipsWindow: false,
                init() {
                    Alpine.store('aiViralClipsData', this);
                },
                // toggle ai clips window, open or close
                toggleAiClipsWindow(open = true) {
                    this.openAiClipsWindow = open;
                    if (open) {
                        this.aiClipsWindowKey++;
                    }
                    Alpine.store('aiInflucencerData').toggleWindow(open);
                }
            }));
        });
    </script>
@endpush
