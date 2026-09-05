<?php

return [
    'list_marketing_campaigns' => [
        'label'       => 'List Marketing Campaigns',
        'description' => 'Returns the user\'s marketing campaigns with their type, status, and schedule. Filter by channel type or status. Use this first to discover campaign IDs before taking action on a campaign.',
        'action'      => \App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\ListMarketingCampaignsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['telegram', 'whatsapp'],
                    'description' => 'Filter by campaign channel type.',
                ],
                'status' => [
                    'type'        => 'string',
                    'enum'        => ['pending', 'scheduled', 'in_progress', 'published', 'failed', 'running'],
                    'description' => 'Filter by campaign status.',
                ],
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'Maximum number of campaigns to return (default: 10, max: 50).',
                ],
            ],
            'required' => [],
        ],
        'default_config' => [
            'limit'          => 10,
            'store_output_as' => 'marketing_campaigns_list',
        ],
    ],

    'get_marketing_stats' => [
        'label'       => 'Get Marketing Stats',
        'description' => 'Returns aggregated marketing KPIs for a given period: campaign counts by status and type, new contacts, and new conversations. Use this for a quick dashboard-style overview.',
        'action'      => \App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\GetMarketingStatsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'period' => [
                    'type'        => 'string',
                    'enum'        => ['today', 'last_7_days', 'last_30_days'],
                    'description' => 'The time window for the stats (default: today).',
                ],
            ],
            'required' => [],
        ],
        'default_config' => [
            'period'         => 'today',
            'store_output_as' => 'marketing_stats',
        ],
    ],

    'list_marketing_conversations' => [
        'label'       => 'List Marketing Conversations',
        'description' => 'Returns inbox conversations from the marketing bot, with message count and last activity. Optionally filter by channel (telegram or whatsapp).',
        'action'      => \App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\ListMarketingConversationsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'channel' => [
                    'type'        => 'string',
                    'enum'        => ['telegram', 'whatsapp'],
                    'description' => 'Filter conversations by channel type.',
                ],
                'limit' => [
                    'type'        => 'integer',
                    'description' => 'Maximum number of conversations to return (default: 10, max: 50).',
                ],
            ],
            'required' => [],
        ],
        'default_config' => [
            'limit'          => 10,
            'store_output_as' => 'marketing_conversations_list',
        ],
    ],

    'create_marketing_campaign' => [
        'label'       => 'Create Marketing Campaign',
        'description' => 'Creates a new marketing campaign with the given name, content, and channel type (telegram or whatsapp). Optionally schedule it with a scheduled_at timestamp.',
        'action'      => \App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\CreateMarketingCampaignAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'name' => [
                    'type'        => 'string',
                    'description' => 'Human-readable campaign name.',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'The message content to send to recipients.',
                ],
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['telegram', 'whatsapp'],
                    'description' => 'The channel this campaign will be sent through.',
                ],
                'scheduled_at' => [
                    'type'        => 'string',
                    'description' => 'ISO 8601 datetime to schedule the campaign (e.g. "2026-04-20T09:00:00Z"). If provided, status is set to "scheduled". Otherwise "pending".',
                ],
            ],
            'required' => ['name', 'content', 'type'],
        ],
        'default_config' => [
            'store_output_as' => 'created_campaign',
        ],
    ],

    'schedule_marketing_campaign' => [
        'label'       => 'Schedule Marketing Campaign',
        'description' => 'Schedules an existing pending campaign to run. If scheduled_at is omitted the campaign runs at the next cron tick (within ~2 minutes). Use list_marketing_campaigns to find the campaign_id.',
        'action'      => \App\Extensions\AIAgentToolMarketingBot\System\Actions\Tools\ScheduleMarketingCampaignAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'campaign_id' => [
                    'type'        => 'integer',
                    'description' => 'ID of the campaign to schedule.',
                ],
                'scheduled_at' => [
                    'type'        => 'string',
                    'description' => 'ISO 8601 datetime for when to send (e.g. "2026-04-20T09:00:00Z"). Defaults to now.',
                ],
            ],
            'required' => ['campaign_id'],
        ],
        'default_config' => [
            'store_output_as' => 'scheduled_campaign',
        ],
    ],
];
