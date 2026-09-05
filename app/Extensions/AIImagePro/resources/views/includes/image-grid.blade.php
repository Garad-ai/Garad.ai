<div class="grid grid-cols-2 gap-0.5 md:grid-cols-3 lg:grid-cols-4">
    <template
        x-for="(image, index) in images"
        :key="image.id"
    >
        @include('ai-image-pro::includes.image-grid-item')
    </template>
</div>
