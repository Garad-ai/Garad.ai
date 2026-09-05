<x-form.group class="flex w-full gap-1">
	<x-form.checkbox
		class="border-input rounded-input border !px-2.5 !py-3"
		name="chatpro_temp_chat_allowed"
		label="{{ __('Temporary Chat Allowed') }}"
		checked="{{ (bool) setting('chatpro-temp-chat-allowed', '1') }}"
		tooltip="{{ __('Allow temporary chat for AI Chat Pro users.') }}"
	/>
</x-form.group>
