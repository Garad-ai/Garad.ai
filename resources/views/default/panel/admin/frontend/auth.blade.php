@extends('panel.layout.settings')
@section('title', __('Auth Settings'))
@section('titlebar_actions', '')

@section('settings')
    <form
        id="settings_form"
        onsubmit="return authSettingsSave();"
        enctype="multipart/form-data"
    >
        <div class="row mb-4">
            <div class="col-md-12">
                <div
                    class="flex flex-col space-y-1"
                    id="menu-items"
                >

                    <div class="menu-item relative rounded-lg border !bg-white shadow-[0_10px_10px_rgba(0,0,0,0.06)] dark:!bg-opacity-5">
                        <h4 class="accordion-title mb-0 flex cursor-pointer items-center justify-between !gap-1 !py-1 !pe-2 !ps-4">
                            <span>{{ __('Sign Pages') }}</span>
                            <div class="accordion-controls flex items-center">
                                <span class="handle size-10 inline-flex cursor-move items-center justify-center rounded-md hover:bg-black hover:!bg-opacity-10 dark:hover:bg-white">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        fill="none"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M9 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M9 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M9 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M15 5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M15 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M15 19m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                    </svg>
                                </span>
                            </div>
                        </h4>
                        <div class="accordion-content mt-3 p-3 pt-0">
                            <x-forms.input
                                class:container="mb-2"
                                id="login_enabled"
                                type="checkbox"
                                name="login_enabled"
                                :checked="$auth?->login_enabled == 1"
                                label="{{ __('ْuse custom image') }}"
                                switcher
                            />

                            <div
                                class="mb-[20px]"
                                id="loginField"
                                style="{{ $auth?->login_enabled == 1 ? '' : 'display:none' }}"
                            >
                                <label class="form-label">{{ __('Login Image') }}</label>
                                @if ($auth?->login_image != null && $auth?->login_image != 'undefined')
                                    <div class="mb-3">
                                        <img
                                            class="h-20 w-20 rounded-lg"
                                            src="/{{ $auth?->login_image }}"
                                            alt="login image"
                                        >
                                    </div>
                                @endif
                                <input
                                    class="form-control"
                                    id="login_image"
                                    type="file"
									accept="image/*"
                                    name="login_image"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="menu-item relative rounded-lg border !bg-white shadow-[0_10px_10px_rgba(0,0,0,0.06)] dark:!bg-opacity-5">
                    <h4 class="accordion-title mb-0 flex cursor-pointer items-center justify-between !gap-1 !py-1 !pe-2 !ps-4">
                        <span>{{ __('Chat Login / Sign up Popup Visual') }}</span>
                    </h4>
                    <div class="accordion-content mt-3 p-3 pt-0">
                        {{-- Slideshow images --}}
                        <div class="mb-5">
                            <label class="form-label">{{ __('Slideshow images (up to 5, change every 5 seconds)') }}</label>
                            <p class="mb-2 text-xs opacity-70">
                                {{ __('Recommended size: 930 x 1400 px (portrait, ratio about 2:3). JPG, PNG, WEBP or GIF, max 5 MB each. If a video is added, the video plays instead of the images.') }}
                            </p>
                            <div class="mb-3 flex flex-wrap gap-3" id="slideList">
                                @foreach ($slides as $slide)
                                    <div class="relative w-32 rounded-lg border p-1 text-xs" data-slide="{{ $slide['path'] }}">
                                        <img class="h-24 w-full rounded object-cover" src="/{{ $slide['path'] }}" alt="slide">
                                        <div class="mt-1 opacity-70">
                                            @if ($slide['width']) {{ $slide['width'] }} x {{ $slide['height'] }} px<br>@endif
                                            {{ number_format($slide['bytes'] / 1024, 0) }} KB
                                        </div>
                                        <button type="button" class="slide-remove absolute right-1 top-1 rounded bg-red-600 px-1.5 text-white" title="{{ __('Delete') }}">&times;</button>
                                    </div>
                                @endforeach
                            </div>
                            <input class="form-control" id="slide_images" type="file" accept="image/*" multiple>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs" id="slidePreview"></div>
                        </div>

                        {{-- Video --}}
                        <div class="mb-5">
                            <label class="form-label">{{ __('Background video (optional)') }}</label>
                            <p class="mb-2 text-xs opacity-70">{{ __('MP4 or WEBM, max 50 MB. Recommended 930 x 1400 px portrait, short loop without sound.') }}</p>
                            @if ($video)
                                <div class="mb-2 flex items-center gap-3 text-xs" id="videoCurrent">
                                    <video class="h-24 rounded-lg" src="/{{ $video['path'] }}" muted controls></video>
                                    <span class="opacity-70">{{ number_format($video['bytes'] / 1048576, 2) }} MB</span>
                                    <button type="button" class="btn btn-danger btn-sm" id="videoRemove">{{ __('Delete video') }}</button>
                                </div>
                            @endif
                            <input class="form-control" id="auth_video" type="file" accept="video/mp4,video/webm">
                            <div class="mt-1 text-xs opacity-70" id="videoInfo"></div>
                        </div>

                                                {{-- Bottom texts --}}
                        <div class="mb-5">
                            <label class="form-label" for="overlay_review">{{ __('Bottom line text') }}</label>
                            <input class="form-control" id="overlay_review" type="text" maxlength="120" value="{{ $auth?->overlay_review ?? '' }}" placeholder="See our 436 reviews on Trustpilot">
                        </div>

                        {{-- Bottom image --}}
                        <div class="mb-1">
                            <label class="form-label">{{ __('Bottom image') }}</label>
                            <p class="mb-2 text-xs opacity-70">{{ __('Shown at the bottom of the visual (for example Trustpilot reviews / SOC2 badge). Recommended: transparent PNG/WEBP, about 600 x 160 px, max 2 MB.') }}</p>
                            @if ($bottomImage)
                                <div class="mb-2 flex items-center gap-3 text-xs" id="bottomCurrent">
                                    <img class="h-16 rounded bg-black/60 p-1" src="/{{ $bottomImage['path'] }}" alt="bottom image">
                                    <span class="opacity-70">
                                        @if ($bottomImage['width']) {{ $bottomImage['width'] }} x {{ $bottomImage['height'] }} px, @endif
                                        {{ number_format($bottomImage['bytes'] / 1024, 0) }} KB
                                    </span>
                                    <button type="button" class="btn btn-danger btn-sm" id="bottomRemove">{{ __('Delete image') }}</button>
                                </div>
                            @endif
                            <input class="form-control" id="bottom_image" type="file" accept="image/png,image/jpeg,image/webp">
                            <div class="mt-1 text-xs opacity-70" id="bottomInfo"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button
            class="btn btn-primary w-full"
            id="settings_button"
            form="settings_form"
        >
            {{ __('Save') }}
        </button>
    </form>
@endsection

@push('script')
    <script>
        $('body').on('click', '.accordion-title', ev => {
            const accordionTitle = ev.currentTarget;
            accordionTitle.classList.toggle("active");
            accordionTitle.nextElementSibling.classList.toggle("hidden");
        });

        $(document).ready(function() {
            var loginCheckbox = document.getElementById('login_enabled');
            var loginField = document.getElementById('loginField');
            loginCheckbox.addEventListener('change', function() {
                loginField.style.display = loginCheckbox.checked ? '' : 'none';
            });
        });

        function authSettingsSave() {
            "use strict";

            document.getElementById("settings_button").disabled = true;
            document.getElementById("settings_button").innerHTML = magicai_localize.please_wait;

            var formData = new FormData();
            formData.append('login_enabled', $("#login_enabled").is(":checked") ? 1 : 0);
            // popup visual
            var files = $('#slide_images').prop('files');
            for (var i = 0; i < files.length; i++) {
                formData.append('slide_images[]', files[i]);
            }
            removedSlides.forEach(function(path) {
                formData.append('remove_slides[]', path);
            });
            if ($('#auth_video').prop('files')[0]) {
                formData.append('auth_video', $('#auth_video').prop('files')[0]);
            }
            if (removeVideo) {
                formData.append('remove_video', 1);
            }
            formData.append('overlay_review', $('#overlay_review').val());
            if ($('#bottom_image').prop('files')[0]) {
                formData.append('bottom_image', $('#bottom_image').prop('files')[0]);
            }
            if (removeBottom) {
                formData.append('remove_bottom_image', 1);
            }
            // login_image
            if ($('#login_image').prop('files') && $('#login_image').prop('files')[0]) {
                formData.append('login_image', $('#login_image').prop('files')[0]);
            }
            $.ajax({
                type: "post",
                url: "{{ url('/dashboard/admin/frontend/auth-save') }}",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: formData,
                contentType: false,
                processData: false,
                success: function(data) {
                    toastr.success('Settings saved succesfully')
                    setTimeout(function() { window.location.reload(); }, 600);
                },
                error: function(data) {
                    var err = (data.responseJSON && data.responseJSON.errors) || {};
                    $.each(err, function(index, value) {
                        toastr.error(value);
                    });
                    document.getElementById("settings_button").disabled = false;
                    document.getElementById("settings_button").innerHTML = "Save";
                }
            });
            return false;
        }

        var removedSlides = [];
        var removeVideo = false;
        var removeBottom = false;

        function fmtSize(bytes) {
            return bytes > 1048576 ? (bytes / 1048576).toFixed(2) + ' MB' : Math.round(bytes / 1024) + ' KB';
        }

        $(document).on('click', '.slide-remove', function() {
            var card = $(this).closest('[data-slide]');
            removedSlides.push(card.data('slide'));
            card.remove();
        });
        $('#videoRemove').on('click', function() { removeVideo = true; $('#videoCurrent').remove(); });
        $('#bottomRemove').on('click', function() { removeBottom = true; $('#bottomCurrent').remove(); });

        $('#slide_images').on('change', function() {
            var kept = $('#slideList [data-slide]').length;
            var files = Array.from(this.files);
            var preview = $('#slidePreview').empty();
            if (kept + files.length > 5) {
                toastr.error('You can have at most 5 images. Delete some first.');
                this.value = '';
                return;
            }
            files.forEach(function(file) {
                var url = URL.createObjectURL(file);
                var img = new Image();
                var cell = $('<div class="w-32"><img class="h-24 w-full rounded object-cover"><div class="opacity-70"></div></div>');
                cell.find('img').attr('src', url);
                preview.append(cell);
                img.onload = function() {
                    cell.find('div').text(img.naturalWidth + ' x ' + img.naturalHeight + ' px, ' + fmtSize(file.size));
                };
                img.src = url;
            });
        });
        $('#auth_video').on('change', function() {
            var f = this.files[0];
            $('#videoInfo').text(f ? f.name + ' - ' + fmtSize(f.size) : '');
        });
        $('#bottom_image').on('change', function() {
            var f = this.files[0];
            if (!f) { $('#bottomInfo').text(''); return; }
            var img = new Image();
            img.onload = function() {
                $('#bottomInfo').text(img.naturalWidth + ' x ' + img.naturalHeight + ' px, ' + fmtSize(f.size));
            };
            img.src = URL.createObjectURL(f);
        });
    </script>
@endpush
