@if ($tempChat && setting('chatpro-temp-chat-allowed', 1) == 1)
	<p class="mx-auto text-balance px-5 text-center lg:w-4/5" id="temp-chat-note">
		<strong>
			{{ __('Temporary Chat Enabled') }}:
		</strong>
		{{ __("This conversation won't be saved to your chat history, but any content you upload may still appear in the content manager.") }}
	</p>
@endif
