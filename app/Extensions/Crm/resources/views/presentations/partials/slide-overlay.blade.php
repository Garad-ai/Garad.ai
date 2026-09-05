@props(['slideExpr' => 'slide', 'variant' => 'grid'])

@php
    $isLightbox = $variant === 'lightbox';
    $pad = $isLightbox ? 'p-8 sm:p-12' : 'p-3';
    $titleSize = $isLightbox ? 'text-2xl sm:text-4xl' : 'text-sm';
    $subSize = $isLightbox ? 'text-base sm:text-lg' : 'text-[11px]';
    $bulletSize = $isLightbox ? 'text-sm sm:text-lg' : 'text-[11px]';
    $descSize = $isLightbox ? 'text-sm sm:text-base' : 'text-[11px]';
    $gap = $isLightbox ? 'gap-3' : 'gap-1';
@endphp

<div
	data-slide-overlay
	class="pointer-events-none absolute inset-0 flex flex-col {{ $pad }} text-white [text-shadow:0_1px_6px_rgba(0,0,0,.55)] bg-gradient-to-t from-black/80 via-black/35 to-black/10"
	:class="{{ $slideExpr }}.slide_type === 'cover' ? 'items-center justify-center text-center' : 'items-start justify-end'"
>
	<div class="flex flex-col {{ $gap }} w-full">
		<p class="{{ $titleSize }} font-bold leading-tight {{ $isLightbox ? '' : 'line-clamp-2' }}" x-text="{{ $slideExpr }}.title"></p>

		<p
			x-show="{{ $slideExpr }}.content && {{ $slideExpr }}.content.subtitle"
			class="{{ $subSize }} font-medium text-white/90 {{ $isLightbox ? '' : 'line-clamp-1' }}"
			x-text="{{ $slideExpr }}.content?.subtitle"
		></p>

		<p
			x-show="{{ $slideExpr }}.content && {{ $slideExpr }}.content.tagline"
			class="{{ $subSize }} italic text-white/80 {{ $isLightbox ? '' : 'line-clamp-1' }}"
			x-text="{{ $slideExpr }}.content?.tagline"
		></p>

		<div
			x-show="{{ $slideExpr }}.content && {{ $slideExpr }}.content.bullet_points && {{ $slideExpr }}.content.bullet_points.length > 0"
			class="flex flex-col {{ $isLightbox ? 'gap-1.5 mt-1' : 'gap-0.5' }}"
		>
			<template x-for="(point, i) in ({{ $slideExpr }}.content?.bullet_points || []).slice(0, {{ $isLightbox ? 8 : 3 }})" :key="i">
				<p class="{{ $bulletSize }} leading-snug text-white/90 flex gap-1.5 {{ $isLightbox ? '' : 'line-clamp-1' }}">
					<span class="shrink-0">&bull;</span>
					<span x-text="point"></span>
				</p>
			</template>
		</div>

		@if ($isLightbox)
			<p
				x-show="{{ $slideExpr }}.content && {{ $slideExpr }}.content.description"
				class="{{ $descSize }} mt-1 max-w-3xl leading-relaxed text-white/80"
				x-text="{{ $slideExpr }}.content?.description"
			></p>
		@endif
	</div>
</div>
