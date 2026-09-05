@php
	use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
@endphp
<x-card>
	<h4 class="mb-8 flex items-center gap-4">
		<span class="h-px grow bg-border"></span>
		{{ __('Scheduled Posts') }}
		<span class="h-px grow bg-border"></span>
	</h4>

	<div class="flex flex-col gap-7">
		@foreach (SocialMediaPost::query()->where('user_id', auth()->id())->whereIn('status', ['pending', 'scheduled'])->take(4)->get() as $post)
			<article class="group relative flex gap-3 justify-between">
				@php
					$bg_color = '';

					switch ($post?->social_media_platform?->value) {
						case 'instagram':
							$bg_color = 'linear-gradient(90deg, #EB6434 0%, #BB2D9F 54.5%, #BB802D 98%)';
							break;
                        case 'tiktok':
                        case 'x':
							$bg_color = '#343434';
							break;
                        case 'linkedin':
							$bg_color = '#0077B5';
							break;
						case 'facebook':
							$bg_color = '#1877F2';
							break;
					}
				@endphp
				<div class="relative flex">
					<div
						class="relative z-1 inline-grid size-9 shrink-0 place-items-center self-center rounded-full text-white [&_svg]:fill-current"
						style="background: {{ $bg_color }}"
					>
						{!! getSocialMediaIcon($post?->social_media_platform?->value) !!}
					</div>
					@if (!$loop->last)
						<span class="absolute -bottom-full start-1/2 top-1/2 -ms-px w-0.5 bg-border"></span>
					@endif
				</div>

				<p class="m-0 self-center w-full">
					{{ str()->words($post['content'], 15) }}
					<x-tabler-arrow-right
						class="ms-1 inline size-[18px] -translate-x-2 scale-y-50 align-middle opacity-0 transition-all group-hover:translate-x-0 group-hover:scale-y-110 group-hover:opacity-100"
					/>
				</p>

				<div class="self-center whitespace-nowrap text-end text-xs">
					<p class="m-0">
						{{ str()->title($post['status']->value) }}
					</p>
					<p class="m-0 opacity-50">
						{{ $post['created_at']->format('M d, Y') }}
					</p>
				</div>

				<a
					class="absolute inset-0 z-2"
					href="{{ route('dashboard.user.social-media.post.index', ['show' => $post['id']]) }}"
				></a>
			</article>
		@endforeach
	</div>
</x-card>
