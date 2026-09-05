<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>{{ $presentation->title }}</title>
	<style>
		@page {
			margin: 0;
		}

		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: 'DejaVu Sans', sans-serif;
		}

		.slide {
			position: relative;
			width: 297mm;
			height: 210mm;
			overflow: hidden;
			page-break-after: always;
			background-color: #0b1220;
		}

		.slide:last-child {
			page-break-after: auto;
		}

		.slide-image {
			position: absolute;
			top: 0;
			left: 0;
			width: 297mm;
			height: 210mm;
		}

		.overlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 297mm;
			height: 210mm;
			color: #ffffff;
		}

		.overlay-inner {
			position: absolute;
			left: 0;
			width: 297mm;
			padding: 18mm;
		}

		.overlay-bottom .overlay-inner {
			bottom: 0;
			background-color: rgba(0, 0, 0, 0.55);
			text-align: left;
		}

		.overlay-center .overlay-inner {
			top: 55mm;
			text-align: center;
		}

		.slide-title {
			font-size: 30pt;
			font-weight: bold;
			line-height: 1.15;
			margin-bottom: 6mm;
		}

		.slide-subtitle {
			font-size: 15pt;
			font-weight: bold;
			color: rgba(255, 255, 255, 0.92);
			margin-bottom: 3mm;
		}

		.slide-tagline {
			font-size: 14pt;
			font-style: italic;
			color: rgba(255, 255, 255, 0.85);
			margin-bottom: 4mm;
		}

		.slide-bullets {
			list-style: none;
		}

		.slide-bullets li {
			font-size: 13pt;
			line-height: 1.5;
			color: rgba(255, 255, 255, 0.92);
			margin-bottom: 2mm;
		}

		.slide-bullets li .dot {
			display: inline;
		}

		.slide-description {
			font-size: 12pt;
			line-height: 1.6;
			color: rgba(255, 255, 255, 0.82);
			margin-top: 4mm;
		}
	</style>
</head>
<body>
	@foreach ($slides as $slide)
		@php
			$content = is_array($slide['content'] ?? null) ? $slide['content'] : [];
			$bullets = is_array($content['bullet_points'] ?? null) ? $content['bullet_points'] : [];
			$isCover = ($slide['slide_type'] ?? null) === 'cover';
		@endphp
		<div class="slide">
			@if (! empty($slide['image']))
				<img class="slide-image" src="{{ $slide['image'] }}" alt="">
			@endif

			<div class="overlay {{ $isCover ? 'overlay-center' : 'overlay-bottom' }}">
				<div class="overlay-inner">
					<div class="slide-title">{{ $slide['title'] }}</div>

					@if (! empty($content['subtitle']))
						<div class="slide-subtitle">{{ $content['subtitle'] }}</div>
					@endif

					@if (! empty($content['tagline']))
						<div class="slide-tagline">{{ $content['tagline'] }}</div>
					@endif

					@if (count($bullets) > 0)
						<ul class="slide-bullets">
							@foreach (array_slice($bullets, 0, 8) as $point)
								<li><span class="dot">&bull;</span> {{ $point }}</li>
							@endforeach
						</ul>
					@endif

					@if (! empty($content['description']))
						<div class="slide-description">{{ $content['description'] }}</div>
					@endif
				</div>
			</div>
		</div>
	@endforeach
</body>
</html>
