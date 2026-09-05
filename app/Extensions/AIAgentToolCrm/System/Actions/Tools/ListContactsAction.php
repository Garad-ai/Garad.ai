<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Crm\System\Models\CrmContact;

class ListContactsAction implements ActionInterface
{
    /**
     * Config keys:
     *   - search          (string) : filter by first/last name or email
     *   - limit           (int)    : max results (default 10, max 50)
     *   - output_format   (string) : 'json' (default) | 'plain'
     *   - store_output_as (string) : context key for the result (default: crm_contacts_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'crm_contacts_list';
        $search = trim((string) ($config['search'] ?? ''));
        $limit = min((int) ($config['limit'] ?? 10), 50);
        $outputFormat = $config['output_format'] ?? 'json';

        $contacts = CrmContact::query()
            ->where('user_id', $workflow->user_id)
            ->with('company:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CrmContact $contact): array => [
                'id'         => $contact->id,
                'name'       => trim($contact->first_name . ' ' . $contact->last_name),
                'email'      => $contact->email,
                'phone'      => $contact->phone,
                'job_title'  => $contact->job_title,
                'status'     => $contact->status,
                'company'    => $contact->company?->name,
                'company_id' => $contact->crm_company_id,
            ])
            ->values()
            ->all();

        if ($outputFormat === 'plain') {
            if ($contacts === []) {
                return array_merge($context, [$storeOutputAs => '_No contacts found._']);
            }

            $rows = array_map(
                fn (array $c): string => sprintf(
                    '| %d | %s | %s | %s | %s | %s |',
                    $c['id'],
                    $c['name'] !== '' ? $c['name'] : 'Unnamed',
                    $c['email'] ?? '-',
                    $c['phone'] ?? '-',
                    $c['status'] ?? '-',
                    $c['company'] ?? '-',
                ),
                $contacts,
            );

            $markdown = implode("\n", array_merge([
                '| ID | Name | Email | Phone | Status | Company |',
                '| --- | --- | --- | --- | --- | --- |',
            ], $rows));

            return array_merge($context, [$storeOutputAs => $markdown]);
        }

        return array_merge($context, [$storeOutputAs => $contacts]);
    }
}
