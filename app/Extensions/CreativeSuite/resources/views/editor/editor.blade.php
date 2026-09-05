<div
    class="lqd-cs-editor pointer-events-none invisible fixed inset-0 overflow-hidden bg-background opacity-0 transition-all before:pointer-events-none before:absolute before:inset-0 before:z-0 before:bg-black/10"
    :class="{ '!visible !opacity-100 pointer-events-auto': currentView === 'editor' }"
    style="background-image: radial-gradient(hsl(var(--foreground)/20%) 0.75px, transparent 0px); background-size: 12px 12px; background-position: center;"
>
    @include('creative-suite::editor.includes.toolbar')
    @include('creative-suite::editor.includes.canvas.canvas')
    @include('creative-suite::editor.includes.propsbar')
    @includeIf('creative-suite-annotations::editor.includes.annotations')
</div>
