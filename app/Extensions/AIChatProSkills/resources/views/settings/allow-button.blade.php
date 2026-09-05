<x-form.group class="flex w-full gap-1">
	<x-form.checkbox
		class="border-input rounded-input border !px-2.5 !py-3 w-full"
		name="ai_chat_pro_skills_enabled"
		label="{{ __('Skills') }}"
		checked="{{ (bool) setting('ai_chat_pro_skills_enabled', '1') }}"
		tooltip="{{ __('Allow users to use Skills in AI Chat Pro. Skills are reusable AI instructions that extend chat capabilities.') }}"
	/>
</x-form.group>
