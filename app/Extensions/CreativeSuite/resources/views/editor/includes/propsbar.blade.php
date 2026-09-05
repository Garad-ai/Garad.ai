<div
    class="lqd-cs-props-bar fixed start-1/2 top-[calc(var(--header-h)+10px)] z-10 flex -translate-x-1/2 gap-2.5 rounded-xl bg-background/90 p-2 shadow-lg shadow-black/15 backdrop-blur-md max-lg:end-0 max-lg:start-0 max-lg:top-[--header-h] max-lg:w-full max-lg:translate-x-0 max-lg:overflow-x-auto max-lg:rounded-t-none"
    x-ref="propsbar"
    x-cloak
    x-show="selectedNodes.length"
    x-data="{
        _activeDropdown: null,
        aiEditMode: false,
        get activeDropdown() {
            return this._activeDropdown;
        },
        set activeDropdown(dropdown) {
            this._activeDropdown = dropdown;
            this.$refs.propsbar.classList.toggle('max-lg:overflow-x-auto', this._activeDropdown == null)
        }
    }"
    @ai-edit-mode.window="aiEditMode = $event.detail.active"
>
    @include('creative-suite::editor.includes.propsbar-items.ai')
    <template x-if="!aiEditMode">
        <div class="contents">
            @include('creative-suite::editor.includes.propsbar-items.typography')
            @include('creative-suite::editor.includes.propsbar-items.fill')
        </div>
    </template>
</div>
