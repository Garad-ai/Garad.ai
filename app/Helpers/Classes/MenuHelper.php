<?php

namespace App\Helpers\Classes;

use App\Extensions\Chatbot\System\Helpers\ChatbotHelper;
use App\Models\OpenAIGenerator;
use Illuminate\Support\Facades\Auth;

class MenuHelper
{
    public $userPlan = null;

    public $generators = null;

    public $freeOpenAiItems = null;

    public $user = null;

    public array $routesDoesNotHaveAnySlug = [
        'ai_editor',
        'ai_writer',
        'ai_chat_all',
        'ai_chat_all',
        'ai_plagiarism',
        'ai_content_detect',
        'ai_article_wizard_generator',
        'ai_webchat',
        'ai_rewriter',
        'seo',
        'brand_voice',
        'support',
        'photo_studio_extension',
        'ai_social_media_extension',
        'ai_social_media_extension',
        'ext_chat_bot',
    ];

    public array $slugsNotInOpenAiGenerator = [
        'ai_chat_all',
        'ai_editor',
        'ai_writer',
        'ai_social_media_extension',
        'ext_chat_bot',
        'brand_voice',
        'photo_studio_extension',
        'ai_vision',
        'ai_product_shot',
        'ai_image_generator',
        'ai_video',
        'seo_tool_extension',
        'ai_voiceover',
        'ai_pdf',
        'ai_speech_to_text',
        'ai_rewriter',
        'ai_code_generator',
        'ai_youtube',
        'ai_chat_image',
        'ai_rss',
        'ai_voiceover_clone',
        'ai_web_chat_extension',
        'ai_realtime_voice_chat',
        'ai_detector_extension',
        'ai_plagiarism_extension',
        'ai_article_wizard',
        'ai_voice_isolator',
        'ext_voice_chatbot',
        'ext_social_media_dropdown',
        'ai_influencer',
        'creative_suite',
        'url_to_video',
        'viral_clips',
        'influencer_avatar',
        'brand_voice',
        'support',
    ];

    public function __construct()
    {
        $this->generators = OpenAIGenerator::query()
            ->whereIn('slug', $this->routesDoesNotHaveAnySlug)
            ->where('active', 1)
            ->get();

        $this->freeOpenAiItems = Helper::setting('free_open_ai_items');

        $this->user = Auth::user();

        // Team member uses team manager plan
        if ($this->user?->getAttribute('team_manager_id')) {
            $this->user = $this->user?->getAttribute('teamManager');
        }

        $this->userPlan = $this->user?->relationPlan;
    }

    public function checker(array $menu): bool
    {
        $slug = (string) data_get($menu, 'key', '');

        // Admin always sees everything
        if (Auth::user()?->isAdmin()) {
            return true;
        }

        // Never show admin-only items to normal users
        if (data_get($menu, 'is_admin', false)) {
            return false;
        }

        $plan = $this->userPlan;

        /*
         * Human Agent:
         * user must have Chatbot access
         * AND Human Agent permission.
         */
        if ($slug === 'ext_chat_bot_agent') {
            return (bool) $plan
                && PlanHelper::planMenuCheck($plan, 'ext_chat_bot')
                && ChatbotHelper::planAllowsHumanAgent($plan);
        }

        // Respect global feature settings
        $setting = $this->settingSlug($slug);

        if ($setting['status']) {
            $enabled = Helper::setting($setting['setting']);

            if ((int) $enabled === 0) {
                return false;
            }
        }

        /*
         * ONE SOURCE OF TRUTH:
         * Dashboard / Chat Pro / Documents
         * Exact plan features
         * Child menus inherited from plan parent
         */
        return PlanHelper::planMenuCheck($plan, $slug);
    }

    public function settingSlug($slug): array
    {
        $data = [
            'ai_article_wizard_generator' => 'feature_ai_article_wizard',
            'ai_writer'                   => 'feature_ai_writer',
            'ai_rewriter'                 => 'feature_ai_rewriter',
            'ai_chat_image'               => 'feature_ai_chat_image',
            'ai_image_generator'          => 'feature_ai_image',
            'ai_code_generator'           => 'feature_ai_code',
            'ai_speech_to_text'           => 'feature_ai_speech_to_text',
            'ai_voiceover'                => 'feature_ai_voiceover',
            'ai_vision'                   => 'feature_ai_vision',
            'ai_pdf'                      => 'feature_ai_pdf',
            'ai_youtube'                  => 'feature_ai_youtube',
            'ai_rss'                      => 'feature_ai_youtube',
        ];

        if (array_key_exists($slug, $data)) {
            return [
                'status'  => true,
                'setting' => $data[$slug],
            ];
        }

        return [
            'status' => false,
        ];
    }
}