{{-- Edit Window --}}
{{-- <template x-teleport="body"> --}}
<div
    @class([
        'lqd-chatbot-edit-window',
        'fixed bottom-0 end-0 start-0 top-0 z-20 overflow-y-auto bg-background lg:start-[--navbar-width]' => $overlay,
    ])
    x-data="socialVideoData"
    @if ($overlay) x-cloak @endif
    x-show="{{ $overlay ? 'openInfluencerAvatarWindow' : 'true' }}"
    x-init="$watch('influencerAvatarWindowKey', () => initialize())"
>
    @if ($overlay)
        {{-- Edit Window Header --}}
        <div class="lqd-chatbot-edit-window-header border-b py-6">
            <div class="container">
                <div class="flex flex-wrap items-center justify-between gap-y-4">
                    <div class="flex flex-col items-start gap-3">
                        <x-button
                            class="m-0 text-xs text-foreground/70"
                            variant="link"
                            @click.prevent="Alpine.store('InfluencerAvatarData').toggleInfluencerAvatarWindow(false);"
                        >
                            <x-tabler-chevron-left class="size-4" />
                            {{ __('Back') }}
                        </x-button>
                        <h1 class="m-0">
                            {{ __('AI Influencer Avatars') }}
                        </h1>
                        <p class="m-0 text-2xs font-medium text-foreground">
                            {{ __('Generate captivating influencer video content for Reels, TikTok, and Shorts.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="lqd-chatbot-edit-window-content mt-3 py-8 max-lg:px-3">
        <div class="mx-auto flex max-w-[786px] flex-col flex-wrap justify-center gap-y-7">
            <div class="grid grid-cols-2 gap-x-1 gap-y-6 rounded-xl border px-2.5 py-4 md:grid-cols-4 lg:grid-cols-6">
                @foreach (\App\Packages\FalAI\Enums\Veed\AvatarEnum::cases() as $avatar)
                    <div
                        class="flex cursor-pointer flex-col gap-3.5 px-2"
                        @if ($loop->first) x-init="selectedAvatar = '{{ $avatar->value }}'" @endif
                        @click.prevent="selectedAvatar = '{{ $avatar->value }}'"
                    >
                        <div
                            class="relative flex h-32 rounded-xl outline"
                            :class="selectedAvatar == '{{ $avatar->value }}' ? 'outline-[3px] outline-accent' :
                                'outline-1 outline-border'"
                        >
                            <img
                                class="h-full w-full rounded-xl object-cover"
                                src="{{ '/images/fal-ai-veed-avatars/' . $avatar->image() }}"
                                alt=""
                            >
                            <span
                                class="absolute end-1 top-1.5 flex items-center justify-center rounded-full bg-background/80 p-2 shadow-lg"
                                x-show="selectedAvatar == '{{ $avatar->value }}'"
                                x-cloak
                            >
                                <x-tabler-check class="size-4" />
                            </span>
                        </div>
                        <span class="w-full text-nowrap text-center text-sm font-semibold text-heading-foreground">{{ $avatar->label() }}</span>
                    </div>
                @endforeach
            </div>
            <x-forms.input
                class:label="text-heading-foreground"
                label="{{ __('Script') }}"
                size="lg"
                name="script"
                type="textarea"
                rows="5"
                x-model="scriptContent"
            >
                <x-slot:label-extra>
                    <x-button
                        class="text-2xs"
                        type="button"
                        variant="link"
                        @click.prevent="generateAIScript"
                    >
                        <span class="me-1 inline-grid place-items-center">
                            <svg
                                class="col-start-1 col-end-1 row-start-1 row-end-1"
                                :class="{ hidden: scriptGeneration }"
                                width="17"
                                height="17"
                                viewBox="0 0 17 17"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    fill-rule="evenodd"
                                    clip-rule="evenodd"
                                    d="M16.5085 6.34955L15.113 6.63248C14.4408 6.7689 13.8236 7.10033 13.3386 7.58536C12.8536 8.0704 12.5221 8.68757 12.3857 9.35981L12.1028 10.7552C12.0748 10.8948 11.9994 11.0203 11.8893 11.1105C11.7792 11.2007 11.6412 11.25 11.4989 11.25C11.3566 11.25 11.2187 11.2007 11.1086 11.1105C10.9985 11.0203 10.923 10.8948 10.895 10.7552L10.6121 9.35981C10.4758 8.68751 10.1444 8.07027 9.65938 7.58522C9.17432 7.10016 8.55709 6.76878 7.8848 6.63248L6.48937 6.34955C6.35011 6.32107 6.22495 6.24537 6.13507 6.13525C6.04519 6.02513 5.99609 5.88733 5.99609 5.74519C5.99609 5.60304 6.04519 5.46526 6.13507 5.35514C6.22495 5.24502 6.35011 5.16932 6.48937 5.14084L7.8848 4.8579C8.55709 4.7216 9.17432 4.39022 9.65938 3.90516C10.1444 3.42011 10.4758 2.80288 10.6121 2.13058L10.895 0.73517C10.923 0.595627 10.9985 0.470081 11.1086 0.379882C11.2187 0.289682 11.3566 0.240395 11.4989 0.240395C11.6412 0.240395 11.7792 0.289682 11.8893 0.379882C11.9994 0.470081 12.0748 0.595627 12.1028 0.73517L12.3857 2.13058C12.5221 2.80283 12.8536 3.41999 13.3386 3.90503C13.8236 4.39007 14.4408 4.72148 15.113 4.8579L16.5085 5.14084C16.6477 5.16932 16.7729 5.24502 16.8627 5.35514C16.9526 5.46526 17.0017 5.60304 17.0017 5.74519C17.0017 5.88733 16.9526 6.02513 16.8627 6.13525C16.7729 6.24537 16.6477 6.32107 16.5085 6.34955ZM6.30231 13.4219L5.92312 13.4989C5.45558 13.5937 5.02634 13.8242 4.689 14.1616C4.35167 14.4989 4.12118 14.9281 4.02633 15.3957L3.94934 15.7749C3.92805 15.881 3.87064 15.9766 3.78687 16.0452C3.70309 16.1139 3.59813 16.1514 3.48982 16.1514C3.38151 16.1514 3.27654 16.1139 3.19277 16.0452C3.10899 15.9766 3.05157 15.881 3.03029 15.7749L2.9533 15.3957C2.85844 14.9281 2.62796 14.4989 2.29062 14.1616C1.95328 13.8242 1.52404 13.5937 1.0565 13.4989L0.677333 13.4219C0.571137 13.4006 0.475582 13.3432 0.406935 13.2594C0.338287 13.1756 0.300781 13.0707 0.300781 12.9624C0.300781 12.854 0.338287 12.7491 0.406935 12.6653C0.475582 12.5815 0.571137 12.5241 0.677333 12.5028L1.0565 12.4258C1.52404 12.331 1.95328 12.1005 2.29062 11.7632C2.62796 11.4258 2.85844 10.9966 2.9533 10.5291L3.03029 10.1499C3.05157 10.0437 3.10899 9.94813 3.19277 9.87948C3.27654 9.81083 3.38151 9.77334 3.48982 9.77334C3.59813 9.77334 3.70309 9.81083 3.78687 9.87948C3.87064 9.94813 3.92805 10.0437 3.94934 10.1499L4.02633 10.5291C4.12118 10.9966 4.35167 11.4258 4.689 11.7632C5.02634 12.1005 5.45558 12.331 5.92312 12.4258L6.30231 12.5028C6.4085 12.5241 6.50404 12.5815 6.57269 12.6653C6.64134 12.7491 6.67884 12.854 6.67884 12.9624C6.67884 13.0707 6.64134 13.1756 6.57269 13.2594C6.50404 13.3432 6.4085 13.4006 6.30231 13.4219Z"
                                    fill="url(#paint0_linear_8906_3722)"
                                />
                                <defs>
                                    <linearGradient
                                        id="paint0_linear_8906_3722"
                                        x1="17.0017"
                                        y1="8.19589"
                                        x2="0.137511"
                                        y2="6.25241"
                                        gradientUnits="userSpaceOnUse"
                                    >
                                        <stop stop-color="#8D65E9" />
                                        <stop
                                            offset="0.483"
                                            stop-color="#5391E4"
                                        />
                                        <stop
                                            offset="1"
                                            stop-color="#6BCD94"
                                        />
                                    </linearGradient>
                                </defs>
                            </svg>
                            <x-tabler-refresh
                                class="col-start-1 col-end-1 row-start-1 row-end-1 hidden size-4 animate-spin"
                                x-show="scriptGeneration"
                                ::class="{ hidden: !scriptGeneration }"
                            />
                        </span>
                        @lang('Generate with AI')
                    </x-button>
                </x-slot:label-extra>
            </x-forms.input>

            <x-button
                id="generate_btn"
                type="submit"
                ::disabled="isGenerating"
                @click.prevent="generateVideo()"
            >
                {{ __('Generate Video') }}
            </x-button>

            <div>
                <x-cost-preview class="w-full justify-end" />
            </div>
        </div>
    </div>
</div>
{{-- </template> --}}

@push('script')
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.dispatchEvent(new CustomEvent('generator-changed', {
                detail: { generator: '{{ \App\Domains\Entity\Enums\EntityEnum::VEED->value }}', quantity: 1, _force: Date.now() }
            }));
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('socialVideoData', () => ({
                // scriptContent
                scriptContent: '',
                // selected avatar
                selectedAvatar: '',
                // status for generating script or video
                isGenerating: false,
                scriptGeneration: false,
                initialize() {
                    this.scriptContent = '';
                    this.selectedAvatar = '';
                    this.isGenerating = false;
                    this.scriptGeneration = false;
                },
                /**
                 * generate ai script
                 *  @todo improve prompt
                 */
                generateAIScript() {
                    if (this.isInValid(this.scriptContent,
                            '{{ __('Please input basic script, so then ai can help you to generate script') }}'
                        )) {
                        return;
                    }

                    this.changeLoadingStatus(true);
                    this.scriptGeneration = true;

                    let formData = new FormData();
                    formData.append('prompt',
                        'Rewrite below content professionally. Must detect the content language and ensure that the response is also in same content language.'
                    );
                    formData.append('content', this.scriptContent);

                    $.ajax({
                        headers: {
                            'Accept': 'application/json',
                        },
                        type: 'post',
                        url: '/dashboard/user/openai/update-writing',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: (data) => {
                            this.scriptContent = data.result;
                            this.changeLoadingStatus(false);
                            this.scriptGeneration = false;
                        },
                        error: (data) => {
                            this.changeLoadingStatus(false);
                            this.scriptGeneration = false;
                            toastr.error(
                                '{{ __('Error happen while generate ai content') }}')
                        }
                    });
                },
                // generate video
                generateVideo() {
                    if (this.isInValid(this.scriptContent,
                            '{{ __('Please input script to generate video') }}') || this.isInValid(this
                            .selectedAvatar, '{{ __('Please select avatar to generate video') }}')) {
                        return;
                    }

                    this.changeLoadingStatus(true);

                    let formData = new FormData();
                    formData.append('avatar_id', this.selectedAvatar);
                    formData.append('text', this.scriptContent);

                    $.ajax({
                        headers: {
                            'Accept': 'application/json',
                        },
                        type: 'post',
                        url: '{{ route('dashboard.user.influencer-avatar.generate-short-video') }}',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: (data) => {
                            try {
                                if (data.status == 'success') {
                                    const video = {
                                        'task_id': data.resData.request_id,
                                        'status': 'in_progress',
                                        'used_ai_tool': 'fal-ai'
                                    };
                                    window.location = "{{ route('dashboard.user.ai-influencer.index') }}";
                                    // Alpine.store('exportedVideoData').addNewInProgressVideo(
                                    //     video);
                                    // Alpine.store('InfluencerAvatarData')
                                    //     .toggleInfluencerAvatarWindow(
                                    //         false);
                                } else {
                                    throw new Error(data.message ||
                                        '{{ __('Failed to generate new video') }}')
                                }
                                this.changeLoadingStatus(false);
                            } catch (error) {
                                this.changeLoadingStatus(false);
                                toastr.error(error.message || error);
                                console.error(error);
                            }
                        },
                        error: (error) => {
                            this.changeLoadingStatus(false);
                            toastr.error(error?.message || error);
                            console.error(error);
                        }
                    });
                },
                // validate the field if empty or not
                isInValid(element, errorMsg) {
                    if (element.trim() == '') {
                        toastr.warning(errorMsg);
                        return true;
                    }
                    return false;
                },
                changeLoadingStatus(loading = true) {
                    this.isGenerating = loading;
                    Alpine.store('appLoadingIndicator')[loading ? 'show' : 'hide']();
                }
            }))
        })
    </script>
@endpush
