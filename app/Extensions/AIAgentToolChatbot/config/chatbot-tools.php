<?php

declare(strict_types=1);

return [
    'list_chatbots' => [
        'label'          => 'List Chatbots',
        'description'    => 'List chatbots belonging to the workflow user, with optional active-only filter.',
        'action'         => \App\Extensions\AIAgentToolChatbot\System\Actions\Tools\ListChatbotsAction::class,
        'parameters'     => [
            'type'       => 'object',
            'properties' => [
                'active_only'     => ['type' => 'boolean', 'description' => 'Return only active chatbots.'],
                'limit'           => ['type' => 'integer', 'description' => 'Max results (default 10, max 50).'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ],
        'default_config' => [
            'active_only'     => false,
            'limit'           => 10,
            'store_output_as' => 'chatbots_list',
        ],
    ],

    'get_chatbot_analytics' => [
        'label'          => 'Get Chatbot Analytics',
        'description'    => 'Get KPI stats for one or all chatbots: ratings, response times, top channels.',
        'action'         => \App\Extensions\AIAgentToolChatbot\System\Actions\Tools\GetChatbotAnalyticsAction::class,
        'parameters'     => [
            'type'       => 'object',
            'properties' => [
                'chatbot_id'      => ['type' => 'integer', 'description' => 'Scope analytics to a single chatbot ID (omit for all).'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ],
        'default_config' => [
            'store_output_as' => 'chatbot_analytics',
        ],
    ],

    'list_chatbot_conversations' => [
        'label'          => 'List Chatbot Conversations',
        'description'    => 'List conversations across chatbots with optional filters for chatbot, channel, and ticket status.',
        'action'         => \App\Extensions\AIAgentToolChatbot\System\Actions\Tools\ListChatbotConversationsAction::class,
        'parameters'     => [
            'type'       => 'object',
            'properties' => [
                'chatbot_id'      => ['type' => 'integer', 'description' => 'Filter by a specific chatbot ID.'],
                'channel'         => ['type' => 'string', 'description' => 'Filter by channel (e.g. whatsapp, telegram, livechat).'],
                'ticket_status'   => ['type' => 'string', 'enum' => ['new', 'closed'], 'description' => 'Filter by ticket status.'],
                'limit'           => ['type' => 'integer', 'description' => 'Max results (default 10, max 50).'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ],
        'default_config' => [
            'limit'           => 10,
            'store_output_as' => 'chatbot_conversations_list',
        ],
    ],

    'get_chatbot_conversation' => [
        'label'          => 'Get Chatbot Conversation',
        'description'    => 'Retrieve the full message history of a specific chatbot conversation.',
        'action'         => \App\Extensions\AIAgentToolChatbot\System\Actions\Tools\GetChatbotConversationAction::class,
        'parameters'     => [
            'type'       => 'object',
            'required'   => ['conversation_id'],
            'properties' => [
                'conversation_id' => ['type' => 'integer', 'description' => 'ID of the conversation to fetch.'],
                'output_format'   => ['type' => 'string', 'enum' => ['json', 'plain'], 'description' => 'json (default) returns structured data; plain returns messages as readable text.'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ],
        'default_config' => [
            'output_format'   => 'json',
            'store_output_as' => 'chatbot_conversation',
        ],
    ],

    'get_chatbot_conversations' => [
        'label'          => 'Get Chatbot Conversations',
        'description'    => 'Fetch the most recent conversations with their full message history, optionally filtered by message sender.',
        'action'         => \App\Extensions\AIAgentToolChatbot\System\Actions\Tools\GetChatbotConversationsAction::class,
        'parameters'     => [
            'type'       => 'object',
            'properties' => [
                'limit'           => ['type' => 'integer', 'description' => 'Number of recent conversations to fetch (default 5, max 50).'],
                'message_sender'  => ['type' => 'string', 'enum' => ['user', 'admin', 'all'], 'description' => 'Filter messages by sender: user, admin, or all (default: all).'],
                'output_format'   => ['type' => 'string', 'enum' => ['json', 'plain'], 'description' => 'json (default) returns structured data; plain returns messages as readable text.'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ],
        'default_config' => [
            'limit'           => 5,
            'message_sender'  => 'all',
            'output_format'   => 'json',
            'store_output_as' => 'chatbot_conversations',
        ],
    ],

    'close_chatbot_ticket' => [
        'label'          => 'Close Chatbot Ticket',
        'description'    => 'Mark a chatbot conversation ticket as closed.',
        'action'         => \App\Extensions\AIAgentToolChatbot\System\Actions\Tools\CloseChatbotTicketAction::class,
        'parameters'     => [
            'type'       => 'object',
            'required'   => ['conversation_id'],
            'properties' => [
                'conversation_id' => ['type' => 'integer', 'description' => 'ID of the conversation to close.'],
                'store_output_as' => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ],
        'default_config' => [
            'store_output_as' => 'closed_ticket',
        ],
    ],
];
