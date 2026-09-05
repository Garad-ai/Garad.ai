@php
	$generators = [];
	$flux = null;

	$isFalAIEnabled = \App\Helpers\Classes\ApiHelper::setFalAIKey();

	$isIdeogramAvailable = \App\Helpers\Classes\MarketplaceHelper::isRegistered('ideogram');

	if (setting('dalle_hidden', 0) == '1') {
		$generators[] = ['value' => 'openai', 'label' => __('DALL-E')];
	}

	if (setting('stable_hidden', 0) !== '1') {
		$generators[] = ['value' => 'stable_diffusion', 'label' => __('Stable Diffusion')];
	}

	if ($isFalAIEnabled) {
		$fluxLabel = setting('fal_ai_default_model') === 'flux-realism'
					? __('Flux Realism Lora')
					: __('Flux Pro');

		$generators[] = ['value' => 'flux-pro', 'label' => $fluxLabel];
		$flux = 'flux-pro';
	}

	if ($isFalAIEnabled && \App\Helpers\Classes\MarketplaceHelper::isRegistered('ideogram')) {
		$generators[] = ['value' => 'ideogram', 'label' => 'Ideogram'];
	}

	if (setting('enabled_gpt_image_1', 0) == '1') {
		$generators[] = ['value' => 'gpt-image-1', 'label' => __('GPT image 1')];
	}

	if (setting('enabled_gpt_image_1_5', 0) == '1') {
		$generators[] = ['value' => 'gpt-image-1-5', 'label' => __('GPT image 1.5')];
	}

	if (setting('enabled_gpt_image_2', 0) == '1') {
		$generators[] = ['value' => 'gpt-image-2', 'label' => __('GPT image 2')];
	}

	if ($isFalAIEnabled && setting('enabled_flux_pro_kontext', 1) == '1') {
		$generators[] = ['value' => 'flux-pro-kontext', 'label' => __('Flux Pro Kontext')];
	}
	if ($isFalAIEnabled && setting('enabled_flux_2_flex', 1) == '1') {
		$generators[] = ['value' => 'flux-2-flex', 'label' => __('Flux 2 Flex')];
	}

	if ($isFalAIEnabled && \App\Helpers\Classes\MarketplaceHelper::isRegistered('nano-banana')) {
		$generators[] = ['value' => 'nano-banana', 'label' => __('Nano Banana')];
		$generators[] = ['value' => 'nano-banana-pro', 'label' => __('Nano Banana Pro')];
		$generators[] = ['value' => 'nano-banana-2', 'label' => __('Nano Banana 2')];
	}

	if ($isFalAIEnabled && \App\Helpers\Classes\MarketplaceHelper::isRegistered('see-dream-v4')) {
		$generators[] = ['value' => 'seedream/v4/text-to-image', 'label' => __('SeeDream V4')];
	}

	if ($isFalAIEnabled) {
		$generators[] = ['value' => 'xai/grok-imagine-image', 'label' => __('Grok Imagine Image')];
	}
@endphp

<div
    class="lqd-adv-img-editor-home transition-all"
    :class="{
        'opacity-0': currentView !== 'home',
        'invisible': currentView !== 'home',
        'pointer-events-none': currentView !== 'home'
    }"
>
    <div class="container">
        @include('advanced-image::home.generator-form')
        @include('advanced-image::home.advanced-options')
        @if ($app_is_demo)
            @if ($tools)
                @include('advanced-image::home.tools-grid', ['tools' => $tools])
            @endif
        @else
            @include('advanced-image::home.recent-images-grid', ['images' => $images])
        @endif
        @include('advanced-image::home.templates-grid')
        @if ($app_is_demo)
            @include('advanced-image::home.recent-images-grid', ['images' => $images])
        @else
            @if ($tools)
                @include('advanced-image::home.tools-grid', ['tools' => $tools])
            @endif
        @endif
        @include('advanced-image::home.predefined-prompts-grid')
    </div>
</div>
