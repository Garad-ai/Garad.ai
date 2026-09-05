@push('script')
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.dispatchEvent(new CustomEvent('generator-changed', {
                detail: { generator: '{{ \App\Domains\Entity\Enums\EntityEnum::AI_CLIP_VIZARD->value }}', quantity: 1, _force: Date.now() }
            }));
        });

        document.addEventListener('alpine:init', () => {
            // create ai clips window data
            Alpine.data('createAiClipsData', () => ({
                // current step
                currentStep: 1,
                init() {
                    Alpine.store('createAiClipsData', this);
                },
                initialize() {
                    this.currentStep = 1;
                }
            }))

            // step generate clip data
            Alpine.data('generateClipsStepData', () => ({
                // submit status
                submitting: false,
                tabs: ["{{ __('URL') }}", "{{ __('Upload') }}"],
                // active tab
                activeTab: '{{ __('URL') }}',
                // form data
                formData: {
                    file: null,
                    videoUrl: '',
                    videoType: 1,
                    lang: "{{ \App\Packages\Vizard\Enums\Language::EN->value }}",
                    ratioOfClip: "{{ \App\Packages\Vizard\Enums\Ratio::CLIP_1->value }}",
                    maxClipNumber: 5,
                    preferLength: 0,
                    headlineSwitch: true,
                    subtitleSwitch: true
                },
                // file selected status
                fileSelected: false,
                initialize() {
                    this.submitting = false;
                    this.activeTab = "{{ __('URL') }}";
                    this.formData = {
                        file: null,
                        videoUrl: '',
                        videoType: 1,
                        lang: "{{ \App\Packages\Vizard\Enums\Language::EN->value }}",
                        ratioOfClip: "{{ \App\Packages\Vizard\Enums\Ratio::CLIP_1->value }}",
                        maxClipNumber: 5,
                        preferLength: 0,
                        headlineSwitch: true,
                        subtitleSwitch: true
                    };
                    this.fileSelected = false;
                },
                // set active tab
                setActiveTab(tab) {
                    if (this.activeTab == tab) {
                        return;
                    }
                    this.activeTab = tab;
                },
                // set file select status
                setFileSelected(status = true) {
                    if (this.fileSelected == status) {
                        return;
                    }
                    this.fileSelected = status;
                },
                // go for next step
                nextStep() {
                    if (this.activeTab == '{{ __('URL') }}') {
                        if (!this.formData.videoUrl || this.formData.videoUrl?.trim() ==
                            '') {
                            toastr.warning('{{ __('Please input video url') }}');
                            return;
                        }
                    } else {
                        if (this.formData.file == null) {
                            toastr.warning(
                                '{{ __('Please select the video what you want to use!') }}');
                            return;
                        }
                    }
                    this.generateShorts();
                },
                // submit generate shorts
                async generateShorts() {
                    try {
                        this.changeSubmittingStatus(true);

                        if (this.activeTab == '{{ __('URL') }}') {
                            delete this.formData.file;
                        } else {
                            delete this.formData.videoUrl;
                        }

                        let formData = new FormData();
                        formData.append('_token', '{{ csrf_token() }}');

                        for (const [key, value] of Object.entries(this.formData)) {
                            if (key == 'file') {
                                const file = document.getElementById('upload-file');
                                formData.append('file', file.files[0]);
                            } else {
                                if (key == 'headlineSwitch' || key == 'subtitleSwitch') {
                                    formData.append(key, +value);
                                } else {
                                    formData.append(key, value);
                                }
                            }
                        }

                        const res = await fetch(
                            "{{ route('ai-viral-clips.vizard.generate-shorts') }}", {
                                method: 'post',
                                headers: {
                                    'Accept': 'application/json'
                                },
                                body: formData
                            })

                        const resData = await res.json();

                        if (!res.ok || resData?.status == 'error') {
                            throw new Error(resData?.message ||
                                '{{ __('Something went wrong, Please contact support for assistance') }}'
                            );
                        } else if (resData?.resData?.errorMsg) {
                            throw new Error(resData?.resData?.errorMsg ||
                                '{{ __('Something went wrong, Please contact support for assistance') }}'
                            );
                        }

                        this.retrieveClips(resData.resData?.projectId);
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error);
                        this.changeSubmittingStatus(false);
                    }
                },
                // check generate short status
                async retrieveClips(id) {
                    try {
                        while (true) {
                            const res = await fetch(
                                `/ai-viral-clips/vizard/retrieve-clips/${id}`, {
                                    method: 'get',
                                    headers: {
                                        'Accept': 'application/json'
                                    }
                                })

                            const resData = await res.json();

                            if (!res.ok || resData?.status == 'error') {
                                throw new Error(resData?.message ||
                                    '{{ __('Something went wrong, Please contact support for assistance') }}'
                                );
                            }

                            if (resData.resData.code == 1000) {
                                await new Promise((resolve) => setTimeout(resolve, 1000));
                            } else if (resData.resData.code != 2000) {
                                throw new Error('Error happen while generate shorts');
                            } else {
                                this.uploadFinalResultToServer(resData.resData?.videos);
                                break;
                            }
                        }
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error);

                        this.changeSubmittingStatus(false);
                    }
                },
                // set submitting status
                changeSubmittingStatus(status = true) {
                    this.submitting = status;
                    Alpine.store('appLoadingIndicator')[status ? 'show' : 'hide']();
                },
                // upload final result to the server (vizard video link expire 3 days later, max 7 days later, that is why we should upload to server)
                async uploadFinalResultToServer(videos) {
                    try {
                        const res = await fetch(
                            '{{ route('ai-viral-clips.vizard.store-final-result-vizard') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    videos
                                })
                            });

                        const resData = await res.json();

                        if (!res.ok || resData.status == 'error') {
                            throw new Error(res.message || resData.message ||
                                "Something went wrong, Please contact support for assistance");
                        }

                        window.location = "{{ route('dashboard.user.ai-influencer.index') }}";
                        // resData.resData.forEach(video => {
                        //     Alpine.store('exportedVideoData').addNewInProgressVideo(video);
                        // });

                        // Alpine.store('aiViralClipsData').toggleAiClipsWindow(false);
                        // this.changeSubmittingStatus(false);
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error);
                        this.changeSubmittingStatus(false);
                    }
                }
            }));
        })
    </script>
@endpush
