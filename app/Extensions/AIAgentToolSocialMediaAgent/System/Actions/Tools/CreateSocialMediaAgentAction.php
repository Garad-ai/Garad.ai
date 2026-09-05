<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\SocialMediaAgent\System\Models\SocialMediaAgent;
use Exception;

class CreateSocialMediaAgentAction implements ActionInterface
{
    /**
     * Config keys:
     *   - name (string)             : Agent name (required)
     *   - tone (string)             : Writing tone, e.g. professional, casual (required)
     *   - language (string)         : Language code, e.g. en, tr (required)
     *   - platform_ids (array)      : IDs of connected SocialMediaPlatform records (required)
     *   - post_types (array|string) : Post formats, e.g. ['text', 'single_image'] (default: ['text'])
     *   - plan_type (string)        : daily | weekly | monthly (default: daily)
     *   - daily_post_count (int)    : Posts per day (default: 1)
     *   - hashtag_count (int)       : Number of hashtags (default: 5)
     *   - site_url (string)         : Optional website URL for context
     *   - site_description (string) : Optional description of the brand/site
     *   - store_output_as (string)  : Context key for the result (default: created_agent)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        if (empty($config['name'])) {
            throw new Exception('CreateSocialMediaAgentAction: name is required.');
        }
        if (empty($config['tone'])) {
            throw new Exception('CreateSocialMediaAgentAction: tone is required.');
        }
        if (empty($config['language'])) {
            throw new Exception('CreateSocialMediaAgentAction: language is required.');
        }
        if (empty($config['platform_ids'])) {
            throw new Exception('CreateSocialMediaAgentAction: at least one social media account (platform_id) is required.');
        }

        $storeOutputAs = $config['store_output_as'] ?? 'created_agent';

        $postTypes = $config['post_types'] ?? ['text'];
        if (is_string($postTypes)) {
            $postTypes = array_map('trim', explode(',', $postTypes));
        }

        $platformIds = $config['platform_ids'] ?? [];
        if (is_string($platformIds)) {
            $platformIds = array_map('intval', array_map('trim', explode(',', $platformIds)));
        }

        $agent = SocialMediaAgent::query()->create([
            'user_id'          => $workflow->user_id,
            'name'             => $config['name'],
            'tone'             => $config['tone'],
            'language'         => $config['language'],
            'post_types'       => $postTypes,
            'platform_ids'     => $platformIds,
            'plan_type'        => $config['plan_type'] ?? 'daily',
            'daily_post_count' => (int) ($config['daily_post_count'] ?? 1),
            'hashtag_count'    => (int) ($config['hashtag_count'] ?? 5),
            'site_url'         => $config['site_url'] ?? null,
            'site_description' => $config['site_description'] ?? null,
            'is_active'        => true,
        ]);

        return array_merge($context, [
            $storeOutputAs => [
                'id'       => $agent->id,
                'name'     => $agent->name,
                'tone'     => $agent->tone,
                'language' => $agent->language,
            ],
        ]);
    }
}
