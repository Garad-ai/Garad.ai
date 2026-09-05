<div
    class="lqd-chat-pro-selected-tools-wrap has-no-tools-selected pointer-events-auto flex h-[34px] grow items-center gap-1 overflow-x-auto lg:h-11"
    :class="{ 'has-no-tools-selected': !selectedTools.length, 'has-tools-selected': selectedTools.length }"
    x-cloak
    x-show="selectedTools.length"
    x-transition
>
    <template x-if="selectedTools.includes('canvas')">
        @include('ai-chat-pro::includes.selected-tool-pill', ['tool_name' => 'canvas', 'label' => __('Canvas'), 'icon' => 'tabler-pencil-minus'])
    </template>
    <template x-if="selectedTools.includes('realtime')">
        @include('ai-chat-pro::includes.selected-tool-pill', ['tool_name' => 'realtime', 'label' => __('Web Search'), 'icon' => 'tabler-world'])
    </template>
    <template x-if="selectedTools.includes('deep_research')">
        @include('ai-chat-pro::includes.selected-tool-pill', ['tool_name' => 'deep_research', 'label' => __('Deep Research'), 'icon' => 'tabler-telescope'])
    </template>
    <template x-if="selectedTools.includes('brand_voice')">
        @include('ai-chat-pro::includes.selected-tool-pill', ['tool_name' => 'brand_voice', 'label' => __('Brand Voice'), 'icon' => 'tabler-target-arrow'])
    </template>
    <template x-if="selectedTools.includes('council')">
        @include('ai-chat-pro::includes.selected-tool-pill', ['tool_name' => 'council', 'label' => __('Council'), 'icon' => 'tabler-users-group'])
    </template>
    <template
        x-for="(tool, index) in selectedTools.filter(tool => tool.startsWith('skill-'))"
        :key="tool"
    >
        <div
            class="contents"
            x-data="{ toolName: tool, toolLabel: tool.split('skill-')[1] }"
        >
            @include('ai-chat-pro::includes.selected-tool-pill', ['icon' => 'tabler-tool'])
        </div>
    </template>
    <template
        x-for="(tool, index) in selectedTools.filter(tool => tool.startsWith('entity-follow-up-'))"
        :key="tool"
    >
        <div
            class="contents"
            x-data="{ toolName: tool, toolLabel: tool.split('entity-follow-up-')[1] }"
        >
            @include('ai-chat-pro::includes.selected-tool-pill', ['icon' => 'tabler-arrow-forward-up'])
        </div>
    </template>
    <template
        x-for="(tool, index) in selectedTools.filter(tool => tool.startsWith('follow-up-'))"
        :key="tool"
    >
        <div
            class="contents"
            x-data="{ toolName: tool, toolLabel: tool.split('follow-up-')[1] }"
        >
            @include('ai-chat-pro::includes.selected-tool-pill', ['icon' => 'tabler-arrow-forward-up'])
        </div>
    </template>
</div>
