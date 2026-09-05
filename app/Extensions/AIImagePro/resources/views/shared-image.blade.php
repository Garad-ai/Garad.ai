@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_default_sidebar' => true,
    'disable_titlebar' => true,
    'disable_footer' => true,
])

@section('content')
	<div class="min-h-screen bg-background">
		<div class="flex flex-col md:flex-row overflow-hidden min-h-screen">
			{{-- Image Section --}}
			<div class="flex-1 flex items-center justify-center p-8 overflow-hidden">
				@if(!empty($image['urls']))
					@foreach($image['urls'] as $imgUrl)
						<img
							src="{{ $imgUrl }}"
							alt="{{ $image['prompt'] }}"
							class="max-w-full max-h-full object-contain mb-4"
						/>
					@endforeach
				@else
					<div class="flex items-center justify-center">
						<p class="text-foreground/50">{{ __('Image not available') }}</p>
					</div>
				@endif
			</div>

			{{-- Details Sidebar --}}
			<div class="w-full md:w-[420px] bg-background flex flex-col overflow-hidden py-10">
				{{-- Header with User Info --}}
				<div class="flex items-center gap-3 p-6 border-b border-border/50">
					<div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 ring-1 ring-border/30">
						@if(!empty($image['user']['avatar']))
							<img src="/{{ $image['user']['avatar'] }}" alt="" class="w-full h-full object-cover">
						@else
							<div class="w-full h-full bg-foreground/10 flex items-center justify-center">
								<span class="text-sm font-semibold text-foreground">
									{{ $image['user']['initial'] ?? 'U' }}
								</span>
							</div>
						@endif
					</div>
					<div class="flex-1 min-w-0">
						<p class="font-medium text-foreground text-sm">
							{{ $image['user']['name'] ?? __('Anonymous') }}
						</p>
					</div>
				</div>

				{{-- Scrollable Content --}}
				<div class="flex-1 overflow-y-auto p-6 space-y-6">
					{{-- Prompt/Description --}}
					<div>
						<p class="text-foreground/70 text-sm leading-relaxed">
							{{ $image['prompt'] ?? __('Untitled') }}
						</p>
					</div>

					{{-- Metadata --}}
					<div class="space-y-4 pt-4 border-t border-border/50">
						<div class="flex justify-between items-center">
							<span class="text-sm text-foreground/50">{{ __('Date') }}</span>
							<span class="text-sm text-foreground font-medium">{{ $image['date'] ?? 'Today' }}</span>
						</div>
						@if(!empty($image['model']))
							<div class="flex justify-between items-center">
								<span class="text-sm text-foreground/50">{{ __('AI Model') }}</span>
								<span class="text-sm text-foreground font-medium">{{ $image['model'] }}</span>
							</div>
						@endif
						@if(!empty($image['style']))
							<div class="flex justify-between items-center">
								<span class="text-sm text-foreground/50">{{ __('Art Style') }}</span>
								<span class="text-sm text-foreground font-medium">{{ $image['style'] }}</span>
							</div>
						@endif
						@if(!empty($image['ratio']))
							<div class="flex justify-between items-center">
								<span class="text-sm text-foreground/50">{{ __('Ratio') }}</span>
								<span class="text-sm text-foreground font-medium">{{ $image['ratio'] }}</span>
							</div>
						@endif
					</div>

					{{-- Tags --}}
					@if(!empty($image['tags']) && count($image['tags']) > 0)
						<div class="pt-4 border-t border-border/50">
							<h4 class="text-sm font-medium text-foreground/70 mb-3">{{ __('Tags') }}</h4>
							<div class="flex flex-wrap gap-2">
								@foreach($image['tags'] as $tag)
									<span class="px-3 py-1.5 bg-foreground/5 text-foreground/70 rounded-lg text-xs font-medium">
										{{ $tag }}
									</span>
								@endforeach
							</div>
						</div>
					@endif
				</div>
			</div>
		</div>
	</div>
@endsection
