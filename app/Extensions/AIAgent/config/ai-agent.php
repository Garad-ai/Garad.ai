<?php

use App\Extensions\AIAgent\System\Actions\GenerateReportAction;
use App\Extensions\AIAgent\System\Actions\MemoryAction;

return [
    'version' => '1.0',

    /*
     * How often the workflow trigger command runs (set in ServiceProvider).
     * This mirrors the Laravel scheduler frequency.
     */
    'trigger_interval' => env('AI_AGENT_TRIGGER_INTERVAL', 'everyMinute'),

    'tools' => [
        'generate_report' => [
            'label'       => 'Generate Report',
            'description' => 'Generates a detailed activity report about messages, workflows, chatbot usage, and more.',
            'action'      => GenerateReportAction::class,
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'date_range' => [
                        'type'        => 'string',
                        'enum'        => ['today', 'yesterday', 'this_week'],
                        'description' => 'The time period for the report.',
                    ],
                    'sections' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'string'],
                        'description' => 'Sections to include: messages, workflows, chatbot, marketing, blogpilot, social_media, social_media_agent, ai_usage, creative_suite, ai_images, marketing_contacts.',
                    ],
                ],
                'required' => [],
            ],
            'default_config' => [
                'date_range'      => 'today',
                'sections'        => ['messages', 'workflows'],
                'store_output_as' => 'tool_result',
            ],
        ],

        'memory' => [
            'label'       => 'Memory',
            'description' => 'Saves or deletes a personal memory entry for the user. Use "set" to add a new free-text memory, "delete" to remove an existing entry by its ID.',
            'action'      => MemoryAction::class,
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'operation' => [
                        'type'        => 'string',
                        'enum'        => ['set', 'delete'],
                        'description' => 'Whether to save ("set") or remove ("delete") the memory entry.',
                    ],
                    'memory' => [
                        'type'        => 'string',
                        'description' => 'Free-text memory to store, e.g. "User prefers English responses". Required when operation is "set".',
                    ],
                    'id' => [
                        'type'        => 'integer',
                        'description' => 'The ID of the memory record to delete. Required when operation is "delete".',
                    ],
                ],
                'required' => ['operation'],
            ],
            'default_config' => [],
        ],
    ],

    /*
     * Avatar assigned to a workflow template, keyed by the template title (key).
     * Files live under public/vendor/ai-agent/images/avatars/ and mirror the
     * selectable avatar presets seeded in ext_ai_agent_avatars.
     */
    'template_avatar_base'    => '/vendor/ai-agent/images/avatars/',
    'template_default_avatar' => 'avatar-1.png',
    'template_avatars'        => [
        'daily_report'                      => 'avatar-1.png',
        'weekly_summary'                    => 'avatar-2.png',
        'channel_auto_reply'                => 'avatar-3.png',
        'morning_briefing'                  => 'avatar-4.png',
        'content_ideas'                     => 'avatar-5.png',
        'smart_follow_up'                   => 'avatar-6.png',
        'channel_path_triage'               => 'avatar-7.png',
        'ai_web_search'                     => 'avatar-1.png',
        'trending_topics'                   => 'avatar-2.png',
        'news_digest'                       => 'avatar-3.png',
        'competitor_watch'                  => 'avatar-4.png',
        'sentiment_triage'                  => 'avatar-5.png',
        'lead_qualifier'                    => 'avatar-6.png',
        'daily_tip'                         => 'avatar-7.png',
        'market_research'                   => 'avatar-1.png',
        'webhook_researcher'                => 'avatar-2.png',
        'social_media_report'               => 'avatar-3.png',
        'onboarding_helper'                 => 'avatar-4.png',
        'gmail_daily_report'                => 'avatar-5.png',
        'gmail_trending_digest'             => 'avatar-6.png',
        'gmail_inbox_summary'               => 'avatar-7.png',
        'gmail_chatbot_report'              => 'avatar-1.png',
        'outlook_daily_report'              => 'avatar-2.png',
        'outlook_meeting_prep'              => 'avatar-3.png',
        'outlook_weekly_digest'             => 'avatar-4.png',
        'social_post_from_trends'           => 'avatar-5.png',
        'social_post_from_channel'          => 'avatar-6.png',
        'outlook_channel_to_email'          => 'avatar-7.png',
        'tiktok_video_ideas'                => 'avatar-1.png',
        'social_post_auto_approver'         => 'avatar-2.png',
        'social_analytics_digest'           => 'avatar-3.png',
        'chatbot_ticket_triage'             => 'avatar-4.png',
        'chatbot_weekly_insights'           => 'avatar-5.png',
        'campaign_from_chatbot_pain_points' => 'avatar-6.png',
        'marketing_stats_alert'             => 'avatar-7.png',
        'campaign_from_channel_brief'       => 'avatar-1.png',
        'lead_to_outlook_contact'           => 'avatar-2.png',
        'meeting_scheduler'                 => 'avatar-3.png',
        'gmail_to_social_post'              => 'avatar-4.png',
        'chatbot_convo_to_marketing'        => 'avatar-5.png',
        'crm_capture_lead'                  => 'avatar-6.png',
        'crm_deal_from_message'             => 'avatar-7.png',
        'crm_log_interaction'               => 'avatar-1.png',
        'crm_message_to_task'               => 'avatar-2.png',
        'crm_daily_pipeline_digest'         => 'avatar-3.png',
        'crm_weekly_deal_review'            => 'avatar-4.png',
    ],

    'workflow_templates' => [
        'crm_capture_lead' => [
            'label'       => 'Capture Lead from Message',
            'description' => 'Reads an incoming channel message, extracts the sender\'s details with AI, and creates a CRM contact automatically.',
            'icon'        => 'tabler-user-plus',
            'color'		     => '#cecff2',
            'tags'        => ['crm'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Capture Lead from Message',
                'description'  => 'Turns incoming channel messages into CRM contacts.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a CRM data-entry assistant. Decide whether the message introduces a lead — someone identifying themselves or another person as a prospect, or leaving contact details. If it does not (small talk, a greeting, a support question), set first_name to null. Otherwise first_name is the person\'s given name only, or "Lead" when the message gives details but no name. company_name is the organisation they represent.',
                            'user_message'    => '{message_text}',
                            'history_turns'   => 0,
                            'response_format' => 'json',
                            'output_keys'     => ['first_name', 'last_name', 'email', 'phone', 'job_title', 'company_name'],
                            'store_output_as' => 'lead',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'name'       => 'Lead found',
                                    'ruleGroups' => [
                                        ['rules' => [['field' => '{first_name}', 'operator' => 'is_not_empty', 'value' => '']]],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'crm_agent',
                                            'config' => [
                                                'action_event'    => 'create_contact',
                                                'first_name'      => '{first_name}',
                                                'last_name'       => '{last_name}',
                                                'email'           => '{email}',
                                                'phone'           => '{phone}',
                                                'job_title'       => '{job_title}',
                                                'company_name'    => '{company_name}',
                                                'notes'           => '{message_text}',
                                                'store_output_as' => 'created_contact',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Thanks! We\'ve saved your details and a team member will follow up shortly.',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name'       => 'No lead details',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Thanks for reaching out! Send your name and the best email or phone number and I will pass you to the right person.',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'crm_deal_from_message' => [
            'label'       => 'Create Deal from Message',
            'description' => 'Summarises an incoming opportunity message into a deal title and opens a new CRM deal in the pipeline.',
            'icon'        => 'tabler-businessplan',
            'color'		     => '#cecff2',
            'tags'        => ['crm'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Create Deal from Message',
                'description'  => 'Opens a CRM deal from an incoming channel message.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a sales assistant. Decide whether the message describes a real sales opportunity — a prospect, a purchase intent or a quote request. If it does not (small talk, a greeting, a support question), set title to null. Otherwise title is a short, clear deal name (max 8 words). value is the monetary amount as digits only — no currency symbol, no thousands separators — or null when none is mentioned. currency is the ISO code (e.g. USD) mentioned or implied, defaulting to USD. contact_name is the full name of the person behind the opportunity, contact_email their email address, and company_name their organisation. expected_close_date is YYYY-MM-DD when the message states a target date. Use null for anything the message does not state.',
                            'user_message'    => '{message_text}',
                            'history_turns'   => 0,
                            'response_format' => 'json',
                            'output_keys'     => ['title', 'value', 'currency', 'contact_name', 'contact_email', 'company_name', 'expected_close_date'],
                            'store_output_as' => 'deal',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'name'       => 'Opportunity with a budget',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                ['field' => '{title}', 'operator' => 'is_not_empty', 'value' => ''],
                                                ['field' => '{value}', 'operator' => 'greater_than', 'value' => '0'],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'crm_agent',
                                            'config' => [
                                                'action_event'        => 'create_deal',
                                                'title'               => '{title}',
                                                'value'               => '{value}',
                                                'description'         => '{message_text}',
                                                'currency'            => '{currency}',
                                                'contact_name'        => '{contact_name}',
                                                'contact_email'       => '{contact_email}',
                                                'company_name'        => '{company_name}',
                                                'expected_close_date' => '{expected_close_date}',
                                                'store_output_as'     => 'created_deal',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Logged as a new opportunity: "{title}" — {value} {currency}. We\'ll be in touch soon!',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    // Two groups because an absent amount arrives either as an empty
                                    // value or as a literal 0, and the groups are OR-combined.
                                    'name'       => 'Opportunity, amount unknown',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                ['field' => '{title}', 'operator' => 'is_not_empty', 'value' => ''],
                                                ['field' => '{value}', 'operator' => 'is_empty', 'value' => ''],
                                            ],
                                        ],
                                        [
                                            'rules' => [
                                                ['field' => '{title}', 'operator' => 'is_not_empty', 'value' => ''],
                                                ['field' => '{value}', 'operator' => 'less_than', 'value' => '1'],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'crm_agent',
                                            'config' => [
                                                'action_event'        => 'create_deal',
                                                'title'               => '{title}',
                                                'description'         => '{message_text}',
                                                'currency'            => '{currency}',
                                                'contact_name'        => '{contact_name}',
                                                'contact_email'       => '{contact_email}',
                                                'company_name'        => '{company_name}',
                                                'expected_close_date' => '{expected_close_date}',
                                                'store_output_as'     => 'created_deal',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Logged as a new opportunity: "{title}". What budget do you have in mind so I can size it correctly?',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name'       => 'Not an opportunity',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Thanks for your message! I did not spot any deal details in it. Send the company name and the amount and I will open an opportunity for you.',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'crm_log_interaction' => [
            'label'       => 'Log Interaction as Note',
            'description' => 'Logs every incoming channel message as an activity note on the related CRM contact for a full interaction history.',
            'icon'        => 'tabler-notes',
            'color'		     => '#cecff2',
            'tags'        => ['crm'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Log Interaction as Note',
                'description'  => 'Saves incoming messages as CRM contact notes.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You log customer messages into a CRM as activity notes. Identify who the message is from or about: contact_name is that person\'s full name, or null when the message names nobody. contact_email is their email address when stated. note_type is exactly one of: note, call, meeting, email — use call or meeting only when the message reports or books one, otherwise note. scheduled_at is the date and time of that call or meeting as YYYY-MM-DD HH:MM when stated. Use null for anything the message does not state.',
                            'user_message'    => '{message_text}',
                            'history_turns'   => 0,
                            'response_format' => 'json',
                            'output_keys'     => ['contact_name', 'contact_email', 'note_type', 'scheduled_at'],
                            'store_output_as' => 'interaction',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'name'       => 'Contact identified',
                                    'ruleGroups' => [
                                        ['rules' => [['field' => '{contact_name}', 'operator' => 'is_not_empty', 'value' => '']]],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'crm_agent',
                                            'config' => [
                                                'action_event'    => 'add_note',
                                                'notable'         => 'contact',
                                                'record_name'     => '{contact_name}',
                                                'contact_email'   => '{contact_email}',
                                                'type'            => '{note_type}',
                                                'scheduled_at'    => '{scheduled_at}',
                                                'content'         => '{message_text}',
                                                'store_output_as' => 'logged_note',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Noted — we\'ve logged this on {contact_name}\'s record.',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name'       => 'No contact named',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Thanks for your message! Tell me who it is about — a name or an email — and I will log it on their record.',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'crm_message_to_task' => [
            'label'       => 'Message to Follow-up Task',
            'description' => 'Drafts a follow-up task title from an incoming message with AI and creates a CRM task to action it.',
            'icon'        => 'tabler-checkbox',
            'color'		     => '#cecff2',
            'tags'        => ['crm'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Message to Follow-up Task',
                'description'  => 'Creates CRM follow-up tasks from incoming messages.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an assistant that turns customer messages into actionable follow-up tasks. Decide whether the message actually asks for something to be done. If it does not (small talk, a greeting, a thank-you), set title to null. Otherwise title is a concise description of what needs to be done (max 10 words). priority is exactly one of: low, medium, high, urgent. due_date is YYYY-MM-DD when the message states a deadline, otherwise null.',
                            'user_message'    => '{message_text}',
                            'history_turns'   => 0,
                            'response_format' => 'json',
                            'output_keys'     => ['title', 'priority', 'due_date'],
                            'store_output_as' => 'task',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'name'       => 'Action requested',
                                    'ruleGroups' => [
                                        ['rules' => [['field' => '{title}', 'operator' => 'is_not_empty', 'value' => '']]],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'crm_agent',
                                            'config' => [
                                                'action_event'    => 'create_task',
                                                'title'           => '{title}',
                                                'description'     => '{message_text}',
                                                'priority'        => '{priority}',
                                                'due_date'        => '{due_date}',
                                                'store_output_as' => 'created_task',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Got it — we\'ve created a follow-up task: "{title}".',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'name'       => 'Nothing to action',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Thanks for your message! Let me know what you need done and I will open a follow-up task for it.',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'crm_daily_pipeline_digest' => [
            'label'       => 'Daily Pipeline Digest',
            'description' => 'Fetches CRM pipeline analytics every morning at 9am, writes a concise digest with AI, and sends it to your channel.',
            'icon'        => 'tabler-chart-funnel',
            'color'		     => '#cecff2',
            'tags'        => ['crm', 'reports'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Daily Pipeline Digest',
                'description'  => 'Sends a daily CRM pipeline summary at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * *',
                'steps'        => [
                    [
                        'type'   => 'crm_agent',
                        'config' => [
                            'action_event'    => 'get_crm_analytics',
                            'store_output_as' => 'crm_analytics',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a sales operations analyst. Turn the provided CRM pipeline data into a concise daily digest: total pipeline value, deal count per stage, and any stage that looks empty or overloaded. Use plain text and short bullet points. Keep it under 200 words.',
                            'user_message'    => '{crm_analytics}',
                            'store_output_as' => 'pipeline_digest',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{pipeline_digest}',
                        ],
                    ],
                ],
            ],
        ],

        'crm_weekly_deal_review' => [
            'label'       => 'Weekly Deal Review',
            'description' => 'Delivers an executive CRM pipeline review every Monday at 9am, highlighting momentum and deals that need attention.',
            'icon'        => 'tabler-presentation-analytics',
            'color'		     => '#cecff2',
            'tags'        => ['crm', 'reports'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Weekly Deal Review',
                'description'  => 'Sends a weekly CRM deal review every Monday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1',
                'steps'        => [
                    [
                        'type'   => 'crm_agent',
                        'config' => [
                            'action_event'    => 'get_crm_analytics',
                            'store_output_as' => 'crm_analytics',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a sales director writing a weekly pipeline review for the team. From the CRM data, summarise total pipeline value and deal distribution, call out the stages with the most value, and suggest one focus area for the week. Professional, concise, plain text.',
                            'user_message'    => '{crm_analytics}',
                            'store_output_as' => 'deal_review',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{deal_review}',
                        ],
                    ],
                ],
            ],
        ],

        'daily_report' => [
            'label'       => 'Daily Report',
            'description' => 'Generates a daily activity report every morning at 9am and sends it to a channel.',
            'icon'        => 'tabler-report-analytics',
            'color'		     => '#cecff2',
            'tags'        => ['reports'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Daily Report',
                'description'  => 'Generates a daily report of messages and workflows.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * *',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'yesterday',
                            'sections'        => ['messages', 'workflows', 'ai_usage', 'ai_images'],
                            'store_output_as' => 'report_text',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{report_text}',
                        ],
                    ],
                ],
            ],
        ],

        'weekly_summary' => [
            'label'       => 'Weekly Summary',
            'description' => 'Delivers a full weekly activity digest every Monday morning covering all key metrics.',
            'icon'        => 'tabler-calendar-week',
            'color'		     => '#cecff2',
            'tags'        => ['reports'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Weekly Summary',
                'description'  => 'Sends a weekly digest every Monday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'this_week',
                            'sections'        => ['messages', 'workflows', 'chatbot', 'ai_usage'],
                            'store_output_as' => 'weekly_report',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an executive assistant. Format the provided data into a concise, bullet-pointed weekly digest with key highlights and trends.',
                            'user_message'    => '{weekly_report}',
                            'store_output_as' => 'formatted_summary',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{formatted_summary}',
                        ],
                    ],
                ],
            ],
        ],

        'channel_auto_reply' => [
            'label'       => 'Channel Auto-Reply',
            'description' => 'Listens for incoming channel messages and automatically replies using AI.',
            'icon'        => 'tabler-message-reply',
            'color'		     => '#cecff2',
            'tags'        => [],
            'featured'    => true,
            'config'      => [
                'name'         => 'Channel Auto-Reply',
                'description'  => 'Automatically replies to channel messages with an AI-generated response.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a helpful assistant. Reply clearly and concisely to the user\'s message.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'ai_response',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{ai_response}',
                        ],
                    ],
                ],
            ],
        ],

        'morning_briefing' => [
            'label'       => 'Morning Briefing',
            'description' => 'Sends a personalized AI-written morning briefing with insights every day at 8am.',
            'icon'        => 'tabler-sun',
            'color'		     => '#cecff2',
            'tags'        => ['reports'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Morning Briefing',
                'description'  => 'AI-generated morning briefing with activity insights.',
                'trigger_type' => 'schedule',
                'cron'         => '0 8 * * *',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'yesterday',
                            'sections'        => ['messages', 'ai_usage', 'chatbot'],
                            'store_output_as' => 'raw_data',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a professional executive assistant. Write a warm, concise morning briefing based on the previous day\'s data. Include highlights, notable activity, and a motivational closing sentence.',
                            'user_message'    => '{raw_data}',
                            'store_output_as' => 'briefing',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{briefing}',
                        ],
                    ],
                ],
            ],
        ],

        'content_ideas' => [
            'label'       => 'Daily Content Ideas',
            'description' => 'Generates fresh AI-powered content ideas every day and delivers them to your channel.',
            'icon'        => 'tabler-bulb',
            'color'		     => '#cecff2',
            'tags'        => ['social_media'],
            'featured'    => true,
            'config'      => [
                'name'         => 'Daily Content Ideas',
                'description'  => 'Generates daily content ideas using AI.',
                'trigger_type' => 'schedule',
                'cron'         => '0 7 * * *',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a creative content strategist. Generate 5 unique, engaging content ideas for today. For each idea, provide a title, a one-sentence description, and the best platform or format to use. Keep it practical and actionable.',
                            'user_message'    => 'Generate 5 content ideas for today.',
                            'store_output_as' => 'content_ideas',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{content_ideas}',
                        ],
                    ],
                ],
            ],
        ],

        'smart_follow_up' => [
            'label'       => 'Smart Follow-Up',
            'description' => 'Detects incoming messages, crafts a thoughtful AI follow-up, and sends it automatically.',
            'icon'        => 'tabler-arrows-right-left',
            'color'		     => '#cecff2',
            'tags'        => [],
            'featured'    => false,
            'config'      => [
                'name'         => 'Smart Follow-Up',
                'description'  => 'AI-powered smart reply for incoming channel messages.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a professional customer success agent. Analyze the incoming message, understand the intent, and craft a helpful, empathetic, and concise follow-up response. If the message is unclear, ask one clarifying question.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'ai_response',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{ai_response}',
                        ],
                    ],
                ],
            ],
        ],

        'channel_path_triage' => [
            'label'       => 'Message Triage',
            'description' => 'Classifies incoming channel messages as a question, request, or other, then routes each to a tailored AI response path.',
            'icon'        => 'tabler-git-fork',
            'color'		     => '#cecff2',
            'tags'        => [],
            'featured'    => false,
            'config'      => [
                'name'         => 'Message Triage',
                'description'  => 'Routes incoming messages based on intent classification.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Classify the user\'s message into exactly one of these categories: "question", "request", or "other". Reply with only the category word, nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'intent',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-question',
                                    'name'       => 'Question',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{intent}',
                                                    'operator' => 'contains',
                                                    'value'    => 'question',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a knowledgeable assistant. Answer the user\'s question clearly and concisely.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-request',
                                    'name'       => 'Request',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{intent}',
                                                    'operator' => 'contains',
                                                    'value'    => 'request',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a helpful assistant. Acknowledge the user\'s request, confirm what you understand they need, and provide next steps or a resolution.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-other',
                                    'name'       => 'Other (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a friendly assistant. Respond helpfully to the user\'s message.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'ai_web_search' => [
            'label'       => 'AI Web Search',
            'description' => 'Performs an AI-powered web search based on incoming messages and replies with the findings.',
            'icon'        => 'tabler-world-www',
            'color'		     => '#cecff2',
            'tags'        => ['ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'AI Web Search',
                'description'  => 'AI-powered web search for incoming channel messages.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an intelligent research assistant. When given a user\'s query, perform a web search to find the most relevant and accurate information. Summarize your findings in a clear and concise response. If the query is ambiguous, ask one clarifying question before searching.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'search_results',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{search_results}',
                        ],
                    ],
                ],
            ],
        ],

        'trending_topics' => [
            'label'       => 'Daily Trending Topics',
            'description' => 'Searches the web every day for trending topics in your field and delivers a curated digest at 9am.',
            'icon'        => 'tabler-trending-up',
            'color'		     => '#cecff2',
            'tags'        => ['ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Daily Trending Topics',
                'description'  => 'Delivers a daily digest of trending topics via web search at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * *',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a trend analyst. Search the web for the top trending topics in AI, technology, and digital marketing right now. For each trend, provide: 1) the trend name, 2) a one-sentence explanation, 3) why it matters today. Format as a clean numbered list with emojis. Limit to 7 trends.',
                            'user_message'    => 'Find today\'s top trending topics.',
                            'store_output_as' => 'trending_topics',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{trending_topics}',
                        ],
                    ],
                ],
            ],
        ],

        'news_digest' => [
            'label'       => 'Morning News Digest',
            'description' => 'Curates the top 5 industry news stories every weekday morning and sends them to your channel.',
            'icon'        => 'tabler-news',
            'color'		     => '#cecff2',
            'tags'        => ['ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Morning News Digest',
                'description'  => 'Delivers top 5 industry news stories every weekday at 8am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 8 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a news editor. Search the web for today\'s top 5 most important stories in technology, business, and AI. For each story, provide: a headline, a 2-sentence summary, and a relevance score (1-10). Order by importance. Keep it scannable and professional.',
                            'user_message'    => 'Curate the top 5 news stories for today.',
                            'store_output_as' => 'news_digest',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{news_digest}',
                        ],
                    ],
                ],
            ],
        ],

        'competitor_watch' => [
            'label'       => 'Weekly Competitor Watch',
            'description' => 'Monitors competitor activity and market moves every Monday morning and sends a structured digest.',
            'icon'        => 'tabler-eye',
            'color'		     => '#cecff2',
            'tags'        => ['ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Weekly Competitor Watch',
                'description'  => 'Weekly competitor and market intelligence report every Monday at 10am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 10 * * 1',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a competitive intelligence analyst. Search the web for notable competitor activity, product launches, funding announcements, and market moves from the past week in the AI and SaaS industry. Gather raw findings including company names, actions taken, and dates.',
                            'user_message'    => 'Research competitor and market activity from the past 7 days.',
                            'store_output_as' => 'raw_intelligence',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a strategic analyst. Format the provided competitive intelligence into a clean weekly digest with sections: "Key Moves", "Product Updates", "Market Shifts", and "Strategic Implications". Be concise and actionable.',
                            'user_message'    => '{raw_intelligence}',
                            'store_output_as' => 'competitor_digest',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{competitor_digest}',
                        ],
                    ],
                ],
            ],
        ],

        'sentiment_triage' => [
            'label'       => 'Sentiment-Based Routing',
            'description' => 'Detects the emotional tone of incoming messages and routes each to a tailored response — positive, negative, or neutral.',
            'icon'        => 'tabler-mood-check',
            'color'		     => '#cecff2',
            'tags'        => [],
            'featured'    => false,
            'config'      => [
                'name'         => 'Sentiment-Based Routing',
                'description'  => 'Routes incoming messages based on detected sentiment.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Analyze the sentiment of the user\'s message. Reply with exactly one word: "positive", "negative", or "neutral". Nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'sentiment',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-positive',
                                    'name'       => 'Positive',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{sentiment}',
                                                    'operator' => 'contains',
                                                    'value'    => 'positive',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are an enthusiastic and warm assistant. The user\'s message has a positive tone. Match their energy, celebrate their positivity, and respond helpfully and encouragingly.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-negative',
                                    'name'       => 'Negative',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{sentiment}',
                                                    'operator' => 'contains',
                                                    'value'    => 'negative',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are an empathetic support specialist. The user\'s message has a negative or frustrated tone. Acknowledge their feelings with genuine empathy, apologize where appropriate, and offer concrete help or next steps to resolve their concern.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-neutral',
                                    'name'       => 'Neutral (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a professional and helpful assistant. Respond clearly and concisely to the user\'s message.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'lead_qualifier' => [
            'label'       => 'Lead Qualifier',
            'description' => 'Qualifies incoming leads, saves their intent to memory, and replies with a personalized follow-up message.',
            'icon'        => 'tabler-user-check',
            'color'		     => '#cecff2',
            'tags'        => ['marketing'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Lead Qualifier',
                'description'  => 'Qualifies and saves leads from incoming channel messages.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a sales qualification specialist. Analyze the incoming message and extract: 1) the user\'s main goal or pain point, 2) their apparent budget signal (high/medium/low/unknown), 3) urgency (immediate/soon/exploring), and 4) a lead quality score (1-10). Format your output as a brief structured summary starting with "Lead Summary:". Then craft a warm, personalized follow-up response that acknowledges their need and suggests a next step.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'qualification_result',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Extract only the personalized follow-up response portion from the qualification result. Return just the customer-facing message, no internal notes or scores.',
                            'user_message'    => '{qualification_result}',
                            'store_output_as' => 'follow_up_message',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{follow_up_message}',
                        ],
                    ],
                ],
            ],
        ],

        'daily_tip' => [
            'label'       => 'Daily Industry Tip',
            'description' => 'Sends a practical, AI-generated industry tip or best practice to your channel every weekday morning.',
            'icon'        => 'tabler-bulb',
            'color'		     => '#cecff2',
            'tags'        => ['ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Daily Industry Tip',
                'description'  => 'Delivers a daily actionable tip every weekday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an expert coach in AI, productivity, and digital business. Generate one highly actionable tip for today. Structure it as: 💡 **Tip of the Day** — [catchy title]\n\n[2-3 sentence explanation of the tip]\n\n**How to apply it today:** [one concrete action step the reader can do right now].\n\nKeep the total length under 150 words. Vary topics across: AI tools, marketing, productivity, automation, and growth strategies.',
                            'user_message'    => 'Generate today\'s actionable industry tip.',
                            'store_output_as' => 'daily_tip',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{daily_tip}',
                        ],
                    ],
                ],
            ],
        ],

        'market_research' => [
            'label'       => 'Weekly Market Research',
            'description' => 'Researches market trends and the competitive landscape every Friday and delivers a structured intelligence report.',
            'icon'        => 'tabler-chart-bar',
            'color'		     => '#cecff2',
            'tags'        => ['ai', 'reports'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Weekly Market Research',
                'description'  => 'Delivers a weekly market intelligence report every Friday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 5',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a market research analyst. Search the web for this week\'s most significant market trends, funding rounds, product launches, and industry shifts in AI and SaaS. Collect raw data: company names, events, dates, and market signals.',
                            'user_message'    => 'Research this week\'s market trends and competitive landscape.',
                            'store_output_as' => 'raw_market_data',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a senior market analyst. Transform the provided raw market data into a polished weekly intelligence report with these sections: **Market Pulse** (top 3 trends), **Notable Funding & M&A**, **Product Launches**, **Opportunities to Watch**, and **Key Takeaway**. Be concise, data-driven, and strategic.',
                            'user_message'    => '{raw_market_data}',
                            'store_output_as' => 'market_report',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{market_report}',
                        ],
                    ],
                ],
            ],
        ],

        'webhook_researcher' => [
            'label'       => 'Webhook Research Agent',
            'description' => 'Receives a topic via webhook, performs a deep web search, and delivers a structured research report.',
            'icon'        => 'tabler-webhook',
            'color'		     => '#cecff2',
            'tags'        => ['ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Webhook Research Agent',
                'description'  => 'On-demand AI research triggered by an incoming webhook payload.',
                'trigger_type' => 'webhook',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a deep research analyst. Search the web thoroughly for information about the given topic. Collect facts, recent developments, key players, statistics, and expert opinions. Gather comprehensive raw findings.',
                            'user_message'    => 'Research this topic in depth: {topic}',
                            'store_output_as' => 'raw_research',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a report writer. Transform the provided research findings into a clear, well-structured report with sections: **Executive Summary**, **Key Findings**, **Recent Developments**, **Key Players**, and **Conclusion**. Use bullet points for scannability.',
                            'user_message'    => '{raw_research}',
                            'store_output_as' => 'research_report',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{research_report}',
                        ],
                    ],
                ],
            ],
        ],

        'social_media_report' => [
            'label'       => 'Weekly Social Media Report',
            'description' => 'Generates a weekly social media performance report with AI-powered insights and growth suggestions every Monday.',
            'icon'        => 'tabler-social',
            'color'		     => '#cecff2',
            'tags'        => ['social_media', 'reports'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Weekly Social Media Report',
                'description'  => 'Weekly social media performance insights every Monday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'this_week',
                            'sections'        => ['social_media', 'social_media_agent', 'ai_images'],
                            'store_output_as' => 'social_raw_data',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a social media strategist. Analyze the provided social media performance data and produce a weekly report with: **Performance Highlights** (top wins), **Areas to Improve**, **Content That Resonated**, **Growth Opportunities**, and **3 Actionable Recommendations for Next Week**. Make it motivating and data-driven.',
                            'user_message'    => '{social_raw_data}',
                            'store_output_as' => 'social_media_report',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{social_media_report}',
                        ],
                    ],
                ],
            ],
        ],

        'onboarding_helper' => [
            'label'       => 'Smart Onboarding Assistant',
            'description' => 'Detects new or onboarding users from incoming messages, saves their preferences to memory, and delivers a personalized welcome guide.',
            'icon'        => 'tabler-user-plus',
            'color'		     => '#cecff2',
            'tags'        => [],
            'featured'    => false,
            'config'      => [
                'name'         => 'Smart Onboarding Assistant',
                'description'  => 'Personalized onboarding flow for new users via channel messages.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Determine whether the user\'s message suggests they are new or onboarding (e.g., introduces themselves, asks "how do I get started", "what can you do", "I\'m new here"). Reply with exactly one word: "onboarding" or "general". Nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'user_intent',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-onboarding',
                                    'name'       => 'New User Onboarding',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{user_intent}',
                                                    'operator' => 'contains',
                                                    'value'    => 'onboarding',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a warm and knowledgeable onboarding specialist. Based on the user\'s introduction or question, craft a personalized welcome message that: 1) greets them warmly by name if mentioned, 2) briefly explains the top 3 things they can do, 3) asks one question to understand their primary goal so you can tailor further help. Keep it friendly and under 150 words.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'welcome_message',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{welcome_message}',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-general',
                                    'name'       => 'General Message (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a helpful assistant. Respond clearly and helpfully to the user\'s message.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'gmail_daily_report' => [
            'label'       => 'Gmail — Daily Activity Report',
            'description' => 'Generates a daily activity report every morning and emails it to you via Gmail.',
            'icon'        => 'tabler-brand-gmail',
            'color'		     => '#cecff2',
            'tags'        => ['gmail', 'reports'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Gmail — Daily Activity Report',
                'description'  => 'Emails a daily activity report via Gmail at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * *',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'yesterday',
                            'sections'        => ['messages', 'workflows', 'ai_usage', 'ai_images'],
                            'store_output_as' => 'raw_report',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an executive assistant. Format the provided activity data into a concise, well-structured daily report with clear sections, bullet points, and key highlights. Use a professional email tone.',
                            'user_message'    => '{raw_report}',
                            'store_output_as' => 'email_body',
                        ],
                    ],
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Daily Activity Report',
                            'body'            => '{email_body}',
                            'store_output_as' => 'gmail_result',
                        ],
                    ],
                ],
            ],
        ],

        'gmail_trending_digest' => [
            'label'       => 'Gmail — Daily Trending Topics Digest',
            'description' => 'Searches the web for trending topics every morning and delivers them to your inbox via Gmail.',
            'icon'        => 'tabler-brand-gmail',
            'color'		     => '#cecff2',
            'tags'        => ['gmail', 'ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Gmail — Daily Trending Topics Digest',
                'description'  => 'Emails a daily trending topics digest via Gmail at 8am on weekdays.',
                'trigger_type' => 'schedule',
                'cron'         => '0 8 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a trend analyst. Search the web for today\'s top 7 trending topics in AI, technology, and digital marketing. For each trend provide: name, a one-sentence explanation, and why it matters right now. Format as a clean numbered list suitable for an email body.',
                            'user_message'    => 'Find today\'s top trending topics.',
                            'store_output_as' => 'trending_content',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Your Daily Trending Topics Digest',
                            'body'            => '{trending_content}',
                            'store_output_as' => 'gmail_result',
                        ],
                    ],
                ],
            ],
        ],

        'gmail_inbox_summary' => [
            'label'       => 'Gmail — Morning Inbox Summary',
            'description' => 'Reads your unread Gmail inbox every morning, summarizes key emails with AI, and sends you a digest.',
            'icon'        => 'tabler-brand-gmail',
            'color'		     => '#cecff2',
            'tags'        => ['gmail'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Gmail — Morning Inbox Summary',
                'description'  => 'AI-powered Gmail inbox digest emailed to you each weekday at 8am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 8 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'find_email',
                            'query'           => 'is:unread newer_than:1d',
                            'max_results'     => 20,
                            'return_format'   => 'full_as_string',
                            'store_output_as' => 'unread_emails',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a personal assistant. Review the list of unread emails provided. Create a concise inbox summary with: 1) total email count, 2) top 5 most important emails with sender, subject, and a one-line summary each, 3) any action items or urgent requests you spotted. Format it clearly for an email digest. If there are no emails, write a friendly "inbox zero" message.',
                            'user_message'    => '{unread_emails}',
                            'store_output_as' => 'inbox_digest',
                        ],
                    ],
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Your Morning Inbox Summary',
                            'body'            => '{inbox_digest}',
                            'store_output_as' => 'gmail_result',
                        ],
                    ],
                ],
            ],
        ],

        'gmail_chatbot_report' => [
            'label'       => 'Gmail — Weekly Chatbot Report',
            'description' => 'Analyzes chatbot and AI usage data every week and emails a performance report with insights via Gmail.',
            'icon'        => 'tabler-brand-gmail',
            'color'		     => '#cecff2',
            'tags'        => ['gmail', 'reports', 'chatbot'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Gmail — Weekly Chatbot Report',
                'description'  => 'Weekly chatbot performance email report every Monday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'this_week',
                            'sections'        => ['chatbot', 'ai_usage', 'messages'],
                            'store_output_as' => 'chatbot_raw',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a product analytics expert. Analyze the chatbot and AI usage data and write a weekly performance report with sections: **Usage Highlights**, **Top Conversation Themes**, **Performance Metrics**, **User Satisfaction Signals**, and **Recommendations for Next Week**. Keep it data-driven and actionable.',
                            'user_message'    => '{chatbot_raw}',
                            'store_output_as' => 'chatbot_report',
                        ],
                    ],
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Weekly Chatbot Performance Report',
                            'body'            => '{chatbot_report}',
                            'store_output_as' => 'gmail_result',
                        ],
                    ],
                ],
            ],
        ],

        'outlook_daily_report' => [
            'label'       => 'Outlook — Daily Activity Report',
            'description' => 'Generates a daily activity report every morning and emails it to you via Outlook.',
            'icon'        => 'tabler-brand-office',
            'color'		     => '#cecff2',
            'tags'        => ['outlook', 'reports'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Outlook — Daily Activity Report',
                'description'  => 'Emails a daily activity report via Outlook at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * *',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'yesterday',
                            'sections'        => ['messages', 'workflows', 'ai_usage', 'ai_images'],
                            'store_output_as' => 'raw_report',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an executive assistant. Format the provided activity data into a concise, well-structured daily report with clear sections, bullet points, and key highlights. Use a professional email tone.',
                            'user_message'    => '{raw_report}',
                            'store_output_as' => 'email_body',
                        ],
                    ],
                    [
                        'type'   => 'outlook',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Daily Activity Report',
                            'body'            => '{email_body}',
                            'store_output_as' => 'outlook_result',
                        ],
                    ],
                ],
            ],
        ],

        'outlook_meeting_prep' => [
            'label'       => 'Outlook — Daily Meeting Prep',
            'description' => 'Fetches today\'s calendar events from Outlook each morning, generates AI-powered prep notes, and emails them to you.',
            'icon'        => 'tabler-brand-office',
            'color'		     => '#cecff2',
            'tags'        => ['outlook'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Outlook — Daily Meeting Prep',
                'description'  => 'AI meeting prep notes emailed from your Outlook calendar every weekday at 7:30am.',
                'trigger_type' => 'schedule',
                'cron'         => '30 7 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'outlook',
                        'config' => [
                            'action_event'    => 'find_calendar_events',
                            'query'           => 'today',
                            'max_results'     => 10,
                            'return_format'   => 'full_as_string',
                            'store_output_as' => 'todays_events',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a personal executive assistant. Review today\'s calendar events. For each meeting prepare: 1) a brief agenda reminder, 2) suggested talking points or questions to raise, 3) any preparation action items. End with a "Focus for Today" section highlighting the most important meeting. If no meetings exist, write a motivational "clear day" message with productivity tips.',
                            'user_message'    => '{todays_events}',
                            'store_output_as' => 'meeting_prep',
                        ],
                    ],
                    [
                        'type'   => 'outlook',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Your Meeting Prep for Today',
                            'body'            => '{meeting_prep}',
                            'store_output_as' => 'outlook_result',
                        ],
                    ],
                ],
            ],
        ],

        'outlook_weekly_digest' => [
            'label'       => 'Outlook — Weekly Market & Activity Digest',
            'description' => 'Combines platform activity data with live web market research every Friday and sends a comprehensive digest via Outlook.',
            'icon'        => 'tabler-brand-office',
            'color'		     => '#cecff2',
            'tags'        => ['outlook', 'reports', 'ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Outlook — Weekly Market & Activity Digest',
                'description'  => 'Weekly combined activity + market intelligence email via Outlook every Friday at 4pm.',
                'trigger_type' => 'schedule',
                'cron'         => '0 16 * * 5',
                'steps'        => [
                    [
                        'type'   => 'generate_report',
                        'config' => [
                            'date_range'      => 'this_week',
                            'sections'        => ['messages', 'workflows', 'ai_usage', 'chatbot'],
                            'store_output_as' => 'activity_data',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a market intelligence analyst. Search the web for this week\'s top 5 industry developments in AI and SaaS. Return concise bullet points with company, event, and one-line significance.',
                            'user_message'    => 'Find this week\'s top 5 market developments in AI and SaaS.',
                            'store_output_as' => 'market_intel',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an executive assistant. Combine the platform activity report and market intelligence into a single, polished weekly digest email. Structure it as: **This Week at a Glance** (activity highlights), **Market Pulse** (industry news), and **Looking Ahead** (one strategic suggestion). Professional tone, scannable format.',
                            'user_message'    => "Activity data:\n{activity_data}\n\nMarket intelligence:\n{market_intel}",
                            'store_output_as' => 'weekly_digest',
                        ],
                    ],
                    [
                        'type'   => 'outlook',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Your Weekly Digest',
                            'body'            => '{weekly_digest}',
                            'store_output_as' => 'outlook_result',
                        ],
                    ],
                ],
            ],
        ],

        'social_post_from_trends' => [
            'label'       => 'Social Post from Daily Trends',
            'description' => 'Searches the web for trending topics every morning, generates platform-ready social media posts, and queues them via Social Media Agent.',
            'icon'        => 'tabler-trending-up',
            'color'		     => '#cecff2',
            'tags'        => ['social_media', 'ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Social Post from Daily Trends',
                'description'  => 'Auto-generates and queues social posts from trending topics every weekday at 8am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 8 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a trend analyst. Search the web for today\'s top 3 trending topics in AI, technology, and digital business. For each trend return: the topic name and a one-sentence summary of why it is trending today.',
                            'user_message'    => 'Find the top 3 trending topics right now.',
                            'store_output_as' => 'trending_topics',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a social media copywriter. Based on the trending topics provided, write one engaging social media post. Requirements: hook in the first line, 2–3 short sentences, end with a thought-provoking question to drive engagement, include 3–5 relevant hashtags. Tone: professional yet conversational. Output only the post text, nothing else.',
                            'user_message'    => '{trending_topics}',
                            'store_output_as' => 'post_content',
                        ],
                    ],
                    [
                        'type'   => 'social_media_agent',
                        'config' => [
                            'action_event'    => 'create_social_post',
                            'content'         => '{post_content}',
                            'post_type'       => 'text',
                            'store_output_as' => 'social_post_result',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{social_post_result}',
                        ],
                    ],
                ],
            ],
        ],

        'social_post_from_channel' => [
            'label'       => 'Social Post from Channel Message',
            'description' => 'Turns an incoming channel message into a polished social media post and queues it via Social Media Agent for review.',
            'icon'        => 'tabler-message-share',
            'color'		     => '#cecff2',
            'tags'        => ['social_media'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Social Post from Channel Message',
                'description'  => 'Converts incoming channel messages into social posts pending approval.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a social media copywriter. Transform the user\'s raw message into a polished, platform-ready social media post. Requirements: compelling hook in the first line, concise and punchy (max 280 characters for Twitter compatibility), end with a call-to-action or engaging question, include 3–5 relevant hashtags. Output only the final post text, nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'post_content',
                        ],
                    ],
                    [
                        'type'   => 'social_media_agent',
                        'config' => [
                            'action_event'    => 'create_social_post',
                            'content'         => '{post_content}',
                            'post_type'       => 'text',
                            'store_output_as' => 'social_post_result',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{social_post_result}',
                        ],
                    ],
                ],
            ],
        ],

        'outlook_channel_to_email' => [
            'label'       => 'Outlook — Channel Alert to Email',
            'description' => 'Monitors incoming channel messages, triages them with AI, and forwards urgent ones as a high-priority Outlook email.',
            'icon'        => 'tabler-brand-office',
            'color'		     => '#cecff2',
            'tags'        => ['outlook'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Outlook — Channel Alert to Email',
                'description'  => 'Forwards urgent channel messages to your Outlook inbox as high-priority email alerts.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are an intelligent triage assistant. Assess the incoming message and decide if it warrants an email alert. Reply with exactly one word: "alert" if the message is urgent, contains a complaint, a sales lead, or a critical request — or "skip" for routine or casual messages. Nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'triage_decision',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-send-alert',
                                    'name'       => 'Send Email Alert',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{triage_decision}',
                                                    'operator' => 'contains',
                                                    'value'    => 'alert',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a professional assistant. Write a brief, well-formatted email alert body for the message below. Include: what the message said, why it needs attention, and a suggested next action. Keep it under 100 words.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'alert_body',
                                            ],
                                        ],
                                        [
                                            'type'   => 'outlook',
                                            'config' => [
                                                'action_event'    => 'send_email',
                                                'to'              => '{sender_id}',
                                                'subject'         => 'Channel Alert: Action Required',
                                                'body'            => '{alert_body}',
                                                'importance'      => 'high',
                                                'store_output_as' => 'outlook_result',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-skip',
                                    'name'       => 'Skip (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'tiktok_video_ideas' => [
            'label'       => 'TikTok Video Ideas',
            'description' => 'Searches for trending TikTok topics every morning and generates 5 ready-to-film video concepts with hooks, scripts, and hashtags.',
            'icon'        => 'tabler-brand-tiktok',
            'color'		     => '#cecff2',
            'tags'        => ['social_media', 'ai'],
            'featured'    => false,
            'config'      => [
                'name'         => 'TikTok Video Ideas',
                'description'  => 'Daily TikTok video concept generator based on live trends, every weekday at 8am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 8 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a TikTok trend researcher. Search the web for today\'s top trending sounds, challenges, and topic formats on TikTok and short-form video. Return the top 5 trends with: trend name, why it is viral right now, and the content category it belongs to.',
                            'user_message'    => 'Find today\'s top 5 trending TikTok formats and topics.',
                            'store_output_as' => 'tiktok_trends',
                            'web_search'      => true,
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a TikTok content strategist. Using the trending topics provided, create 5 distinct video concepts. For each concept include: 🎬 Title, 🪝 Hook (first 3 seconds), 📝 Script outline (3–5 bullet points), 🎵 Suggested audio style, and #️⃣ 5 hashtags. Keep each concept under 120 words. Output only the 5 concepts, numbered.',
                            'user_message'    => '{tiktok_trends}',
                            'store_output_as' => 'video_concepts',
                        ],
                    ],
                    [
                        'type'   => 'social_media_agent',
                        'config' => [
                            'action_event'    => 'create_social_post',
                            'content'         => '{video_concepts}',
                            'post_type'       => 'text',
                            'store_output_as' => 'post_result',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{video_concepts}',
                        ],
                    ],
                ],
            ],
        ],

        'social_post_auto_approver' => [
            'label'       => 'Social Post Auto-Approver',
            'description' => 'Reviews pending social posts with AI every hour and auto-approves quality content or alerts you about posts that need attention.',
            'icon'        => 'tabler-circle-check',
            'color'		     => '#cecff2',
            'tags'        => ['social_media'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Social Post Auto-Approver',
                'description'  => 'Hourly AI quality gate for pending social media posts.',
                'trigger_type' => 'schedule',
                'cron'         => '0 * * * *',
                'steps'        => [
                    [
                        'type'   => 'social_media_agent',
                        'config' => [
                            'action_event'    => 'list_social_posts',
                            'status'          => 'pending_approval',
                            'limit'           => 10,
                            'store_output_as' => 'pending_posts',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a social media quality reviewer. Review the pending posts list. For each post evaluate: brand safety (no offensive content), grammar/clarity, engagement potential, and hashtag relevance. Classify each post as "approve" or "flag". Return a JSON array: [{"post_id": 123, "decision": "approve|flag", "reason": "one sentence"}]. If no posts exist return an empty array [].',
                            'user_message'    => '{pending_posts}',
                            'store_output_as' => 'review_decisions',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Extract only the post_id values where decision is "approve" from the JSON array provided. Return a comma-separated list of IDs only, e.g. "12,45,67". If none, return "none".',
                            'user_message'    => '{review_decisions}',
                            'store_output_as' => 'approve_ids',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-has-approvals',
                                    'name'       => 'Posts to Approve',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{approve_ids}',
                                                    'operator' => 'not_equals',
                                                    'value'    => 'none',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => "Auto-approver review complete.\n\nDecisions:\n{review_decisions}",
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-nothing',
                                    'name'       => 'No Pending Posts (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'social_analytics_digest' => [
            'label'       => 'Social Media Analytics Digest',
            'description' => 'Pulls social post performance data every Monday, analyzes top and bottom performers with AI, and sends actionable growth tips.',
            'icon'        => 'tabler-chart-line',
            'color'		     => '#cecff2',
            'tags'        => ['social_media', 'reports'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Social Media Analytics Digest',
                'description'  => 'Weekly social post performance analysis delivered every Monday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1',
                'steps'        => [
                    [
                        'type'   => 'social_media_agent',
                        'config' => [
                            'action_event'    => 'get_social_post_analytics',
                            'lookback_days'   => 7,
                            'store_output_as' => 'analytics_data',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a social media analyst. Analyze the post performance data and produce a weekly digest with: **Top 3 Posts** (why they worked), **Bottom 3 Posts** (what to fix), **Engagement Trends** (patterns you notice), and **3 Specific Recommendations** to improve next week\'s performance. Be concrete and data-driven.',
                            'user_message'    => '{analytics_data}',
                            'store_output_as' => 'analytics_digest',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => '{analytics_digest}',
                        ],
                    ],
                ],
            ],
        ],

        'chatbot_ticket_triage' => [
            'label'       => 'Chatbot Ticket Triage',
            'description' => 'Scans open support tickets every hour, classifies urgency with AI, alerts you about critical ones, and auto-closes resolved conversations.',
            'icon'        => 'tabler-ticket',
            'color'		     => '#cecff2',
            'tags'        => ['chatbot'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Chatbot Ticket Triage',
                'description'  => 'Hourly autonomous triage of open chatbot support tickets.',
                'trigger_type' => 'schedule',
                'cron'         => '0 * * * *',
                'steps'        => [
                    [
                        'type'   => 'external_chatbot',
                        'config' => [
                            'action_event'    => 'list_chatbot_conversations',
                            'ticket_status'   => 'open',
                            'limit'           => 20,
                            'store_output_as' => 'open_tickets',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a support triage specialist. Review the open ticket list. Classify each by urgency: "critical" (angry customer, billing issue, outage report, data loss), "resolved" (conversation clearly finished, user said thanks/bye), or "normal" (standard inquiry, in progress). Return JSON: [{"conversation_id": "abc", "urgency": "critical|resolved|normal", "summary": "one sentence"}]. If no tickets return [].',
                            'user_message'    => '{open_tickets}',
                            'store_output_as' => 'triage_results',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'From the triage JSON provided, extract only items where urgency is "critical". Return a human-readable summary of critical tickets: list each with conversation_id and its summary. If none exist, return "none".',
                            'user_message'    => '{triage_results}',
                            'store_output_as' => 'critical_summary',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-critical',
                                    'name'       => 'Critical Tickets Found',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{critical_summary}',
                                                    'operator' => 'not_equals',
                                                    'value'    => 'none',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => "🚨 Critical tickets require immediate attention:\n\n{critical_summary}",
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-clear',
                                    'name'       => 'No Critical Tickets (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'chatbot_weekly_insights' => [
            'label'       => 'Chatbot Weekly Insights',
            'description' => 'Analyzes chatbot performance data every Monday and emails a report with top pain points, drop-off patterns, and CSAT signals via Gmail.',
            'icon'        => 'tabler-message-dots',
            'color'		     => '#cecff2',
            'tags'        => ['chatbot', 'reports', 'gmail'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Chatbot Weekly Insights',
                'description'  => 'Weekly chatbot analytics insight report emailed via Gmail every Monday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1',
                'steps'        => [
                    [
                        'type'   => 'external_chatbot',
                        'config' => [
                            'action_event'    => 'get_chatbot_analytics',
                            'store_output_as' => 'chatbot_analytics',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a customer experience analyst. Analyze the chatbot analytics data and write a weekly insights report with sections: **Volume & Engagement** (conversation counts, avg length), **Top User Pain Points** (most common issues raised), **Drop-off Signals** (where users disengage), **Satisfaction Indicators** (positive vs negative sentiment patterns), and **Top 3 Improvements** to boost chatbot effectiveness. Be specific and actionable.',
                            'user_message'    => '{chatbot_analytics}',
                            'store_output_as' => 'insights_report',
                        ],
                    ],
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'send_email',
                            'to'              => '{sender_id}',
                            'subject'         => 'Weekly Chatbot Insights Report',
                            'body'            => '{insights_report}',
                            'store_output_as' => 'gmail_result',
                        ],
                    ],
                ],
            ],
        ],

        'campaign_from_chatbot_pain_points' => [
            'label'       => 'Campaign from Chatbot Pain Points',
            'description' => 'Mines chatbot conversations every Friday for recurring pain points, then auto-creates a targeted marketing campaign to address them.',
            'icon'        => 'tabler-speakerphone',
            'color'		     => '#cecff2',
            'tags'        => ['chatbot', 'marketing'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Campaign from Chatbot Pain Points',
                'description'  => 'Turns chatbot pain points into a marketing campaign automatically every Friday at 3pm.',
                'trigger_type' => 'schedule',
                'cron'         => '0 15 * * 5',
                'steps'        => [
                    [
                        'type'   => 'external_chatbot',
                        'config' => [
                            'action_event'    => 'get_chatbot_analytics',
                            'store_output_as' => 'chatbot_data',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a marketing strategist. From the chatbot analytics data, identify the top 3 recurring customer pain points or objections. For each, write a short campaign message (2–3 sentences) that directly addresses the pain point with an empathetic, benefit-focused angle. Output as a numbered list.',
                            'user_message'    => '{chatbot_data}',
                            'store_output_as' => 'campaign_angles',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a copywriter. Combine the campaign angles into a single, cohesive marketing campaign message. Lead with the most compelling pain point, use a conversational tone, include a clear call-to-action at the end. Max 200 words.',
                            'user_message'    => '{campaign_angles}',
                            'store_output_as' => 'campaign_content',
                        ],
                    ],
                    [
                        'type'   => 'marketing_bot',
                        'config' => [
                            'action_event'    => 'create_marketing_campaign',
                            'name'            => 'Pain Point Campaign',
                            'content'         => '{campaign_content}',
                            'store_output_as' => 'campaign_result',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => "Campaign created from chatbot pain points:\n\n{campaign_content}\n\nResult: {campaign_result}",
                        ],
                    ],
                ],
            ],
        ],

        'marketing_stats_alert' => [
            'label'       => 'Marketing Campaign Performance Alert',
            'description' => 'Checks marketing campaign stats every day, flags underperformers with AI analysis, and sends targeted alerts or positive summaries.',
            'icon'        => 'tabler-alert-triangle',
            'color'		     => '#cecff2',
            'tags'        => ['marketing'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Marketing Campaign Performance Alert',
                'description'  => 'Daily marketing performance watchdog — alerts on underperformers, celebrates wins.',
                'trigger_type' => 'schedule',
                'cron'         => '0 10 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'marketing_bot',
                        'config' => [
                            'action_event'    => 'get_marketing_stats',
                            'period'          => 'last_7_days',
                            'store_output_as' => 'marketing_stats',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a marketing analyst. Review the campaign stats and assess overall performance. Reply with exactly one word: "underperforming" if open rates, clicks, or conversions are significantly below average — or "good" if performance is on track or better. Nothing else.',
                            'user_message'    => '{marketing_stats}',
                            'store_output_as' => 'performance_verdict',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-underperforming',
                                    'name'       => 'Underperforming',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{performance_verdict}',
                                                    'operator' => 'contains',
                                                    'value'    => 'underperforming',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a marketing consultant. Based on the campaign stats, identify the specific underperforming metrics and write a concise alert with: which campaigns are struggling, likely reasons, and 3 quick fixes to try today.',
                                                'user_message'    => '{marketing_stats}',
                                                'store_output_as' => 'alert_message',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => "⚠️ Marketing Alert — Campaigns Need Attention:\n\n{alert_message}",
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-good',
                                    'name'       => 'Good Performance (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a marketing analyst. Write a brief, upbeat 2-sentence performance summary highlighting the strongest metric from the data.',
                                                'user_message'    => '{marketing_stats}',
                                                'store_output_as' => 'good_summary',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => "✅ Campaigns performing well this week:\n\n{good_summary}",
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'campaign_from_channel_brief' => [
            'label'       => 'Campaign from Channel Brief',
            'description' => 'Takes a plain-language campaign brief from an incoming channel message, expands it into full campaign copy with AI, and creates the campaign.',
            'icon'        => 'tabler-pennant',
            'color'		     => '#cecff2',
            'tags'        => ['marketing'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Campaign from Channel Brief',
                'description'  => 'Converts natural language briefs from channel messages into marketing campaigns.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a senior marketing copywriter. The user has sent a brief campaign idea or instruction. Expand it into polished campaign copy: a compelling subject line, an engaging opening paragraph (2–3 sentences), a value proposition, a clear call-to-action, and a closing line. Keep the total under 250 words. Output only the campaign copy, nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'campaign_copy',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Extract the subject line from the campaign copy provided. It is the first line or the line starting with "Subject:". Return only the subject line text, nothing else.',
                            'user_message'    => '{campaign_copy}',
                            'store_output_as' => 'campaign_name',
                        ],
                    ],
                    [
                        'type'   => 'marketing_bot',
                        'config' => [
                            'action_event'    => 'create_marketing_campaign',
                            'name'            => '{campaign_name}',
                            'content'         => '{campaign_copy}',
                            'store_output_as' => 'campaign_result',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => "Campaign created!\n\n{campaign_copy}\n\nResult: {campaign_result}",
                        ],
                    ],
                ],
            ],
        ],

        'lead_to_outlook_contact' => [
            'label'       => 'Lead to Outlook Contact & Meeting',
            'description' => 'Extracts contact details from an incoming channel message, saves them as an Outlook contact, and books an intro call on your calendar.',
            'icon'        => 'tabler-address-book',
            'color'		     => '#cecff2',
            'tags'        => ['outlook', 'marketing'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Lead to Outlook Contact & Meeting',
                'description'  => 'Captures leads from channel messages as Outlook contacts with an auto-scheduled intro call.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Extract contact information from the message. Return a JSON object with these keys (use null if not found): {"given_name": "", "surname": "", "email": "", "phone": "", "company": "", "job_title": ""}. Return only valid JSON, nothing else.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'contact_json',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Determine if this message contains enough information to create a contact (at minimum a name or email is needed). Reply with exactly one word: "create" if sufficient info exists, or "skip" if not. Nothing else.',
                            'user_message'    => '{contact_json}',
                            'store_output_as' => 'contact_decision',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-create-contact',
                                    'name'       => 'Create Contact & Event',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{contact_decision}',
                                                    'operator' => 'contains',
                                                    'value'    => 'create',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'outlook',
                                            'config' => [
                                                'action_event'    => 'create_contact',
                                                'given_name'      => '{contact_json}',
                                                'email'           => '{contact_json}',
                                                'company'         => '{contact_json}',
                                                'job_title'       => '{contact_json}',
                                                'store_output_as' => 'contact_result',
                                            ],
                                        ],
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'Write a brief, warm confirmation message (2 sentences) telling the user that their contact has been saved to Outlook and an intro call will be scheduled. Mention the contact name if available from the JSON.',
                                                'user_message'    => '{contact_json}',
                                                'store_output_as' => 'confirmation',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{confirmation}',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-skip',
                                    'name'       => 'Insufficient Info (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => 'Could not extract contact details. Please share a name, email, or company to create a contact.',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'meeting_scheduler' => [
            'label'       => 'Natural Language Meeting Scheduler',
            'description' => 'Parses a plain-language meeting request from a channel message and creates the event on your Outlook calendar automatically.',
            'icon'        => 'tabler-calendar-plus',
            'color'		     => '#cecff2',
            'tags'        => ['outlook'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Natural Language Meeting Scheduler',
                'description'  => 'Converts channel message meeting requests into Outlook calendar events.',
                'trigger_type' => 'channel_message',
                'steps'        => [
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Parse the meeting request and return a JSON object with: {"subject": "", "start": "ISO8601", "end": "ISO8601", "timezone": "UTC", "location": "", "attendees": "", "is_meeting_request": true/false}. Use today\'s date as reference if only a time is given. Default duration 1 hour if end not specified. If the message is not a meeting request set is_meeting_request to false. Return only valid JSON.',
                            'user_message'    => '{message_text}',
                            'store_output_as' => 'meeting_json',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'Check the JSON. If is_meeting_request is true reply "schedule". If false reply "skip". Nothing else.',
                            'user_message'    => '{meeting_json}',
                            'store_output_as' => 'schedule_decision',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-schedule',
                                    'name'       => 'Schedule Meeting',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{schedule_decision}',
                                                    'operator' => 'contains',
                                                    'value'    => 'schedule',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'outlook',
                                            'config' => [
                                                'action_event'    => 'create_event',
                                                'subject'         => '{meeting_json}',
                                                'start'           => '{meeting_json}',
                                                'end'             => '{meeting_json}',
                                                'timezone'        => 'UTC',
                                                'location'        => '{meeting_json}',
                                                'attendees'       => '{meeting_json}',
                                                'store_output_as' => 'event_result',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => "📅 Meeting scheduled on Outlook!\n\nDetails: {event_result}",
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-not-meeting',
                                    'name'       => 'Not a Meeting Request (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a helpful assistant. Respond to the user\'s message naturally.',
                                                'user_message'    => '{message_text}',
                                                'store_output_as' => 'ai_response',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => '{ai_response}',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'gmail_to_social_post' => [
            'label'       => 'Gmail Newsletter to Social Post',
            'description' => 'Reads unread newsletters and insight emails from Gmail every morning, extracts the best insight, and queues a social post from it.',
            'icon'        => 'tabler-brand-gmail',
            'color'		     => '#cecff2',
            'tags'        => ['gmail', 'social_media'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Gmail Newsletter to Social Post',
                'description'  => 'Repurposes Gmail newsletter insights as social media posts every weekday at 9am.',
                'trigger_type' => 'schedule',
                'cron'         => '0 9 * * 1-5',
                'steps'        => [
                    [
                        'type'   => 'gmail',
                        'config' => [
                            'action_event'    => 'find_email',
                            'query'           => 'is:unread newer_than:1d category:updates OR category:promotions',
                            'max_results'     => 10,
                            'return_format'   => 'full_as_string',
                            'store_output_as' => 'newsletter_emails',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a content curator. Read through the email list and identify the single most interesting, shareable insight, statistic, or trend. Extract it as a 1–2 sentence key insight. If no relevant content found, return "no_content".',
                            'user_message'    => '{newsletter_emails}',
                            'store_output_as' => 'key_insight',
                        ],
                    ],
                    [
                        'type'   => 'path',
                        'config' => [
                            'paths' => [
                                [
                                    'id'         => 'path-has-content',
                                    'name'       => 'Insight Found',
                                    'ruleGroups' => [
                                        [
                                            'rules' => [
                                                [
                                                    'field'    => '{key_insight}',
                                                    'operator' => 'not_equals',
                                                    'value'    => 'no_content',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'steps' => [
                                        [
                                            'type'   => 'ai_call',
                                            'config' => [
                                                'system_prompt'   => 'You are a social media copywriter. Transform the insight into a compelling social post: strong hook, 2–3 punchy sentences expanding on the insight, end with an engaging question, include 3–5 hashtags. Max 280 characters for the main text. Output only the post text.',
                                                'user_message'    => '{key_insight}',
                                                'store_output_as' => 'post_content',
                                            ],
                                        ],
                                        [
                                            'type'   => 'social_media_agent',
                                            'config' => [
                                                'action_event'    => 'create_social_post',
                                                'content'         => '{post_content}',
                                                'post_type'       => 'text',
                                                'store_output_as' => 'post_result',
                                            ],
                                        ],
                                        [
                                            'type'   => 'send_message',
                                            'config' => [
                                                'recipient_id' => '{sender_id}',
                                                'message'      => "📧→📱 Social post queued from your inbox:\n\n{post_content}",
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'id'         => 'path-no-content',
                                    'name'       => 'No Relevant Email (Fallback)',
                                    'ruleGroups' => [],
                                    'steps'      => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'chatbot_convo_to_marketing' => [
            'label'       => 'Chatbot Conversations to Marketing Campaign',
            'description' => 'Every week mines chatbot conversations for FAQs and objections, then auto-creates a marketing campaign that addresses them head-on.',
            'icon'        => 'tabler-refresh',
            'color'		     => '#cecff2',
            'tags'        => ['chatbot', 'marketing'],
            'featured'    => false,
            'config'      => [
                'name'         => 'Chatbot Conversations to Marketing Campaign',
                'description'  => 'Closes the sales loop — turns chatbot objections into targeted campaigns every Friday at 2pm.',
                'trigger_type' => 'schedule',
                'cron'         => '0 14 * * 5',
                'steps'        => [
                    [
                        'type'   => 'external_chatbot',
                        'config' => [
                            'action_event'    => 'list_chatbot_conversations',
                            'limit'           => 30,
                            'store_output_as' => 'recent_conversations',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a customer research analyst. Analyze the chatbot conversations. Identify: 1) the top 3 FAQs (questions asked most often), 2) the top 3 objections or hesitations customers expressed, 3) any feature requests or missing information that caused confusion. Output a structured summary under these 3 headings.',
                            'user_message'    => '{recent_conversations}',
                            'store_output_as' => 'conversation_insights',
                        ],
                    ],
                    [
                        'type'   => 'ai_call',
                        'config' => [
                            'system_prompt'   => 'You are a marketing copywriter. Using the customer insights provided, write a targeted email marketing campaign that proactively addresses the top FAQs and objections. Structure: subject line, personalized opening, answer the top 2 FAQs as benefit statements, handle the top objection with social proof or reassurance, strong CTA. Max 200 words. Output only the campaign copy.',
                            'user_message'    => '{conversation_insights}',
                            'store_output_as' => 'campaign_copy',
                        ],
                    ],
                    [
                        'type'   => 'marketing_bot',
                        'config' => [
                            'action_event'    => 'create_marketing_campaign',
                            'name'            => 'FAQ & Objection Handler Campaign',
                            'content'         => '{campaign_copy}',
                            'store_output_as' => 'campaign_result',
                        ],
                    ],
                    [
                        'type'   => 'send_message',
                        'config' => [
                            'recipient_id' => '{sender_id}',
                            'message'      => "Campaign created from chatbot insights:\n\n**Insights found:**\n{conversation_insights}\n\n**Campaign drafted and saved.**",
                        ],
                    ],
                ],
            ],
        ],
    ],
];
