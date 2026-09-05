@php
    $banner = $app_is_demo ? null : \App\Extensions\OnboardingPro\System\Models\Banner::query()->where('status', true)->first();
    $style_string = '';

	if ($banner) {
		$backgroundColor = $banner->background_color ?? null;
		$textColor = $banner->text_color ?? null;

		if ($backgroundColor) {
			$style_string .= '.top-banner { background-color: ' . $backgroundColor . '; }';
		}
		if ($textColor) {
			$style_string .= '.top-banner { color: ' . $textColor . '; }';
		}
	}
@endphp

@if (filled($style_string))
    <style>
        {{ $style_string }}
    </style>
@endif

@auth
    @if ($banner && !auth()?->user()?->isAdmin())
        @php
            $display = \App\Extensions\OnboardingPro\System\Models\BannerUser::query()
                ->where('user_id', auth()->user()->id)
                ->where('banner_id', $banner->id)
                ->first();
        @endphp

        @if ($banner->permanent == 0 && !$display)
            <x-alert
                class="top-banner relative z-[9999] items-center rounded-md py-3 shadow-md"
                id="banner-extension"
                size="xs"
            >
                <div class="flex w-full grow items-center justify-between gap-3">
                    <p class="m-0 text-lg font-semibold">
                        {{ $banner->description }}
                    </p>
                    <x-button
                        class="rounded-md bg-white px-4 py-2 text-sm transition"
                        onclick="sendRequestBanner({{ $banner->id }})"
                    >
                        {{ __('Close') }}
                    </x-button>
                </div>
            </x-alert>
        @elseif($banner->permanent == 1)
            <x-alert
                class="top-banner relative z-[9999] items-center rounded-md py-3 shadow-md"
                id="banner-extension"
                size="xs"
            >
                <div class="flex w-full grow items-center justify-between gap-3">
                    <p class="m-0 text-lg font-semibold">
                        {{ $banner->description }}
                    </p>
                </div>
            </x-alert>
        @endif
    @endif
@endauth
<script>
    function sendRequestBanner(bannerId) {
        const url = `{{ route('dashboard.admin.onboarding-pro.banner.display', ['id' => ':id']) }}`.replace(':id', bannerId);

        fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => {
                if (response.ok) {
                    return response.json();
                } else {}
            })
            .then(data => {
                const alertElement = document.getElementById('banner-extension');
                if (alertElement) {
                    alertElement.style.display = 'none';
                }
            })
            .catch(error => {});
    }
</script>
