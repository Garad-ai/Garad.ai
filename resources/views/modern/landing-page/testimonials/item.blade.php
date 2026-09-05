<div class="mb-12 mt-10 w-full shrink-0 grow-0 basis-auto px-4 text-sm/[1.466em] text-heading-foreground lg:w-[28%]">
    <blockquote class="rounded-2xl bg-background font-normal shadow-[0px_10px_40px_0px_#00000014]">
        <div class="border-b p-8">
            <p class="m-0">
                {!! __('“' . $item->words . '”') !!}
            </p>
        </div>
        <div class="flex items-center gap-4 px-8 py-4">
            <img
                class="size-[52px] rounded-full object-cover object-center"
                src="{{ url('') . isset($item->avatar) ? (str_starts_with($item->avatar, 'asset') ? custom_theme_url($item->avatar) : '/testimonialAvatar/' . $item->avatar) : custom_theme_url('assets/img/auth/default-avatar.png') }}"
                alt="{{ __($item->full_name) }}"
            >
            <div>
                <p class="mb-2 text-[12px] font-semibold uppercase leading-tight tracking-widest text-black">
                    {!! __($item->full_name) !!}
                </p>
                <p class="m-0 text-[12px] font-semibold uppercase leading-tight tracking-widest text-black/30">
                    {!! __($item->job_title) !!}
                </p>
            </div>
        </div>
    </blockquote>
</div>
