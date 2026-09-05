<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Crm\System\Models\CrmCompany;

class ListCompaniesAction implements ActionInterface
{
    /**
     * Config keys:
     *   - search          (string) : filter by name or email
     *   - limit           (int)    : max results (default 10, max 50)
     *   - output_format   (string) : 'json' (default) | 'plain'
     *   - store_output_as (string) : context key for the result (default: crm_companies_list)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'crm_companies_list';
        $search = trim((string) ($config['search'] ?? ''));
        $limit = min((int) ($config['limit'] ?? 10), 50);
        $outputFormat = $config['output_format'] ?? 'json';

        $companies = CrmCompany::query()
            ->where('user_id', $workflow->user_id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->withCount(['contacts', 'deals'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CrmCompany $company): array => [
                'id'             => $company->id,
                'name'           => $company->name,
                'industry'       => $company->industry,
                'website'        => $company->website,
                'email'          => $company->email,
                'phone'          => $company->phone,
                'city'           => $company->city,
                'country'        => $company->country,
                'contacts_count' => $company->contacts_count,
                'deals_count'    => $company->deals_count,
            ])
            ->values()
            ->all();

        if ($outputFormat === 'plain') {
            if ($companies === []) {
                return array_merge($context, [$storeOutputAs => '_No companies found._']);
            }

            $rows = array_map(
                fn (array $c): string => sprintf(
                    '| %d | %s | %s | %s | %d | %d |',
                    $c['id'],
                    $c['name'] ?? 'Unnamed',
                    $c['industry'] ?? '-',
                    $c['email'] ?? '-',
                    $c['contacts_count'],
                    $c['deals_count'],
                ),
                $companies,
            );

            $markdown = implode("\n", array_merge([
                '| ID | Name | Industry | Email | Contacts | Deals |',
                '| --- | --- | --- | --- | --- | --- |',
            ], $rows));

            return array_merge($context, [$storeOutputAs => $markdown]);
        }

        return array_merge($context, [$storeOutputAs => $companies]);
    }
}
