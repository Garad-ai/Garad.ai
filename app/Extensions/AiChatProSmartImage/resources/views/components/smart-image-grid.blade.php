@props(['images' => []])

@php
    $displayImages = array_slice($images, 0, 3);
    $totalCount = count($images);
    $hasMore = $totalCount > 3;
@endphp

@if (count($displayImages) > 0)
    <div
        class="lqd-smart-image-grid relative mb-3 grid cursor-pointer grid-cols-3 gap-2"
        data-smart-images='@json($images)'
        onclick="
            var images = JSON.parse(this.dataset.smartImages);
            var item = event.target.closest('.lqd-smart-image-item');
            var index = item ? parseInt(item.dataset.index) : 0;
            var urls = images.map(function(img) { return { url: img.imageUrl || img.thumbnailUrl, title: img.title || '', source: img.source || '', domain: img.domain || '', link: img.link || '' }; });
            if (typeof openSmartImageLightbox === 'function') {
                event.preventDefault();
                openSmartImageLightbox(urls, index);
            }
        "
    >
        @foreach ($displayImages as $index => $image)
            <div
                class="lqd-smart-image-item lqd-shimmer-effect aspect-[4/3] overflow-hidden rounded-[10px] bg-foreground/5"
                data-index="{{ $index }}"
            >
                <img
                    class="size-full object-cover transition-transform duration-300 hover:scale-105"
                    src="{{ $image['thumbnailUrl'] ?? $image['imageUrl'] }}"
                    alt="{{ $image['title'] ?? '' }}"
                    loading="lazy"
                    onload="this.parentElement.classList.remove('lqd-shimmer-effect')"
                    onerror="this.parentElement.classList.remove('lqd-shimmer-effect')"
                />
            </div>
            @if ($hasMore)
                <span
                    class="absolute bottom-2.5 end-2.5 inline-flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-2 text-2xs font-medium leading-none text-white backdrop-blur-sm"
                >
                    <svg
                        class="2.5"
                        width="12"
                        height="12"
                        viewBox="0 0 12 12"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M11 4H1C0.734784 4 0.48043 4.10536 0.292893 4.29289C0.105357 4.48043 0 4.73478 0 5V11C0 11.2652 0.105357 11.5196 0.292893 11.7071C0.48043 11.8946 0.734784 12 1 12H11C11.2652 12 11.5196 11.8946 11.7071 11.7071C11.8946 11.5196 12 11.2652 12 11V5C12 4.73478 11.8946 4.48043 11.7071 4.29289C11.5196 4.10536 11.2652 4 11 4ZM11 11H1V5H11V11ZM1 2.5C1 2.36739 1.05268 2.24021 1.14645 2.14645C1.24021 2.05268 1.36739 2 1.5 2H10.5C10.6326 2 10.7598 2.05268 10.8536 2.14645C10.9473 2.24021 11 2.36739 11 2.5C11 2.63261 10.9473 2.75979 10.8536 2.85355C10.7598 2.94732 10.6326 3 10.5 3H1.5C1.36739 3 1.24021 2.94732 1.14645 2.85355C1.05268 2.75979 1 2.63261 1 2.5ZM2 0.5C2 0.367392 2.05268 0.240215 2.14645 0.146447C2.24021 0.0526784 2.36739 0 2.5 0H9.5C9.63261 0 9.75979 0.0526784 9.85355 0.146447C9.94732 0.240215 10 0.367392 10 0.5C10 0.632608 9.94732 0.759785 9.85355 0.853553C9.75979 0.947321 9.63261 1 9.5 1H2.5C2.36739 1 2.24021 0.947321 2.14645 0.853553C2.05268 0.759785 2 0.632608 2 0.5Z"
                        />
                    </svg>
                    {{ $totalCount }}
                </span>
            @endif
        @endforeach
    </div>
@endif
