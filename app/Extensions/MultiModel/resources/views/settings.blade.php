<x-form.checkbox
	class="border-input rounded-input border !px-2.5 !py-3"
	name="ai_chat_pro_multi_model_feature"
	label="{{ __('Multi Model Selection') }}"
	checked="{{ (bool) setting('ai_chat_pro_multi_model_feature', '1') }}"
	tooltip="{{ __('AI Chat Pro can now select different AI models for generating responses. Choose the model that best fits your needs!') }}"
/>
