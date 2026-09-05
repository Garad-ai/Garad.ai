{{--
    Recursively renders a JS value (object, array, or primitive) using Alpine templates.

    @param string $expr  JS expression resolving to the value to render (e.g. "step.args", "entry[1]")
    @param int $depth    Current recursion depth (omit on first call)
--}}
@php
    $depth = $depth ?? 0;
    $maxDepth = 6;
@endphp

@if ($depth >= $maxDepth)
    <span class="text-foreground/40">…</span>
@else
    {{-- null / undefined --}}
    <template x-if="({{ $expr }}) === null || ({{ $expr }}) === undefined">
        <span class="text-foreground/40">—</span>
    </template>

    {{-- Empty array --}}
    <template x-if="Array.isArray({{ $expr }}) && ({{ $expr }}).length === 0">
        <span class="text-foreground/40">[]</span>
    </template>

    {{-- Non-empty array --}}
    <template x-if="Array.isArray({{ $expr }}) && ({{ $expr }}).length > 0">
        <ul class="m-0 list-disc">
            <template
                x-for="(item, idx) in {{ $expr }}"
                :key="idx"
            >
                <li>
                    @include('ai-agent::workflows.builder.partials.render-arg-value', ['expr' => 'item', 'depth' => $depth + 1])
                </li>
            </template>
        </ul>
    </template>

    {{-- Empty object --}}
    <template
        x-if="({{ $expr }}) !== null && typeof ({{ $expr }}) === 'object' && !Array.isArray({{ $expr }}) && Object.keys({{ $expr }}).length === 0"
    >
        <span class="text-foreground/40">{}</span>
    </template>

    {{-- Non-empty object --}}
    <template
        x-if="({{ $expr }}) !== null && typeof ({{ $expr }}) === 'object' && !Array.isArray({{ $expr }}) && Object.keys({{ $expr }}).length > 0"
    >
        <ul class="m-0 list-disc">
            <template
                x-for="entry in Object.entries({{ $expr }})"
                :key="entry[0]"
            >
                <li>
                    <span
                        class="font-medium text-foreground/80"
                        x-text="entry[0] + ':'"
                    ></span>
                    @include('ai-agent::workflows.builder.partials.render-arg-value', ['expr' => 'entry[1]', 'depth' => $depth + 1])
                </li>
            </template>
        </ul>
    </template>

    {{-- Primitive (string, number, boolean) — JSON.stringify quotes strings and leaves numbers/booleans bare --}}
    <template x-if="({{ $expr }}) !== null && ({{ $expr }}) !== undefined && typeof ({{ $expr }}) !== 'object'">
        <span
            class="whitespace-pre-wrap break-words text-foreground/70"
            x-text="JSON.stringify({{ $expr }}).replaceAll('\\n', '\n')"
        ></span>
    </template>
@endif
