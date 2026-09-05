@php
    $is_vertical = empty($mega_menu_item['parent_id']);
@endphp

<div class="lqd-megamenu-divider flex min-w-full lg:min-w-px">
    <div @class([
        'lqd-megamenu-divider-inner',
        'h-px w-full border-t lg:h-full lg:w-px lg:border-s lg:border-t-0' => $is_vertical,
        'w-full h-px border-t my-6' => !$is_vertical,
    ])></div>
</div>
