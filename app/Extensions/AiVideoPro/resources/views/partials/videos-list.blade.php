@if (filled($list))
	<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
		@foreach ($list as $entry)
			@include('ai-video-pro::partials.video-item', ['entry' => $entry, 'loop' => $loop, 'selectionGroup' => 'selectedToday'])
		@endforeach
	</div>
@endif
