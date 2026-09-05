<?php

declare(strict_types=1);

namespace App\Extensions\AIAgentToolCrm\System\Actions\Tools;

use App\Extensions\AIAgent\System\Actions\Contracts\ActionInterface;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflowRun;
use App\Extensions\Crm\System\Models\CrmCompany;
use Exception;

class CreateCompanyAction implements ActionInterface
{
    /**
     * Config keys:
     *   - name            (string, req)
     *   - industry        (string)
     *   - website         (string)
     *   - email           (string)
     *   - phone           (string)
     *   - city            (string)
     *   - country         (string)
     *   - notes           (string)
     *   - store_output_as (string) : context key for the result (default: created_company)
     */
    public function execute(array $config, array $context, AIAgentWorkflow $workflow, AIAgentWorkflowRun $run): array
    {
        $storeOutputAs = $config['store_output_as'] ?? 'created_company';
        $name = trim((string) ($config['name'] ?? ''));

        if ($name === '') {
            throw new Exception('create_company requires name.');
        }

        $company = CrmCompany::query()->create([
            'user_id'  => $workflow->user_id,
            'name'     => $name,
            'industry' => $config['industry'] ?? null,
            'website'  => $config['website'] ?? null,
            'email'    => $config['email'] ?? null,
            'phone'    => $config['phone'] ?? null,
            'city'     => $config['city'] ?? null,
            'country'  => $config['country'] ?? null,
            'notes'    => $config['notes'] ?? null,
        ]);

        $result = [
            'id'       => $company->id,
            'name'     => $company->name,
            'industry' => $company->industry,
            'website'  => $company->website,
            'email'    => $company->email,
        ];

        return array_merge($context, [$storeOutputAs => $result]);
    }
}
