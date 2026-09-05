@php
    $sizes = [
        // Instagram
        'ig_story' => [
            'image' => url('vendor/creative-suite/img/instagram-story.png'),
            'label' => __('Instagram Story/Reels'),
            'width' => 1080,
            'height' => 1920,
            'aspect' => __('Vertical'),
            'featured' => true,
        ],
        'ig_post' => [
            'label' => __('Instagram Post (Square)'),
            'image' => url('vendor/creative-suite/img/instagram-post.png'),
            'width' => 1080,
            'height' => 1080,
            'aspect' => __('Square'),
            'featured' => true,
        ],
        'ig_post_portrait' => [
            'label' => __('Instagram Post (Portrait)'),
            'image' => url('vendor/creative-suite/img/instagram-story.png'),
            'width' => 1080,
            'height' => 1350,
            'aspect' => __('Vertical'),
        ],
        'ig_feed_ad' => [
            'label' => __('Instagram Feed Ad'),
            'image' => url('vendor/creative-suite/img/instagram-post.png'),
            'width' => 1080,
            'height' => 1080,
            'aspect' => __('Square'),
        ],

        // Facebook
        'fb_post' => [
            'label' => __('Facebook Feed Post/Ad'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 1200,
            'height' => 628,
            'aspect' => __('Horizontal'),
        ],
        'fb_story' => [
            'label' => __('Facebook Story'),
            'image' => url('vendor/creative-suite/img/instagram-story.png'),
            'width' => 1080,
            'height' => 1920,
            'aspect' => __('Vertical'),
        ],

        // Twitter (X)
        'tw_header' => [
            'label' => __('Twitter/X Header'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 1500,
            'height' => 500,
            'aspect' => __('Horizontal'),
        ],
        'tw_post' => [
            'label' => __('Twitter/X Post Image'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 1200,
            'height' => 675,
            'aspect' => __('Horizontal'),
        ],

        // YouTube
        'yt_thumb' => [
            'label' => __('YouTube Thumbnail'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 1280,
            'height' => 720,
            'aspect' => __('Horizontal'),
            'featured' => true,
        ],
        'yt_channel' => [
            'label' => __('YouTube Channel Banner'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 2560,
            'height' => 1440,
            'aspect' => __('Horizontal'),
        ],

        // LinkedIn
        'li_feed' => [
            'label' => __('LinkedIn Feed Image'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 1200,
            'height' => 627,
            'aspect' => __('Horizontal'),
        ],
        'li_banner' => [
            'label' => __('LinkedIn Profile Banner'),
            'image' => url('vendor/creative-suite/img/linkedin-profile-banner.png'),
            'width' => 1584,
            'height' => 396,
            'aspect' => __('Horizontal'),
            'featured' => true,
        ],
        'li_story' => [
            'label' => __('LinkedIn Story Ad'),
            'image' => url('vendor/creative-suite/img/instagram-story.png'),
            'width' => 1080,
            'height' => 1920,
            'aspect' => __('Vertical'),
        ],

        // Google Display Ads
        'g_square' => [
            'label' => __('Google Square Ad'),
            'image' => url('vendor/creative-suite/img/instagram-post.png'),
            'width' => 250,
            'height' => 250,
            'aspect' => __('Square'),
        ],
        'g_rectangle' => [
            'label' => __('Google Rectangle Ad'),
            'image' => url('vendor/creative-suite/img/google-rectangle-ad.png'),
            'width' => 336,
            'height' => 280,
            'aspect' => __('Horizontal'),
            'featured' => true,
        ],
        'g_leaderboard' => [
            'label' => __('Google Leaderboard Ad'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 728,
            'height' => 90,
            'aspect' => __('Horizontal'),
        ],
        'g_skyscraper' => [
            'label' => __('Google Skyscraper Ad'),
            'image' => url('vendor/creative-suite/img/instagram-story.png'),
            'width' => 160,
            'height' => 600,
            'aspect' => __('Vertical'),
        ],
        'g_mobile' => [
            'label' => __('Google Mobile Banner'),
            'image' => url('vendor/creative-suite/img/youtube-thumbnail.png'),
            'width' => 320,
            'height' => 100,
            'aspect' => __('Horizontal'),
        ],
    ];
@endphp

<div x-data="{
    templatesList: {{ json_encode(array_values($sizes)) }}
}">
    {{-- Templates Grid --}}
    <div class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-5">
        <template
            x-for="(template, index) in templatesList"
            :key="index"
        >
            <div class="group/item relative">
                <div
                    class="relative mb-1.5 w-full overflow-hidden rounded-lg shadow-md shadow-black/10 transition group-hover/item:scale-105 group-hover/item:shadow-lg group-hover/item:shadow-black/5">
                    <img
                        class="scale-105 transition group-hover/item:scale-100"
                        :src="template.image"
                        :alt="template.label"
                        loading="lazy"
                    >

                    {{-- Hover Overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/item:opacity-100">
                        <div class="flex size-12 items-center justify-center rounded-full bg-white text-black">
                            <x-tabler-plus class="size-6" />
                        </div>
                    </div>
                </div>

                <p
                    class="mb-0.5 w-full truncate text-3xs font-medium opacity-60"
                    x-text="template.aspect"
                ></p>
                <p
                    class="mb-0 text-2xs font-medium text-heading-foreground"
                    x-text="template.label"
                ></p>

                <a
                    class="absolute inset-0 z-1"
                    href="#"
                    @click.prevent="sessionStorage.setItem('pendingBlankTemplate', JSON.stringify({width: template.width, height: template.height, label: template.label}));window.location.href = '/dashboard/user/creative-suite';"
                ></a>
            </div>
        </template>
    </div>
</div>
