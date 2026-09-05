<button
    class="group/btn flex flex-col items-center gap-2 p-1"
    @click="openAddStepModal($el.closest('[data-order]')?.dataset.order ?? steps.length)"
    type="button"
>
    <span class="h-3 w-px bg-foreground/10"></span>

    <span class="inline-grid size-6 place-items-center rounded-button transition group-hover/btn:scale-110 group-hover/btn:bg-primary group-hover/btn:text-primary-foreground">
        <x-tabler-plus class="size-4" />
    </span>

    <span class="h-3 w-px bg-foreground/10"></span>
</button>
