<x-forms.input
    class="h-11 shrink-0 rounded-none !border-x-0 !border-t-0 bg-transparent px-4 !text-[11px] font-semibold uppercase tracking-wide text-foreground/70 focus:border-input-border focus:ring-0 xl:px-6"
    name="channel_filter"
    size="lg"
    type="select"
    x-model="selectedChannelId"
    @change.prevent="loadConversations()"
>
    <option
        class="bg-background text-foreground"
        value=""
    >
        {{ __('All Channels') }}
    </option>
    @foreach ($channels as $channel)
        <option
            class="bg-background text-foreground"
            value="{{ $channel->id }}"
        >
            {{ $channel->name }} ({{ $channel->type->value }})
        </option>
    @endforeach
</x-forms.input>
