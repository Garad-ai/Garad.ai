@php
    use App\Helpers\Classes\MarketplaceHelper;

    $veAiVideoProEnabled = MarketplaceHelper::isRegistered('ai-video-pro');
    $veAiMusicProEnabled = MarketplaceHelper::isRegistered('ai-music-pro');
    $veTtsEnabled = ($settings_two->feature_tts_openai ?? false)
        || ($settings_two->feature_tts_google ?? false)
        || ($settings_two->feature_tts_elevenlabs ?? false)
        || ($settings_two->feature_tts_azure ?? false)
        || ($settings_two->feature_tts_speechify ?? false);
@endphp

{{-- Compact Icon Sidebar --}}
<div class="lqd-ve-sidebar flex min-h-0 w-[90px] shrink-0 flex-col items-center overflow-y-auto border-e border-foreground/5 bg-background px-2 py-4">
	<nav class="flex w-full shrink-0 flex-col items-center gap-1.5">
		{{-- Library --}}
		<button
			class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium transition-colors"
			:class="leftPanelOpen && leftPanelTab === 'library' ? 'bg-primary/10 text-primary' : 'text-foreground/60 hover:bg-foreground/5 hover:text-foreground'"
			@click.prevent="if (leftPanelOpen && leftPanelTab === 'library') { leftPanelOpen = false } else { leftPanelTab = 'library'; leftPanelOpen = true }"
			title="{{ __('Library') }}"
		>
			<div
				class="flex size-9 items-center justify-center rounded-full border transition-colors"
				:class="leftPanelOpen && leftPanelTab === 'library' ? 'border-primary/20 bg-primary/5' : 'border-foreground/10 group-hover:border-foreground/20'"
			>
				<x-tabler-folder class="size-[18px]" />
			</div>
			{{ __('Library') }}
		</button>

		{{-- Text --}}
		<button
			class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium text-foreground/60 transition-colors hover:bg-foreground/5 hover:text-foreground"
			@click.prevent="addTextClip()"
			title="{{ __('Text') }}"
		>
			<div class="flex size-9 items-center justify-center rounded-full border border-foreground/10 transition-colors group-hover:border-foreground/20">
				<x-tabler-typography class="size-[18px]" />
			</div>
			{{ __('Text') }}
		</button>

		@if ($veAiVideoProEnabled)
			{{-- AI Generate --}}
			<button
				class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium transition-colors"
				:class="leftPanelOpen && leftPanelTab === 'ai' ? 'bg-primary/10 text-primary' : 'text-foreground/60 hover:bg-foreground/5 hover:text-foreground'"
				@click.prevent="if (leftPanelOpen && leftPanelTab === 'ai') { leftPanelOpen = false } else { leftPanelTab = 'ai'; leftPanelOpen = true }"
				title="{{ __('AI Generate') }}"
			>
				<div
					class="flex size-9 items-center justify-center rounded-full border transition-colors"
					:class="leftPanelOpen && leftPanelTab === 'ai' ? 'border-primary/20 bg-primary/5' : 'border-foreground/10 group-hover:border-foreground/20'"
				>
					<x-tabler-sparkles class="size-[18px]" />
				</div>
				{{ __('AI') }}
			</button>
		@endif

		@if ($veTtsEnabled)
			{{-- AI Voice --}}
			<button
				class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium transition-colors"
				:class="leftPanelOpen && leftPanelTab === 'voice' ? 'bg-primary/10 text-primary' : 'text-foreground/60 hover:bg-foreground/5 hover:text-foreground'"
				@click.prevent="if (leftPanelOpen && leftPanelTab === 'voice') { leftPanelOpen = false } else { leftPanelTab = 'voice'; leftPanelOpen = true; loadVoiceModels(); if (ttsProvider !== 'openai' && !voiceOptions.length) onVoiceLanguageChange() }"
				title="{{ __('AI Voice') }}"
			>
				<div
					class="flex size-9 items-center justify-center rounded-full border transition-colors"
					:class="leftPanelOpen && leftPanelTab === 'voice' ? 'border-primary/20 bg-primary/5' : 'border-foreground/10 group-hover:border-foreground/20'"
				>
					<x-tabler-microphone class="size-[18px]" />
				</div>
				{{ __('Voice') }}
			</button>
		@endif

		@if ($veAiMusicProEnabled)
			{{-- AI Music --}}
			<button
				class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium transition-colors"
				:class="leftPanelOpen && leftPanelTab === 'music' ? 'bg-primary/10 text-primary' : 'text-foreground/60 hover:bg-foreground/5 hover:text-foreground'"
				@click.prevent="if (leftPanelOpen && leftPanelTab === 'music') { leftPanelOpen = false } else { leftPanelTab = 'music'; leftPanelOpen = true }"
				title="{{ __('AI Music') }}"
			>
				<div
					class="flex size-9 items-center justify-center rounded-full border transition-colors"
					:class="leftPanelOpen && leftPanelTab === 'music' ? 'border-primary/20 bg-primary/5' : 'border-foreground/10 group-hover:border-foreground/20'"
				>
					<x-tabler-music class="size-[18px]" />
				</div>
				{{ __('Music') }}
			</button>
		@endif

		{{-- Size --}}
		<button
			class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium transition-colors"
			:class="leftPanelOpen && leftPanelTab === 'size' ? 'bg-primary/10 text-primary' : 'text-foreground/60 hover:bg-foreground/5 hover:text-foreground'"
			@click.prevent="if (leftPanelOpen && leftPanelTab === 'size') { leftPanelOpen = false } else { leftPanelTab = 'size'; leftPanelOpen = true }"
			title="{{ __('Size') }}"
		>
			<div
				class="flex size-9 items-center justify-center rounded-full border transition-colors"
				:class="leftPanelOpen && leftPanelTab === 'size' ? 'border-primary/20 bg-primary/5' : 'border-foreground/10 group-hover:border-foreground/20'"
			>
				<x-tabler-dimensions class="size-[18px]" />
			</div>
			{{ __('Size') }}
		</button>

		{{-- Layers --}}
		<button
			class="group flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[10px] font-medium transition-colors"
			:class="leftPanelOpen && leftPanelTab === 'layers' ? 'bg-primary/10 text-primary' : 'text-foreground/60 hover:bg-foreground/5 hover:text-foreground'"
			@click.prevent="if (leftPanelOpen && leftPanelTab === 'layers') { leftPanelOpen = false } else { leftPanelTab = 'layers'; leftPanelOpen = true }"
			title="{{ __('Layers') }}"
		>
			<div
				class="flex size-9 items-center justify-center rounded-full border transition-colors"
				:class="leftPanelOpen && leftPanelTab === 'layers' ? 'border-primary/20 bg-primary/5' : 'border-foreground/10 group-hover:border-foreground/20'"
			>
				<x-tabler-stack-2 class="size-[18px]" />
			</div>
			{{ __('Layers') }}
		</button>
	</nav>
</div>
