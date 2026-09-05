<x-form.group class="flex w-full gap-1">
	<x-form.checkbox
		class="border-input rounded-input border !px-2.5 !py-3 w-full"
		name="chatpro_file_chat_allowed"
		label="{{ __('File Chat Allowed') }}"
		checked="{{ (bool) setting('chatpro_file_chat_allowed', '1') }}"
		tooltip="{{ __('If enabled, users will be able to use the File Chat feature inside AI Chat Pro.') }}"
	/>
</x-form.group>
