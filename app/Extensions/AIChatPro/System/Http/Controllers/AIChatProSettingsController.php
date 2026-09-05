<?php

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Services\Common\MenuService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AIChatProSettingsController extends Controller
{
    public function index()
    {
        return view('ai-chat-pro::settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        if (Helper::appIsNotDemo()) {
            $request->validate([
                'ai_chat_display_type'                   => 'required|in:menu,ai_chat,both_fm,frontend',
                'guest_user_daily_message_limit'         => 'required',
                'ai_chat_pro.features.image_generation'  => 'nullable|boolean',
                'guest_user_bottom_text'                 => 'nullable|string|max:255',
                'ai_chat_pro_default_screen'             => 'required|in:new,last,pinned',
                'ai_chat_pro_suggestion_style'           => 'required|in:pill,inline',
                'ai_chat_pro_council_max_models'         => 'nullable|integer|min:2|max:5',
                'ai_chat_pro_council_synthesis_engine'   => 'nullable|string|max:120',
                'ai_chat_pro_council_synthesis_model'    => 'nullable|string|max:255',
                'ai_chat_pro_show_mobile_nav_header'     => 'nullable',
                'ai_chat_pro_show_mobile_bottom_bar'     => 'nullable',
                'ai_chat_pro_smart_image_max_images'     => 'nullable|integer|min:1|max:10',
                'ai_chat_pro_entity_highlight_max'       => 'nullable|integer|min:1|max:10',
                'ai_chat_pro_entity_highlight_threshold' => 'nullable|numeric|min:0.5|max:1.0',
                'ai_chat_pro_typography'                 => 'nullable|json|max:5000',
            ]);

            $suggestions = collect($request->input('input_name'))
                ->zip($request->input('input_prompt'))
                ->map(function ($pair) {
                    return [
                        'name'   => trim($pair[0]),
                        'prompt' => trim($pair[1]),
                    ];
                })
                ->filter(fn ($item) => $item['name'] && $item['prompt']) // Safety net
                ->values()
                ->all();
            $suggestions = json_encode($suggestions, JSON_THROW_ON_ERROR);

            $type = setting('frontend_additional_url_type', 'default');
            $url = setting('frontend_additional_url', '/');
            if (in_array($request->ai_chat_display_type, ['both_fm', 'frontend'])) {
                $url = '/chat';
                $type = 'ai-chat-pro';
            } elseif ($url === '/chat' || $type === 'ai-chat-pro') {
                // Reset to default only if currently pointing to chat
                $url = '/';
                $type = 'default';
            }

            setting([
                'frontend_additional_url'                       => $url,
                'frontend_additional_url_type'                  => $type,
                'ai_chat_display_type'                          => $request->ai_chat_display_type,
                'guest_user_daily_message_limit'                => $request->guest_user_daily_message_limit,
                'ai_chat_pro_suggestions'                       => $suggestions,
                'ai_chat_pro_image_generation_feature'          => $request->boolean('ai_chat_pro_image_generation_feature') ? '1' : '0',
                'ai_chat_pro_canvas'                            => $request->boolean('ai_chat_pro_canvas') ? '1' : '0',
                'chatpro-temp-chat-allowed'                     => $request->boolean('chatpro_temp_chat_allowed') ? '1' : '0',
                'ai_chat_pro_multi_model_feature'               => $request->boolean('ai_chat_pro_multi_model_feature') ? '1' : '0',
                'ai_chat_pro_model_council_feature'             => $request->boolean('ai_chat_pro_model_council_feature') ? '1' : '0',
                'ai_chat_pro_council_max_models'                => max(2, min(5, (int) $request->integer('ai_chat_pro_council_max_models', 5))),
                'ai_chat_pro_council_synthesis_engine'          => $request->string('ai_chat_pro_council_synthesis_engine')->toString(),
                'ai_chat_pro_council_synthesis_model'           => $request->string('ai_chat_pro_council_synthesis_model')->toString(),
                'guest_user_bottom_text'                        => $request->guest_user_bottom_text,
                'chatpro_file_chat_allowed'                     => $request->boolean('chatpro_file_chat_allowed') ? '1' : '0',
                'ai_chat_pro_skills_enabled'                    => $request->boolean('ai_chat_pro_skills_enabled') ? '1' : '0',
                'ai_chat_pro_connectors_enabled'                => $request->boolean('ai_chat_pro_connectors_enabled') ? '1' : '0',
                'ai_chat_pro_connector_gmail_enabled'           => $request->boolean('ai_chat_pro_connector_gmail_enabled') ? '1' : '0',
                'ai_chat_pro_connector_outlook_enabled'         => $request->boolean('ai_chat_pro_connector_outlook_enabled') ? '1' : '0',
                'ai_chat_pro_connector_notion_enabled'          => $request->boolean('ai_chat_pro_connector_notion_enabled') ? '1' : '0',
                'ai_chat_pro_connector_google_drive_enabled'    => $request->boolean('ai_chat_pro_connector_google_drive_enabled') ? '1' : '0',
                'ai_chat_pro_connector_google_calendar_enabled' => $request->boolean('ai_chat_pro_connector_google_calendar_enabled') ? '1' : '0',

                'ai_chat_pro_gmail_client_id'                   => (string) $request->input('ai_chat_pro_gmail_client_id', ''),
                'ai_chat_pro_gmail_client_secret'               => (string) $request->input('ai_chat_pro_gmail_client_secret', ''),
                'ai_chat_pro_gmail_redirect_uri'                => (string) $request->input('ai_chat_pro_gmail_redirect_uri', ''),

                'ai_chat_pro_outlook_client_id'                 => (string) $request->input('ai_chat_pro_outlook_client_id', ''),
                'ai_chat_pro_outlook_client_secret'             => (string) $request->input('ai_chat_pro_outlook_client_secret', ''),
                'ai_chat_pro_outlook_tenant'                    => (string) $request->input('ai_chat_pro_outlook_tenant', ''),
                'ai_chat_pro_outlook_redirect_uri'              => (string) $request->input('ai_chat_pro_outlook_redirect_uri', ''),

                'ai_chat_pro_notion_client_id'                  => (string) $request->input('ai_chat_pro_notion_client_id', ''),
                'ai_chat_pro_notion_client_secret'              => (string) $request->input('ai_chat_pro_notion_client_secret', ''),
                'ai_chat_pro_notion_redirect_uri'               => (string) $request->input('ai_chat_pro_notion_redirect_uri', ''),

                'ai_chat_pro_google_drive_client_id'            => (string) $request->input('ai_chat_pro_google_drive_client_id', ''),
                'ai_chat_pro_google_drive_client_secret'        => (string) $request->input('ai_chat_pro_google_drive_client_secret', ''),
                'ai_chat_pro_google_drive_redirect_uri'         => (string) $request->input('ai_chat_pro_google_drive_redirect_uri', ''),

                'ai_chat_pro_google_calendar_client_id'         => (string) $request->input('ai_chat_pro_google_calendar_client_id', ''),
                'ai_chat_pro_google_calendar_client_secret'     => (string) $request->input('ai_chat_pro_google_calendar_client_secret', ''),
                'ai_chat_pro_google_calendar_redirect_uri'      => (string) $request->input('ai_chat_pro_google_calendar_redirect_uri', ''),
                'deep_research_auto_canvas'                     => $request->boolean('deep_research_auto_canvas') ? '1' : '0',
                'deep_research_default_engine'                  => $request->deep_research_default_engine ?? 'openai',
                'deep_research_openai_model'                    => $request->deep_research_openai_model ?? 'o3-deep-research',
                'ai_chat_pro_smart_image_enabled'               => $request->boolean('ai_chat_pro_smart_image_enabled') ? '1' : '0',
                'ai_chat_pro_smart_image_max_images'            => max(1, min(10, $request->integer('ai_chat_pro_smart_image_max_images', 5))),
                'ai_chat_pro_entity_highlight_enabled'          => $request->boolean('ai_chat_pro_entity_highlight_enabled') ? '1' : '0',
                'ai_chat_pro_entity_highlight_max'              => max(1, min(10, $request->integer('ai_chat_pro_entity_highlight_max', 3))),
                'ai_chat_pro_entity_highlight_threshold'        => max(0.5, min(1.0, (float) $request->input('ai_chat_pro_entity_highlight_threshold', 0.8))),
                'ai_chat_pro_default_screen'                    => $request->ai_chat_pro_default_screen,
                'ai_chat_pro_suggestion_style'                  => $request->ai_chat_pro_suggestion_style,
                'ai_chat_pro_prompt_navigator'                  => $request->boolean('ai_chat_pro_prompt_navigator') ? '1' : '0',
                'ai_chat_pro_show_mobile_nav_header'            => $request->boolean('ai_chat_pro_show_mobile_nav_header') ? '1' : '0',
                'ai_chat_pro_show_mobile_bottom_bar'            => $request->boolean('ai_chat_pro_show_mobile_bottom_bar') ? '1' : '0',
                'ai_chat_pro_typography'                        => $request->input('ai_chat_pro_typography', '{}'),
                'ai_chat_pro_highlight_to_ask_enabled'          => $request->boolean('ai_chat_pro_highlight_to_ask_enabled') ? '1' : '0',
            ])->save();

            app(MenuService::class)->regenerate();
        }

        return back()->with(['message' => __('Updated Successfully'), 'type' => 'success']);
    }
}
