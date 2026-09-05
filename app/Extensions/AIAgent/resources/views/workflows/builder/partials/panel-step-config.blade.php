{{-- Step config dispatcher — routes to sub-partial based on selectedStep.type --}}
<div>
    {{-- AI Call --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-ai')

    {{-- Send Message --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-send-message')

    {{-- Generate Report --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-generate-report')

    {{-- External Chatbot --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-external-chatbot')

    {{-- Marketing Bot --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-marketing-bot')

    {{-- Social Media Agent --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-social-media-agent')

    {{-- CRM Agent --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-crm-agent')

    {{-- Path: condition builder --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-path')

    {{-- GMail --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-gmail')

    {{-- Outlook --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-outlook')

    {{-- Fallback: generic JSON config editor --}}
    @include('ai-agent::workflows.builder.partials.node-configs.node-config-generic')
</div>
