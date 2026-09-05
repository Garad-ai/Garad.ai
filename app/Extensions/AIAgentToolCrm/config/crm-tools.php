<?php

use App\Extensions\AIAgentToolCrm\System\Actions\Tools\AddNoteAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\CreateCompanyAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\CreateContactAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\CreateDealAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\CreateTaskAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\GetCrmAnalyticsAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\ListCompaniesAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\ListContactsAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\ListDealsAction;
use App\Extensions\AIAgentToolCrm\System\Actions\Tools\MoveDealStageAction;

return [
    'list_contacts' => [
        'label'       => 'List Contacts',
        'description' => 'Returns the user\'s CRM contacts, optionally filtered by a search term over name and email. Call this to discover contact IDs before using other CRM tools.',
        'action'      => ListContactsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Filter by first/last name or email.'],
                'limit'  => ['type' => 'integer', 'description' => 'Maximum number of results (default: 10, max: 50).'],
            ],
            'required'   => [],
        ],
        'default_config' => [
            'limit'           => 10,
            'store_output_as' => 'crm_contacts_list',
        ],
    ],

    'create_contact' => [
        'label'       => 'Create Contact',
        'description' => 'Creates a new CRM contact for the user. Optionally link it to a company via company_id.',
        'action'      => CreateContactAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'first_name' => ['type' => 'string', 'description' => 'Contact first name.'],
                'last_name'  => ['type' => 'string', 'description' => 'Contact last name.'],
                'email'      => ['type' => 'string', 'description' => 'Contact email address.'],
                'phone'      => ['type' => 'string', 'description' => 'Contact phone number.'],
                'job_title'  => ['type' => 'string', 'description' => 'Contact job title.'],
                'notes'      => ['type' => 'string', 'description' => 'Free-form notes.'],
                'company_id' => ['type' => 'integer', 'description' => 'Related company ID.'],
            ],
            'required'   => ['first_name'],
        ],
        'default_config' => [
            'store_output_as' => 'created_contact',
        ],
    ],

    'list_companies' => [
        'label'       => 'List Companies',
        'description' => 'Returns the user\'s CRM companies, optionally filtered by a search term over name and email.',
        'action'      => ListCompaniesAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'search' => ['type' => 'string', 'description' => 'Filter by name or email.'],
                'limit'  => ['type' => 'integer', 'description' => 'Maximum number of results (default: 10, max: 50).'],
            ],
            'required'   => [],
        ],
        'default_config' => [
            'limit'           => 10,
            'store_output_as' => 'crm_companies_list',
        ],
    ],

    'create_company' => [
        'label'       => 'Create Company',
        'description' => 'Creates a new CRM company for the user.',
        'action'      => CreateCompanyAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'name'     => ['type' => 'string', 'description' => 'Company name.'],
                'industry' => ['type' => 'string', 'description' => 'Industry.'],
                'website'  => ['type' => 'string', 'description' => 'Website URL.'],
                'email'    => ['type' => 'string', 'description' => 'Company email.'],
                'phone'    => ['type' => 'string', 'description' => 'Company phone.'],
                'city'     => ['type' => 'string', 'description' => 'City.'],
                'country'  => ['type' => 'string', 'description' => 'Country.'],
                'notes'    => ['type' => 'string', 'description' => 'Free-form notes.'],
            ],
            'required'   => ['name'],
        ],
        'default_config' => [
            'store_output_as' => 'created_company',
        ],
    ],

    'list_deals' => [
        'label'       => 'List Deals',
        'description' => 'Returns the user\'s CRM deals, optionally filtered by pipeline stage. Use to browse the deal pipeline.',
        'action'      => ListDealsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'stage_id' => ['type' => 'integer', 'description' => 'Filter by deal stage ID.'],
                'limit'    => ['type' => 'integer', 'description' => 'Maximum number of results (default: 10, max: 50).'],
            ],
            'required'   => [],
        ],
        'default_config' => [
            'limit'           => 10,
            'store_output_as' => 'crm_deals_list',
        ],
    ],

    'create_deal' => [
        'label'       => 'Create Deal',
        'description' => 'Creates a new CRM deal. Default pipeline stages are created automatically if the user has none, and the deal lands in the first stage unless stage_id is given.',
        'action'      => CreateDealAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'title'               => ['type' => 'string', 'description' => 'Deal title.'],
                'value'               => ['type' => 'number', 'description' => 'Monetary value of the deal.'],
                'currency'            => ['type' => 'string', 'description' => 'Currency code (default: USD).'],
                'stage_id'            => ['type' => 'integer', 'description' => 'Pipeline stage ID. Defaults to the first stage.'],
                'contact_id'          => ['type' => 'integer', 'description' => 'Related contact ID.'],
                'company_id'          => ['type' => 'integer', 'description' => 'Related company ID.'],
                'expected_close_date' => ['type' => 'string', 'description' => 'Expected close date (YYYY-MM-DD).'],
                'description'         => ['type' => 'string', 'description' => 'Deal description.'],
            ],
            'required'   => ['title'],
        ],
        'default_config' => [
            'currency'        => 'USD',
            'store_output_as' => 'created_deal',
        ],
    ],

    'move_deal_stage' => [
        'label'       => 'Move Deal Stage',
        'description' => 'Moves a deal to a different pipeline stage. Stage-change history is recorded automatically. Use list_deals and get_crm_analytics to find deal and stage IDs.',
        'action'      => MoveDealStageAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'deal_id'    => ['type' => 'integer', 'description' => 'The deal to move. Overrides deal_title when set.'],
                'deal_title' => ['type' => 'string', 'description' => 'Deal matched by title when the ID is unknown; must already exist.'],
                'stage_id'   => ['type' => 'integer', 'description' => 'The target pipeline stage. Overrides stage_name when set.'],
                'stage_name' => ['type' => 'string', 'description' => 'Stage matched by name when the ID is unknown; must already exist.'],
            ],
            'required'   => [],
        ],
        'default_config' => [
            'store_output_as' => 'moved_deal',
        ],
    ],

    'create_task' => [
        'label'       => 'Create Task',
        'description' => 'Creates a CRM follow-up task, optionally linked to a contact or deal.',
        'action'      => CreateTaskAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'title'       => ['type' => 'string', 'description' => 'Task title.'],
                'description' => ['type' => 'string', 'description' => 'Task description.'],
                'priority'    => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'Task priority (default: medium).'],
                'due_date'    => ['type' => 'string', 'description' => 'Due date/time (ISO 8601 or YYYY-MM-DD).'],
                'contact_id'  => ['type' => 'integer', 'description' => 'Related contact ID.'],
                'deal_id'     => ['type' => 'integer', 'description' => 'Related deal ID.'],
            ],
            'required'   => ['title'],
        ],
        'default_config' => [
            'priority'        => 'medium',
            'store_output_as' => 'created_task',
        ],
    ],

    'add_note' => [
        'label'       => 'Add Note',
        'description' => 'Attaches an activity note (note, call, meeting or email) to a contact or deal.',
        'action'      => AddNoteAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'notable'       => ['type' => 'string', 'enum' => ['contact', 'deal'], 'description' => 'Record type to attach the note to.'],
                'notable_id'    => ['type' => 'integer', 'description' => 'ID of the contact or deal. Overrides record_name when set.'],
                'record_name'   => ['type' => 'string', 'description' => 'Contact full name or deal title when the ID is unknown. Contacts are matched or created; deals must already exist.'],
                'contact_email' => ['type' => 'string', 'description' => 'Email used to match an existing contact before creating one by name.'],
                'type'          => ['type' => 'string', 'enum' => ['note', 'call', 'meeting', 'email'], 'description' => 'Note type (default: note).'],
                'scheduled_at'  => ['type' => 'string', 'description' => 'Date/time for call and meeting notes (ISO 8601 or YYYY-MM-DD HH:MM).'],
                'content'       => ['type' => 'string', 'description' => 'Note content.'],
            ],
            'required'   => ['notable', 'content'],
        ],
        'default_config' => [
            'type'            => 'note',
            'store_output_as' => 'created_note',
        ],
    ],

    'get_crm_analytics' => [
        'label'       => 'Get CRM Analytics',
        'description' => 'Returns a pipeline snapshot: deal counts and total value per stage, plus totals for deals, contacts, companies and open tasks.',
        'action'      => GetCrmAnalyticsAction::class,
        'parameters'  => [
            'type'       => 'object',
            'properties' => [],
            'required'   => [],
        ],
        'default_config' => [
            'store_output_as' => 'crm_analytics',
        ],
    ],
];
