<x-button
    class="lqd-image-generator-tabs-trigger py-2 text-2xs font-bold text-heading-foreground hover:shadow-none [&.active]:bg-foreground/10"
    data-generator-name="nano-banana-2"
    tag="button"
    type="button"
    variant="ghost"
    x-data="{}"
    ::class="{ 'active': activeGenerator === 'nano-banana-2' }"
    x-bind:data-active="activeGenerator === 'nano-banana-2'"
    @click="changeActiveGenerator('nano-banana-2')"
>
    @lang('Nano Banana 2')
</x-button>
