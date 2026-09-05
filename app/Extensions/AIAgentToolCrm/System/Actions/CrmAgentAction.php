<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions;

use App\Extensions\AIAgent\System\Actions\Contracts\AIAgentActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
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
use Exception;

class CrmAgentAction implements AIAgentActionInterface
{
    public function __construct(
        private readonly ListContactsAction $listContactsAction,
        private readonly CreateContactAction $createContactAction,
        private readonly ListCompaniesAction $listCompaniesAction,
        private readonly CreateCompanyAction $createCompanyAction,
        private readonly ListDealsAction $listDealsAction,
        private readonly CreateDealAction $createDealAction,
        private readonly MoveDealStageAction $moveDealStageAction,
        private readonly CreateTaskAction $createTaskAction,
        private readonly AddNoteAction $addNoteAction,
        private readonly GetCrmAnalyticsAction $getCrmAnalyticsAction,
    ) {}

    /**
     * Config keys:
     *   - action_event    (string) : sub-operation to perform (required)
     *   - store_output_as (string) : context key to store results under
     *
     * Per action_event additional keys:
     *   list_contacts:     search (string), limit (int)
     *   create_contact:    first_name (string, req), last_name, email, phone, job_title, notes, company_id (int), company_name (string)
     *   list_companies:    search (string), limit (int)
     *   create_company:    name (string, req), industry, website, email, phone, city, country, notes
     *   list_deals:        stage_id (int), limit (int)
     *   create_deal:       title (string, req), value (float), currency, stage_id (int), contact_id (int), contact_name, contact_email, company_id (int), company_name, expected_close_date (string), description
     *   move_deal_stage:   deal_id (int), deal_title (string), stage_id (int), stage_name (string)
     *   create_task:       title (string, req), description, priority (string), due_date (string), contact_id (int), deal_id (int)
     *   add_note:          notable (contact|deal, req), notable_id (int), record_name (string), contact_email (string), type (note|call|meeting|email), scheduled_at (string), content (string, req)
     *   get_crm_analytics: (none)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $actionEvent = $config['action_event'] ?? '';

        return match ($actionEvent) {
            'list_contacts'     => $this->listContactsAction->execute($config, $context, $workflow, $run),
            'create_contact'    => $this->createContactAction->execute($config, $context, $workflow, $run),
            'list_companies'    => $this->listCompaniesAction->execute($config, $context, $workflow, $run),
            'create_company'    => $this->createCompanyAction->execute($config, $context, $workflow, $run),
            'list_deals'        => $this->listDealsAction->execute($config, $context, $workflow, $run),
            'create_deal'       => $this->createDealAction->execute($config, $context, $workflow, $run),
            'move_deal_stage'   => $this->moveDealStageAction->execute($config, $context, $workflow, $run),
            'create_task'       => $this->createTaskAction->execute($config, $context, $workflow, $run),
            'add_note'          => $this->addNoteAction->execute($config, $context, $workflow, $run),
            'get_crm_analytics' => $this->getCrmAnalyticsAction->execute($config, $context, $workflow, $run),
            default             => throw new Exception('CrmAgentAction: unknown action_event "' . $actionEvent . '".'),
        };
    }

    public function getCategory(): string
    {
        return 'agents';
    }

    public function getLabel(): string
    {
        return 'CRM Agent';
    }

    public function getDescription(): string
    {
        return 'Interact with the CRM — list and create contacts, companies and deals, move pipeline stages, create tasks and notes, and fetch pipeline analytics.';
    }

    public function getIcon(): string
    {
        return 'tabler-address-book';
    }

    public function getConfigSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['action_event'],
            'properties' => [
                'action_event' => [
                    'type' => 'string',
                    'enum' => [
                        'list_contacts',
                        'create_contact',
                        'list_companies',
                        'create_company',
                        'list_deals',
                        'create_deal',
                        'move_deal_stage',
                        'create_task',
                        'add_note',
                        'get_crm_analytics',
                    ],
                    'description' => 'The CRM operation to perform.',
                ],
                'search'              => ['type' => 'string', 'description' => 'Search term for list operations (name/email).'],
                'limit'               => ['type' => 'integer', 'description' => 'Max results (1–50).'],
                'first_name'          => ['type' => 'string', 'description' => 'Contact first name (create_contact).'],
                'last_name'           => ['type' => 'string', 'description' => 'Contact last name (create_contact).'],
                'email'               => ['type' => 'string', 'description' => 'Email (create_contact / create_company).'],
                'phone'               => ['type' => 'string', 'description' => 'Phone (create_contact / create_company).'],
                'job_title'           => ['type' => 'string', 'description' => 'Contact job title (create_contact).'],
                'notes'               => ['type' => 'string', 'description' => 'Free-text notes stored on the contact (create_contact).'],
                'name'                => ['type' => 'string', 'description' => 'Company name (create_company).'],
                'industry'            => ['type' => 'string', 'description' => 'Company industry (create_company).'],
                'website'             => ['type' => 'string', 'description' => 'Company website (create_company).'],
                'city'                => ['type' => 'string', 'description' => 'Company city (create_company).'],
                'country'             => ['type' => 'string', 'description' => 'Company country (create_company).'],
                'title'               => ['type' => 'string', 'description' => 'Deal/task title (create_deal / create_task).'],
                'value'               => ['type' => 'number', 'description' => 'Deal value (create_deal).'],
                'currency'            => ['type' => 'string', 'description' => 'Deal currency code (create_deal).'],
                'stage_id'            => ['type' => 'integer', 'description' => 'Deal stage ID (list_deals / create_deal / move_deal_stage).'],
                'contact_id'          => ['type' => 'integer', 'description' => 'Related contact ID.'],
                'contact_name'        => ['type' => 'string', 'description' => 'Related contact by full name; matched or created when no contact_id (create_deal).'],
                'contact_email'       => ['type' => 'string', 'description' => 'Email used to match contact_name against existing contacts (create_deal).'],
                'company_id'          => ['type' => 'integer', 'description' => 'Related company ID.'],
                'company_name'        => ['type' => 'string', 'description' => 'Related company by name; matched or created when no company_id (create_contact / create_deal).'],
                'deal_id'             => ['type' => 'integer', 'description' => 'Deal ID (move_deal_stage / create_task).'],
                'deal_title'          => ['type' => 'string', 'description' => 'Deal matched by title when the ID is unknown; must exist (move_deal_stage).'],
                'stage_name'          => ['type' => 'string', 'description' => 'Pipeline stage matched by name when the ID is unknown; must exist (move_deal_stage).'],
                'expected_close_date' => ['type' => 'string', 'description' => 'Deal expected close date (YYYY-MM-DD).'],
                'description'         => ['type' => 'string', 'description' => 'Deal/task description.'],
                'priority'            => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'Task priority (create_task).'],
                'due_date'            => ['type' => 'string', 'description' => 'Task due date/time (create_task).'],
                'notable'             => ['type' => 'string', 'enum' => ['contact', 'deal'], 'description' => 'Record type to attach a note to (add_note).'],
                'notable_id'          => ['type' => 'integer', 'description' => 'ID of the record to attach a note to; overrides record_name (add_note).'],
                'record_name'         => ['type' => 'string', 'description' => 'Contact full name or deal title when the ID is unknown; contacts are matched or created, deals must exist (add_note).'],
                'type'                => ['type' => 'string', 'enum' => ['note', 'call', 'meeting', 'email'], 'description' => 'Note type (add_note).'],
                'scheduled_at'        => ['type' => 'string', 'description' => 'Date/time for call and meeting notes (add_note).'],
                'content'             => ['type' => 'string', 'description' => 'Note content (add_note).'],
                'output_format'       => ['type' => 'string', 'enum' => ['json', 'plain'], 'description' => 'Result format for read operations (list_contacts / list_companies / list_deals / get_crm_analytics). Defaults to json.'],
                'store_output_as'     => ['type' => 'string', 'description' => 'Context key to store results under.'],
            ],
        ];
    }
}
