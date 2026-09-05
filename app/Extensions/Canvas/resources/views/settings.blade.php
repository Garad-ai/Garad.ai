<x-form.group class="flex w-full gap-1">
	<x-form.checkbox
		class="border-input rounded-input border !px-2.5 !py-3 w-full"
		name="ai_chat_pro_canvas"
		label="{{ __('Canvas Enabled') }}"
		checked="{{ (bool) setting('ai_chat_pro_canvas', '1') }}"
		tooltip="{{ __('AI Chat Pro enable Canvas.') }}"
	/>
</x-form.group>
