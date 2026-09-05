<div
    class="lqd-social-media-post-content w-full"
    id="lqd-social-media-post-content"
>
    @if (isset($post))
        @include('social-media::components.post.post-content', [
            'post' => $post,
            'prev_post_id' => $post->prev_post_id ?? null,
            'next_post_id' => $next_post_id ?? null,
        ])
    @else
        <h3>
            @lang('404 Not Found')
        </h3>
    @endif
</div>
