@push('script')
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.dispatchEvent(new CustomEvent('generator-changed', {
                detail: { generator: '{{ \App\Domains\Entity\Enums\EntityEnum::AI_CLIP_KLAP->value }}', quantity: 1, _force: Date.now() }
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
                },
                // update the current step
                updateCurrentStep(step) {
                    if (this.currentStep === step) return;
                    this.currentStep = step;
                }
            }))

            // step generate clip data
            Alpine.data('generateClipsStepData', () => ({
                // submit status
                submitting: false,
                tabs: ["{{ __('Youtube URL') }}", "{{ __('Upload') }}"],
                // active tab
                activeTab: '{{ __('Youtube URL') }}',
                // form data
                formData: {
                    file: null,
                    source_video_url: '',
                    language: "{{ \App\Packages\Klap\Enums\Language::AUTO->value }}",
                    target_clip_count: 1,
                    max_duration: 30,
                    editing_options: {
                        captions: true,
                        emojis: true,
                        intro_title: true,
                    }
                },
                // file selected status
                fileSelected: false,
                initialize() {
                    this.submitting = false;
                    this.activeTab = "{{ __('Youtube URL') }}";
                    this.formData = {
                        file: null,
                        source_video_url: '',
                        language: "{{ \App\Packages\Klap\Enums\Language::AUTO->value }}",
                        target_clip_count: 1,
                        max_duration: 30,
                        editing_options: {
                            captions: true,
                            emojis: true,
                            intro_title: true,
                        }
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
                    if (this.activeTab == '{{ __('Youtube URL') }}') {
                        if (!this.formData.source_video_url || this.formData.source_video_url?.trim() ==
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

                        if (this.activeTab == '{{ __('Youtube URL') }}') {
                            delete this.formData.file;
                        } else {
                            delete this.formData.source_video_url;

                        }

                        let formData = new FormData();
                        formData.append('_token', '{{ csrf_token() }}');

                        for (const [key, value] of Object.entries(this.formData)) {
                            if (key == 'editing_options') {
                                Object.entries(this.formData.editing_options).forEach(([key,
                                    value
                                ]) => {
                                    formData.append(`editing_options[${key}]`,
                                        typeof value === 'boolean' ? (value ? '1' :
                                            '0') : value);
                                })
                            } else if (key == 'file') {
                                const file = document.getElementById('upload-file');
                                formData.append('file', file.files[0]);
                            } else {
                                formData.append(key, value);
                            }
                        }

                        const res = await fetch("{{ route('ai-viral-clips.generate-shorts') }}", {
                            method: 'post',
                            headers: {
                                'Accept': 'application/json'
                            },
                            body: formData
                        })

                        const resData = await res.json();

                        if (!res.ok || resData?.status == 'error') {
                            throw new Error(resData?.message ||
                                '{{ __('Unexpected issue happen') }}');
                        }

                        this.checkGenerateShortStatus(resData.resData?.id);
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error);
                        this.changeSubmittingStatus(false);
                    }
                },
                // check generate short status
                async checkGenerateShortStatus(id) {
                    try {
                        while (true) {
                            const res = await fetch(`/ai-viral-clips/check-clip-status/${id}`, {
                                method: 'get',
                                headers: {
                                    'Accept': 'application/json'
                                }
                            })

                            const resData = await res.json();

                            if (!res.ok || resData?.status == 'error') {
                                throw new Error(resData?.message ||
                                    '{{ __('Unexpected issue happen') }}');
                            }

                            if (resData.resData.status == 'processing') {
                                await new Promise((resolve) => setTimeout(resolve, 1000));
                            } else if (resData.resData.status == 'error') {
                                throw new Error('Error happen while generate shorts');
                            } else {
                                this.fetchPreviewList(resData.resData.output_id);
                                break;
                            }
                        }
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.error(error);

                        this.changeSubmittingStatus(false);
                    }
                },
                // fetch preview list
                async fetchPreviewList(folderId) {
                    try {
                        const res = await fetch(`/ai-viral-clips/preview-lists/${folderId}`, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        if (!res.ok) {
                            throw new Error(
                                "{{ __('Something went wrong. Please contact support for assistance') }}"
                            )
                        }

                        const resData = await res.json();

                        if (resData.status == 'error') {
                            throw new Error(resData.message ||
                                "{{ __('Something went wrong. Please contact support for assistance') }}"
                            );
                        }

                        if (resData.resData.length > 0) {
                            const shorts = resData.resData.map(data => {
                                data.videoUrl = `https://klap.app/player/${data.id}`;
                                return data;
                            });

                            Alpine.store('previewClipsStepData').setPreviews(shorts);
                            Alpine.store('createAiClipsData').updateCurrentStep(2);
                            this.changeSubmittingStatus(false);
                        } else {
                            throw new Error("{{ __('Generated nothing, Please try again.') }}")
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
                }
            }));

            // step previewe clips data
            Alpine.data('previewClipsStepData', () => ({
                // submitting status
                submitting: false,
                // folder id
                folderId: '',
                // playing video
                playingVideo: null,
                // previews
                previews: [{
                    id: 1,
                    folder_id: 1,
                    name: 'Test',
                    videoUrl: 'https://v3.fal.media/files/kangaroo/eAV2O8aojbuCksYzB0Dyr_tmpzs52cfoy.mp4',
                    playing: false,
                    checked: false
                }, {
                    id: 2,
                    folder_id: 2,
                    name: 'Test',
                    videoUrl: 'https://v3.fal.media/files/kangaroo/eAV2O8aojbuCksYzB0Dyr_tmpzs52cfoy.mp4',
                    playing: false,
                    checked: false
                }, {
                    id: 3,
                    folder_id: 3,
                    name: 'Test',
                    videoUrl: 'https://v3.fal.media/files/kangaroo/eAV2O8aojbuCksYzB0Dyr_tmpzs52cfoy.mp4',
                    playing: false,
                    checked: false
                }, {
                    id: 4,
                    folder_id: 4,
                    name: 'Test',
                    videoUrl: 'https://v3.fal.media/files/kangaroo/eAV2O8aojbuCksYzB0Dyr_tmpzs52cfoy.mp4',
                    playing: false,
                    checked: false
                }],
                init() {
                    Alpine.store('previewClipsStepData', this);
                },
                initialize() {
                    this.folderId = '';
                    this.submitting = false;
                    this.playingVideo = null;
                },
                // set folder id
                setFolderId(folderId) {
                    this.folderId = folderId;
                },
                // render final vide
                async renderVideos() {
                    const selectedVideos = this.previews.filter(preview => preview.checked);
                    if (selectedVideos.length == 0) {
                        toastr.warning("{{ __('Please select the videos you want to render') }}")
                        return;
                    }

                    if (this.playingVideo && this.playingVideo.playing) {
                        this.playingVideo.playing = false;
                        this._changeVideoPlayStatus(this.playingVideo);
                    }

                    const folderId = selectedVideos[0].folder_id;
                    const projectIds = selectedVideos.map(video => video.id).join(',');

                    this.changeSubmittingStatus(true);
                    try {
                        const res = await fetch("{{ route('ai-viral-clips.export-clips') }}", {
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                folder_id: folderId,
                                project_ids: projectIds
                            })
                        });

                        if (!res.ok) {
                            throw new Error(
                                "{{ __('Something went wrong. Please contact support for assistance') }}"
                            );
                        }

                        const resData = await res.json();

                        if (resData.status == 'error') {
                            throw new Error(resData.message ||
                                "{{ __('Something went wrong. Please contact support for assistance') }}"
                            );
                        }

                        window.location = "{{ route('dashboard.user.ai-influencer.index') }}";
                        // resData.resData.forEach(video => {
                        //     Alpine.store('exportedVideoData').addNewInProgressVideo(video);
                        // });

                        // Alpine.store('aiViralClipsData').toggleAiClipsWindow(false);
                        // this.changeSubmittingStatus(false);
                    } catch (error) {
                        toastr.error(error.message || error);
                        console.log(error);

                        this.changeSubmittingStatus(false);
                    }

                },
                // convert the duration to human readable time
                getDurationByString(duration) {
                    duration = parseInt(duration || 0);

                    const mins = Math.floor(duration / 60);
                    const secs = Math.floor(duration % 60);

                    return (mins > 9 ? mins : `0${mins}`) + ':' + (secs > 9 ? secs : `0${secs}`)
                },
                // play the video
                playVideo(video) {
                    if (this.playingVideo && this.playingVideo.id != video.id && this.playingVideo
                        .playing) {
                        this.playingVideo.playing = false;
                        this._changeVideoPlayStatus(this.playingVideo);
                    }

                    video.playing = !video.playing;
                    this._changeVideoPlayStatus(video);

                    this.playingVideo = video;
                },
                // video play or pause
                _changeVideoPlayStatus(video) {
                    const videoElement = document.getElementById(`generated-clips-item-${video.id}`);
                    if (video.playing) {
                        videoElement.play();
                    } else {
                        videoElement.pause();
                        videoElement.currentTime = 0;
                    }
                },
                // set previews
                setPreviews(previews) {
                    this.previews = previews;
                },
                // set submitting status
                changeSubmittingStatus(status = true) {
                    this.submitting = status;
                    Alpine.store('appLoadingIndicator')[status ? 'show' : 'hide']();
                }
            }));
        })
    </script>
@endpush
