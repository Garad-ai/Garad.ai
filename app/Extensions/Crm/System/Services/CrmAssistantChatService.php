<?php

declare(strict_types=1);

namespace App\Extensions\Crm\System\Services;

use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\CrmProject;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Extensions\Crm\System\Models\SalesInvoice;

class CrmAssistantChatService
{
    /**
     * Context scopes selectable from the chat header. `all` keeps the historical
     * behaviour of sending the entire CRM snapshot.
     */
    public const SCOPES = [
        'all'           => 'All CRM Data',
        'contacts'      => 'Contacts',
        'companies'     => 'Companies',
        'deals'         => 'Deals',
        'tasks'         => 'Tasks',
        'projects'      => 'Projects',
        'invoices'      => 'Invoices',
        'presentations' => 'Presentations',
    ];

    /**
     * Collections that are always sent regardless of scope, because the action
     * protocol needs their IDs to resolve relationship fields.
     */
    private const ALWAYS_INCLUDED = ['deal_stages'];

    public static function scopes(): array
    {
        return self::SCOPES;
    }

    public static function normalizeScope(?string $scope): string
    {
        return array_key_exists((string) $scope, self::SCOPES) ? (string) $scope : 'all';
    }

    public function systemPrompt(int $userId, string $scope = 'all'): string
    {
        $context = $this->buildCrmContext($userId, self::normalizeScope($scope));

        return $this->buildSystemPrompt($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCrmContext(int $userId, string $scope = 'all'): array
    {
        $scope = self::normalizeScope($scope);

        $contacts = $this->contacts($userId);
        $companies = $this->companies($userId);
        $dealStages = $this->dealStages($userId);
        $deals = $this->deals($userId);
        $projects = $this->projects($userId);
        $tasks = $this->tasks($userId);
        $presentations = $this->presentations($userId);
        $invoices = $this->invoices($userId);

        $context = [
            'contacts'      => $contacts->toArray(),
            'companies'     => $companies->toArray(),
            'deal_stages'   => $dealStages->toArray(),
            'deals'         => $deals->toArray(),
            'projects'      => $projects->toArray(),
            'tasks'         => $tasks->toArray(),
            'invoices'      => $invoices->toArray(),
            'presentations' => $presentations->toArray(),
            'summary'       => [
                'total_contacts'      => $contacts->count(),
                'total_companies'     => $companies->count(),
                'total_deals'         => $deals->count(),
                'total_projects'      => $projects->count(),
                'total_invoices'      => $invoices->count(),
                'total_presentations' => $presentations->count(),
                'total_pipeline'      => $deals->sum('value'),
                'total_invoiced'      => $invoices->sum('total'),
                'total_outstanding'   => $invoices->sum('balance_due'),
                'pending_tasks'       => $tasks->where('status', 'pending')->count(),
                'overdue_tasks'       => $tasks->filter(fn ($t) => $t['status'] === 'pending' && $t['due_date'] && $t['due_date'] < now()->format('Y-m-d'))->count(),
            ],
        ];

        return $this->applyScope($context, $scope);
    }

    /**
     * Trim the context down to the selected module. The summary block always
     * survives so the assistant can still answer "how many …" questions.
     *
     * @param  array<string, mixed>  $context
     *
     * @return array<string, mixed>
     */
    private function applyScope(array $context, string $scope): array
    {
        if ($scope === 'all') {
            return $context;
        }

        $keep = array_merge([$scope, 'summary'], self::ALWAYS_INCLUDED);

        foreach (array_keys($context) as $key) {
            if (! in_array($key, $keep, true)) {
                $context[$key] = [];
            }
        }

        return $context;
    }

    private function contacts(int $userId)
    {
        return CrmContact::where('user_id', $userId)
            ->with('company')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'first_name'     => $c->first_name,
                'last_name'      => $c->last_name,
                'name'           => $c->full_name,
                'email'          => $c->email,
                'phone'          => $c->phone,
                'job_title'      => $c->job_title,
                'crm_company_id' => $c->crm_company_id,
                'company'        => $c->company?->name,
                'status'         => $c->status,
            ]);
    }

    private function companies(int $userId)
    {
        return CrmCompany::where('user_id', $userId)
            ->get()
            ->map(fn ($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'industry' => $c->industry,
                'email'    => $c->email,
                'phone'    => $c->phone,
            ]);
    }

    private function dealStages(int $userId)
    {
        return CrmDealStage::where('user_id', $userId)
            ->orderBy('order')
            ->get()
            ->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name,
            ]);
    }

    private function deals(int $userId)
    {
        return CrmDeal::where('user_id', $userId)
            ->with(['stage', 'contact', 'company'])
            ->get()
            ->map(fn ($d) => [
                'id'                  => $d->id,
                'title'               => $d->title,
                'value'               => $d->value,
                'currency'            => $d->currency,
                'crm_deal_stage_id'   => $d->crm_deal_stage_id,
                'stage'               => $d->stage?->name,
                'crm_contact_id'      => $d->crm_contact_id,
                'contact'             => $d->contact?->full_name,
                'crm_company_id'      => $d->crm_company_id,
                'company'             => $d->company?->name,
                'expected_close_date' => $d->expected_close_date?->format('Y-m-d'),
            ]);
    }

    private function projects(int $userId)
    {
        return CrmProject::where('user_id', $userId)
            ->with(['contact', 'company', 'deal'])
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'status'         => $p->status,
                'priority'       => $p->priority,
                'category'       => $p->category,
                'crm_contact_id' => $p->crm_contact_id,
                'contact'        => $p->contact?->full_name,
                'crm_company_id' => $p->crm_company_id,
                'company'        => $p->company?->name,
                'crm_deal_id'    => $p->crm_deal_id,
                'deal'           => $p->deal?->title,
                'start_date'     => $p->start_date?->format('Y-m-d'),
                'due_date'       => $p->due_date?->format('Y-m-d'),
                'budget'         => $p->budget,
            ]);
    }

    private function tasks(int $userId)
    {
        return CrmTask::where('user_id', $userId)
            ->with(['contact', 'deal', 'project'])
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'title'          => $t->title,
                'type'           => $t->type,
                'status'         => $t->status,
                'priority'       => $t->priority,
                'due_date'       => $t->due_date?->format('Y-m-d'),
                'crm_contact_id' => $t->crm_contact_id,
                'contact'        => $t->contact?->full_name,
                'crm_deal_id'    => $t->crm_deal_id,
                'deal'           => $t->deal?->title,
                'crm_project_id' => $t->crm_project_id,
                'project'        => $t->project?->name,
            ]);
    }

    private function presentations(int $userId)
    {
        return CrmPresentation::where('user_id', $userId)
            ->with(['deal'])
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'title'            => $p->title,
                'status'           => $p->status,
                'style'            => $p->style,
                'crm_deal_id'      => $p->crm_deal_id,
                'deal'             => $p->deal?->title,
                'total_slides'     => $p->total_slides,
                'completed_slides' => $p->completed_slides,
                'created_at'       => $p->created_at->format('Y-m-d H:i'),
            ]);
    }

    private function invoices(int $userId)
    {
        return SalesInvoice::where('user_id', $userId)
            ->with(['contact', 'company', 'deal', 'items'])
            ->get()
            ->map(fn ($inv) => [
                'id'             => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'status'         => $inv->status,
                'issue_date'     => $inv->issue_date?->format('Y-m-d'),
                'due_date'       => $inv->due_date?->format('Y-m-d'),
                'subtotal'       => (float) $inv->subtotal,
                'tax_rate'       => (float) $inv->tax_rate,
                'total'          => (float) $inv->total,
                'amount_paid'    => (float) $inv->amount_paid,
                'balance_due'    => $inv->balance_due,
                'currency'       => $inv->currency,
                'discount_type'  => $inv->discount_type,
                'discount_value' => (float) $inv->discount_value,
                'crm_contact_id' => $inv->crm_contact_id,
                'contact'        => $inv->contact?->full_name,
                'crm_company_id' => $inv->crm_company_id,
                'company'        => $inv->company?->name,
                'crm_deal_id'    => $inv->crm_deal_id,
                'deal'           => $inv->deal?->title,
                'items_count'    => $inv->items->count(),
                'items'          => $inv->items->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity'    => (float) $item->quantity,
                    'unit_price'  => (float) $item->unit_price,
                    'unit'        => $item->unit,
                    'total'       => (float) $item->total,
                ])->toArray(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildSystemPrompt(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        return <<<PROMPT
You are an intelligent CRM assistant. You can answer questions about the user's CRM data AND perform actions (create, update, delete) on contacts, companies, deals, tasks, projects, invoices, and presentations.

RESPONSE FORMAT:
- Always provide a human-readable message explaining what you found or what you propose to do.
- When the user requests a create, update, or delete action, include a structured action block in your response using this exact format:

<<<ACTIONS>>>
[
  {
    "action": "create",
    "entity": "contact",
    "id": null,
    "data": { "first_name": "Jane", "last_name": "Smith" },
    "summary": "Create contact Jane Smith"
  }
]
<<<END_ACTIONS>>>

RULES:
1. For CREATE: set "id" to null. Populate "data" with the entity fields.
2. For UPDATE: set "id" to the entity's ID from the data below. Only include the fields being changed in "data".
3. For DELETE: set "id" to the entity's ID. Omit "data" or set it to {}.
4. Use entity IDs (not names) for all relationship fields (crm_contact_id, crm_company_id, crm_deal_id, crm_deal_stage_id, crm_project_id). Look up the correct IDs from the CRM data below.
5. If you cannot find or resolve a reference (e.g., no matching contact name), explain the issue in your message and do NOT include the action block.
6. You may include multiple actions in one response.
7. If the user only asks a question (no create/update/delete intent), respond normally WITHOUT the action block.
8. Always include a short "summary" for each action describing what it does.

ENTITY FIELDS:

Contact: first_name (required), last_name (required), email, phone, job_title, crm_company_id, status (active|inactive), notes
Company: name (required), industry, website, phone, email, address, city, state, country, notes
Deal: title (required), crm_deal_stage_id (required — use deal_stages list below), crm_contact_id, crm_company_id, value (number), currency, expected_close_date (YYYY-MM-DD), description
Task: title (required), description, type (task|call|meeting|email|follow_up), status (pending|completed|cancelled), priority (low|medium|high), due_date (YYYY-MM-DD), crm_contact_id, crm_deal_id, crm_project_id
Project: name (required), description, status (not_started|in_progress|on_hold|completed|cancelled), priority (low|medium|high|urgent), category, start_date (YYYY-MM-DD), due_date (YYYY-MM-DD), budget (number), currency, notes, crm_contact_id, crm_company_id, crm_deal_id
Invoice: crm_contact_id, crm_company_id, crm_deal_id, status (draft|sent|paid|partial|overdue|cancelled), issue_date (YYYY-MM-DD), due_date (YYYY-MM-DD), currency, discount_type (fixed|percentage), discount_value (number), tax_rate (number, 0-100), notes, from_name, from_email, from_phone, from_address, items (array of line items)
Presentation: crm_deal_id (required — must reference an existing deal), style (optional: corporate|modern|minimal|creative)

INVOICE ITEMS FORMAT:
Each item in the "items" array must have: description (required), quantity (required, number), unit_price (required, number), unit (optional, e.g. "hours", "pcs", "units")
Example:
{
  "action": "create",
  "entity": "invoice",
  "id": null,
  "data": {
    "crm_contact_id": 1,
    "crm_company_id": 2,
    "due_date": "2026-03-15",
    "tax_rate": 10,
    "items": [
      { "description": "Web Development", "quantity": 40, "unit_price": 150, "unit": "hours" },
      { "description": "Domain Registration", "quantity": 1, "unit_price": 15 }
    ]
  },
  "summary": "Create invoice for web development services"
}

INVOICE NOTES:
- Invoice number is auto-generated (do NOT include it in data).
- issue_date defaults to today if not specified.
- due_date defaults to 30 days from today if not specified.
- currency defaults to USD if not specified.
- Subtotal, tax_amount, and total are auto-calculated from items, discount, and tax_rate.
- When updating an invoice and providing "items", ALL existing line items will be replaced with the new ones.
- To update only invoice metadata (status, due_date, notes, etc.) without changing line items, omit the "items" field.

PRESENTATION NOTES:
- Presentations generate an AI-powered pitch deck with 5-8 slide images for a deal.
- Only CREATE and DELETE are supported. Presentations cannot be updated.
- To create: only provide crm_deal_id (required) and optionally style. The system auto-generates all slide content and images.
- The generation takes 30-60 seconds. The user can view progress on the presentations page.
- Example: {"action":"create","entity":"presentation","id":null,"data":{"crm_deal_id":5,"style":"modern"},"summary":"Generate a modern pitch deck for the Acme deal"}

Format your text responses clearly. Use bullet points or short paragraphs. Do not use markdown code blocks unless asked.

Here is the user's CRM data:

{$json}
PROMPT;
    }
}
