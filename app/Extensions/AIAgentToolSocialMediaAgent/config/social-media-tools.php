<?php

return [
    'list_social_media_agents' => [
        'label'       => 'List Social Media Agents',
        'description' => 'Returns all of the user\'s Social Media Agents with their configuration, active status, and average engagement metrics. Call this first to discover available agent IDs before using other social media tools.',
        'action'      => \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\ListSocialMediaAgentsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [],
            'required'   => [],
        ],
        'default_config' => [
            'store_output_as' => 'social_agents_list',
        ],
    ],

    'list_social_posts' => [
        'label'       => 'List Social Posts',
        'description' => 'Returns a paginated list of social media posts for the user. Filter by agent, status (draft, scheduled, published, failed), and limit. Use this to browse the post pipeline.',
        'action'      => \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\ListSocialPostsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'agent_id' => [
                    'type'        => 'integer',
                    'description' => 'Filter posts by a specific agent ID. Omit to return posts from all agents.',
                ],
                'status' => [
                    'type'        => 'string',
                    'enum'        => ['draft', 'pending_approval', 'approved', 'scheduled', 'published', 'failed'],
                    'description' => 'Filter posts by status.',
                ],
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'Maximum number of posts to return (default: 10, max: 50).',
                ],
            ],
            'required' => [],
        ],
        'default_config' => [
            'limit'           => 10,
            'store_output_as' => 'social_posts_list',
        ],
    ],

    'create_social_post' => [
        'label'       => 'Create Social Post',
        'description' => 'Creates a new draft social media post for a given agent. Optionally schedule it by providing a scheduled_at timestamp. Use list_social_media_agents to find the agent_id.',
        'action'      => \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\CreateSocialPostAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'agent_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the Social Media Agent that owns this post.',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'The full text content of the post.',
                ],
                'post_type' => [
                    'type'        => 'string',
                    'enum'        => ['text', 'single_image', 'carousel', 'video'],
                    'description' => 'Post format type. Defaults to "text".',
                ],
                'platform_id' => [
                    'type'        => 'integer',
                    'description' => 'Target platform ID. Defaults to the first platform configured on the agent.',
                ],
                'scheduled_at' => [
                    'type'        => 'string',
                    'description' => 'ISO 8601 datetime to schedule the post (e.g. "2026-04-15T09:00:00Z"). If provided, the post status is set to "scheduled".',
                ],
            ],
            'required' => ['agent_id', 'content'],
        ],
        'default_config' => [
            'store_output_as' => 'created_post',
        ],
    ],

    'get_social_post_analytics' => [
        'label'       => 'Get Social Post Analytics',
        'description' => 'Returns detailed engagement analytics for a specific Social Media Agent: engagement rate trends, platform breakdown, top/lowest performing posts, and recent post performance. Use list_social_media_agents to find the agent_id.',
        'action'      => \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\GetSocialPostAnalyticsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'agent_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the Social Media Agent to analyse.',
                ],
                'lookback_days' => [
                    'type'        => 'integer',
                    'description' => 'Number of days to look back for the "recent" period (default: 14). The same window is used to compare against the prior period.',
                ],
            ],
            'required' => ['agent_id'],
        ],
        'default_config' => [
            'lookback_days'   => 14,
            'store_output_as' => 'social_analytics',
        ],
    ],

    'create_social_media_agent' => [
        'label'       => 'Create Social Media Agent',
        'description' => 'Creates a new Social Media Agent for the user with the specified name, tone, language, and post settings.',
        'action'      => \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\CreateSocialMediaAgentAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'Display name of the agent.',
                ],
                'tone' => [
                    'type'        => 'string',
                    'description' => 'Writing tone (e.g. professional, casual, humorous, inspirational).',
                ],
                'language' => [
                    'type'        => 'string',
                    'description' => 'Language code (e.g. en, tr, de).',
                ],
                'post_types' => [
                    'type'        => 'array',
                    'description' => 'List of post formats: text, single_image, carousel, video.',
                ],
                'plan_type' => [
                    'type'        => 'string',
                    'enum'        => ['daily', 'weekly', 'monthly'],
                    'description' => 'Posting frequency plan.',
                ],
                'daily_post_count' => [
                    'type'        => 'integer',
                    'description' => 'Number of posts per day (1–10). Default: 1.',
                ],
                'hashtag_count' => [
                    'type'        => 'integer',
                    'description' => 'Number of hashtags to include (0–30). Default: 5.',
                ],
                'site_url' => [
                    'type'        => 'string',
                    'description' => 'Optional website URL for brand context.',
                ],
                'site_description' => [
                    'type'        => 'string',
                    'description' => 'Optional description of the brand or website.',
                ],
            ],
            'required' => ['name', 'tone', 'language'],
        ],
        'default_config' => [
            'plan_type'        => 'daily',
            'daily_post_count' => 1,
            'hashtag_count'    => 5,
            'store_output_as'  => 'created_agent',
        ],
    ],

    'approve_social_post' => [
        'label'       => 'Approve Social Post',
        'description' => 'Approves a draft social media post, marking it ready for scheduling or publishing. Use list_social_posts to find draft post IDs.',
        'action'      => \App\Extensions\AIAgentToolSocialMediaAgent\System\Actions\Tools\ApproveSocialPostAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the draft post to approve.',
                ],
            ],
            'required' => ['post_id'],
        ],
        'default_config' => [
            'store_output_as' => 'approved_post',
        ],
    ],
];
